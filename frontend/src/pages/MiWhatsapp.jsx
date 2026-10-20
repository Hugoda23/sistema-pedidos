import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { fechaHora } from '../lib/formato'
import { useListado } from '../lib/useListado'
import DataTable from '../components/DataTable'
import { Alerta, Badge, Boton, Encabezado, Select } from '../components/ui'

const ESTADO_SESION = {
  conectado: ['Vinculado', 'verde'],
  qr: ['Esperando escaneo', 'amarillo'],
  iniciando: ['Iniciando…', 'azul'],
  desconectado: ['No vinculado', 'gris'],
  sin_servicio: ['Servicio caído', 'rojo'],
}
const ESTADO_MSJ = { enviado: ['Enviado', 'verde'], pendiente: ['En cola', 'azul'], fallido: ['Falló', 'rojo'] }

export default function MiWhatsapp() {
  const { user, puede } = useAuth()
  const [sesion, setSesion] = useState(null)
  const [error, setError] = useState('')
  const [ocupado, setOcupado] = useState(false)
  const temporizador = useRef(null)

  const consultar = useCallback(() => api('/whatsapp').then(setSesion).catch((e) => setError(e.message)), [])

  // Mientras se espera el QR o el escaneo, se pregunta cada 2 s.
  useEffect(() => {
    consultar()
    return () => clearInterval(temporizador.current)
  }, [consultar])
  useEffect(() => {
    clearInterval(temporizador.current)
    if (sesion && ['iniciando', 'qr'].includes(sesion.status)) {
      temporizador.current = setInterval(consultar, 2000)
    }
  }, [sesion, consultar])

  const accion = async (ruta) => {
    setOcupado(true)
    setError('')
    try {
      setSesion(await api(ruta, { method: 'POST' }))
    } catch (e) {
      setError(e.message)
    } finally {
      setOcupado(false)
    }
  }

  const desvincular = () => confirm('¿Desvincular tu WhatsApp? El sistema ya no podrá enviar avisos desde tu número.') && accion('/whatsapp/desconectar')

  const [txt, color] = ESTADO_SESION[sesion?.status] ?? ['…', 'gris']

  return (
    <div className="max-w-5xl space-y-6">
      <Encabezado titulo="Mi WhatsApp" />

      <section className="rounded-xl border bg-white p-5">
        <div className="flex flex-wrap items-center gap-3">
          <h2 className="font-semibold text-slate-700">Estado</h2>
          <Badge color={color}>{txt}</Badge>
          {sesion?.numero && <span className="text-sm text-slate-600">+{sesion.numero}</span>}
        </div>
        <Alerta>{error || (sesion?.status === 'desconectado' && sesion.error) || ''}</Alerta>

        {!user.telefono && (
          <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
            Tu usuario no tiene teléfono registrado, así que no puedes <b>recibir</b> avisos por WhatsApp. Pídele al administrador que lo agregue.
          </p>
        )}

        {sesion?.status === 'conectado' && (
          <div className="mt-4 space-y-3 text-sm text-slate-600">
            <p>Los avisos que generes (por ejemplo, al cambiar el estado de un repuesto) saldrán desde tu número.</p>
            <Boton variante="secundario" onClick={desvincular} disabled={ocupado}>Desvincular</Boton>
          </div>
        )}

        {['desconectado', 'sin_servicio'].includes(sesion?.status) && (
          <div className="mt-4 space-y-3 text-sm text-slate-600">
            <p>Vincula tu WhatsApp para que el sistema envíe los avisos desde tu número.</p>
            <Boton onClick={() => accion('/whatsapp/conectar')} disabled={ocupado || sesion.status === 'sin_servicio'}>Vincular mi WhatsApp</Boton>
          </div>
        )}

        {sesion?.status === 'iniciando' && <p className="mt-4 text-sm text-slate-500">Preparando el código QR… puede tardar unos segundos.</p>}

        {sesion?.status === 'qr' && sesion.qr && (
          <div className="mt-4 grid gap-6 sm:grid-cols-[auto_1fr]">
            <img src={sesion.qr} alt="Código QR de WhatsApp" className="h-64 w-64 rounded-lg border" />
            <ol className="list-decimal space-y-2 pl-5 text-sm text-slate-600">
              <li>Abre WhatsApp en tu teléfono.</li>
              <li>Toca <b>Menú ⋮</b> o <b>Configuración</b> y elige <b>Dispositivos vinculados</b>.</li>
              <li>Toca <b>Vincular un dispositivo</b> y apunta la cámara a este código.</li>
              <li>El código se renueva solo. Si no lo escaneas en 3 minutos, vuelve a empezar.</li>
            </ol>
          </div>
        )}

        <p className="mt-4 text-xs text-slate-400">
          Esta conexión usa WhatsApp Web sin la API oficial. WhatsApp podría limitar el número si se envían muchos mensajes seguidos.
        </p>
      </section>

      <Mensajes esAdmin={puede('usuarios.ver')} />
      {puede('usuarios.ver') && <Sesiones />}
    </div>
  )
}

function Mensajes({ esAdmin }) {
  const [filtros, setFiltros] = useState({ estado: '', todos: '' })
  const { filas, pagina, cargando, setPage, recargar } = useListado('/whatsapp/mensajes', filtros)
  const [error, setError] = useState('')

  const columnas = useMemo(() => {
    const reintentar = async (m) => {
      try { await api(`/whatsapp/mensajes/${m.id}/reintentar`, { method: 'POST' }); recargar() } catch (e) { setError(e.message) }
    }
    return [
      { header: 'Fecha', accessorKey: 'fecha', cell: (c) => fechaHora(c.getValue()) },
      { header: 'De → Para', id: 'dp', cell: ({ row: { original: m } }) => <span>{m.de} → {m.para ?? m.telefono}</span> },
      { header: 'Mensaje', accessorKey: 'mensaje', cell: (c) => <div className="max-w-md truncate whitespace-nowrap" title={c.getValue()}>{c.getValue()}</div> },
      { header: 'Pedido', accessorKey: 'pedido', cell: (c) => c.getValue() ? <Link className="text-blue-600 hover:underline" to={`/pedidos/${c.getValue().id}`}>{c.getValue().numero}</Link> : '—' },
      {
        header: 'Estado', accessorKey: 'estado',
        cell: ({ row: { original: m } }) => {
          const [t, c] = ESTADO_MSJ[m.estado] ?? [m.estado, 'gris']
          return <div><Badge color={c}>{t}</Badge>{m.error && <div className="max-w-56 truncate text-xs text-red-600" title={m.error}>{m.error}</div>}</div>
        },
      },
      { id: 'acc', header: '', cell: ({ row: { original: m } }) => m.puede_reintentar && <button className="text-blue-600 hover:underline" onClick={() => reintentar(m)}>Reintentar</button> },
    ]
  }, [recargar])

  return (
    <section>
      <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold text-slate-700">Mensajes</h2>
        <div className="flex gap-2">
          <Select value={filtros.estado} onChange={(e) => setFiltros({ ...filtros, estado: e.target.value })}>
            <option value="">Todos</option><option value="fallido">Fallidos</option><option value="pendiente">En cola</option><option value="enviado">Enviados</option>
          </Select>
          {esAdmin && (
            <Select value={filtros.todos} onChange={(e) => setFiltros({ ...filtros, todos: e.target.value })}>
              <option value="">Solo míos</option><option value="1">De todos</option>
            </Select>
          )}
        </div>
      </div>
      <Alerta>{error}</Alerta>
      <DataTable columnas={columnas} datos={filas} pagina={pagina} onPagina={setPage} cargando={cargando} vacio="Todavía no hay mensajes." />
    </section>
  )
}

function Sesiones() {
  const [filas, setFilas] = useState([])
  useEffect(() => { api('/whatsapp/sesiones').then(setFilas).catch(() => {}) }, [])

  const columnas = useMemo(() => [
    { header: 'Usuario', accessorKey: 'name' },
    { header: 'Rol', accessorKey: 'rol' },
    { header: 'Teléfono', accessorKey: 'telefono', cell: (c) => c.getValue() ?? <span className="text-amber-700">Sin teléfono</span> },
    { header: 'WhatsApp', accessorKey: 'estado', cell: ({ row: { original: u } }) => { const [t, c] = ESTADO_SESION[u.estado] ?? [u.estado, 'gris']; return <span><Badge color={c}>{t}</Badge> {u.numero && <span className="text-xs text-slate-500">+{u.numero}</span>}</span> } },
  ], [])

  return (
    <section>
      <h2 className="mb-2 text-lg font-semibold text-slate-700">WhatsApp del equipo</h2>
      <DataTable columnas={columnas} datos={filas} />
    </section>
  )
}
