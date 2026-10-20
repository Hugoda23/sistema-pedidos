<?php

namespace App\Services;

use App\Enums\EstadoItem;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    /**
     * @param  array{cliente_id:int, notas?:?string, anticipo?:?float, items:array}  $datos
     */
    public function crear(array $datos, User $vendedor): Pedido
    {
        return DB::transaction(function () use ($datos, $vendedor) {
            $pedido = Pedido::create([
                'cliente_id' => $datos['cliente_id'],
                'vendedor_id' => $vendedor->id,
                'notas' => $datos['notas'] ?? null,
            ]);
            $pedido->asignarNumero();

            foreach ($datos['items'] as $i => $item) {
                $this->agregarItem($pedido, $item, $vendedor, "items.{$i}.codigo");
            }

            if (! empty($datos['anticipo']) && $datos['anticipo'] > 0) {
                $pedido->pagos()->create([
                    'monto' => $datos['anticipo'], 'tipo' => 'anticipo', 'registrado_por' => $vendedor->id,
                ]);
            }

            $pedido->recalcular();

            return $pedido;
        });
    }

    /** Agrega una línea con el código y nombre que escribió el vendedor (no hay catálogo). */
    public function agregarItem(Pedido $pedido, array $item, User $user, string $campo = 'codigo'): PedidoItem
    {
        $linea = $pedido->items()->create([
            'lote_id' => Lote::actual(Lote::CLIENTES)->id,
            'codigo' => PedidoItem::normalizarCodigo($item['codigo']),
            'descripcion' => trim($item['descripcion']),
            'cantidad' => $item['cantidad'],
            'precio' => $item['precio'],
            'estado' => EstadoItem::Pendiente,
        ]);
        $linea->historial()->create(['user_id' => $user->id, 'estado_nuevo' => 'pendiente', 'nota' => 'Solicitado']);

        return $linea;
    }

    public function anular(Pedido $pedido, User $user, string $motivo): void
    {
        $avanzados = $pedido->items()->whereIn('estado', ['enviado', 'recibido', 'entregado'])->count();
        if ($avanzados) {
            throw ValidationException::withMessages([
                'motivo' => 'El proveedor ya envió productos de este pedido; anula solo las líneas que falten.',
            ]);
        }

        DB::transaction(function () use ($pedido, $user, $motivo) {
            foreach ($pedido->items()->whereIn('estado', ['pendiente', 'no_disponible'])->get() as $item) {
                $item->cambiarEstado(EstadoItem::Anulado, $user, "Pedido anulado: {$motivo}");
            }
            $pedido->update(['anulado_en' => now(), 'anulado_por' => $user->id, 'motivo_anulacion' => $motivo]);
            $pedido->recalcular();
        });
    }
}
