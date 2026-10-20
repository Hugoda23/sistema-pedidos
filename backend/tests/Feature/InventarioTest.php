<?php

namespace Tests\Feature;

use App\Models\InventarioItem;
use App\Models\Lote;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private User $beto;

    private User $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(RolesYPermisosSeeder::class);
        $this->ana = User::factory()->create(['name' => 'Ana', 'telefono' => '55550001'])->assignRole('Vendedor');
        $this->beto = User::factory()->create(['name' => 'Beto'])->assignRole('Vendedor');
        $this->proveedor = User::factory()->create(['name' => 'Prov'])->assignRole('Proveedor');
    }

    private function agregar(User $u, string $codigo, int $cantidad, bool $sumar = false)
    {
        return $this->actingAs($u)->postJson('/api/inventario', [
            'codigo' => $codigo, 'descripcion' => 'Filtro de aire', 'cantidad' => $cantidad, 'sumar' => $sumar,
        ]);
    }

    public function test_todos_los_vendedores_ven_la_misma_lista(): void
    {
        $this->agregar($this->ana, 'fil-1', 2)->assertCreated()->assertJsonPath('codigo', 'FIL-1')->assertJsonPath('agregado_por', 'Ana');
        $this->agregar($this->beto, 'BUJ', 4)->assertCreated();

        foreach ([$this->ana, $this->beto] as $u) {
            $this->actingAs($u)->getJson('/api/inventario')->assertOk()->assertJsonCount(2, 'data');
        }
        $this->actingAs($this->proveedor)->getJson('/api/inventario')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_codigo_repetido_avisa_y_si_confirma_suma_la_cantidad(): void
    {
        $this->agregar($this->ana, 'FIL-1', 2)->assertCreated();

        $this->agregar($this->beto, ' fil-1 ', 3)->assertStatus(409)
            ->assertJsonPath('message', 'El código FIL-1 ya está agregado.')
            ->assertJsonPath('existente.cantidad', 2)
            ->assertJsonPath('existente.agregado_por', 'Ana')
            ->assertJsonPath('existente.en_pedido_abierto', true);
        $this->assertSame(1, InventarioItem::count());

        $this->agregar($this->beto, 'FIL-1', 3, sumar: true)->assertCreated()
            ->assertJsonPath('sumado', true)
            ->assertJsonPath('cantidad', 5)
            ->assertJsonPath('aportes', [['nombre' => 'Ana', 'cantidad' => 2], ['nombre' => 'Beto', 'cantidad' => 3]]);
        $this->assertSame(1, InventarioItem::count());
    }

    public function test_si_el_proveedor_ya_cerro_el_pedido_lo_adicional_va_en_otra_linea(): void
    {
        $this->agregar($this->ana, 'FIL-1', 2)->assertCreated();
        $this->actingAs($this->proveedor)->postJson('/api/proveedor/lotes/cerrar')->assertOk()->assertJsonPath('inventario', 1);

        $this->agregar($this->beto, 'FIL-1', 1)->assertStatus(409)->assertJsonPath('existente.en_pedido_abierto', false);
        $this->agregar($this->beto, 'FIL-1', 1, sumar: true)->assertCreated()->assertJsonPath('sumado', false)->assertJsonPath('cantidad', 1);

        $this->assertSame(2, InventarioItem::count());
        $this->assertSame(2, Lote::count()); // la nueva línea cayó en el pedido nuevo
    }

    public function test_enviado_sale_de_la_lista_y_queda_en_atendidos(): void
    {
        $id = $this->agregar($this->ana, 'FIL-1', 2)->json('id');
        $this->agregar($this->ana, 'BUJ', 1);

        $this->actingAs($this->proveedor)->postJson('/api/inventario/estado', ['ids' => [$id], 'estado' => 'enviado'])
            ->assertOk()->assertJsonPath('whatsapp.enviados', ['Ana']);

        $this->getJson('/api/inventario')->assertJsonCount(1, 'data')->assertJsonPath('data.0.codigo', 'BUJ');
        $this->getJson('/api/inventario?estado=atendidos')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'enviado')->assertJsonPath('data.0.atendido_por', 'Prov');
        $this->assertSame('/inventario', $this->ana->notifications()->first()->data['url']);

        // Ya enviado, se puede volver a pedir el mismo código sin alerta.
        $this->agregar($this->beto, 'FIL-1', 1)->assertCreated();
    }

    public function test_no_disponible_sigue_en_la_lista_con_su_fecha(): void
    {
        $id = $this->agregar($this->ana, 'FIL-1', 2)->json('id');
        $fecha = today()->addDays(4)->toDateString();

        $this->actingAs($this->proveedor)->postJson('/api/inventario/estado', ['ids' => [$id], 'estado' => 'no_disponible'])->assertStatus(422);
        $this->postJson('/api/inventario/estado', ['ids' => [$id], 'estado' => 'no_disponible', 'fecha_ingreso' => $fecha, 'nota' => 'Viene el jueves'])->assertOk();

        $this->getJson('/api/inventario')->assertJsonPath('data.0.estado', 'no_disponible')
            ->assertJsonPath('data.0.fecha_ingreso', $fecha)->assertJsonPath('data.0.nota_proveedor', 'Viene el jueves');
    }

    public function test_vendedor_corrige_o_quita_solo_mientras_el_pedido_esta_abierto(): void
    {
        $id = $this->agregar($this->ana, 'FIL-1', 2)->json('id');

        $this->actingAs($this->beto)->putJson("/api/inventario/{$id}", ['cantidad' => 6])->assertOk()->assertJsonPath('cantidad', 6);

        Lote::query()->update(['estado' => 'cerrado']);
        $this->putJson("/api/inventario/{$id}", ['cantidad' => 1])->assertStatus(422);
        $this->deleteJson("/api/inventario/{$id}")->assertStatus(422);

        Lote::query()->update(['estado' => 'abierto']);
        $this->deleteJson("/api/inventario/{$id}")->assertNoContent();

        // Quitado no se borra: sale de la lista pero queda el movimiento, y el código se puede volver a pedir.
        $this->getJson('/api/inventario')->assertJsonCount(0, 'data');
        $this->assertSame('anulado', InventarioItem::find($id)->estado->value);
        $this->assertSame(['agregado', 'editado', 'quitado'], InventarioItem::find($id)->historial->pluck('accion')->all());
        $this->assertSame('Cantidad 2 → 6', InventarioItem::find($id)->historial[1]->nota);
        $this->agregar($this->ana, 'FIL-1', 1)->assertCreated();
    }

    public function test_lista_de_pedidos_y_reporte_con_todos_los_movimientos(): void
    {
        $id = $this->agregar($this->ana, 'FIL-1', 2)->json('id');
        $this->agregar($this->beto, 'FIL-1', 3, sumar: true);
        $bujia = $this->agregar($this->beto, 'BUJ', 1)->json('id');
        $this->actingAs($this->beto)->deleteJson("/api/inventario/{$bujia}")->assertNoContent();
        $this->actingAs($this->proveedor)->postJson('/api/proveedor/lotes/cerrar')->assertOk();
        $this->postJson('/api/inventario/estado', ['ids' => [$id], 'estado' => 'enviado'])->assertOk();

        $lote = Lote::first();
        $this->getJson('/api/inventario/pedidos')->assertOk()
            ->assertJsonPath('0.id', $lote->id)->assertJsonPath('0.abierto', false)
            ->assertJsonPath('0.cerrado_por', 'Prov')
            ->assertJsonPath('0.repuestos', 1)->assertJsonPath('0.unidades', 5)->assertJsonPath('0.enviados', 1);

        $html = $this->actingAs($this->ana)->get("/api/inventario/pedidos/{$lote->id}/reporte")->assertOk()->getContent();
        foreach (["#{$lote->id}", 'Motorepuestos Mota', 'FIL-1', 'Ana (2), Beto (3)', 'Agregado', 'Sumó cantidad', 'Quitado', 'Enviado', 'Prov', 'Beto'] as $t) {
            $this->assertStringContainsString($t, $html, "Falta «{$t}»");
        }

        $pdf = $this->get("/api/inventario/pedidos/{$lote->id}/reporte?formato=pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString("inventario-pedido-{$lote->id}.pdf", $pdf->headers->get('content-disposition'));
    }

    public function test_permisos(): void
    {
        $id = $this->agregar($this->ana, 'FIL-1', 2)->json('id');

        // El vendedor no marca enviado; el proveedor no agrega.
        $this->actingAs($this->ana)->postJson('/api/inventario/estado', ['ids' => [$id], 'estado' => 'enviado'])->assertForbidden();
        $this->actingAs($this->proveedor)->postJson('/api/inventario', ['codigo' => 'X', 'descripcion' => 'x', 'cantidad' => 1])->assertForbidden();
        $this->getJson('/api/inventario')->assertJsonPath('data.0.acciones', ['enviado', 'no_disponible', 'descontinuado'])
            ->assertJsonPath('data.0.editable', false);
    }

    public function test_cerrar_pedido_solo_con_inventario(): void
    {
        $this->agregar($this->ana, 'FIL-1', 2)->assertCreated();

        $this->actingAs($this->proveedor)->getJson('/api/proveedor/lotes')->assertJsonPath('0.inventario', 1)->assertJsonPath('0.lineas', 0);
        $this->getJson('/api/inventario/pedidos')->assertJsonPath('0.repuestos', 1)->assertJsonPath('0.lineas_clientes', 0);
        $this->postJson('/api/proveedor/lotes/cerrar')->assertOk();
    }

    public function test_exportar_excel(): void
    {
        $this->agregar($this->ana, 'FIL-1', 2);

        $res = $this->actingAs($this->proveedor)->get('/api/inventario/exportar')->assertOk();
        $this->assertStringContainsString('inventario-', $res->headers->get('content-disposition'));
    }
}
