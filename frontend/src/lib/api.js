// Envoltorio de fetch: agrega el token y convierte errores en excepciones legibles.
const CLAVE_TOKEN = 'pedidos_token'

export const token = {
  get: () => localStorage.getItem(CLAVE_TOKEN),
  set: (t) => localStorage.setItem(CLAVE_TOKEN, t),
  clear: () => localStorage.removeItem(CLAVE_TOKEN),
}

export class ApiError extends Error {
  constructor(status, data) {
    super(data?.message ?? 'Error de comunicación con el servidor')
    this.status = status
    this.errors = data?.errors ?? {}
    this.data = data
  }
}

export async function api(ruta, { method = 'GET', body, params } = {}) {
  const url = new URL(`/api${ruta}`, window.location.origin)
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v)
    })
  }

  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token.get()) headers.Authorization = `Bearer ${token.get()}`

  const res = await fetch(url, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined })
  const data = res.status === 204 ? null : await res.json().catch(() => null)

  if (res.status === 401 && ruta !== '/login') {
    token.clear()
    window.dispatchEvent(new Event('sesion-expirada'))
  }
  if (!res.ok) throw new ApiError(res.status, data)
  return data
}

/** Descarga un archivo (p. ej. Excel) de la API con el token de la sesión. */
export async function descargar(ruta, params, nombre) {
  const url = new URL(`/api${ruta}`, window.location.origin)
  Object.entries(params ?? {}).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v) })

  const res = await fetch(url, { headers: { Authorization: `Bearer ${token.get()}` } })
  if (!res.ok) throw new ApiError(res.status, await res.json().catch(() => null))

  const enlace = document.createElement('a')
  enlace.href = URL.createObjectURL(await res.blob())
  enlace.download = res.headers.get('content-disposition')?.match(/filename="?([^"]+)"?/)?.[1] ?? nombre
  enlace.click()
  setTimeout(() => URL.revokeObjectURL(enlace.href), 1000)
}

/** GET que devuelve texto (p. ej. el HTML del comprobante para la vista previa). */
export async function apiTexto(ruta) {
  const res = await fetch(`/api${ruta}`, { headers: { Authorization: `Bearer ${token.get()}`, Accept: 'text/html' } })
  if (!res.ok) throw new ApiError(res.status, await res.json().catch(() => null))
  return res.text()
}
