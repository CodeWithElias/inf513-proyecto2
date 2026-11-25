import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';
import { fecha, moneda } from '../../lib/formato';

export default function Tarifas() {
    return (
        <PaginaCrud
            titulo="Tarifas"
            descripcion="Precios vigentes de cada servicio publicitario, duración y unidad de cobro."
            singular="tarifa"
            endpoint="/tarifas"
            privilegio="tarifas"
            idCampo="id_tarifa"
            placeholderBusqueda="Buscar por servicio o unidad de cobro…"
            filtros={[
                FILTRO_ESTADO,
                { nombre: 'id_servicio', etiqueta: 'Servicio', opciones: 'servicios' },
                { nombre: 'id_unidad', etiqueta: 'Unidad', opciones: 'unidades' },
            ]}
            columnas={[
                { titulo: 'ID', campo: 'id_tarifa', className: 'w-14' },
                {
                    titulo: 'Servicio',
                    render: (t) => (
                        <div>
                            <p className="font-medium">{t.servicio?.nombre ?? '—'}</p>
                            <p className="text-xs text-texto-suave">{t.unidad?.nombre ?? 'General'}</p>
                        </div>
                    ),
                },
                { titulo: 'Monto', render: (t) => <span className="font-semibold">{moneda(t.monto)}</span> },
                { titulo: 'Unidad de cobro', render: (t) => t.unidad_cobro ?? '—' },
                { titulo: 'Duración', render: (t) => (t.duracion_seg ? `${t.duracion_seg} s` : '—') },
                {
                    titulo: 'Vigencia',
                    render: (t) => (t.vigente_desde || t.vigente_hasta ? `${fecha(t.vigente_desde)} → ${fecha(t.vigente_hasta)}` : 'Permanente'),
                },
                { titulo: 'Estado', render: (t) => <InsigniaActivo activo={t.activo} /> },
            ]}
            campos={[
                { nombre: 'id_servicio', etiqueta: 'Servicio publicitario', tipo: 'select', opciones: 'servicios', ancho: 'medio', reglas: { requerido: true } },
                { nombre: 'id_unidad', etiqueta: 'Unidad universitaria', tipo: 'select', opciones: 'unidades', ancho: 'medio', vacio: 'General (todas)' },
                { nombre: 'monto', etiqueta: 'Monto (Bs)', tipo: 'number', ancho: 'medio', reglas: { requerido: true, minValor: 0.01 } },
                { nombre: 'unidad_cobro', etiqueta: 'Unidad de cobro', placeholder: 'Ej. por pase, por día, por publicación', ancho: 'medio', reglas: { max: 50 } },
                { nombre: 'duracion_seg', etiqueta: 'Duración (segundos)', tipo: 'number', ancho: 'medio', reglas: { entero: true, minValor: 1 } },
                { nombre: 'vigente_desde', etiqueta: 'Vigente desde', tipo: 'date', ancho: 'medio', reglas: { fecha: true } },
                { nombre: 'vigente_hasta', etiqueta: 'Vigente hasta', tipo: 'date', ancho: 'medio', reglas: { fecha: true } },
            ]}
            aFormulario={(t) => ({
                id_servicio: String(t.id_servicio),
                id_unidad: t.id_unidad ? String(t.id_unidad) : '',
                monto: String(t.monto),
                unidad_cobro: t.unidad_cobro ?? '',
                duracion_seg: t.duracion_seg ? String(t.duracion_seg) : '',
                vigente_desde: t.vigente_desde ?? '',
                vigente_hasta: t.vigente_hasta ?? '',
            })}
            estado={{ activo: (t) => t.activo, endpoint: (t) => `/tarifas/${t.id_tarifa}/estado` }}
        />
    );
}

