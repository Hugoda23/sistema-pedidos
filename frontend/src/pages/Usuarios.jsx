import { useEffect, useMemo, useState } from 'react'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { primerError } from '../lib/errores'
import { useListado } from '../lib/useListado'
import DataTable from '../components/DataTable'
import { Alerta, Badge, Boton, Campo, Encabezado, Input, Modal, Select } from '../components/ui'
import { PlusIcon } from '@heroicons/react/24/outline'

const VACIO = { name: '', email: '', telefono: '', rol: '', activo: true, password: '' }
const COLOR_ROL = { Administrador: 'morado', Vendedor: 'azul', Proveedor: 'amarillo' }

export default function Usuarios() {
  const { puede, user } = useAuth()
  const [filtros, setFiltros] = useState({ q: '', rol: '', activo: '' })
  const { filas, pagina, cargando, error, setPage, recargar } = useListado('/usuarios', filtros)
  const [roles, setRoles] = useState([])
  const [form, setForm] = useState(null)

  useEffect(() => {
    api('/roles').then((d) => setRoles(d.roles.map((r) => r.name))).catch(() => {})
  }, [])

  const columnas = useMemo(() => [
    { header: 'Nombre', accessorKey: 'name' },
    { header: 'Correo', accessorKey: 'email' },
    { header: 'Teléfono', accessorKey: 'telefono', cell: (c) => c.getValue() ?? '—' },
    { header: 'Rol', accessorKey: 'rol', cell: (c) => <Badge color={COLOR_ROL[c.getValue()] ?? 'gris'}>{c.getValue() ?? 'Sin rol'}</Badge> },
    { header: 'Estado', accessorKey: 'activo', cell: (c) => c.getValue() ? <Badge color="verde">Activo</Badge> : <Badge color="rojo">Inactivo</Badge> },
    ...(puede('usuarios.gestionar') ? [{
      id: 'acciones', header: '',
      cell: ({ row }) => (
        <button className="text-blue-600 hover:underline" onClick={() => setForm({ ...VACIO, ...row.original, telefono: row.original.telefono ?? '', password: '' })}>
          Editar
        </button>
      ),
    }] : []),
  ], [puede])

  return (
    <div>
      <Encabezado titulo="Usuarios">
        {puede('usuarios.gestionar') && <Boton icono={PlusIcon} onClick={() => setForm({ ...VACIO, rol: roles[1] ?? '' })}>Nuevo usuario</Boton>}
      </Encabezado>

      <div className="mb-3 grid gap-2 sm:grid-cols-3">
        <Input placeholder="Buscar nombre, correo o teléfono" value={filtros.q} onChange={(e) => setFiltros({ ...filtros, q: e.target.value })} />
        <Select value={filtros.rol} onChange={(e) => setFiltros({ ...filtros, rol: e.target.value })}>
          <option value="">Todos los roles</option>
          {roles.map((r) => <option key={r}>{r}</option>)}
        </Select>
        <Select value={filtros.activo} onChange={(e) => setFiltros({ ...filtros, activo: e.target.value })}>
          <option value="">Activos e inactivos</option>
          <option value="1">Solo activos</option>
          <option value="0">Solo inactivos</option>
        </Select>
      </div>

      <Alerta>{error}</Alerta>
      <DataTable columnas={columnas} datos={filas} pagina={pagina} onPagina={setPage} cargando={cargando} />

      {form && (
        <FormUsuario inicial={form} roles={roles} esYo={form.id === user.id}
          onCerrar={() => setForm(null)} onGuardado={() => { setForm(null); recargar() }} />
      )}
    </div>
  )
}

function FormUsuario({ inicial, roles, esYo, onCerrar, onGuardado }) {
  const [d, setD] = useState(inicial)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState('')
  const [guardando, setGuardando] = useState(false)
  const editando = !!inicial.id
  const set = (campo) => (e) => setD({ ...d, [campo]: e.target.type === 'checkbox' ? e.target.checked : e.target.value })

  const guardar = async (e) => {
    e.preventDefault()
    setGuardando(true)
    setErrores({})
    setMensaje('')
    try {
      await api(editando ? `/usuarios/${d.id}` : '/usuarios', { method: editando ? 'PUT' : 'POST', body: d })
      onGuardado()
    } catch (err) {
      setErrores(primerError(err.errors))
      if (!Object.keys(err.errors).length) setMensaje(err.message)
    } finally {
      setGuardando(false)
    }
  }

  return (
    <Modal abierto titulo={editando ? 'Editar usuario' : 'Nuevo usuario'} onCerrar={onCerrar}>
      <form onSubmit={guardar} className="space-y-3">
        <Alerta>{mensaje}</Alerta>
        <Campo label="Nombre" error={errores.name}><Input required value={d.name} onChange={set('name')} /></Campo>
        <Campo label="Correo" error={errores.email}><Input type="email" required value={d.email} onChange={set('email')} /></Campo>
        <Campo label="Teléfono (WhatsApp)" error={errores.telefono}><Input value={d.telefono} onChange={set('telefono')} placeholder="5555 1234" /></Campo>
        <Campo label="Rol" error={errores.rol}>
          <Select required value={d.rol ?? ''} onChange={set('rol')}>
            <option value="" disabled>Elige un rol</option>
            {roles.map((r) => <option key={r}>{r}</option>)}
          </Select>
        </Campo>
        <Campo label={editando ? 'Nueva contraseña (déjala vacía para no cambiarla)' : 'Contraseña'} error={errores.password}>
          <Input type="password" autoComplete="new-password" required={!editando} value={d.password} onChange={set('password')} />
        </Campo>
        {editando && (
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={d.activo} onChange={set('activo')} disabled={esYo} />
            Usuario activo {esYo && <span className="text-slate-400">(no puedes desactivarte)</span>}
          </label>
        )}
        {errores.activo && <p className="text-xs text-red-600">{errores.activo}</p>}
        <div className="flex justify-end gap-2 pt-2">
          <Boton type="button" variante="secundario" onClick={onCerrar}>Cancelar</Boton>
          <Boton disabled={guardando}>{guardando ? 'Guardando…' : 'Guardar'}</Boton>
        </div>
      </form>
    </Modal>
  )
}
