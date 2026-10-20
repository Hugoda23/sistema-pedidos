<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientesYProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->vendedor = User::factory()->create(['name' => 'Ana'])->assignRole('Vendedor');
    }

    public function test_vendedor_crea_cliente_y_queda_como_creador(): void
    {
        $this->actingAs($this->vendedor)->postJson('/api/clientes', [
            'nombre' => 'Carlos Ruiz', 'telefono' => '(502) 5555-0000', 'moto' => 'Honda XR150 2020',
        ])->assertCreated()
            ->assertJsonPath('telefono', '50255550000')
            ->assertJsonPath('creado_por', 'Ana');
    }

    public function test_busca_cliente_por_nombre_telefono_o_moto(): void
    {
        Cliente::factory()->create(['nombre' => 'Luis Gómez', 'telefono' => '41112222', 'moto' => 'Yamaha FZ']);
        Cliente::factory()->create(['nombre' => 'Otro', 'telefono' => '30000000', 'moto' => 'Bajaj']);

        $this->actingAs($this->vendedor);
        foreach (['luis', '4111-2222', 'yamaha'] as $q) {
            $this->getJson('/api/clientes?q='.urlencode($q))->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Luis Gómez');
        }
    }

    public function test_codigo_de_producto_se_normaliza_y_es_unico(): void
    {
        $this->actingAs($this->vendedor)->postJson('/api/productos', ['codigo' => ' lod 123 ', 'nombre' => 'Lodera'])
            ->assertCreated()->assertJsonPath('codigo', 'LOD123');

        $this->postJson('/api/productos', ['codigo' => 'Lod123', 'nombre' => 'Otra'])
            ->assertStatus(422)->assertJsonPath('errors.codigo.0', 'Ya existe un producto con ese código.');
    }

    public function test_buscar_producto_pone_primero_el_codigo_exacto(): void
    {
        Producto::factory()->create(['codigo' => 'XLOD1', 'nombre' => 'Otra cosa']);
        Producto::factory()->create(['codigo' => 'LOD1-B', 'nombre' => 'Lodera B']);
        Producto::factory()->create(['codigo' => 'LOD1', 'nombre' => 'Lodera']);

        $this->actingAs($this->vendedor)->getJson('/api/productos/buscar?q=lod1')
            ->assertOk()->assertJsonPath('0.codigo', 'LOD1')->assertJsonPath('1.codigo', 'LOD1-B')->assertJsonCount(3);
    }

    public function test_proveedor_no_ve_clientes_ni_productos_con_precio(): void
    {
        $prov = User::factory()->create()->assignRole('Proveedor');

        $this->actingAs($prov)->getJson('/api/clientes')->assertForbidden();
        $this->getJson('/api/productos')->assertForbidden();
        $this->getJson('/api/productos/buscar?q=a')->assertForbidden();
    }

    public function test_editar_y_eliminar_cliente(): void
    {
        $c = Cliente::factory()->create();

        $this->actingAs($this->vendedor)->putJson("/api/clientes/{$c->id}", ['nombre' => 'Nuevo nombre'])
            ->assertOk()->assertJsonPath('nombre', 'Nuevo nombre');
        $this->deleteJson("/api/clientes/{$c->id}")->assertNoContent();
    }
}
