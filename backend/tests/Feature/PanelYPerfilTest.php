<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PedidoItem;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelYPerfilTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->vendedor = User::factory()->create()->assignRole('Vendedor');
    }

    private function pedido(User $v, float $precio, float $anticipo): array
    {
        return $this->actingAs($v)->postJson('/api/pedidos', [
            'cliente_id' => Cliente::factory()->create()->id, 'anticipo' => $anticipo,
            'items' => [['codigo' => 'A'.$precio, 'descripcion' => 'x', 'cantidad' => 1, 'precio' => $precio]],
        ])->json();
    }

    public function test_panel_del_vendedor_cuenta_solo_lo_suyo(): void
    {
        $p = $this->pedido($this->vendedor, 300, 100);
        $this->pedido(User::factory()->create()->assignRole('Vendedor'), 999, 0);
        PedidoItem::whereKey($p['items'][0]['id'])->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()]);

        $this->actingAs($this->vendedor)->getJson('/api/panel')->assertOk()
            ->assertJsonPath('ventas.activos', 1)
            ->assertJsonPath('ventas.por_cobrar', '200.00')
            ->assertJsonPath('ventas.mes_total', '300.00')
            ->assertJsonPath('ventas.sin_existencia', 1)
            ->assertJsonPath('ventas.ingreso_cumplido', 1)
            ->assertJsonPath('ventas.lista_sin_existencia.0.cumplida', true)
            ->assertJsonMissingPath('proveedor');
    }

    public function test_panel_del_proveedor_sin_dinero(): void
    {
        $this->pedido($this->vendedor, 300, 100);
        $prov = User::factory()->create()->assignRole('Proveedor');

        $res = $this->actingAs($prov)->getJson('/api/panel')->assertOk()
            ->assertJsonPath('proveedor.por_atender', 1)
            ->assertJsonPath('proveedor.abierto.lineas', 1)
            ->assertJsonMissingPath('ventas');
        $this->assertStringNotContainsString('300', $res->getContent());
    }

    public function test_perfil_cambia_telefono_y_contrasena_con_la_actual(): void
    {
        $token = $this->vendedor->createToken('web')->plainTextToken;
        $this->vendedor->createToken('otro');

        $this->withToken($token)->putJson('/api/perfil', ['name' => 'Ana', 'telefono' => '5555 1234', 'password' => 'nuevaclave1', 'password_confirmation' => 'nuevaclave1'])
            ->assertStatus(422)->assertJsonValidationErrors('password_actual');
        $this->withToken($token)->putJson('/api/perfil', ['name' => 'Ana', 'telefono' => '5555 1234', 'password_actual' => 'mala', 'password' => 'nuevaclave1', 'password_confirmation' => 'nuevaclave1'])
            ->assertStatus(422)->assertJsonPath('errors.password_actual.0', 'La contraseña actual no es correcta.');

        $this->withToken($token)->putJson('/api/perfil', ['name' => 'Ana', 'telefono' => '5555 1234', 'password_actual' => 'password', 'password' => 'nuevaclave1', 'password_confirmation' => 'nuevaclave1'])
            ->assertOk()->assertJsonPath('user.telefono', '55551234');

        $this->assertSame(1, $this->vendedor->tokens()->count()); // la otra sesión se cerró
        $this->postJson('/api/login', ['email' => $this->vendedor->email, 'password' => 'nuevaclave1'])->assertOk();
    }
}
