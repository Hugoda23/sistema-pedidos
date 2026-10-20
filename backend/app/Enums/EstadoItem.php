<?php

namespace App\Enums;

/**
 * Ciclo de vida de una línea de pedido.
 *   pendiente → enviado → recibido → entregado          (camino normal)
 *   pendiente → no_disponible (con fecha aprox.) → enviado
 *   pendiente / no_disponible → descontinuado            (lo marca el proveedor)
 *   pendiente / no_disponible → anulado                  (el cliente ya no lo quiere)
 */
enum EstadoItem: string
{
    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case NoDisponible = 'no_disponible';
    case Descontinuado = 'descontinuado';
    case Recibido = 'recibido';
    case Entregado = 'entregado';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Enviado => 'Enviado',
            self::NoDisponible => 'No disponible',
            self::Descontinuado => 'Descontinuado',
            self::Recibido => 'Recibido',
            self::Entregado => 'Entregado',
            self::Anulado => 'Anulado',
        };
    }

    /** Cambios que puede hacer el vendedor desde su pedido. */
    public function siguientesVendedor(): array
    {
        return match ($this) {
            self::Pendiente, self::NoDisponible => [self::Anulado],
            self::Enviado => [self::Recibido],
            self::Recibido => [self::Entregado],
            default => [],
        };
    }

    /** Cambios que puede hacer el proveedor desde su tabla. */
    public function siguientesProveedor(): array
    {
        return match ($this) {
            self::Pendiente => [self::Enviado, self::NoDisponible, self::Descontinuado],
            self::NoDisponible => [self::Enviado, self::NoDisponible, self::Descontinuado],
            default => [],
        };
    }

    /** Las líneas que ya no se cobran. */
    public function cuentaEnTotal(): bool
    {
        return ! in_array($this, [self::Anulado, self::Descontinuado], true);
    }
}
