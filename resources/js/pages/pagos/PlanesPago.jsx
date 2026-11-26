import { useEffect, useState } from 'react';
import { CreditCard, Eye, Plus, Search } from 'lucide-react';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia from '../../components/Insignia';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { limpiarOpciones, useListado, useOpciones } from '../../hooks/useListado';
import { api, erroresCampos, mensajeError } from '../../lib/api';
import { pagarConVerificacion } from '../../lib/stripe';
import { fecha, fechaHora, moneda } from '../../lib/formato';

const FRECUENCIAS = [
    { id: 'mensual', nombre: 'Mensual' },
    { id: 'quincenal', nombre: 'Quincenal' },
    { id: 'semanal', nombre: 'Semanal' },
];
const TONO_PLAN = { activo: 'primario', completado: 'exito', cancelado: 'peligro' };
const TONO_CUOTA = { pendiente: 'aviso', pagada: 'exito', vencida: 'peligro' };

export default function PlanesPago() {
    const { puede } = useAuth();
    const { exito } = useAvisos();
    const listado = useListado('/planes-pago', { estado: '' });
    const { opciones: proformas } = useOpciones('proformas-pagables');

    const [abierto, setAbierto] = useState(false);
    const [detalleId, setDetalleId] = useState(null);
    const [form, setForm] = useState({
        id_proforma: '',
        nro_cuotas: '3',
        frecuencia: 'mensual',
        fecha_inicio: new Date().toISOString().slice(0, 10),
    });
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const proformaSel = proformas.find((p) => String(p.id) === String(form.id_proforma));
    const cuotasNum = Math.max(2, Math.min(24, Number(form.nro_cuotas) || 2));
    const cuotaAprox = proformaSel ? Number(proformaSel.monto) / cuotasNum : 0;

    const crearPlan = async (e) => {
        e.preventDefault();
        setErrorGeneral(null);
        const locales = {};
        if (!form.id_proforma) locales.id_proforma = 'Seleccione la proforma.';
        const n = Number(form.nro_cuotas);
        if (!Number.isInteger(n) || n < 2 || n > 24) locales.nro_cuotas = 'El número de cuotas debe estar entre 2 y 24.';
        setErrores(locales);
        if (Object.keys(locales).length) return;

        setEnviando(true);
        try {
            const { data } = await api.post('/planes-pago', {
                id_proforma: Number(form.id_proforma),
                nro_cuotas: n,
                frecuencia: form.frecuencia,
                fecha_inicio: form.fecha_inicio || null,
            });
            exito(data.message);
            setAbierto(false);
            limpiarOpciones();
            listado.recargar();
        } catch (err) {
            setErrores(erroresCampos(err));
            setErrorGeneral(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    const columnas = [
        { titulo: 'Plan', render: (p) => `#${p.id_plan}`, className: 'w-16 font-semibold' },
        {
            titulo: 'Proforma / Empresa',
            render: (p) => (
                <div>
                    <p className="font-medium">{p.proforma?.nro_proforma ?? '—'}</p>
                    <p className="text-xs text-texto-suave">{p.proforma?.empresa?.razon_social ?? p.usuario?.nombre_completo ?? '—'}</p>
                </div>
            ),
        },
        { titulo: 'Monto total', render: (p) => <span className="font-semibold">{moneda(p.monto_total)}</span> },
        {
            titulo: 'Progreso',
            render: (p) => {
                const pagadas = (p.cuotas ?? []).filter((c) => c.estado === 'pagada').length;
                return `${pagadas} / ${p.nro_cuotas} cuotas (${p.frecuencia})`;
            },
        },
        { titulo: 'Inicio', render: (p) => fecha(p.fecha_inicio) },
        { titulo: 'Estado', render: (p) => <Insignia tono={TONO_PLAN[p.estado]}>{p.estado}</Insignia> },
        {
            titulo: <span className="sr-only">Acciones</span>,
            className: 'text-right',
            render: (p) => (
                <button className="btn-icono" onClick={() => setDetalleId(p.id_plan)} title="Ver cuotas y pagar" aria-label="Ver cuotas">
                    <Eye size={16} />
                </button>
            ),
        },
    ];

    return (
        <div>
            <EncabezadoPagina titulo="Planes de pago en cuotas" descripcion="Divida una proforma aprobada en cuotas y registre el pago de cada cuota.">
                {puede('planes_pago.crear') && (
                    <button
                        className="btn btn-primario"
                        onClick={() => {
                            setForm({ id_proforma: '', nro_cuotas: '3', frecuencia: 'mensual', fecha_inicio: new Date().toISOString().slice(0, 10) });
                            setErrores({});
                            setErrorGeneral(null);
                            setAbierto(true);
                        }}
                    >
                        <Plus size={16} /> Nuevo plan
                    </button>
                )}
            </EncabezadoPagina>

            <div className="flex flex-col sm:flex-row gap-2 mb-4">
                <label className="relative flex-1">
                    <span className="sr-only">Buscar</span>
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" aria-hidden />
                    <input className="input !pl-9" placeholder="Buscar por número de proforma…" value={listado.filtros.q} onChange={(e) => listado.setFiltro('q', e.target.value)} />
                </label>
                <label className="sm:w-48">
                    <span className="sr-only">Estado</span>
                    <select className="input" value={listado.filtros.estado} onChange={(e) => listado.setFiltro('estado', e.target.value)}>
                        <option value="">Estado: todos</option>
                        <option value="activo">Activos</option>
                        <option value="completado">Completados</option>
                        <option value="cancelado">Cancelados</option>
                    </select>
                </label>
            </div>

            {listado.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{listado.error}</p>}

            <Tabla columnas={columnas} filas={listado.filas} cargando={listado.cargando} meta={listado.meta} onPagina={listado.setPagina} idFila="id_plan" />

            <Modal
                titulo="Nuevo plan de pagos"
                abierto={abierto}
                onCerrar={() => setAbierto(false)}
                ancho="max-w-lg"
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setAbierto(false)}>Cancelar</button>
                        <button type="submit" form="form-plan" className="btn btn-primario" disabled={enviando}>
                            {enviando ? 'Creando plan…' : 'Generar cuotas'}
                        </button>
                    </>
                }
            >
                <form id="form-plan" onSubmit={crearPlan} noValidate className="space-y-4">
                    {errorGeneral && <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{errorGeneral}</p>}
                    <Campo
                        etiqueta="Proforma aprobada"
                        nombre="id_proforma"
                        tipo="select"
                        opciones={proformas}
                        valor={form.id_proforma}
                        onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))}
                        error={errores.id_proforma}
                        vacio={proformas.length ? 'Seleccione una proforma…' : 'No hay proformas disponibles'}
                        requerido
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Campo etiqueta="Número de cuotas" nombre="nro_cuotas" tipo="number" min={2} max={24} valor={form.nro_cuotas} onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))} error={errores.nro_cuotas} requerido />
                        <Campo etiqueta="Frecuencia" nombre="frecuencia" tipo="select" opciones={FRECUENCIAS} valor={form.frecuencia} onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))} requerido />
                    </div>
                    <Campo etiqueta="Fecha de primera cuota" nombre="fecha_inicio" tipo="date" valor={form.fecha_inicio} onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))} />

                    {proformaSel && (
                        <div className="bg-superficie-2 rounded-tema p-3 text-sm space-y-1">
                            <p>Monto total: <strong>{moneda(proformaSel.monto)}</strong></p>
                            <p className="text-primario font-semibold">Cuota aproximada: {moneda(cuotaAprox)} × {cuotasNum}</p>
                        </div>
                    )}
                </form>
            </Modal>

            {detalleId && (
                <DetallePlanModal
                    id={detalleId}
                    onCerrar={() => setDetalleId(null)}
                    onCambio={() => {
                        limpiarOpciones();
                        listado.recargar();
                    }}
                />
            )}
        </div>
    );
}

function DetallePlanModal({ id, onCerrar, onCambio }) {
    const { puede } = useAuth();
    const { exito, error } = useAvisos();
    const { opciones: metodos } = useOpciones('metodos-pago');
    const [plan, setPlan] = useState(null);
    const [idMetodo, setIdMetodo] = useState('');
    const [pagandoId, setPagandoId] = useState(null);

    const cargar = () => {
        api.get(`/planes-pago/${id}`).then(({ data }) => setPlan(data.data)).catch((e) => error(mensajeError(e)));
    };

    useEffect(() => {
        cargar();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        if (!idMetodo && metodos.length) {
            const pred = metodos.find((m) => m.predeterminado) ?? metodos[0];
            setIdMetodo(String(pred.id));
        }
    }, [metodos, idMetodo]);

    const pagarCuota = async (cuota) => {
        if (!idMetodo) {
            error('Seleccione un método de pago antes de continuar.');
            return;
        }
        setPagandoId(cuota.id_cuota);
        try {
            const { data } = await pagarConVerificacion((extra) => api.post(`/cuotas/${cuota.id_cuota}/pagar`, { id_metodo: Number(idMetodo), ...extra }));
            if (data.data.estado === 'aprobada') {
                exito(data.message);
                cargar();
                onCambio();
            } else {
                error(data.message);
            }
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setPagandoId(null);
        }
    };

    return (
        <Modal titulo={plan ? `Plan #${plan.id_plan} · ${plan.proforma?.nro_proforma ?? ''}` : 'Detalle del plan'} abierto onCerrar={onCerrar} ancho="max-w-2xl">
            {!plan ? (
                <p className="text-texto-suave">Cargando…</p>
            ) : (
                <div className="space-y-4 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2 bg-superficie-2 rounded-tema p-3">
                        <div>
                            <p className="font-semibold">{plan.proforma?.nro_proforma} · {plan.proforma?.empresa?.razon_social ?? plan.usuario?.nombre_completo}</p>
                            <p className="text-xs text-texto-suave">Total: {moneda(plan.monto_total)} · {plan.nro_cuotas} cuotas ({plan.frecuencia})</p>
                        </div>
                        <Insignia tono={TONO_PLAN[plan.estado]}>{plan.estado}</Insignia>
                    </div>

                    {plan.estado === 'activo' && puede('pagos.crear') && (
                        <Campo
                            etiqueta="Método de pago para abonar cuotas"
                            nombre="id_metodo"
                            tipo="select"
                            opciones={metodos}
                            valor={idMetodo}
                            onChange={(_, v) => setIdMetodo(v)}
                            vacio={metodos.length ? 'Seleccione un método…' : 'Sin métodos de pago registrados'}
                        />
                    )}

                    <div className="overflow-x-auto border border-borde rounded-tema">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-superficie-2 text-texto-suave">
                                <tr>
                                    <th className="p-2">Cuota</th>
                                    <th className="p-2">Vencimiento</th>
                                    <th className="p-2 text-right">Monto</th>
                                    <th className="p-2">Estado</th>
                                    <th className="p-2 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-borde">
                                {(plan.cuotas ?? []).map((c) => (
                                    <tr key={c.id_cuota}>
                                        <td className="p-2 font-semibold">#{c.nro}</td>
                                        <td className="p-2">{fecha(c.fecha_vencimiento)}</td>
                                        <td className="p-2 text-right font-semibold">{moneda(c.monto)}</td>
                                        <td className="p-2">
                                            <Insignia tono={TONO_CUOTA[c.estado]}>{c.estado}</Insignia>
                                            {c.fecha_pago && <span className="block text-[11px] text-texto-suave">{fechaHora(c.fecha_pago)}</span>}
                                        </td>
                                        <td className="p-2 text-right">
                                            {c.estado !== 'pagada' && plan.estado === 'activo' && puede('pagos.crear') ? (
                                                <button
                                                    type="button"
                                                    className="btn btn-primario !py-1 !px-2.5 text-xs"
                                                    disabled={pagandoId === c.id_cuota}
                                                    onClick={() => pagarCuota(c)}
                                                >
                                                    <CreditCard size={13} /> {pagandoId === c.id_cuota ? 'Pagando…' : 'Pagar'}
                                                </button>
                                            ) : (
                                                <span className="text-texto-suave">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </Modal>
    );
}

