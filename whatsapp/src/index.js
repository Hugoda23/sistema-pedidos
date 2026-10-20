import { config } from './config.js'
import { createManager } from './manager.js'
import { createServer } from './server.js'

const log = (nivel, msg, datos = {}) => console.log(JSON.stringify({ t: new Date().toISOString(), nivel, msg, ...datos }))

const manager = createManager({ config, log })
const server = createServer({ manager, token: config.token, log }).listen(config.port, () => log('info', `escuchando en :${config.port}`))

manager.restaurar()

const salir = async () => {
  log('info', 'apagando')
  server.close()
  await manager.apagar()
  process.exit(0)
}
process.on('SIGTERM', salir)
process.on('SIGINT', salir)
