<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('permisos.permisos') as $nombre => $descripcion) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        foreach (config('permisos.roles') as $rol => $permisos) {
            $role = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
            // Solo se asignan al crearse: si el admin ya los ajustó, no se pisan.
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permisos === '*' ? array_keys(config('permisos.permisos')) : $permisos);
            }
        }

        // El Administrador siempre tiene todo, incluidos permisos nuevos.
        Role::findByName('Administrador')->syncPermissions(array_keys(config('permisos.permisos')));
    }
}
