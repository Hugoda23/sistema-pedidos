import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from './api'

// La misma lista vacía siempre: si fuera `[]` nuevo en cada render, los efectos
// que dependen de `filas` se dispararían sin fin mientras carga.
const SIN_FILAS = []

/** Carga un listado paginado y lo recarga cuando cambian los filtros. */
export function useListado(ruta, filtros) {
  const [datos, setDatos] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const clave = JSON.stringify(filtros)

  // Al cambiar un filtro se vuelve a la página 1.
  useEffect(() => setPage(1), [clave])

  const recargar = useCallback(() => {
    setCargando(true)
    return api(ruta, { params: { ...JSON.parse(clave), page } })
      .then((d) => { setDatos(d); setError('') })
      .catch((e) => setError(e.message))
      .finally(() => setCargando(false))
  }, [ruta, clave, page])

  useEffect(() => {
    // Espera un momento para no consultar en cada tecla del buscador.
    const t = setTimeout(recargar, 250)
    return () => clearTimeout(t)
  }, [recargar])

  const filas = useMemo(() => datos?.data ?? SIN_FILAS, [datos])

  return { filas, pagina: datos, cargando, error, setPage, recargar }
}
