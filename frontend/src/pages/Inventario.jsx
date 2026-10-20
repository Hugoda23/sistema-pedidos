import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { primerError } from '../lib/errores'
import { fecha, fechaHora } from '../lib/formato'
import { useListado } from '../lib/useListado'
import DataTable from '../components/DataTable'
import BotonExcel from '../components/BotonExcel'
import { EstadoItem } from '../components/EstadoBadge'
import { DialogoEstado, ResultadoWhatsapp } from '../components/AccionesProveedor'
import VistaPreviaDocumento from '../components/VistaPreviaDocumento'
import { Alerta, Boton, Campo, Encabezado, Input, Modal } from '../components/ui'
import { DocumentTextIcon, ExclamationTriangleIcon, LockClosedIcon, PlusIcon } from '@heroicons/react/24/outline'

const PESTANAS = [
  ['activos', 'Pendientes'],
  ['revisar', 'Revisar ingreso'],
  ['atendidos', 'Enviados y descontinuados'],
  ['pedidos', 'Pedidos'],
]

const VACIO = { codigo: '', descripcion: '', cantidad: 1 }

export default function Inventario() {
  const { puede } = useAuth()
  const gestiona = puede('inventario.gestionar')
  const atiende = puede('inventario.atender')
  const cierra = puede('proveedor.cerrar_lote')
  const [filtros, setFiltros] = useState({ estado: 'activos', q: '', por_pagina: 50 })
  const { filas, pagina, cargando, error, setPage, recargar } = useListado('/inventario', filtros)
  const [nuevo, setNuevo] = useState(VACIO)
  const [errores, setErrores] = useState({})
  const [duplicado, setDuplicado] = useState(null) // { existente, datos }
  const [editar, setEditar] = useState(null)
  const [marcados, setMarcados] = useState(new Set())
  const [dialogo, setDialogo] = useState(null)
  const [aviso, setAviso] = useState({ tipo: 'ok', texto: '' })
  const [whatsapp, setWhatsapp] = useState(null)
  const [pedidos, setPedidos] = useState([])
  const [reporte, setReporte] = useState(null) // id del pedido

  const cargarPedidos = useCallback(() => api('/inventario/pedidos').then(setPedidos).catch(() => {}), [])
  useEffect(() => { cargarPedidos() }, [cargarPedidos])
  const abierto = pedidos.find((p) => p.abierto)
  const verPedidos = filtros.estado === 'pedidos'
  // Al entrar a «Pedidos» se recargan los totales (pudieron cambiar por ediciones o envíos).
  useEffect(() => { if (verPedidos) cargarPedidos() }, [verPedidos, cargarPedidos])

  const agregar = async (datos) => {
    setErrores({})
    setAviso({ texto: '' })
    try {
      const r = await api('/inventario', { method: 'POST', body: datos })
      setNuevo(VACIO)
      setDuplicado(null)
      cargarPedidos()
      setAviso({ tipo: 'ok', texto: r.sumado ? `Se sumaron ${datos.cantidad} a ${r.codigo}: ahora son ${r.cantidad}.` : `${r.codigo} agregado.` })
      recargar()
    } catch (e) {
      if (e.status === 409) { setDuplicado({ existente: e.data.existente, datos }); return }
      setErrores(primerError(e.errors))
      if (!Object.keys(e.errors).length) setAviso({ tipo: 'error', texto: e.message })
    }
  }

  const quitar = async (f) => {
    if (!confirm(`¿Quitar ${f.codigo} del pedido de inventario?`)) return
    try { await api(`/inventario/${f.id}`, { method: 'DELETE' }); recargar(); cargarPedidos() } catch (e) { setAviso({ tipo: 'error', texto: e.message }) }
  }

  const cambiar = async (estado, ids, extra = {}) => {
    setAviso({ texto: '' })
    try {
      const r = await api('/inventario/estado', { method: 'POST', body: { ids, estado, ...extra } })
      setAviso({ tipo: 'ok', texto: `${r.actualizados} repuesto(s) actualizado(s).` })
      setWhatsapp(r.whatsapp)
      setDialogo(null)
      setMarcados(new Set())
      recargar()
      cargarPedidos()
      return null
    } catch (e) {
      return e
    }
  }

  /** El proveedor cierra el pedido consolidado: lo que se agregue después cae en el siguiente. */
  const cerrarPedido = async () => {
    const cli = abierto.lineas_clientes ? ` y ${abierto.lineas_clientes} línea(s) de pedidos de clientes` : ''
    if (!confirm(`¿Cerrar el pedido #${abierto.id} con ${abierto.repuestos} repuesto(s) de inventario${cli}? Lo que se agregue después irá al siguiente pedido.`)) return
    setAviso({ texto: '' })
    try {
      const r = await api('/proveedor/lotes/cerrar', { method: 'POST' })
      setAviso({ tipo: 'ok', texto: `Pedido #${r.id} cerrado.` })
      setWhatsapp(r.whatsapp)
      recargar()
      cargarPedidos()
    } catch (e) {
      setAviso({ tipo: 'error', texto: e.message })
    }
  }

  const alternar = (id) => setMarcados((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n })
  const seleccionables = filas.filter((f) => f.acciones.length)
  const todos = seleccionables.length > 0 && seleccionables.every((f) => marcados.has(f.id))
  const atendidos = filtros.estado === 'atendidos'

  const columnas = useMemo(() => [
    ...(atiende && !atendidos ? [{
      id: 'sel',
      header: () => <input type="checkbox" aria-label="Marcar todas" checked={todos} onChange={() => setMarcados(todos ? new Set() : new Set(seleccionables.map((f) => f.id)))} />,
      cell: ({ row }) => row.original.acciones.length > 0 && <input type="checkbox" aria-label="Marcar" checked={marcados.has(row.original.id)} onChange={() => alternar(row.original.id)} />,
    }] : []),
    { header: 'Código', accessorKey: 'codigo', cell: (c) => <span className="font-mono font-medium">{c.getValue()}</span> },
    { header: 'Descripción', accessorKey: 'descripcion', cell: (c) => <div className="min-w-32 max-w-xs whitespace-normal">{c.getValue()}</div> },
    { header: 'Cant.', accessorKey: 'cantidad', cell: (c) => <span className="font-semibold">{c.getValue()}</span> },
    {
      header: 'Agregado por', id: 'aportes',
      cell: ({ row: { original: f } }) => <div className="whitespace-normal">{f.aportes.map((a) => `${a.nombre} (${a.cantidad})`).join(', ')}</div>,
    },
    { header: 'Fecha', accessorKey: 'fecha', cell: (c) => <span title={fechaHora(c.getValue())}>{fecha(c.getValue())}</span> },
    {
      header: 'Estado', accessorKey: 'estado',
      cell: ({ row: { original: f } }) => (
        <div>
          <EstadoItem estado={f.estado} />
          {f.lote && <div className="text-xs text-slate-500">Pedido #{f.lote.id}{f.lote.abierto ? ' (abierto)' : ''}</div>}
          {f.fecha_ingreso && (
            <div className={`text-xs ${f.revisar_ingreso ? 'font-semibold text-red-600' : 'text-amber-700'}`}>
              {f.revisar_ingreso && <ExclamationTriangleIcon className="mr-0.5 inline h-3.5 w-3.5 align-[-2px]" />}
              {f.revisar_ingreso ? '¿Ya ingresó? ' : 'Ingreso aprox. '}{fecha(f.fecha_ingreso)}
            </div>
          )}
          {f.nota_proveedor && <div className="max-w-56 truncate text-xs text-slate-500" title={f.nota_proveedor}>{f.nota_proveedor}</div>}
          {atendidos && f.atendido_en && <div className="text-xs text-slate-500">{f.atendido_por} · {fechaHora(f.atendido_en)}</div>}
        </div>
      ),
    },
    {
      id: 'acciones', header: '',
      cell: ({ row: { original: f } }) => (
        <div className="flex flex-col items-start gap-0.5 text-sm">
          {f.editable && <button className="text-blue-600 hover:underline" onClick={() => setEditar(f)}>Editar</button>}
          {f.editable && <button className="text-slate-500 hover:underline" onClick={() => quitar(f)}>Quitar</button>}
          {f.acciones.includes('enviado') && <button className="text-blue-600 hover:underline" onClick={() => cambiar('enviado', [f.id])}>Enviado</button>}
          {f.acciones.includes('no_disponible') && <button className="text-amber-700 hover:underline" onClick={() => setDialogo({ estado: 'no_disponible', ids: [f.id], fila: f })}>{f.estado === 'no_disponible' ? 'Nueva fecha' : 'No disponible'}</button>}
          {f.acciones.includes('descontinuado') && <button className="text-red-600 hover:underline" onClick={() => setDialogo({ estado: 'descontinuado', ids: [f.id], fila: f })}>Descontinuado</button>}
        </div>
      ),
    },
  // eslint-disable-next-line react-hooks/exhaustive-deps
  ], [atiende, atendidos, marcados, todos, filas])

  return (
    <div>
      <Encabezado titulo={
        <span className="flex flex-wrap items-center gap-3">
          Pedido de inventario
          {abierto
            ? <span className="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">Pedido #{abierto.id} · abierto desde {fecha(abierto.abierto_en)}</span>
            : <span className="rounded-full bg-slate-200 px-3 py-1 text-sm font-medium text-slate-600">Sin pedido abierto</span>}
        </span>
      }>
        {cierra && abierto && abierto.repuestos + abierto.lineas_clientes > 0 && (
          <Boton icono={LockClosedIcon} onClick={cerrarPedido}>Cerrar pedido #{abierto.id}</Boton>
        )}
        <BotonExcel ruta="/inventario/exportar" params={{ estado: filtros.estado, q: filtros.q }} nombre="inventario.xlsx"
          onError={(m) => setAviso({ tipo: 'error', texto: m })} />
      </Encabezado>
      <p className="-mt-2 mb-4 text-sm text-slate-500">
        Repuestos que faltan en la tienda. Lo ven todos los vendedores y el proveedor; lo que el proveedor envía sale de la lista.
        Se puede corregir o quitar mientras el proveedor no cierre el pedido.
      </p>

      {gestiona && (
        <form onSubmit={(e) => { e.preventDefault(); agregar(nuevo) }} className="mb-4 grid gap-2 rounded-xl border bg-white p-4 sm:grid-cols-12">
          <Campo label="Código" error={errores.codigo} className="sm:col-span-3">
            <Input required className="font-mono uppercase" value={nuevo.codigo} onChange={(e) => setNuevo({ ...nuevo, codigo: e.target.value })} />
          </Campo>
          <Campo label="Descripción" error={errores.descripcion} className="sm:col-span-5">
            <Input required value={nuevo.descripcion} onChange={(e) => setNuevo({ ...nuevo, descripcion: e.target.value })} />
          </Campo>
          <Campo label="Cantidad" error={errores.cantidad} className="sm:col-span-2">
            <Input required type="number" min="1" value={nuevo.cantidad} onChange={(e) => setNuevo({ ...nuevo, cantidad: e.target.value })} />
          </Campo>
          <div className="flex items-end sm:col-span-2">
            <Boton icono={PlusIcon} className="w-full">Agregar</Boton>
          </div>
        </form>
      )}

      <div className="mb-3 flex flex-wrap gap-1 border-b">
        {PESTANAS.map(([k, t]) => (
          <button key={k} onClick={() => { setFiltros({ ...filtros, estado: k }); setMarcados(new Set()) }}
            className={`-mb-px border-b-2 px-3 py-2 text-sm ${filtros.estado === k ? 'border-blue-600 font-medium text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>
            {t}
          </button>
        ))}
      </div>
      {!verPedidos && <Input className="mb-3 sm:max-w-sm" placeholder="Buscar código o descripción" value={filtros.q} onChange={(e) => setFiltros({ ...filtros, q: e.target.value })} />}

      <Alerta tipo={aviso.tipo}>{aviso.texto}</Alerta>
      <Alerta>{error}</Alerta>
      {whatsapp && <ResultadoWhatsapp r={whatsapp} onCerrar={() => setWhatsapp(null)} />}

      {atiende && marcados.size > 0 && (
        <div className="sticky top-0 z-10 my-2 flex flex-wrap items-center gap-2 rounded-lg bg-slate-800 p-2 text-sm text-white">
          <span className="px-2">{marcados.size} seleccionado(s)</span>
          <Boton onClick={() => cambiar('enviado', [...marcados])}>Marcar enviado</Boton>
          <Boton variante="secundario" onClick={() => setDialogo({ estado: 'no_disponible', ids: [...marcados] })}>No disponible…</Boton>
          <Boton variante="peligro" onClick={() => setDialogo({ estado: 'descontinuado', ids: [...marcados] })}>Descontinuado…</Boton>
        </div>
      )}

      {verPedidos ? <TablaPedidos pedidos={pedidos} onReporte={setReporte} /> : <div className="mt-2">
        <DataTable columnas={columnas} datos={filas} pagina={pagina} onPagina={setPage} cargando={cargando}
          vacio={atendidos ? 'Todavía no hay repuestos enviados.' : 'No falta nada en el inventario.'}
          claseFila={(f) => (f.revisar_ingreso ? 'bg-red-50' : '')} />
      </div>}

      {reporte && (
        <VistaPreviaDocumento titulo={`Reporte de inventario · Pedido #${reporte}`} ruta={`/inventario/pedidos/${reporte}/reporte`}
          nombrePdf={`inventario-pedido-${reporte}.pdf`} onCerrar={() => setReporte(null)} />
      )}

      {duplicado && (
        <Modal abierto titulo="Este código ya está agregado" onCerrar={() => setDuplicado(null)}>
          <div className="space-y-3 text-sm">
            <div className="flex gap-3 rounded-lg bg-amber-50 p-3 text-amber-900">
              <ExclamationTriangleIcon className="h-6 w-6 shrink-0" />
              <div>
                <p><span className="font-mono font-semibold">{duplicado.existente.codigo}</span> · {duplicado.existente.descripcion}</p>
                <p>Ya hay <b>{duplicado.existente.cantidad}</b> en el pedido de inventario (agregado por {duplicado.existente.agregado_por}).</p>
                {duplicado.existente.estado === 'no_disponible' && <p>El proveedor lo tiene sin existencia{duplicado.existente.fecha_ingreso && `, ingreso aprox. ${fecha(duplicado.existente.fecha_ingreso)}`}.</p>}
              </div>
            </div>
            <p className="text-slate-700">
              ¿Desea agregar <b>{duplicado.datos.cantidad}</b> más?
              {duplicado.existente.en_pedido_abierto
                ? ` Quedarían ${duplicado.existente.cantidad + Number(duplicado.datos.cantidad)} en total.`
                : ' Como el proveedor ya tomó ese pedido, se agregará como una línea nueva en el siguiente.'}
            </p>
            <div className="flex justify-end gap-2">
              <Boton variante="secundario" onClick={() => setDuplicado(null)}>No</Boton>
              <Boton icono={PlusIcon} onClick={() => agregar({ ...duplicado.datos, sumar: true })}>Sí, agregar {duplicado.datos.cantidad} más</Boton>
            </div>
          </div>
        </Modal>
      )}

      {editar && <EditarLinea fila={editar} onCerrar={() => setEditar(null)} onGuardado={() => { setEditar(null); recargar() }} />}
      {dialogo && <DialogoEstado dialogo={dialogo} onCerrar={() => setDialogo(null)} onEnviar={cambiar}
        avisoDescontinuado="El repuesto saldrá de la lista de inventario y se le avisará a quien lo agregó." />}
    </div>
  )
}

/** Pedidos al proveedor que llevaron inventario, cada uno con su reporte de movimientos. */
function TablaPedidos({ pedidos, onReporte }) {
  const columnas = useMemo(() => [
    { header: 'Pedido', accessorKey: 'id', cell: (c) => <span className="font-semibold">#{c.getValue()}</span> },
    { header: 'Abierto', accessorKey: 'abierto_en', cell: (c) => fechaHora(c.getValue()) },
    {
      header: 'Cerrado', id: 'cerrado',
      cell: ({ row: { original: p } }) => p.abierto
        ? <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Abierto</span>
        : <span>{fechaHora(p.cerrado_en)}{p.cerrado_por && <span className="text-slate-500"> · {p.cerrado_por}</span>}</span>,
    },
    { header: 'Repuestos', accessorKey: 'repuestos' },
    { header: 'Unidades', accessorKey: 'unidades' },
    { header: 'Enviados', accessorKey: 'enviados' },
    { header: 'Pendientes', accessorKey: 'pendientes' },
    {
      id: 'reporte', header: '',
      cell: ({ row: { original: p } }) => (
        <Boton variante="secundario" icono={DocumentTextIcon} onClick={() => onReporte(p.id)}>Reporte</Boton>
      ),
    },
  ], [onReporte])

  return <div className="mt-2"><DataTable columnas={columnas} datos={pedidos} vacio="Todavía no hay pedidos con inventario." /></div>
}

function EditarLinea({ fila, onCerrar, onGuardado }) {
  const [d, setD] = useState({ descripcion: fila.descripcion, cantidad: fila.cantidad })
  const [errores, setErrores] = useState({})
  const [msg, setMsg] = useState('')

  const guardar = async (e) => {
    e.preventDefault()
    try {
      await api(`/inventario/${fila.id}`, { method: 'PUT', body: d })
      onGuardado()
    } catch (err) {
      setErrores(primerError(err.errors))
      setMsg(err.message)
    }
  }

  return (
    <Modal abierto titulo={`Editar ${fila.codigo}`} onCerrar={onCerrar}>
      <form onSubmit={guardar} className="space-y-3">
        {!errores.descripcion && !errores.cantidad && <Alerta>{msg}</Alerta>}
        <Campo label="Descripción" error={errores.descripcion}><Input required value={d.descripcion} onChange={(e) => setD({ ...d, descripcion: e.target.value })} /></Campo>
        <Campo label="Cantidad" error={errores.cantidad}><Input required type="number" min="1" value={d.cantidad} onChange={(e) => setD({ ...d, cantidad: e.target.value })} /></Campo>
        <div className="flex justify-end gap-2">
          <Boton type="button" variante="secundario" onClick={onCerrar}>Cancelar</Boton>
          <Boton>Guardar</Boton>
        </div>
      </form>
    </Modal>
  )
}
