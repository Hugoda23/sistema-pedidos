import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { fecha, moneda } from '../lib/formato'
import { Alerta, Boton, Campo, Modal, Select, Textarea } from './ui'

const lista = (items) => items.map((i) => `• ${i.descripcion} (${i.cantidad})`).join('\n')

/** Plantillas armadas con lo que tiene el pedido en este momento. */
function plantillas(p) {
  const nombre = p.cliente.nombre.split(' ')[0]
  const de = (estado) => p.items.filter((i) => i.estado === estado)
  const saldo = Number(p.saldo) > 0 ? `\n\nSaldo pendiente: ${moneda(p.saldo)}.` : ''
  const t = []

  if (de('recibido').length) t.push(['llego', 'Ya llegó', `Hola ${nombre} 👋, ya tenemos tus repuestos del pedido ${p.numero}:\n${lista(de('recibido'))}${saldo}\n\n¡Te esperamos!`])
  if (de('no_disponible').length) {
    const l = de('no_disponible').map((i) => `• ${i.descripcion}: ingreso aprox. ${fecha(i.fecha_ingreso)}`).join('\n')
    t.push(['sin_existencia', 'Sin existencia', `Hola ${nombre}, te cuento que estos repuestos aún no tienen existencia:\n${l}\n\nTe aviso en cuanto lleguen.`])
  }
  if (de('descontinuado').length) t.push(['descontinuado', 'Descontinuado', `Hola ${nombre}, lamentablemente estos repuestos están descontinuados:\n${lista(de('descontinuado'))}\n\n¿Quieres que te busque una alternativa?`])
  if (Number(p.saldo) > 0) t.push(['saldo', 'Recordatorio de saldo', `Hola ${nombre}, te recuerdo que tu pedido ${p.numero} tiene un saldo pendiente de ${moneda(p.saldo)}.`])
  t.push(['libre', 'Mensaje libre', `Hola ${nombre}, `])
  return t
}

const enlaceWa = (tel, texto) => {
  const d = tel.replace(/\D/g, '')
  return `https://wa.me/${d.length === 8 ? `502${d}` : d}?text=${encodeURIComponent(texto)}`
}

export default function WhatsappCliente({ pedido, onCerrar }) {
  const opciones = useMemo(() => plantillas(pedido), [pedido])
  const [clave, setClave] = useState(opciones[0][0])
  const [texto, setTexto] = useState(opciones[0][2])
  const [sesion, setSesion] = useState(null)
  const [estado, setEstado] = useState({ tipo: 'error', msg: '' })
  const [enviando, setEnviando] = useState(false)

  useEffect(() => { api('/whatsapp').then(setSesion).catch(() => setSesion({ status: 'sin_servicio' })) }, [])

  const elegir = (k) => { setClave(k); setTexto(opciones.find((o) => o[0] === k)[2]) }
  const vinculado = sesion?.status === 'conectado'

  const enviar = async () => {
    setEnviando(true)
    try {
      await api(`/pedidos/${pedido.id}/whatsapp`, { method: 'POST', body: { mensaje: texto } })
      setEstado({ tipo: 'ok', msg: 'Mensaje en camino. Puedes ver si salió en «Mi WhatsApp».' })
    } catch (e) {
      setEstado({ tipo: 'error', msg: e.errors?.mensaje?.[0] ?? e.message })
    } finally {
      setEnviando(false)
    }
  }

  return (
    <Modal abierto titulo={`WhatsApp a ${pedido.cliente.nombre}`} onCerrar={onCerrar}>
      {!pedido.cliente.telefono ? (
        <Alerta>El cliente no tiene teléfono registrado. Agrégalo en Clientes.</Alerta>
      ) : (
        <div className="space-y-3">
          <p className="text-sm text-slate-500">Para: {pedido.cliente.telefono}</p>
          <Campo label="Plantilla">
            <Select value={clave} onChange={(e) => elegir(e.target.value)}>
              {opciones.map(([k, t]) => <option key={k} value={k}>{t}</option>)}
            </Select>
          </Campo>
          <Campo label="Mensaje"><Textarea rows={7} value={texto} onChange={(e) => setTexto(e.target.value)} /></Campo>
          <Alerta tipo={estado.tipo}>{estado.msg}</Alerta>
          {sesion && !vinculado && (
            <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
              Tu WhatsApp no está vinculado. <Link to="/whatsapp" className="underline">Vincúlalo aquí</Link> o abre el mensaje en tu WhatsApp con el botón de abajo.
            </p>
          )}
          <div className="flex flex-wrap justify-end gap-2">
            <a href={enlaceWa(pedido.cliente.telefono, texto)} target="_blank" rel="noreferrer"
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Abrir en WhatsApp</a>
            {vinculado && estado.tipo !== 'ok' && <Boton onClick={enviar} disabled={enviando || !texto.trim()}>{enviando ? 'Enviando…' : 'Enviar desde el sistema'}</Boton>}
            {estado.tipo === 'ok' && <Boton onClick={onCerrar}>Listo</Boton>}
          </div>
        </div>
      )}
    </Modal>
  )
}
