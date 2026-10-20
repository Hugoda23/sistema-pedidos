import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../lib/auth'

// Cada opción aparece solo si el usuario tiene el permiso.
const MENU = [
  { to: '/', label: 'Inicio', permiso: null },
  { to: '/pedidos', label: 'Pedidos', permiso: 'pedidos.ver' },
  { to: '/clientes', label: 'Clientes', permiso: 'clientes.ver' },
  { to: '/productos', label: 'Productos', permiso: 'productos.ver' },
  { to: '/proveedor', label: 'Repuestos solicitados', permiso: 'proveedor.ver' },
  { to: '/usuarios', label: 'Usuarios', permiso: 'usuarios.ver' },
  { to: '/roles', label: 'Roles y permisos', permiso: 'roles.ver' },
]

export default function Layout() {
  const { user, logout, puede } = useAuth()
  const [abierto, setAbierto] = useState(false)

  const enlaces = MENU.filter((m) => !m.permiso || puede(m.permiso))

  return (
    <div className="flex min-h-screen">
      <aside className={`fixed inset-y-0 left-0 z-30 w-60 transform bg-slate-900 text-slate-200 transition md:static md:translate-x-0 ${abierto ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="px-5 py-4 text-lg font-bold text-white">Pedidos</div>
        <nav className="space-y-1 px-2">
          {enlaces.map((m) => (
            <NavLink key={m.to} to={m.to} end={m.to === '/'} onClick={() => setAbierto(false)}
              className={({ isActive }) => `block rounded-lg px-3 py-2 text-sm ${isActive ? 'bg-blue-600 text-white' : 'hover:bg-slate-800'}`}>
              {m.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      {abierto && <div className="fixed inset-0 z-20 bg-black/40 md:hidden" onClick={() => setAbierto(false)} />}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between border-b bg-white px-4 py-3">
          <button className="rounded p-1 md:hidden" onClick={() => setAbierto(true)} aria-label="Menú">☰</button>
          <div className="ml-auto flex items-center gap-3 text-sm">
            <span className="text-slate-600">{user.name} · <span className="text-slate-400">{user.roles.join(', ')}</span></span>
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
