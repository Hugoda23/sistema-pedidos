<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
    }

    public function test_inicia_sesion_y_devuelve_rol_y_permisos(): void
    {
        $user = User::factory()->create(['email' => 'vende@x.test']);
        $user->assignRole('Vendedor');

        $res = $this->postJson('/api/login', ['email' => 'vende@x.test', 'password' => 'password']);

        $res->assertOk()
            ->assertJsonPath('user.roles', ['Vendedor'])
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'permisos']]);
        $this->assertContains('pedidos.gestionar', $res->json('user.permisos'));
        $this->assertNotContains('proveedor.ver', $res->json('user.permisos'));
    }

    public function test_rechaza_contrasena_incorrecta(): void
    {
        User::factory()->create(['email' => 'a@x.test']);

        $this->postJson('/api/login', ['email' => 'a@x.test', 'password' => 'mala'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_usuario_inactivo_no_entra(): void
    {
        User::factory()->create(['email' => 'b@x.test', 'activo' => false]);

        $this->postJson('/api/login', ['email' => 'b@x.test', 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_me_requiere_token_y_logout_lo_invalida(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();

        User::factory()->create(['email' => 'c@x.test'])->assignRole('Proveedor');
        $token = $this->postJson('/api/login', ['email' => 'c@x.test', 'password' => 'password'])->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.roles', ['Proveedor']);
        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_usuario_desactivado_con_sesion_abierta_queda_fuera(): void
    {
        $user = User::factory()->create(['email' => 'd@x.test']);
        $token = $this->postJson('/api/login', ['email' => 'd@x.test', 'password' => 'password'])->json('token');

        $user->update(['activo' => false]);

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }
}
