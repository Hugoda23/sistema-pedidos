import { useMemo, useState } from 'react'
import { fecha, moneda } from '../lib/formato'
import { enlaceWa } from '../lib/whatsapp'
import BotonWhatsapp from './BotonWhatsapp'
import { Alerta, Campo, Modal, Select, Textarea } from './ui'

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

export default function WhatsappCliente({ pedido, onCerrar }) {
  const opciones = useMemo(() => plantillas(pedido), [pedido])
  const [clave, setClave] = useState(opciones[0][0])
  const [texto, setTexto] = useState(opciones[0][2])
  const elegir = (k) => { setClave(k); setTexto(opciones.find((o) => o[0] === k)[2]) }
  const url = enlaceWa(pedido.cliente.telefono, texto)

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
          {!url && <Alerta>El teléfono del cliente no parece válido. Corrígelo en Clientes.</Alerta>}
          <p className="text-xs text-slate-500">Se abre tu WhatsApp con el mensaje escrito; solo presiona enviar.</p>
          <div className="flex justify-end">
            <BotonWhatsapp url={url}>Abrir en WhatsApp</BotonWhatsapp>
          </div>
        </div>
      )}
    </Modal>
  )
}
