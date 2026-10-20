<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientesTest extends TestCase
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

    public function test_proveedor_no_ve_clientes(): void
    {
        $prov = User::factory()->create()->assignRole('Proveedor');

        $this->actingAs($prov)->getJson('/api/clientes')->assertForbidden();
    }

    public function test_editar_y_eliminar_cliente(): void
    {
        $c = Cliente::factory()->create();

        $this->actingAs($this->vendedor)->putJson("/api/clientes/{$c->id}", ['nombre' => 'Nuevo nombre'])
            ->assertOk()->assertJsonPath('nombre', 'Nuevo nombre');
        $this->deleteJson("/api/clientes/{$c->id}")->assertNoContent();
    }
}
