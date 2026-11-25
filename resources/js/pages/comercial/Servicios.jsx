import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import Insignia, { InsigniaActivo } from '../../components/Insignia';

const TIPOS_SERVICIO = [
    { id: 'television', nombre: 'Televisión' },
    { id: 'radio', nombre: 'Radio' },
    { id: 'redes_sociales', nombre: 'Redes sociales' },
    { id: 'web', nombre: 'Sitio web' },
    { id: 'produccion', nombre: 'Producción audiovisual' },
    { id: 'otro', nombre: 'Otro' },
];

const ETIQUETA_TIPO = Object.fromEntries(TIPOS_SERVICIO.map((t) => [t.id, t.nombre]));

export default function Servicios() {
    return (
        <PaginaCrud
            titulo="Servicios publicitarios"
            descripcion="Catálogo de servicios ofrecidos por TVU / Canal 11 UAGRM."
            singular="servicio"
            endpoint="/servicios"
            privilegio="servicios"
            idCampo="id_servicio"
            placeholderBusqueda="Buscar por nombre o descripción…"
            filtros={[
                FILTRO_ESTADO,
                { nombre: 'tipo', etiqueta: 'Tipo', opciones: TIPOS_SERVICIO },
            ]}
            columnas={[
                { titulo: 'ID', campo: 'id_servicio', className: 'w-14' },
                {
                    titulo: 'Servicio',
                    render: (s) => (
                        <div>
                            <p className="font-medium">{s.nombre}</p>
                            {s.descripcion && <p className="text-xs text-texto-suave line-clamp-2">{s.descripcion}</p>}
                        </div>
                    ),
                },
                { titulo: 'Tipo', render: (s) => (s.tipo ? <Insignia tono="primario">{ETIQUETA_TIPO[s.tipo] ?? s.tipo}</Insignia> : '—') },
                { titulo: 'Tarifas', campo: 'tarifas_count', className: 'text-center' },
                { titulo: 'Estado', render: (s) => <InsigniaActivo activo={s.activo} /> },
            ]}
            campos={[
                { nombre: 'nombre', etiqueta: 'Nombre del servicio', ancho: 'medio', reglas: { requerido: true, min: 2, max: 150 } },
                { nombre: 'tipo', etiqueta: 'Tipo', tipo: 'select', opciones: TIPOS_SERVICIO, ancho: 'medio', vacio: 'Sin especificar' },
                { nombre: 'descripcion', etiqueta: 'Descripción', tipo: 'textarea', reglas: { max: 500 } },
            ]}
            aFormulario={(s) => ({
                nombre: s.nombre,
                tipo: s.tipo ?? '',
                descripcion: s.descripcion ?? '',
            })}
            estado={{ activo: (s) => s.activo, endpoint: (s) => `/servicios/${s.id_servicio}/estado` }}
        />
    );
}

