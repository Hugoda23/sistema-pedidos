@php
    use App\Enums\EstadoItem;
    $neg = config('negocio');
    $logo = is_file($neg['logo']) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($neg['logo'])) : null;
    $activos = $items->filter(fn ($i) => $i->estado !== EstadoItem::Anulado);
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $lote->nombre() }}</title>
<style>
    @page { size: letter; margin: 14mm 14mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
    @media screen { body { padding: 28px; } }
    .hoja { max-width: 760px; margin: 0 auto; }
    table { width: 100%; border-collapse: collapse; }
    .enc td { vertical-align: top; }
    .logo { width: 92px; padding-right: 10px; }
    .logo img { width: 84px; height: 84px; }
    .negocio { font-size: 17px; font-weight: bold; color: #0f172a; }
    .gris { color: #64748b; }
    .titulo { text-align: right; }
    .titulo .doc { font-size: 11px; letter-spacing: 1px; color: #64748b; text-transform: uppercase; }
    .titulo .num { font-size: 20px; font-weight: bold; color: #1d4ed8; }
    .caja { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; margin-top: 12px; }
    .resumen td { text-align: center; padding: 2px 4px; }
    .resumen .n { font-size: 15px; font-weight: bold; }
    h3 { font-size: 11px; margin: 16px 0 6px; color: #334155; }
    .tabla th { background: #e2e8f0; text-align: left; font-size: 9px; text-transform: uppercase; color: #334155; padding: 5px 6px; }
    .tabla td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .der { text-align: right; white-space: nowrap; }
    .cod { font-family: "DejaVu Sans Mono", monospace; font-size: 9px; }
    .quitado td { color: #94a3b8; }
    .tras { color: #b45309; font-size: 9px; }
    .pie { margin-top: 22px; text-align: center; color: #64748b; font-size: 9px; border-top: 1px solid #e2e8f0; padding-top: 8px; }
</style>
</head>
<body>
<div class="hoja">
    <table class="enc">
        <tr>
            @if ($logo)<td class="logo"><img src="{{ $logo }}" alt="{{ $neg['nombre'] }}"></td>@endif
            <td>
                <div class="negocio">{{ $neg['nombre'] }}</div>
                <div class="gris">Reporte de pedido de inventario</div>
            </td>
            <td class="titulo">
                <div class="doc">Pedido de inventario</div>
                <div class="num">#{{ $lote->numero }}</div>
                <div class="gris">Abierto el {{ $lote->created_at->format('d/m/Y H:i') }}</div>
                <div class="gris">
                    @if ($lote->abierto()) Sigue abierto
                    @else Cerrado el {{ $lote->cerrado_en?->format('d/m/Y H:i') }}{{ $lote->cerradoPor ? ' por '.$lote->cerradoPor->name : '' }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="caja">
        <table class="resumen">
            <tr>
                <td><div class="n">{{ $activos->count() }}</div><div class="gris">repuestos</div></td>
                <td><div class="n">{{ $activos->sum('cantidad') }}</div><div class="gris">unidades</div></td>
                <td><div class="n">{{ $activos->sum('enviada') }}</div><div class="gris">unid. enviadas</div></td>
                <td><div class="n">{{ $items->where('estado', EstadoItem::Enviado)->count() }}</div><div class="gris">enviados</div></td>
                <td><div class="n">{{ $items->where('estado', EstadoItem::NoDisponible)->count() }}</div><div class="gris">sin existencia</div></td>
                <td><div class="n">{{ $items->where('estado', EstadoItem::Pendiente)->count() }}</div><div class="gris">pendientes</div></td>
                <td><div class="n">{{ $items->where('estado', EstadoItem::Anulado)->count() }}</div><div class="gris">quitados</div></td>
            </tr>
        </table>
    </div>

    <h3>Repuestos</h3>
    <table class="tabla">
        <thead><tr><th>Código</th><th>Descripción</th><th class="der">Cant.</th><th class="der">Enviadas</th><th>Agregado por</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($items as $i)
            @php
                $aportes = $i->historial->whereIn('accion', ['agregado', 'sumado'])->groupBy(fn ($h) => $h->user?->name ?? '—')
                    ->map(fn ($hs, $n) => $n.' ('.$hs->sum('cantidad').')')->implode(', ');
            @endphp
            <tr class="{{ $i->estado === EstadoItem::Anulado ? 'quitado' : '' }}">
                <td class="cod">{{ $i->codigo }}</td>
                <td>{{ $i->descripcion }}@if ($i->tras_cierre)<br><span class="tras">Agregado después del cierre</span>@endif</td>
                <td class="der">{{ $i->cantidad }}</td>
                <td class="der">{{ $i->enviada }}</td>
                <td>{{ $aportes }}</td>
                <td>
                    {{ $i->estado === EstadoItem::Anulado ? 'Quitado' : $i->estado->etiqueta() }}
                    @if ($i->enviada > 0 && $i->faltan() > 0 && $i->estado !== EstadoItem::Anulado)<br><span class="gris">Envío parcial: faltan {{ $i->faltan() }}</span>@endif
                    @if ($i->estado === EstadoItem::NoDisponible && $i->fecha_ingreso)<br><span class="gris">Ingreso aprox. {{ $i->fecha_ingreso->format('d/m/Y') }}</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="gris">Este pedido no lleva repuestos de inventario.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Movimientos</h3>
    <table class="tabla">
        <thead><tr><th style="width:95px">Fecha</th><th>Código</th><th>Movimiento</th><th class="der">Cant.</th><th>Usuario</th><th>Detalle</th></tr></thead>
        <tbody>
        @forelse ($movimientos as $m)
            <tr>
                <td>{{ $m['h']->created_at->format('d/m/Y H:i') }}</td>
                <td class="cod">{{ $m['item']->codigo }}</td>
                <td>{{ $m['h']->etiqueta() }}</td>
                <td class="der">{{ $m['h']->cantidad ?? '' }}</td>
                <td>{{ $m['h']->user?->name ?? 'Sistema' }}</td>
                <td>{{ $m['h']->nota }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="gris">Sin movimientos.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="pie">{{ $neg['nombre'] }} · Generado el {{ now()->format('d/m/Y H:i') }}</div>
</div>
</body>
</html>
