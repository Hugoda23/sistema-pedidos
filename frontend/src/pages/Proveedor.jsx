import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { fecha, fechaHora } from '../lib/formato'
import { useListado } from '../lib/useListado'
import DataTable from '../components/DataTable'
import { EstadoItem } from '../components/EstadoBadge'
import BotonExcel from '../components/BotonExcel'
import { Alerta, Boton, Encabezado, Input, Select } from '../components/ui'
import { DialogoEstado, ResultadoWhatsapp } from '../components/AccionesProveedor'

const PESTANAS = [
  ['por_atender', 'Por atender'],
  ['revisar', 'Revisar ingreso'],
  ['no_disponible', 'No disponibles'],
  ['todos', 'Todos'],
]

export default function Proveedor() {
  const { puede } = useAuth()
  const actualiza = puede('proveedor.actualizar')
  const [params] = useSearchParams()
  const [filtros, setFiltros] = useState({ estado: params.get('estado') ?? 'por_atender', q: '', pedido_id: '', por_pagina: 50 })
  useEffect(() => { if (params.get('estado')) setFiltros((f) => ({ ...f, estado: params.get('estado') })) }, [params])
  const { filas, pagina, cargando, error, setPage, recargar } = useListado('/proveedor/items', filtros)
  const [pedidos, setPedidos] = useState([]) // pedidos de los vendedores, para el filtro
  const [marcados, setMarcados] = useState(new Set())
  const [dialogo, setDialogo] = useState(null) // { estado, ids }
  const [aviso, setAviso] = useState({ tipo: 'ok', texto: '' })
  const [whatsapp, setWhatsapp] = useState(null) // { enviados, sin_telefono, vinculado }

  const cargarPedidos = useCallback(() => api('/proveedor/pedidos').then(setPedidos).catch(() => {}), [])
  useEffect(() => { cargarPedidos() }, [cargarPedidos])
  useEffect(() => setMarcados(new Set()), [filas])

  const set = (k) => (e) => setFiltros({ ...filtros, [k]: e.target.value })

  const refrescar = () => { recargar(); cargarPedidos() }


  const cambiar = async (estado, ids, extra = {}) => {
    setAviso({ texto: '' })
    try {
      const r = await api('/proveedor/items/estado', { method: 'POST', body: { ids, estado, ...extra } })
      setAviso({ tipo: 'ok', texto: `${r.actualizados} línea(s) actualizada(s).` })
      setWhatsapp(r.whatsapp)
      setDialogo(null)
      refrescar()
      return null
    } catch (e) {
      return e
    }
  }


  const alternar = (id) => setMarcados((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n })
  const seleccionables = filas.filter((f) => f.acciones.length)
  const todos = seleccionables.length > 0 && seleccionables.every((f) => marcados.has(f.id))

  const columnas = useMemo(() => [
    ...(actualiza ? [{
      id: 'sel',
      header: () => <input type="checkbox" aria-label="Marcar todas" checked={todos} onChange={() => setMarcados(todos ? new Set() : new Set(seleccionables.map((f) => f.id)))} />,
      cell: ({ row }) => row.original.acciones.length > 0 && <input type="checkbox" aria-label="Marcar" checked={marcados.has(row.original.id)} onChange={() => alternar(row.original.id)} />,
    }] : []),
    { header: 'Código', accessorKey: 'codigo', cell: (c) => <span className="font-mono font-medium">{c.getValue()}</span> },
    { header: 'Descripción', accessorKey: 'descripcion', cell: (c) => <div className="min-w-28 max-w-60 whitespace-normal">{c.getValue()}</div> },
    { header: 'Cant.', accessorKey: 'cantidad', cell: (c) => <span className="font-semibold">{c.getValue()}</span> },
    { header: 'Vendedor', accessorKey: 'vendedor', cell: (c) => <div className="min-w-20 whitespace-normal">{c.getValue()}</div> },
    { header: 'Pedido', accessorKey: 'pedido', cell: (c) => <span className="font-medium text-slate-800">{c.getValue()}</span> },
    { header: 'Fecha', accessorKey: 'solicitado', cell: (c) => <span title={fechaHora(c.getValue())}>{fecha(c.getValue())}</span> },
    {
      header: 'Estado', accessorKey: 'estado',
      cell: ({ row: { original: f } }) => (
        <div className="max-w-44 whitespace-normal">
          <EstadoItem estado={f.estado} />
          {f.fecha_ingreso && <div className={`text-xs ${f.revisar_ingreso ? 'font-semibold text-red-600' : 'text-amber-700'}`}>{f.revisar_ingreso ? '¿Ya ingresó? ' : 'Ingreso aprox. '}{fecha(f.fecha_ingreso)}</div>}
          {f.nota_proveedor && <div className="max-w-44 truncate text-xs text-slate-500" title={f.nota_proveedor}>{f.nota_proveedor}</div>}
        </div>
      ),
    },
    ...(actualiza ? [{
      id: 'acciones', header: '',
      cell: ({ row: { original: f } }) => (
        <div className="flex flex-col items-start gap-0.5 text-sm">
          {f.acciones.includes('enviado') && <button className="text-blue-600 hover:underline" onClick={() => cambiar('enviado', [f.id])}>Enviado</button>}
          {f.acciones.includes('no_disponible') && <button className="text-amber-700 hover:underline" onClick={() => setDialogo({ estado: 'no_disponible', ids: [f.id], fila: f })}>{f.estado === 'no_disponible' ? 'Nueva fecha' : 'No disponible'}</button>}
          {f.acciones.includes('descontinuado') && <button className="text-red-600 hover:underline" onClick={() => setDialogo({ estado: 'descontinuado', ids: [f.id], fila: f })}>Descontinuado</button>}
        </div>
      ),
    }] : []),
  // eslint-disable-next-line react-hooks/exhaustive-deps
  ], [actualiza, marcados, todos, filas])


  return (
    <div>
      <Encabezado titulo="Repuestos solicitados">
        <BotonExcel ruta="/proveedor/exportar" params={{ estado: filtros.estado, q: filtros.q, pedido_id: filtros.pedido_id }} nombre="repuestos.xlsx"
          onError={(m) => setAviso({ tipo: 'error', texto: m })} />
      </Encabezado>



      <div className="mb-3 flex flex-wrap gap-1 border-b">
        {PESTANAS.map(([k, t]) => (
          <button key={k} onClick={() => setFiltros({ ...filtros, estado: k })}
            className={`-mb-px border-b-2 px-3 py-2 text-sm ${filtros.estado === k ? 'border-blue-600 font-medium text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>
            {t}
          </button>
        ))}
      </div>

      <div className="mb-3 grid gap-2 sm:grid-cols-3">
        <Input className="sm:col-span-2" placeholder="Buscar código, descripción o número de pedido" value={filtros.q} onChange={set('q')} />
        <Select value={filtros.pedido_id} onChange={set('pedido_id')} aria-label="Filtrar por pedido">
          <option value="">Todos los pedidos</option>
          {pedidos.map((p) => (
            <option key={p.id} value={p.id}>{p.numero} · {p.vendedor}{p.por_atender ? ` · ${p.por_atender} por atender` : ''}</option>
          ))}
        </Select>
      </div>


      <Alerta tipo={aviso.tipo}>{aviso.texto}</Alerta>
      <Alerta>{error}</Alerta>
      {whatsapp && <ResultadoWhatsapp r={whatsapp} onCerrar={() => setWhatsapp(null)} />}

      {actualiza && marcados.size > 0 && (
        <div className="sticky top-0 z-10 mb-2 flex flex-wrap items-center gap-2 rounded-lg bg-slate-800 p-2 text-sm text-white">
          <span className="px-2">{marcados.size} seleccionada(s)</span>
          <Boton onClick={() => cambiar('enviado', [...marcados])}>Marcar enviado</Boton>
          <Boton variante="secundario" onClick={() => setDialogo({ estado: 'no_disponible', ids: [...marcados] })}>No disponible…</Boton>
          <Boton variante="peligro" onClick={() => setDialogo({ estado: 'descontinuado', ids: [...marcados] })}>Descontinuado…</Boton>
        </div>
      )}
      <DataTable columnas={columnas} datos={filas} pagina={pagina} onPagina={setPage} cargando={cargando}
        vacio="No hay repuestos en esta vista." claseFila={(f) => (f.revisar_ingreso ? 'bg-red-50' : '')} />

      {dialogo && <DialogoEstado dialogo={dialogo} onCerrar={() => setDialogo(null)} onEnviar={cambiar} />}
    </div>
  )
}
