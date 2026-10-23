<?php

namespace App\Services;

use App\Enums\EstadoItem;
use App\Jobs\EnviarWhatsapp;
use App\Models\Lote;
use App\Models\MensajeWhatsapp;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use App\Notifications\Aviso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as Modelos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Avisos del sistema: siempre van a la campanita y además por WhatsApp, enviados
 * solos desde el WhatsApp vinculado de quien hizo la acción (el proveedor al cambiar
 * estados; el vendedor al preguntar por un ingreso o escribirle a su cliente).
 */
class Avisos
{
    /** El seeder de demostración lo apaga para no llenar la cola de mensajes. */
    public bool $whatsapp = true;

    /**
     * El proveedor marcó líneas: a cada vendedor afectado, un solo mensaje con lo suyo.
     *
     * @return array{enviados: string[], sin_telefono: string[]}
     */
    public function proveedorActualizo(Collection $items, EstadoItem $estado, User $proveedor, ?string $nota): array
    {
        $items = Modelos::make($items->all())->load('pedido.vendedor');
        $resumen = ['enviados' => [], 'sin_telefono' => []];

        foreach ($items->groupBy(fn (PedidoItem $i) => $i->pedido->vendedor_id) as $lineas) {
            $vendedor = $lineas->first()->pedido->vendedor;
            $titulo = match ($estado) {
                EstadoItem::Enviado => 'Repuestos enviados',
                EstadoItem::NoDisponible => 'Repuestos sin existencia',
                EstadoItem::Descontinuado => 'Repuestos descontinuados',
                default => 'Actualización del proveedor',
            };

            $texto = "*{$titulo}*\n\n".$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i)
                .($estado === EstadoItem::NoDisponible ? ' — ingreso aprox. '.$i->fecha_ingreso->format('d/m/Y') : '')
            )->implode("\n").($nota ? "\n\nNota: {$nota}" : '');

            $pedidos = $lineas->pluck('pedido_id')->unique();
            $vendedor->notify(new Aviso($titulo, $this->resumen($lineas), $pedidos->count() === 1 ? "/pedidos/{$pedidos->first()}" : '/pedidos'));

            $this->aUsuario($proveedor, $vendedor, $texto, 'estado_proveedor', $pedidos->count() === 1 ? $pedidos->first() : null, $resumen);
        }

        return $resumen;
    }

    /** El proveedor atendió repuestos del pedido de inventario: se le avisa a quien los agregó. */
    /** @param  array<int,int>  $unidades  id => unidades que salieron (el resto de un envío parcial, no el total) */
    public function inventarioActualizado(Collection $items, EstadoItem $estado, User $proveedor, ?string $nota, array $unidades = []): array
    {
        $items = Modelos::make($items->all())->load('creador');
        $resumen = ['enviados' => [], 'sin_telefono' => []];
        $titulo = match ($estado) {
            EstadoItem::Enviado => 'Inventario: repuestos enviados',
            EstadoItem::NoDisponible => 'Inventario: repuestos sin existencia',
            EstadoItem::Descontinuado => 'Inventario: repuestos descontinuados',
            default => 'Inventario: actualización del proveedor',
        };

        foreach ($items->groupBy('creado_por') as $lineas) {
            $vendedor = $lineas->first()->creador;
            $texto = "*{$titulo}*\n\n".$lineas->map(fn ($i) => "• {$i->codigo} {$i->descripcion} ".$this->unidadesInventario($i, $estado, $unidades[$i->id] ?? null)
                .($estado === EstadoItem::NoDisponible ? ' — ingreso aprox. '.$i->fecha_ingreso->format('d/m/Y') : ''))->implode("\n")
                .($nota ? "\n\nNota: {$nota}" : '');

            $vendedor->notify(new Aviso($titulo, $this->resumen($lineas), '/inventario'));
            $this->aUsuario($proveedor, $vendedor, $texto, 'inventario', null, $resumen);
        }

        return $resumen;
    }

    /** «(10 u.)», o al completar un envío parcial «(las 5 que faltaban de 10)». */
    private function unidadesInventario(\App\Models\InventarioItem $i, EstadoItem $estado, ?int $salieron): string
    {
        if ($estado === EstadoItem::Enviado && $salieron !== null && $salieron < $i->cantidad) {
            return "(las {$salieron} que faltaban de {$i->cantidad})";
        }
        if ($estado !== EstadoItem::Enviado && $i->enviada > 0) {
            return "(faltan {$i->faltan()} de {$i->cantidad})";
        }

        return "({$i->cantidad} u.)";
    }

    /** El proveedor mandó solo una parte: a quien lo agregó, cuántas van y cuándo ingresa el resto. */
    public function inventarioParcial(\App\Models\InventarioItem $item, int $cantidad, User $proveedor, ?string $nota): array
    {
        $item->loadMissing('creador');
        $resumen = ['enviados' => [], 'sin_telefono' => []];
        $vendedor = $item->creador;
        $titulo = 'Inventario: envío parcial';
        $detalle = "se enviaron {$cantidad} de {$item->cantidad} (van {$item->enviada}); faltan {$item->faltan()}, ingreso aprox. ".$item->fecha_ingreso->format('d/m/Y');
        $texto = "*{$titulo}*\n\n• {$item->codigo} {$item->descripcion}: {$detalle}".($nota ? "\n\nNota: {$nota}" : '');

        $vendedor->notify(new Aviso($titulo, "{$item->codigo}: {$detalle}", '/inventario'));
        $this->aUsuario($proveedor, $vendedor, $texto, 'inventario', null, $resumen);

        return $resumen;
    }

    /** El proveedor cerró el pedido de inventario: a cada vendedor, lo que él agregó. */
    public function inventarioCerrado(Lote $lote, User $proveedor): array
    {
        $items = $lote->inventario()->with('creador')->whereIn('estado', \App\Models\InventarioItem::ACTIVOS)->get();
        $resumen = ['enviados' => [], 'sin_telefono' => []];

        foreach ($items->groupBy('creado_por') as $lineas) {
            $vendedor = $lineas->first()->creador;
            $texto = "*{$lote->nombre()} cerrado*\n\nIncluye lo que usted agregó:\n"
                .$lineas->map(fn ($i) => "• {$i->codigo} {$i->descripcion} ({$i->cantidad} u.)")->implode("\n");

            $vendedor->notify(new Aviso("{$lote->nombre()} cerrado", $lineas->count().' repuesto(s) suyos van en este pedido.', '/inventario'));
            $this->aUsuario($proveedor, $vendedor, $texto, 'inventario_cerrado', null, $resumen);
        }

        return $resumen;
    }

    /** El proveedor cerró el pedido consolidado: cada vendedor recibe la lista de lo suyo. */
    public function loteCerrado(Lote $lote, User $proveedor): array
    {
        $items = $lote->items()->with('pedido.vendedor')->where('estado', '!=', 'anulado')->get();
        $resumen = ['enviados' => [], 'sin_telefono' => []];

        foreach ($items->groupBy(fn (PedidoItem $i) => $i->pedido->vendedor_id) as $lineas) {
            $vendedor = $lineas->first()->pedido->vendedor;
            $texto = "*{$lote->nombre()} cerrado*\n\nIncluye sus repuestos:\n"
                .$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i))->implode("\n");

            $vendedor->notify(new Aviso("{$lote->nombre()} cerrado", $lineas->count().' de sus repuestos van en este pedido.', '/pedidos'));
            $this->aUsuario($proveedor, $vendedor, $texto, 'lote_cerrado', null, $resumen);
        }

        return $resumen;
    }

    /**
     * Llegó la fecha aproximada de ingreso: el vendedor le pregunta al proveedor
     * (desde el WhatsApp del vendedor) si ya entró. Devuelve cuántas líneas se avisaron.
     */
    public function ingresosVencidos(): int
    {
        $items = PedidoItem::query()
            ->with('pedido.vendedor')
            ->where('estado', EstadoItem::NoDisponible)
            ->whereDate('fecha_ingreso', '<=', today())
            ->where(fn ($q) => $q->whereNull('ingreso_notificado')->orWhereColumn('ingreso_notificado', '<', 'fecha_ingreso'))
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        foreach ($items->groupBy(fn (PedidoItem $i) => $i->pedido->vendedor_id) as $lineas) {
            $vendedor = $lineas->first()->pedido->vendedor;
            $enviados = $this->preguntar($lineas, $vendedor, 'ingreso_vencido');

            $vendedor->notify(new Aviso(
                'Fecha de ingreso cumplida',
                $this->resumen($lineas).($enviados ? '. Se le preguntó al proveedor por WhatsApp.' : ': ¿ya ingresó?'),
                $lineas->pluck('pedido_id')->unique()->count() === 1 ? "/pedidos/{$lineas->first()->pedido_id}" : '/pedidos',
            ));
        }

        PedidoItem::whereKey($items->modelKeys())->update(['ingreso_notificado' => today()]);

        return $items->count();
    }

    /**
     * El vendedor pregunta al proveedor por líneas sin existencia, desde su WhatsApp.
     * Devuelve los nombres de los proveedores a los que se les envió.
     */
    public function preguntar(Collection $lineas, User $vendedor, string $tipo = 'pregunta_ingreso'): array
    {
        $texto = "Buen día, ¿ya ingresaron estos repuestos?\n\n"
            .$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i).($i->fecha_ingreso ? ' — aprox. '.$i->fecha_ingreso->format('d/m/Y') : ''))->implode("\n");

        $resumen = ['enviados' => [], 'sin_telefono' => []];
        foreach (User::role('Proveedor')->where('activo', true)->get() as $proveedor) {
            $proveedor->notify(new Aviso('¿Ya ingresaron?', "{$vendedor->name}: ".$this->resumen($lineas), '/proveedor?estado=revisar'));
            $this->aUsuario($vendedor, $proveedor, $texto, $tipo, null, $resumen);
        }

        return $resumen['enviados'];
    }

    /**
     * Se agregó algo a un pedido ya cerrado (el vendedor ya lo habló por teléfono con el proveedor):
     * queda en la campanita del proveedor como constancia, sin WhatsApp.
     */
    public function trasCierre(Lote $lote, string $repuesto, User $quien, string $url): void
    {
        foreach (User::role('Proveedor')->where('activo', true)->get() as $proveedor) {
            if ($proveedor->id !== $quien->id) {
                $proveedor->notify(new Aviso("Agregado a {$lote->nombre()} (ya cerrado)", "{$quien->name}: {$repuesto}", $url));
            }
        }
    }

    /** Mensaje del vendedor a su cliente, desde su WhatsApp; opcionalmente con el comprobante en PDF. */
    public function alCliente(Pedido $pedido, User $vendedor, string $texto, bool $conComprobante = false): MensajeWhatsapp
    {
        $datos = [
            'cliente_id' => $pedido->cliente_id,
            'pedido_id' => $pedido->id,
            'telefono' => $pedido->cliente->telefono,
            'tipo' => 'cliente',
            'mensaje' => $texto,
        ];

        if ($conComprobante) {
            // Se guarda tal como está ahora: es lo que el cliente debe recibir aunque el pedido cambie después.
            $pedido->load(['cliente', 'vendedor:id,name', 'items', 'pagos']);
            $ruta = 'whatsapp/'.Str::uuid().'.pdf';
            Storage::disk('local')->put($ruta, Pdf::loadView('comprobantes.pedido', ['pedido' => $pedido])->setPaper('letter')->output());
            $datos += ['adjunto' => $ruta, 'adjunto_nombre' => "Pedido {$pedido->numero}.pdf"];
        }

        return $this->registrar($vendedor, $datos);
    }

    public function reintentar(MensajeWhatsapp $m): void
    {
        $m->update(['estado' => 'pendiente', 'error' => null]);
        EnviarWhatsapp::dispatch($m->id)->afterCommit();
    }

    private function aUsuario(User $de, User $para, string $texto, string $tipo, ?int $pedidoId, array &$resumen): void
    {
        if ($para->id === $de->id) {
            return;
        }
        // Sin teléfono no hay WhatsApp; queda la campanita.
        if (! $para->telefono) {
            $resumen['sin_telefono'][] = $para->name;

            return;
        }

        if ($this->whatsapp) {
            $this->registrar($de, [
                'destinatario_id' => $para->id,
                'pedido_id' => $pedidoId,
                'telefono' => $para->telefono,
                'tipo' => $tipo,
                'mensaje' => $texto,
            ]);
        }
        $resumen['enviados'][] = $para->name;
    }

    private function registrar(User $de, array $datos): MensajeWhatsapp
    {
        $m = MensajeWhatsapp::create($datos + ['remitente_id' => $de->id, 'estado' => 'pendiente']);
        EnviarWhatsapp::dispatch($m->id)->afterCommit();

        return $m;
    }

    private function linea(PedidoItem $i): string
    {
        return "{$i->codigo} {$i->descripcion} ({$i->cantidad} u.) · {$i->pedido->numero}";
    }

    private function resumen(Collection $lineas): string
    {
        $codigos = $lineas->pluck('codigo')->take(3)->implode(', ');

        return $lineas->count() > 3 ? "{$codigos} y ".($lineas->count() - 3).' más' : $codigos;
    }
}
