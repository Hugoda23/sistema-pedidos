import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table'

/**
 * Tabla con paginación del servidor (respuesta paginate() de Laravel).
 * `pagina` = { current_page, last_page, total, from, to }.
 */
export default function DataTable({ columnas, datos, pagina, onPagina, cargando, vacio = 'No hay registros.', claseFila }) {
  const tabla = useReactTable({ data: datos ?? [], columns: columnas, getCoreRowModel: getCoreRowModel(), getRowId: (f, i) => String(f.id ?? f.codigo ?? i) })

  return (
    <div className="overflow-hidden rounded-xl border bg-white">
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
            {tabla.getHeaderGroups().map((g) => (
              <tr key={g.id}>
                {g.headers.map((h) => (
                  <th key={h.id} className="whitespace-nowrap px-4 py-3 font-medium">
                    {flexRender(h.column.columnDef.header, h.getContext())}
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody className={`divide-y ${cargando ? 'opacity-50' : ''}`}>
            {tabla.getRowModel().rows.map((fila) => (
              <tr key={fila.id} className={`hover:bg-slate-50 ${claseFila?.(fila.original) ?? ''}`}>
                {fila.getVisibleCells().map((c) => (
                  <td key={c.id} className="whitespace-nowrap px-4 py-2.5 text-slate-700">
                    {flexRender(c.column.columnDef.cell, c.getContext())}
                  </td>
                ))}
              </tr>
            ))}
            {!cargando && !datos?.length && (
              <tr><td colSpan={columnas.length} className="px-4 py-8 text-center text-slate-400">{vacio}</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {pagina && pagina.last_page > 1 && (
        <div className="flex items-center justify-between border-t px-4 py-2 text-sm text-slate-600">
          <span>{pagina.from}–{pagina.to} de {pagina.total}</span>
          <div className="flex gap-1">
            <button disabled={pagina.current_page <= 1} onClick={() => onPagina(pagina.current_page - 1)}
              className="rounded border px-3 py-1 disabled:opacity-40">Anterior</button>
            <span className="px-2 py-1">{pagina.current_page} / {pagina.last_page}</span>
            <button disabled={pagina.current_page >= pagina.last_page} onClick={() => onPagina(pagina.current_page + 1)}
              className="rounded border px-3 py-1 disabled:opacity-40">Siguiente</button>
          </div>
        </div>
      )}
    </div>
  )
}
