<?php

namespace App\Services;

use App\Enums\EstadoItem;
use App\Models\Lote;
use App\Models\PedidoItem;
use App\Models\User;
use App\Notifications\Aviso;
use App\Support\WhatsApp;
use Illuminate\Support\Collection;

/**
 * Avisos del sistema: siempre van a la campanita, y además se arma el mensaje
 * de WhatsApp para que quien hizo la acción lo envíe desde su propio teléfono
 * con un clic (enlace wa.me con el texto ya escrito).
 */
class Avisos
{
    /**
     * El proveedor marcó líneas. Devuelve un enlace de WhatsApp por vendedor afectado.
     *
     * @return array<int, array{para:string, lineas:int, url:?string}>
     */
    public function proveedorActualizo(Collection $items, EstadoItem $estado, ?string $nota): array
    {
        $items->load('pedido.vendedor');
        $enlaces = [];

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

            $enlaces[] = ['para' => $vendedor->name, 'lineas' => $lineas->count(), 'url' => WhatsApp::enlace($vendedor->telefono, $texto)];
        }

        return $enlaces;
    }

    /** El proveedor cerró el pedido consolidado: un enlace por vendedor con la lista de lo suyo. */
    public function loteCerrado(Lote $lote): array
    {
        $items = $lote->items()->with('pedido.vendedor')->where('estado', '!=', 'anulado')->get();
        $enlaces = [];

        foreach ($items->groupBy(fn (PedidoItem $i) => $i->pedido->vendedor_id) as $lineas) {
            $vendedor = $lineas->first()->pedido->vendedor;
            $texto = "📦 *Pedido #{$lote->id} cerrado*\n\nIncluye tus repuestos:\n"
                .$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i))->implode("\n");

            $vendedor->notify(new Aviso("Pedido #{$lote->id} cerrado", $lineas->count().' de tus líneas van en este pedido.', '/pedidos'));
            $enlaces[] = ['para' => $vendedor->name, 'lineas' => $lineas->count(), 'url' => WhatsApp::enlace($vendedor->telefono, $texto)];
        }

        return $enlaces;
    }

    /**
     * Llegó la fecha aproximada de ingreso. Al proveedor le aparece en «Revisar ingreso»
     * y en la campanita; al vendedor, en la campanita con el botón para preguntarle por WhatsApp.
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
            $texto = $this->preguntaIngreso($lineas);

            $botones = $proveedores->map(fn (User $p) => [
                'texto' => 'Preguntar a '.$p->name,
                'url' => WhatsApp::enlace($p->telefono, $texto),
            ])->filter(fn ($b) => $b['url'])->values()->all();

            $vendedor->notify(new Aviso(
                'Fecha de ingreso cumplida',
                $this->resumen($lineas).': ¿ya ingresó?',
                $lineas->pluck('pedido_id')->unique()->count() === 1 ? "/pedidos/{$lineas->first()->pedido_id}" : '/pedidos',
                $botones,
            ));

            foreach ($proveedores as $proveedor) {
                $proveedor->notify(new Aviso('¿Ya ingresaron?', "{$vendedor->name}: ".$this->resumen($lineas), '/proveedor?estado=revisar'));
            }
        }

        PedidoItem::whereKey($items->modelKeys())->update(['ingreso_notificado' => today()]);

        return $items->count();
    }

    /** Texto del vendedor al proveedor preguntando por líneas sin existencia. */
    public function preguntaIngreso(Collection $lineas): string
    {
        return "Hola 👋 ¿ya ingresaron estos repuestos?\n\n"
            .$lineas->map(fn (PedidoItem $i) => '• '.$this->linea($i).($i->fecha_ingreso ? ' — aprox. '.$i->fecha_ingreso->format('d/m/Y') : ''))->implode("\n");
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
