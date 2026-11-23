import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Activity, LogIn, ShieldX, UserX } from 'lucide-react';
import { api, mensajeError } from '../../lib/api';
import { useListado } from '../../hooks/useListado';
import { fechaHora, numero } from '../../lib/formato';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Insignia from '../../components/Insignia';

const EVENTOS = {
    login_ok: ['Inicio de sesión', 'exito'],
    login_fallido: ['Intento fallido', 'peligro'],
    logout: ['Cierre de sesión', 'neutro'],
    acceso: ['Acceso', 'primario'],
    denegado: ['Acceso denegado', 'aviso'],
    error: ['Error', 'peligro'],
};

const TONO_ICONO = {
    primario: 'bg-primario/15 text-primario',
    exito: 'bg-exito/15 text-exito',
    peligro: 'bg-peligro/15 text-peligro',
    aviso: 'bg-aviso/15 text-aviso',
};

function Indicador({ icono: I, titulo, valor, tono }) {
    return (
        <div className="tarjeta p-4 flex items-center gap-3">
            <span className={`size-11 rounded-tema flex items-center justify-center ${TONO_ICONO[tono]}`}>
                <I size={22} aria-hidden />
            </span>
            <div>
                <p className="text-2xl font-bold">{numero(valor)}</p>
                <p className="text-xs text-texto-suave">{titulo}</p>
            </div>
        </div>
    );
}

export default function Bitacora() {
    const [resumen, setResumen] = useState(null);
    const [errorResumen, setErrorResumen] = useState(null);
    const l = useListado('/bitacora', { evento: '', ip: '', desde: '', hasta: '' });

    useEffect(() => {
        api.get('/bitacora/resumen', { params: { dias: 14 } })
            .then(({ data }) => setResumen(data.data))
            .catch((e) => setErrorResumen(mensajeError(e)));
    }, []);

    const t = resumen?.totales ?? {};

    return (
        <div>
            <EncabezadoPagina titulo="Bitácora de auditoría" descripcion="Inicios de sesión, intentos fallidos, direcciones IP y recursos más consultados (últimos 14 días)." />
            {errorResumen && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{errorResumen}</p>}

            <div className="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-5">
                <Indicador icono={Activity} titulo="Accesos a la API" valor={t.accesos} tono="primario" />
                <Indicador icono={LogIn} titulo="Inicios de sesión" valor={t.logins_ok} tono="exito" />
                <Indicador icono={UserX} titulo="Intentos fallidos" valor={t.logins_fallidos} tono="peligro" />
                <Indicador icono={ShieldX} titulo="Accesos denegados" valor={t.denegados} tono="aviso" />
            </div>

            <div className="grid gap-4 lg:grid-cols-3 mb-6">
                <section className="tarjeta p-4 lg:col-span-2">
                    <h2 className="font-semibold mb-3">Sesiones por día</h2>
                    <div className="h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={resumen?.por_dia ?? []} margin={{ left: -20 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="var(--borde)" />
                                <XAxis dataKey="dia" tickFormatter={(d) => d.slice(8, 10) + '/' + d.slice(5, 7)} tick={{ fill: 'var(--texto-suave)', fontSize: 12 }} />
                                <YAxis allowDecimals={false} tick={{ fill: 'var(--texto-suave)', fontSize: 12 }} />
                                <Tooltip contentStyle={{ background: 'var(--superficie)', border: '1px solid var(--borde)', color: 'var(--texto)' }} />
                                <Legend />
                                <Bar dataKey="logins_ok" name="Exitosos" fill="var(--exito)" radius={[4, 4, 0, 0]} />
                                <Bar dataKey="logins_fallidos" name="Fallidos" fill="var(--peligro)" radius={[4, 4, 0, 0]} />
                                <Bar dataKey="denegados" name="Denegados" fill="var(--aviso)" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </section>
                <section className="tarjeta p-4">
                    <h2 className="font-semibold mb-3">Direcciones IP más activas</h2>
                    <ul className="text-sm divide-y divide-borde">
                        {(resumen?.top_ips ?? []).map((i) => (
                            <li key={i.ip} className="py-2 flex justify-between gap-2">
                                <code>{i.ip}</code>
                                <span className="text-texto-suave">
                                    {numero(i.total)} {i.fallidos > 0 && <span className="text-peligro">({i.fallidos} fallidos)</span>}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <section className="mb-6">
                <h2 className="font-semibold mb-3">Recursos con mayor índice de acceso</h2>
                <Tabla
                    idFila="recurso"
                    filas={resumen?.top_recursos ?? []}
                    cargando={!resumen && !errorResumen}
                    columnas={[
                        { titulo: 'Recurso', render: (r) => <code className="text-xs">{r.recurso}</code> },
                        { titulo: 'Accesos', render: (r) => numero(r.total), className: 'text-right' },
                        { titulo: 'Último acceso', render: (r) => fechaHora(r.ultimo_acceso) },
                    ]}
                />
            </section>

            <h2 className="font-semibold mb-3">Registro de eventos</h2>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5 mb-4">
                <input className="input" placeholder="Correo o recurso…" value={l.filtros.q} onChange={(e) => l.setFiltro('q', e.target.value)} aria-label="Buscar por correo o recurso" />
                <select className="input" value={l.filtros.evento} onChange={(e) => l.setFiltro('evento', e.target.value)} aria-label="Evento">
                    <option value="">Todos los eventos</option>
                    {Object.entries(EVENTOS).map(([k, [n]]) => <option key={k} value={k}>{n}</option>)}
                </select>
                <input className="input" placeholder="IP" value={l.filtros.ip} onChange={(e) => l.setFiltro('ip', e.target.value)} aria-label="Dirección IP" />
                <input className="input" type="date" value={l.filtros.desde} onChange={(e) => l.setFiltro('desde', e.target.value)} aria-label="Desde" />
                <input className="input" type="date" value={l.filtros.hasta} onChange={(e) => l.setFiltro('hasta', e.target.value)} aria-label="Hasta" />
            </div>
            {l.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{l.error}</p>}
            <Tabla
                idFila="id_bitacora"
                filas={l.filas}
                cargando={l.cargando}
                meta={l.meta}
                onPagina={l.setPagina}
                columnas={[
                    { titulo: 'Fecha', render: (b) => <span className="whitespace-nowrap">{fechaHora(b.fecha)}</span> },
                    { titulo: 'Evento', render: (b) => <Insignia tono={EVENTOS[b.evento]?.[1]}>{EVENTOS[b.evento]?.[0] ?? b.evento}</Insignia> },
                    { titulo: 'Usuario', render: (b) => b.usuario?.nombre_completo ?? b.email ?? <span className="text-texto-suave">Anónimo</span> },
                    { titulo: 'Recurso', render: (b) => <code className="text-xs">{b.recurso ?? '—'}</code> },
                    { titulo: 'HTTP', campo: 'codigo_http' },
                    { titulo: 'IP', render: (b) => <code className="text-xs">{b.ip}</code> },
                    { titulo: 'Detalle', render: (b) => b.detalle ?? (b.duracion_ms != null ? `${b.duracion_ms} ms` : '—') },
                ]}
            />
        </div>
    );
}
