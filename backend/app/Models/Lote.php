<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

class Lote extends Model
{
    protected $fillable = ['estado', 'cerrado_por', 'cerrado_en'];

    protected function casts(): array
    {
        return ['cerrado_en' => 'datetime'];
    }

    /** El lote abierto donde caen las líneas nuevas; si no hay, se abre uno. */
    public static function actual(): self
    {
        try {
            return self::firstOrCreate(['estado' => 'abierto']);
        } catch (UniqueConstraintViolationException) {
            // Otro vendedor lo abrió al mismo tiempo.
            return self::where('estado', 'abierto')->firstOrFail();
        }
    }

    public function abierto(): bool
    {
        return $this->estado === 'abierto';
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function inventario(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}
