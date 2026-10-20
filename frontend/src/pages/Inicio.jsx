import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { fecha, fechaHora, moneda } from '../lib/formato'
import { Alerta, Boton } from '../components/ui'
import { ChatBubbleLeftRightIcon, ExclamationTriangleIcon, PlusIcon } from '@heroicons/react/24/outline'

/** Tarjeta de número: etiqueta, valor y una línea de contexto. El color solo va en el aviso, con ícono. */
function Tarjeta({ etiqueta, valor, contexto, alerta, a }) {
  const cuerpo = (
    <div className="h-full rounded-xl border bg-white p-4 transition hover:border-blue-300">
      <div className="text-sm text-slate-500">{etiqueta}</div>
      <div className="mt-1 text-2xl font-semibold text-slate-800">{valor}</div>
      {contexto && <div className="mt-1 text-sm text-slate-500">{contexto}</div>}
      {alerta && <div className="mt-1 flex items-start gap-1 text-sm font-medium text-amber-700"><ExclamationTriangleIcon className="mt-0.5 h-4 w-4 shrink-0" />{alerta}</div>}
    </div>
  )
  return a ? <Link to={a} className="block">{cuerpo}</Link> : cuerpo
}

function Lista({ titulo, vacio, children, a, textoA }) {
  return (
    <section className="min-w-0 rounded-xl border bg-white">
      <div className="flex items-center justify-between border-b px-4 py-3">
        <h2 className="font-semibold text-slate-700">{titulo}</h2>
        {a && <Link to={a} className="text-sm text-blue-600 hover:underline">{textoA}</Link>}
      </div>
      {children?.length ? <ul className="divide-y text-sm">{children}</ul> : <p className="px-4 py-6 text-center text-sm text-slate-400">{vacio}</p>}
    </section>
  )
}

export default function Inicio() {
  const { user, puede } = useAuth()
  const [d, setD] = useState(null)
  const [error, setError] = useState('')

  const [wa, setWa] = useState(null)

  useEffect(() => { api('/panel').then(setD).catch((e) => setError(e.message)) }, [])
  useEffect(() => { api('/whatsapp').then(setWa).catch(() => {}) }, [])

  const v = d?.ventas
  const p = d?.proveedor

  return (
    <div className="max-w-6xl space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Hola, {user.name.split(' ')[0]}</h1>
          <p className="text-slate-500">{new Date().toLocaleDateString('es-GT', { weekday: 'long', day: 'numeric', month: 'long' })}</p>
        </div>
        {puede('pedidos.gestionar') && <Link to="/pedidos/nuevo"><Boton icono={PlusIcon}>Nuevo pedido</Boton></Link>}
      </div>

      <Alerta>{error}</Alerta>
      {wa && wa.status !== 'conectado' && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          <span className="flex items-center gap-2"><ExclamationTriangleIcon className="h-5 w-5 shrink-0" />Su WhatsApp no está vinculado: los avisos que usted genere no saldrán por WhatsApp.</span>
          <Link to="/whatsapp"><Boton icono={ChatBubbleLeftRightIcon}>Vincular mi WhatsApp</Boton></Link>
        </div>
      )}
      {!d && !error && <p className="text-slate-400">Cargando…</p>}

      {v && (
        <>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Tarjeta etiqueta="Pedidos activos" valor={v.activos} contexto={`${v.listos} listo(s) para entregar`} a="/pedidos" />
            <Tarjeta etiqueta="Por cobrar" valor={moneda(v.por_cobrar)} contexto={`en ${v.con_saldo} pedido(s)`} />
            <Tarjeta etiqueta="Repuestos sin existencia" valor={v.sin_existencia}
              alerta={v.ingreso_cumplido ? `${v.ingreso_cumplido} con fecha de ingreso cumplida` : null} />
            <Tarjeta etiqueta="Vendido este mes" valor={moneda(v.mes_total)} contexto={`${v.mes_pedidos} pedido(s)`} />
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <Lista titulo="Listos para entregar" vacio="Nada pendiente de entregar." a="/pedidos?estado=listo" textoA="Ver todos">
              {v.lista_listos.map((x) => (
                <li key={x.id}>
                  <Link to={`/pedidos/${x.id}`} className="flex items-center justify-between px-4 py-2.5 hover:bg-slate-50">
                    <span><span className="font-medium text-blue-600">{x.numero}</span> · {x.cliente}</span>
                    <span className={Number(x.saldo) > 0 ? 'text-slate-700' : 'text-slate-400'}>{Number(x.saldo) > 0 ? `Saldo ${moneda(x.saldo)}` : 'Pagado'}</span>
                  </Link>
                </li>
              ))}
            </Lista>
            <Lista titulo="Sin existencia" vacio="Todos los repuestos están disponibles.">
              {v.lista_sin_existencia.map((x, i) => (
                <li key={i}>
                  <Link to={`/pedidos/${x.pedido_id}`} className="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50">
                    <span className="min-w-0 truncate"><span className="font-mono">{x.codigo}</span> · {x.descripcion} <span className="text-slate-400">({x.pedido})</span></span>
                    <span className={`shrink-0 ${x.cumplida ? 'font-medium text-amber-700' : 'text-slate-500'}`}>{x.cumplida && <ExclamationTriangleIcon className="mr-1 inline h-4 w-4 align-[-2px]" />}{fecha(x.fecha_ingreso)}</span>
                  </Link>
                </li>
              ))}
            </Lista>
          </div>
        </>
      )}

      {p && (
        <>
          {v && <h2 className="pt-2 text-lg font-semibold text-slate-700">Proveedor</h2>}
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Tarjeta etiqueta="Por atender" valor={p.por_atender} contexto="líneas pendientes" a="/proveedor" />
            <Tarjeta etiqueta="Revisar ingreso" valor={p.revisar} alerta={p.revisar ? '¿ya ingresaron?' : null} contexto={p.revisar ? null : 'ninguna fecha cumplida'} a="/proveedor?estado=revisar" />
            <Tarjeta etiqueta="Sin existencia" valor={p.sin_existencia} contexto="con fecha aproximada" a="/proveedor?estado=no_disponible" />
            <Tarjeta etiqueta="Enviados" valor={p.enviados_semana} contexto="en los últimos 7 días" />
          </div>
          {p.abierto && (
            <p className="rounded-xl border bg-white px-4 py-3 text-sm text-slate-600">
              Pedido de clientes <b>#{p.abierto.numero}</b> abierto desde {fechaHora(p.abierto.desde)}: {p.abierto.lineas} línea(s), {p.abierto.unidades} unidad(es).{' '}
              <Link to="/proveedor" className="text-blue-600 hover:underline">Ver repuestos</Link>
            </p>
          )}
          {p.inventario_abierto && (
            <p className="rounded-xl border bg-white px-4 py-3 text-sm text-slate-600">
              Pedido de inventario <b>#{p.inventario_abierto.numero}</b> abierto: {p.inventario_abierto.repuestos} repuesto(s) por atender.{' '}
              <Link to="/inventario" className="text-blue-600 hover:underline">Ver inventario</Link>
            </p>
          )}
          <Lista titulo="Fechas de ingreso cumplidas" vacio="No hay fechas cumplidas." a="/proveedor?estado=revisar" textoA="Revisar">
            {p.lista_revisar.map((x, i) => (
              <li key={i} className="flex items-center justify-between gap-3 px-4 py-2.5">
                <span className="min-w-0 truncate"><span className="font-mono">{x.codigo}</span> · {x.descripcion} ({x.cantidad} u.) · {x.vendedor}</span>
                <span className="shrink-0 font-medium text-amber-700"><ExclamationTriangleIcon className="mr-1 inline h-4 w-4 align-[-2px]" />{fecha(x.fecha_ingreso)}</span>
              </li>
            ))}
          </Lista>
        </>
      )}
    </div>
  )
}
