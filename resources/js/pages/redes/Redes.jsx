import { useEffect, useState } from 'react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ExternalLink, TrendingUp } from 'lucide-react';
import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { useFormulario } from '../../hooks/useFormulario';
import { api, mensajeError } from '../../lib/api';
import { fecha, numero } from '../../lib/formato';

export default function Redes() {
    const { puede, usuario } = useAuth();
    const [red, setRed] = useState(null);

    return (
        <>
            <PaginaCrud
                titulo="Redes sociales institucionales"
                descripcion={
                    usuario.rol === 'Encargado de Unidad'
                        ? 'Redes oficiales de su unidad o a su cargo.'
                        : 'Cuentas oficiales de cada unidad universitaria, con su responsable de contenido.'
                }
                singular="red social"
                endpoint="/redes"
                privilegio="redes"
                idCampo="id_canal"
                placeholderBusqueda="Buscar por nombre o URL…"
                filtros={[FILTRO_ESTADO, { nombre: 'id_plataforma', etiqueta: 'Plataforma', opciones: 'plataformas' }, { nombre: 'id_unidad', etiqueta: 'Unidad', opciones: 'unidades' }]}
                columnas={[
                    {
                        titulo: 'Red social',
                        render: (r) => (
                            <div>
                                <p className="font-medium">{r.canal?.nombre}</p>
                                <a href={r.url} target="_blank" rel="noreferrer" className="text-xs text-primario hover:underline inline-flex items-center gap-1 break-all">
                                    {r.url} <ExternalLink size={12} aria-hidden />
                                    <span className="sr-only">(se abre en otra pestaña)</span>
                                </a>
                            </div>
                        ),
                    },
                    { titulo: 'Plataforma', render: (r) => r.plataforma?.nombre },
                    { titulo: 'Unidad', render: (r) => r.unidad?.nombre ?? '—' },
                    { titulo: 'Responsable', render: (r) => r.responsable?.nombre_completo ?? '—' },
                    { titulo: 'Seguidores', render: (r) => numero(r.ultimos_seguidores), className: 'text-right' },
                    { titulo: 'Estado', render: (r) => <InsigniaActivo activo={r.canal?.activo} /> },
                ]}
                campos={[
                    { nombre: 'nombre', etiqueta: 'Nombre de la cuenta', reglas: { requerido: true, min: 2, max: 150 } },
                    { nombre: 'url', etiqueta: 'Dirección (URL)', tipo: 'url', placeholder: 'https://www.facebook.com/…', reglas: { requerido: true, url: true, max: 500 } },
                    { nombre: 'id_plataforma', etiqueta: 'Plataforma', tipo: 'select', opciones: 'plataformas', ancho: 'medio', reglas: { requerido: true } },
                    { nombre: 'id_unidad', etiqueta: 'Unidad universitaria', tipo: 'select', opciones: 'unidades', ancho: 'medio', vacio: 'Sin unidad' },
                    { nombre: 'id_usuario', etiqueta: 'Responsable de contenido', tipo: 'select', opciones: 'usuarios', vacio: 'Sin responsable' },
                ]}
                aFormulario={(r) => ({
                    nombre: r.canal?.nombre ?? '',
                    url: r.url,
                    id_plataforma: String(r.id_plataforma),
                    id_unidad: r.id_unidad ? String(r.id_unidad) : '',
                    id_usuario: r.id_usuario ? String(r.id_usuario) : '',
                })}
                estado={{ activo: (r) => r.canal?.activo, endpoint: (r) => `/redes/${r.id_canal}/estado` }}
                acciones={(r) =>
                    puede('seguidores.ver') && (
                        <button className="btn-icono" onClick={() => setRed(r)} title="Seguidores" aria-label={`Seguidores de ${r.canal?.nombre}`}>
                            <TrendingUp size={16} />
                        </button>
                    )
                }
            />
            {red && <ModalSeguidores red={red} onCerrar={() => setRed(null)} />}
        </>
    );
}

function ModalSeguidores({ red, onCerrar }) {
    const { puede } = useAuth();
    const { exito } = useAvisos();
    const [datos, setDatos] = useState([]);
    const [error, setError] = useState(null);
    const f = useFormulario({ seguidores: '', fecha: new Date().toISOString().slice(0, 10) });

    const cargar = () =>
        api.get(`/redes/${red.id_canal}/seguidores`)
            .then(({ data }) => setDatos(data.data))
            .catch((e) => setError(mensajeError(e)));

    useEffect(() => {
        cargar();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [red.id_canal]);

    const registrar = async (e) => {
        e.preventDefault();
        const r = await f.enviar((v) => api.post(`/redes/${red.id_canal}/seguidores`, v), {
            seguidores: { requerido: true, entero: true, minValor: 0, maxValor: 999999999, etiqueta: 'cantidad de seguidores' },
            fecha: { fecha: true, hastaHoy: true, etiqueta: 'fecha' },
        });
        if (r) {
            exito(r.data.message);
            f.reiniciar({ seguidores: '', fecha: new Date().toISOString().slice(0, 10) });
            cargar();
        }
    };

    return (
        <Modal titulo={`Seguidores · ${red.canal?.nombre}`} abierto onCerrar={onCerrar} ancho="max-w-2xl">
            {error && <p className="text-peligro text-sm mb-3" role="alert">{error}</p>}
            {datos.length === 0 ? (
                <p className="text-texto-suave text-sm mb-4">Aún no hay mediciones de seguidores para esta red.</p>
            ) : (
                <div className="h-56 mb-4" role="img" aria-label={`Evolución de seguidores: último valor ${numero(datos.at(-1).seguidores)}`}>
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={datos} margin={{ left: 0, right: 10 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="var(--borde)" />
                            <XAxis dataKey="fecha" tickFormatter={fecha} tick={{ fill: 'var(--texto-suave)', fontSize: 12 }} />
                            <YAxis tickFormatter={numero} tick={{ fill: 'var(--texto-suave)', fontSize: 12 }} width={70} />
                            <Tooltip labelFormatter={fecha} formatter={(v) => [numero(v), 'Seguidores']} contentStyle={{ background: 'var(--superficie)', border: '1px solid var(--borde)' }} />
                            <Line type="monotone" dataKey="seguidores" stroke="var(--primario)" strokeWidth={2.5} dot={{ r: 3 }} />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            )}

            {puede('seguidores.crear') && red.canal?.activo && (
                <form onSubmit={registrar} noValidate className="grid gap-3 sm:grid-cols-3 items-start border-t border-borde pt-4">
                    {f.errorGeneral && <p className="sm:col-span-3 text-sm text-peligro" role="alert">{f.errorGeneral}</p>}
                    <Campo etiqueta="Seguidores" nombre="seguidores" tipo="number" min={0} valor={f.valores.seguidores} onChange={f.cambiar} error={f.errores.seguidores} requerido />
                    <Campo etiqueta="Fecha" nombre="fecha" tipo="date" valor={f.valores.fecha} onChange={f.cambiar} error={f.errores.fecha} max={new Date().toISOString().slice(0, 10)} />
                    <button type="submit" className="btn btn-primario sm:mt-6" disabled={f.enviando}>Registrar medición</button>
                </form>
            )}
        </Modal>
    );
}
