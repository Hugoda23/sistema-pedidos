@php
    use App\Enums\EstadoItem;
    $q = fn ($v) => 'Q '.number_format((float) $v, 2);
    $neg = config('negocio');
    $logo = is_file($neg['logo']) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($neg['logo'])) : null;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Pedido {{ $pedido->numero }}</title>
<style>
    @page { size: letter; margin: 14mm 14mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10.5px; color: #1e293b; margin: 0; }
    .hoja { max-width: 760px; margin: 0 auto; position: relative; }
    @media screen { body { padding: 28px; } }
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
    .caja td { padding: 1px 0; }
    .etq { color: #64748b; width: 75px; }
    .lineas { margin-top: 14px; }
    .lineas th { background: #e2e8f0; text-align: left; font-size: 9.5px; text-transform: uppercase; color: #334155; padding: 6px; }
    .lineas td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .der { text-align: right; white-space: nowrap; }
    .cod { font-family: "DejaVu Sans Mono", monospace; font-size: 9.5px; }
    .fuera td { color: #94a3b8; }
    .estado { font-size: 9px; }
    .totales { width: 260px; margin: 10px 0 0 auto; }
    .totales td { padding: 3px 6px; }
    .totales .saldo td { border-top: 1.5px solid #0f172a; font-weight: bold; font-size: 12px; }
    h3 { font-size: 11px; margin: 16px 0 4px; color: #334155; }
    .pagos td { padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
    .notas { white-space: pre-line; }
    .pie { margin-top: 22px; text-align: center; color: #64748b; font-size: 9.5px; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    .anulado { position: fixed; top: 38%; left: 8%; width: 84%; text-align: center; font-size: 90px; font-weight: bold;
               color: rgba(220, 38, 38, 0.15); transform: rotate(-30deg); }
</style>
</head>
<body>
<div class="hoja">
    @if ($pedido->anulado())
        <div class="anulado">ANULADO</div>
    @endif

    <table class="enc">
        <tr>
            @if ($logo)
                <td class="logo"><img src="{{ $logo }}" alt="{{ $neg['nombre'] }}"></td>
            @endif
            <td>
                <div class="negocio">{{ $neg['nombre'] }}</div>
                @if ($neg['direccion'])<div class="gris">{{ $neg['direccion'] }}</div>@endif
                @if ($neg['telefono'])<div class="gris">Tel. {{ $neg['telefono'] }}</div>@endif
                @if ($neg['nit'])<div class="gris">NIT {{ $neg['nit'] }}</div>@endif
            </td>
            <td class="titulo">
                <div class="doc">Comprobante de pedido</div>
                <div class="num">{{ $pedido->numero }}</div>
                <div class="gris">{{ $pedido->created_at->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="caja">
        <table>
            <tr><td class="etq">Cliente</td><td><b>{{ $pedido->cliente->nombre }}</b></td>
                <td class="etq">Vendedor</td><td>{{ $pedido->vendedor->name }}</td></tr>
            <tr><td class="etq">Teléfono</td><td>{{ $pedido->cliente->telefono ?? '—' }}</td>
                <td class="etq">Estado</td><td>{{ \App\Models\Pedido::ESTADOS[$pedido->estado] ?? $pedido->estado }}</td></tr>
            @if ($pedido->cliente->moto)
                <tr><td class="etq">Moto</td><td colspan="3">{{ $pedido->cliente->moto }}</td></tr>
            @endif
        </table>
    </div>

    <table class="lineas">
        <thead>
            <tr><th>Código</th><th>Descripción</th><th class="der">Cant.</th><th class="der">Precio</th><th class="der">Subtotal</th><th>Estado</th></tr>
        </thead>
        <tbody>
        @foreach ($pedido->items as $i)
            @php $cuenta = $i->estado->cuentaEnTotal(); @endphp
            <tr class="{{ $cuenta ? '' : 'fuera' }}">
                <td class="cod">{{ $i->codigo }}</td>
                <td>{{ $i->descripcion }}</td>
                <td class="der">{{ $i->cantidad }}</td>
                <td class="der">{{ $q($i->precio) }}</td>
                <td class="der">{{ $cuenta ? $q($i->cantidad * (float) $i->precio) : '—' }}</td>
                <td class="estado">
                    {{ $i->estado->etiqueta() }}
                    @if ($i->estado === EstadoItem::NoDisponible && $i->fecha_ingreso)
                        <br><span class="gris">Ingreso aprox. {{ $i->fecha_ingreso->format('d/m/Y') }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($pedido->items->contains(fn ($i) => ! $i->estado->cuentaEnTotal()))
        <div class="gris" style="margin-top:4px; font-size:9px;">Los repuestos anulados o descontinuados no se cobran.</div>
    @endif

    <table class="totales">
        <tr><td>Total</td><td class="der">{{ $q($pedido->total) }}</td></tr>
        <tr><td>Abonado</td><td class="der">{{ $q($pedido->pagado) }}</td></tr>
        <tr class="saldo"><td>Saldo</td><td class="der">{{ $q($pedido->saldo()) }}</td></tr>
    </table>

    @if ($pedido->pagos->isNotEmpty())
        <h3>Pagos recibidos</h3>
        <table class="pagos">
            @foreach ($pedido->pagos as $p)
                <tr><td style="width:110px">{{ $p->created_at->format('d/m/Y') }}</td><td>{{ ucfirst($p->tipo) }}{{ $p->nota ? ' · '.$p->nota : '' }}</td><td class="der">{{ $q($p->monto) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($pedido->notas)
        <h3>Notas</h3>
        <div class="notas">{{ $pedido->notas }}</div>
    @endif

    @if ($pedido->anulado())
        <h3>Pedido anulado</h3>
        <div>{{ $pedido->anulado_en->format('d/m/Y') }}: {{ $pedido->motivo_anulacion }}</div>
    @endif

    <div class="pie">
        Gracias por su preferencia. Este documento no es una factura. · Generado el {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
