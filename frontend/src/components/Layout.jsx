import { useState } from 'react'
import { Link, NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import Campanita from './Campanita'
import {
  Bars3Icon, ChatBubbleLeftRightIcon, ClipboardDocumentListIcon, HomeIcon,
  ShieldCheckIcon, TruckIcon, UserGroupIcon, UsersIcon,
} from '@heroicons/react/24/outline'

// Cada opción aparece solo si el usuario tiene el permiso.
const MENU = [
  { to: '/', label: 'Inicio', permiso: null, icono: HomeIcon },
  { to: '/pedidos', label: 'Pedidos', permiso: 'pedidos.ver', icono: ClipboardDocumentListIcon },
  { to: '/clientes', label: 'Clientes', permiso: 'clientes.ver', icono: UserGroupIcon },
  { to: '/proveedor', label: 'Repuestos solicitados', permiso: 'proveedor.ver', icono: TruckIcon },
  { to: '/usuarios', label: 'Usuarios', permiso: 'usuarios.ver', icono: UsersIcon },
  { to: '/roles', label: 'Roles y permisos', permiso: 'roles.ver', icono: ShieldCheckIcon },
  { to: '/whatsapp', label: 'Mi WhatsApp', permiso: null, icono: ChatBubbleLeftRightIcon },
]

export default function Layout() {
  const { user, logout, puede } = useAuth()
  const [abierto, setAbierto] = useState(false)

  const enlaces = MENU.filter((m) => !m.permiso || puede(m.permiso))

  return (
    <div className="flex min-h-screen">
      <aside className={`fixed inset-y-0 left-0 z-30 w-60 transform bg-slate-900 text-slate-200 transition md:static md:translate-x-0 ${abierto ? 'translate-x-0' : '-translate-x-full'}`}>
        <Link to="/" onClick={() => setAbierto(false)} className="m-3 flex items-center gap-3 rounded-xl bg-white p-2">
          <img src="/logo.jpg" alt="Motorepuestos Mota" className="h-12 w-12 shrink-0 object-contain" />
          <span className="text-sm font-bold leading-tight text-slate-800">Motorepuestos<br />Mota</span>
        </Link>
        <nav className="space-y-1 px-2">
          {enlaces.map((m) => (
            <NavLink key={m.to} to={m.to} end={m.to === '/'} onClick={() => setAbierto(false)}
              className={({ isActive }) => `flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${isActive ? 'bg-blue-600 text-white' : 'hover:bg-slate-800'}`}>
              <m.icono className="h-5 w-5 shrink-0" aria-hidden="true" />
              {m.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      {abierto && <div className="fixed inset-0 z-20 bg-black/40 md:hidden" onClick={() => setAbierto(false)} />}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between border-b bg-white px-4 py-3">
          <button className="rounded p-1 md:hidden" onClick={() => setAbierto(true)} aria-label="Menú"><Bars3Icon className="h-6 w-6" /></button>
          <div className="ml-auto flex items-center gap-3 text-sm">
            <Campanita />
            <Link to="/perfil" className="text-slate-600 hover:text-blue-600" title="Mi perfil">
              <span className="hidden sm:inline">{user.name} · <span className="text-slate-400">{user.roles.join(', ')}</span></span>
              <span className="sm:hidden">Perfil</span>
            </Link>
            <button onClick={logout} className="rounded-lg border px-3 py-1 hover:bg-slate-50">Salir</button>
          </div>
        </header>
        <main className="flex-1 p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
