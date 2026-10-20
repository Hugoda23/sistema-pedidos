import { useState } from 'react'
import { api } from '../lib/api'
import { primerError } from '../lib/errores'
import { Alerta, Boton, Modal } from './ui'

/**
 * Modal de alta/edición genérico: maneja guardado, errores por campo y botones.
 * `campos(d, set, errores)` dibuja los inputs.
 */
export default function FormModal({ titulo, ruta, inicial, onCerrar, onGuardado, campos }) {
  const [d, setD] = useState(inicial)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState('')
  const [guardando, setGuardando] = useState(false)
  const editando = !!inicial.id

  const set = (campo) => (e) =>
    setD((prev) => ({ ...prev, [campo]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const guardar = async (e) => {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setMensaje('')
    try {
      const r = await api(editando ? `${ruta}/${d.id}` : ruta, { method: editando ? 'PUT' : 'POST', body: d })
      onGuardado(r)
    } catch (err) {
      setErrores(primerError(err.errors))
      if (!Object.keys(err.errors).length) setMensaje(err.message)
    } finally {
      setGuardando(false)
    }
  }

  return (
    <Modal abierto titulo={titulo} onCerrar={onCerrar}>
      <form onSubmit={guardar} className="space-y-3">
        <Alerta>{mensaje}</Alerta>
        {campos(d, set, errores)}
        <div className="flex justify-end gap-2 pt-2">
          <Boton type="button" variante="secundario" onClick={onCerrar}>Cancelar</Boton>
          <Boton disabled={guardando}>{guardando ? 'Guardando…' : 'Guardar'}</Boton>
        </div>
      </form>
    </Modal>
  )
}
