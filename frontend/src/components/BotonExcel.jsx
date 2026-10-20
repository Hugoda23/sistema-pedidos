import { useState } from 'react'
import { descargar } from '../lib/api'
import { Boton } from './ui'
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline'

export default function BotonExcel({ ruta, params, nombre, onError }) {
  const [bajando, setBajando] = useState(false)

  const bajar = async () => {
    setBajando(true)
    try {
      await descargar(ruta, params, nombre)
    } catch (e) {
      onError?.(e.message)
    } finally {
      setBajando(false)
    }
  }

  return <Boton variante="secundario" icono={ArrowDownTrayIcon} onClick={bajar} disabled={bajando}>{bajando ? 'Generando…' : 'Exportar a Excel'}</Boton>
}
