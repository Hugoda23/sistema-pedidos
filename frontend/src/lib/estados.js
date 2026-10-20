export const ESTADO_PEDIDO = {
  pendiente: ['Pendiente', 'gris'],
  en_proceso: ['En proceso', 'azul'],
  listo: ['Listo para entregar', 'amarillo'],
  entregado: ['Entregado', 'verde'],
  sin_productos: ['Sin repuestos', 'rojo'],
  anulado: ['Anulado', 'rojo'],
}

export const ESTADO_ITEM = {
  pendiente: ['Pendiente', 'gris'],
  enviado: ['Enviado', 'azul'],
  no_disponible: ['No disponible', 'amarillo'],
  descontinuado: ['Descontinuado', 'rojo'],
  recibido: ['Recibido', 'morado'],
  entregado: ['Entregado', 'verde'],
  anulado: ['Anulado', 'rojo'],
}

// Texto del botón para cada cambio que puede hacer el vendedor.
export const ACCION_ITEM = {
  recibido: 'Marcar recibido',
  entregado: 'Marcar entregado',
  anulado: 'Anular',
}
