import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { primerError } from '../lib/errores'
import { moneda } from '../lib/formato'
import Autocompletar from '../components/Autocompletar'
import { buscarClientes, buscarProductos } from '../lib/busquedas'
import FormModal from '../components/FormModal'
import { CamposCliente } from './Clientes'
import { Alerta, Boton, Campo, Encabezado, Input, Textarea } from '../components/ui'
import { PlusIcon, TrashIcon, UserPlusIcon } from '@heroicons/react/24/outline'

// randomUUID no existe fuera de HTTPS; un contador basta como clave.
let sig = 0
const lineaVacia = () => ({ clave: ++sig, codigo: '', descripcion: '', cantidad: 1, precio: '' })

export default function NuevoPedido() {
  const navegar = useNavigate()
  const [cliente, setCliente] = useState(null)
  const [textoCliente, setTextoCliente] = useState('')
  const [nuevoCliente, setNuevoCliente] = useState(null)
  const [lineas, setLineas] = useState([lineaVacia()])
  const [anticipo, setAnticipo] = useState('')
  const [notas, setNotas] = useState('')
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState('')
  const [guardando, setGuardando] = useState(false)

  const cambiar = (i, campo, valor) => setLineas((ls) => ls.map((l, j) => (j === i ? { ...l, [campo]: valor } : l)))
  const elegirProducto = (i, p) => setLineas((ls) => ls.map((l, j) => (j === i
    ? { ...l, codigo: p.codigo, descripcion: p.nombre, precio: l.precio || p.precio_venta || '' } : l)))

  const total = lineas.reduce((s, l) => s + (Number(l.cantidad) || 0) * (Number(l.precio) || 0), 0)

  const guardar = async () => {
    setErrores({})
    setMensaje('')
    if (!cliente) { setErrores({ cliente_id: 'Elige o registra un cliente.' }); return }
    const items = lineas.filter((l) => l.codigo.trim())
    setGuardando(true)
    try {
      const p = await api('/pedidos', {
        method: 'POST',
        body: { cliente_id: cliente.id, notas, anticipo: anticipo || 0, items: items.map((l) => ({ codigo: l.codigo, descripcion: l.descripcion, cantidad: l.cantidad, precio: l.precio })) },
      })
      navegar(`/pedidos/${p.id}`)
    } catch (err) {
      setErrores(primerError(err.errors))
      setMensaje(err.message)
    } finally {
      setGuardando(false)
    }
  }

  return (
    <div className="max-w-5xl">
      <Encabezado titulo="Nuevo pedido" />
      <Alerta>{mensaje}</Alerta>

      <section className="mt-3 rounded-xl border bg-white p-4">
        <h2 className="mb-2 font-semibold text-slate-700">Cliente</h2>
        {cliente ? (
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm">
            <div>
              <div className="font-medium text-slate-800">{cliente.nombre}</div>
              <div className="text-slate-500">{[cliente.telefono, cliente.moto].filter(Boolean).join(' · ') || 'Sin datos de contacto'}</div>
            </div>
            <button className="text-blue-600 hover:underline" onClick={() => { setCliente(null); setTextoCliente('') }}>Cambiar</button>
          </div>
        ) : (
          <Campo label="Busca por nombre, teléfono o moto" error={errores.cliente_id}>
            <Autocompletar value={textoCliente} onChange={setTextoCliente} onElegir={setCliente} buscar={buscarClientes} autoFocus
              placeholder="Escribe para buscar…"
              render={(c) => <><span className="font-medium">{c.nombre}</span> <span className="text-slate-500">{c.telefono} {c.moto && `· ${c.moto}`}</span></>}
              extra={(cerrar) => (
                <button className="inline-flex items-center gap-1 text-blue-600" onMouseDown={(e) => { e.preventDefault(); cerrar(); setNuevoCliente({ nombre: textoCliente, telefono: '', moto: '', notas: '' }) }}>
                  <UserPlusIcon className="h-4 w-4" />Registrar cliente nuevo{textoCliente && ` «${textoCliente}»`}
                </button>
              )} />
          </Campo>
        )}
      </section>

      <section className="mt-4 rounded-xl border bg-white p-4">
        <h2 className="mb-2 font-semibold text-slate-700">Productos</h2>
        <div className="space-y-3">
          {lineas.map((l, i) => (
            <div key={l.clave} className="grid gap-2 rounded-lg border p-3 sm:grid-cols-12 sm:border-0 sm:p-0">
              <div className="sm:col-span-3">
                <Autocompletar value={l.codigo} onChange={(v) => cambiar(i, 'codigo', v)} onElegir={(p) => elegirProducto(i, p)}
                  buscar={buscarProductos} placeholder="Código" className="font-mono uppercase"
                  render={(p) => <><span className="font-mono font-medium">{p.codigo}</span> <span className="text-slate-500">{p.nombre}</span>{p.descontinuado && <span className="ml-1 text-red-600">(descontinuado)</span>}</>} />
                {errores[`items.${i}.codigo`] && <p className="mt-1 text-xs text-red-600">{errores[`items.${i}.codigo`]}</p>}
              </div>
              <div className="sm:col-span-4">
                <Input placeholder="Descripción" value={l.descripcion} onChange={(e) => cambiar(i, 'descripcion', e.target.value)} />
                {errores[`items.${i}.descripcion`] && <p className="mt-1 text-xs text-red-600">{errores[`items.${i}.descripcion`]}</p>}
              </div>
              <Input className="sm:col-span-1" type="number" min="1" placeholder="Cant." value={l.cantidad} onChange={(e) => cambiar(i, 'cantidad', e.target.value)} aria-label="Cantidad" />
              <Input className="sm:col-span-2" type="number" min="0" step="0.01" placeholder="Precio unit." value={l.precio} onChange={(e) => cambiar(i, 'precio', e.target.value)} aria-label="Precio" />
              <div className="flex items-center justify-between gap-2 sm:col-span-2">
                <span className="text-sm text-slate-600">{moneda((Number(l.cantidad) || 0) * (Number(l.precio) || 0))}</span>
                {lineas.length > 1 && (
                  <button className="rounded p-1 text-red-500 hover:bg-red-50 hover:text-red-700" title="Quitar línea" aria-label="Quitar línea" onClick={() => setLineas(lineas.filter((_, j) => j !== i))}><TrashIcon className="h-5 w-5" /></button>
                )}
              </div>
            </div>
          ))}
        </div>
        {errores.items && <p className="mt-2 text-sm text-red-600">{errores.items}</p>}
        <button className="mt-3 inline-flex items-center gap-1 text-sm text-blue-600 hover:underline" onClick={() => setLineas([...lineas, lineaVacia()])}><PlusIcon className="h-4 w-4" />Agregar otro producto</button>
      </section>

      <section className="mt-4 grid gap-4 rounded-xl border bg-white p-4 sm:grid-cols-2">
        <Campo label="Notas del pedido"><Textarea value={notas} onChange={(e) => setNotas(e.target.value)} /></Campo>
        <div className="space-y-2 text-sm">
          <div className="flex justify-between"><span className="text-slate-500">Total</span><span className="text-lg font-semibold">{moneda(total)}</span></div>
          <Campo label="Anticipo del cliente" error={errores.anticipo}>
            <Input type="number" min="0" step="0.01" value={anticipo} onChange={(e) => setAnticipo(e.target.value)} placeholder="0.00" />
          </Campo>
          <div className="flex justify-between"><span className="text-slate-500">Saldo</span><span className="font-medium text-red-600">{moneda(Math.max(0, total - (Number(anticipo) || 0)))}</span></div>
        </div>
      </section>

      <div className="mt-4 flex justify-end gap-2">
        <Boton variante="secundario" onClick={() => navegar('/pedidos')}>Cancelar</Boton>
        <Boton onClick={guardar} disabled={guardando}>{guardando ? 'Guardando…' : 'Guardar pedido'}</Boton>
      </div>

      {nuevoCliente && (
        <FormModal titulo="Nuevo cliente" ruta="/clientes" inicial={nuevoCliente} campos={CamposCliente}
          onCerrar={() => setNuevoCliente(null)} onGuardado={(c) => { setCliente(c); setNuevoCliente(null) }} />
      )}
    </div>
  )
}
