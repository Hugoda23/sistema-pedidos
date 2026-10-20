<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    /** Roles que usa el sistema por nombre: se pueden ajustar, no borrar ni renombrar. */
    private const BASE = ['Administrador', 'Vendedor', 'Proveedor'];

    public function index(): JsonResponse
    {
        // withCount('users') no funciona con spatie (la relación depende del guard).
        $roles = Role::with('permissions:id,name')
            ->addSelect(['users_count' => DB::table('model_has_roles')->selectRaw('count(*)')->whereColumn('role_id', 'roles.id')])
            ->orderBy('id')->get();

        return response()->json([
            'roles' => $roles->map(fn (Role $r) => self::datos($r)),
            'permisos' => $this->catalogo(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $rol = Role::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $rol->syncPermissions($datos['permisos']);

        return response()->json(self::datos($rol->load('permissions')), 201);
    }

    public function update(Request $request, Role $rol): JsonResponse
    {
        if ($rol->name === 'Administrador') {
            throw ValidationException::withMessages(['name' => 'El rol Administrador siempre tiene todos los permisos.']);
        }

        $datos = $this->validar($request, $rol);

        if (in_array($rol->name, self::BASE, true) && $datos['name'] !== $rol->name) {
            throw ValidationException::withMessages(['name' => 'Los roles base no se pueden renombrar.']);
        }

        $rol->update(['name' => $datos['name']]);
        $rol->syncPermissions($datos['permisos']);

        return response()->json(self::datos($rol->load('permissions')));
    }

    public function destroy(Role $rol): JsonResponse
    {
        if (in_array($rol->name, self::BASE, true)) {
            throw ValidationException::withMessages(['name' => 'Los roles base no se pueden eliminar.']);
        }
        if ($rol->users()->exists()) {
            throw ValidationException::withMessages(['name' => 'Hay usuarios con este rol; cámbiales el rol antes de eliminarlo.']);
        }

        $rol->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request, ?Role $rol = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($rol)],
            'permisos' => ['array'],
            'permisos.*' => ['string', Rule::exists(Permission::class, 'name')],
        ]) + ['permisos' => []];
    }

    /** Permisos agrupados por módulo (lo que va antes del punto). */
    private function catalogo(): array
    {
        $modulos = ['usuarios' => 'Usuarios', 'roles' => 'Roles', 'clientes' => 'Clientes',
            'pedidos' => 'Pedidos', 'proveedor' => 'Proveedor', 'reportes' => 'Reportes'];

        return collect(config('permisos.permisos'))
            ->map(fn ($desc, $nombre) => ['name' => $nombre, 'descripcion' => $desc])
            ->groupBy(fn ($p) => $modulos[strtok($p['name'], '.')] ?? 'Otros')
            ->map(fn ($items, $modulo) => ['modulo' => $modulo, 'permisos' => $items->values()])
            ->values()
            ->all();
    }

    private static function datos(Role $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'base' => in_array($r->name, self::BASE, true),
            'usuarios' => (int) ($r->users_count ?? $r->users()->count()),
            'permisos' => $r->permissions->pluck('name')->sort()->values(),
        ];
    }
}
