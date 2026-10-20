import { useAuth } from '../lib/auth'

export default function Inicio() {
  const { user } = useAuth()
  return (
    <div>
      <h1 className="text-2xl font-bold text-slate-800">Hola, {user.name}</h1>
      <p className="mt-1 text-slate-500">Bienvenido al sistema de pedidos.</p>
    </div>
  )
}
