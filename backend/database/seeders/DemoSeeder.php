<?php

namespace Database\Seeders;

use App\Enums\EstadoItem;
use App\Models\Cliente;
use App\Models\InventarioItem;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use App\Services\Avisos;
use App\Services\PedidoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Simula ~6 semanas de trabajo para probar todos los apartados:
 *  - Pedidos de clientes: los vendedores toman pedidos; el proveedor responde cada 3 días
 *    (enviado / sin existencia / descontinuado); los vendedores reciben, entregan y cobran.
 *  - Pedido de inventario: los vendedores agregan lo que falta (a veces el mismo código: se suma),
 *    corrigen o quitan; el proveedor cierra un «Inventario #N» cada 5 días y responde al día siguiente.
 *
 *   php artisan migrate:fresh --seed --seeder=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private const DIAS = 42;

    private PedidoService $pedidos;

    private Avisos $avisos;

    private User $proveedor;

    /** @var array<int, array{0:Carbon, 1:int, 2:callable}> */
    private array $agenda = [];

    private int $secuencia = 0;

    /** Repuestos de ejemplo [código, nombre, precio]; no se guardan como catálogo, solo se usan para inventar pedidos. */
    private array $repuestos = [];

    /** Códigos que el proveedor marcó descontinuados: el vendedor ya no los vuelve a pedir. */
    private array $descontinuados = [];

    public function run(PedidoService $pedidos, Avisos $avisos): void
    {
        $this->pedidos = $pedidos;
        $this->avisos = $avisos;
        $this->avisos->whatsapp = false; // solo campanita: no llenar la cola de mensajes
        mt_srand(2026); // siempre los mismos datos

        $this->call(DatabaseSeeder::class);
        $vendedores = $this->usuarios();
        $this->catalogo();
        $clientes = $this->clientes($vendedores);

        $ahora = now();
        $inicio = today()->subDays(self::DIAS);

        // Pedidos de cada día (menos los domingos).
        for ($dia = $inicio->copy(); $dia->lte(today()); $dia->addDay()) {
            if ($dia->isSunday()) {
                continue;
            }
            $cuantos = $dia->isSaturday() ? mt_rand(0, 1) : mt_rand(1, 3);
            for ($n = 0; $n < $cuantos; $n++) {
                $hora = $dia->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59));
                $this->agendar($hora, fn () => $this->nuevoPedido($vendedores->random(), $clientes->random(), $hora));
            }
            $n = (int) $inicio->diffInDays($dia);
            // Cada 3 días el proveedor responde lo pendiente de clientes.
            if ($n % 3 === 1) {
                $this->agendar($dia->copy()->setTime(10, mt_rand(0, 59)), fn () => $this->responderClientes());
            }
            // Inventario: casi todos los días alguien anota lo que falta.
            for ($k = 0, $m = $dia->isSaturday() ? 0 : mt_rand(0, 2); $k < $m; $k++) {
                $hora = $dia->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59));
                $this->agendar($hora, fn () => $this->agregarInventario($vendedores->random()));
            }
            // Cada 5 días el proveedor cierra el pedido de inventario y responde al día siguiente.
            if ($n % 5 === 4) {
                $cierre = $dia->copy()->setTime(17, 30);
                $this->agendar($cierre, fn () => $this->cerrarInventario($cierre));
            }
        }

        // Ejecuta todo en orden cronológico, con el reloj puesto en cada momento.
        while ($this->agenda) {
            usort($this->agenda, fn ($a, $b) => [$a[0]->timestamp, $a[1]] <=> [$b[0]->timestamp, $b[1]]);
            [$cuando, , $hacer] = array_shift($this->agenda);
            if ($cuando->gt($ahora)) {
                continue;
            }
            Carbon::setTestNow($cuando);
            $hacer();
        }
        Carbon::setTestNow();

        $this->casosEspeciales($vendedores);

        // El aviso diario de las 08:00 sobre fechas de ingreso cumplidas.
        $this->avisos->ingresosVencidos();

        // Lo de días anteriores ya se leyó; quedan sin leer solo los avisos de hoy.
        \DB::table('notifications')->where('created_at', '<', today())->update(['read_at' => today()]);
    }

    private function agendar(Carbon $cuando, callable $hacer): void
    {
        $this->agenda[] = [$cuando->copy(), $this->secuencia++, $hacer];
    }

    private function usuarios(): Collection
    {
        Role::findOrCreate('Supervisor', 'web')->syncPermissions([
            'pedidos.ver', 'pedidos.ver_todos', 'clientes.ver', 'inventario.ver', 'proveedor.ver', 'reportes.ver',
        ]);

        User::where('email', 'vendedor@pedidos.test')->update(['name' => 'Carlos Méndez', 'telefono' => '55550001']);
        $this->proveedor = User::where('email', 'proveedor@pedidos.test')->first();
        $this->proveedor->update(['name' => 'Distribuidora El Pistón', 'telefono' => '55550009']);
        User::where('email', 'admin@pedidos.test')->update(['telefono' => '55550000']);

        $crear = function (string $nombre, string $email, string $rol, ?string $tel, bool $activo = true) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $nombre, 'password' => 'password', 'telefono' => $tel, 'activo' => $activo]);
            $u->syncRoles([$rol]);

            return $u;
        };

        $crear('María López', 'maria@pedidos.test', 'Vendedor', '55550002');
        $crear('José Ramírez', 'jose@pedidos.test', 'Vendedor', null); // sin teléfono: no recibe WhatsApp
        $crear('Sofía Herrera', 'supervisor@pedidos.test', 'Supervisor', '55550005');
        $crear('Luis Ortiz', 'luis@pedidos.test', 'Vendedor', '55550006', activo: false); // ya no trabaja

        return User::role('Vendedor')->where('activo', true)->orderBy('id')->get();
    }

    private function catalogo(): void
    {
        $productos = [
            ['LOD-XR150-T', 'Lodera trasera Honda XR150', 185], ['LOD-FZ-D', 'Lodera delantera Yamaha FZ', 160],
            ['PF-FZ-D', 'Pastillas de freno delanteras Yamaha FZ', 95], ['PF-XR150-T', 'Zapatas de freno traseras XR150', 70],
            ['PF-PUL200-D', 'Pastillas de freno delanteras Pulsar 200', 110], ['CAD-428H-120', 'Cadena 428H 120 eslabones', 260],
            ['CAD-520-110', 'Cadena 520 110 eslabones', 340], ['KIT-ARR-XR150', 'Kit de arrastre Honda XR150', 395],
            ['KIT-ARR-PUL200', 'Kit de arrastre Pulsar 200', 420], ['BUJ-CR7HSA', 'Bujía NGK CR7HSA', 35],
            ['BUJ-DR8EA', 'Bujía NGK DR8EA', 40], ['FIL-AIR-GN125', 'Filtro de aire Suzuki GN125', 75],
            ['FIL-AIR-FZ', 'Filtro de aire Yamaha FZ', 90], ['FIL-ACE-XR150', 'Filtro de aceite Honda XR150', 45],
            ['ESP-UNI-NEG', 'Espejo retrovisor universal negro', 45], ['MAN-XR150-I', 'Manigueta de clutch XR150', 65],
            ['MAN-FZ-D', 'Manigueta de freno Yamaha FZ', 70], ['CDI-FT150', 'CDI Italika FT150', 210],
            ['REG-XR150', 'Regulador de voltaje XR150', 185], ['BAT-YTX7L', 'Batería YTX7L-BS', 450],
            ['CAR-PD24', 'Carburador PD24 GN125', 380], ['LLA-275-18', 'Llanta 2.75-18 trasera', 290],
            ['LLA-300-17', 'Llanta 3.00-17 trasera', 320], ['TUB-18', 'Tubo de llanta rin 18', 60],
            ['CAB-ACE-XR', 'Cable de acelerador XR150', 55], ['CAB-CLU-FZ', 'Cable de clutch Yamaha FZ', 60],
            ['FAR-LED-UNI', 'Faro LED universal', 175], ['DIR-PAR', 'Par de direccionales LED', 95],
            ['AMO-T-XR150', 'Amortiguador trasero XR150', 650], ['RET-FZ-HORQ', 'Retenedores de horquilla FZ', 85],
            ['EMP-CLU-GN125', 'Discos de clutch GN125', 210], ['ACE-20W50-1L', 'Aceite 20W-50 4T (1 litro)', 55],
        ];
        $this->repuestos = $productos;
    }

    private function clientes(Collection $vendedores): Collection
    {
        $datos = [
            ['Mario Pérez', '41234567', 'Yamaha FZ 2.0 2022'], ['Lucía Méndez', '41230000', 'Honda XR150 2020'],
            ['Jorge Castillo', '42221111', 'Suzuki GN125 2019'], ['Ana Ruiz', '43334444', 'Bajaj Pulsar 200 2021'],
            ['Pedro Gómez', '45556666', 'Italika FT150 2018'], ['Rosa Aguilar', '47778888', 'Honda XR150 2023'],
            ['Kevin Morales', '48889999', 'Yamaha FZ 2.0 2021'], ['Andrea Cifuentes', '30001111', 'Bajaj Pulsar 200 2020'],
            ['Byron Estrada', '31112222', 'Suzuki GN125 2017'], ['Gloria Juárez', '32223333', 'Honda XR150 2019'],
            ['Taller Moto Express', '33334444', 'Varias (taller)'], ['Edgar Pineda', '34445555', 'Italika FT150 2020'],
            ['Diana Velásquez', '35556666', 'Yamaha FZ 2.0 2023'], ['Fernando Cabrera', null, 'Honda XR150 2018'],
            ['Mensajería Rápida GT', '36667777', 'Flota Honda XR150'], ['Walter Sic', '37778888', 'Bajaj Pulsar 200 2022'],
        ];

        return collect($datos)->map(fn ($c, $i) => Cliente::firstOrCreate(['nombre' => $c[0]], [
            'telefono' => $c[1], 'moto' => $c[2], 'creado_por' => $vendedores[$i % $vendedores->count()]->id,
            'notas' => $i === 10 ? 'Cliente frecuente: compra para su taller.' : null,
        ]));
    }

    private function nuevoPedido(User $vendedor, Cliente $cliente, Carbon $cuando): void
    {
        // Solo lo que el proveedor aún trabaja.
        $disponibles = array_values(array_filter($this->repuestos, fn ($r) => ! in_array($r[0], $this->descontinuados, true)));
        $elegidos = (array) array_rand($disponibles, mt_rand(1, 4));
        $items = array_map(fn ($i) => [
            'codigo' => $disponibles[$i][0],
            'descripcion' => $disponibles[$i][1],
            'cantidad' => mt_rand(1, 10) <= 7 ? 1 : mt_rand(2, 4),
            'precio' => round($disponibles[$i][2] * mt_rand(95, 110) / 100 / 5) * 5,
        ], $elegidos);
        $total = collect($items)->sum(fn ($i) => $i['cantidad'] * $i['precio']);
        $anticipo = mt_rand(1, 10) <= 6 ? round($total * mt_rand(30, 60) / 100 / 10) * 10 : 0;

        $this->pedidos->crear([
            'cliente_id' => $cliente->id,
            'anticipo' => $anticipo,
            'notas' => mt_rand(1, 10) === 1 ? 'Cliente pide que se le avise apenas llegue.' : null,
            'items' => $items,
        ], $vendedor);
    }

    /** El proveedor responde línea por línea: la mayoría sale, algunas no hay, pocas descontinuadas. */
    private function responderClientes(): void
    {
        $grupos = ['enviado' => [], 'no_disponible' => [], 'descontinuado' => []];
        // Lo que se pidió hasta ayer (lo de hoy lo verá en la próxima respuesta).
        $pendientes = PedidoItem::where('estado', 'pendiente')->where('created_at', '<', today())->get();
        foreach ($pendientes as $item) {
            $r = mt_rand(1, 100);
            $grupos[match (true) { $r <= 74 => 'enviado', $r <= 90 => 'no_disponible', $r <= 94 => 'descontinuado', default => 'nada' }][] = $item;
        }

        $this->marcar(collect($grupos['enviado']), EstadoItem::Enviado);
        foreach ($grupos['no_disponible'] as $item) {
            $fecha = today()->addDays(mt_rand(3, 14));
            $this->marcar(collect([$item]), EstadoItem::NoDisponible, $fecha->toDateString(),
                ['Viene en el próximo contenedor', 'Agotado en bodega central', 'Pedido a fábrica'][mt_rand(0, 2)]);
            // Cuando llega la fecha, muchas veces ya entró.
            if (mt_rand(1, 10) <= 6) {
                $this->agendar($fecha->copy()->setTime(9, 30), fn () => $item->fresh()->estado === EstadoItem::NoDisponible
                    ? $this->marcar(collect([$item->fresh()]), EstadoItem::Enviado) : null);
            }
        }
        if ($grupos['descontinuado']) {
            $this->marcar(collect($grupos['descontinuado']), EstadoItem::Descontinuado, null, 'Ya no se fabrica');
        }
    }

    /** Igual que la tabla del proveedor: cambia estado, recalcula y agenda lo que sigue. */
    private function marcar(Collection $items, EstadoItem $estado, ?string $fecha = null, ?string $nota = null): void
    {
        if ($items->isEmpty()) {
            return;
        }
        foreach ($items as $item) {
            $item->nota_proveedor = $nota ?? $item->nota_proveedor;
            $item->cambiarEstado($estado, $this->proveedor, $nota, $fecha);

            if ($estado === EstadoItem::Enviado && mt_rand(1, 10) <= 9) {
                $this->agendar(now()->addDays(mt_rand(1, 2))->setTime(mt_rand(9, 12), 0), fn () => $this->recibir($item));
            }
        }
        if ($estado === EstadoItem::Descontinuado) {
            $this->descontinuados = array_merge($this->descontinuados, $items->pluck('codigo')->all());
        }
        Pedido::whereIn('id', $items->pluck('pedido_id')->unique())->get()->each->recalcular();

        if (now()->gte(today()->subDays(3))) {
            $this->avisos->proveedorActualizo($items, $estado, $this->proveedor, $nota);
        }
    }

    private function recibir(PedidoItem $item): void
    {
        $item = $item->fresh();
        $vendedor = $item->pedido->vendedor;
        $item->cambiarEstado(EstadoItem::Recibido, $vendedor);
        $item->pedido->recalcular();

        if (mt_rand(1, 100) <= 85) {
            $this->agendar(now()->addDays(mt_rand(1, 4))->setTime(mt_rand(10, 18), 0), fn () => $this->entregar($item));
        }
    }

    private function entregar(PedidoItem $item): void
    {
        $item = $item->fresh();
        $pedido = $item->pedido;
        $item->cambiarEstado(EstadoItem::Entregado, $pedido->vendedor);
        $pedido->recalcular();

        // Al entregar cobra lo que corresponde; casi siempre liquida el saldo al final.
        $saldo = (float) $pedido->saldo();
        if ($saldo > 0 && ($pedido->estado === 'entregado' ? mt_rand(1, 10) <= 9 : mt_rand(1, 10) <= 3)) {
            $monto = $pedido->estado === 'entregado' ? $saldo : min($saldo, $item->cantidad * (float) $item->precio);
            $pedido->pagos()->create(['monto' => $monto, 'tipo' => $pedido->pagos()->exists() ? 'abono' : 'anticipo', 'registrado_por' => $pedido->vendedor_id]);
            $pedido->recalcular();
        }
    }

    /** Un vendedor anota un repuesto que falta. Si ya está en la lista abierta, le suma. */
    private function agregarInventario(User $vendedor, ?array $repuesto = null): void
    {
        $disponibles = array_values(array_filter($this->repuestos, fn ($r) => ! in_array($r[0], $this->descontinuados, true)));
        // A veces otro vendedor anota algo que ya está en la lista abierta (y el sistema le suma).
        $yaAnotado = mt_rand(1, 100) <= 25
            ? InventarioItem::where('estado', 'pendiente')->whereHas('lote', fn ($q) => $q->where('estado', 'abierto'))->inRandomOrder(mt_rand())->first()
            : null;
        [$codigo, $nombre] = $repuesto ?? ($yaAnotado ? [$yaAnotado->codigo, $yaAnotado->descripcion] : $disponibles[array_rand($disponibles)]);
        $cantidad = [1, 2, 2, 3, 4, 5, 6, 10, 12][mt_rand(0, 8)];

        $existente = InventarioItem::activos()->where('codigo', $codigo)->with('lote')->latest('id')->first();
        if ($existente && $existente->editable()) {
            $existente->increment('cantidad', $cantidad);
            $existente->registrar($vendedor, 'sumado', $cantidad);

            return;
        }

        $item = InventarioItem::create([
            'codigo' => $codigo, 'descripcion' => $nombre, 'cantidad' => $cantidad, 'estado' => EstadoItem::Pendiente,
            'lote_id' => Lote::actual(Lote::INVENTARIO)->id, 'creado_por' => $vendedor->id,
        ]);
        $item->registrar($vendedor, 'agregado', $cantidad, $existente ? "Adicional a lo ya pedido (#{$existente->id})" : null);

        // De vez en cuando alguien corrige o quita algo del pedido abierto.
        $r = mt_rand(1, 100);
        $editable = InventarioItem::where('estado', 'pendiente')->whereHas('lote', fn ($q) => $q->where('estado', 'abierto'))->inRandomOrder(mt_rand())->first();
        if ($editable && $r <= 12) {
            $nueva = max(1, $editable->cantidad + [-1, 1, 2, 4][mt_rand(0, 3)]);
            if ($nueva !== $editable->cantidad) {
                $nota = "Cantidad {$editable->cantidad} → {$nueva}";
                $editable->update(['cantidad' => $nueva]);
                $editable->registrar($editable->creador, 'editado', $nueva, $nota);
            }
        } elseif ($editable && $r <= 17 && $editable->id !== $item->id) {
            $editable->update(['estado' => EstadoItem::Anulado]);
            $editable->registrar($editable->creador, 'quitado', $editable->cantidad);
        }
    }

    private function cerrarInventario(Carbon $cuando): void
    {
        $lote = Lote::abiertoDe(Lote::INVENTARIO);
        if (! $lote || ! $lote->inventario()->activos()->exists()) {
            return;
        }
        $lote->update(['estado' => 'cerrado', 'cerrado_por' => $this->proveedor->id, 'cerrado_en' => $cuando]);
        if ($cuando->gte(now()->subDays(4))) {
            $this->avisos->inventarioCerrado($lote, $this->proveedor);
        }

        $this->agendar($cuando->copy()->addDay()->setTime(10, mt_rand(30, 59)), fn () => $this->responderInventario($lote));
    }

    /** El proveedor atiende el inventario del pedido cerrado: casi todo sale; algo no hay; poco se descontinúa. */
    private function responderInventario(Lote $lote): void
    {
        $grupos = ['enviado' => [], 'no_disponible' => [], 'descontinuado' => []];
        foreach ($lote->inventario()->where('estado', 'pendiente')->get() as $item) {
            $r = mt_rand(1, 100);
            $grupos[$r <= 76 ? 'enviado' : ($r <= 94 ? 'no_disponible' : 'descontinuado')][] = $item;
        }

        $this->marcarInventario(collect($grupos['enviado']), EstadoItem::Enviado);
        foreach ($grupos['no_disponible'] as $item) {
            $fecha = today()->addDays(mt_rand(3, 12));
            $this->marcarInventario(collect([$item]), EstadoItem::NoDisponible, $fecha->toDateString(),
                ['Llega la próxima semana', 'Agotado en bodega', 'Pedido a fábrica'][mt_rand(0, 2)]);
            if (mt_rand(1, 10) <= 7) {
                $this->agendar($fecha->copy()->setTime(10, 15), fn () => $item->fresh()->estado === EstadoItem::NoDisponible
                    ? $this->marcarInventario(collect([$item->fresh()]), EstadoItem::Enviado) : null);
            }
        }
        $this->marcarInventario(collect($grupos['descontinuado']), EstadoItem::Descontinuado, null, 'Ya no se fabrica');
    }

    private function marcarInventario(Collection $items, EstadoItem $estado, ?string $fecha = null, ?string $nota = null): void
    {
        if ($items->isEmpty()) {
            return;
        }
        foreach ($items as $item) {
            $item->update([
                'estado' => $estado,
                'fecha_ingreso' => $estado === EstadoItem::NoDisponible ? $fecha : null,
                'nota_proveedor' => $nota ?? $item->nota_proveedor,
                'atendido_por' => $this->proveedor->id,
                'atendido_en' => now(),
            ]);
            $item->registrar($this->proveedor, $estado->value, null, $nota);
        }
        if (now()->gte(today()->subDays(3))) {
            $this->avisos->inventarioActualizado($items, $estado, $this->proveedor, $nota);
        }
    }

    private function casosEspeciales(Collection $vendedores): void
    {
        $admin = User::where('email', 'admin@pedidos.test')->first();

        // Un pedido anulado porque el cliente canceló.
        $pendiente = Pedido::where('estado', 'pendiente')->orderBy('id')->first();
        if ($pendiente) {
            $this->pedidos->anular($pendiente, $admin, 'El cliente consiguió el repuesto en otro lado.');
        }

        // Una línea que el cliente ya no quiso.
        $item = PedidoItem::where('estado', 'pendiente')->whereHas('pedido', fn ($q) => $q->has('items', '>', 1))->latest('id')->first();
        if ($item) {
            $item->cambiarEstado(EstadoItem::Anulado, $item->pedido->vendedor, 'El cliente ya no lo necesita');
            $item->pedido->recalcular();
        }

        // Inventario de hoy en el pedido abierto: agregar, sumar (otro vendedor), corregir y quitar.
        foreach ([0, 1, 2, 0] as $v) {
            $this->agregarInventario($vendedores[$v]);
        }
        $primero = InventarioItem::activos()->whereHas('lote', fn ($q) => $q->where('estado', 'abierto'))->orderBy('id')->first();
        if ($primero) {
            $this->agregarInventario($vendedores[2], [$primero->codigo, $primero->descripcion]);
        }
        // Siempre hay al menos una corrección y un quitado en el pedido abierto (para el reporte).
        $abiertas = InventarioItem::where('estado', 'pendiente')->whereHas('lote', fn ($q) => $q->where('estado', 'abierto'))->orderBy('id')->get();
        if ($abiertas->count() >= 3) {
            $editar = $abiertas[1];
            $nota = "Cantidad {$editar->cantidad} → ".($editar->cantidad + 2);
            $editar->update(['cantidad' => $editar->cantidad + 2]);
            $editar->registrar($editar->creador, 'editado', $editar->cantidad, $nota);

            $quitar = $abiertas->last();
            $quitar->update(['estado' => EstadoItem::Anulado]);
            $quitar->registrar($quitar->creador, 'quitado', $quitar->cantidad);
        }

        // Pedidos de hoy, para que el proveedor tenga qué atender.
        $clientes = Cliente::orderBy('id')->get();
        foreach ([[0, 2], [1, 5]] as [$v, $c]) {
            $this->nuevoPedido($vendedores[$v], $clientes[$c], now());
        }
    }
}
