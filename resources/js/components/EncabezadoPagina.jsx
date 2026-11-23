/** Título de pantalla con descripción y acciones a la derecha. */
export default function EncabezadoPagina({ titulo, descripcion, children }) {
    return (
        <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
            <div>
                <h1 className="text-2xl font-bold">{titulo}</h1>
                {descripcion && <p className="text-texto-suave text-sm mt-1">{descripcion}</p>}
            </div>
            {children && <div className="flex flex-wrap gap-2">{children}</div>}
        </div>
    );
}
