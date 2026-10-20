const quetzales = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' })

export const moneda = (v) => (v === null || v === undefined || v === '' ? '—' : quetzales.format(Number(v)))

export const fecha = (iso) => (iso ? new Date(iso.length === 10 ? `${iso}T12:00:00` : iso).toLocaleDateString('es-GT') : '—')

export const fechaHora = (iso) => (iso ? new Date(iso).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' }) : '—')
