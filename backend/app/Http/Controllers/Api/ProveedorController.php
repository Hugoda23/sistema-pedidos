<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoItem;
use App\Http\Controllers\Controller;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Services\Avisos;
use App\Services\WhatsApp\WhatsAppCliente;
use App\Support\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lo que ve y hace el proveedor. Nunca se envía el cliente ni el precio.
 */
class ProveedorController extends Controller
{
    private const POR_ATENDER = ['pendiente', 'no_disponible'];

    public function items(Request $request): JsonResponse
    {
        $items = $this->filtrar($request)
            ->with(['pedido:id,numero,vendedor_id', 'pedido.vendedor:id,name', 'lote:id,numero'])
            ->orderByRaw("CASE WHEN estado = 'no_disponible' AND fecha_ingreso <= CURRENT_DATE THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->paginate(min((int) $request->query('por_pagina', 50), 200));

        return response()->json($items->through(fn (PedidoItem $i) => self::datos($i)));
    }

    /** Suma por código: lo que hay que despachar en total. */
    public function consolidado(Request $request): JsonResponse
    {
        $filas = $this->filtrar($request)
            ->selectRaw('codigo, MIN(descripcion) AS descripcion, SUM(cantidad) AS cantidad, COUNT(*) AS lineas')
            ->groupBy('codigo')
            ->orderBy('codigo')
            ->get();

        return response()->json($filas);
    }

    /** Lo mismo que se ve en pantalla, en Excel: detalle y suma por código. Sin clientes ni precios. */
    public function exportar(Request $request): StreamedResponse
    {
        $items = $this->filtrar($request)->with(['pedido:id,numero,vendedor_id', 'pedido.vendedor:id,name', 'lote:id,numero'])->orderBy('id')->get();
        $consolidado = $this->filtrar($request)
            ->selectRaw('codigo, MIN(descripcion) AS descripcion, SUM(cantidad) AS cantidad, COUNT(*) AS lineas')
            ->groupBy('codigo')->orderBy('codigo')->get();

        return (new Excel)
            ->hoja('Repuestos solicitados', ['Código', 'Descripción', 'Cantidad', 'Vendedor', 'Pedido', 'Pedido al proveedor', 'Solicitado', 'Estado', 'Ingreso aprox.', 'Nota'],
                $items->map(fn (PedidoItem $i) => [
                    $i->codigo, $i->descripcion, $i->cantidad, $i->pedido->vendedor->name, $i->pedido->numero,
                    $i->lote ? "#{$i->lote->numero}" : '', FechaExcel::PHPToExcel($i->created_at), $i->estado->etiqueta(),
                    $i->fecha_ingreso ? FechaExcel::PHPToExcel($i->fecha_ingreso) : null, $i->nota_proveedor,
                ]), [6 => Excel::FECHA_HORA, 8 => Excel::FECHA])
            ->hoja('Sumado por código', ['Código', 'Descripción', 'Unidades', 'Líneas'],
                $consolidado->map(fn ($f) => [$f->codigo, $f->descripcion, (int) $f->cantidad, (int) $f->lineas]))
            ->descargar('repuestos-'.now()->format('Y-m-d').'.xlsx');
    }

    public function lotes(): JsonResponse
    {
        $lotes = Lote::query()
            ->where('tipo', Lote::CLIENTES)
            ->with('cerradoPor:id,name')
            ->withCount([
                'items',
                'items as por_atender' => fn ($q) => $q->whereIn('estado', self::POR_ATENDER),
            ])
            ->withSum('items as unidades', 'cantidad')
            ->orderByRaw("CASE WHEN estado = 'abierto' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json($lotes->map(fn (Lote $l) => [
            'id' => $l->id,
            'numero' => $l->numero,
            'estado' => $l->estado,
            'abierto_en' => $l->created_at->toIso8601String(),
            'cerrado_en' => $l->cerrado_en?->toIso8601String(),
            'cerrado_por' => $l->cerradoPor?->name,
            'lineas' => $l->items_count,
            'unidades' => (int) $l->unidades,
            'por_atender' => $l->por_atender,
        ]));
    }

    /**
     * Reporte de un pedido de clientes para el proveedor: sumado por código, detalle y movimientos.
     * Igual que su tabla, sin nombres de clientes ni precios. HTML para la vista previa, o PDF.
     */
    public function reporte(Request $request, Lote $lote)
    {
        abort_unless($lote->tipo === Lote::CLIENTES, 404);

        $items = $lote->items()->with(['pedido:id,numero,vendedor_id', 'pedido.vendedor:id,name', 'historial.user:id,name'])
            ->orderBy('codigo')->orderBy('id')->get();
        $movimientos = $items->flatMap(fn (PedidoItem $i) => $i->historial->map(fn ($h) => ['item' => $i, 'h' => $h]))
            ->sortBy(fn ($m) => [$m['h']->created_at->timestamp, $m['h']->id])->values();
        $vista = view('reportes.pedido-clientes', ['lote' => $lote->load('cerradoPor'), 'items' => $items, 'movimientos' => $movimientos]);

        if ($request->query('formato') === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($vista->render())->setPaper('letter')
                ->download("pedido-{$lote->numero}.pdf");
        }

        return response($vista->render())->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** El proveedor cierra el pedido de clientes (el de inventario se cierra aparte). */
    public function cerrarLote(Request $request, Avisos $avisos): JsonResponse
    {
        $lote = Lote::abiertoDe(Lote::CLIENTES);

        if (! $lote || ! $lote->items()->exists()) {
            throw ValidationException::withMessages(['lote' => 'No hay repuestos solicitados en el pedido abierto.']);
        }

        $lote->update(['estado' => 'cerrado', 'cerrado_por' => $request->user()->id, 'cerrado_en' => now()]);

        return response()->json([
            'id' => $lote->id,
            'numero' => $lote->numero,
            'lineas' => $lote->items()->count(),
            'whatsapp' => $avisos->loteCerrado($lote, $request->user()) + $this->vinculado($request),
        ]);
    }

    /** Cambio de estado de una o varias líneas a la vez. */
    public function cambiarEstado(Request $request, Avisos $avisos): JsonResponse
    {
        $datos = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'estado' => ['required', Rule::in(['enviado', 'no_disponible', 'descontinuado'])],
            'fecha_ingreso' => ['nullable', 'required_if:estado,no_disponible', 'date', 'after_or_equal:today'],
            'nota' => ['nullable', 'string', 'max:500'],
        ], [
            'fecha_ingreso.required_if' => 'Indica la fecha aproximada de ingreso.',
            'fecha_ingreso.after_or_equal' => 'La fecha de ingreso no puede ser anterior a hoy.',
        ]);
        $nuevo = EstadoItem::from($datos['estado']);

        $items = DB::transaction(function () use ($datos, $nuevo, $request) {
            $items = PedidoItem::whereIn('id', $datos['ids'])->lockForUpdate()->get();
            if ($items->count() !== count(array_unique($datos['ids']))) {
                throw ValidationException::withMessages(['ids' => 'Alguna línea ya no existe; recarga la tabla.']);
            }

            foreach ($items as $item) {
                if (! in_array($nuevo, $item->estado->siguientesProveedor(), true)) {
                    throw ValidationException::withMessages([
                        'ids' => "{$item->codigo}: no se puede pasar de «{$item->estado->etiqueta()}» a «{$nuevo->etiqueta()}». Recarga la tabla.",
                    ]);
                }
                $item->nota_proveedor = $datos['nota'] ?? $item->nota_proveedor;
                $item->cambiarEstado($nuevo, $request->user(), $datos['nota'] ?? null, $datos['fecha_ingreso'] ?? null);
            }

            Pedido::whereIn('id', $items->pluck('pedido_id')->unique())->get()->each->recalcular();

            return $items;
        });

        return response()->json([
            'actualizados' => $items->count(),
            'whatsapp' => $avisos->proveedorActualizo($items, $nuevo, $request->user(), $datos['nota'] ?? null) + $this->vinculado($request),
        ]);
    }

    /** Para avisarle en pantalla si sus mensajes van a quedar detenidos. */
    private function vinculado(Request $request): array
    {
        return ['vinculado' => app(WhatsAppCliente::class)->estado($request->user()->id)['status'] === 'conectado'];
    }

    private function filtrar(Request $request): Builder
    {
        $estado = $request->query('estado', 'por_atender');
        $q = trim((string) $request->query('q'));

        return PedidoItem::query()
            ->when($estado === 'por_atender', fn ($w) => $w->whereIn('estado', self::POR_ATENDER))
            ->when($estado === 'revisar', fn ($w) => $w->where('estado', 'no_disponible')->whereDate('fecha_ingreso', '<=', today()))
            ->when(! in_array($estado, ['por_atender', 'revisar', 'todos'], true), fn ($w) => $w->where('estado', $estado))
            // Lo anulado por el vendedor no le interesa al proveedor.
            ->when($estado === 'todos', fn ($w) => $w->where('estado', '!=', 'anulado'))
            ->when($request->query('lote_id'), fn ($w, $l) => $w->where('lote_id', $l))
            ->when($request->query('vendedor_id'), fn ($w, $v) => $w->whereHas('pedido', fn ($p) => $p->where('vendedor_id', $v)))
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w
                ->where('codigo', 'like', '%'.PedidoItem::normalizarCodigo($q).'%')
                ->orWhereRaw('descripcion ILIKE ?', ["%{$q}%"])));
    }

    private static function datos(PedidoItem $i): array
    {
        return [
            'id' => $i->id,
            'codigo' => $i->codigo,
            'descripcion' => $i->descripcion,
            'cantidad' => $i->cantidad,
            'vendedor' => $i->pedido->vendedor->name,
            'pedido' => $i->pedido->numero,
            'solicitado' => $i->created_at->toIso8601String(),
            'estado' => $i->estado->value,
            'fecha_ingreso' => $i->fecha_ingreso?->format('Y-m-d'),
            'revisar_ingreso' => $i->estado === EstadoItem::NoDisponible && $i->fecha_ingreso?->lte(today()),
            'nota_proveedor' => $i->nota_proveedor,
            'lote_id' => $i->lote_id,
            'lote_numero' => $i->lote?->numero,
            'acciones' => array_map(fn (EstadoItem $e) => $e->value, $i->estado->siguientesProveedor()),
        ];
    }
}
