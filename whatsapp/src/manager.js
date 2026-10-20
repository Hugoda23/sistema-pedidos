import { readdirSync } from 'node:fs'
import { NotReadyError, createSession } from './session.js'

/** Lleva las sesiones abiertas, una por usuario del sistema. */
export function createManager({ config, log }) {
  const sesiones = new Map()
  // Lo último que se supo de las sesiones que ya se cerraron (p. ej. el error).
  const ultimoEstado = new Map()

  function abrir(id) {
    if (sesiones.has(id)) return sesiones.get(id)
    const s = createSession({
      id,
      dataPath: config.dataPath,
      chromiumPath: config.chromiumPath,
      qrTimeoutMs: config.qrTimeoutMs,
      log,
      onStop: (sid) => {
        const actual = sesiones.get(sid)
        if (actual) ultimoEstado.set(sid, { ...actual.state(), status: 'desconectado', qr: null })
        sesiones.delete(sid)
      },
    })
    sesiones.set(id, s)
    ultimoEstado.delete(id)
    s.start()
    return s
  }

  return {
    abrir,

    estado(id) {
      return sesiones.get(id)?.state() ?? ultimoEstado.get(id) ?? { status: 'desconectado', qr: null, numero: null, error: null }
    },

    todas() {
      return Object.fromEntries([...sesiones].map(([id, s]) => [id, s.state()]))
    },

    async cerrar(id) {
      const s = sesiones.get(id)
      if (s) await s.logout()
      ultimoEstado.delete(id)
    },

    async enviar(id, to, body, media) {
      const s = sesiones.get(id)
      if (!s) throw new NotReadyError('Tu WhatsApp no está vinculado.')
      return s.send(to, body, config.countryCode, media)
    },

    /** Al arrancar, reabre las sesiones que ya estaban vinculadas, de a una para no saturar. */
    async restaurar() {
      let carpetas = []
      try {
        carpetas = readdirSync(config.dataPath).filter((d) => /^session-u\d+$/.test(d))
      } catch { /* aún no hay ninguna */ }

      for (const c of carpetas) {
        const id = c.replace('session-u', '')
        log('info', 'restaurando sesión', { id })
        abrir(id)
        await new Promise((r) => setTimeout(r, 4000))
      }
    },

    async apagar() {
      await Promise.all([...sesiones.values()].map((s) => s.stop()))
    },
  }
}
