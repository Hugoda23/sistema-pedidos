<?php

namespace App\Support;

/**
 * Enlaces wa.me: abren el WhatsApp de quien hace clic con el mensaje ya escrito;
 * solo falta presionar enviar. No hay sesiones ni servicio aparte.
 */
class WhatsApp
{
    /** 5555-1234 → 50255551234; +1 305… se respeta. Null si no hay un número usable. */
    public static function numero(?string $telefono): ?string
    {
        if (! $telefono) {
            return null;
        }
        $digitos = preg_replace('/^00/', '', preg_replace('/\D/', '', $telefono));

        if (strlen($digitos) === 8) {
            return config('services.whatsapp.pais', '502').$digitos;
        }

        return strlen($digitos) > 8 ? $digitos : null;
    }

    public static function enlace(?string $telefono, string $texto): ?string
    {
        $numero = self::numero($telefono);

        return $numero ? "https://wa.me/{$numero}?text=".rawurlencode($texto) : null;
    }
}
