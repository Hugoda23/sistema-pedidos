<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesYPermisosSeeder::class);

        $usuarios = [
            ['Administrador', 'admin@pedidos.test', 'Administrador'],
            ['Vendedor Uno', 'vendedor@pedidos.test', 'Vendedor'],
            ['Proveedor', 'proveedor@pedidos.test', 'Proveedor'],
        ];

        foreach ($usuarios as [$nombre, $email, $rol]) {
            User::firstOrCreate(['email' => $email], ['name' => $nombre, 'password' => 'password'])
                ->syncRoles([$rol]);
        }
    }
}
