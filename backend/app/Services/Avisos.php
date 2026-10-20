<?php

namespace App\Services;

use App\Enums\EstadoItem;
use App\Jobs\EnviarWhatsapp;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\MensajeWhatsapp;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use App\Notifications\Aviso;
use Illuminate\Support\Collection;

/**
 * Arma los avisos del sistema. Cada WhatsApp sale desde el número de quien hizo
 * la acción (el proveedor al cambiar estados, el vendedor al preguntar por un ingreso)
 * hacia el número guardado en el usuario que lo recibe.
 */
class Avisos
{
    /** El proveedor marcó líneas: se le avisa a cada vendedor lo suyo, en un solo mensaje. */
    public function proveedorActualizo(Collection $items, EstadoItem $estado, User $proveedor, ?string $nota): void
    {
        $items->load('pedido.vendedor');

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
            )->implode("\n");
            if ($nota) {
                $texto .= "\n\nNota: {$nota}";
            }

            $pedidos = $lineas->pluck('pedido_id')->unique();
            $url = $pedidos->count() === 1 ? "/pedidos/{$pedidos->first()}" : '/pedidos';

            $vendedor->notify(new Aviso($titulo, $this->resumen($lineas), $url));
            $this->whatsapp($proveedor, $vendedor, $texto, 'estado_proveedor', pedidoId: $pedidos->count() === 1 ? $pedidos->first() : null);
        }
    }

    /** El proveedor cerró el pedido consolidado: cada vendedor recibe la lista de lo suyo. */
    public function loteCerrado(Lote $lote, User $proveedor): void
    {
        $items = $lote->items()->with('pedido.vendedor')->where('estado', '!=', 'anulado')->get();

        foreach ($items->groupBy(fn (PedidoItem $i) => $i->pedido->vendedor_id) as $lineas) {
            $vendedor = $lineas->first()->pedido->vendedor;
            $texto = "📦 *Pedido #{$lote->id} cerrado*\n\nIncluye tus repuestos:\n"
                .$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i))->implode("\n");

            $vendedor->notify(new Aviso("Pedido #{$lote->id} cerrado", $lineas->count().' de tus líneas van en este pedido.', '/pedidos'));
            $this->whatsapp($proveedor, $vendedor, $texto, 'lote_cerrado');
        }
    }

    /**
     * Llegó la fecha aproximada de ingreso: el vendedor le pregunta al proveedor si ya entró.
     * Devuelve cuántas líneas se avisaron.
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

        $proveedores = User::role('Proveedor')->where('activo', true)->get();

        foreach ($items->groupBy(fn (PedidoItem $i) => $i->pedido->vendedor_id) as $lineas) {
            $vendedor = $lineas->first()->pedido->vendedor;
            $texto = "Hola 👋 Ya se cumplió la fecha aproximada de ingreso de estos repuestos, ¿ya entraron?\n\n"
                .$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i).' — aprox. '.$i->fecha_ingreso->format('d/m/Y'))->implode("\n");

            foreach ($proveedores as $proveedor) {
                $proveedor->notify(new Aviso('¿Ya ingresaron?', "{$vendedor->name}: ".$this->resumen($lineas), '/proveedor?estado=revisar'));
                $this->whatsapp($vendedor, $proveedor, $texto, 'ingreso_vencido');
            }

            $vendedor->notify(new Aviso(
                'Fecha de ingreso cumplida',
                $this->resumen($lineas).'. Se le consultó al proveedor.',
                $lineas->pluck('pedido_id')->unique()->count() === 1 ? "/pedidos/{$lineas->first()->pedido_id}" : '/pedidos',
            ));
        }

        PedidoItem::whereKey($items->modelKeys())->update(['ingreso_notificado' => today()]);

        return $items->count();
    }

    /** Mensaje escrito por el vendedor para su cliente, desde su propio WhatsApp. */
    public function alCliente(Pedido $pedido, User $vendedor, string $texto): MensajeWhatsapp
    {
        return $this->registrar($vendedor, [
            'cliente_id' => $pedido->cliente_id,
            'pedido_id' => $pedido->id,
            'telefono' => $pedido->cliente->telefono,
            'tipo' => 'cliente',
            'mensaje' => $texto,
        ]);
    }

    public function reintentar(MensajeWhatsapp $m): void
    {
        $m->update(['estado' => 'pendiente', 'error' => null]);
        EnviarWhatsapp::dispatch($m->id)->afterCommit();
    }

    private function whatsapp(User $de, User $para, string $texto, string $tipo, ?int $pedidoId = null): void
    {
        // Sin teléfono no hay WhatsApp; queda la campanita.
        if (! $para->telefono || $para->id === $de->id) {
            return;
        }

        $this->registrar($de, [
            'destinatario_id' => $para->id,
            'pedido_id' => $pedidoId,
            'telefono' => $para->telefono,
            'tipo' => $tipo,
            'mensaje' => $texto,
        ]);
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
