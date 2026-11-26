/**
 * Título de pantalla con descripción y acciones a la derecha.
 * Las acciones se pasan como hijos o en la prop `accion` (ambas formas se usan en el proyecto).
 */
export default function EncabezadoPagina({ titulo, descripcion, accion, children }) {
    const acciones = children ?? accion;
    return (
        <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
            <div>
                <h1 className="text-2xl font-bold">{titulo}</h1>
                {descripcion && <p className="text-texto-suave text-sm mt-1">{descripcion}</p>}
            </div>
            {acciones && <div className="flex flex-wrap gap-2">{acciones}</div>}
        </div>
    );
}
