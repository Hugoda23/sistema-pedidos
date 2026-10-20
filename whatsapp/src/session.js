// whatsapp-web.js es CommonJS: sus clases solo salen por el export por defecto.
import { rmSync } from 'node:fs'
import { join } from 'node:path'
import whatsappWeb from 'whatsapp-web.js'
import QRCode from 'qrcode'
import { normalizePhone, toChatId } from './phone.js'

const { Client, LocalAuth, MessageMedia } = whatsappWeb

export class NotReadyError extends Error {}
export class RecipientError extends Error {}

/**
 * Una sesión = el WhatsApp de un usuario, abierto en un Chromium.
 * Estados: iniciando → qr (esperando escaneo) → conectado; o desconectado.
 */
export function createSession({ id, dataPath, chromiumPath, qrTimeoutMs, log, onStop }) {
  const clientId = `u${id}`
  let status = 'iniciando'
  let qrDataUrl = null
  let numero = null
  let error = null
  let qrTimer = null

  const client = new Client({
    authStrategy: new LocalAuth({ clientId, dataPath }),
    puppeteer: {
      executablePath: chromiumPath,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--no-zygote'],
    },
  })

  const stopQrTimer = () => { clearTimeout(qrTimer); qrTimer = null }

  client.on('qr', async (qr) => {
    status = 'qr'
    numero = null
    qrDataUrl = await QRCode.toDataURL(qr)
    // Solo el primer QR arranca el reloj; WhatsApp los renueva cada ~20 s.
    if (!qrTimer) {
      qrTimer = setTimeout(async () => {
        log('info', 'QR sin escanear, se cierra la sesión', { id })
        error = 'No se escaneó el QR a tiempo. Vuelve a intentarlo.'
        await stop()
      }, qrTimeoutMs)
    }
  })

  client.on('ready', () => {
    stopQrTimer()
    status = 'conectado'
    qrDataUrl = null
    error = null
    numero = client.info?.wid?.user ?? null
    log('info', 'sesión lista', { id, numero })
  })

  client.on('auth_failure', (msg) => {
    status = 'desconectado'
    error = `Falló la autenticación: ${msg}`
    log('error', 'auth_failure', { id, msg })
  })

  client.on('disconnected', async (reason) => {
    stopQrTimer()
    status = 'desconectado'
    qrDataUrl = null
    numero = null
    error = reason === 'LOGOUT' ? 'Se cerró la sesión desde el teléfono.' : `Se perdió la conexión (${reason}).`
    log('warn', 'desconectado', { id, reason })
    await stop()
  })

  // Si el contenedor se cayó de golpe, Chromium deja candados que impiden volver a abrir el perfil.
  function limpiarCandados() {
    for (const nombre of ['SingletonLock', 'SingletonCookie', 'SingletonSocket']) {
      rmSync(join(dataPath, `session-${clientId}`, nombre), { force: true })
    }
  }

  async function stop() {
    stopQrTimer()
    if (status !== 'desconectado') status = 'desconectado'
    qrDataUrl = null
    await client.destroy().catch(() => {})
    onStop?.(id)
  }

  return {
    start() {
      limpiarCandados()
      client.initialize().catch((e) => {
        status = 'desconectado'
        error = e.message
        log('error', 'no se pudo iniciar', { id, error: e.message })
        onStop?.(id)
      })
    },

    state: () => ({ status, qr: qrDataUrl, numero, error }),

    /** media opcional: { data (base64), mimetype, filename } → se manda el archivo con el texto como pie. */
    async send(to, body, countryCode, media = null) {
      let digits
      try {
        digits = normalizePhone(to, countryCode)
      } catch (e) {
        throw new RecipientError(e.message)
      }
      if (status !== 'conectado') throw new NotReadyError('Tu WhatsApp no está vinculado.')
      if (digits === numero) throw new RecipientError('No se envían mensajes a tu propio número.')

      const registrado = await client.getNumberId(digits)
      if (!registrado) throw new RecipientError(`El número +${digits} no tiene WhatsApp.`)

      const enviado = media
        ? await client.sendMessage(toChatId(digits), new MessageMedia(media.mimetype, media.data, media.filename), { caption: body })
        : await client.sendMessage(toChatId(digits), body)
      return { id: enviado?.id?._serialized ?? null, para: digits }
    },

    async logout() {
      // Borra la vinculación: para volver a usarlo hay que escanear otra vez.
      await client.logout().catch(() => {})
      error = null
      await stop()
      rmSync(join(dataPath, `session-${clientId}`), { recursive: true, force: true })
    },

    stop,
  }
}
