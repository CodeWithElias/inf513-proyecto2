import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';

/**
 * Tabla de datos con estados de carga/vacío y paginación.
 *   columnas: [{ titulo, campo?, render?(fila), className? }]
 *   meta: { current_page, last_page, total }  (opcional)
 */
export default function Tabla({ columnas, filas, cargando, meta, onPagina, idFila = 'id', vacio = 'No hay registros para mostrar.' }) {
    return (
        <div className="tarjeta overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-superficie-2 text-left">
                        <tr>
                            {columnas.map((c, i) => (
                                <th key={i} scope="col" className={`px-4 py-3 font-semibold whitespace-nowrap ${c.className ?? ''}`}>
                                    {c.titulo}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {cargando ? (
                            <tr>
                                <td colSpan={columnas.length} className="px-4 py-10 text-center text-texto-suave">
                                    <Loader2 className="inline animate-spin mr-2" size={18} aria-hidden /> Cargando…
                                </td>
                            </tr>
                        ) : filas.length === 0 ? (
                            <tr>
                                <td colSpan={columnas.length} className="px-4 py-10 text-center text-texto-suave">{vacio}</td>
                            </tr>
                        ) : (
                            filas.map((f, i) => (
                                <tr key={f[idFila] ?? i} className="border-t border-borde hover:bg-superficie-2/60">
                                    {columnas.map((c, j) => (
                                        <td key={j} className={`px-4 py-3 align-middle ${c.className ?? ''}`}>
                                            {c.render ? c.render(f) : (f[c.campo] ?? '—')}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {meta && meta.last_page > 1 && (
                <nav className="flex items-center justify-between px-4 py-3 border-t border-borde text-sm" aria-label="Paginación">
                    <span className="text-texto-suave">
                        Página {meta.current_page} de {meta.last_page} · {meta.total} registros
                    </span>
                    <div className="flex gap-1">
                        <button className="btn btn-secundario !px-2 !py-1" disabled={meta.current_page <= 1} onClick={() => onPagina(meta.current_page - 1)} aria-label="Página anterior">
                            <ChevronLeft size={16} />
                        </button>
                        <button className="btn btn-secundario !px-2 !py-1" disabled={meta.current_page >= meta.last_page} onClick={() => onPagina(meta.current_page + 1)} aria-label="Página siguiente">
                            <ChevronRight size={16} />
                        </button>
                    </div>
                </nav>
            )}
            {meta && meta.last_page <= 1 && meta.total > 0 && (
                <p className="px-4 py-2 border-t border-borde text-xs text-texto-suave">{meta.total} registro(s)</p>
            )}
        </div>
    );
}
