<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWhatsapp extends Model
{
    protected $table = 'mensajes_whatsapp';

    protected $fillable = ['remitente_id', 'destinatario_id', 'cliente_id', 'pedido_id', 'telefono', 'tipo',
        'mensaje', 'adjunto', 'adjunto_nombre', 'estado', 'error', 'intentos', 'enviado_en'];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime'];
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
