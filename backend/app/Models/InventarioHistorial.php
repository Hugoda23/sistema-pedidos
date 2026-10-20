<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioHistorial extends Model
{
    protected $table = 'inventario_historial';

    public const UPDATED_AT = null;

    protected $fillable = ['inventario_item_id', 'user_id', 'accion', 'cantidad', 'nota'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public const ACCIONES = [
        'agregado' => 'Agregado',
        'sumado' => 'Sumó cantidad',
        'editado' => 'Editado',
        'quitado' => 'Quitado',
        'enviado' => 'Enviado',
        'no_disponible' => 'No disponible',
        'descontinuado' => 'Descontinuado',
    ];

    public function etiqueta(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
