@php
    use App\Enums\EstadoItem;
    $neg = config('negocio');
    $logo = is_file($neg['logo']) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($neg['logo'])) : null;
    $activas = $items->filter(fn ($i) => $i->estado !== EstadoItem::Anulado);
    $porEstado = fn ($e) => $items->where('estado', $e)->count();
    $sumado = $activas->groupBy('codigo')->map(fn ($g) => ['codigo' => $g->first()->codigo, 'descripcion' => $g->first()->descripcion,
        'unidades' => $g->sum('cantidad'), 'lineas' => $g->count()])->sortKeys();
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
    .anulada td { color: #94a3b8; }
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
                <div class="gris">Repuestos solicitados al proveedor</div>
            </td>
            <td class="titulo">
                <div class="doc">Pedido de clientes</div>
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
                <td><div class="n">{{ $activas->count() }}</div><div class="gris">líneas</div></td>
                <td><div class="n">{{ $activas->sum('cantidad') }}</div><div class="gris">unidades</div></td>
                <td><div class="n">{{ $sumado->count() }}</div><div class="gris">códigos</div></td>
                <td><div class="n">{{ $porEstado(EstadoItem::Pendiente) }}</div><div class="gris">pendientes</div></td>
                <td><div class="n">{{ $items->filter(fn ($i) => in_array($i->estado, [EstadoItem::Enviado, EstadoItem::Recibido, EstadoItem::Entregado], true))->count() }}</div><div class="gris">enviados</div></td>
                <td><div class="n">{{ $porEstado(EstadoItem::NoDisponible) }}</div><div class="gris">sin existencia</div></td>
            </tr>
        </table>
    </div>

    <h3>Sumado por código</h3>
    <table class="tabla">
        <thead><tr><th>Código</th><th>Descripción</th><th class="der">Unidades</th><th class="der">Líneas</th></tr></thead>
        <tbody>
        @forelse ($sumado as $f)
            <tr><td class="cod">{{ $f['codigo'] }}</td><td>{{ $f['descripcion'] }}</td><td class="der"><b>{{ $f['unidades'] }}</b></td><td class="der">{{ $f['lineas'] }}</td></tr>
        @empty
            <tr><td colspan="4" class="gris">Sin repuestos.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Detalle de líneas</h3>
    <table class="tabla">
        <thead><tr><th>Código</th><th>Descripción</th><th class="der">Cant.</th><th>Vendedor</th><th>Pedido</th><th>Estado</th></tr></thead>
        <tbody>
        @foreach ($items as $i)
            <tr class="{{ $i->estado === EstadoItem::Anulado ? 'anulada' : '' }}">
                <td class="cod">{{ $i->codigo }}</td>
                <td>{{ $i->descripcion }}</td>
                <td class="der">{{ $i->cantidad }}</td>
                <td>{{ $i->pedido->vendedor->name }}</td>
                <td>{{ $i->pedido->numero }}</td>
                <td>
                    {{ $i->estado->etiqueta() }}
                    @if ($i->estado === EstadoItem::NoDisponible && $i->fecha_ingreso)<br><span class="gris">Ingreso aprox. {{ $i->fecha_ingreso->format('d/m/Y') }}</span>@endif
                    @if ($i->nota_proveedor)<br><span class="gris">{{ $i->nota_proveedor }}</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Movimientos</h3>
    <table class="tabla">
        <thead><tr><th style="width:95px">Fecha</th><th>Código</th><th>Pedido</th><th>Movimiento</th><th>Usuario</th><th>Detalle</th></tr></thead>
        <tbody>
        @forelse ($movimientos as $m)
            <tr>
                <td>{{ $m['h']->created_at->format('d/m/Y H:i') }}</td>
                <td class="cod">{{ $m['item']->codigo }}</td>
                <td>{{ $m['item']->pedido->numero }}</td>
                <td>{{ EstadoItem::tryFrom($m['h']->estado_nuevo)?->etiqueta() ?? $m['h']->estado_nuevo }}{{ ! $m['h']->estado_anterior ? ' (solicitado)' : '' }}</td>
                <td>{{ $m['h']->user?->name ?? 'Sistema' }}</td>
                <td>{{ $m['h']->fecha_ingreso ? 'Ingreso aprox. '.$m['h']->fecha_ingreso->format('d/m/Y').($m['h']->nota ? ' · ' : '') : '' }}{{ $m['h']->nota }}</td>
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
