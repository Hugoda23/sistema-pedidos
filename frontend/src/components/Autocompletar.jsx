import { useEffect, useRef, useState } from 'react'

/**
 * Input que sugiere opciones mientras se escribe.
 * `buscar(q)` devuelve una promesa con la lista; `render(op)` dibuja cada opción.
 */
export default function Autocompletar({ value, onChange, onElegir, buscar, render, placeholder, className = '', extra, ...props }) {
  const [opciones, setOpciones] = useState([])
  const [abierto, setAbierto] = useState(false)
  const [activo, setActivo] = useState(0)
  const caja = useRef(null)

  useEffect(() => {
    if (!abierto || !value?.trim()) { setOpciones([]); return }
    let vigente = true
    const t = setTimeout(() => buscar(value).then((r) => vigente && (setOpciones(r), setActivo(0))).catch(() => {}), 200)
    return () => { vigente = false; clearTimeout(t) }
  }, [value, abierto, buscar])

  useEffect(() => {
    const fuera = (e) => caja.current && !caja.current.contains(e.target) && setAbierto(false)
    document.addEventListener('mousedown', fuera)
    return () => document.removeEventListener('mousedown', fuera)
  }, [])

  const elegir = (op) => { onElegir(op); setAbierto(false) }

  const teclas = (e) => {
    if (!abierto || !opciones.length) return
    if (e.key === 'ArrowDown') { e.preventDefault(); setActivo((a) => Math.min(a + 1, opciones.length - 1)) }
    if (e.key === 'ArrowUp') { e.preventDefault(); setActivo((a) => Math.max(a - 1, 0)) }
    if (e.key === 'Enter') { e.preventDefault(); elegir(opciones[activo]) }
    if (e.key === 'Escape') setAbierto(false)
  }

  return (
    <div ref={caja} className="relative">
      <input {...props} value={value} placeholder={placeholder} autoComplete="off"
        onChange={(e) => { onChange(e.target.value); setAbierto(true) }}
        onFocus={() => setAbierto(true)} onKeyDown={teclas}
        className={`w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none ${className}`} />
      {abierto && (opciones.length > 0 || extra) && (
        <ul className="absolute z-30 mt-1 max-h-64 w-full min-w-64 overflow-auto rounded-lg border bg-white py-1 text-sm shadow-lg">
          {opciones.map((op, i) => (
            <li key={op.id} onMouseDown={(e) => { e.preventDefault(); elegir(op) }}
              className={`cursor-pointer px-3 py-2 ${i === activo ? 'bg-blue-50' : 'hover:bg-slate-50'}`}>
              {render(op)}
            </li>
          ))}
          {extra && <li className="border-t px-3 py-2">{extra(() => setAbierto(false))}</li>}
        </ul>
      )}
    </div>
  )
}
