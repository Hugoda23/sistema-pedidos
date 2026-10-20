<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $fillable = ['pedido_id', 'monto', 'tipo', 'nota', 'registrado_por'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
