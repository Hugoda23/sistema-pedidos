import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { primerError } from '../lib/errores'
import { ACCION_ITEM } from '../lib/estados'
import { fecha, fechaHora, moneda } from '../lib/formato'
import { EstadoItem, EstadoPedido } from '../components/EstadoBadge'
import WhatsappCliente from '../components/WhatsappCliente'
import VistaPreviaComprobante from '../components/VistaPreviaComprobante'
import { Alerta, Boton, Campo, Input, Modal, Textarea } from '../components/ui'
import { ArrowLeftIcon, ChatBubbleLeftRightIcon, NoSymbolIcon, PlusIcon, PrinterIcon } from '@heroicons/react/24/outline'

export default function PedidoDetalle() {
  const { id } = useParams()
  const { puede } = useAuth()
  const [p, setP] = useState(null)
  const [error, setError] = useState('')
  const [historial, setHistorial] = useState(null)
  const [anular, setAnular] = useState(false)
  const [wa, setWa] = useState(false)
  const [comprobante, setComprobante] = useState(false)
  const gestiona = puede('pedidos.gestionar')

  const cargar = useCallback(() => api(`/pedidos/${id}`).then(setP).catch((e) => setError(e.status === 404 ? 'Pedido no encontrado.' : e.message)), [id])
  useEffect(() => { cargar() }, [cargar])

  // Todas las acciones devuelven el pedido actualizado.
  const accion = async (ruta, opciones) => {
    setError('')
    try {
      setP(await api(ruta, opciones))
      return true
    } catch (e) {
      setError(e.message)
      return false
    }
  }

  const cambiarEstado = (item, estado) => {
    let nota = null
    if (estado === 'anulado') {
      nota = prompt(`¿Por qué se anula ${item.codigo}? (opcional)`)
      if (nota === null) return
    }
    accion(`/pedido-items/${item.id}/estado`, { method: 'POST', body: { estado, nota } })
  }

  const [info, setInfo] = useState('')
  const preguntar = async (item) => {
    setError('')
    try {
      const r = await api(`/pedido-items/${item.id}/preguntar`, { method: 'POST' })
      if (!r.enviados.length) setError('El proveedor no tiene teléfono registrado.')
      else if (!r.vinculado) setError('Su WhatsApp no está vinculado: la pregunta quedó detenida. Vincúlelo en «Mi WhatsApp» y reintente desde ahí.')
      else setInfo(`Se le preguntó por WhatsApp a ${r.enviados.join(', ')} por ${item.codigo}.`)
    } catch (e) {
      setError(e.message)
    }
  }

  const quitar = (item) => confirm(`¿Quitar ${item.codigo} del pedido?`) && accion(`/pedido-items/${item.id}`, { method: 'DELETE' })

  if (!p) return <div><Alerta>{error}</Alerta>{!error && <p className="text-slate-500">Cargando…</p>}</div>

  const abierto = !['anulado', 'entregado'].includes(p.estado)

  return (
    <div className="max-w-6xl space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/pedidos" className="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline"><ArrowLeftIcon className="h-4 w-4" />Pedidos</Link>
          <h1 className="flex items-center gap-3 text-2xl font-bold text-slate-800">{p.numero} <EstadoPedido estado={p.estado} /></h1>
          <p className="text-sm text-slate-500">{fechaHora(p.fecha)} · Vendedor: {p.vendedor.name}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Boton variante="secundario" icono={PrinterIcon} onClick={() => setComprobante(true)}>Imprimir / PDF</Boton>
          {gestiona && !p.anulado && <Boton variante="secundario" icono={ChatBubbleLeftRightIcon} onClick={() => setWa(true)}>WhatsApp al cliente</Boton>}
          {puede('pedidos.anular') && !p.anulado && <Boton variante="peligro" icono={NoSymbolIcon} onClick={() => setAnular(true)}>Anular pedido</Boton>}
        </div>
      </div>

      <Alerta>{error}</Alerta>
      <Alerta tipo="ok">{info}</Alerta>
      {p.anulado && <Alerta>Anulado el {fechaHora(p.anulado.en)} por {p.anulado.por}: {p.anulado.motivo}</Alerta>}

      <div className="grid gap-4 md:grid-cols-3">
        <div className="rounded-xl border bg-white p-4 text-sm">
          <h2 className="mb-1 font-semibold text-slate-700">Cliente</h2>
          <div className="font-medium">{p.cliente.nombre}</div>
          <div className="text-slate-500">{p.cliente.telefono ?? 'Sin teléfono'}</div>
          <div className="text-slate-500">{p.cliente.moto}</div>
          {p.notas && <p className="mt-2 whitespace-pre-line rounded bg-slate-50 p-2 text-slate-600">{p.notas}</p>}
        </div>
        <div className="rounded-xl border bg-white p-4 text-sm md:col-span-2">
          <div className="grid grid-cols-3 gap-2 text-center">
            <div><div className="text-slate-500">Total</div><div className="text-xl font-semibold">{moneda(p.total)}</div></div>
            <div><div className="text-slate-500">Pagado</div><div className="text-xl font-semibold text-green-700">{moneda(p.pagado)}</div></div>
            <div><div className="text-slate-500">Saldo</div><div className={`text-xl font-semibold ${Number(p.saldo) > 0 ? 'text-red-600' : 'text-slate-400'}`}>{moneda(p.saldo)}</div></div>
          </div>
          <Pagos p={p} gestiona={gestiona && !p.anulado} puedeBorrar={puede('pedidos.anular')} accion={accion} />
        </div>
      </div>

      <div className="overflow-hidden rounded-xl border bg-white">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-4 py-3">Código</th><th className="px-4 py-3">Descripción</th><th className="px-4 py-3">Cant.</th>
                <th className="px-4 py-3">Precio</th><th className="px-4 py-3">Subtotal</th><th className="px-4 py-3">Estado</th><th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {p.items.map((it) => (
                <tr key={it.id} className={['anulado', 'descontinuado'].includes(it.estado) ? 'text-slate-400' : ''}>
                  <td className="whitespace-nowrap px-4 py-2.5 font-mono">{it.codigo}</td>
                  <td className="px-4 py-2.5">{it.descripcion}</td>
                  <td className="px-4 py-2.5">{it.cantidad}</td>
                  <td className="whitespace-nowrap px-4 py-2.5">{moneda(it.precio)}</td>
                  <td className="whitespace-nowrap px-4 py-2.5">{moneda(it.subtotal)}</td>
                  <td className="px-4 py-2.5">
                    <EstadoItem estado={it.estado} />
                    {it.fecha_ingreso && <div className="mt-0.5 text-xs text-amber-700">Ingreso aprox. {fecha(it.fecha_ingreso)}</div>}
                    {it.nota_proveedor && <div className="mt-0.5 text-xs text-slate-500">Proveedor: {it.nota_proveedor}</div>}
                    {gestiona && it.estado === 'no_disponible' && (
                      <button onClick={() => preguntar(it)} className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-green-700 hover:underline"><ChatBubbleLeftRightIcon className="h-3.5 w-3.5" />Preguntar al proveedor</button>
                    )}
                  </td>
                  <td className="whitespace-nowrap px-4 py-2.5 text-right">
                    <div className="flex justify-end gap-3">
                      {gestiona && it.acciones.map((a) => (
                        <button key={a} onClick={() => cambiarEstado(it, a)} className={a === 'anulado' ? 'text-red-600 hover:underline' : 'text-blue-600 hover:underline'}>{ACCION_ITEM[a]}</button>
                      ))}
                      {gestiona && it.editable && abierto && <button onClick={() => quitar(it)} className="text-slate-500 hover:underline">Quitar</button>}
                      <button onClick={() => setHistorial(it)} className="text-slate-500 hover:underline">Historial</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {gestiona && abierto && <AgregarLinea pedidoId={p.id} accion={accion} />}
      </div>

      {historial && (
        <Modal abierto titulo={`Historial de ${historial.codigo}`} onCerrar={() => setHistorial(null)}>
          <ol className="space-y-3 text-sm">
            {historial.historial.map((h, i) => (
              <li key={i} className="border-l-2 border-blue-200 pl-3">
                <div className="flex items-center gap-2"><EstadoItem estado={h.a} /> <span className="text-slate-400">{fechaHora(h.fecha)}</span></div>
                <div className="text-slate-600">{h.usuario ?? 'Sistema'}{h.fecha_ingreso && ` · ingreso aprox. ${fecha(h.fecha_ingreso)}`}</div>
                {h.nota && <div className="text-slate-500">{h.nota}</div>}
              </li>
            ))}
          </ol>
        </Modal>
      )}

      {wa && <WhatsappCliente pedido={p} onCerrar={() => setWa(false)} plantilla={wa === 'detalle' ? 'detalle' : undefined} />}
      {comprobante && (
        <VistaPreviaComprobante pedido={p} onCerrar={() => setComprobante(false)}
          onWhatsapp={gestiona && !p.anulado ? () => { setComprobante(false); setWa('detalle') } : null} />
      )}
      {anular && <AnularPedido p={p} onCerrar={() => setAnular(false)} onListo={(nuevo) => { setP(nuevo); setAnular(false) }} />}
    </div>
  )
}

function Pagos({ p, gestiona, puedeBorrar, accion }) {
  const [monto, setMonto] = useState('')
  const [nota, setNota] = useState('')

  const abonar = async (e) => {
    e.preventDefault()
    if (await accion(`/pedidos/${p.id}/pagos`, { method: 'POST', body: { monto, nota } })) { setMonto(''); setNota('') }
  }

  return (
    <div className="mt-4 border-t pt-3">
      <h3 className="mb-1 font-semibold text-slate-700">Pagos</h3>
      {p.pagos.length === 0 && <p className="text-slate-400">Sin pagos todavía.</p>}
      <ul className="divide-y">
        {p.pagos.map((pg) => (
          <li key={pg.id} className="flex items-center justify-between py-1.5">
            <span><span className="capitalize">{pg.tipo}</span> · {fechaHora(pg.fecha)} · {pg.registrado_por}{pg.nota && ` · ${pg.nota}`}</span>
            <span className="flex items-center gap-3">
              <span className="font-medium">{moneda(pg.monto)}</span>
              {puedeBorrar && <button className="text-xs text-red-600 hover:underline" onClick={() => confirm('¿Eliminar este pago?') && accion(`/pagos/${pg.id}`, { method: 'DELETE' })}>Eliminar</button>}
            </span>
          </li>
        ))}
      </ul>
      {gestiona && Number(p.saldo) > 0 && (
        <form onSubmit={abonar} className="mt-2 grid gap-2 sm:grid-cols-[9rem_1fr_auto]">
          <Input type="number" min="0.01" step="0.01" max={p.saldo} required placeholder="Monto" value={monto} onChange={(e) => setMonto(e.target.value)} />
          <Input placeholder="Nota (opcional)" value={nota} onChange={(e) => setNota(e.target.value)} />
          <Boton variante="secundario">{p.pagos.length ? 'Registrar abono' : 'Registrar anticipo'}</Boton>
        </form>
      )}
    </div>
  )
}

function AgregarLinea({ pedidoId, accion }) {
  const vacia = { codigo: '', descripcion: '', cantidad: 1, precio: '' }
  const [l, setL] = useState(vacia)

  const agregar = async (e) => {
    e.preventDefault()
    if (await accion(`/pedidos/${pedidoId}/items`, { method: 'POST', body: l })) setL(vacia)
  }

  return (
    <form onSubmit={agregar} className="grid gap-2 border-t bg-slate-50 p-3 sm:grid-cols-12">
      <div className="sm:col-span-3">
        <Input required placeholder="Código" aria-label="Código" className="font-mono uppercase" value={l.codigo} onChange={(e) => setL({ ...l, codigo: e.target.value })} />
      </div>
      <Input className="sm:col-span-4" required placeholder="Nombre del repuesto" aria-label="Nombre del repuesto" value={l.descripcion} onChange={(e) => setL({ ...l, descripcion: e.target.value })} />
      <Input className="sm:col-span-1" type="number" min="1" required value={l.cantidad} onChange={(e) => setL({ ...l, cantidad: e.target.value })} aria-label="Cantidad" />
      <Input className="sm:col-span-2" type="number" min="0" step="0.01" required placeholder="Precio" value={l.precio} onChange={(e) => setL({ ...l, precio: e.target.value })} />
      <Boton className="sm:col-span-2" variante="secundario" icono={PlusIcon}>Agregar</Boton>
    </form>
  )
}

function AnularPedido({ p, onCerrar, onListo }) {
  const [motivo, setMotivo] = useState('')
  const [errores, setErrores] = useState({})
  const [msg, setMsg] = useState('')

  const enviar = async (e) => {
    e.preventDefault()
    try {
      onListo(await api(`/pedidos/${p.id}/anular`, { method: 'POST', body: { motivo } }))
    } catch (err) {
      setErrores(primerError(err.errors))
      setMsg(err.message)
    }
  }

  return (
    <Modal abierto titulo={`Anular ${p.numero}`} onCerrar={onCerrar}>
      <form onSubmit={enviar} className="space-y-3">
        <p className="text-sm text-slate-600">Las líneas pendientes se anulan y el proveedor deja de verlas. Los pagos quedan registrados.</p>
        {!errores.motivo && <Alerta>{msg}</Alerta>}
        <Campo label="Motivo" error={errores.motivo}><Textarea required value={motivo} onChange={(e) => setMotivo(e.target.value)} /></Campo>
        <div className="flex justify-end gap-2">
          <Boton type="button" variante="secundario" onClick={onCerrar}>Cancelar</Boton>
          <Boton variante="peligro">Anular pedido</Boton>
        </div>
      </form>
    </Modal>
  )
}
