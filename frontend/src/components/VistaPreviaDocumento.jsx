import { useEffect, useRef, useState } from 'react'
import { apiTexto, descargar } from '../lib/api'
import { Alerta, Boton, Modal } from './ui'
import { ArrowDownTrayIcon, ChatBubbleLeftRightIcon, PrinterIcon } from '@heroicons/react/24/outline'

const ANCHO_HOJA = 816 // carta a 96 ppp
const ALTO_HOJA = 1056

/**
 * Vista previa de un documento (comprobante, reporte). Lo que se ve aquí es exactamente
 * lo que se imprime; el PDF sale de la misma plantilla (`ruta` y `ruta?formato=pdf`).
 */
export default function VistaPreviaDocumento({ titulo, ruta, nombrePdf, onCerrar, onWhatsapp, nota }) {
  const marco = useRef(null)
  const caja = useRef(null)
  const [escala, setEscala] = useState(1)
  const [html, setHtml] = useState(null)
  const [error, setError] = useState('')
  const [bajando, setBajando] = useState(false)

  useEffect(() => {
    apiTexto(ruta).then(setHtml).catch((e) => setError(e.message))
  }, [ruta])

  // La hoja se dibuja a 816 px (carta) y se reduce para que quepa completa en pantallas angostas.
  useEffect(() => {
    const medir = () => caja.current && setEscala(Math.min(1, caja.current.clientWidth / ANCHO_HOJA))
    medir()
    window.addEventListener('resize', medir)
    return () => window.removeEventListener('resize', medir)
  }, [html])

  const imprimir = () => marco.current?.contentWindow?.print()

  const pdf = async () => {
    setBajando(true)
    try {
      await descargar(ruta, { formato: 'pdf' }, nombrePdf)
    } catch (e) {
      setError(e.message)
    } finally {
      setBajando(false)
    }
  }

  return (
    <Modal abierto titulo={titulo} onCerrar={onCerrar} ancho="max-w-4xl">
      <Alerta>{error}</Alerta>
      <div className="max-h-[65vh] overflow-y-auto rounded-lg border bg-slate-100 p-2 sm:p-4">
        {html === null && !error && <p className="py-20 text-center text-slate-400">Preparando vista previa…</p>}
        {html !== null && (
          <div ref={caja} style={{ height: ALTO_HOJA * escala }}>
            <iframe ref={marco} title="Vista previa del documento" srcDoc={html}
              style={{ width: ANCHO_HOJA, height: ALTO_HOJA, transform: `scale(${escala})`, transformOrigin: 'top left' }}
              className="rounded bg-white shadow" />
          </div>
        )}
      </div>
      <div className="mt-4 flex flex-wrap justify-end gap-2">
        {onWhatsapp && <Boton variante="secundario" icono={ChatBubbleLeftRightIcon} onClick={onWhatsapp}>Enviar por WhatsApp</Boton>}
        <Boton variante="secundario" icono={ArrowDownTrayIcon} onClick={pdf} disabled={!html || bajando}>{bajando ? 'Generando…' : 'Descargar PDF'}</Boton>
        <Boton icono={PrinterIcon} onClick={imprimir} disabled={!html}>Imprimir</Boton>
      </div>
      {nota && <p className="mt-2 text-right text-xs text-slate-500">{nota}</p>}
    </Modal>
  )
}
