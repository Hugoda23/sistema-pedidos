<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class WhatsAppError extends RuntimeException
{
    /** permanente = reintentar no lo arregla (número inválido, sin WhatsApp). */
    public function __construct(string $mensaje, public readonly bool $permanente = false, public readonly bool $noVinculado = false)
    {
        parent::__construct($mensaje);
    }
}
