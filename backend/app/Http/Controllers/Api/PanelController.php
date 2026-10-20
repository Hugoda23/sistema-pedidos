<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\PedidoItemHistorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Números de inicio: cada bloque aparece solo si el usuario tiene el permiso. */
class PanelController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $panel = [];

        if ($user->can('pedidos.ver')) {
            $pedidos = fn () => Pedido::query()->visiblesPara($user);
            $lineas = fn () => PedidoItem::query()->whereHas('pedido', fn ($q) => $q->visiblesPara($user));

            $panel['ventas'] = [
                'activos' => $pedidos()->whereIn('estado', ['pendiente', 'en_proceso', 'listo'])->count(),
                'listos' => $pedidos()->where('estado', 'listo')->count(),
                'por_cobrar' => number_format((float) $pedidos()->where('estado', '!=', 'anulado')->whereColumn('total', '>', 'pagado')->sum(DB::raw('total - pagado')), 2, '.', ''),
                'con_saldo' => $pedidos()->where('estado', '!=', 'anulado')->whereColumn('total', '>', 'pagado')->count(),
                'sin_existencia' => $lineas()->where('estado', 'no_disponible')->count(),
                'ingreso_cumplido' => $lineas()->where('estado', 'no_disponible')->whereDate('fecha_ingreso', '<=', today())->count(),
                'mes_total' => number_format((float) $pedidos()->where('estado', '!=', 'anulado')->where('created_at', '>=', now()->startOfMonth())->sum('total'), 2, '.', ''),
                'mes_pedidos' => $pedidos()->where('estado', '!=', 'anulado')->where('created_at', '>=', now()->startOfMonth())->count(),
                'lista_listos' => $pedidos()->with('cliente:id,nombre')->where('estado', 'listo')->orderBy('updated_at')->limit(8)->get()
                    ->map(fn (Pedido $p) => ['id' => $p->id, 'numero' => $p->numero, 'cliente' => $p->cliente->nombre, 'saldo' => $p->saldo()]),
                'lista_sin_existencia' => $lineas()->with('pedido:id,numero')->where('estado', 'no_disponible')->orderBy('fecha_ingreso')->limit(8)->get()
                    ->map(fn (PedidoItem $i) => [
                        'pedido_id' => $i->pedido_id, 'pedido' => $i->pedido->numero, 'codigo' => $i->codigo,
                        'descripcion' => $i->descripcion, 'fecha_ingreso' => $i->fecha_ingreso?->format('Y-m-d'),
                        'cumplida' => $i->fecha_ingreso?->lte(today()) ?? false,
                    ]),
            ];
        }

        if ($user->can('proveedor.ver')) {
            $abierto = Lote::where('tipo', Lote::CLIENTES)->where('estado', 'abierto')->withCount('items')->withSum('items as unidades', 'cantidad')->first();
            $inventario = Lote::abiertoDe(Lote::INVENTARIO);

            $panel['proveedor'] = [
                'por_atender' => PedidoItem::where('estado', 'pendiente')->count(),
                'sin_existencia' => PedidoItem::where('estado', 'no_disponible')->count(),
                'revisar' => PedidoItem::where('estado', 'no_disponible')->whereDate('fecha_ingreso', '<=', today())->count(),
                'enviados_semana' => PedidoItemHistorial::where('estado_nuevo', 'enviado')->where('created_at', '>=', now()->subDays(7))->count(),
                'inventario_abierto' => $inventario ? ['numero' => $inventario->numero, 'repuestos' => $inventario->inventario()->whereIn('estado', \App\Models\InventarioItem::ACTIVOS)->count()] : null,
                'abierto' => $abierto ? ['id' => $abierto->id, 'numero' => $abierto->numero, 'lineas' => $abierto->items_count, 'unidades' => (int) $abierto->unidades, 'desde' => $abierto->created_at->toIso8601String()] : null,
                'lista_revisar' => PedidoItem::with('pedido.vendedor:id,name')->where('estado', 'no_disponible')
                    ->whereDate('fecha_ingreso', '<=', today())->orderBy('fecha_ingreso')->limit(8)->get()
                    ->map(fn (PedidoItem $i) => [
                        'codigo' => $i->codigo, 'descripcion' => $i->descripcion, 'cantidad' => $i->cantidad,
                        'vendedor' => $i->pedido->vendedor->name, 'fecha_ingreso' => $i->fecha_ingreso->format('Y-m-d'),
                    ]),
            ];
        }

        return response()->json($panel);
    }
}
