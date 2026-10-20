<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lote;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private User $proveedor;

    private array $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->vendedor = User::factory()->create(['name' => 'Ana'])->assignRole('Vendedor');
        $this->proveedor = User::factory()->create()->assignRole('Proveedor');
        $this->pedido = $this->pedir([
            ['codigo' => 'LOD-1', 'descripcion' => 'Lodera', 'cantidad' => 2, 'precio' => 150],
            ['codigo' => 'PF-9', 'descripcion' => 'Pastillas', 'cantidad' => 1, 'precio' => 95],
        ]);
    }

    private function pedir(array $items): array
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Cliente Secreto']);

        return $this->actingAs($this->vendedor)->postJson('/api/pedidos', ['cliente_id' => $cliente->id, 'items' => $items])
            ->assertCreated()->json();
    }

    private function ids(int ...$pos): array
    {
        return array_map(fn ($i) => $this->pedido['items'][$i]['id'], $pos);
    }

    public function test_proveedor_ve_las_lineas_sin_cliente_ni_precio(): void
    {
        $res = $this->actingAs($this->proveedor)->getJson('/api/proveedor/items')->assertOk()->assertJsonCount(2, 'data');

        $fila = $res->json('data.0');
        $this->assertSame('Ana', $fila['vendedor']);
        $this->assertSame(2, $fila['cantidad']);
        $this->assertArrayNotHasKey('precio', $fila);
        $this->assertArrayNotHasKey('cliente', $fila);
        $this->assertStringNotContainsString('Cliente Secreto', $res->getContent());
        $this->assertStringNotContainsString('150.00', $res->getContent());
    }

    public function test_vendedor_no_entra_a_la_tabla_del_proveedor(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/proveedor/items')->assertForbidden();
        $this->postJson('/api/proveedor/lotes/cerrar')->assertForbidden();
    }

    public function test_marcar_enviado_en_bloque_actualiza_el_pedido(): void
    {
        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(0, 1), 'estado' => 'enviado'])
            ->assertOk()->assertJsonPath('actualizados', 2);

        $this->actingAs($this->vendedor)->getJson("/api/pedidos/{$this->pedido['id']}")
            ->assertJsonPath('estado', 'en_proceso')
            ->assertJsonPath('items.0.estado', 'enviado')
            ->assertJsonPath('items.0.acciones', ['recibido']);

        // Ya no está "por atender".
        $this->actingAs($this->proveedor)->getJson('/api/proveedor/items')->assertJsonCount(0, 'data');
        $this->getJson('/api/proveedor/items?estado=enviado')->assertJsonCount(2, 'data');
    }

    public function test_no_disponible_pide_fecha_futura_y_se_guarda(): void
    {
        $this->actingAs($this->proveedor);
        $this->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(0), 'estado' => 'no_disponible'])
            ->assertStatus(422)->assertJsonPath('errors.fecha_ingreso.0', 'Indica la fecha aproximada de ingreso.');
        $this->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(0), 'estado' => 'no_disponible', 'fecha_ingreso' => today()->subDay()->toDateString()])
            ->assertStatus(422);

        $fecha = today()->addDays(10)->toDateString();
        $this->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(0), 'estado' => 'no_disponible', 'fecha_ingreso' => $fecha, 'nota' => 'Viene de fábrica'])
            ->assertOk();

        $item = PedidoItem::find($this->ids(0)[0]);
        $this->assertSame($fecha, $item->fecha_ingreso->toDateString());
        $this->assertSame('Viene de fábrica', $item->nota_proveedor);

        // Sigue por atender: después lo puede marcar enviado y la fecha se limpia.
        $this->getJson('/api/proveedor/items')->assertJsonCount(2, 'data');
        $this->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(0), 'estado' => 'enviado'])->assertOk();
        $this->assertNull(PedidoItem::find($this->ids(0)[0])->fecha_ingreso);
    }

    public function test_filtro_revisar_muestra_fechas_vencidas_primero(): void
    {
        PedidoItem::whereKey($this->ids(1))->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()]);

        $this->actingAs($this->proveedor)->getJson('/api/proveedor/items')
            ->assertJsonPath('data.0.id', $this->ids(1)[0])->assertJsonPath('data.0.revisar_ingreso', true);
        $this->getJson('/api/proveedor/items?estado=revisar')->assertJsonCount(1, 'data');
    }

    public function test_descontinuado_marca_el_producto_y_saca_la_linea_del_total(): void
    {
        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(1), 'estado' => 'descontinuado', 'nota' => 'Ya no se fabrica'])
            ->assertOk();

        $this->assertTrue(Producto::where('codigo', 'PF-9')->value('descontinuado'));
        $this->actingAs($this->vendedor)->getJson("/api/pedidos/{$this->pedido['id']}")->assertJsonPath('total', '300.00');
    }

    public function test_transicion_invalida_no_cambia_ninguna_linea(): void
    {
        PedidoItem::whereKey($this->ids(1))->update(['estado' => 'anulado']);

        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(0, 1), 'estado' => 'enviado'])
            ->assertStatus(422);
        $this->assertSame('pendiente', PedidoItem::find($this->ids(0)[0])->estado->value);
    }

    public function test_cerrar_lote_y_lo_nuevo_cae_en_otro(): void
    {
        $this->actingAs($this->proveedor)->postJson('/api/proveedor/lotes/cerrar')->assertOk()->assertJsonPath('lineas', 2);
        $this->postJson('/api/proveedor/lotes/cerrar')->assertStatus(422); // no hay lote con líneas

        $this->pedir([['codigo' => 'BUJ', 'descripcion' => 'Bujía', 'cantidad' => 4, 'precio' => 30]]);

        $lotes = $this->actingAs($this->proveedor)->getJson('/api/proveedor/lotes')->assertOk()->json();
        $this->assertSame(['abierto', 'cerrado'], array_column($lotes, 'estado'));
        $this->assertSame(4, $lotes[0]['unidades']);
        $this->assertSame($this->proveedor->name, $lotes[1]['cerrado_por']);

        $this->getJson("/api/proveedor/items?lote_id={$lotes[0]['id']}")->assertJsonCount(1, 'data');
    }

    public function test_consolidado_suma_por_codigo(): void
    {
        $this->pedir([['codigo' => 'lod-1', 'descripcion' => 'Lodera', 'cantidad' => 3, 'precio' => 140]]);

        $filas = collect($this->actingAs($this->proveedor)->getJson('/api/proveedor/consolidado')->assertOk()->json())->keyBy('codigo');
        $this->assertEquals(5, $filas['LOD-1']['cantidad']);
        $this->assertEquals(2, $filas['LOD-1']['lineas']);
        $this->assertArrayNotHasKey('precio', $filas['LOD-1']);
    }

    public function test_linea_anulada_por_el_vendedor_desaparece_para_el_proveedor(): void
    {
        $this->actingAs($this->vendedor)->postJson("/api/pedido-items/{$this->ids(0)[0]}/estado", ['estado' => 'anulado'])->assertOk();

        $this->actingAs($this->proveedor)->getJson('/api/proveedor/items')->assertJsonCount(1, 'data');
        $this->getJson('/api/proveedor/items?estado=todos')->assertJsonCount(1, 'data');
    }
}
