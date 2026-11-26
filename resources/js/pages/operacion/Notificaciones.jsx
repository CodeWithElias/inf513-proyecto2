import { useState, useEffect, useCallback } from 'react';
import { Bell, Check, CheckCheck, Loader2, Calendar, ChevronLeft, ChevronRight } from 'lucide-react';
import { api, mensajeError } from '../../lib/api';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import { fechaHora } from '../../lib/formato';
import { avisarCambioNotificaciones } from '../../components/CampanaNotificaciones';

export default function Notificaciones() {
    const { exito, error } = useAvisos();

    const [notificaciones, setNotificaciones] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [cargando, setCargando] = useState(false);
    const [filtroLeida, setFiltroLeida] = useState(''); // '' (todas) | 'false' (no leídas) | 'true' (leídas)
    const [pagina, setPagina] = useState(1);

    const cargarNotificaciones = useCallback(async () => {
        setCargando(true);
        try {
            const params = {
                page: pagina,
                ...(filtroLeida !== '' ? { leida: filtroLeida } : {}),
            };
            const { data } = await api.get('/notificaciones', { params });
            setNotificaciones(data.data || []);
            setMeta(data.meta || { current_page: 1, last_page: 1, total: 0 });
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setCargando(false);
        }
    }, [pagina, filtroLeida, error]);

    useEffect(() => {
        cargarNotificaciones();
    }, [cargarNotificaciones]);

    const marcarUnaLeida = async (notif) => {
        try {
            await api.patch(`/notificaciones/${notif.id_notificacion}/leida`);
            setNotificaciones((prev) =>
                prev.map((n) => (n.id_notificacion === notif.id_notificacion ? { ...n, leida: true } : n))
            );
            avisarCambioNotificaciones();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const marcarTodas = async () => {
        try {
            await api.post('/notificaciones/marcar-todas');
            exito('Todas las notificaciones han sido marcadas como leídas.');
            avisarCambioNotificaciones();
            cargarNotificaciones();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    return (
        <div className="space-y-4 max-w-4xl mx-auto">
            <EncabezadoPagina
                titulo="Notificaciones del sistema"
                descripcion="Avisos sobre programación de pautas publicitarias, revisiones y alertas operativas."
                accion={
                    <button type="button" className="btn btn-secundario text-xs sm:text-sm" onClick={marcarTodas}>
                        <CheckCheck size={16} /> Marcar todo como leído
                    </button>
                }
            />

            {/* Filtro de estado */}
            <div className="flex items-center gap-2 border-b border-borde pb-2">
                <button
                    type="button"
                    onClick={() => {
                        setFiltroLeida('');
                        setPagina(1);
                    }}
                    className={`px-3 py-1.5 rounded-tema text-xs font-semibold transition-colors ${
                        filtroLeida === '' ? 'bg-primario text-primario-texto' : 'hover:bg-superficie-2 text-texto-suave'
                    }`}
                >
                    Todas
                </button>
                <button
                    type="button"
                    onClick={() => {
                        setFiltroLeida('false');
                        setPagina(1);
                    }}
                    className={`px-3 py-1.5 rounded-tema text-xs font-semibold transition-colors ${
                        filtroLeida === 'false' ? 'bg-primario text-primario-texto' : 'hover:bg-superficie-2 text-texto-suave'
                    }`}
                >
                    No leídas
                </button>
                <button
                    type="button"
                    onClick={() => {
                        setFiltroLeida('true');
                        setPagina(1);
                    }}
                    className={`px-3 py-1.5 rounded-tema text-xs font-semibold transition-colors ${
                        filtroLeida === 'true' ? 'bg-primario text-primario-texto' : 'hover:bg-superficie-2 text-texto-suave'
                    }`}
                >
                    Leídas
                </button>
            </div>

            {/* Listado de tarjetas de notificación */}
            {cargando ? (
                <div className="tarjeta p-12 text-center text-texto-suave">
                    <Loader2 className="inline animate-spin mr-2" size={20} /> Cargando notificaciones...
                </div>
            ) : notificaciones.length === 0 ? (
                <div className="tarjeta p-12 text-center text-texto-suave">
                    <Bell className="mx-auto mb-2 opacity-40" size={32} />
                    <p className="font-medium">No tienes notificaciones en esta sección.</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {notificaciones.map((n) => (
                        <div
                            key={n.id_notificacion}
                            className={`tarjeta p-4 transition-all flex items-start gap-3.5 ${
                                !n.leida
                                    ? 'bg-superficie border-l-4 border-l-primario shadow-sm'
                                    : 'bg-superficie/70 opacity-80'
                            }`}
                        >
                            <div
                                className={`p-2.5 rounded-tema shrink-0 mt-0.5 ${
                                    !n.leida ? 'bg-primario/15 text-primario' : 'bg-superficie-2 text-texto-suave'
                                }`}
                            >
                                <Bell size={18} />
                            </div>

                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className={`text-sm ${!n.leida ? 'font-bold text-texto' : 'font-medium text-texto'}`}>
                                        {n.titulo}
                                    </h3>
                                    <span className="text-[11px] text-texto-suave shrink-0 flex items-center gap-1">
                                        <Calendar size={11} />
                                        {fechaHora(n.fecha_envio)}
                                    </span>
                                </div>

                                <p className="text-xs text-texto-suave mt-1 whitespace-pre-wrap leading-relaxed">
                                    {n.mensaje}
                                </p>

                                {n.pauta && (
                                    <div className="mt-2 text-[11px] text-texto font-medium bg-superficie-2/50 p-2 rounded border border-borde/50">
                                        Pauta #{n.pauta.id_programacion} · Pieza:{' '}
                                        <span className="text-primario">{n.pauta.material?.nombre_archivo}</span>
                                        {n.pauta.espacio?.canal && ` · Canal: ${n.pauta.espacio.canal.nombre}`}
                                    </div>
                                )}
                            </div>

                            {!n.leida && (
                                <button
                                    type="button"
                                    onClick={() => marcarUnaLeida(n)}
                                    className="btn-icono text-primario hover:bg-primario-suave shrink-0"
                                    title="Marcar como leída"
                                    aria-label="Marcar como leída"
                                >
                                    <Check size={16} />
                                </button>
                            )}
                        </div>
                    ))}

                    {meta.last_page > 1 && (
                        <nav className="tarjeta px-4 py-3 flex items-center justify-between text-sm" aria-label="Paginación de notificaciones">
                            <span className="text-texto-suave">
                                Página {meta.current_page} de {meta.last_page} · {meta.total} notificaciones
                            </span>
                            <div className="flex gap-1">
                                <button
                                    type="button"
                                    className="btn btn-secundario !px-2.5 !py-1"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => setPagina((p) => p - 1)}
                                    aria-label="Página anterior"
                                >
                                    <ChevronLeft size={16} />
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-secundario !px-2.5 !py-1"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => setPagina((p) => p + 1)}
                                    aria-label="Página siguiente"
                                >
                                    <ChevronRight size={16} />
                                </button>
                            </div>
                        </nav>
                    )}
                </div>
            )}
        </div>
    );
}
