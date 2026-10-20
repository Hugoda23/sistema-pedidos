<?php

namespace App\Models;

use App\Enums\EstadoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Repuesto que falta en la tienda (no es de un cliente). */
class InventarioItem extends Model
{
    /** Lo que sigue en la lista; lo enviado o descontinuado sale de ella. */
    public const ACTIVOS = ['pendiente', 'no_disponible'];

    protected $fillable = ['codigo', 'descripcion', 'cantidad', 'estado', 'fecha_ingreso', 'nota_proveedor',
        'lote_id', 'creado_por', 'atendido_por', 'atendido_en'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoItem::class,
            'cantidad' => 'integer',
            'fecha_ingreso' => 'date:Y-m-d',
            'atendido_en' => 'datetime',
        ];
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->whereIn('estado', self::ACTIVOS);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(InventarioHistorial::class)->orderBy('id');
    }

    /** Mientras el proveedor no cierre el pedido, los vendedores pueden corregirla o quitarla. */
    public function editable(): bool
    {
        return $this->estado === EstadoItem::Pendiente && (! $this->lote || $this->lote->abierto());
    }

    public function registrar(?User $user, string $accion, ?int $cantidad = null, ?string $nota = null): void
    {
        $this->historial()->create(['user_id' => $user?->id, 'accion' => $accion, 'cantidad' => $cantidad, 'nota' => $nota]);
    }
}
