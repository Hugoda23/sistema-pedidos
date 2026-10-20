import { useMemo, useState } from 'react'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { moneda } from '../lib/formato'
import { useListado } from '../lib/useListado'
import DataTable from '../components/DataTable'
import FormModal from '../components/FormModal'
import { Alerta, Badge, Boton, Campo, Encabezado, Input, Select } from '../components/ui'
import { PlusIcon } from '@heroicons/react/24/outline'

const VACIO = { codigo: '', nombre: '', precio_venta: '', descontinuado: false }

export default function Productos() {
  const { puede } = useAuth()
  const [filtros, setFiltros] = useState({ q: '', descontinuado: '' })
  const { filas, pagina, cargando, error, setPage, recargar } = useListado('/productos', filtros)
  const [form, setForm] = useState(null)
  const [aviso, setAviso] = useState('')
  const gestiona = puede('productos.gestionar')

  const columnas = useMemo(() => {
    const eliminar = async (p) => {
      if (!confirm(`¿Eliminar el producto ${p.codigo}?`)) return
      try {
        await api(`/productos/${p.id}`, { method: 'DELETE' })
        recargar()
      } catch (e) {
        setAviso(e.message)
      }
    }
    return [
      { header: 'Código', accessorKey: 'codigo', cell: (c) => <span className="font-mono">{c.getValue()}</span> },
      { header: 'Nombre', accessorKey: 'nombre' },
      { header: 'Último precio', accessorKey: 'precio_venta', cell: (c) => moneda(c.getValue()) },
      { header: 'Estado', accessorKey: 'descontinuado', cell: (c) => c.getValue() ? <Badge color="rojo">Descontinuado</Badge> : <Badge color="verde">Disponible</Badge> },
      ...(gestiona ? [{
        id: 'acciones', header: '',
        cell: ({ row }) => (
          <div className="flex gap-3">
            <button className="text-blue-600 hover:underline" onClick={() => setForm({ ...row.original, precio_venta: row.original.precio_venta ?? '' })}>Editar</button>
            <button className="text-red-600 hover:underline" onClick={() => eliminar(row.original)}>Eliminar</button>
          </div>
        ),
      }] : []),
    ]
  }, [gestiona, recargar])

  return (
    <div>
      <Encabezado titulo="Productos">
        {gestiona && <Boton icono={PlusIcon} onClick={() => setForm(VACIO)}>Nuevo producto</Boton>}
      </Encabezado>
      <p className="-mt-2 mb-3 text-sm text-slate-500">Los productos también se agregan solos al registrar un pedido con un código nuevo.</p>
      <div className="mb-3 grid gap-2 sm:grid-cols-3">
        <Input className="sm:col-span-2" placeholder="Buscar por código o nombre" value={filtros.q} onChange={(e) => setFiltros({ ...filtros, q: e.target.value })} />
        <Select value={filtros.descontinuado} onChange={(e) => setFiltros({ ...filtros, descontinuado: e.target.value })}>
          <option value="">Todos</option>
          <option value="0">Disponibles</option>
          <option value="1">Descontinuados</option>
        </Select>
      </div>
      <Alerta>{error || aviso}</Alerta>
      <DataTable columnas={columnas} datos={filas} pagina={pagina} onPagina={setPage} cargando={cargando} />

      {form && (
        <FormModal titulo={form.id ? 'Editar producto' : 'Nuevo producto'} ruta="/productos" inicial={form}
          onCerrar={() => setForm(null)} onGuardado={() => { setForm(null); recargar() }}
          campos={(d, set, e) => (
            <>
              <Campo label="Código del proveedor" error={e.codigo}><Input required autoFocus className="font-mono uppercase" value={d.codigo} onChange={set('codigo')} /></Campo>
              <Campo label="Nombre / descripción" error={e.nombre}><Input required value={d.nombre} onChange={set('nombre')} /></Campo>
              <Campo label="Precio de venta (referencia)" error={e.precio_venta}><Input type="number" step="0.01" min="0" value={d.precio_venta} onChange={set('precio_venta')} /></Campo>
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={d.descontinuado} onChange={set('descontinuado')} /> Descontinuado</label>
            </>
          )} />
      )}
    </div>
  )
}
