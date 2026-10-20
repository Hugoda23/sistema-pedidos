<?php

namespace Tests\Feature;

use App\Enums\EstadoItem;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidosTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->vendedor = User::factory()->create()->assignRole('Vendedor');
        $this->cliente = Cliente::factory()->create(['nombre' => 'Carlos Ruiz']);
    }

    private function crearPedido(array $extra = [], ?User $como = null): array
    {
        return $this->actingAs($como ?? $this->vendedor)->postJson('/api/pedidos', $extra + [
            'cliente_id' => $this->cliente->id,
            'anticipo' => 100,
            'items' => [
                ['codigo' => 'lod-1', 'descripcion' => 'Lodera trasera', 'cantidad' => 2, 'precio' => 150],
                ['codigo' => 'PF-9', 'descripcion' => 'Pastillas', 'cantidad' => 1, 'precio' => 95.5],
            ],
        ])->assertCreated()->json();
    }

    private function item(array $pedido, int $i = 0): PedidoItem
    {
        return PedidoItem::findOrFail($pedido['items'][$i]['id']);
    }

    public function test_crea_pedido_con_varias_lineas_y_anticipo(): void
    {
        $p = $this->crearPedido();

        $this->assertSame('P-'.str_pad((string) $p['id'], 5, '0', STR_PAD_LEFT), $p['numero']);
        $this->assertSame('395.50', $p['total']);
        $this->assertSame('100.00', $p['pagado']);
        $this->assertSame('295.50', $p['saldo']);
        $this->assertSame('pendiente', $p['estado']);
        $this->assertSame('anticipo', $p['pagos'][0]['tipo']);
        $this->assertSame('LOD-1', $p['items'][0]['codigo']);

        // No hay catálogo: el código y nombre viven solo en la línea. Todo cae en el lote abierto.
        $this->assertSame('Lodera trasera', $p['items'][0]['descripcion']);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('productos'));
        $this->assertSame(1, Lote::count());
        $this->assertSame(2, PedidoItem::where('lote_id', Lote::first()->id)->count());
    }

    public function test_codigo_se_normaliza_y_el_nombre_es_obligatorio(): void
    {
        $this->actingAs($this->vendedor)->postJson('/api/pedidos', [
            'cliente_id' => $this->cliente->id,
            'items' => [['codigo' => ' lod 9 ', 'descripcion' => 'Lodera', 'cantidad' => 1, 'precio' => 10]],
        ])->assertCreated()->assertJsonPath('items.0.codigo', 'LOD9');

        $this->postJson('/api/pedidos', [
            'cliente_id' => $this->cliente->id,
            'items' => [['codigo' => 'X1', 'descripcion' => '', 'cantidad' => 1, 'precio' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.descripcion');
    }

    public function test_valida_lineas_y_anticipo(): void
    {
        $this->actingAs($this->vendedor)->postJson('/api/pedidos', ['cliente_id' => $this->cliente->id, 'items' => []])
            ->assertStatus(422)->assertJsonPath('errors.items.0', 'Agrega al menos un producto.');

        $this->postJson('/api/pedidos', [
            'cliente_id' => $this->cliente->id, 'anticipo' => 500,
            'items' => [['codigo' => 'A', 'descripcion' => 'x', 'cantidad' => 1, 'precio' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('anticipo');
        $this->assertSame(0, Pedido::count());
    }

    public function test_vendedor_ve_solo_sus_pedidos_y_admin_todos(): void
    {
        $otro = User::factory()->create()->assignRole('Vendedor');
        $mio = $this->crearPedido();
        $ajeno = $this->crearPedido(como: $otro);

        $this->actingAs($this->vendedor)->getJson('/api/pedidos')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mio['id']);
        $this->getJson("/api/pedidos/{$ajeno['id']}")->assertNotFound();

        $admin = User::factory()->create()->assignRole('Administrador');
        $this->actingAs($admin)->getJson('/api/pedidos')->assertJsonCount(2, 'data');
    }

    public function test_busca_por_cliente_numero_o_codigo(): void
    {
        $p = $this->crearPedido();
        $this->actingAs($this->vendedor);

        foreach (['carlos', $p['numero'], 'pf-9'] as $q) {
            $this->getJson('/api/pedidos?q='.$q)->assertJsonCount(1, 'data');
        }
        $this->getJson('/api/pedidos?q=nadie')->assertJsonCount(0, 'data');
    }

    public function test_flujo_enviado_recibido_entregado_actualiza_estado_del_pedido(): void
    {
        $p = $this->crearPedido();
        [$a, $b] = [$this->item($p, 0), $this->item($p, 1)];

        // El vendedor no puede marcar enviado: eso lo hace el proveedor.
        $this->postJson("/api/pedido-items/{$a->id}/estado", ['estado' => 'enviado'])->assertStatus(422);

        $a->cambiarEstado(EstadoItem::Enviado, null);
        $b->cambiarEstado(EstadoItem::Enviado, null);

        $this->postJson("/api/pedido-items/{$a->id}/estado", ['estado' => 'recibido'])->assertOk()->assertJsonPath('estado', 'en_proceso');
        $this->postJson("/api/pedido-items/{$b->id}/estado", ['estado' => 'recibido'])->assertOk()->assertJsonPath('estado', 'listo');
        $this->postJson("/api/pedido-items/{$a->id}/estado", ['estado' => 'entregado'])->assertOk();
        $res = $this->postJson("/api/pedido-items/{$b->id}/estado", ['estado' => 'entregado'])->assertOk()->assertJsonPath('estado', 'entregado');

        $this->assertCount(4, $res->json('items.0.historial')); // solicitado, enviado, recibido, entregado
    }

    public function test_anular_linea_la_saca_del_total(): void
    {
        $p = $this->crearPedido();

        $this->postJson("/api/pedido-items/{$p['items'][1]['id']}/estado", ['estado' => 'anulado', 'nota' => 'Ya no lo quiere'])
            ->assertOk()->assertJsonPath('total', '300.00')->assertJsonPath('saldo', '200.00');
    }

    public function test_editar_y_quitar_lineas_solo_mientras_el_lote_esta_abierto(): void
    {
        $p = $this->crearPedido();
        $a = $this->item($p);

        $this->putJson("/api/pedido-items/{$a->id}", ['cantidad' => 3])->assertOk()->assertJsonPath('total', '545.50');
        $this->postJson("/api/pedidos/{$p['id']}/items", ['codigo' => 'BUJ', 'descripcion' => 'Bujía', 'cantidad' => 1, 'precio' => 30])
            ->assertCreated()->assertJsonCount(3, 'items');
        $this->deleteJson("/api/pedido-items/{$a->id}")->assertOk()->assertJsonCount(2, 'items');

        // El proveedor cierra el lote: ya no se cambia cantidad ni se quita, pero el precio sí.
        Lote::first()->update(['estado' => 'cerrado']);
        $b = $this->item($p, 1);
        $this->putJson("/api/pedido-items/{$b->id}", ['cantidad' => 5])->assertStatus(422);
        $this->putJson("/api/pedido-items/{$b->id}", ['precio' => 80])->assertOk();
        $this->deleteJson("/api/pedido-items/{$b->id}")->assertStatus(422);

        // Una línea nueva cae en un lote nuevo.
        $this->postJson("/api/pedidos/{$p['id']}/items", ['codigo' => 'X1', 'descripcion' => 'x', 'cantidad' => 1, 'precio' => 1])->assertCreated();
        $this->assertSame(2, Lote::count());
    }

    public function test_abonos_hasta_el_saldo(): void
    {
        $p = $this->crearPedido();

        $this->postJson("/api/pedidos/{$p['id']}/pagos", ['monto' => 300])->assertStatus(422);
        $this->postJson("/api/pedidos/{$p['id']}/pagos", ['monto' => 295.5])
            ->assertCreated()->assertJsonPath('saldo', '0.00')->assertJsonPath('pagos.1.tipo', 'abono');

        // Eliminar pagos requiere permiso de anular.
        $this->deleteJson("/api/pagos/{$this->item($p)->pedido->pagos()->first()->id}")->assertForbidden();
    }

    public function test_anular_pedido_requiere_permiso_y_que_nada_se_haya_enviado(): void
    {
        $p = $this->crearPedido();
        $this->postJson("/api/pedidos/{$p['id']}/anular", ['motivo' => 'x'])->assertForbidden();

        $admin = User::factory()->create()->assignRole('Administrador');
        $this->item($p)->cambiarEstado(EstadoItem::Enviado, null);
        $this->actingAs($admin)->postJson("/api/pedidos/{$p['id']}/anular", ['motivo' => 'Cliente canceló'])->assertStatus(422);

        $this->item($p)->update(['estado' => 'pendiente']);
        $this->postJson("/api/pedidos/{$p['id']}/anular", ['motivo' => 'Cliente canceló'])
            ->assertOk()->assertJsonPath('estado', 'anulado')->assertJsonPath('total', '0.00')
            ->assertJsonPath('anulado.motivo', 'Cliente canceló');

        $this->postJson("/api/pedidos/{$p['id']}/items", ['codigo' => 'X', 'descripcion' => 'x', 'cantidad' => 1, 'precio' => 1])->assertStatus(422);
    }

    public function test_cliente_con_pedidos_no_se_elimina(): void
    {
        $this->crearPedido();
        $this->deleteJson("/api/clientes/{$this->cliente->id}")->assertStatus(422);
    }

    public function test_proveedor_no_accede_a_pedidos(): void
    {
        $p = $this->crearPedido();
        $prov = User::factory()->create()->assignRole('Proveedor');

        $this->actingAs($prov)->getJson('/api/pedidos')->assertForbidden();
        $this->getJson("/api/pedidos/{$p['id']}")->assertForbidden();
    }
}
