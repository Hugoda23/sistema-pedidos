import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { fechaHora } from '../lib/formato'

export default function Campanita() {
  const [datos, setDatos] = useState({ sin_leer: 0, items: [] })
  const [abierta, setAbierta] = useState(false)
  const caja = useRef(null)
  const navegar = useNavigate()

  const cargar = useCallback(() => api('/notificaciones').then(setDatos).catch(() => {}), [])

  useEffect(() => {
    cargar()
    const t = setInterval(cargar, 30000)
    return () => clearInterval(t)
  }, [cargar])

  useEffect(() => {
    const fuera = (e) => caja.current && !caja.current.contains(e.target) && setAbierta(false)
    document.addEventListener('mousedown', fuera)
    return () => document.removeEventListener('mousedown', fuera)
  }, [])

  const abrir = async (n) => {
    setAbierta(false)
    if (!n.leida) await api('/notificaciones/leer', { method: 'POST', body: { id: n.id } }).catch(() => {})
    cargar()
    if (n.url) navegar(n.url)
  }

  const leerTodas = async () => { await api('/notificaciones/leer', { method: 'POST' }).catch(() => {}); cargar() }

  return (
    <div ref={caja} className="relative">
      <button onClick={() => setAbierta(!abierta)} className="relative rounded-lg p-1.5 text-xl hover:bg-slate-100" aria-label="Notificaciones">
        🔔
        {datos.sin_leer > 0 && (
          <span className="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-xs font-semibold text-white">{datos.sin_leer > 99 ? '99+' : datos.sin_leer}</span>
        )}
      </button>
      {abierta && (
        <div className="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border bg-white shadow-xl">
          <div className="flex items-center justify-between border-b px-4 py-2">
            <span className="font-semibold text-slate-700">Notificaciones</span>
            {datos.sin_leer > 0 && <button onClick={leerTodas} className="text-xs text-blue-600 hover:underline">Marcar todas como leídas</button>}
          </div>
          <ul className="max-h-96 divide-y overflow-y-auto">
            {datos.items.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-400">Sin notificaciones.</li>}
            {datos.items.map((n) => (
              <li key={n.id} className={n.leida ? '' : 'bg-blue-50/60'}>
                <button onClick={() => abrir(n)} className="block w-full px-4 py-2.5 text-left text-sm hover:bg-slate-50">
                  <div className="font-medium text-slate-800">{n.titulo}</div>
                  <div className="text-slate-600">{n.mensaje}</div>
                  <div className="text-xs text-slate-400">{fechaHora(n.fecha)}</div>
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
