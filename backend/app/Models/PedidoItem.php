<?php

namespace App\Models;

use App\Enums\EstadoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoItem extends Model
{
    protected $fillable = ['pedido_id', 'producto_id', 'lote_id', 'codigo', 'descripcion', 'cantidad', 'precio',
        'estado', 'fecha_ingreso', 'ingreso_notificado', 'nota_proveedor'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoItem::class,
            'precio' => 'decimal:2',
            'cantidad' => 'integer',
            'fecha_ingreso' => 'date:Y-m-d',
            'ingreso_notificado' => 'date:Y-m-d',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(PedidoItemHistorial::class)->orderBy('id');
    }

    /** Mientras el lote siga abierto el proveedor no lo ha tomado: se puede corregir o quitar. */
    public function editable(): bool
    {
        return $this->estado === EstadoItem::Pendiente && (! $this->lote || $this->lote->abierto());
    }

    /** Cambia el estado dejando rastro en el historial. */
    public function cambiarEstado(EstadoItem $nuevo, ?User $user, ?string $nota = null, ?string $fechaIngreso = null): void
    {
        $anterior = $this->estado;

        $this->estado = $nuevo;
        $this->fecha_ingreso = $nuevo === EstadoItem::NoDisponible ? $fechaIngreso : null;
        if ($nuevo === EstadoItem::NoDisponible) {
            $this->ingreso_notificado = null;
        }
        $this->save();

        $this->historial()->create([
            'user_id' => $user?->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'fecha_ingreso' => $this->fecha_ingreso,
            'nota' => $nota,
        ]);
    }
}
