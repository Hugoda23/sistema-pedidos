import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { api, token } from './api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [cargando, setCargando] = useState(!!token.get())

  useEffect(() => {
    if (!token.get()) return
    api('/me')
      .then((d) => setUser(d.user))
      .catch(() => token.clear())
      .finally(() => setCargando(false))
  }, [])

  useEffect(() => {
    const salir = () => setUser(null)
    window.addEventListener('sesion-expirada', salir)
    return () => window.removeEventListener('sesion-expirada', salir)
  }, [])

  const login = async (email, password) => {
    const d = await api('/login', { method: 'POST', body: { email, password } })
    token.set(d.token)
    setUser(d.user)
  }

  const logout = async () => {
    try { await api('/logout', { method: 'POST' }) } catch { /* el token ya no sirve */ }
    token.clear()
    setUser(null)
  }

  const puede = useCallback((permiso) => !!user?.permisos.includes(permiso), [user])

  return (
    <AuthContext.Provider value={{ user, cargando, login, logout, puede, actualizarUsuario: setUser }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => useContext(AuthContext)
