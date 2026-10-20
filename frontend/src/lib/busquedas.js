import { api } from './api'

export const buscarProductos = (q) => api('/productos/buscar', { params: { q } })
export const buscarClientes = (q) => api('/clientes', { params: { q, por_pagina: 8 } }).then((r) => r.data)
