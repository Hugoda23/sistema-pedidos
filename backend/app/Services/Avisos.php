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
            [$icono, $titulo] = match ($estado) {
                EstadoItem::Enviado => ['✅', 'Repuestos enviados'],
                EstadoItem::NoDisponible => ['⏳', 'Repuestos sin existencia'],
                EstadoItem::Descontinuado => ['⛔', 'Repuestos descontinuados'],
                default => ['🔔', 'Actualización del proveedor'],
            };

            $texto = "{$icono} *{$titulo}*\n\n".$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i)
                .($estado === EstadoItem::NoDisponible ? ' — ingreso aprox. '.$i->fecha_ingreso->format('d/m/Y') : '')
            )->implode("\n").($nota ? "\n\nNota: {$nota}" : '');

            $pedidos = $lineas->pluck('pedido_id')->unique();
            $vendedor->notify(new Aviso($titulo, $this->resumen($lineas), $pedidos->count() === 1 ? "/pedidos/{$pedidos->first()}" : '/pedidos'));

            $this->aUsuario($proveedor, $vendedor, $texto, 'estado_proveedor', $pedidos->count() === 1 ? $pedidos->first() : null, $resumen);
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
            $texto = "📦 *Pedido #{$lote->id} cerrado*\n\nIncluye sus repuestos:\n"
                .$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i))->implode("\n");

            $vendedor->notify(new Aviso("Pedido #{$lote->id} cerrado", $lineas->count().' de sus líneas van en este pedido.', '/pedidos'));
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
