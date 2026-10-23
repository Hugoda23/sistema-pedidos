<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Pedido consolidado al proveedor. Hay dos tipos que se abren y cierran por separado:
 *   clientes   → líneas de los pedidos de clientes («Pedido #13»)
 *   inventario → repuestos que faltan en la tienda («Inventario #4»)
 */
class Lote extends Model
{
    public const CLIENTES = 'clientes';

    public const INVENTARIO = 'inventario';

    protected $fillable = ['tipo', 'numero', 'estado', 'cerrado_por', 'cerrado_en'];

    protected function casts(): array
    {
        return ['cerrado_en' => 'datetime', 'numero' => 'integer'];
    }

    /** El lote abierto del tipo donde caen las líneas nuevas; si no hay, se abre uno. */
    public static function actual(string $tipo = self::CLIENTES): self
    {
        $abierto = self::abiertoDe($tipo);
        if ($abierto) {
            return $abierto;
        }

        try {
            return self::create([
                'tipo' => $tipo,
                'estado' => 'abierto',
                'numero' => (int) self::where('tipo', $tipo)->max('numero') + 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otro usuario lo abrió al mismo tiempo.
            return self::abiertoDe($tipo) ?? throw new \RuntimeException('No se pudo abrir el pedido.');
        }
    }

    public static function abiertoDe(string $tipo): ?self
    {
        return self::where('tipo', $tipo)->where('estado', 'abierto')->first();
    }

    /** El último pedido cerrado del tipo: ahí van los repuestos que se le piden al proveedor por teléfono. */
    public static function ultimoCerrado(string $tipo): ?self
    {
        return self::where('tipo', $tipo)->where('estado', 'cerrado')->orderByDesc('numero')->first();
    }

    /** Nota del historial para lo agregado después del cierre. */
    public static function notaTrasCierre(self $lote, ?string $nota): string
    {
        return "Agregado a {$lote->nombre()} ya cerrado, pedido por teléfono".($nota ? ": {$nota}" : '');
    }

    public function abierto(): bool
    {
        return $this->estado === 'abierto';
    }

    /** «Pedido #13» o «Inventario #4». */
    public function nombre(): string
    {
        return ($this->tipo === self::INVENTARIO ? 'Inventario' : 'Pedido').' #'.$this->numero;
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
