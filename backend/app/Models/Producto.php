<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = ['codigo', 'nombre', 'precio_venta', 'descontinuado'];

    protected function casts(): array
    {
        return ['precio_venta' => 'decimal:2', 'descontinuado' => 'boolean'];
    }

    /** "lod-123 " y "LOD-123" son el mismo repuesto. */
    public static function normalizarCodigo(string $codigo): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $codigo));
    }

    protected function codigo(): Attribute
    {
        return Attribute::set(fn (string $v) => self::normalizarCodigo($v));
    }
}
