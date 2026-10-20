import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './lib/auth'
import Layout from './components/Layout'
import Login from './pages/Login'
import Inicio from './pages/Inicio'
import Usuarios from './pages/Usuarios'
import Clientes from './pages/Clientes'
import Pedidos from './pages/Pedidos'
import NuevoPedido from './pages/NuevoPedido'
import PedidoDetalle from './pages/PedidoDetalle'
import Proveedor from './pages/Proveedor'
import Perfil from './pages/Perfil'
import Inventario from './pages/Inventario'
import MiWhatsapp from './pages/MiWhatsapp'
import Roles from './pages/Roles'

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
        <Route path="pedidos" element={<Protegida permiso="pedidos.ver"><Pedidos /></Protegida>} />
        <Route path="pedidos/nuevo" element={<Protegida permiso="pedidos.gestionar"><NuevoPedido /></Protegida>} />
        <Route path="pedidos/:id" element={<Protegida permiso="pedidos.ver"><PedidoDetalle /></Protegida>} />
        <Route path="inventario" element={<Protegida permiso="inventario.ver"><Inventario /></Protegida>} />
        <Route path="clientes" element={<Protegida permiso="clientes.ver"><Clientes /></Protegida>} />
        <Route path="proveedor" element={<Protegida permiso="proveedor.ver"><Proveedor /></Protegida>} />
        <Route path="usuarios" element={<Protegida permiso="usuarios.ver"><Usuarios /></Protegida>} />
        <Route path="perfil" element={<Perfil />} />
        <Route path="whatsapp" element={<MiWhatsapp />} />
        <Route path="roles" element={<Protegida permiso="roles.ver"><Roles /></Protegida>} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
