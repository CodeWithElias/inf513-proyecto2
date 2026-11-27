import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Check, CalendarDays, CreditCard, Film, Flag, Loader2, Eye, UploadCloud, Clock, AlertTriangle, PartyPopper } from 'lucide-react';
import { api, erroresCampos, mensajeError } from '../../lib/api';
import { pagarConVerificacion } from '../../lib/stripe';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia from '../../components/Insignia';
import { fecha, fechaHora, moneda } from '../../lib/formato';
import { validar } from '../../lib/validar';
import { VisorMaterial, validarArchivo, leerDuracion, MAX_MB, tipoPorArchivo, formatosDe, describirFormatos } from '../operacion/ArchivoMaterial';

const PASOS = [
    { clave: 'campania', titulo: 'Campaña', icono: Flag },
    { clave: 'material', titulo: 'Material', icono: Film },
    { clave: 'agenda', titulo: 'Días y horarios', icono: CalendarDays },
    { clave: 'pago', titulo: 'Pago', icono: CreditCard },
];
const NOMBRE_TIPO = { video: 'Video', audio: 'Audio', imagen: 'Imagen', banner: 'Banner', texto: 'Texto' };
const sumarDias = (d) => new Date(Date.now() + d * 86400e3).toISOString().slice(0, 10);

/**
 * Contratación guiada de una proforma: campaña → material de cada servicio → días y horarios → pago.
 * Es la única forma de aprobarla; hasta terminar, la proforma sigue pendiente con el avance guardado.
 */
export default function Contratar() {
    const { id } = useParams();
    const [c, setC] = useState(null);
    const [error, setError] = useState(null);
    const [paso, setPaso] = useState(0);
    const [final, setFinal] = useState(null);

    const cargar = useCallback(async (irAlPendiente = false) => {
        try {
            const { data } = await api.get(`/contrataciones/${id}`);
            setC(data.data);
            if (irAlPendiente) {
                const i = data.data.pasos.findIndex((p) => !p.hecho);
                setPaso(i === -1 ? 3 : i);
            }
        } catch (e) {
            setError(mensajeError(e));
        }
    }, [id]);

    useEffect(() => {
        cargar(true);
    }, [cargar]);

    if (error) return <p className="tarjeta p-6 text-peligro" role="alert">{error}</p>;
    if (!c) return <p className="tarjeta p-6 text-texto-suave"><Loader2 className="inline animate-spin mr-2" size={18} />Cargando la contratación…</p>;

    const hecho = (i) => c.pasos[i]?.hecho;
    const habilitado = (i) => i === 0 || PASOS.slice(0, i).every((_, j) => hecho(j));
    const ir = (i) => habilitado(i) && setPaso(i);
    const alGuardar = (estado, avanzar = true) => {
        setC(estado);
        if (avanzar) {
            const i = estado.pasos.findIndex((p) => !p.hecho);
            setPaso(i === -1 ? 3 : i);
        }
    };

    return (
        <div className="space-y-4 max-w-5xl">
            <EncabezadoPagina
                titulo={`Contratar ${c.proforma.nro_proforma}`}
                descripcion={`${c.proforma.cliente ?? ''} · Total ${moneda(c.proforma.total)}${c.proforma.fecha_validez ? ` · Válida hasta ${fecha(c.proforma.fecha_validez)}` : ''}`}
            >
                <Link to="/proformas" className="btn btn-secundario">Volver a proformas</Link>
            </EncabezadoPagina>

            {final ? (
                <Confirmada final={final} />
            ) : !c.puede_editar ? (
                <p className="tarjeta p-5" role="status">
                    Esta proforma está <strong>{c.proforma.estado}</strong>; la contratación solo se hace sobre proformas pendientes.
                </p>
            ) : (
                <>
                    {c.requiere_empresa && (
                        <div className="tarjeta p-4 border-l-4 border-l-aviso flex flex-wrap items-center justify-between gap-3" role="alert">
                            <p>Para contratar primero registre su empresa: el material y las campañas quedan a su nombre.</p>
                            <Link to="/perfil" className="btn btn-primario">Registrar mi empresa</Link>
                        </div>
                    )}
                    <p className="text-sm text-texto-suave">
                        Complete los 4 pasos para aprobar la proforma. Su avance se guarda: puede salir y volver. <strong>Hasta pagar, la proforma sigue pendiente.</strong>
                    </p>

                    <ol className="tarjeta p-3 grid grid-cols-2 sm:grid-cols-4 gap-2" aria-label="Pasos de la contratación">
                        {PASOS.map((p, i) => {
                            const Icono = p.icono;
                            const activo = paso === i;
                            return (
                                <li key={p.clave}>
                                    <button
                                        type="button"
                                        onClick={() => ir(i)}
                                        disabled={!habilitado(i)}
                                        aria-current={activo ? 'step' : undefined}
                                        className={`w-full flex items-center gap-2 p-2 rounded-tema text-left text-sm transition-colors disabled:opacity-50 ${
                                            activo ? 'bg-primario text-white' : 'hover:bg-superficie-2'
                                        }`}
                                    >
                                        <span className={`size-7 shrink-0 rounded-full flex items-center justify-center ${hecho(i) ? 'bg-exito text-white' : activo ? 'bg-white/20' : 'bg-superficie-2'}`}>
                                            {hecho(i) ? <Check size={15} /> : <Icono size={15} />}
                                        </span>
                                        <span>
                                            <span className="block text-[11px] opacity-80">Paso {i + 1}</span>
                                            <span className="font-semibold">{p.titulo}</span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ol>

                    {paso === 0 && <PasoCampania c={c} alGuardar={alGuardar} />}
                    {paso === 1 && <PasoMaterial c={c} alGuardar={alGuardar} />}
                    {paso === 2 && <PasoAgenda c={c} alGuardar={alGuardar} />}
                    {paso === 3 && <PasoPago c={c} recargar={cargar} alConfirmar={setFinal} />}
                </>
            )}
        </div>
    );
}

// ---------------------------------------------------------------- Paso 1
function PasoCampania({ c, alGuardar }) {
    const { exito, error } = useAvisos();
    const [v, setV] = useState({
        nombre: c.campania?.nombre ?? `Campaña ${c.proforma.cliente ?? ''} · ${c.proforma.nro_proforma}`.trim(),
        fecha_inicio: c.campania?.fecha_inicio ?? sumarDias(1),
        fecha_fin: c.campania?.fecha_fin ?? sumarDias(30),
    });
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const cambiar = (k, val) => setV((x) => ({ ...x, [k]: val }));

    const guardar = async (e) => {
        e.preventDefault();
        const loc = validar(v, {
            nombre: { requerido: true, max: 200, etiqueta: 'nombre de la campaña' },
            fecha_inicio: { requerido: true, fecha: true, etiqueta: 'fecha de inicio' },
            fecha_fin: { requerido: true, fecha: true, etiqueta: 'fecha de fin' },
        });
        if (!loc.fecha_fin && v.fecha_fin < v.fecha_inicio) loc.fecha_fin = 'La fecha de fin no puede ser anterior a la de inicio.';
        if (!loc.fecha_inicio && v.fecha_inicio < sumarDias(0)) loc.fecha_inicio = 'La campaña no puede empezar en una fecha pasada.';
        setErrores(loc);
        if (Object.keys(loc).length) return;
        setEnviando(true);
        try {
            const { data } = await api.put(`/contrataciones/${c.proforma.id_proforma}/campania`, v);
            exito(data.message);
            alGuardar(data.data);
        } catch (err) {
            setErrores(erroresCampos(err));
            error(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <form onSubmit={guardar} noValidate className="tarjeta p-5 space-y-4">
            <div>
                <h2 className="text-lg font-semibold">1. Datos de la campaña</h2>
                <p className="text-sm text-texto-suave">Sus anuncios solo se pueden programar dentro de estas fechas.</p>
            </div>
            <Campo etiqueta="Nombre de la campaña" nombre="nombre" valor={v.nombre} onChange={cambiar} error={errores.nombre} requerido />
            <div className="grid sm:grid-cols-2 gap-3">
                <Campo etiqueta="Fecha de inicio" nombre="fecha_inicio" tipo="date" valor={v.fecha_inicio} onChange={cambiar} error={errores.fecha_inicio} requerido min={sumarDias(0)} />
                <Campo etiqueta="Fecha de fin" nombre="fecha_fin" tipo="date" valor={v.fecha_fin} onChange={cambiar} error={errores.fecha_fin} requerido min={v.fecha_inicio} />
            </div>
            <div className="flex justify-end">
                <button className="btn btn-primario" disabled={enviando}>{enviando ? 'Guardando…' : 'Guardar y continuar'}</button>
            </div>
        </form>
    );
}

// ---------------------------------------------------------------- Paso 2
function PasoMaterial({ c, alGuardar }) {
    const [viendo, setViendo] = useState(null);
    const listos = c.lineas.filter((l) => l.material).length;
    // Piezas ya cargadas en esta contratación (para reutilizarlas en otro servicio)
    const cargados = useMemo(() => {
        const m = new Map();
        c.lineas.forEach((l) => l.material && m.set(l.material.id_material, l.material));
        return [...m.values()];
    }, [c]);

    return (
        <section className="space-y-3">
            <div className="tarjeta p-4">
                <h2 className="text-lg font-semibold">2. Material de cada servicio</h2>
                <p className="text-sm text-texto-suave">
                    Cargue la pieza que se emitirá en cada servicio contratado ({listos} de {c.lineas.length} listos). Canal 11 la revisará después del pago; si pide
                    correcciones, se le avisará.
                </p>
            </div>
            {c.lineas.map((l) => (
                <LineaMaterial key={l.id_linea} c={c} l={l} cargados={cargados} alGuardar={alGuardar} alVer={setViendo} />
            ))}
            <Modal titulo={viendo ? viendo.nombre_archivo : ''} abierto={Boolean(viendo)} onCerrar={() => setViendo(null)} ancho="max-w-3xl">
                <VisorMaterial material={viendo} />
            </Modal>
        </section>
    );
}

function LineaMaterial({ c, l, cargados, alGuardar, alVer }) {
    const { exito, error } = useAvisos();
    const idArchivo = useId();
    const [editando, setEditando] = useState(!l.material);
    const [tipo, setTipo] = useState(l.tipos_material[0]);
    const [archivo, setArchivo] = useState(null);
    const [texto, setTexto] = useState('');
    const [duracion, setDuracion] = useState('');
    const [err, setErr] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const compatibles = cargados.filter((m) => l.tipos_material.includes(m.tipo) && m.id_material !== l.material?.id_material);

    const elegir = async (f) => {
        // El tipo de pieza se ajusta al archivo elegido (dentro de los tipos que admite este servicio)
        const nuevoTipo = tipoPorArchivo(f, tipo, l.tipos_material) ?? tipo;
        setTipo(nuevoTipo);
        setArchivo(f);
        setErr(f ? validarArchivo(f, nuevoTipo) : null);
        if (f && /^(video|audio)\//.test(f.type)) {
            const s = await leerDuracion(f);
            if (s) {
                setDuracion(String(s));
                if (l.duracion_seg && s > l.duracion_seg) setErr(`La pieza dura ${s} s y el servicio contratado es de ${l.duracion_seg} s.`);
            }
        }
    };

    const enviar = async (cuerpo) => {
        setEnviando(true);
        try {
            const { data } = await api.post(`/contrataciones/${c.proforma.id_proforma}/lineas/${l.id_linea}/material`, cuerpo);
            exito(data.message);
            setEditando(false);
            alGuardar(data.data, data.data.pasos[1].hecho);
        } catch (e) {
            setErr(mensajeError(e));
            error(mensajeError(e));
        } finally {
            setEnviando(false);
        }
    };

    const subir = (e) => {
        e.preventDefault();
        if (tipo === 'texto' && !archivo) {
            if (!texto.trim()) return setErr('Escriba el texto de la publicación o adjunte un archivo .txt o .pdf.');
            return enviar({ tipo, texto_publicacion: texto, nombre_archivo: 'publicacion.txt' });
        }
        if (!archivo) return setErr('Elija el archivo de la pieza.');
        const e2 = validarArchivo(archivo, tipo);
        if (e2) return setErr(e2);
        if (['video', 'audio'].includes(tipo) && !duracion) return setErr('Indique la duración de la pieza en segundos.');
        const fd = new FormData();
        fd.append('tipo', tipo);
        fd.append('archivo', archivo);
        if (duracion) fd.append('duracion_seg', duracion);
        if (texto.trim()) fd.append('texto_publicacion', texto);
        return enviar(fd);
    };

    return (
        <article className={`tarjeta p-4 border-l-4 ${l.material ? 'border-l-exito' : 'border-l-aviso'}`}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 className="font-semibold">{l.servicio}{l.paquete && <span className="text-texto-suave font-normal"> · {l.paquete}</span>}</h3>
                    <p className="text-xs text-texto-suave">
                        {l.cantidad} × {l.unidad_cobro ?? 'unidad'}{l.duracion_seg ? ` · pieza de hasta ${l.duracion_seg} s` : ''} · admite: {l.tipos_material.map((t) => NOMBRE_TIPO[t]).join(', ')}
                    </p>
                </div>
                {l.material ? <Insignia tono="exito">Material listo</Insignia> : <Insignia tono="aviso">Falta material</Insignia>}
            </div>

            {l.material && !editando && (
                <div className="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    <span className="font-medium break-all">{l.material.nombre_archivo}</span>
                    <span className="text-xs text-texto-suave">{NOMBRE_TIPO[l.material.tipo]}{l.material.duracion_seg ? ` · ${l.material.duracion_seg} s` : ''}</span>
                    <button type="button" className="text-primario text-xs hover:underline inline-flex items-center gap-1" onClick={() => alVer(l.material)}><Eye size={13} /> Ver</button>
                    <button type="button" className="text-primario text-xs hover:underline" onClick={() => setEditando(true)}>Cambiar</button>
                </div>
            )}

            {editando && (
                <form onSubmit={subir} noValidate className="mt-3 space-y-3">
                    {compatibles.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="text-texto-suave">Usar una pieza ya cargada:</span>
                            {compatibles.map((m) => (
                                <button key={m.id_material} type="button" className="btn btn-secundario !py-1 !px-2.5 text-xs" disabled={enviando} onClick={() => enviar({ id_material: m.id_material })}>
                                    {m.nombre_archivo}
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label className="etiqueta" htmlFor={`${idArchivo}-tipo`}>Tipo de pieza</label>
                            <select id={`${idArchivo}-tipo`} className="input" value={tipo} onChange={(e) => { setTipo(e.target.value); setArchivo(null); setErr(null); }}>
                                {l.tipos_material.map((t) => <option key={t} value={t}>{NOMBRE_TIPO[t]}</option>)}
                            </select>
                        </div>
                        <div className="sm:col-span-2">
                            <label className="etiqueta" htmlFor={idArchivo}>Archivo {tipo !== 'texto' && <span className="text-peligro" aria-hidden>*</span>}</label>
                            <input id={idArchivo} type="file" className="input" accept={formatosDe(l.tipos_material).map((x) => `.${x}`).join(',')} onChange={(e) => elegir(e.target.files?.[0] ?? null)} aria-describedby={`${idArchivo}-ayuda`} />
                            <p id={`${idArchivo}-ayuda`} className="text-xs text-texto-suave mt-1">Formatos: {describirFormatos(l.tipos_material)} · máximo {MAX_MB} MB</p>
                        </div>
                    </div>
                    {['video', 'audio'].includes(tipo) && (
                        <Campo etiqueta="Duración (segundos)" nombre="duracion" tipo="number" valor={duracion} onChange={(_, val) => setDuracion(val)} ayuda="Se completa sola al elegir el archivo." />
                    )}
                    <Campo etiqueta={tipo === 'texto' ? 'Texto de la publicación' : 'Texto / copy (opcional)'} nombre="texto" tipo="textarea" valor={texto} onChange={(_, val) => setTexto(val)} />
                    {err && <p className="texto-error" role="alert">{err}</p>}
                    <div className="flex justify-end gap-2">
                        {l.material && <button type="button" className="btn btn-secundario" onClick={() => setEditando(false)}>Cancelar</button>}
                        <button className="btn btn-primario" disabled={enviando}>
                            <UploadCloud size={16} /> {enviando ? 'Subiendo…' : 'Cargar pieza'}
                        </button>
                    </div>
                </form>
            )}
        </article>
    );
}

// ---------------------------------------------------------------- Paso 3
function PasoAgenda({ c, alGuardar }) {
    return (
        <section className="space-y-3">
            <div className="tarjeta p-4">
                <h2 className="text-lg font-semibold">3. Días y horarios</h2>
                <p className="text-sm text-texto-suave">
                    Elija en la agenda del canal un horario por cada unidad contratada, entre el {fecha(c.campania?.fecha_inicio)} y el {fecha(c.campania?.fecha_fin)}.
                    Los horarios elegidos quedan apartados por {c.horas_reserva} h{c.reservas_vencen ? ` (hasta ${fechaHora(c.reservas_vencen)})` : ''}; si no paga en ese tiempo se liberan.
                </p>
            </div>
            {c.lineas.map((l) => <LineaAgenda key={l.id_linea} c={c} l={l} alGuardar={alGuardar} />)}
        </section>
    );
}

function LineaAgenda({ c, l, alGuardar }) {
    const { exito, error } = useAvisos();
    const [disp, setDisp] = useState(null);
    const [sel, setSel] = useState(() => new Set(l.espacios.map((e) => e.id_espacio)));
    const [enviando, setEnviando] = useState(false);
    const [abierto, setAbierto] = useState(l.espacios.length !== l.cantidad);

    // La agenda se pide una sola vez por apertura (aunque cambie el contexto de avisos mientras carga)
    // y la selección del usuario no se pisa al recargar.
    const errorRef = useRef(error);
    errorRef.current = error;
    const cargando = useRef(false);
    const cargar = useCallback(async ({ reiniciarSeleccion = false } = {}) => {
        if (cargando.current) return;
        cargando.current = true;
        try {
            const { data } = await api.get(`/contrataciones/${c.proforma.id_proforma}/lineas/${l.id_linea}/espacios`);
            setDisp(data.data);
            if (reiniciarSeleccion) setSel(new Set(data.data.filter((e) => e.elegido).map((e) => e.id_espacio)));
        } catch (e) {
            errorRef.current(mensajeError(e));
            setDisp([]);
        } finally {
            cargando.current = false;
        }
    }, [c.proforma.id_proforma, l.id_linea]);

    useEffect(() => {
        if (abierto && disp === null) cargar({ reiniciarSeleccion: true });
    }, [abierto, disp, cargar]);

    const alternar = (idEsp) => setSel((s) => {
        const n = new Set(s);
        if (n.has(idEsp)) n.delete(idEsp);
        else if (n.size < l.cantidad) n.add(idEsp);
        return n;
    });

    const guardar = async () => {
        setEnviando(true);
        try {
            const { data } = await api.put(`/contrataciones/${c.proforma.id_proforma}/lineas/${l.id_linea}/espacios`, { espacios: [...sel] });
            exito(data.message);
            const lista = data.data.lineas.find((x) => x.id_linea === l.id_linea);
            if (lista?.espacios.length === l.cantidad) setAbierto(false);
            alGuardar(data.data, data.data.pasos[2].hecho);
        } catch (e) {
            error(mensajeError(e));
            // Un horario pudo haberse ocupado: se actualiza la agenda y se quita de la selección lo que ya no está libre
            await cargar();
            setDisp((d) => {
                const libres = new Set((d ?? []).map((x) => x.id_espacio));
                setSel((s) => new Set([...s].filter((x) => libres.has(x))));
                return d;
            });
        } finally {
            setEnviando(false);
        }
    };

    const porDia = useMemo(() => {
        const m = new Map();
        (disp ?? []).forEach((e) => m.set(e.fecha, [...(m.get(e.fecha) ?? []), e]));
        return [...m.entries()];
    }, [disp]);
    const completo = l.espacios.length === l.cantidad;

    return (
        <article className={`tarjeta p-4 border-l-4 ${completo ? 'border-l-exito' : 'border-l-aviso'}`}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 className="font-semibold">{l.servicio}{l.paquete && <span className="text-texto-suave font-normal"> · {l.paquete}</span>}</h3>
                    <p className="text-xs text-texto-suave">{l.espacios.length} de {l.cantidad} horario(s) elegido(s){l.material ? ` · pieza: ${l.material.nombre_archivo}` : ''}</p>
                </div>
                <button type="button" className="btn btn-secundario !py-1 !px-2.5 text-xs" onClick={() => setAbierto((a) => !a)} aria-expanded={abierto}>
                    {abierto ? 'Ocultar agenda' : completo ? 'Cambiar horarios' : 'Elegir horarios'}
                </button>
            </div>

            {!abierto && l.espacios.length > 0 && (
                <ul className="mt-2 flex flex-wrap gap-1.5 text-xs">
                    {l.espacios.map((e) => (
                        <li key={e.id_espacio} className="px-2 py-1 rounded-tema bg-superficie-2">{fecha(e.fecha)} · {e.hora_inicio}–{e.hora_fin} · {e.canal}</li>
                    ))}
                </ul>
            )}

            {abierto && (
                <div className="mt-3">
                    {disp === null ? (
                        <p className="text-sm text-texto-suave"><Loader2 size={14} className="inline animate-spin mr-1" />Cargando agenda…</p>
                    ) : disp.length === 0 ? (
                        <p className="text-sm text-aviso" role="alert">No hay horarios libres para este servicio dentro de las fechas de la campaña. Amplíe las fechas (paso 1) o consulte a Canal 11.</p>
                    ) : (
                        <>
                            <p className="text-xs text-texto-suave mb-2" aria-live="polite">Seleccionados: {sel.size} de {l.cantidad}</p>
                            {/* relative: las casillas ocultas (sr-only) quedan dentro de la lista y no estiran la página */}
                            <div className="relative max-h-80 overflow-y-auto space-y-2 pr-1 [scrollbar-width:thin] [scrollbar-color:var(--borde)_transparent]">
                                {porDia.map(([dia, lista]) => (
                                    <fieldset key={dia} className="border border-borde rounded-tema p-2">
                                        <legend className="text-xs font-semibold px-1">{new Date(`${dia}T12:00:00`).toLocaleDateString('es-BO', { weekday: 'long', day: 'numeric', month: 'long' })}</legend>
                                        <div className="flex flex-wrap gap-1.5">
                                            {lista.map((e) => {
                                                const marcado = sel.has(e.id_espacio);
                                                const lleno = !marcado && sel.size >= l.cantidad;
                                                return (
                                                    <label key={e.id_espacio} className={`text-xs px-2 py-1.5 rounded-tema border cursor-pointer select-none ${marcado ? 'bg-primario text-white border-primario' : lleno ? 'opacity-40 cursor-not-allowed border-borde' : 'border-borde hover:bg-superficie-2'}`}>
                                                        <input type="checkbox" className="sr-only" checked={marcado} disabled={lleno} onChange={() => alternar(e.id_espacio)} />
                                                        <Clock size={11} className="inline mr-1" aria-hidden />{e.hora_inicio}–{e.hora_fin} · {e.canal}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </fieldset>
                                ))}
                            </div>
                            <div className="flex justify-end mt-3">
                                <button type="button" className="btn btn-primario" onClick={guardar} disabled={enviando || sel.size === 0}>
                                    {enviando ? 'Guardando…' : sel.size === l.cantidad ? 'Apartar horarios' : `Guardar (${sel.size} de ${l.cantidad})`}
                                </button>
                            </div>
                        </>
                    )}
                </div>
            )}
        </article>
    );
}

// ---------------------------------------------------------------- Paso 4
function PasoPago({ c, recargar, alConfirmar }) {
    const { error, confirmar } = useAvisos();
    const [modalidad, setModalidad] = useState('contado');
    const [nroCuotas, setNroCuotas] = useState('3');
    const [frecuencia, setFrecuencia] = useState('mensual');
    const [metodo, setMetodo] = useState(c.metodos.find((m) => m.predeterminado)?.id_metodo ?? c.metodos[0]?.id_metodo ?? '');
    const [enviando, setEnviando] = useState(false);
    const [err, setErr] = useState(null);
    const listos = c.pasos.slice(0, 3).every((p) => p.hecho);
    const cuota = Math.floor((c.proforma.total / Number(nroCuotas || 1)) * 100) / 100;

    const pagar = async () => {
        setErr(null);
        if (!metodo) return setErr('Elija un método de pago.');
        const texto = modalidad === 'contado' ? `¿Pagar ${moneda(c.proforma.total)} al contado y aprobar la proforma?` : `¿Pagar la primera de ${nroCuotas} cuotas (${moneda(cuota)}) y aprobar la proforma?`;
        if (!(await confirmar(texto, { titulo: 'Confirmar contratación', textoAceptar: 'Pagar y aprobar' }))) return;
        setEnviando(true);
        try {
            const { data } = await pagarConVerificacion((extra) => api.post(`/contrataciones/${c.proforma.id_proforma}/confirmar`, {
                modalidad, id_metodo: Number(metodo), ...(modalidad === 'cuotas' ? { nro_cuotas: Number(nroCuotas), frecuencia } : {}), ...extra,
            }));
            window.dispatchEvent(new Event('notificaciones-cambiaron'));
            alConfirmar(data.data);
        } catch (e) {
            setErr(mensajeError(e));
            error(mensajeError(e));
            recargar();
        } finally {
            setEnviando(false);
        }
    };

    return (
        <section className="space-y-3">
            <div className="tarjeta p-4">
                <h2 className="text-lg font-semibold">4. Resumen y pago</h2>
                <p className="text-sm text-texto-suave">Revise lo contratado. Al aprobarse el pago, la proforma queda aprobada y sus emisiones programadas.</p>
            </div>

            <div className="tarjeta overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-superficie-2 text-xs text-texto-suave text-left">
                        <tr><th className="p-2">Servicio</th><th className="p-2">Pieza</th><th className="p-2">Horarios</th></tr>
                    </thead>
                    <tbody className="divide-y divide-borde">
                        {c.lineas.map((l) => (
                            <tr key={l.id_linea} className="align-top">
                                <td className="p-2"><p className="font-medium">{l.servicio}</p><p className="text-xs text-texto-suave">{l.cantidad} × {l.unidad_cobro}</p></td>
                                <td className="p-2 text-xs break-all">{l.material?.nombre_archivo ?? <span className="text-aviso">Falta</span>}</td>
                                <td className="p-2 text-xs">{l.espacios.length ? l.espacios.map((e) => <div key={e.id_espacio}>{fecha(e.fecha)} {e.hora_inicio} · {e.canal}</div>) : <span className="text-aviso">Faltan</span>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {!listos ? (
                <p className="tarjeta p-4 text-aviso flex items-center gap-2" role="alert"><AlertTriangle size={16} /> Complete los pasos anteriores antes de pagar.</p>
            ) : (
                <div className="tarjeta p-4 space-y-4">
                    <fieldset>
                        <legend className="etiqueta">Forma de pago</legend>
                        <div className="grid sm:grid-cols-2 gap-2">
                            {[['contado', `Al contado · ${moneda(c.proforma.total)}`], ['cuotas', 'En cuotas (se paga la primera ahora)']].map(([k, t]) => (
                                <label key={k} className={`p-3 rounded-tema border cursor-pointer text-sm ${modalidad === k ? 'border-primario bg-primario-suave' : 'border-borde'}`}>
                                    <input type="radio" name="modalidad" value={k} checked={modalidad === k} onChange={() => setModalidad(k)} className="mr-2" />{t}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    {modalidad === 'cuotas' && (
                        <div className="grid sm:grid-cols-2 gap-3">
                            <Campo etiqueta="Número de cuotas" nombre="nro" tipo="select" valor={nroCuotas} onChange={(_, val) => setNroCuotas(val)} opciones={[2, 3, 4, 6, 9, 12].map((n) => ({ id: String(n), nombre: `${n} cuotas` }))} />
                            <Campo etiqueta="Frecuencia" nombre="frec" tipo="select" valor={frecuencia} onChange={(_, val) => setFrecuencia(val)} opciones={[{ id: 'mensual', nombre: 'Mensual' }, { id: 'quincenal', nombre: 'Quincenal' }, { id: 'semanal', nombre: 'Semanal' }]} />
                            <p className="sm:col-span-2 text-sm">Primera cuota ahora: <strong>{moneda(cuota)}</strong>. Las demás se pagan en «Planes de pago», en orden.</p>
                        </div>
                    )}
                    {c.metodos.length === 0 ? (
                        <p className="text-sm text-aviso" role="alert">
                            No hay métodos de pago registrados. <Link to="/metodos-pago" className="underline">Registre una tarjeta</Link> y vuelva a esta pantalla: su avance se conserva.
                        </p>
                    ) : (
                        <Campo etiqueta="Método de pago" nombre="metodo" tipo="select" valor={String(metodo)} onChange={(_, val) => setMetodo(val)} requerido
                            opciones={c.metodos.map((m) => ({ id: String(m.id_metodo), nombre: `${m.marca ?? m.tipo} ${m.ultimos4 ? `•••• ${m.ultimos4}` : ''} · ${m.titular}${m.propio ? '' : ` (de ${m.de})`}` }))} />
                    )}
                    {err && <p className="texto-error" role="alert">{err}</p>}
                    <div className="flex justify-end">
                        <button type="button" className="btn btn-primario" onClick={pagar} disabled={enviando || !c.metodos.length}>
                            <CreditCard size={16} /> {enviando ? 'Procesando pago…' : 'Pagar y aprobar la proforma'}
                        </button>
                    </div>
                </div>
            )}
        </section>
    );
}

function Confirmada({ final }) {
    return (
        <section className="tarjeta p-6 text-center space-y-3" role="status">
            <PartyPopper size={40} className="mx-auto text-exito" aria-hidden />
            <h2 className="text-xl font-bold">¡Contratación confirmada!</h2>
            <p>
                La proforma <strong>{final.proforma.nro_proforma}</strong> quedó <strong>aprobada</strong>. Pago {final.pago.modalidad === 'cuotas' ? 'de la primera cuota' : 'al contado'} por{' '}
                <strong>{moneda(final.pago.monto)}</strong> (código {final.pago.codigo}). Se programaron <strong>{final.pautas_programadas}</strong> emisión(es).
            </p>
            <p className="text-sm text-texto-suave">Canal 11 revisará su material antes de emitirlo. Le llegará un aviso en la campana si pide correcciones.</p>
            <div className="flex flex-wrap justify-center gap-2">
                <Link to="/" className="btn btn-primario">Ir a Inicio</Link>
                <Link to="/pautas" className="btn btn-secundario">Ver mis emisiones</Link>
                <Link to="/materiales" className="btn btn-secundario">Ver mi material</Link>
            </div>
        </section>
    );
}
