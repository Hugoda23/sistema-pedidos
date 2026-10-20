import { useCallback, useEffect, useState } from 'react'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { primerError } from '../lib/errores'
import { Alerta, Badge, Boton, Campo, Encabezado, Input, Modal } from '../components/ui'

export default function Roles() {
  const { puede } = useAuth()
  const [datos, setDatos] = useState(null)
  const [error, setError] = useState('')
  const [form, setForm] = useState(null)
  const gestiona = puede('roles.gestionar')

  const cargar = useCallback(() => api('/roles').then(setDatos).catch((e) => setError(e.message)), [])
  useEffect(() => { cargar() }, [cargar])

  const eliminar = async (rol) => {
    if (!confirm(`¿Eliminar el rol «${rol.name}»?`)) return
    try {
      await api(`/roles/${rol.id}`, { method: 'DELETE' })
      cargar()
    } catch (e) {
      setError(e.errors?.name?.[0] ?? e.message)
    }
  }

  const total = datos?.permisos.reduce((n, m) => n + m.permisos.length, 0)

  return (
    <div>
      <Encabezado titulo="Roles y permisos">
        {gestiona && <Boton onClick={() => setForm({ name: '', permisos: [] })}>Nuevo rol</Boton>}
      </Encabezado>
      <Alerta>{error}</Alerta>

      <div className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {datos?.roles.map((r) => (
          <div key={r.id} className="rounded-xl border bg-white p-4">
            <div className="flex items-start justify-between">
              <div>
                <h2 className="font-semibold text-slate-800">{r.name}</h2>
                <p className="text-xs text-slate-500">{r.usuarios} usuario(s) · {r.permisos.length} de {total} permisos</p>
              </div>
              {r.base && <Badge>Base</Badge>}
            </div>
            {gestiona && (
              <div className="mt-3 flex gap-3 text-sm">
                {r.name !== 'Administrador'
                  ? <button className="text-blue-600 hover:underline" onClick={() => setForm(r)}>Editar permisos</button>
                  : <span className="text-slate-400">Tiene todos los permisos</span>}
                {!r.base && <button className="text-red-600 hover:underline" onClick={() => eliminar(r)}>Eliminar</button>}
              </div>
            )}
          </div>
        ))}
      </div>

      {form && datos && (
        <FormRol inicial={form} catalogo={datos.permisos} onCerrar={() => setForm(null)} onGuardado={() => { setForm(null); cargar() }} />
      )}
    </div>
  )
}

function FormRol({ inicial, catalogo, onCerrar, onGuardado }) {
  const [nombre, setNombre] = useState(inicial.name)
  const [marcados, setMarcados] = useState(new Set(inicial.permisos))
  const [errores, setErrores] = useState({})
  const [guardando, setGuardando] = useState(false)
  const editando = !!inicial.id

  const alternar = (p) => {
    const s = new Set(marcados)
    s.has(p) ? s.delete(p) : s.add(p)
    setMarcados(s)
  }
  const alternarModulo = (permisos, todos) => {
    const s = new Set(marcados)
    permisos.forEach((p) => (todos ? s.delete(p.name) : s.add(p.name)))
    setMarcados(s)
  }

  const guardar = async (e) => {
    e.preventDefault()
    setGuardando(true)
    try {
      await api(editando ? `/roles/${inicial.id}` : '/roles', {
        method: editando ? 'PUT' : 'POST', body: { name: nombre, permisos: [...marcados] },
      })
      onGuardado()
    } catch (err) {
      setErrores(primerError(err.errors))
    } finally {
      setGuardando(false)
    }
  }

  return (
    <Modal abierto titulo={editando ? `Rol: ${inicial.name}` : 'Nuevo rol'} onCerrar={onCerrar} ancho="max-w-2xl">
      <form onSubmit={guardar} className="space-y-4">
        <Campo label="Nombre del rol" error={errores.name}>
          <Input required value={nombre} onChange={(e) => setNombre(e.target.value)} disabled={inicial.base} />
        </Campo>
        <div className="grid gap-3 sm:grid-cols-2">
          {catalogo.map((m) => {
            const todos = m.permisos.every((p) => marcados.has(p.name))
            return (
              <fieldset key={m.modulo} className="rounded-lg border p-3">
                <legend className="flex items-center gap-2 px-1 text-sm font-semibold text-slate-700">
                  {m.modulo}
                  <button type="button" className="text-xs font-normal text-blue-600" onClick={() => alternarModulo(m.permisos, todos)}>
                    {todos ? 'quitar todos' : 'marcar todos'}
                  </button>
                </legend>
                {m.permisos.map((p) => (
                  <label key={p.name} className="flex items-center gap-2 py-0.5 text-sm text-slate-600">
                    <input type="checkbox" checked={marcados.has(p.name)} onChange={() => alternar(p.name)} />
                    {p.descripcion}
                  </label>
                ))}
              </fieldset>
            )
          })}
        </div>
        <div className="flex justify-end gap-2">
          <Boton type="button" variante="secundario" onClick={onCerrar}>Cancelar</Boton>
          <Boton disabled={guardando}>{guardando ? 'Guardando…' : 'Guardar'}</Boton>
        </div>
      </form>
    </Modal>
  )
}
