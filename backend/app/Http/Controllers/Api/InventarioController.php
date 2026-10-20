<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoItem;
use App\Http\Controllers\Controller;
use App\Models\InventarioItem;
use App\Models\Lote;
use App\Models\PedidoItem;
use App\Services\Avisos;
use App\Support\Excel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pedido de inventario: lista compartida por todos los vendedores. El proveedor
 * la atiende; lo enviado sale de la lista (queda en «Enviados»).
 */
class InventarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $this->filtrar($request)
            ->with(['creador:id,name', 'atendidoPor:id,name', 'lote:id,estado', 'historial.user:id,name'])
            ->orderByRaw("CASE WHEN estado = 'no_disponible' AND fecha_ingreso <= CURRENT_DATE THEN 0 ELSE 1 END")
            ->orderByDesc($request->query('estado') === 'atendidos' ? 'atendido_en' : 'id')
            ->paginate(min((int) $request->query('por_pagina', 50), 200));

        return response()->json($items->through(fn (InventarioItem $i) => $this->datos($i, $request)));
    }

    /**
     * Agrega un repuesto. Si el código ya está en la lista responde 409 con la línea
     * existente; con `sumar=true` el vendedor confirma que quiere agregar más.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:60'],
            'descripcion' => ['required', 'string', 'max:200'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'sumar' => ['boolean'],
        ]);
        $codigo = PedidoItem::normalizarCodigo($datos['codigo']);
        $user = $request->user();

        $resultado = DB::transaction(function () use ($datos, $codigo, $user) {
            $existente = InventarioItem::activos()->where('codigo', $codigo)->with(['creador:id,name', 'lote'])->lockForUpdate()->latest('id')->first();

            if ($existente && empty($datos['sumar'])) {
                return ['duplicado' => $existente];
            }

            // Si la línea anterior todavía no la toma el proveedor, se le suma; si ya la tomó, va una línea nueva.
            if ($existente && $existente->editable()) {
                $existente->increment('cantidad', $datos['cantidad']);
                $existente->registrar($user, 'sumado', $datos['cantidad']);

                return ['item' => $existente->fresh(), 'sumado' => true];
            }

            $item = InventarioItem::create([
                'codigo' => $codigo,
                'descripcion' => trim($datos['descripcion']),
                'cantidad' => $datos['cantidad'],
                'estado' => EstadoItem::Pendiente,
                'lote_id' => Lote::actual()->id,
                'creado_por' => $user->id,
            ]);
            $item->registrar($user, 'agregado', $datos['cantidad'], $existente ? "Adicional a lo ya pedido (#{$existente->id})" : null);

            return ['item' => $item, 'sumado' => false];
        });

        if (isset($resultado['duplicado'])) {
            $e = $resultado['duplicado'];

            return response()->json([
                'message' => "El código {$codigo} ya está agregado.",
                'existente' => [
                    'id' => $e->id,
                    'codigo' => $e->codigo,
                    'descripcion' => $e->descripcion,
                    'cantidad' => $e->cantidad,
                    'estado' => $e->estado->value,
                    'fecha_ingreso' => $e->fecha_ingreso?->format('Y-m-d'),
                    'agregado_por' => $e->creador->name,
                    'en_pedido_abierto' => $e->editable(),
                ],
            ], 409);
        }

        return response()->json(['sumado' => $resultado['sumado']] + $this->datos($resultado['item']->load(['creador', 'lote', 'historial.user']), $request), 201);
    }

    public function update(Request $request, InventarioItem $item): JsonResponse
    {
        $this->editable($item);
        $datos = $request->validate([
            'descripcion' => ['sometimes', 'required', 'string', 'max:200'],
            'cantidad' => ['sometimes', 'required', 'integer', 'min:1', 'max:9999'],
        ]);

        $cambios = [];
        if (isset($datos['cantidad']) && (int) $datos['cantidad'] !== $item->cantidad) {
            $cambios[] = "cantidad {$item->cantidad} → {$datos['cantidad']}";
        }
        if (isset($datos['descripcion']) && trim($datos['descripcion']) !== $item->descripcion) {
            $cambios[] = "descripción «{$item->descripcion}» → «".trim($datos['descripcion']).'»';
        }

        $item->update($datos);
        if ($cambios) {
            $item->registrar($request->user(), 'editado', $item->cantidad, ucfirst(implode('; ', $cambios)));
        }

        return response()->json($this->datos($item->load(['creador', 'lote', 'historial.user']), $request));
    }

    /** Quitar no borra: queda como «anulado» para que el reporte del pedido muestre el movimiento. */
    public function destroy(Request $request, InventarioItem $item): JsonResponse
    {
        $this->editable($item);
        $item->update(['estado' => EstadoItem::Anulado]);
        $item->registrar($request->user(), 'quitado', $item->cantidad);

        return response()->json(null, 204);
    }

    /** El proveedor marca una o varias líneas. Lo enviado y lo descontinuado salen de la lista. */
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
        $user = $request->user();

        $items = DB::transaction(function () use ($datos, $nuevo, $user) {
            $items = InventarioItem::whereIn('id', $datos['ids'])->lockForUpdate()->get();
            if ($items->count() !== count(array_unique($datos['ids']))) {
                throw ValidationException::withMessages(['ids' => 'Alguna línea ya no existe; recarga la tabla.']);
            }

            foreach ($items as $item) {
                if (! in_array($nuevo, $item->estado->siguientesProveedor(), true)) {
                    throw ValidationException::withMessages([
                        'ids' => "{$item->codigo}: no se puede pasar de «{$item->estado->etiqueta()}» a «{$nuevo->etiqueta()}». Recarga la tabla.",
                    ]);
                }
                $item->update([
                    'estado' => $nuevo,
                    'fecha_ingreso' => $nuevo === EstadoItem::NoDisponible ? $datos['fecha_ingreso'] : null,
                    'nota_proveedor' => $datos['nota'] ?? $item->nota_proveedor,
                    'atendido_por' => $user->id,
                    'atendido_en' => now(),
                ]);
                $item->registrar($user, $nuevo->value, null, $datos['nota'] ?? null);
            }

            return $items;
        });

        return response()->json([
            'actualizados' => $items->count(),
            'whatsapp' => $avisos->inventarioActualizado($items, $nuevo, $user, $datos['nota'] ?? null),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $items = $this->filtrar($request)->with(['creador:id,name', 'lote:id'])->orderBy('codigo')->get();
        $f = fn ($d) => $d ? FechaExcel::PHPToExcel($d) : null;

        return (new Excel)
            ->hoja('Pedido de inventario', ['Código', 'Descripción', 'Cantidad', 'Agregado por', 'Fecha', 'Pedido al proveedor', 'Estado', 'Ingreso aprox.', 'Nota del proveedor'],
                $items->map(fn (InventarioItem $i) => [
                    $i->codigo, $i->descripcion, $i->cantidad, $i->creador->name, $f($i->created_at),
                    $i->lote_id ? "#{$i->lote_id}" : '', $i->estado->etiqueta(), $f($i->fecha_ingreso), $i->nota_proveedor,
                ]), [4 => Excel::FECHA_HORA, 7 => Excel::FECHA])
            ->descargar('inventario-'.now()->format('Y-m-d').'.xlsx');
    }

    /** Pedidos al proveedor que llevan inventario (y el abierto, aunque aún no tenga). */
    public function pedidos(): JsonResponse
    {
        $lotes = Lote::query()
            ->where(fn ($q) => $q->where('estado', 'abierto')->orWhereHas('inventario'))
            ->with('cerradoPor:id,name')
            ->withCount([
                'inventario as repuestos' => fn ($q) => $q->where('estado', '!=', 'anulado'),
                'inventario as enviados' => fn ($q) => $q->where('estado', 'enviado'),
                'inventario as pendientes' => fn ($q) => $q->whereIn('estado', InventarioItem::ACTIVOS),
            ])
            ->withSum(['inventario as unidades' => fn ($q) => $q->where('estado', '!=', 'anulado')], 'cantidad')
            ->orderByRaw("CASE WHEN estado = 'abierto' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json($lotes->map(fn (Lote $l) => [
            'id' => $l->id,
            'abierto' => $l->abierto(),
            'abierto_en' => $l->created_at->toIso8601String(),
            'cerrado_en' => $l->cerrado_en?->toIso8601String(),
            'cerrado_por' => $l->cerradoPor?->name,
            'repuestos' => $l->repuestos,
            'unidades' => (int) $l->unidades,
            'enviados' => $l->enviados,
            'pendientes' => $l->pendientes,
        ]));
    }

    /** Reporte de un pedido: sus repuestos y cada movimiento. HTML para la vista previa, o PDF. */
    public function reporte(Request $request, Lote $lote)
    {
        $items = $lote->inventario()->with(['creador:id,name', 'historial.user:id,name'])->orderBy('codigo')->get();
        $movimientos = $items->flatMap(fn (InventarioItem $i) => $i->historial->map(fn ($h) => ['item' => $i, 'h' => $h]))
            ->sortBy(fn ($m) => [$m['h']->created_at->timestamp, $m['h']->id])->values();
        $vista = view('reportes.inventario', ['lote' => $lote->load('cerradoPor'), 'items' => $items, 'movimientos' => $movimientos]);

        if ($request->query('formato') === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($vista->render())->setPaper('letter')
                ->download("inventario-pedido-{$lote->id}.pdf");
        }

        return response($vista->render())->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function filtrar(Request $request): Builder
    {
        $q = trim((string) $request->query('q'));

        return InventarioItem::query()
            ->when(in_array($request->query('estado', 'activos'), ['activos', ''], true), fn ($w) => $w->activos())
            ->when($request->query('estado') === 'atendidos', fn ($w) => $w->whereIn('estado', ['enviado', 'descontinuado']))
            ->when($request->query('estado') === 'revisar', fn ($w) => $w->where('estado', 'no_disponible')->whereDate('fecha_ingreso', '<=', today()))
            ->when($request->query('lote_id'), fn ($w, $l) => $w->where('lote_id', $l))
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w
                ->where('codigo', 'like', '%'.PedidoItem::normalizarCodigo($q).'%')
                ->orWhereRaw('descripcion ILIKE ?', ["%{$q}%"])));
    }

    private function editable(InventarioItem $item): void
    {
        if (! $item->editable()) {
            throw ValidationException::withMessages(['item' => 'El proveedor ya tomó este repuesto; ya no se puede cambiar.']);
        }
    }

    private function datos(InventarioItem $i, Request $request): array
    {
        $user = $request->user();
        $aportes = $i->historial->whereIn('accion', ['agregado', 'sumado'])
            ->groupBy(fn ($h) => $h->user?->name ?? '—')
            ->map(fn ($hs, $nombre) => ['nombre' => $nombre, 'cantidad' => $hs->sum('cantidad')])->values();

        return [
            'id' => $i->id,
            'codigo' => $i->codigo,
            'descripcion' => $i->descripcion,
            'cantidad' => $i->cantidad,
            'agregado_por' => $i->creador->name,
            'aportes' => $aportes,
            'fecha' => $i->created_at->toIso8601String(),
            'estado' => $i->estado->value,
            'fecha_ingreso' => $i->fecha_ingreso?->format('Y-m-d'),
            'revisar_ingreso' => $i->estado === EstadoItem::NoDisponible && $i->fecha_ingreso?->lte(today()),
            'nota_proveedor' => $i->nota_proveedor,
            'atendido_por' => $i->atendidoPor?->name,
            'atendido_en' => $i->atendido_en?->toIso8601String(),
            'lote' => $i->lote ? ['id' => $i->lote->id, 'abierto' => $i->lote->abierto()] : null,
            'editable' => $i->editable() && $user->can('inventario.gestionar'),
            'acciones' => $user->can('inventario.atender')
                ? array_map(fn (EstadoItem $e) => $e->value, $i->estado->siguientesProveedor()) : [],
        ];
    }
}
