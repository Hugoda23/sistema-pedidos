<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItemHistorial extends Model
{
    protected $table = 'pedido_item_historial';

    public const UPDATED_AT = null;

    protected $fillable = ['pedido_item_id', 'user_id', 'estado_anterior', 'estado_nuevo', 'fecha_ingreso', 'nota'];

    protected function casts(): array
    {
        return ['fecha_ingreso' => 'date:Y-m-d', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
