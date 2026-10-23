import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { ESTADO_PEDIDO } from '../lib/estados'
import { fecha, moneda } from '../lib/formato'
import { useListado } from '../lib/useListado'
import DataTable from '../components/DataTable'
import { EstadoPedido } from '../components/EstadoBadge'
import BotonExcel from '../components/BotonExcel'
import { Alerta, Boton, Encabezado, Input, Select } from '../components/ui'
import { PlusIcon } from '@heroicons/react/24/outline'

export default function Pedidos() {
  const { puede } = useAuth()
  const navegar = useNavigate()
  const verTodos = puede('pedidos.ver_todos')
  const [params] = useSearchParams()
  const [filtros, setFiltros] = useState({ q: '', estado: params.get('estado') ?? '', vendedor_id: '', desde: '', hasta: '', con_saldo: '' })
  const { filas, pagina, cargando, error, setPage } = useListado('/pedidos', filtros)
  const [errorExcel, setErrorExcel] = useState('')
  const [vendedores, setVendedores] = useState([])
  const set = (k) => (e) => setFiltros({ ...filtros, [k]: e.target.type === 'checkbox' ? (e.target.checked ? 1 : '') : e.target.value })

  useEffect(() => {
    if (verTodos && puede('usuarios.ver')) {
      api('/usuarios', { params: { rol: 'Vendedor', por_pagina: 100 } }).then((r) => setVendedores(r.data)).catch(() => {})
    }
  }, [verTodos, puede])

  const columnas = useMemo(() => [
    { header: 'Número', accessorKey: 'numero', cell: ({ row }) => <Link to={`/pedidos/${row.original.id}`} className="font-medium text-blue-600 hover:underline">{row.original.numero}</Link> },
    { header: 'Fecha', accessorKey: 'fecha', cell: (c) => fecha(c.getValue()) },
    { header: 'Cliente', accessorKey: 'cliente' },
    ...(verTodos ? [{ header: 'Vendedor', accessorKey: 'vendedor' }] : []),
    { header: 'Repuestos', accessorKey: 'items' },
    { header: 'Total', accessorKey: 'total', cell: (c) => moneda(c.getValue()) },
    { header: 'Saldo', accessorKey: 'saldo', cell: (c) => <span className={Number(c.getValue()) > 0 ? 'font-medium text-red-600' : 'text-slate-400'}>{moneda(c.getValue())}</span> },
    { header: 'Estado', accessorKey: 'estado', cell: (c) => <EstadoPedido estado={c.getValue()} /> },
  ], [verTodos])

  return (
    <div>
      <Encabezado titulo="Pedidos">
        <BotonExcel ruta="/pedidos/exportar" params={filtros} nombre="pedidos.xlsx" onError={setErrorExcel} />
        {puede('pedidos.gestionar') && <Boton icono={PlusIcon} onClick={() => navegar('/pedidos/nuevo')}>Nuevo pedido</Boton>}
      </Encabezado>

      <div className="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <Input className="lg:col-span-2" placeholder="Buscar número, cliente o código" value={filtros.q} onChange={set('q')} />
        <Select value={filtros.estado} onChange={set('estado')}>
          <option value="">Todos los estados</option>
          {Object.entries(ESTADO_PEDIDO).map(([k, [t]]) => <option key={k} value={k}>{t}</option>)}
        </Select>
        {vendedores.length > 0 && (
          <Select value={filtros.vendedor_id} onChange={set('vendedor_id')}>
            <option value="">Todos los vendedores</option>
            {vendedores.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
          </Select>
        )}
        <Input type="date" value={filtros.desde} onChange={set('desde')} title="Desde" />
        <Input type="date" value={filtros.hasta} onChange={set('hasta')} title="Hasta" />
      </div>
      <label className="mb-3 flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" checked={!!filtros.con_saldo} onChange={set('con_saldo')} /> Solo con saldo pendiente
      </label>

      <Alerta>{error || errorExcel}</Alerta>
      <DataTable columnas={columnas} datos={filas} pagina={pagina} onPagina={setPage} cargando={cargando} vacio="No hay pedidos." />
    </div>
  )
}
