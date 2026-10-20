// Piezas básicas de interfaz que usan todos los módulos.
import { useEffect } from 'react'
import { XMarkIcon } from '@heroicons/react/24/outline'

const VARIANTES = {
  primario: 'bg-blue-600 text-white hover:bg-blue-700',
  secundario: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
  peligro: 'bg-red-600 text-white hover:bg-red-700',
}

/** `icono`: un componente de Heroicons (p. ej. PrinterIcon) que va antes del texto. */
export function Boton({ variante = 'primario', className = '', icono: Icono, children, ...props }) {
  return (
    <button {...props}
      className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-60 ${VARIANTES[variante]} ${className}`}>
      {Icono && <Icono className="h-4 w-4 shrink-0" aria-hidden="true" />}
      {children}
    </button>
  )
}

export function Campo({ label, error, children, className = '' }) {
  return (
    <label className={`block text-sm ${className}`}>
      <span className="text-slate-700">{label}</span>
      <div className="mt-1">{children}</div>
      {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
    </label>
  )
}

const ESTILO_INPUT = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-slate-100'

export function Input(props) {
  return <input {...props} className={`${ESTILO_INPUT} ${props.className ?? ''}`} />
}

export function Select({ children, ...props }) {
  return <select {...props} className={`${ESTILO_INPUT} ${props.className ?? ''}`}>{children}</select>
}

export function Textarea(props) {
  return <textarea rows={3} {...props} className={`${ESTILO_INPUT} ${props.className ?? ''}`} />
}

export function Modal({ abierto, titulo, onCerrar, children, ancho = 'max-w-lg' }) {
  useEffect(() => {
    if (!abierto) return
    const esc = (e) => e.key === 'Escape' && onCerrar()
    window.addEventListener('keydown', esc)
    return () => window.removeEventListener('keydown', esc)
  }, [abierto, onCerrar])

  if (!abierto) return null
  return (
    <div className="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center">
      <div className={`w-full ${ancho} rounded-xl bg-white shadow-xl`}>
        <div className="flex items-center justify-between border-b px-5 py-3">
          <h2 className="font-semibold text-slate-800">{titulo}</h2>
          <button onClick={onCerrar} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar"><XMarkIcon className="h-5 w-5" /></button>
        </div>
        <div className="p-5">{children}</div>
      </div>
    </div>
  )
}

const COLORES = {
  verde: 'bg-green-100 text-green-800',
  rojo: 'bg-red-100 text-red-800',
  gris: 'bg-slate-100 text-slate-700',
  azul: 'bg-blue-100 text-blue-800',
  amarillo: 'bg-amber-100 text-amber-800',
  morado: 'bg-purple-100 text-purple-800',
}

export function Badge({ color = 'gris', children }) {
  return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${COLORES[color]}`}>{children}</span>
}

export function Alerta({ tipo = 'error', children }) {
  if (!children) return null
  const c = tipo === 'error' ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'
  return <p className={`rounded-lg p-3 text-sm ${c}`}>{children}</p>
}

export function Encabezado({ titulo, children }) {
  return (
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-2xl font-bold text-slate-800">{titulo}</h1>
      <div className="flex flex-wrap gap-2">{children}</div>
    </div>
  )
}
