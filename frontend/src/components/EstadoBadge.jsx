import { ESTADO_ITEM, ESTADO_PEDIDO } from '../lib/estados'
import { Badge } from './ui'

export function EstadoPedido({ estado }) {
  const [txt, color] = ESTADO_PEDIDO[estado] ?? [estado, 'gris']
  return <Badge color={color}>{txt}</Badge>
}

export function EstadoItem({ estado }) {
  const [txt, color] = ESTADO_ITEM[estado] ?? [estado, 'gris']
  return <Badge color={color}>{txt}</Badge>
}
