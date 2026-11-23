import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';

export default function Unidades() {
    return (
        <PaginaCrud
            titulo="Unidades universitarias"
            descripcion="Facultades, carreras y direcciones de la UAGRM, organizadas en jerarquía."
            singular="unidad"
            endpoint="/unidades"
            privilegio="unidades"
            idCampo="id_unidad"
            placeholderBusqueda="Buscar por nombre o tipo…"
            filtros={[FILTRO_ESTADO]}
            columnas={[
                { titulo: 'ID', campo: 'id_unidad', className: 'w-14' },
                { titulo: 'Unidad', render: (u) => <span className="font-medium">{u.nombre}</span> },
                { titulo: 'Tipo', render: (u) => u.tipo ?? '—' },
                { titulo: 'Depende de', render: (u) => u.padre?.nombre ?? <span className="text-texto-suave">Unidad raíz</span> },
                { titulo: 'Usuarios', campo: 'usuarios_activos', className: 'text-center' },
                { titulo: 'Subunidades', campo: 'subunidades_activas', className: 'text-center' },
                { titulo: 'Estado', render: (u) => <InsigniaActivo activo={u.activo} /> },
            ]}
            campos={[
                { nombre: 'nombre', etiqueta: 'Nombre', reglas: { requerido: true, min: 3, max: 150 } },
                { nombre: 'tipo', etiqueta: 'Tipo', ancho: 'medio', placeholder: 'Facultad, Carrera, Dirección…', reglas: { max: 50 } },
                { nombre: 'id_unidad_padre', etiqueta: 'Unidad padre', tipo: 'select', opciones: 'unidades', ancho: 'medio', vacio: 'Ninguna (unidad raíz)' },
            ]}
            aFormulario={(u) => ({ nombre: u.nombre, tipo: u.tipo ?? '', id_unidad_padre: u.id_unidad_padre ? String(u.id_unidad_padre) : '' })}
            estado={{ activo: (u) => u.activo, endpoint: (u) => `/unidades/${u.id_unidad}/estado` }}
        />
    );
}
