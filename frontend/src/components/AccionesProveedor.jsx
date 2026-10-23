// Piezas que comparten la tabla del proveedor y el pedido de inventario.
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { primerError } from '../lib/errores'
import { Alerta, Boton, Campo, Input, Modal, Textarea } from './ui'
import { ChatBubbleLeftRightIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/react/24/outline'

const hoy = () => new Date().toLocaleDateString('en-CA') // YYYY-MM-DD local

/** Qué pasó con los avisos por WhatsApp a los vendedores. */
export function ResultadoWhatsapp({ r, onCerrar }) {
  if (!r.enviados.length && !r.sin_telefono.length) return null
  const detenido = r.enviados.length > 0 && !r.vinculado
  return (
    <div className={`my-3 flex items-start justify-between gap-3 rounded-xl border p-3 text-sm ${detenido ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-green-200 bg-green-50 text-green-900'}`}>
      <div className="space-y-1">
        {r.enviados.length > 0 && (detenido
          ? <p className="flex items-start gap-1.5"><ExclamationTriangleIcon className="mt-0.5 h-4 w-4 shrink-0" /><span>Su WhatsApp no está vinculado: el aviso a {r.enviados.join(', ')} quedó detenido. <Link to="/whatsapp" className="font-medium underline">Vincúlelo y reintente</Link>.</span></p>
          : <p className="flex items-center gap-1.5"><ChatBubbleLeftRightIcon className="h-4 w-4 shrink-0" />Aviso por WhatsApp enviado a: {r.enviados.join(', ')}.</p>)}
        {r.sin_telefono.length > 0 && <p className="text-amber-800">Sin teléfono registrado (solo campanita): {r.sin_telefono.join(', ')}.</p>}
      </div>
      <button onClick={onCerrar} aria-label="Cerrar" className="rounded p-0.5 hover:bg-black/5"><XMarkIcon className="h-4 w-4" /></button>
    </div>
  )
}

const AVISO_DESCONTINUADO = 'El repuesto quedará como descontinuado, ya no se le cobrará al cliente y se le avisará al vendedor.'

export function DialogoEstado({ dialogo, onCerrar, onEnviar, avisoDescontinuado = AVISO_DESCONTINUADO }) {
  const [fechaIngreso, setFechaIngreso] = useState(dialogo.fila?.fecha_ingreso ?? '')
  const [nota, setNota] = useState('')
  const [errores, setErrores] = useState({})
  const [msg, setMsg] = useState('')
  const noDisp = dialogo.estado === 'no_disponible'
  const cuantos = dialogo.ids.length

  const enviar = async (e) => {
    e.preventDefault()
    const err = await onEnviar(dialogo.estado, dialogo.ids, noDisp ? { fecha_ingreso: fechaIngreso, nota } : { nota })
    if (err) { setErrores(primerError(err.errors)); setMsg(err.message) }
  }

  const titulo = dialogo.fila ? `${dialogo.fila.codigo} · ${dialogo.fila.descripcion}` : `${cuantos} repuesto(s)`

  return (
    <Modal abierto titulo={noDisp ? 'No disponible' : 'Repuesto descontinuado'} onCerrar={onCerrar}>
      <form onSubmit={enviar} className="space-y-3">
        <p className="text-sm text-slate-600">{titulo}</p>
        {!Object.keys(errores).length && <Alerta>{msg}</Alerta>}
        {noDisp ? (
          <Campo label="Fecha aproximada de ingreso" error={errores.fecha_ingreso}>
            <Input type="date" required min={hoy()} value={fechaIngreso} onChange={(e) => setFechaIngreso(e.target.value)} />
          </Campo>
        ) : (
          <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{avisoDescontinuado}</p>
        )}
        {errores.ids && <Alerta>{errores.ids}</Alerta>}
        <Campo label="Nota para el vendedor (opcional)" error={errores.nota}>
          <Textarea value={nota} onChange={(e) => setNota(e.target.value)} placeholder={noDisp ? 'Ej.: viene en el próximo contenedor' : 'Ej.: ya no se trabaja, sugerir alternativa'} />
        </Campo>
        <div className="flex justify-end gap-2">
          <Boton type="button" variante="secundario" onClick={onCerrar}>Cancelar</Boton>
          <Boton variante={noDisp ? 'primario' : 'peligro'}>Guardar</Boton>
        </div>
      </form>
    </Modal>
  )
}
