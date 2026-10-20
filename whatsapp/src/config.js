export const config = {
  port: Number(process.env.WHATSAPP_PORT ?? 3000),
  token: process.env.WHATSAPP_TOKEN ?? '',
  countryCode: process.env.WHATSAPP_COUNTRY_CODE ?? '502',
  dataPath: process.env.WHATSAPP_DATA_PATH ?? '/data',
  chromiumPath: process.env.CHROMIUM_PATH ?? '/usr/bin/chromium',
  // Un QR que nadie escanea tiene un Chromium abierto por nada: se apaga a los 3 minutos.
  qrTimeoutMs: Number(process.env.WHATSAPP_QR_TIMEOUT_MS ?? 180_000),
}

if (!config.token) {
  console.error('Falta WHATSAPP_TOKEN: sin él cualquiera en la red podría mandar mensajes.')
  process.exit(1)
}
