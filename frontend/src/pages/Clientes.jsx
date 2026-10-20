import { useMemo, useState } from 'react'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { useListado } from '../lib/useListado'
import DataTable from '../components/DataTable'
import FormModal from '../components/FormModal'
import { Alerta, Boton, Campo, Encabezado, Input, Textarea } from '../components/ui'
import { PlusIcon } from '@heroicons/react/24/outline'

const VACIO = { nombre: '', telefono: '', moto: '', notas: '' }

export function CamposCliente(d, set, e) {
  return (
    <>
      <Campo label="Nombre" error={e.nombre}><Input required autoFocus value={d.nombre} onChange={set('nombre')} /></Campo>
      <Campo label="Teléfono / WhatsApp" error={e.telefono}><Input value={d.telefono ?? ''} onChange={set('telefono')} placeholder="5555 1234" /></Campo>
      <Campo label="Moto (marca, modelo, año)" error={e.moto}><Input value={d.moto ?? ''} onChange={set('moto')} placeholder="Honda XR150 2020" /></Campo>
      <Campo label="Notas" error={e.notas}><Textarea value={d.notas ?? ''} onChange={set('notas')} /></Campo>
    </>
  )
}

export default function Clientes() {
  const { puede } = useAuth()
  const [q, setQ] = useState('')
  const { filas, pagina, cargando, error, setPage, recargar } = useListado('/clientes', { q })
  const [form, setForm] = useState(null)
  const [aviso, setAviso] = useState('')
  const gestiona = puede('clientes.gestionar')

  const columnas = useMemo(() => {
    const eliminar = async (c) => {
      if (!confirm(`¿Eliminar al cliente «${c.nombre}»?`)) return
      try {
        await api(`/clientes/${c.id}`, { method: 'DELETE' })
        recargar()
      } catch (e) {
        setAviso(e.message)
      }
    }
    return [
      { header: 'Nombre', accessorKey: 'nombre' },
      { header: 'Teléfono', accessorKey: 'telefono', cell: (c) => c.getValue() ?? '—' },
      { header: 'Moto', accessorKey: 'moto', cell: (c) => c.getValue() ?? '—' },
      { header: 'Registrado por', accessorKey: 'creado_por', cell: (c) => c.getValue() ?? '—' },
      ...(gestiona ? [{
        id: 'acciones', header: '',
        cell: ({ row }) => (
          <div className="flex gap-3">
            <button className="text-blue-600 hover:underline" onClick={() => setForm({ ...VACIO, ...row.original })}>Editar</button>
            <button className="text-red-600 hover:underline" onClick={() => eliminar(row.original)}>Eliminar</button>
          </div>
        ),
      }] : []),
    ]
  }, [gestiona, recargar])

  return (
    <div>
      <Encabezado titulo="Clientes">
        {gestiona && <Boton icono={PlusIcon} onClick={() => setForm(VACIO)}>Nuevo cliente</Boton>}
      </Encabezado>
      <Input className="mb-3 sm:max-w-sm" placeholder="Buscar por nombre, teléfono o moto" value={q} onChange={(e) => setQ(e.target.value)} />
      <Alerta>{error || aviso}</Alerta>
      <DataTable columnas={columnas} datos={filas} pagina={pagina} onPagina={setPage} cargando={cargando} />

      {form && (
        <FormModal titulo={form.id ? 'Editar cliente' : 'Nuevo cliente'} ruta="/clientes" inicial={form}
          onCerrar={() => setForm(null)} onGuardado={() => { setForm(null); recargar() }} campos={CamposCliente} />
      )}
    </div>
  )
}
