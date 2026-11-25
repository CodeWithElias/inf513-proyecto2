import { useState, useEffect, useCallback } from 'react';
import {
    DollarSign,
    Briefcase,
    FileCheck,
    Radio,
    ShieldAlert,
    TrendingUp,
    Users,
    Activity,
    Calendar,
    Eye,
    BarChart2,
    Lock,
    Tv,
    Loader2,
} from 'lucide-react';
import { api, mensajeError } from '../../lib/api';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Insignia from '../../components/Insignia';
import { moneda, numero, fecha } from '../../lib/formato';

export default function Dashboard() {
    const { error } = useAvisos();

    const [pestana, setPestana] = useState('negocio'); // 'negocio' | 'seguridad'
    const [cargando, setCargando] = useState(true);

    // Datos de Negocio
    const [datosNegocio, setDatosNegocio] = useState({
        kpis: {
            ingresos_totales: 0,
            campanias_activas: 0,
            proformas_aprobadas: 0,
            pautas_emitidas: 0,
            material_aprobado: 0,
            tasa_ocupacion: 0,
        },
        ingresos_mensuales: [],
        proformas_por_estado: [],
        canales_ocupacion: [],
        top_servicios: [],
        impacto: { alcance: 0, impresiones: 0, interacciones: 0, clics: 0 },
    });

    // Datos de Auditoría / Bitácora
    const [datosBitacora, setDatosBitacora] = useState({
        dias: 14,
        totales: { login_ok: 0, login_fallido: 0, logout: 0, acceso: 0, denegado: 0, error: 0 },
        por_dia: [],
        top_recursos: [],
        top_ips: [],
    });

    const cargarEstadisticas = useCallback(async () => {
        setCargando(true);
        try {
            const [rNegocio, rBitacora] = await Promise.all([
                api.get('/estadisticas/negocio'),
                api.get('/bitacora/resumen?dias=14'),
            ]);
            setDatosNegocio(rNegocio.data?.data || {});
            setDatosBitacora(rBitacora.data?.data || {});
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setCargando(false);
        }
    }, [error]);

    useEffect(() => {
        cargarEstadisticas();
    }, [cargarEstadisticas]);

    if (cargando) {
        return (
            <div className="tarjeta p-16 text-center text-texto-suave">
                <Loader2 className="inline animate-spin mr-2" size={24} />
                <p className="mt-2 text-sm">Cargando métricas y estadísticas del sistema...</p>
            </div>
        );
    }

    const { kpis, ingresos_mensuales, proformas_por_estado, canales_ocupacion, top_servicios, impacto } = datosNegocio;
    const maxIngreso = Math.max(...ingresos_mensuales.map((m) => m.total), 1);

    return (
        <div className="space-y-6">
            <EncabezadoPagina
                titulo="Panel de estadísticas"
                descripcion="Monitoreo consolidado de ingresos, pautas, métricas de impacto y actividad de acceso."
                accion={
                    <button type="button" className="btn btn-secundario text-xs" onClick={cargarEstadisticas}>
                        <Activity size={14} /> Actualizar datos
                    </button>
                }
            />

            {/* Selector de Pestaña */}
            <div className="flex items-center gap-2 border-b border-borde pb-2">
                <button
                    type="button"
                    onClick={() => setPestana('negocio')}
                    className={`flex items-center gap-2 px-4 py-2 rounded-tema text-sm font-semibold transition-colors ${
                        pestana === 'negocio'
                            ? 'bg-primario text-primario-texto shadow-sm'
                            : 'hover:bg-superficie-2 text-texto-suave'
                    }`}
                >
                    <TrendingUp size={16} /> Rendimiento de negocio y pautas
                </button>
                <button
                    type="button"
                    onClick={() => setPestana('seguridad')}
                    className={`flex items-center gap-2 px-4 py-2 rounded-tema text-sm font-semibold transition-colors ${
                        pestana === 'seguridad'
                            ? 'bg-primario text-primario-texto shadow-sm'
                            : 'hover:bg-superficie-2 text-texto-suave'
                    }`}
                >
                    <ShieldAlert size={16} /> Auditoría y tráfico de accesos
                </button>
            </div>

            {pestana === 'negocio' ? (
                <div className="space-y-6">
                    {/* Tarjetas KPI Superiores */}
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div className="tarjeta p-4 flex items-center gap-3.5">
                            <div className="p-3 rounded-tema bg-exito/15 text-exito">
                                <DollarSign size={24} />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-texto-suave font-semibold uppercase">Total cobrado</p>
                                <p className="text-xl font-bold text-texto truncate">{moneda(kpis?.ingresos_totales)}</p>
                            </div>
                        </div>

                        <div className="tarjeta p-4 flex items-center gap-3.5">
                            <div className="p-3 rounded-tema bg-primario-suave text-primario">
                                <Briefcase size={24} />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-texto-suave font-semibold uppercase">Campañas activas</p>
                                <p className="text-xl font-bold text-texto">{numero(kpis?.campanias_activas)}</p>
                            </div>
                        </div>

                        <div className="tarjeta p-4 flex items-center gap-3.5">
                            <div className="p-3 rounded-tema bg-superficie-2 text-texto">
                                <Radio size={24} />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-texto-suave font-semibold uppercase">Pautas emitidas</p>
                                <p className="text-xl font-bold text-texto">{numero(kpis?.pautas_emitidas)}</p>
                            </div>
                        </div>

                        <div className="tarjeta p-4 flex items-center gap-3.5">
                            <div className="p-3 rounded-tema bg-aviso/15 text-aviso">
                                <Tv size={24} />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-texto-suave font-semibold uppercase">Ocupación canales</p>
                                <p className="text-xl font-bold text-texto">{kpis?.tasa_ocupacion}%</p>
                            </div>
                        </div>
                    </div>

                    {/* Gráficos de Negocio */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Ingresos Mensuales */}
                        <div className="tarjeta p-5 space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <BarChart2 size={18} className="text-primario" /> Ingresos mensuales (Bs)
                                </h3>
                                <span className="text-xs text-texto-suave">Últimos meses</span>
                            </div>

                            {ingresos_mensuales.length === 0 ? (
                                <p className="text-xs text-texto-suave py-8 text-center italic">
                                    No hay registros de pagos completados en el período.
                                </p>
                            ) : (
                                <div className="space-y-3 pt-2">
                                    {ingresos_mensuales.map((m) => {
                                        const pct = Math.max(5, Math.round((m.total / maxIngreso) * 100));
                                        return (
                                            <div key={m.mes} className="space-y-1">
                                                <div className="flex justify-between text-xs">
                                                    <span className="font-medium text-texto">{m.mes}</span>
                                                    <span className="font-bold text-texto">{moneda(m.total)}</span>
                                                </div>
                                                <div className="w-full h-3 bg-superficie-2 rounded-full overflow-hidden">
                                                    <div
                                                        className="h-full bg-primario rounded-full transition-all duration-500"
                                                        style={{ width: `${pct}%` }}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Proformas por Estado */}
                        <div className="tarjeta p-5 space-y-4">
                            <h3 className="font-semibold text-base flex items-center gap-2">
                                <FileCheck size={18} className="text-primario" /> Distribución de proformas
                            </h3>

                            {proformas_por_estado.length === 0 ? (
                                <p className="text-xs text-texto-suave py-8 text-center italic">
                                    No hay proformas emitidas.
                                </p>
                            ) : (
                                <div className="space-y-3 pt-2">
                                    {proformas_por_estado.map((p) => (
                                        <div
                                            key={p.estado}
                                            className="flex items-center justify-between p-3 rounded-tema bg-superficie-2/50 border border-borde/50"
                                        >
                                            <div className="flex items-center gap-2.5">
                                                <Insignia
                                                    tono={
                                                        p.estado === 'aprobada'
                                                            ? 'exito'
                                                            : p.estado === 'rechazada'
                                                            ? 'peligro'
                                                            : 'aviso'
                                                    }
                                                >
                                                    {p.estado}
                                                </Insignia>
                                                <span className="text-xs text-texto-suave">
                                                    {p.cantidad} {p.cantidad === 1 ? 'proforma' : 'proformas'}
                                                </span>
                                            </div>
                                            <span className="font-bold text-sm text-texto">{moneda(p.monto)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Fila Inferior: Ocupación de Canales y Top Servicios */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Ocupación por Canal */}
                        <div className="tarjeta p-5 space-y-4">
                            <h3 className="font-semibold text-base flex items-center gap-2">
                                <Tv size={18} className="text-primario" /> Ocupación de canales
                            </h3>
                            <div className="space-y-3">
                                {canales_ocupacion.map((c) => {
                                    const total = Number(c.total_espacios) || 0;
                                    const ocupados = Number(c.ocupados) || 0;
                                    const pct = total > 0 ? Math.round((ocupados / total) * 100) : 0;
                                    return (
                                        <div key={c.canal} className="space-y-1">
                                            <div className="flex justify-between text-xs">
                                                <span className="font-medium text-texto">
                                                    {c.canal} <span className="text-texto-suave uppercase">({c.tipo})</span>
                                                </span>
                                                <span className="text-texto-suave">
                                                    {ocupados}/{total} ocupados ({pct}%)
                                                </span>
                                            </div>
                                            <div className="w-full h-2.5 bg-superficie-2 rounded-full overflow-hidden">
                                                <div
                                                    className="h-full bg-primario rounded-full"
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Top Servicios y Métricas de Redes */}
                        <div className="space-y-6">
                            {/* Top Servicios */}
                            <div className="tarjeta p-5 space-y-3">
                                <h3 className="font-semibold text-base">Top 5 servicios más contratados</h3>
                                <div className="divide-y divide-borde/60">
                                    {top_servicios.map((s, idx) => (
                                        <div key={s.nombre} className="py-2 flex items-center justify-between text-sm">
                                            <span className="font-medium text-texto">
                                                <span className="text-primario font-bold mr-1.5">#{idx + 1}</span>
                                                {s.nombre}
                                            </span>
                                            <div className="text-right">
                                                <span className="font-bold text-xs">{s.contrataciones} contratos</span>
                                                <p className="text-[11px] text-texto-suave">{moneda(s.volumen_bs)}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Métricas de impacto acumuladas */}
                            <div className="tarjeta p-5 bg-gradient-to-r from-primario-suave/40 to-superficie border border-primario/20">
                                <h3 className="font-semibold text-sm mb-3 text-texto flex items-center gap-2">
                                    <Eye size={16} className="text-primario" /> Impacto consolidado en audiencias
                                </h3>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                                    <div className="p-2 bg-superficie rounded-tema border border-borde">
                                        <p className="text-[11px] text-texto-suave font-semibold">Alcance</p>
                                        <p className="text-base font-bold text-texto">{numero(impacto?.alcance)}</p>
                                    </div>
                                    <div className="p-2 bg-superficie rounded-tema border border-borde">
                                        <p className="text-[11px] text-texto-suave font-semibold">Impresiones</p>
                                        <p className="text-base font-bold text-texto">{numero(impacto?.impresiones)}</p>
                                    </div>
                                    <div className="p-2 bg-superficie rounded-tema border border-borde">
                                        <p className="text-[11px] text-texto-suave font-semibold">Interacciones</p>
                                        <p className="text-base font-bold text-texto">{numero(impacto?.interacciones)}</p>
                                    </div>
                                    <div className="p-2 bg-superficie rounded-tema border border-borde">
                                        <p className="text-[11px] text-texto-suave font-semibold">Clics</p>
                                        <p className="text-base font-bold text-texto">{numero(impacto?.clics)}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ) : (
                /* Pestaña: Tráfico y Seguridad (Bitácora) */
                <div className="space-y-6">
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div className="tarjeta p-4 flex items-center gap-3">
                            <div className="p-3 rounded-tema bg-exito/15 text-exito">
                                <Users size={22} />
                            </div>
                            <div>
                                <p className="text-xs text-texto-suave font-semibold uppercase">Inicios de sesión exitosos</p>
                                <p className="text-xl font-bold text-texto">{numero(datosBitacora.totales?.login_ok)}</p>
                            </div>
                        </div>

                        <div className="tarjeta p-4 flex items-center gap-3">
                            <div className="p-3 rounded-tema bg-peligro/15 text-peligro">
                                <Lock size={22} />
                            </div>
                            <div>
                                <p className="text-xs text-texto-suave font-semibold uppercase">Intentos fallidos</p>
                                <p className="text-xl font-bold text-texto">{numero(datosBitacora.totales?.login_fallido)}</p>
                            </div>
                        </div>

                        <div className="tarjeta p-4 flex items-center gap-3">
                            <div className="p-3 rounded-tema bg-primario-suave text-primario">
                                <Activity size={22} />
                            </div>
                            <div>
                                <p className="text-xs text-texto-suave font-semibold uppercase">Peticiones totales (14d)</p>
                                <p className="text-xl font-bold text-texto">{numero(datosBitacora.totales?.acceso)}</p>
                            </div>
                        </div>

                        <div className="tarjeta p-4 flex items-center gap-3">
                            <div className="p-3 rounded-tema bg-aviso/15 text-aviso">
                                <ShieldAlert size={22} />
                            </div>
                            <div>
                                <p className="text-xs text-texto-suave font-semibold uppercase">Accesos denegados (403)</p>
                                <p className="text-xl font-bold text-texto">{numero(datosBitacora.totales?.denegado)}</p>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Actividad diaria */}
                        <div className="tarjeta p-5 space-y-4">
                            <h3 className="font-semibold text-base flex items-center gap-2">
                                <Calendar size={18} className="text-primario" /> Actividad por día (Últimos 14 días)
                            </h3>
                            <div className="space-y-2">
                                {datosBitacora.por_dia?.map((d) => (
                                    <div
                                        key={d.dia}
                                        className="flex items-center justify-between text-xs p-2 rounded bg-superficie-2/40"
                                    >
                                        <span className="font-medium text-texto">{fecha(d.dia)}</span>
                                        <div className="flex gap-4">
                                            <span className="text-exito font-medium">{d.logins} logins</span>
                                            <span className="font-bold text-texto">{d.accesos} accesos</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Top Recursos y Top IPs */}
                        <div className="space-y-6">
                            <div className="tarjeta p-5 space-y-3">
                                <h3 className="font-semibold text-base">Top 5 endpoints más consultados</h3>
                                <div className="divide-y divide-borde/60">
                                    {datosBitacora.top_recursos?.map((r, i) => (
                                        <div key={r.recurso} className="py-2 flex items-center justify-between text-xs">
                                            <span className="font-mono text-texto truncate max-w-xs">
                                                {i + 1}. {r.recurso}
                                            </span>
                                            <span className="font-bold">{r.accesos} peticiones</span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="tarjeta p-5 space-y-3">
                                <h3 className="font-semibold text-base">Direcciones IP con mayor actividad</h3>
                                <div className="divide-y divide-borde/60">
                                    {datosBitacora.top_ips?.map((ip) => (
                                        <div key={ip.ip} className="py-2 flex items-center justify-between text-xs">
                                            <span className="font-mono text-texto">{ip.ip}</span>
                                            <span className="font-bold">{ip.accesos} solicitudes</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
