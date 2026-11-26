import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CheckCircle2, Eye, Pencil, Plus, Search, Trash2, XCircle } from 'lucide-react';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia from '../../components/Insignia';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { limpiarOpciones, useListado, useOpciones } from '../../hooks/useListado';
import { api, erroresCampos, mensajeError } from '../../lib/api';
import { fecha, moneda } from '../../lib/formato';

const ESTADOS = [
    { id: 'pendiente', nombre: 'Pendiente', tono: 'aviso' },
    { id: 'aprobada', nombre: 'Aprobada', tono: 'exito' },
    { id: 'rechazada', nombre: 'Rechazada', tono: 'peligro' },
    { id: 'vencida', nombre: 'Vencida', tono: 'neutro' },
];
const MAPA_ESTADO = Object.fromEntries(ESTADOS.map((e) => [e.id, e]));

const FILA_VACIA = { tipoItem: 'tarifa', id_tarifa: '', id_paquete: '', cantidad: '1', frecuencia: '' };

export default function Proformas() {
    const { usuario, puede } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const listado = useListado('/proformas', { estado: '' });
    const { opciones: empresas } = useOpciones('empresas');
    const { opciones: tarifas } = useOpciones('tarifas');
    const { opciones: paquetes } = useOpciones('paquetes');

    const [modo, setModo] = useState(null); // null | 'crear' | 'editar'
    const [actual, setActual] = useState(null);
    const [detalleId, setDetalleId] = useState(null);
    const [form, setForm] = useState({ id_empresa: '', fecha_validez: '', descuento: '0', detalles: [{ ...FILA_VACIA }] });
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const esCliente = usuario?.rol === 'Cliente';
    const puedeDescuento = puede('proformas.descuento');

    const precioTarifa = Object.fromEntries(tarifas.map((t) => [String(t.id), Number(t.monto ?? 0)]));
    const precioPaquete = Object.fromEntries(paquetes.map((p) => [String(p.id), Number(p.monto ?? 0)]));

    const subtotal = form.detalles.reduce((acc, d) => {
        const pu = d.tipoItem === 'tarifa' ? (precioTarifa[String(d.id_tarifa)] ?? 0) : (precioPaquete[String(d.id_paquete)] ?? 0);
        return acc + pu * Math.max(0, Number(d.cantidad) || 0);
    }, 0);
    const descuento = puedeDescuento ? Math.max(0, Number(form.descuento) || 0) : 0;
    const total = Math.max(0, subtotal - descuento);

    const abrirCrear = () => {
        const validez = new Date(Date.now() + 15 * 86400000).toISOString().slice(0, 10);
        setActual(null);
        setForm({ id_empresa: '', fecha_validez: validez, descuento: '0', detalles: [{ ...FILA_VACIA }] });
        setErrores({});
        setErrorGeneral(null);
        setModo('crear');
    };

    const abrirEditar = async (p) => {
        try {
            const { data } = await api.get(`/proformas/${p.id_proforma}`);
            const full = data.data;
            setActual(full);
            setForm({
                id_empresa: full.id_empresa ? String(full.id_empresa) : '',
                fecha_validez: full.fecha_validez ?? '',
                descuento: String(full.descuento ?? 0),
                detalles: (full.detalles ?? []).map((d) => ({
                    tipoItem: d.id_paquete ? 'paquete' : 'tarifa',
                    id_tarifa: d.id_tarifa ? String(d.id_tarifa) : '',
                    id_paquete: d.id_paquete ? String(d.id_paquete) : '',
                    cantidad: String(d.cantidad),
                    frecuencia: d.frecuencia ?? '',
                })),
            });
            setErrores({});
            setErrorGeneral(null);
            setModo('editar');
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const cambiarDetalle = (idx, campo, valor) => {
        setForm((f) => ({
            ...f,
            detalles: f.detalles.map((d, i) => {
                if (i !== idx) return d;
                if (campo === 'tipoItem') return { ...d, tipoItem: valor, id_tarifa: '', id_paquete: '' };
                return { ...d, [campo]: valor };
            }),
        }));
    };

    const guardar = async (e) => {
        e.preventDefault();
        setErrorGeneral(null);
        const locales = {};
        if (modo === 'crear' && !esCliente && !form.id_empresa) {
            locales.id_empresa = 'Seleccione la empresa anunciante.';
        }
        if (!form.detalles.length || form.detalles.some((d) => (d.tipoItem === 'tarifa' ? !d.id_tarifa : !d.id_paquete) || Number(d.cantidad) < 1)) {
            locales.detalles = 'Complete una tarifa o paquete y una cantidad válida (mínimo 1) en cada fila.';
        }
        if (puedeDescuento && Number(form.descuento) > subtotal) {
            locales.descuento = 'El descuento no puede superar el subtotal.';
        }
        setErrores(locales);
        if (Object.keys(locales).length) {
            setErrorGeneral('Revise los campos marcados en rojo.');
            return;
        }

        const payload = {
            ...(modo === 'crear' && !esCliente ? { id_empresa: Number(form.id_empresa) } : {}),
            fecha_validez: form.fecha_validez || null,
            ...(puedeDescuento ? { descuento: Number(form.descuento || 0) } : {}),
            detalles: form.detalles.map((d) => ({
                id_tarifa: d.tipoItem === 'tarifa' ? Number(d.id_tarifa) : null,
                id_paquete: d.tipoItem === 'paquete' ? Number(d.id_paquete) : null,
                cantidad: Number(d.cantidad),
                frecuencia: d.frecuencia || null,
            })),
        };

        setEnviando(true);
        try {
            const { data } = modo === 'crear'
                ? await api.post('/proformas', payload)
                : await api.put(`/proformas/${actual.id_proforma}`, payload);
            exito(data.message);
            setModo(null);
            limpiarOpciones();
            listado.recargar();
        } catch (err) {
            setErrores(erroresCampos(err));
            setErrorGeneral(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    const eliminar = async (p) => {
        const ok = await confirmar(`¿Eliminar la proforma ${p.nro_proforma}? Esta acción no se puede deshacer.`, {
            titulo: 'Eliminar proforma',
            textoAceptar: 'Eliminar',
            peligro: true,
        });
        if (!ok) return;
        try {
            const { data } = await api.delete(`/proformas/${p.id_proforma}`);
            exito(data.message);
            limpiarOpciones();
            listado.recargar();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const columnas = [
        { titulo: 'Número', campo: 'nro_proforma', className: 'font-semibold whitespace-nowrap' },
        {
            titulo: 'Titular',
            render: (p) => (
                <div>
                    <p className="font-medium">{p.empresa?.razon_social ?? p.visitante?.nombre ?? '—'}</p>
                    <p className="text-xs text-texto-suave">
                        {p.empresa ? `NIT ${p.empresa.nit}` : `Visitante · ${p.visitante?.email ?? ''}`}
                    </p>
                </div>
            ),
        },
        { titulo: 'Emisión', render: (p) => fecha(p.fecha_emision) },
        { titulo: 'Validez', render: (p) => fecha(p.fecha_validez) },
        { titulo: 'Ítems', campo: 'detalles_count', className: 'text-center' },
        {
            titulo: 'Total',
            render: (p) => (
                <div>
                    <p className="font-semibold">{moneda(p.total)}</p>
                    {Number(p.descuento) > 0 && <p className="text-xs text-exito">Desc. {moneda(p.descuento)}</p>}
                </div>
            ),
        },
        {
            titulo: 'Estado',
            render: (p) => {
                const m = MAPA_ESTADO[p.estado];
                const pago = p.situacion_pago;
                return (
                    <div className="space-y-1">
                        <Insignia tono={m?.tono ?? 'neutro'}>{m?.nombre ?? p.estado}</Insignia>
                        {pago && (
                            <p className={`text-xs ${pago.habilita_pautas ? 'text-exito' : 'text-aviso'}`} title={pago.detalle}>
                                {{ pagada: 'Pagada', al_dia: 'Cuotas al día', atrasado: 'Cuotas vencidas', sin_pago: 'Sin pagar' }[pago.estado] ?? pago.estado}
                            </p>
                        )}
                    </div>
                );
            },
        },
        {
            titulo: <span className="sr-only">Acciones</span>,
            className: 'text-right whitespace-nowrap',
            render: (p) => (
                <div className="flex justify-end gap-1">
                    <button className="btn-icono" onClick={() => setDetalleId(p.id_proforma)} title="Ver detalle" aria-label="Ver detalle">
                        <Eye size={16} />
                    </button>
                    {p.estado === 'pendiente' && puede('proformas.aprobar') && (
                        <Link to={`/contratar/${p.id_proforma}`} className="btn btn-primario !py-1 !px-2.5 text-xs" title="Completar la contratación para aprobar la proforma">
                            Contratar
                        </Link>
                    )}
                    {p.estado === 'pendiente' && puede('proformas.editar') && (
                        <button className="btn-icono" onClick={() => abrirEditar(p)} title="Editar" aria-label="Editar proforma">
                            <Pencil size={16} />
                        </button>
                    )}
                    {p.estado !== 'aprobada' && puede('proformas.eliminar') && (
                        <button className="btn-icono hover:!text-peligro" onClick={() => eliminar(p)} title="Eliminar" aria-label="Eliminar proforma">
                            <Trash2 size={16} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div>
            <EncabezadoPagina titulo="Proformas" descripcion="Cotizaciones con numeración PRO-AAAA-NNNNN, control de vigencia, descuentos y aprobación.">
                {puede('proformas.crear') && (
                    <button className="btn btn-primario" onClick={abrirCrear}>
                        <Plus size={16} /> Nueva proforma
                    </button>
                )}
            </EncabezadoPagina>

            <div className="flex flex-col sm:flex-row gap-2 mb-4">
                <label className="relative flex-1">
                    <span className="sr-only">Buscar</span>
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" aria-hidden />
                    <input className="input !pl-9" placeholder="Buscar por número, empresa o visitante…" value={listado.filtros.q} onChange={(e) => listado.setFiltro('q', e.target.value)} />
                </label>
                <label className="sm:w-48">
                    <span className="sr-only">Estado</span>
                    <select className="input" value={listado.filtros.estado} onChange={(e) => listado.setFiltro('estado', e.target.value)}>
                        <option value="">Estado: todos</option>
                        {ESTADOS.map((e) => <option key={e.id} value={e.id}>{e.nombre}</option>)}
                    </select>
                </label>
            </div>

            {listado.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{listado.error}</p>}

            <Tabla columnas={columnas} filas={listado.filas} cargando={listado.cargando} meta={listado.meta} onPagina={listado.setPagina} idFila="id_proforma" />

            <Modal
                titulo={modo === 'crear' ? 'Nueva proforma' : `Editar proforma ${actual?.nro_proforma ?? ''}`}
                abierto={modo !== null}
                onCerrar={() => setModo(null)}
                ancho="max-w-3xl"
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModo(null)}>Cancelar</button>
                        <button type="submit" form="form-proforma" className="btn btn-primario" disabled={enviando}>
                            {enviando ? 'Guardando…' : 'Guardar proforma'}
                        </button>
                    </>
                }
            >
                <form id="form-proforma" onSubmit={guardar} noValidate className="space-y-4">
                    {errorGeneral && <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{errorGeneral}</p>}

                    <div className="grid sm:grid-cols-3 gap-4">
                        {modo === 'crear' && !esCliente && (
                            <div className="sm:col-span-2">
                                <Campo
                                    etiqueta="Empresa anunciante"
                                    nombre="id_empresa"
                                    tipo="select"
                                    opciones={empresas}
                                    valor={form.id_empresa}
                                    onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))}
                                    error={errores.id_empresa}
                                    requerido
                                />
                            </div>
                        )}
                        <Campo
                            etiqueta="Válida hasta"
                            nombre="fecha_validez"
                            tipo="date"
                            valor={form.fecha_validez}
                            onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))}
                            error={errores.fecha_validez}
                        />
                        {puedeDescuento && (
                            <Campo
                                etiqueta="Descuento (Bs)"
                                nombre="descuento"
                                tipo="number"
                                valor={form.descuento}
                                onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))}
                                error={errores.descuento}
                                min={0}
                                step="0.01"
                            />
                        )}
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <span className="etiqueta !mb-0">Ítems de la proforma <span className="text-peligro">*</span></span>
                            <button type="button" className="btn btn-secundario !py-1 !px-2.5 text-xs" onClick={() => setForm((f) => ({ ...f, detalles: [...f.detalles, { ...FILA_VACIA }] }))}>
                                <Plus size={14} /> Agregar ítem
                            </button>
                        </div>
                        {errores.detalles && <p className="texto-error mb-2" role="alert">{errores.detalles}</p>}
                        <div className="space-y-2">
                            {form.detalles.map((d, idx) => (
                                <div key={idx} className="grid grid-cols-12 gap-2 items-center">
                                    <select className="input col-span-3" value={d.tipoItem} onChange={(e) => cambiarDetalle(idx, 'tipoItem', e.target.value)}>
                                        <option value="tarifa">Tarifa</option>
                                        <option value="paquete">Paquete</option>
                                    </select>
                                    {d.tipoItem === 'tarifa' ? (
                                        <select className="input col-span-5" value={d.id_tarifa} onChange={(e) => cambiarDetalle(idx, 'id_tarifa', e.target.value)}>
                                            <option value="">Seleccione tarifa…</option>
                                            {tarifas.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                        </select>
                                    ) : (
                                        <select className="input col-span-5" value={d.id_paquete} onChange={(e) => cambiarDetalle(idx, 'id_paquete', e.target.value)}>
                                            <option value="">Seleccione paquete…</option>
                                            {paquetes.map((p) => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                        </select>
                                    )}
                                    <input
                                        type="number"
                                        min={1}
                                        className="input col-span-2"
                                        placeholder="Cant."
                                        value={d.cantidad}
                                        onChange={(e) => cambiarDetalle(idx, 'cantidad', e.target.value)}
                                    />
                                    <input
                                        type="text"
                                        className="input col-span-1"
                                        placeholder="Frec."
                                        title="Frecuencia (ej. diario, semanal)"
                                        value={d.frecuencia}
                                        onChange={(e) => cambiarDetalle(idx, 'frecuencia', e.target.value)}
                                    />
                                    <div className="col-span-1 flex justify-end">
                                        {form.detalles.length > 1 && (
                                            <button type="button" className="btn-icono hover:!text-peligro" onClick={() => setForm((f) => ({ ...f, detalles: f.detalles.filter((_, i) => i !== idx) }))}>
                                                <Trash2 size={16} />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="bg-superficie-2 rounded-tema p-3 text-sm flex flex-wrap justify-between gap-2">
                        <span>Subtotal: <strong>{moneda(subtotal)}</strong></span>
                        <span>Descuento: <strong>-{moneda(descuento)}</strong></span>
                        <span className="text-primario font-bold">Total: {moneda(total)}</span>
                    </div>
                </form>
            </Modal>

            {detalleId && (
                <DetalleProformaModal
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

function DetalleProformaModal({ id, onCerrar, onCambio }) {
    const { puede, usuario } = useAuth();
    const esCliente = usuario?.rol === 'Cliente';
    const { exito, error, confirmar } = useAvisos();
    const [p, setP] = useState(null);

    useEffect(() => {
        api.get(`/proformas/${id}`).then(({ data }) => setP(data.data)).catch((e) => error(mensajeError(e)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    const resolverEstado = async (nuevoEstado) => {
        const esAprobar = nuevoEstado === 'aprobada';
        const ok = await confirmar(
            esAprobar
                ? `¿Aprobar la proforma ${p.nro_proforma}? Se abrirá su campaña y quedará lista para pagarla y cargar el material.`
                : `¿Rechazar la proforma ${p.nro_proforma}?`,
            { titulo: esAprobar ? 'Aprobar proforma' : 'Rechazar proforma', textoAceptar: esAprobar ? 'Aprobar' : 'Rechazar', peligro: !esAprobar },
        );
        if (!ok) return;
        try {
            const { data } = await api.patch(`/proformas/${id}/estado`, { estado: nuevoEstado });
            setP(data.data);
            exito(data.message);
            onCambio();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const meta = MAPA_ESTADO[p?.estado];

    return (
        <Modal
            titulo={p ? `Proforma ${p.nro_proforma}` : 'Detalle de proforma'}
            abierto
            onCerrar={onCerrar}
            ancho="max-w-3xl"
            pie={
                p?.estado === 'pendiente' && puede('proformas.aprobar') && (
                    <>
                        <button className="btn btn-peligro" onClick={() => resolverEstado('rechazada')}>
                            <XCircle size={16} /> Rechazar
                        </button>
                        {/* Se aprueba solo completando la contratación: campaña, material, horarios y pago */}
                        <Link to={`/contratar/${p.id_proforma}`} className="btn btn-primario">
                            <CheckCircle2 size={16} /> {esCliente ? 'Contratar' : 'Contratar en nombre del cliente'}
                        </Link>
                    </>
                )
            }
        >
            {!p ? (
                <p className="text-texto-suave">Cargando…</p>
            ) : (
                <div className="space-y-4 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2 bg-superficie-2 rounded-tema p-3">
                        <div>
                            <p className="font-semibold text-base">{p.empresa?.razon_social ?? p.visitante?.nombre ?? 'Sin titular'}</p>
                            <p className="text-xs text-texto-suave">
                                {p.empresa ? `NIT ${p.empresa.nit}` : `Visitante: ${p.visitante?.email ?? ''} ${p.visitante?.telefono ?? ''}`}
                            </p>
                        </div>
                        <Insignia tono={meta?.tono ?? 'neutro'}>{meta?.nombre ?? p.estado}</Insignia>
                    </div>

                    <div className="grid sm:grid-cols-3 gap-2 text-xs">
                        <div>Emisión: <strong>{fecha(p.fecha_emision)}</strong></div>
                        <div>Validez: <strong>{fecha(p.fecha_validez)}</strong></div>
                        <div>Campaña: <strong>{p.campania?.nombre ?? 'Sin campaña'}</strong></div>
                    </div>

                    {p.estado === 'aprobada' && p.situacion_pago && (
                        <div className={`rounded-tema border p-3 text-xs space-y-2 ${p.situacion_pago.habilita_pautas ? 'border-exito/40 bg-exito/5' : 'border-aviso/40 bg-aviso/5'}`}>
                            <p>
                                <strong>Pago:</strong> {p.situacion_pago.detalle}
                            </p>
                            <p className="text-texto-suave">
                                Siguientes pasos: pago → carga del material → revisión de Canal 11 → programación de pautas → emisión.
                                Las pautas solo se programan con la proforma pagada (o con las cuotas al día) y el material aprobado.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {!p.situacion_pago.habilita_pautas && puede('pagos.crear') && (
                                    <>
                                        <Link to="/pagos" className="btn btn-primario !py-1 !px-2.5 text-xs">Pagar al contado</Link>
                                        {puede('planes_pago.crear') && <Link to="/planes-pago" className="btn btn-secundario !py-1 !px-2.5 text-xs">Pagar en cuotas</Link>}
                                    </>
                                )}
                                {p.campania && puede('materiales.ver') && (
                                    <Link to={`/materiales?q=${encodeURIComponent(p.campania.nombre)}`} className="btn btn-secundario !py-1 !px-2.5 text-xs">Material de la campaña</Link>
                                )}
                                {p.campania && puede('pautas.ver') && (
                                    <Link to="/pautas" className="btn btn-secundario !py-1 !px-2.5 text-xs">Pautas</Link>
                                )}
                            </div>
                        </div>
                    )}

                    <div className="overflow-x-auto border border-borde rounded-tema">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-superficie-2 text-texto-suave">
                                <tr>
                                    <th className="p-2">Concepto</th>
                                    <th className="p-2">Frecuencia</th>
                                    <th className="p-2 text-center">Cant.</th>
                                    <th className="p-2 text-right">P. Unit.</th>
                                    <th className="p-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-borde">
                                {(p.detalles ?? []).map((d) => (
                                    <tr key={d.id_detalle}>
                                        <td className="p-2 font-medium">
                                            {d.paquete
                                                ? `Paquete: ${d.paquete.nombre}`
                                                : `${d.tarifa?.servicio?.nombre ?? 'Tarifa'} (${d.tarifa?.unidad_cobro ?? 'unidad'})`}
                                        </td>
                                        <td className="p-2">{d.frecuencia ?? '—'}</td>
                                        <td className="p-2 text-center">{d.cantidad}</td>
                                        <td className="p-2 text-right">{moneda(d.precio_unitario)}</td>
                                        <td className="p-2 text-right font-semibold">{moneda(d.subtotal)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-col items-end gap-1 pt-2 border-t border-borde">
                        <p>Subtotal: <strong>{moneda(p.subtotal)}</strong></p>
                        <p>Descuento: <strong>-{moneda(p.descuento)}</strong></p>
                        <p className="text-base text-primario font-bold">Total: {moneda(p.total)}</p>
                    </div>
                </div>
            )}
        </Modal>
    );
}

