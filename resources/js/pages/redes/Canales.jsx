import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';
import { fecha } from '../../lib/formato';

const TIPOS = [
    { id: 'television', nombre: 'Televisión' },
    { id: 'radio', nombre: 'Radio' },
    { id: 'web', nombre: 'Web' },
];
const nombreTipo = (t) => TIPOS.find((x) => x.id === t)?.nombre ?? t;

export default function Canales() {
    return (
        <PaginaCrud
            titulo="Canales de difusión"
            descripcion="Canales de televisión, radio y web donde se emite la pauta. Las redes sociales se gestionan aparte."
            singular="canal"
            endpoint="/canales"
            privilegio="canales"
            idCampo="id_canal"
            editable={false}
            placeholderBusqueda="Buscar canal…"
            filtros={[FILTRO_ESTADO, { nombre: 'tipo', etiqueta: 'Tipo', opciones: TIPOS }]}
            columnas={[
                { titulo: 'ID', campo: 'id_canal', className: 'w-14' },
                { titulo: 'Canal', render: (c) => <span className="font-medium">{c.nombre}</span> },
                { titulo: 'Tipo', render: (c) => nombreTipo(c.tipo) },
                { titulo: 'Alta', render: (c) => fecha(c.fecha_alta) },
                { titulo: 'Baja', render: (c) => fecha(c.fecha_baja) },
                { titulo: 'Estado', render: (c) => <InsigniaActivo activo={c.activo} /> },
            ]}
            campos={[
                { nombre: 'tipo', etiqueta: 'Tipo', tipo: 'select', opciones: TIPOS, ancho: 'medio', reglas: { requerido: true } },
                { nombre: 'nombre', etiqueta: 'Nombre', ancho: 'medio', reglas: { requerido: true, min: 2, max: 150 } },
            ]}
            estado={{ activo: (c) => c.activo, endpoint: (c) => `/canales/${c.id_canal}/estado` }}
        />
    );
}
