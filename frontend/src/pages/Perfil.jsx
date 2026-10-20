import { useState } from 'react'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { primerError } from '../lib/errores'
import { Alerta, Boton, Campo, Encabezado, Input } from '../components/ui'

export default function Perfil() {
  const { user, actualizarUsuario } = useAuth()
  const [d, setD] = useState({ name: user.name, telefono: user.telefono ?? '', password_actual: '', password: '', password_confirmation: '' })
  const [errores, setErrores] = useState({})
  const [ok, setOk] = useState('')
  const set = (k) => (e) => setD({ ...d, [k]: e.target.value })

  const guardar = async (e) => {
    e.preventDefault()
    setErrores({})
    setOk('')
    try {
      const r = await api('/perfil', { method: 'PUT', body: d })
      actualizarUsuario(r.user)
      setD({ ...d, password_actual: '', password: '', password_confirmation: '' })
      setOk('Datos guardados.')
    } catch (err) {
      setErrores(primerError(err.errors))
    }
  }

  return (
    <div className="max-w-lg">
      <Encabezado titulo="Mi perfil" />
      <form onSubmit={guardar} className="space-y-3 rounded-xl border bg-white p-5">
        <Alerta tipo="ok">{ok}</Alerta>
        <p className="text-sm text-slate-500">{user.email} · {user.roles.join(', ')}</p>
        <Campo label="Nombre" error={errores.name}><Input required value={d.name} onChange={set('name')} /></Campo>
        <Campo label="Teléfono / WhatsApp" error={errores.telefono}>
          <Input value={d.telefono} onChange={set('telefono')} placeholder="5555 1234" />
        </Campo>
        <p className="text-xs text-slate-500">Es el número al que te llegan los avisos por WhatsApp.</p>
        <hr />
        <p className="text-sm font-medium text-slate-700">Cambiar contraseña (opcional)</p>
        <Campo label="Contraseña actual" error={errores.password_actual}><Input type="password" autoComplete="current-password" value={d.password_actual} onChange={set('password_actual')} /></Campo>
        <Campo label="Nueva contraseña" error={errores.password}><Input type="password" autoComplete="new-password" value={d.password} onChange={set('password')} /></Campo>
        <Campo label="Repite la nueva contraseña"><Input type="password" autoComplete="new-password" value={d.password_confirmation} onChange={set('password_confirmation')} /></Campo>
        <div className="flex justify-end"><Boton>Guardar</Boton></div>
      </form>
    </div>
  )
}
