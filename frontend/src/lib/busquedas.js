import { api } from './api'

export const buscarClientes = (q) => api('/clientes', { params: { q, por_pagina: 8 } }).then((r) => r.data)
