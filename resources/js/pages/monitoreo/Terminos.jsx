import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';

export default function Terminos() {
    return (
        <PaginaCrud
            titulo="Términos de monitoreo"
            descripcion="Palabras que se buscan para detectar páginas que usan sin autorización la imagen de la UAGRM o de Canal 11."
            singular="término"
            endpoint="/terminos"
            privilegio="terminos"
            idCampo="id_termino"
            editable={false}
            placeholderBusqueda="Buscar término…"
            filtros={[FILTRO_ESTADO]}
            columnas={[
                { titulo: 'ID', campo: 'id_termino', className: 'w-14' },
                { titulo: 'Término', render: (t) => <span className="font-medium">{t.termino}</span> },
                { titulo: 'Tipo', render: (t) => t.tipo ?? '—' },
                { titulo: 'Estado', render: (t) => <InsigniaActivo activo={t.activo} /> },
            ]}
            campos={[
                { nombre: 'termino', etiqueta: 'Término', placeholder: 'Canal 11 UAGRM', reglas: { requerido: true, min: 3, max: 200 } },
                { nombre: 'tipo', etiqueta: 'Tipo', placeholder: 'marca, institución…', reglas: { max: 50 } },
            ]}
            estado={{ activo: (t) => t.activo, endpoint: (t) => `/terminos/${t.id_termino}/estado` }}
        />
    );
}
