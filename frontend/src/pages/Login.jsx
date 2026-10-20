import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '../lib/auth'

export default function Login() {
  const { user, login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [enviando, setEnviando] = useState(false)

  if (user) return <Navigate to="/" replace />

  const enviar = async (e) => {
    e.preventDefault()
    setError('')
    setEnviando(true)
    try {
      await login(email, password)
    } catch (err) {
      setError(err.errors?.email?.[0] ?? err.message)
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={enviar} className="w-full max-w-sm space-y-4 rounded-xl bg-white p-8 shadow">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Sistema de Pedidos</h1>
          <p className="text-sm text-slate-500">Ingresa con tu cuenta</p>
        </div>

        {error && <p className="rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}

        <label className="block text-sm">
          <span className="text-slate-700">Correo</span>
          <input type="email" required autoFocus value={email} onChange={(e) => setEmail(e.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none" />
        </label>

        <label className="block text-sm">
          <span className="text-slate-700">Contraseña</span>
          <input type="password" required value={password} onChange={(e) => setPassword(e.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none" />
        </label>

        <button disabled={enviando}
          className="w-full rounded-lg bg-blue-600 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-60">
          {enviando ? 'Entrando…' : 'Entrar'}
        </button>
      </form>
    </div>
  )
}
