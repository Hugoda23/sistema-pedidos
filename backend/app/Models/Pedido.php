<?php

namespace App\Models;

use App\Enums\EstadoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use HasFactory;

    public const ESTADOS = [
        'pendiente' => 'Pendiente',     // nada enviado todavía
        'en_proceso' => 'En proceso',   // algo enviado, sin existencia, etc.
        'listo' => 'Listo para entregar',
        'entregado' => 'Entregado',
        'sin_productos' => 'Sin productos', // todo anulado o descontinuado
        'anulado' => 'Anulado',
    ];

    protected $fillable = ['cliente_id', 'vendedor_id', 'notas', 'estado', 'total', 'pagado',
        'anulado_en', 'anulado_por', 'motivo_anulacion'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'pagado' => 'decimal:2', 'anulado_en' => 'datetime'];
    }

    /** P-00001, P-00002…: sale del id, así no hay dos iguales. */
    public function asignarNumero(): void
    {
        $this->numero = 'P-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
        $this->save();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class)->orderBy('id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->orderBy('id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /** Pedidos que el usuario puede ver: los suyos, o todos con el permiso. */
    public function scopeVisiblesPara(Builder $q, User $user): Builder
    {
        return $user->can('pedidos.ver_todos') ? $q : $q->where('vendedor_id', $user->id);
    }

    public function visiblePara(User $user): bool
    {
        return $user->can('pedidos.ver_todos') || $this->vendedor_id === $user->id;
    }

    public function anulado(): bool
    {
        return $this->anulado_en !== null;
    }

    public function saldo(): string
    {
        return number_format(max(0, (float) $this->total - (float) $this->pagado), 2, '.', '');
    }

    /** Recalcula total, pagado y estado a partir de líneas y pagos. */
    public function recalcular(): void
    {
        $items = $this->items()->get();
        $centavos = 0;
        foreach ($items as $i) {
            if ($i->estado->cuentaEnTotal()) {
                $centavos += $i->cantidad * (int) round((float) $i->precio * 100);
            }
        }

        $this->total = $centavos / 100;
        $this->pagado = (float) $this->pagos()->sum('monto');
        $this->estado = $this->calcularEstado($items->pluck('estado')->all());
        $this->save();
    }

    /** @param EstadoItem[] $estados */
    private function calcularEstado(array $estados): string
    {
        if ($this->anulado()) {
            return 'anulado';
        }

        $activos = array_filter($estados, fn (EstadoItem $e) => $e->cuentaEnTotal());
        if (! $activos) {
            return $estados ? 'sin_productos' : 'pendiente';
        }

        $todos = fn (array $permitidos) => ! array_diff(array_map(fn ($e) => $e->value, $activos), $permitidos);

        return match (true) {
            $todos(['entregado']) => 'entregado',
            $todos(['recibido', 'entregado']) => 'listo',
            $todos(['pendiente']) => 'pendiente',
            default => 'en_proceso',
        };
    }
}
