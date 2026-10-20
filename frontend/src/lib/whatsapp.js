// Igual que App\Support\WhatsApp: 8 dígitos = número de Guatemala.
export const enlaceWa = (tel, texto) => {
  const d = (tel ?? '').replace(/\D/g, '').replace(/^00/, '')
  if (d.length < 8) return null
  return `https://wa.me/${d.length === 8 ? `502${d}` : d}?text=${encodeURIComponent(texto)}`
}
