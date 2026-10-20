import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './lib/auth'
import Layout from './components/Layout'
import Login from './pages/Login'
import Inicio from './pages/Inicio'
import Pendiente from './pages/Pendiente'

function Protegida({ permiso, children }) {
  const { user, cargando, puede } = useAuth()
  if (cargando) return <div className="p-8 text-slate-500">Cargando…</div>
  if (!user) return <Navigate to="/login" replace />
  if (permiso && !puede(permiso)) return <Navigate to="/" replace />
  return children
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Protegida><Layout /></Protegida>}>
        <Route index element={<Inicio />} />
        <Route path="pedidos" element={<Protegida permiso="pedidos.ver"><Pendiente titulo="Pedidos" /></Protegida>} />
        <Route path="clientes" element={<Protegida permiso="clientes.ver"><Pendiente titulo="Clientes" /></Protegida>} />
        <Route path="productos" element={<Protegida permiso="productos.ver"><Pendiente titulo="Productos" /></Protegida>} />
        <Route path="proveedor" element={<Protegida permiso="proveedor.ver"><Pendiente titulo="Repuestos solicitados" /></Protegida>} />
        <Route path="usuarios" element={<Protegida permiso="usuarios.ver"><Pendiente titulo="Usuarios" /></Protegida>} />
        <Route path="roles" element={<Protegida permiso="roles.ver"><Pendiente titulo="Roles y permisos" /></Protegida>} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
