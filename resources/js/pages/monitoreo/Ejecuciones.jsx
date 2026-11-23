import PaginaCrud from '../../components/PaginaCrud';
import Insignia from '../../components/Insignia';
import { fechaHora } from '../../lib/formato';

const ESTADOS = {
    en_proceso: ['En proceso', 'aviso'],
    completada: ['Completada', 'exito'],
    error: ['Error', 'peligro'],
};

/** Ejecuciones del monitoreo automático (scraping de YouTube del sistema vía correo). */
export default function Ejecuciones() {
    return (
        <PaginaCrud
            titulo="Ejecuciones de monitoreo"
            descripcion="Historial de búsquedas automáticas de páginas no autorizadas y cuántas detecciones produjo cada una."
            singular="ejecución"
            endpoint="/ejecuciones"
            privilegio="ejecuciones"
            idCampo="id_ejecucion"
            busqueda={false}
            filtros={[{ nombre: 'estado', etiqueta: 'Estado', opciones: Object.entries(ESTADOS).map(([id, [nombre]]) => ({ id, nombre })) }]}
            columnas={[
                { titulo: 'ID', campo: 'id_ejecucion', className: 'w-14' },
                { titulo: 'Plataforma', render: (e) => e.plataforma?.nombre },
                { titulo: 'Inicio', render: (e) => fechaHora(e.fecha_inicio) },
                { titulo: 'Fin', render: (e) => fechaHora(e.fecha_fin) },
                { titulo: 'Detecciones', campo: 'detecciones_count', className: 'text-center' },
                { titulo: 'Estado', render: (e) => <Insignia tono={ESTADOS[e.estado]?.[1]}>{ESTADOS[e.estado]?.[0] ?? e.estado}</Insignia> },
                { titulo: 'Mensaje', render: (e) => <span className="text-xs text-peligro">{e.mensaje_error ?? ''}</span> },
            ]}
        />
    );
}
