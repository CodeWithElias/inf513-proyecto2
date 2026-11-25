import { useState, useEffect, useCallback } from 'react';
import {
    Plus,
    CalendarClock,
    CheckCircle,
    Ban,
    ExternalLink,
    Search,
    Tv,
    FileText,
    Calendar,
    Clock,
} from 'lucide-react';
import { api, mensajeError } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia from '../../components/Insignia';
import { fecha, fechaHora } from '../../lib/formato';

const ESTADOS_PAUTA = [
    { id: 'programada', nombre: 'Programada', tono: 'aviso' },
    { id: 'emitida', nombre: 'Emitida / Publicada', tono: 'exito' },
    { id: 'cancelada', nombre: 'Cancelada', tono: 'peligro' },
];

const MAPA_ESTADO = Object.fromEntries(ESTADOS_PAUTA.map((e) => [e.id, e]));

export default function Pautas() {
    const { puede, usuario } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const esCliente = usuario?.rol?.nombre === 'Cliente';

    const [pautas, setPautas] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [cargando, setCargando] = useState(false);
    const [filtros, setFiltros] = useState({ id_campania: '', estado: '', q: '', page: 1 });

    // Opciones para formularios
    const [opcionesCampania, setOpcionesCampania] = useState([]);
    const [materialesAprobados, setMaterialesAprobados] = useState([]);
    const [espaciosDisponibles, setEspaciosDisponibles] = useState([]);
    const [detallesProforma, setDetallesProforma] = useState([]);

    // Modal Programar Pauta
    const [modalProgramar, setModalProgramar] = useState(false);
    const [formProgramar, setFormProgramar] = useState({
        id_campania: '',
        id_material: '',
        id_espacio: '',
        id_detalle: '',
    });
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    // Modal Reprogramar
    const [modalReprogramar, setModalReprogramar] = useState(false);
    const [pautaActual, setPautaActual] = useState(null);
    const [nuevoEspacio, setNuevoEspacio] = useState('');

    // Modal Emitir
    const [modalEmitir, setModalEmitir] = useState(false);
    const [urlPublicacion, setUrlPublicacion] = useState('');

    const cargarOpciones = useCallback(async () => {
        try {
            const [rCamp, rMat, rEsp, rDet] = await Promise.all([
                api.get('/opciones/campanias'),
                api.get('/opciones/materiales-aprobados'),
                api.get('/opciones/espacios-disponibles'),
                api.get('/opciones/detalles-proforma'),
            ]);
            setOpcionesCampania(rCamp.data?.data || []);
            setMaterialesAprobados(rMat.data?.data || []);
            setEspaciosDisponibles(rEsp.data?.data || []);
            setDetallesProforma(rDet.data?.data || []);
        } catch {
            // Opciones auxiliares silenciosas
        }
    }, []);

    const cargarPautas = useCallback(async () => {
        setCargando(true);
        try {
            const params = {
                page: filtros.page,
                ...(filtros.id_campania ? { id_campania: filtros.id_campania } : {}),
                ...(filtros.estado ? { estado: filtros.estado } : {}),
                ...(filtros.q ? { q: filtros.q } : {}),
            };
            const { data } = await api.get('/pautas', { params });
            setPautas(data.data || []);
            setMeta(data.meta || { current_page: 1, last_page: 1, total: 0 });
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setCargando(false);
        }
    }, [filtros, error]);

    useEffect(() => {
        cargarOpciones();
    }, [cargarOpciones]);

    useEffect(() => {
        cargarPautas();
    }, [cargarPautas]);

    // Filtrar materiales y detalles según la campaña seleccionada en el formulario
    const materialesFiltrados = formProgramar.id_campania
        ? materialesAprobados.filter((m) => String(m.id_campania) === String(formProgramar.id_campania))
        : materialesAprobados;

    const detallesFiltrados = formProgramar.id_campania
        ? detallesProforma.filter((d) => String(d.id_campania) === String(formProgramar.id_campania))
        : detallesProforma;

    const abrirProgramar = () => {
        const idCamp = opcionesCampania[0]?.id ? String(opcionesCampania[0].id) : '';
        const mats = idCamp ? materialesAprobados.filter((m) => String(m.id_campania) === idCamp) : materialesAprobados;
        const dets = idCamp ? detallesProforma.filter((d) => String(d.id_campania) === idCamp) : detallesProforma;

        setFormProgramar({
            id_campania: idCamp,
            id_material: mats[0]?.id ? String(mats[0].id) : '',
            id_detalle: dets[0]?.id ? String(dets[0].id) : '',
            id_espacio: espaciosDisponibles[0]?.id ? String(espaciosDisponibles[0].id) : '',
        });
        setErrores({});
        setModalProgramar(true);
    };

    const guardarPauta = async (e) => {
        e.preventDefault();
        setEnviando(true);
        setErrores({});
        try {
            await api.post('/pautas', {
                id_material: Number(formProgramar.id_material),
                id_espacio: Number(formProgramar.id_espacio),
                id_detalle: Number(formProgramar.id_detalle),
            });
            exito('Pauta publicitaria programada correctamente.');
            setModalProgramar(false);
            cargarPautas();
            cargarOpciones();
        } catch (err) {
            if (err.response?.status === 422 && err.response.data?.errors) {
                setErrores(err.response.data.errors);
            } else {
                error(mensajeError(err));
            }
        } finally {
            setEnviando(false);
        }
    };

    const abrirReprogramar = (p) => {
        setPautaActual(p);
        setNuevoEspacio('');
        setModalReprogramar(true);
    };

    const guardarReprogramacion = async (e) => {
        e.preventDefault();
        if (!nuevoEspacio) {
            error('Seleccione un nuevo espacio disponible.');
            return;
        }

        setEnviando(true);
        try {
            await api.put(`/pautas/${pautaActual.id_programacion}/reprogramar`, {
                id_espacio: Number(nuevoEspacio),
            });
            exito('Pauta reprogramada exitosamente.');
            setModalReprogramar(false);
            cargarPautas();
            cargarOpciones();
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    const abrirEmitir = (p) => {
        setPautaActual(p);
        setUrlPublicacion(p.url_publicacion || '');
        setModalEmitir(true);
    };

    const guardarEmision = async (e) => {
        e.preventDefault();
        setEnviando(true);
        try {
            await api.patch(`/pautas/${pautaActual.id_programacion}/emitir`, {
                url_publicacion: urlPublicacion || null,
            });
            exito('Pauta registrada como emitida.');
            setModalEmitir(false);
            cargarPautas();
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    const cancelarPauta = async (p) => {
        if (await confirmar(`¿Desea cancelar la pauta #${p.id_programacion}? El espacio reservado quedará disponible nuevamente.`)) {
            try {
                await api.delete(`/pautas/${p.id_programacion}`);
                exito('Pauta cancelada y espacio liberado.');
                cargarPautas();
                cargarOpciones();
            } catch (err) {
                error(mensajeError(err));
            }
        }
    };

    const columnas = [
        {
            titulo: 'ID / Fecha prog.',
            render: (p) => (
                <div>
                    <span className="font-bold text-sm">#{p.id_programacion}</span>
                    <p className="text-xs text-texto-suave">{fechaHora(p.fecha_programacion)}</p>
                </div>
            ),
        },
        {
            titulo: 'Campaña / Cliente',
            render: (p) => (
                <div>
                    <p className="font-semibold text-texto leading-snug">{p.material?.campania?.nombre}</p>
                    <p className="text-xs text-texto-suave">
                        {p.material?.campania?.proforma?.empresa?.razon_social ?? 'Empresa'}
                    </p>
                </div>
            ),
        },
        {
            titulo: 'Material aprobado',
            render: (p) => (
                <div className="flex items-center gap-2">
                    <FileText size={16} className="text-primario shrink-0" />
                    <div className="min-w-0">
                        <p className="text-sm font-medium truncate max-w-xs">{p.material?.nombre_archivo}</p>
                        <p className="text-xs text-texto-suave">
                            <span className="uppercase font-semibold">{p.material?.tipo}</span>
                            {p.material?.duracion_seg ? ` · ${p.material.duracion_seg} seg` : ''}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            titulo: 'Canal y Espacio',
            render: (p) => (
                <div>
                    <div className="flex items-center gap-1.5 font-medium text-sm">
                        <Tv size={14} className="text-primario" />
                        <span>{p.espacio?.canal?.nombre}</span>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-texto-suave mt-0.5">
                        <span className="inline-flex items-center gap-1">
                            <Calendar size={12} /> {fecha(p.espacio?.fecha)}
                        </span>
                        <span className="inline-flex items-center gap-1 font-medium">
                            <Clock size={12} /> {p.espacio?.hora_inicio?.slice(0, 5)} - {p.espacio?.hora_fin?.slice(0, 5)}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            titulo: 'Estado',
            render: (p) => {
                const metaEst = MAPA_ESTADO[p.estado] || { tono: 'neutro', nombre: p.estado };
                return (
                    <div>
                        <Insignia tono={metaEst.tono}>{metaEst.nombre}</Insignia>
                        {p.url_publicacion && (
                            <a
                                href={p.url_publicacion}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-xs text-primario hover:underline mt-1 block"
                            >
                                Ver emisión <ExternalLink size={11} />
                            </a>
                        )}
                    </div>
                );
            },
        },
        {
            titulo: 'Acciones',
            className: 'text-right w-36',
            render: (p) => (
                <div className="flex items-center justify-end gap-1">
                    {puede('pautas.editar') && !esCliente && p.estado === 'programada' && (
                        <>
                            <button
                                type="button"
                                className="btn-icono text-exito hover:bg-exito/10"
                                onClick={() => abrirEmitir(p)}
                                title="Marcar como emitida / publicar enlace"
                            >
                                <CheckCircle size={16} />
                            </button>

                            <button
                                type="button"
                                className="btn-icono text-primario hover:bg-primario-suave"
                                onClick={() => abrirReprogramar(p)}
                                title="Reprogramar a otro espacio"
                            >
                                <CalendarClock size={16} />
                            </button>

                            <button
                                type="button"
                                className="btn-icono text-peligro hover:bg-peligro/10"
                                onClick={() => cancelarPauta(p)}
                                title="Cancelar pauta y liberar espacio"
                            >
                                <Ban size={16} />
                            </button>
                        </>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            <EncabezadoPagina
                titulo="Pautas publicitarias"
                descripcion="Programación y control de emisión de piezas publicitarias aprobadas en espacios de difusión."
                accion={
                    puede('pautas.crear') && !esCliente && (
                        <button type="button" className="btn btn-primario" onClick={abrirProgramar}>
                            <Plus size={16} /> Programar pauta
                        </button>
                    )
                }
            />

            {/* Filtros */}
            <div className="tarjeta p-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                <div className="relative flex-1">
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" />
                    <input
                        type="search"
                        placeholder="Buscar por campaña, archivo o URL de emisión..."
                        value={filtros.q}
                        onChange={(e) => setFiltros((prev) => ({ ...prev, q: e.target.value, page: 1 }))}
                        className="input pl-9"
                    />
                </div>

                <div className="flex flex-wrap gap-2">
                    <select
                        value={filtros.id_campania}
                        onChange={(e) => setFiltros((prev) => ({ ...prev, id_campania: e.target.value, page: 1 }))}
                        className="input w-auto text-sm"
                    >
                        <option value="">Todas las campañas</option>
                        {opcionesCampania.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.nombre}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filtros.estado}
                        onChange={(e) => setFiltros((prev) => ({ ...prev, estado: e.target.value, page: 1 }))}
                        className="input w-auto text-sm"
                    >
                        <option value="">Todos los estados</option>
                        {ESTADOS_PAUTA.map((e) => (
                            <option key={e.id} value={e.id}>
                                {e.nombre}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Tabla */}
            <Tabla
                columnas={columnas}
                filas={pautas}
                cargando={cargando}
                meta={meta}
                onPagina={(page) => setFiltros((prev) => ({ ...prev, page }))}
                idFila="id_programacion"
                vacio="No se encontraron pautas publicitarias programadas."
            />

            {/* Modal: Programar Pauta */}
            <Modal
                titulo="Programar pauta publicitaria"
                abierto={modalProgramar}
                onCerrar={() => setModalProgramar(false)}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModalProgramar(false)}>
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="btn btn-primario"
                            onClick={guardarPauta}
                            disabled={enviando}
                        >
                            {enviando ? 'Programando...' : 'Programar pauta'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardarPauta} className="space-y-3">
                    <Campo
                        etiqueta="Campaña publicitaria"
                        nombre="id_campania"
                        tipo="select"
                        valor={formProgramar.id_campania}
                        onChange={(c, v) => setFormProgramar((prev) => ({ ...prev, id_campania: v, id_material: '', id_detalle: '' }))}
                        opciones={opcionesCampania}
                        requerido
                    />

                    <Campo
                        etiqueta="Pieza publicitaria aprobada"
                        nombre="id_material"
                        tipo="select"
                        valor={formProgramar.id_material}
                        onChange={(c, v) => setFormProgramar((prev) => ({ ...prev, id_material: v }))}
                        opciones={materialesFiltrados}
                        ayuda="Solo se muestran piezas en estado 'Aprobado'"
                        error={errores.id_material?.[0]}
                        requerido
                    />

                    <Campo
                        etiqueta="Ítem contratado en la proforma"
                        nombre="id_detalle"
                        tipo="select"
                        valor={formProgramar.id_detalle}
                        onChange={(c, v) => setFormProgramar((prev) => ({ ...prev, id_detalle: v }))}
                        opciones={detallesFiltrados}
                        error={errores.id_detalle?.[0]}
                        requerido
                    />

                    <Campo
                        etiqueta="Espacio de difusión disponible"
                        nombre="id_espacio"
                        tipo="select"
                        valor={formProgramar.id_espacio}
                        onChange={(c, v) => setFormProgramar((prev) => ({ ...prev, id_espacio: v }))}
                        opciones={espaciosDisponibles}
                        ayuda="Franjas horarias libres en televisión, radio o redes"
                        error={errores.id_espacio?.[0]}
                        requerido
                    />
                </form>
            </Modal>

            {/* Modal: Reprogramar */}
            <Modal
                titulo={`Reprogramar pauta #${pautaActual?.id_programacion}`}
                abierto={modalReprogramar}
                onCerrar={() => setModalReprogramar(false)}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModalReprogramar(false)}>
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="btn btn-primario"
                            onClick={guardarReprogramacion}
                            disabled={enviando}
                        >
                            {enviando ? 'Reprogramando...' : 'Confirmar nuevo espacio'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardarReprogramacion} className="space-y-3">
                    <p className="text-sm text-texto-suave">
                        Espacio actual:{' '}
                        <strong className="text-texto font-medium">
                            {pautaActual?.espacio?.canal?.nombre} · {fecha(pautaActual?.espacio?.fecha)} ({pautaActual?.espacio?.hora_inicio?.slice(0, 5)} - {pautaActual?.espacio?.hora_fin?.slice(0, 5)})
                        </strong>
                    </p>

                    <Campo
                        etiqueta="Nuevo espacio disponible"
                        nombre="nuevoEspacio"
                        tipo="select"
                        valor={nuevoEspacio}
                        onChange={(c, v) => setNuevoEspacio(v)}
                        opciones={espaciosDisponibles}
                        requerido
                    />
                </form>
            </Modal>

            {/* Modal: Emitir / Publicar */}
            <Modal
                titulo="Registrar emisión / publicación"
                abierto={modalEmitir}
                onCerrar={() => setModalEmitir(false)}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModalEmitir(false)}>
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="btn btn-primario"
                            onClick={guardarEmision}
                            disabled={enviando}
                        >
                            {enviando ? 'Guardando...' : 'Marcar emitida'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardarEmision} className="space-y-3">
                    <p className="text-sm text-texto-suave">
                        Confirme que la pauta publicitaria ha sido transmitida en TV/Radio o publicada en redes sociales.
                    </p>

                    <Campo
                        etiqueta="URL o enlace del post / testimonio de emisión (opcional)"
                        nombre="urlPublicacion"
                        tipo="url"
                        valor={urlPublicacion}
                        onChange={(c, v) => setUrlPublicacion(v)}
                        placeholder="https://facebook.com/tvu/posts/123456"
                    />
                </form>
            </Modal>
        </div>
    );
}
