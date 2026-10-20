<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsuariosYRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Admin'])->assignRole('Administrador');
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    public function test_admin_crea_usuario_con_rol(): void
    {
        $this->actingAs($this->admin)->postJson('/api/usuarios', [
            'name' => 'Juan Vendedor', 'email' => 'JUAN@x.test', 'telefono' => '5555-1234',
            'rol' => 'Vendedor', 'password' => 'secreto123',
        ])->assertCreated()
            ->assertJsonPath('email', 'juan@x.test')
            ->assertJsonPath('telefono', '55551234')
            ->assertJsonPath('rol', 'Vendedor');

        $this->postJson('/api/login', ['email' => 'juan@x.test', 'password' => 'secreto123'])->assertOk();
    }

    public function test_valida_correo_unico_y_telefono(): void
    {
        $this->actingAs($this->admin)->postJson('/api/usuarios', [
            'name' => 'X', 'email' => $this->admin->email, 'telefono' => '12', 'rol' => 'Vendedor', 'password' => 'secreto123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email', 'telefono']);
    }

    public function test_lista_filtra_por_texto_y_rol(): void
    {
        $this->usuario('Vendedor')->update(['name' => 'Pedro Pérez']);
        $this->usuario('Proveedor');

        $this->actingAs($this->admin)->getJson('/api/usuarios?q=pedro')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pedro Pérez');
        $this->getJson('/api/usuarios?rol=Proveedor')->assertJsonCount(1, 'data');
    }

    public function test_editar_sin_contrasena_la_conserva_y_con_contrasena_cierra_sesiones(): void
    {
        $v = $this->usuario('Vendedor');
        $v->createToken('web');
        $base = ['name' => 'Nuevo', 'email' => $v->email, 'rol' => 'Vendedor', 'activo' => true];

        $this->actingAs($this->admin)->putJson("/api/usuarios/{$v->id}", $base)->assertOk()->assertJsonPath('name', 'Nuevo');
        $this->assertSame(1, $v->tokens()->count());

        $this->putJson("/api/usuarios/{$v->id}", $base + ['password' => 'otraclave1'])->assertOk();
        $this->assertSame(0, $v->tokens()->count());
    }

    public function test_no_puede_desactivarse_ni_quitar_el_ultimo_admin(): void
    {
        $datos = ['name' => 'Admin', 'email' => $this->admin->email, 'rol' => 'Administrador'];

        $this->actingAs($this->admin)->putJson("/api/usuarios/{$this->admin->id}", $datos + ['activo' => false])
            ->assertStatus(422)->assertJsonValidationErrors('activo');
        $this->putJson("/api/usuarios/{$this->admin->id}", ['rol' => 'Vendedor'] + $datos)
            ->assertStatus(422)->assertJsonValidationErrors('rol');
    }

    public function test_vendedor_y_proveedor_no_gestionan_usuarios(): void
    {
        foreach (['Vendedor', 'Proveedor'] as $rol) {
            $this->actingAs($this->usuario($rol))->getJson('/api/usuarios')
                ->assertForbidden()->assertJsonPath('message', 'No tienes permiso para esta acción.');
        }
    }

    public function test_crear_editar_y_eliminar_rol(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/roles', [
            'name' => 'Supervisor', 'permisos' => ['pedidos.ver', 'pedidos.ver_todos'],
        ])->assertCreated()->json('id');

        $this->putJson("/api/roles/{$id}", ['name' => 'Supervisor', 'permisos' => ['pedidos.ver']])
            ->assertOk()->assertJsonPath('permisos', ['pedidos.ver']);

        $u = $this->usuario('Supervisor');
        $this->deleteJson("/api/roles/{$id}")->assertStatus(422);

        $u->syncRoles(['Vendedor']);
        $this->deleteJson("/api/roles/{$id}")->assertNoContent();
    }

    public function test_cambiar_permisos_de_un_rol_afecta_a_sus_usuarios(): void
    {
        $v = $this->usuario('Vendedor');
        $vendedor = Role::findByName('Vendedor');

        $this->actingAs($this->admin)->putJson("/api/roles/{$vendedor->id}", [
            'name' => 'Vendedor', 'permisos' => ['pedidos.ver', 'pedidos.ver_todos', 'usuarios.ver'],
        ])->assertOk();

        $this->actingAs($v->fresh())->getJson('/api/usuarios')->assertOk();
    }

    public function test_roles_base_y_administrador_protegidos(): void
    {
        $admin = Role::findByName('Administrador');
        $prov = Role::findByName('Proveedor');

        $this->actingAs($this->admin)->putJson("/api/roles/{$admin->id}", ['name' => 'Administrador', 'permisos' => []])->assertStatus(422);
        $this->putJson("/api/roles/{$prov->id}", ['name' => 'Distribuidor', 'permisos' => []])->assertStatus(422);
        $this->deleteJson("/api/roles/{$prov->id}")->assertStatus(422);
    }

    public function test_listado_de_roles_trae_catalogo_agrupado(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/api/roles')->assertOk();

        $this->assertContains('Proveedor', collect($res->json('permisos'))->pluck('modulo'));
        $this->assertSame(3, count($res->json('roles')));
    }
}
