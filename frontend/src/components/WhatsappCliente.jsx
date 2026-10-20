import { useMemo, useState } from 'react'
import { fecha, moneda } from '../lib/formato'
import { enlaceWa } from '../lib/whatsapp'
import BotonWhatsapp from './BotonWhatsapp'
import { Alerta, Campo, Modal, Select, Textarea } from './ui'

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

export default function WhatsappCliente({ pedido, onCerrar, plantilla }) {
  const opciones = useMemo(() => plantillas(pedido), [pedido])
  const inicial = opciones.find((o) => o[0] === plantilla) ?? opciones[0]
  const [clave, setClave] = useState(inicial[0])
  const [texto, setTexto] = useState(inicial[2])
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
          <p className="text-xs text-slate-500">Se abre su WhatsApp con el mensaje escrito; solo presione enviar.{clave === 'detalle' && ' Recuerde adjuntar el PDF del comprobante.'}</p>
          <div className="flex justify-end">
            <BotonWhatsapp url={url}>Abrir en WhatsApp</BotonWhatsapp>
          </div>
        </div>
      )}
    </Modal>
  )
}
