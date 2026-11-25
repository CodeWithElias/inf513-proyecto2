import PaginaCrud from '../../components/PaginaCrud';
import Insignia from '../../components/Insignia';
import { fecha, moneda } from '../../lib/formato';

const ESTADOS_CAMPANIA = [
    { id: 'activa', nombre: 'Activa', tono: 'exito' },
    { id: 'pausada', nombre: 'Pausada', tono: 'aviso' },
    { id: 'finalizada', nombre: 'Finalizada', tono: 'primario' },
    { id: 'cancelada', nombre: 'Cancelada', tono: 'peligro' },
];
const MAPA_ESTADO = Object.fromEntries(ESTADOS_CAMPANIA.map((e) => [e.id, e]));

export default function Campanias() {
    return (
        <PaginaCrud
            titulo="Campañas publicitarias"
            descripcion="Campañas creadas a partir de proformas aprobadas para coordinar material y pautas."
            singular="campaña"
            endpoint="/campanias"
            privilegio="campanias"
            idCampo="id_campania"
            placeholderBusqueda="Buscar por nombre o número de proforma…"
            filtros={[
                { nombre: 'estado', etiqueta: 'Estado', opciones: ESTADOS_CAMPANIA },
            ]}
            columnas={[
                { titulo: 'ID', campo: 'id_campania', className: 'w-14' },
                { titulo: 'Campaña', campo: 'nombre' },
                {
                    titulo: 'Proforma / Cliente',
                    render: (c) => (
                        <div>
                            <p className="font-medium">{c.proforma?.nro_proforma ?? '—'}</p>
                            <p className="text-xs text-texto-suave">
                                {c.proforma?.empresa?.razon_social ?? c.proforma?.visitante?.nombre ?? '—'}
                            </p>
                        </div>
                    ),
                },
                { titulo: 'Monto', render: (c) => moneda(c.proforma?.total) },
                {
                    titulo: 'Fechas',
                    render: (c) => `${fecha(c.fecha_inicio)} → ${fecha(c.fecha_fin)}`,
                },
                {
                    titulo: 'Estado',
                    render: (c) => {
                        const meta = MAPA_ESTADO[c.estado];
                        return <Insignia tono={meta?.tono ?? 'neutro'}>{meta?.nombre ?? c.estado}</Insignia>;
                    },
                },
            ]}
            campos={[
                { nombre: 'nombre', etiqueta: 'Nombre de la campaña', reglas: { requerido: true, min: 3, max: 200 } },
                {
                    nombre: 'id_proforma',
                    etiqueta: 'Proforma aprobada',
                    tipo: 'select',
                    opciones: 'proformas-sin-campania',
                    soloCrear: true,
                    reglas: (modo) => (modo === 'crear' ? { requerido: true } : {}),
                },
                { nombre: 'fecha_inicio', etiqueta: 'Fecha de inicio', tipo: 'date', ancho: 'medio', reglas: { fecha: true } },
                { nombre: 'fecha_fin', etiqueta: 'Fecha de fin', tipo: 'date', ancho: 'medio', reglas: { fecha: true } },
                {
                    nombre: 'estado',
                    etiqueta: 'Estado',
                    tipo: 'select',
                    opciones: ESTADOS_CAMPANIA,
                    valorInicial: 'activa',
                    reglas: { requerido: true },
                },
            ]}
            aFormulario={(c) => ({
                nombre: c.nombre,
                fecha_inicio: c.fecha_inicio ?? '',
                fecha_fin: c.fecha_fin ?? '',
                estado: c.estado,
            })}
        />
    );
}

