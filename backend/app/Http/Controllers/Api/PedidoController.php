<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoItem;
use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Services\Avisos;
use App\Services\PedidoService;
use App\Support\WhatsApp;
use App\Support\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    public function __construct(private PedidoService $servicio) {}

    public function index(Request $request): JsonResponse
    {
        $pedidos = $this->consulta($request)
            ->with(['cliente:id,nombre,telefono', 'vendedor:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('por_pagina', 15), 100));

        return response()->json($pedidos->through(fn (Pedido $p) => [
            'id' => $p->id,
            'numero' => $p->numero,
            'fecha' => $p->created_at->toIso8601String(),
            'cliente' => $p->cliente->nombre,
            'vendedor' => $p->vendedor->name,
            'items' => $p->items_count,
            'total' => $p->total,
            'pagado' => $p->pagado,
            'saldo' => $p->saldo(),
            'estado' => $p->estado,
        ]));
    }

    /** Pedidos con los mismos filtros de la pantalla: una hoja de pedidos y otra con sus líneas. */
    public function exportar(Request $request): StreamedResponse
    {
        $pedidos = $this->consulta($request)
            ->with(['cliente:id,nombre,telefono', 'vendedor:id,name', 'items'])
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        $f = fn ($d) => $d ? FechaExcel::PHPToExcel($d) : null;

        return (new Excel)
            ->hoja('Pedidos', ['Número', 'Fecha', 'Cliente', 'Teléfono', 'Vendedor', 'Líneas', 'Total', 'Pagado', 'Saldo', 'Estado', 'Notas'],
                $pedidos->map(fn (Pedido $p) => [
                    $p->numero, $f($p->created_at), $p->cliente->nombre, $p->cliente->telefono, $p->vendedor->name,
                    $p->items->count(), (float) $p->total, (float) $p->pagado, (float) $p->saldo(),
                    Pedido::ESTADOS[$p->estado] ?? $p->estado, $p->notas,
                ]), [1 => Excel::FECHA, 6 => Excel::QUETZALES, 7 => Excel::QUETZALES, 8 => Excel::QUETZALES])
            ->hoja('Líneas', ['Pedido', 'Cliente', 'Código', 'Descripción', 'Cantidad', 'Precio', 'Subtotal', 'Estado', 'Ingreso aprox.', 'Nota del proveedor'],
                $pedidos->flatMap(fn (Pedido $p) => $p->items->map(fn (PedidoItem $i) => [
                    $p->numero, $p->cliente->nombre, $i->codigo, $i->descripcion, $i->cantidad, (float) $i->precio,
                    $i->cantidad * (float) $i->precio, $i->estado->etiqueta(), $f($i->fecha_ingreso), $i->nota_proveedor,
                ])), [5 => Excel::QUETZALES, 6 => Excel::QUETZALES, 8 => Excel::FECHA])
            ->descargar('pedidos-'.now()->format('Y-m-d').'.xlsx');
    }

    /** Filtros compartidos por el listado y la exportación. */
    private function consulta(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $q = trim((string) $request->query('q'));

        return Pedido::query()
            ->visiblesPara($request->user())
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w
                ->whereRaw('numero ILIKE ?', ["%{$q}%"])
                ->orWhereHas('cliente', fn ($c) => $c->whereRaw('nombre ILIKE ?', ["%{$q}%"]))
                ->orWhereHas('items', fn ($i) => $i->where('codigo', 'like', '%'.mb_strtoupper($q).'%'))))
            ->when($request->query('estado'), fn ($w, $e) => $w->where('estado', $e))
            ->when($request->query('vendedor_id'), fn ($w, $v) => $w->where('vendedor_id', $v))
            ->when($request->query('desde'), fn ($w, $d) => $w->whereDate('created_at', '>=', $d))
            ->when($request->query('hasta'), fn ($w, $h) => $w->whereDate('created_at', '<=', $h))
            ->when($request->boolean('con_saldo'), fn ($w) => $w->whereColumn('total', '>', 'pagado')->where('estado', '!=', 'anulado'));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            ...$this->reglasItem('items.*.'),
        ], ['items.required' => 'Agrega al menos un producto.', 'items.min' => 'Agrega al menos un producto.']);

        $this->validarAnticipo($datos);

        $pedido = $this->servicio->crear($datos, $request->user());

        return response()->json($this->detalle($pedido), 201);
    }

    public function show(Request $request, Pedido $pedido): JsonResponse
    {
        $this->autorizar($request, $pedido);

        return response()->json($this->detalle($pedido));
    }

    public function update(Request $request, Pedido $pedido): JsonResponse
    {
        $this->autorizar($request, $pedido, editar: true);
        $pedido->update($request->validate(['notas' => ['nullable', 'string', 'max:2000']]));

        return response()->json($this->detalle($pedido));
    }

    public function agregarItem(Request $request, Pedido $pedido): JsonResponse
    {
        $this->autorizar($request, $pedido, editar: true);
        $this->abiertoParaCambios($pedido);
        $datos = $request->validate($this->reglasItem());

        DB::transaction(function () use ($pedido, $datos, $request) {
            $this->servicio->agregarItem($pedido, $datos, $request->user());
            $pedido->recalcular();
        });

        return response()->json($this->detalle($pedido->fresh()), 201);
    }

    public function actualizarItem(Request $request, PedidoItem $item): JsonResponse
    {
        $pedido = $item->pedido;
        $this->autorizar($request, $pedido, editar: true);
        $this->abiertoParaCambios($pedido);

        $datos = $request->validate([
            'descripcion' => ['sometimes', 'required', 'string', 'max:200'],
            'cantidad' => ['sometimes', 'required', 'integer', 'min:1', 'max:9999'],
            'precio' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        // El precio es solo nuestro; la cantidad y la descripción ya no, si el proveedor tomó la línea.
        if ((isset($datos['cantidad']) && $datos['cantidad'] != $item->cantidad || isset($datos['descripcion']) && $datos['descripcion'] !== $item->descripcion)
            && ! $item->editable()) {
            throw ValidationException::withMessages(['cantidad' => 'El proveedor ya tomó esta línea; solo se puede cambiar el precio.']);
        }

        $item->update($datos);
        $pedido->recalcular();

        return response()->json($this->detalle($pedido->fresh()));
    }

    public function eliminarItem(Request $request, PedidoItem $item): JsonResponse
    {
        $pedido = $item->pedido;
        $this->autorizar($request, $pedido, editar: true);

        if (! $item->editable()) {
            throw ValidationException::withMessages(['item' => 'El proveedor ya tomó esta línea; anúlala en lugar de quitarla.']);
        }
        if ($pedido->items()->count() === 1) {
            throw ValidationException::withMessages(['item' => 'Es la única línea del pedido; anula el pedido completo.']);
        }

        $item->delete();
        $pedido->recalcular();

        return response()->json($this->detalle($pedido->fresh()));
    }

    /** Cambios de estado que hace el vendedor: recibido, entregado o anular la línea. */
    public function estadoItem(Request $request, PedidoItem $item): JsonResponse
    {
        $pedido = $item->pedido;
        $this->autorizar($request, $pedido, editar: true);

        $datos = $request->validate([
            'estado' => ['required', Rule::enum(EstadoItem::class)],
            'nota' => ['nullable', 'string', 'max:500'],
        ]);
        $nuevo = EstadoItem::from($datos['estado']);

        if (! in_array($nuevo, $item->estado->siguientesVendedor(), true)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede pasar de «{$item->estado->etiqueta()}» a «{$nuevo->etiqueta()}».",
            ]);
        }

        DB::transaction(function () use ($item, $nuevo, $request, $datos, $pedido) {
            $item->cambiarEstado($nuevo, $request->user(), $datos['nota'] ?? null);
            $pedido->recalcular();
        });

        return response()->json($this->detalle($pedido->fresh()));
    }

    public function agregarPago(Request $request, Pedido $pedido): JsonResponse
    {
        $this->autorizar($request, $pedido, editar: true);
        if ($pedido->anulado()) {
            throw ValidationException::withMessages(['monto' => 'El pedido está anulado.']);
        }

        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, (float) $pedido->saldo())],
            'nota' => ['nullable', 'string', 'max:300'],
        ], ['monto.max' => 'El abono no puede ser mayor al saldo (Q :max).']);

        $pedido->pagos()->create($datos + [
            'tipo' => $pedido->pagos()->exists() ? 'abono' : 'anticipo',
            'registrado_por' => $request->user()->id,
        ]);
        $pedido->recalcular();

        return response()->json($this->detalle($pedido->fresh()), 201);
    }

    public function eliminarPago(Request $request, Pago $pago): JsonResponse
    {
        $pedido = Pedido::findOrFail($pago->pedido_id);
        $this->autorizar($request, $pedido);
        abort_unless($request->user()->can('pedidos.anular'), 403, 'No tienes permiso para eliminar pagos.');

        $pago->delete();
        $pedido->recalcular();

        return response()->json($this->detalle($pedido->fresh()));
    }

    public function anular(Request $request, Pedido $pedido): JsonResponse
    {
        $this->autorizar($request, $pedido);
        if ($pedido->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El pedido ya está anulado.']);
        }

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $this->servicio->anular($pedido, $request->user(), $datos['motivo']);

        return response()->json($this->detalle($pedido->fresh()));
    }

    private function reglasItem(string $p = ''): array
    {
        return [
            "{$p}codigo" => ['required', 'string', 'max:60'],
            "{$p}descripcion" => ['required', 'string', 'max:200'],
            "{$p}cantidad" => ['required', 'integer', 'min:1', 'max:9999'],
            "{$p}precio" => ['required', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    private function validarAnticipo(array $datos): void
    {
        $total = collect($datos['items'])->sum(fn ($i) => $i['cantidad'] * $i['precio']);
        if (($datos['anticipo'] ?? 0) > $total + 0.001) {
            throw ValidationException::withMessages(['anticipo' => 'El anticipo no puede ser mayor al total del pedido.']);
        }
    }

    private function autorizar(Request $request, Pedido $pedido, bool $editar = false): void
    {
        abort_unless($pedido->visiblePara($request->user()), 404);
        if ($editar) {
            abort_unless($request->user()->can('pedidos.gestionar'), 403, 'No tienes permiso para modificar pedidos.');
        }
    }

    private function abiertoParaCambios(Pedido $pedido): void
    {
        if (in_array($pedido->estado, ['anulado', 'entregado'], true)) {
            throw ValidationException::withMessages(['pedido' => 'El pedido está '.mb_strtolower(Pedido::ESTADOS[$pedido->estado]).'; ya no se puede modificar.']);
        }
    }

    private function detalle(Pedido $pedido): array
    {
        $pedido->load(['cliente', 'vendedor:id,name', 'anuladoPor:id,name', 'pagos.registradoPor:id,name',
            'items.lote', 'items.historial.user:id,name']);

        $proveedor = User::role('Proveedor')->where('activo', true)->whereNotNull('telefono')->orderBy('id')->first();
        $avisos = app(Avisos::class);

        return [
            'id' => $pedido->id,
            'numero' => $pedido->numero,
            'fecha' => $pedido->created_at->toIso8601String(),
            'estado' => $pedido->estado,
            'cliente' => $pedido->cliente->only(['id', 'nombre', 'telefono', 'moto']),
            'vendedor' => $pedido->vendedor->only(['id', 'name']),
            'notas' => $pedido->notas,
            'total' => $pedido->total,
            'pagado' => $pedido->pagado,
            'saldo' => $pedido->saldo(),
            'anulado' => $pedido->anulado() ? [
                'en' => $pedido->anulado_en->toIso8601String(),
                'por' => $pedido->anuladoPor?->name,
                'motivo' => $pedido->motivo_anulacion,
            ] : null,
            'items' => $pedido->items->map(fn (PedidoItem $i) => [
                'id' => $i->id,
                'codigo' => $i->codigo,
                'descripcion' => $i->descripcion,
                'cantidad' => $i->cantidad,
                'precio' => $i->precio,
                'subtotal' => number_format($i->cantidad * (float) $i->precio, 2, '.', ''),
                'estado' => $i->estado->value,
                'fecha_ingreso' => $i->fecha_ingreso?->format('Y-m-d'),
                'nota_proveedor' => $i->nota_proveedor,
                'preguntar_url' => $proveedor && $i->estado === EstadoItem::NoDisponible
                    ? WhatsApp::enlace($proveedor->telefono, $avisos->preguntaIngreso(collect([$i->setRelation('pedido', $pedido)])))
                    : null,
                'lote' => $i->lote ? ['id' => $i->lote->id, 'estado' => $i->lote->estado] : null,
                'editable' => $i->editable(),
                'acciones' => array_map(fn (EstadoItem $e) => $e->value, $i->estado->siguientesVendedor()),
                'historial' => $i->historial->map(fn ($h) => [
                    'fecha' => $h->created_at->toIso8601String(),
                    'usuario' => $h->user?->name,
                    'de' => $h->estado_anterior,
                    'a' => $h->estado_nuevo,
                    'fecha_ingreso' => $h->fecha_ingreso?->format('Y-m-d'),
                    'nota' => $h->nota,
                ]),
            ]),
            'pagos' => $pedido->pagos->map(fn (Pago $p) => [
                'id' => $p->id,
                'fecha' => $p->created_at->toIso8601String(),
                'monto' => $p->monto,
                'tipo' => $p->tipo,
                'nota' => $p->nota,
                'registrado_por' => $p->registradoPor?->name,
            ]),
        ];
    }
}
