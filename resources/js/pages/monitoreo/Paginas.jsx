import { useEffect, useState } from 'react';
import { ExternalLink, Eye } from 'lucide-react';
import PaginaCrud from '../../components/PaginaCrud';
import Insignia from '../../components/Insignia';
import Modal from '../../components/Modal';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { api, mensajeError } from '../../lib/api';
import { fechaHora } from '../../lib/formato';

export const ESTADOS_PAGINA = {
    detectada: ['Detectada', 'aviso'],
    confirmada: ['Confirmada', 'peligro'],
    denunciada: ['Denunciada', 'primario'],
    eliminada: ['Eliminada', 'exito'],
};
const SIGUIENTE = { detectada: 'confirmada', confirmada: 'denunciada', denunciada: 'eliminada' };
const VERBO = { confirmada: 'Confirmar uso indebido', denunciada: 'Marcar como denunciada', eliminada: 'Marcar como eliminada' };

export const InsigniaPagina = ({ estado }) => <Insignia tono={ESTADOS_PAGINA[estado]?.[1]}>{ESTADOS_PAGINA[estado]?.[0] ?? estado}</Insignia>;

export default function Paginas() {
    const [detalle, setDetalle] = useState(null);
    const [recargarLista, setRecargarLista] = useState(() => () => {});

    return (
        <>
            <PaginaCrud
                titulo="Páginas no autorizadas"
                descripcion="Páginas detectadas que usan la imagen de la UAGRM o Canal 11. Flujo: detectada → confirmada → denunciada → eliminada."
                singular="página"
                endpoint="/paginas"
                privilegio="paginas"
                idCampo="id_pagina"
                editable={false}
                placeholderBusqueda="Buscar por nombre o URL…"
                filtros={[
                    { nombre: 'estado', etiqueta: 'Estado', opciones: Object.entries(ESTADOS_PAGINA).map(([id, [nombre]]) => ({ id, nombre })) },
                    { nombre: 'id_plataforma', etiqueta: 'Plataforma', opciones: 'plataformas' },
                ]}
                columnas={[
                    { titulo: 'ID', campo: 'id_pagina', className: 'w-14' },
                    {
                        titulo: 'Página',
                        render: (p) => (
                            <div className="max-w-md">
                                <p className="font-medium">{p.nombre_pagina ?? 'Sin nombre'}</p>
                                <a href={p.url} target="_blank" rel="noreferrer" className="text-xs text-primario hover:underline break-all">{p.url}</a>
                            </div>
                        ),
                    },
                    { titulo: 'Plataforma', render: (p) => p.plataforma?.nombre },
                    { titulo: 'Detecciones', campo: 'detecciones_count', className: 'text-center' },
                    { titulo: 'Estado', render: (p) => <InsigniaPagina estado={p.estado} /> },
                    { titulo: 'Revisión', render: (p) => (p.revisor ? <span className="text-xs">{p.revisor.nombre_completo}<br />{fechaHora(p.fecha_revision)}</span> : '—') },
                ]}
                campos={[
                    { nombre: 'url', etiqueta: 'Dirección (URL)', tipo: 'url', placeholder: 'https://…', reglas: { requerido: true, url: true, max: 500 } },
                    { nombre: 'nombre_pagina', etiqueta: 'Nombre de la página', ancho: 'medio', reglas: { max: 200 } },
                    { nombre: 'id_plataforma', etiqueta: 'Plataforma', tipo: 'select', opciones: 'plataformas', ancho: 'medio', reglas: { requerido: true } },
                ]}
                acciones={(p, recargar) => (
                    <button
                        className="btn-icono"
                        onClick={() => {
                            setRecargarLista(() => recargar);
                            setDetalle(p.id_pagina);
                        }}
                        title="Ver detalle y revisar"
                        aria-label="Ver detalle y revisar"
                    >
                        <Eye size={16} />
                    </button>
                )}
            />
            {detalle && <DetallePagina id={detalle} onCerrar={() => setDetalle(null)} onCambio={recargarLista} />}
        </>
    );
}

function DetallePagina({ id, onCerrar, onCambio }) {
    const { puede } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const [p, setP] = useState(null);

    useEffect(() => {
        api.get(`/paginas/${id}`).then(({ data }) => setP(data.data)).catch((e) => error(mensajeError(e)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    const avanzar = async () => {
        const nuevo = SIGUIENTE[p.estado];
        if (!(await confirmar(`La página pasará a estado «${nuevo}». Este cambio no se puede deshacer.`, { titulo: VERBO[nuevo], textoAceptar: VERBO[nuevo] }))) return;
        try {
            const { data } = await api.patch(`/paginas/${id}/estado`, { estado: nuevo });
            setP(data.data);
            exito(data.message);
            onCambio();
        } catch (e) {
            error(mensajeError(e));
        }
    };

    return (
        <Modal titulo="Detalle de página no autorizada" abierto onCerrar={onCerrar} ancho="max-w-2xl"
            pie={p && SIGUIENTE[p.estado] && puede('paginas.editar') && <button className="btn btn-primario" onClick={avanzar}>{VERBO[SIGUIENTE[p.estado]]}</button>}
        >
            {!p ? (
                <p className="text-texto-suave">Cargando…</p>
            ) : (
                <div className="space-y-4 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <InsigniaPagina estado={p.estado} />
                        <span className="text-texto-suave">{p.plataforma?.nombre}</span>
                    </div>
                    <div>
                        <p className="font-semibold text-base">{p.nombre_pagina ?? 'Sin nombre'}</p>
                        <a href={p.url} target="_blank" rel="noreferrer" className="text-primario hover:underline break-all inline-flex items-center gap-1">
                            {p.url} <ExternalLink size={14} aria-hidden />
                        </a>
                    </div>
                    {p.revisor && <p>Última revisión: <strong>{p.revisor.nombre_completo}</strong> el {fechaHora(p.fecha_revision)}</p>}
                    <div>
                        <h3 className="font-semibold mb-2">Detecciones ({p.detecciones.length})</h3>
                        {p.detecciones.length === 0 ? (
                            <p className="text-texto-suave">Registrada manualmente, sin detecciones automáticas.</p>
                        ) : (
                            <ul className="divide-y divide-borde max-h-64 overflow-y-auto">
                                {p.detecciones.map((d) => (
                                    <li key={d.id_deteccion} className="py-2">
                                        <p className="text-xs text-texto-suave">{fechaHora(d.fecha_deteccion)} · término: <strong>{d.termino?.termino ?? '—'}</strong></p>
                                        {d.evidencia && <p className="mt-1 break-words">{d.evidencia}</p>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}
        </Modal>
    );
}
