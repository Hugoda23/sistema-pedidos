// Convierte { campo: ['mensaje'] } de Laravel en { campo: 'mensaje' }.
export const primerError = (errors = {}) =>
  Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]))
