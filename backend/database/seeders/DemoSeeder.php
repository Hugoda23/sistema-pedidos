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

        $ejemplos[] = [$v1, ['Jorge Castillo', '42221111', 'Suzuki GN125 2019'], 150, [
            ['FIL-GN125', 'Filtro de aire GN125', 1, 75],
            ['ESP-UNI', 'Espejo retrovisor universal', 2, 45],
        ]];
        $ejemplos[] = [$v1, ['Ana Ruiz', '43334444', 'Bajaj Pulsar 200 2021'], 320, [
            ['KIT-ARR-200', 'Kit de arrastre Pulsar 200', 1, 320],
        ]];
        $ejemplos[] = [$v2, ['Pedro Gómez', '45556666', 'Italika FT150 2018'], 0, [
            ['CDI-FT150', 'CDI Italika FT150', 1, 210],
        ]];

        $creados = [];
        foreach ($ejemplos as [$vendedor, [$nombre, $tel, $moto], $anticipo, $items]) {
            $cliente = Cliente::firstOrCreate(['nombre' => $nombre], ['telefono' => $tel, 'moto' => $moto, 'creado_por' => $vendedor->id]);
            $creados[] = $pedidos->crear([
                'cliente_id' => $cliente->id,
                'anticipo' => $anticipo,
                'items' => array_map(fn ($i) => ['codigo' => $i[0], 'descripcion' => $i[1], 'cantidad' => $i[2], 'precio' => $i[3]], $items),
            ], $vendedor);
        }

        // Estados variados para ver el panel: el proveedor cerró el primer pedido y fue respondiendo.
        $prov = User::where('email', 'proveedor@pedidos.test')->first();
        \App\Models\Lote::where('estado', 'abierto')->update(['estado' => 'cerrado', 'cerrado_por' => $prov->id, 'cerrado_en' => now()]);
        $item = fn ($p, $i) => $p->items()->orderBy('id')->get()[$i];
        $E = \App\Enums\EstadoItem::class;

        [$mario, $lucia, $jorge, $ana] = $creados;
        $item($mario, 0)->cambiarEstado($E::Enviado, $prov);
        $item($mario, 1)->cambiarEstado($E::NoDisponible, $prov, 'Viene de fábrica', today()->toDateString());
        $item($lucia, 1)->cambiarEstado($E::NoDisponible, $prov, 'Próximo contenedor', today()->addDays(6)->toDateString());
        $item($lucia, 0)->cambiarEstado($E::Enviado, $prov);
        foreach ([0, 1] as $i) {
            $item($jorge, $i)->cambiarEstado($E::Enviado, $prov);
            $item($jorge, $i)->cambiarEstado($E::Recibido, $v1);
        }
        $item($ana, 0)->cambiarEstado($E::Enviado, $prov);
        $item($ana, 0)->cambiarEstado($E::Recibido, $v1);
        $item($ana, 0)->cambiarEstado($E::Entregado, $v1);
        foreach ($creados as $p) {
            $p->recalcular();
        }
        // El de Pedro (último) queda pendiente en el pedido abierto nuevo.
    }
}
