<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Database\Seeder;

/** Datos de ejemplo para probar: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(PedidoService $pedidos): void
    {
        $v1 = User::where('email', 'vendedor@pedidos.test')->firstOrFail();
        $v2 = User::firstOrCreate(['email' => 'vendedor2@pedidos.test'], ['name' => 'Vendedor Dos', 'password' => 'password']);
        $v2->syncRoles(['Vendedor']);

        // Teléfonos de ejemplo para ver los botones de WhatsApp.
        $v1->update(['telefono' => '55550001']);
        $v2->update(['telefono' => '55550002']);
        User::where('email', 'proveedor@pedidos.test')->update(['telefono' => '55550009']);

        $ejemplos = [
            [$v1, ['Mario Pérez', '41234567', 'Yamaha FZ 2.0 2022'], 100, [
                ['LOD-XR150', 'Lodera trasera Honda XR150', 2, 185],
                ['PF-FZ22', 'Pastillas de freno delanteras FZ', 1, 95.5],
            ]],
            [$v2, ['Lucía Méndez', '41230000', 'Honda XR150 2020'], 100, [
                ['LOD-XR150', 'Lodera trasera Honda XR150', 1, 190],
                ['CAD-428', 'Cadena 428H 120L', 1, 260],
                ['BUJ-CR7', 'Bujía NGK CR7', 4, 35],
            ]],
        ];

        foreach ($ejemplos as [$vendedor, [$nombre, $tel, $moto], $anticipo, $items]) {
            $cliente = Cliente::firstOrCreate(['nombre' => $nombre], ['telefono' => $tel, 'moto' => $moto, 'creado_por' => $vendedor->id]);
            $pedidos->crear([
                'cliente_id' => $cliente->id,
                'anticipo' => $anticipo,
                'items' => array_map(fn ($i) => ['codigo' => $i[0], 'descripcion' => $i[1], 'cantidad' => $i[2], 'precio' => $i[3]], $items),
            ], $vendedor);
        }
    }
}
