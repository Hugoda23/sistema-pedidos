import express from 'express'
import { NotReadyError, RecipientError } from './session.js'

/**
 * API interna para Laravel (no se publica fuera de la red de Docker).
 *   GET  /health
 *   GET  /sessions                 estado de todas las abiertas
 *   GET  /sessions/:id             estado + QR de un usuario
 *   POST /sessions/:id/start       abrir (muestra QR si no estaba vinculado)
 *   POST /sessions/:id/logout      desvincular
 *   POST /sessions/:id/send        { to, body, media? } → enviar desde el WhatsApp de ese usuario
 */
export function createServer({ manager, token, log }) {
  const app = express()
  app.use(express.json({ limit: '12mb' })) // el PDF del comprobante viaja en base64

  app.get('/health', (_req, res) => res.json({ ok: true, sesiones: Object.keys(manager.todas()).length }))

  app.use((req, res, next) => {
    if (req.get('authorization') !== `Bearer ${token}`) return res.status(401).json({ message: 'Token inválido.' })
    next()
  })

  const id = (req) => String(Number(req.params.id))

  app.get('/sessions', (_req, res) => res.json(manager.todas()))
  app.get('/sessions/:id', (req, res) => res.json(manager.estado(id(req))))

  app.post('/sessions/:id/start', (req, res) => {
    manager.abrir(id(req))
    res.json(manager.estado(id(req)))
  })

  app.post('/sessions/:id/logout', async (req, res) => {
    await manager.cerrar(id(req))
    res.json(manager.estado(id(req)))
  })

  app.post('/sessions/:id/send', async (req, res) => {
    const { to, body, media = null } = req.body ?? {}
    if (typeof to !== 'string' || !to.trim() || typeof body !== 'string' || !body.trim()) {
      return res.status(422).json({ message: 'Faltan el destinatario o el mensaje.', permanente: true })
    }
    if (media && (typeof media.data !== 'string' || typeof media.mimetype !== 'string')) {
      return res.status(422).json({ message: 'El archivo adjunto viene incompleto.', permanente: true })
    }
    try {
      const r = await manager.enviar(id(req), to, body, media)
      log('info', 'enviado', { de: id(req), para: r.para })
      res.json(r)
    } catch (e) {
      if (e instanceof NotReadyError) return res.status(409).json({ message: e.message, permanente: false })
      if (e instanceof RecipientError) return res.status(422).json({ message: e.message, permanente: true })
      log('error', 'falló el envío', { de: id(req), error: e.message })
      res.status(500).json({ message: e.message, permanente: false })
    }
  })

  return app
}
