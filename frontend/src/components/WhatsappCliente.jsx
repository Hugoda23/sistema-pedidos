import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { fecha, moneda } from '../lib/formato'
import { Alerta, Boton, Campo, Modal, Select, Textarea } from './ui'

const lista = (items) => items.map((i) => `• ${i.descripcion} (${i.cantidad})`).join('\n')

/** Plantillas armadas con lo que tiene el pedido en este momento. Trato de usted. */
function plantillas(p) {
  const nombre = p.cliente.nombre
  const de = (estado) => p.items.filter((i) => i.estado === estado)
  const saldo = Number(p.saldo) > 0 ? `\n\nSaldo pendiente: ${moneda(p.saldo)}.` : ''
  const t = []

  if (de('recibido').length) t.push(['llego', 'Ya llegó', `Buen día, ${nombre}. Le informamos que ya tenemos sus repuestos del pedido ${p.numero}:\n${lista(de('recibido'))}${saldo}\n\nLe esperamos. ¡Gracias por su preferencia!`])
  if (de('no_disponible').length) {
    const l = de('no_disponible').map((i) => `• ${i.descripcion}: ingreso aprox. ${fecha(i.fecha_ingreso)}`).join('\n')
    t.push(['sin_existencia', 'Sin existencia', `Buen día, ${nombre}. Le informamos que los siguientes repuestos de su pedido ${p.numero} aún no tienen existencia:\n${l}\n\nLe avisaremos en cuanto ingresen. Gracias por su paciencia.`])
  }
  if (de('descontinuado').length) t.push(['descontinuado', 'Descontinuado', `Buen día, ${nombre}. Lamentamos informarle que los siguientes repuestos fueron descontinuados por el fabricante:\n${lista(de('descontinuado'))}\n\n¿Desea que le busquemos una alternativa?`])
  if (Number(p.saldo) > 0) t.push(['saldo', 'Recordatorio de saldo', `Buen día, ${nombre}. Le recordamos que su pedido ${p.numero} tiene un saldo pendiente de ${moneda(p.saldo)}. Quedamos a sus órdenes.`])
  t.push(['detalle', 'Envío del comprobante', `Buen día, ${nombre}. Le compartimos el comprobante de su pedido ${p.numero}.\n\nTotal: ${moneda(p.total)}\nAbonado: ${moneda(p.pagado)}\nSaldo: ${moneda(p.saldo)}\n\nQuedamos a sus órdenes.`])
  t.push(['libre', 'Mensaje libre', `Buen día, ${nombre}. `])
  return t
}

/** El vendedor le escribe al cliente desde su WhatsApp vinculado; puede adjuntar el comprobante en PDF. */
export default function WhatsappCliente({ pedido, onCerrar, plantilla }) {
  const opciones = useMemo(() => plantillas(pedido), [pedido])
  const inicial = opciones.find((o) => o[0] === plantilla) ?? opciones[0]
  const [clave, setClave] = useState(inicial[0])
  const [texto, setTexto] = useState(inicial[2])
  const [adjuntar, setAdjuntar] = useState(inicial[0] === 'detalle')
  const [sesion, setSesion] = useState(null)
  const [estado, setEstado] = useState({ tipo: 'error', msg: '' })
  const [enviando, setEnviando] = useState(false)

  useEffect(() => { api('/whatsapp').then(setSesion).catch(() => setSesion({ status: 'sin_servicio' })) }, [])

  const elegir = (k) => {
    setClave(k)
    setTexto(opciones.find((o) => o[0] === k)[2])
    if (k === 'detalle') setAdjuntar(true)
  }
  const vinculado = sesion?.status === 'conectado'

  const enviar = async () => {
    setEnviando(true)
    try {
      await api(`/pedidos/${pedido.id}/whatsapp`, { method: 'POST', body: { mensaje: texto, adjuntar } })
      setEstado({ tipo: 'ok', msg: 'Mensaje enviado a la cola. Puede ver si salió en «Mi WhatsApp».' })
    } catch (e) {
      setEstado({ tipo: 'error', msg: e.errors?.mensaje?.[0] ?? e.message })
    } finally {
      setEnviando(false)
    }
  }

  return (
    <Modal abierto titulo={`WhatsApp a ${pedido.cliente.nombre}`} onCerrar={onCerrar}>
      {!pedido.cliente.telefono ? (
        <Alerta>El cliente no tiene teléfono registrado. Agréguelo en Clientes.</Alerta>
      ) : (
        <div className="space-y-3">
          <p className="text-sm text-slate-500">Para: {pedido.cliente.telefono}{sesion?.numero && ` · Desde su número +${sesion.numero}`}</p>
          <Campo label="Plantilla">
            <Select value={clave} onChange={(e) => elegir(e.target.value)}>
              {opciones.map(([k, t]) => <option key={k} value={k}>{t}</option>)}
            </Select>
          </Campo>
          <Campo label="Mensaje"><Textarea rows={7} value={texto} onChange={(e) => setTexto(e.target.value)} /></Campo>
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" checked={adjuntar} onChange={(e) => setAdjuntar(e.target.checked)} />
            📎 Adjuntar el comprobante del pedido en PDF
          </label>
          <Alerta tipo={estado.tipo}>{estado.msg}</Alerta>
          {sesion && !vinculado && (
            <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
              Su WhatsApp no está vinculado. <Link to="/whatsapp" className="font-medium underline">Vincúlelo en «Mi WhatsApp»</Link> para enviar desde el sistema.
            </p>
          )}
          <div className="flex justify-end gap-2">
            {estado.tipo === 'ok'
              ? <Boton onClick={onCerrar}>Listo</Boton>
              : <Boton onClick={enviar} disabled={!vinculado || enviando || !texto.trim()}>{enviando ? 'Enviando…' : 'Enviar por WhatsApp'}</Boton>}
          </div>
        </div>
      )}
    </Modal>
  )
}
