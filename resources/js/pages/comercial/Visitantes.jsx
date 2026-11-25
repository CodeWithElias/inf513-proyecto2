import { useEffect, useState } from 'react';
import { Eye } from 'lucide-react';
import PaginaCrud from '../../components/PaginaCrud';
import Modal from '../../components/Modal';
import Insignia from '../../components/Insignia';
import { useAvisos } from '../../context/AvisosContext';
import { api, mensajeError } from '../../lib/api';
import { fecha, fechaHora, moneda } from '../../lib/formato';

const TONO_PROFORMA = { pendiente: 'aviso', aprobada: 'exito', rechazada: 'peligro', vencida: 'neutro' };

export default function Visitantes() {
    const [detalle, setDetalle] = useState(null);

    return (
        <>
            <PaginaCrud
                titulo="Visitantes"
                descripcion="Personas externas que solicitaron cotizaciones públicas desde el catálogo sin iniciar sesión."
                singular="visitante"
                endpoint="/visitantes"
                privilegio="visitantes"
                idCampo="id_visitante"
                creable={false}
                editable={false}
                placeholderBusqueda="Buscar por nombre, correo o teléfono…"
                columnas={[
                    { titulo: 'ID', campo: 'id_visitante', className: 'w-14' },
                    { titulo: 'Nombre', campo: 'nombre' },
                    { titulo: 'Correo', render: (v) => v.email ?? '—' },
                    { titulo: 'Teléfono', render: (v) => v.telefono ?? '—' },
                    { titulo: 'Cotizaciones', campo: 'proformas_count', className: 'text-center' },
                    { titulo: 'Registro', render: (v) => fechaHora(v.fecha_registro) },
                ]}
                acciones={(v) => (
                    <button
                        className="btn-icono"
                        onClick={() => setDetalle(v.id_visitante)}
                        title="Ver cotizaciones del visitante"
                        aria-label="Ver cotizaciones del visitante"
                    >
                        <Eye size={16} />
                    </button>
                )}
            />
            {detalle && <DetalleVisitante id={detalle} onCerrar={() => setDetalle(null)} />}
        </>
    );
}

function DetalleVisitante({ id, onCerrar }) {
    const { error } = useAvisos();
    const [v, setV] = useState(null);

    useEffect(() => {
        api.get(`/visitantes/${id}`).then(({ data }) => setV(data.data)).catch((e) => error(mensajeError(e)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    return (
        <Modal titulo="Detalle de visitante" abierto onCerrar={onCerrar} ancho="max-w-2xl">
            {!v ? (
                <p className="text-texto-suave">Cargando…</p>
            ) : (
                <div className="space-y-4 text-sm">
                    <div className="grid sm:grid-cols-2 gap-3 bg-superficie-2 rounded-tema p-3">
                        <div>
                            <p className="text-xs text-texto-suave">Nombre</p>
                            <p className="font-semibold">{v.nombre}</p>
                        </div>
                        <div>
                            <p className="text-xs text-texto-suave">Correo</p>
                            <p>{v.email ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-texto-suave">Teléfono</p>
                            <p>{v.telefono ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-texto-suave">Fecha de registro</p>
                            <p>{fechaHora(v.fecha_registro)}</p>
                        </div>
                    </div>

                    <div>
                        <h3 className="font-semibold mb-2">Proformas solicitadas ({v.proformas?.length ?? 0})</h3>
                        {!v.proformas?.length ? (
                            <p className="text-texto-suave">Sin proformas registradas.</p>
                        ) : (
                            <ul className="divide-y divide-borde">
                                {v.proformas.map((p) => (
                                    <li key={p.id_proforma} className="py-2 flex items-center justify-between gap-2">
                                        <div>
                                            <p className="font-medium">{p.nro_proforma}</p>
                                            <p className="text-xs text-texto-suave">
                                                Emisión: {fecha(p.fecha_emision)} · Válida hasta: {fecha(p.fecha_validez)}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="font-semibold">{moneda(p.total)}</span>
                                            <Insignia tono={TONO_PROFORMA[p.estado]}>{p.estado}</Insignia>
                                        </div>
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

