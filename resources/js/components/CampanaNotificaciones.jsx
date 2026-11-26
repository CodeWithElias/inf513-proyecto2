import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Bell, Check, CheckCheck, Loader2 } from 'lucide-react';
import { api, mensajeError } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import { useAvisos } from '../context/AvisosContext';
import { fechaHora } from '../lib/formato';

const CADA_MS = 60_000;
const ULTIMAS = 6;

/** Otras pantallas (ej. /notificaciones) avisan cuando cambian las notificaciones con esta función. */
export function avisarCambioNotificaciones() {
    window.dispatchEvent(new Event('notificaciones-cambiaron'));
}

/**
 * Campana del encabezado: número de notificaciones sin leer y lista desplegable de las últimas.
 * El número se actualiza al entrar, al cambiar de pantalla, cada minuto y al marcarlas como leídas.
 */
export default function CampanaNotificaciones() {
    const { puede, usuario } = useAuth();
    const { error } = useAvisos();
    const { pathname } = useLocation();
    const habilitada = puede('notificaciones.ver');
    const puedeMarcar = puede('notificaciones.editar');

    const [noLeidas, setNoLeidas] = useState(0);
    const [abierto, setAbierto] = useState(false);
    const [lista, setLista] = useState([]);
    const [cargando, setCargando] = useState(false);
    const ref = useRef(null);

    const contar = useCallback(async () => {
        try {
            const { data } = await api.get('/notificaciones/contador');
            setNoLeidas(Number(data.data?.no_leidas ?? 0));
        } catch {
            /* sin conexión: se mantiene el último número */
        }
    }, []);

    const cargarLista = useCallback(async () => {
        setCargando(true);
        try {
            // Siempre las del propio usuario (el Director puede ver las de todos en la pantalla completa)
            const { data } = await api.get('/notificaciones', { params: { id_usuario: usuario?.id_usuario, per_page: ULTIMAS } });
            setLista((data.data ?? []).slice(0, ULTIMAS));
        } catch (e) {
            error(mensajeError(e));
        } finally {
            setCargando(false);
        }
    }, [usuario?.id_usuario, error]);

    // Al entrar y al cambiar de pantalla
    useEffect(() => {
        if (habilitada) contar();
        setAbierto(false);
    }, [habilitada, pathname, contar]);

    // Cada minuto y cuando otra pantalla avisa un cambio
    useEffect(() => {
        if (!habilitada) return undefined;
        const id = setInterval(contar, CADA_MS);
        const alCambiar = () => {
            contar();
            if (abierto) cargarLista();
        };
        window.addEventListener('notificaciones-cambiaron', alCambiar);
        return () => {
            clearInterval(id);
            window.removeEventListener('notificaciones-cambiaron', alCambiar);
        };
    }, [habilitada, abierto, contar, cargarLista]);

    // Cerrar con clic afuera o Escape
    useEffect(() => {
        if (!abierto) return undefined;
        const cerrar = (e) => ref.current && !ref.current.contains(e.target) && setAbierto(false);
        const esc = (e) => e.key === 'Escape' && setAbierto(false);
        document.addEventListener('mousedown', cerrar);
        document.addEventListener('keydown', esc);
        return () => {
            document.removeEventListener('mousedown', cerrar);
            document.removeEventListener('keydown', esc);
        };
    }, [abierto]);

    if (!habilitada) return null;

    const alternar = () => {
        if (!abierto) cargarLista();
        setAbierto((a) => !a);
    };

    const marcarLeida = async (n) => {
        try {
            await api.patch(`/notificaciones/${n.id_notificacion}/leida`);
            setLista((prev) => prev.map((x) => (x.id_notificacion === n.id_notificacion ? { ...x, leida: true } : x)));
            setNoLeidas((c) => Math.max(0, c - 1));
        } catch (e) {
            error(mensajeError(e));
        }
    };

    const marcarTodas = async () => {
        try {
            await api.post('/notificaciones/marcar-todas');
            setLista((prev) => prev.map((x) => ({ ...x, leida: true })));
            setNoLeidas(0);
        } catch (e) {
            error(mensajeError(e));
        }
    };

    const etiqueta = noLeidas ? `Notificaciones: ${noLeidas} sin leer` : 'Notificaciones: ninguna sin leer';
    const numero = noLeidas > 99 ? '99+' : noLeidas;

    return (
        <div className="relative" ref={ref}>
            <button type="button" className="btn-icono relative" onClick={alternar} aria-expanded={abierto} aria-haspopup="dialog" aria-label={etiqueta} title={etiqueta}>
                <Bell size={21} />
                {noLeidas > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-peligro text-white text-[10px] font-bold leading-[18px] text-center ring-2 ring-superficie" aria-hidden>
                        {numero}
                    </span>
                )}
            </button>
            <span className="sr-only" aria-live="polite">{noLeidas ? `${noLeidas} notificaciones sin leer` : ''}</span>

            {abierto && (
                <div className="tarjeta absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] z-50 overflow-hidden" role="dialog" aria-label="Notificaciones">
                    <div className="flex items-center justify-between px-4 py-3 border-b border-borde">
                        <p className="font-semibold text-sm">
                            Notificaciones {noLeidas > 0 && <span className="text-texto-suave font-normal">· {noLeidas} sin leer</span>}
                        </p>
                        {puedeMarcar && noLeidas > 0 && (
                            <button type="button" className="text-xs text-primario hover:underline inline-flex items-center gap-1" onClick={marcarTodas}>
                                <CheckCheck size={14} aria-hidden /> Marcar todas
                            </button>
                        )}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto">
                        {cargando && !lista.length ? (
                            <p className="p-6 text-center text-sm text-texto-suave">
                                <Loader2 size={16} className="inline animate-spin mr-1" aria-hidden /> Cargando…
                            </p>
                        ) : !lista.length ? (
                            <div className="p-6 text-center text-sm text-texto-suave">
                                <Bell size={28} className="mx-auto mb-2 opacity-40" aria-hidden />
                                No tiene notificaciones.
                            </div>
                        ) : (
                            <ul className="divide-y divide-borde">
                                {lista.map((n) => (
                                    <li key={n.id_notificacion} className={`px-4 py-3 flex gap-3 ${n.leida ? 'opacity-70' : 'bg-primario/5'}`}>
                                        <span className={`mt-1.5 w-2 h-2 rounded-full shrink-0 ${n.leida ? 'bg-transparent' : 'bg-primario'}`} aria-hidden />
                                        <div className="flex-1 min-w-0">
                                            <p className={`text-sm leading-snug ${n.leida ? '' : 'font-semibold'}`}>{n.titulo}</p>
                                            <p className="text-xs text-texto-suave mt-0.5 line-clamp-2">{n.mensaje}</p>
                                            <p className="text-[11px] text-texto-suave mt-1">{fechaHora(n.fecha_envio)}</p>
                                        </div>
                                        {puedeMarcar && !n.leida && (
                                            <button type="button" className="btn-icono shrink-0 self-start text-primario" onClick={() => marcarLeida(n)} aria-label={`Marcar como leída: ${n.titulo}`} title="Marcar como leída">
                                                <Check size={15} />
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <Link to="/notificaciones" className="block px-4 py-2.5 text-center text-sm font-medium text-primario hover:bg-superficie-2 border-t border-borde">
                        Ver todas las notificaciones
                    </Link>
                </div>
            )}
        </div>
    );
}
