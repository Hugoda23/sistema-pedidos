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
import { DocumentTextIcon, ListBulletIcon, Squares2X2Icon } from '@heroicons/react/24/outline'
import VistaPreviaDocumento from '../components/VistaPreviaDocumento'

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
  const [filtros, setFiltros] = useState({ estado: params.get('estado') ?? 'por_atender', q: '', lote_id: '', por_pagina: 50 })
  useEffect(() => { if (params.get('estado')) setFiltros((f) => ({ ...f, estado: params.get('estado') })) }, [params])
  const [vista, setVista] = useState('detalle')
  const { filas, pagina, cargando, error, setPage, recargar } = useListado('/proveedor/items', filtros)
  const [consolidado, setConsolidado] = useState([])
  const [lotes, setLotes] = useState([])
  const [marcados, setMarcados] = useState(new Set())
  const [dialogo, setDialogo] = useState(null) // { estado, ids }
  const [aviso, setAviso] = useState({ tipo: 'ok', texto: '' })
  const [whatsapp, setWhatsapp] = useState(null) // { enviados, sin_telefono, vinculado }
  const [reporte, setReporte] = useState(null) // { id, numero } del pedido a mostrar en PDF

  const cargarLotes = useCallback(() => api('/proveedor/lotes').then(setLotes).catch(() => {}), [])
  useEffect(() => { cargarLotes() }, [cargarLotes])
  useEffect(() => setMarcados(new Set()), [filas])
  useEffect(() => {
    if (vista !== 'consolidado') return
    const { estado, q, lote_id } = filtros
    api('/proveedor/consolidado', { params: { estado, q, lote_id } }).then(setConsolidado).catch(() => {})
  }, [vista, filtros])

  const abierto = lotes.find((l) => l.estado === 'abierto')
  const set = (k) => (e) => setFiltros({ ...filtros, [k]: e.target.value })

  const refrescar = () => { recargar(); cargarLotes() }


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
    { header: 'Descripción', accessorKey: 'descripcion', cell: (c) => <div className="min-w-40 max-w-xs whitespace-normal">{c.getValue()}</div> },
    { header: 'Cant.', accessorKey: 'cantidad', cell: (c) => <span className="font-semibold">{c.getValue()}</span> },
    { header: 'Vendedor', accessorKey: 'vendedor' },
    { header: 'Solicitado', accessorKey: 'solicitado', cell: (c) => <span title={fechaHora(c.getValue())}>{fecha(c.getValue())}</span> },
    {
      header: 'Estado', accessorKey: 'estado',
      cell: ({ row: { original: f } }) => (
        <div>
          <EstadoItem estado={f.estado} />
          {f.fecha_ingreso && <div className={`text-xs ${f.revisar_ingreso ? 'font-semibold text-red-600' : 'text-amber-700'}`}>{f.revisar_ingreso ? '¿Ya ingresó? ' : 'Ingreso aprox. '}{fecha(f.fecha_ingreso)}</div>}
          {f.nota_proveedor && <div className="max-w-56 truncate text-xs text-slate-500" title={f.nota_proveedor}>{f.nota_proveedor}</div>}
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

  const columnasConsolidado = useMemo(() => [
    { header: 'Código', accessorKey: 'codigo', cell: (c) => <span className="font-mono font-medium">{c.getValue()}</span> },
    { header: 'Descripción', accessorKey: 'descripcion' },
    { header: 'Total unidades', accessorKey: 'cantidad', cell: (c) => <span className="font-semibold">{c.getValue()}</span> },
    { header: 'Líneas', accessorKey: 'lineas' },
  ], [])

  return (
    <div>
      <Encabezado titulo="Repuestos solicitados">
        <BotonExcel ruta="/proveedor/exportar" params={{ estado: filtros.estado, q: filtros.q, lote_id: filtros.lote_id }} nombre="repuestos.xlsx"
          onError={(m) => setAviso({ tipo: 'error', texto: m })} />
      </Encabezado>

      {abierto && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4">
          <div className="text-sm">
            <div className="font-semibold text-blue-900">Pedido de clientes #{abierto.numero} abierto</div>
            <div className="text-blue-800">Desde {fechaHora(abierto.abierto_en)} · {abierto.lineas} línea(s) · {abierto.unidades} unidad(es) · {abierto.por_atender} por atender</div>
          </div>
          <div className="flex gap-2">
            <Boton variante="secundario" onClick={() => { setFiltros({ ...filtros, lote_id: String(abierto.id), estado: 'todos' }); setVista('consolidado') }}>Total por código</Boton>
          </div>
        </div>
      )}

      {lotes.length > 0 && !abierto && (
        <p className="mb-4 rounded-xl border bg-white p-4 text-sm text-slate-600">No hay pedido de clientes abierto. Lo próximo que soliciten los vendedores abrirá uno nuevo.</p>
      )}

      <div className="mb-3 flex flex-wrap gap-1 border-b">
        {PESTANAS.map(([k, t]) => (
          <button key={k} onClick={() => setFiltros({ ...filtros, estado: k })}
            className={`-mb-px border-b-2 px-3 py-2 text-sm ${filtros.estado === k ? 'border-blue-600 font-medium text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>
            {t}
          </button>
        ))}
      </div>

      <div className="mb-3 grid gap-2 sm:grid-cols-3">
        <Input className="sm:col-span-2" placeholder="Buscar código o descripción" value={filtros.q} onChange={set('q')} />
        <div className="flex gap-2">
          <Select value={filtros.lote_id} onChange={set('lote_id')}>
            <option value="">Todos los pedidos</option>
            {lotes.map((l) => (
              <option key={l.id} value={l.id}>#{l.numero} · {l.estado === 'abierto' ? 'abierto' : `cerrado ${fecha(l.cerrado_en)}`} · {l.lineas} líneas</option>
            ))}
          </Select>
          {filtros.lote_id && (
            <Boton variante="secundario" icono={DocumentTextIcon} title="Reporte del pedido en PDF"
              onClick={() => setReporte({ id: filtros.lote_id, numero: lotes.find((l) => String(l.id) === String(filtros.lote_id))?.numero })}>Reporte</Boton>
          )}
        </div>
      </div>

      {/* Dos formas de ver la misma lista. */}
      <div className="mb-3 flex flex-wrap items-center gap-3">
        <div className="inline-flex rounded-lg border bg-white p-0.5" role="group" aria-label="Forma de ver">
          {[['detalle', 'Detalle por pedido', <ListBulletIcon key="i" className="h-4 w-4" />], ['consolidado', 'Total por código', <Squares2X2Icon key="i" className="h-4 w-4" />]].map(([k, t, icono]) => (
            <button key={k} onClick={() => setVista(k)} aria-pressed={vista === k}
              className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm ${vista === k ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
              {icono}{t}
            </button>
          ))}
        </div>
        <p className="text-sm text-slate-500">
          {vista === 'detalle'
            ? 'Una fila por cada repuesto que pidió cada vendedor para su cliente. Aquí se marca enviado, no disponible o descontinuado.'
            : 'Junta las filas del mismo código y suma las unidades: lo que hay que despachar en total. Aquí no se marcan estados.'}
        </p>
      </div>

      <Alerta tipo={aviso.tipo}>{aviso.texto}</Alerta>
      <Alerta>{error}</Alerta>
      {whatsapp && <ResultadoWhatsapp r={whatsapp} onCerrar={() => setWhatsapp(null)} />}

      {vista === 'detalle' ? (
        <>
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
        </>
      ) : (
        <DataTable columnas={columnasConsolidado} datos={consolidado} vacio="No hay repuestos en esta vista." />
      )}

      {reporte && (
        <VistaPreviaDocumento titulo={`Reporte · Pedido de clientes #${reporte.numero}`} ruta={`/proveedor/lotes/${reporte.id}/reporte`}
          nombrePdf={`pedido-${reporte.numero}.pdf`} onCerrar={() => setReporte(null)} />
      )}
      {dialogo && <DialogoEstado dialogo={dialogo} onCerrar={() => setDialogo(null)} onEnviar={cambiar} />}
    </div>
  )
}
