import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
    Video,
    Music,
    Image as ImageIcon,
    FileText,
    Layers,
    Plus,
    Pencil,
    Trash2,
    ShieldCheck,
    History,
    UploadCloud,
    Search,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Eye,
} from 'lucide-react';
import { OrigenArchivo, VisorMaterial, validarArchivo, leerDuracion, extension } from './ArchivoMaterial';
import { api, mensajeError } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia from '../../components/Insignia';
import { fechaHora } from '../../lib/formato';
import { validarCampo, validarLista } from '../../lib/validar';

/** Reglas de validación en el cliente (las mismas de MaterialRequest / MaterialService en el servidor). */
function reglasMaterial(d, { crear = false, conTipo = true } = {}) {
    const conEnlace = d.origen === 'enlace';
    const r = {
        nombre_archivo: { requerido: d.tipo !== 'texto' && !d.archivo, max: 255, etiqueta: 'nombre del archivo' },
        texto_publicacion: { max: 3000, etiqueta: 'texto de la publicación' },
    };
    if (conEnlace) r.url_archivo = { requerido: d.tipo !== 'texto', url: true, max: 500, etiqueta: 'enlace del archivo' };
    if (['video', 'audio'].includes(d.tipo)) {
        r.duracion_seg = { requerido: true, entero: true, minValor: 1, maxValor: 7200, etiqueta: 'duración (segundos)' };
    }
    if (conTipo) r.tipo = { requerido: true, etiqueta: 'tipo de material' };
    if (crear) r.id_campania = { requerido: true, etiqueta: 'campaña' };
    return r;
}

/** Validación completa del formulario, incluido el archivo elegido. Devuelve { campo: ['mensaje'] }. */
function validarMaterial(d, opciones) {
    const errores = validarLista(d, reglasMaterial(d, opciones));
    if (d.origen === 'archivo') {
        const e = d.archivo
            ? validarArchivo(d.archivo, d.tipo)
            : d.tipo !== 'texto' && !d.interno
              ? 'Elija el archivo del material (o cambie a «Enlace web»).'
              : null;
        if (e) errores.archivo = [e];
    }
    if (d.tipo === 'texto' && !d.texto_publicacion?.trim() && !d.archivo && !d.interno && !(d.origen === 'enlace' && d.url_archivo?.trim())) {
        errores.texto_publicacion = ['Escriba el texto de la publicación, o adjunte un archivo o un enlace.'];
    }
    return errores;
}

/** Estado inicial del bloque de archivo a partir de un material existente (o vacío). */
const origenDe = (m) => ({
    origen: m?.url_archivo && !m.archivo_interno ? 'enlace' : 'archivo',
    archivo: null,
    interno: Boolean(m?.archivo_interno),
    nombre_actual: m?.nombre_archivo ?? '',
    url_actual: m?.url_archivo ?? '',
    url_archivo: m?.url_archivo && !m.archivo_interno ? m.url_archivo : '',
});

/**
 * Arma el FormData para la API: el archivo viaja como multipart y la BD guarda solo su ruta.
 * Si no se elige archivo nuevo, se reenvía la ruta actual (el servidor solo la acepta si es la del material).
 */
function aFormData(d, extra = {}) {
    const fd = new FormData();
    const url = d.origen === 'enlace' ? d.url_archivo.trim() : d.archivo ? '' : d.interno ? d.url_actual : '';
    const campos = {
        ...extra,
        tipo: d.tipo,
        nombre_archivo: d.nombre_archivo,
        url_archivo: url,
        formato: d.archivo ? extension(d.archivo.name) : extension(d.nombre_archivo) || d.formato,
        duracion_seg: ['video', 'audio'].includes(d.tipo) ? d.duracion_seg : '',
        texto_publicacion: d.texto_publicacion,
    };
    for (const [k, v] of Object.entries(campos)) {
        if (v !== null && v !== undefined && String(v).trim() !== '') fd.append(k, v);
    }
    if (d.origen === 'archivo' && d.archivo) fd.append('archivo', d.archivo);
    return fd;
}

const TIPOS_MATERIAL = [
    { id: 'video', nombre: 'Video (Spot TV / Redes)', icono: Video },
    { id: 'audio', nombre: 'Audio (Spot Radio)', icono: Music },
    { id: 'imagen', nombre: 'Imagen fija', icono: ImageIcon },
    { id: 'banner', nombre: 'Banner publicitario', icono: Layers },
    { id: 'texto', nombre: 'Texto / Publicación', icono: FileText },
];

const ESTADOS_MATERIAL = [
    { id: 'pendiente', nombre: 'Pendiente', tono: 'aviso' },
    { id: 'aprobado', nombre: 'Aprobado', tono: 'exito' },
    { id: 'correccion', nombre: 'En corrección', tono: 'primario' },
    { id: 'rechazado', nombre: 'Rechazado', tono: 'peligro' },
    { id: 'retirado', nombre: 'Retirado', tono: 'neutro' },
];

const MAPA_ESTADO = Object.fromEntries(ESTADOS_MATERIAL.map((e) => [e.id, e]));

export default function Materiales() {
    const { puede, usuario } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const esCliente = usuario?.rol?.nombre === 'Cliente';

    // Lista principal y filtros
    const [materiales, setMateriales] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [cargando, setCargando] = useState(false);
    // ?q= en la URL llega desde la búsqueda global del encabezado
    const [params] = useSearchParams();
    const qUrl = params.get('q') ?? '';
    const [filtros, setFiltros] = useState({ q: qUrl, estado: '', tipo: '', id_campania: '', page: 1 });
    useEffect(() => {
        setFiltros((f) => (f.q === qUrl ? f : { ...f, q: qUrl, page: 1 }));
    }, [qUrl]);

    // Opciones auxiliares
    const [opcionesCampania, setOpcionesCampania] = useState([]);

    // Estados de modales
    const [modalForm, setModalForm] = useState(false);
    const [modoForm, setModoForm] = useState('crear'); // 'crear' | 'editar'
    const [materialActual, setMaterialActual] = useState(null);
    const [formData, setFormData] = useState({
        id_campania: '',
        tipo: 'video',
        nombre_archivo: '',
        formato: '',
        duracion_seg: '',
        texto_publicacion: '',
        ...origenDe(null),
    });
    const [erroresForm, setErroresForm] = useState({});
    const [enviando, setEnviando] = useState(false);

    // Modal de Nueva Versión
    const [modalVersion, setModalVersion] = useState(false);
    const [formVersion, setFormVersion] = useState({
        tipo: 'video',
        nombre_archivo: '',
        formato: '',
        duracion_seg: '',
        texto_publicacion: '',
        ...origenDe(null),
    });

    // Visor del material
    const [materialVisto, setMaterialVisto] = useState(null);

    // Modal de Revisión
    const [modalRevision, setModalRevision] = useState(false);
    const [formRevision, setFormRevision] = useState({
        resultado: 'aprobado',
        observaciones: '',
    });

    // Modal de Historial / Detalle
    const [modalHistorial, setModalHistorial] = useState(false);
    const [historialRevisiones, setHistorialRevisiones] = useState([]);
    const [cargandoHistorial, setCargandoHistorial] = useState(false);

    // Cargar opciones de campañas disponibles
    const cargarCampanias = useCallback(async () => {
        try {
            const { data } = await api.get('/opciones/campanias');
            setOpcionesCampania(data.data || []);
        } catch {
            setOpcionesCampania([]);
        }
    }, []);

    // Cargar lista de materiales
    const cargarMateriales = useCallback(async () => {
        setCargando(true);
        try {
            const params = {
                page: filtros.page,
                ...(filtros.q ? { q: filtros.q } : {}),
                ...(filtros.estado ? { estado: filtros.estado } : {}),
                ...(filtros.tipo ? { tipo: filtros.tipo } : {}),
                ...(filtros.id_campania ? { id_campania: filtros.id_campania } : {}),
            };
            const { data } = await api.get('/materiales', { params });
            setMateriales(data.data || []);
            setMeta(data.meta || { current_page: 1, last_page: 1, total: 0 });
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setCargando(false);
        }
    }, [filtros, error]);

    useEffect(() => {
        cargarCampanias();
    }, [cargarCampanias]);

    useEffect(() => {
        cargarMateriales();
    }, [cargarMateriales]);

    const cambiarCampoForm = (campo, valor) => {
        setFormData((prev) => ({ ...prev, [campo]: valor }));
        if (erroresForm[campo] || campo === 'origen' || campo === 'tipo') {
            setErroresForm((prev) => ({ ...prev, [campo]: undefined, archivo: undefined, url_archivo: undefined }));
        }
    };

    /**
     * Al elegir un archivo: se valida al instante, se completa el nombre y,
     * si es video o audio, se lee su duración real.
     */
    const elegirArchivo = (setForm, tipo) => async (archivo) => {
        const e = validarArchivo(archivo, tipo);
        setErroresForm((er) => ({ ...er, archivo: e ? [e] : undefined, nombre_archivo: undefined }));
        setForm((prev) => ({ ...prev, archivo, nombre_archivo: archivo ? archivo.name : prev.nombre_actual }));
        if (archivo && /^(video|audio)\//.test(archivo.type)) {
            const segundos = await leerDuracion(archivo);
            if (segundos) {
                setForm((prev) => (prev.archivo === archivo ? { ...prev, duracion_seg: String(segundos) } : prev));
                setErroresForm((er) => ({ ...er, duracion_seg: undefined }));
            }
        }
    };

    const abrirCrear = () => {
        setMaterialActual(null);
        setFormData({
            id_campania: opcionesCampania[0]?.id ? String(opcionesCampania[0].id) : '',
            tipo: 'video',
            nombre_archivo: '',
            formato: '',
            duracion_seg: '',
            texto_publicacion: '',
            ...origenDe(null),
        });
        setErroresForm({});
        setModoForm('crear');
        setModalForm(true);
    };

    const abrirEditar = (m) => {
        setMaterialActual(m);
        setFormData({
            id_campania: String(m.id_campania),
            tipo: m.tipo,
            nombre_archivo: m.nombre_archivo || '',
            formato: m.formato || '',
            duracion_seg: m.duracion_seg ? String(m.duracion_seg) : '',
            texto_publicacion: m.texto_publicacion || '',
            ...origenDe(m),
        });
        setErroresForm({});
        setModoForm('editar');
        setModalForm(true);
    };

    const guardarMaterial = async (e) => {
        e.preventDefault();
        const locales = validarMaterial(formData, { crear: modoForm === 'crear' });
        if (Object.keys(locales).length) {
            setErroresForm(locales);
            error('Revise los campos marcados en rojo.');
            return;
        }
        setEnviando(true);
        setErroresForm({});
        try {
            if (modoForm === 'crear') {
                await api.post('/materiales', aFormData(formData, { id_campania: formData.id_campania }));
                exito('Material publicitario cargado correctamente.');
            } else {
                // multipart no admite PUT en PHP: se envía POST con _method=PUT (lo entiende Laravel)
                await api.post(`/materiales/${materialActual.id_material}`, aFormData(formData, { _method: 'PUT' }));
                exito('Material publicitario modificado correctamente.');
            }
            setModalForm(false);
            cargarMateriales();
        } catch (err) {
            if (err.response?.status === 422 && err.response.data?.errors) {
                setErroresForm(err.response.data.errors);
            } else {
                error(mensajeError(err));
            }
        } finally {
            setEnviando(false);
        }
    };

    // Subir nueva versión
    const abrirNuevaVersion = (m) => {
        setMaterialActual(m);
        setFormVersion({
            tipo: m.tipo,
            nombre_archivo: m.nombre_archivo || '',
            formato: m.formato || '',
            duracion_seg: m.duracion_seg ? String(m.duracion_seg) : '',
            texto_publicacion: m.texto_publicacion || '',
            ...origenDe(m),
        });
        setErroresForm({});
        setModalVersion(true);
    };

    const cambiarCampoVersion = (campo, valor) => {
        setFormVersion((prev) => ({ ...prev, [campo]: valor }));
        setErroresForm((prev) => ({ ...prev, [campo]: undefined, ...(campo === 'origen' ? { archivo: undefined, url_archivo: undefined } : {}) }));
    };

    const guardarNuevaVersion = async (e) => {
        e.preventDefault();
        const locales = validarMaterial(formVersion, { conTipo: false });
        if (Object.keys(locales).length) {
            setErroresForm(locales);
            error('Revise los campos marcados en rojo.');
            return;
        }
        setErroresForm({});
        setEnviando(true);
        try {
            await api.post(`/materiales/${materialActual.id_material}/version`, aFormData(formVersion));
            exito(`Nueva versión (v${materialActual.version + 1}) registrada correctamente.`);
            setModalVersion(false);
            cargarMateriales();
        } catch (err) {
            if (err.response?.status === 422 && err.response.data?.errors) {
                setErroresForm(err.response.data.errors);
            }
            error(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    // Retirar material (baja lógica)
    const retirarMaterial = async (m) => {
        if (await confirmar(`¿Desea retirar el material «${m.nombre_archivo}»? No se podrá vincular a nuevas pautas.`)) {
            try {
                await api.delete(`/materiales/${m.id_material}`);
                exito('Material retirado correctamente.');
                cargarMateriales();
            } catch (err) {
                error(mensajeError(err));
            }
        }
    };

    // Revisión institucional
    const abrirRevision = (m) => {
        setMaterialActual(m);
        setFormRevision({ resultado: 'aprobado', observaciones: '' });
        setModalRevision(true);
    };

    const guardarRevision = async (e) => {
        e.preventDefault();
        if (formRevision.resultado !== 'aprobado' && !formRevision.observaciones.trim()) {
            error('Debe ingresar las observaciones para rechazar o pedir correcciones.');
            return;
        }
        const errObs = validarCampo(formRevision.observaciones, { max: 2000, etiqueta: 'las observaciones' });
        if (errObs) {
            error(errObs);
            return;
        }

        setEnviando(true);
        try {
            await api.post(`/materiales/${materialActual.id_material}/revisar`, formRevision);
            exito('Revisión institucional registrada correctamente.');
            setModalRevision(false);
            cargarMateriales();
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    // Historial de revisiones
    const abrirHistorial = async (m) => {
        setMaterialActual(m);
        setModalHistorial(true);
        setCargandoHistorial(true);
        try {
            const { data } = await api.get(`/materiales/${m.id_material}/revisiones`);
            setHistorialRevisiones(data.data || []);
        } catch {
            setHistorialRevisiones([]);
        } finally {
            setCargandoHistorial(false);
        }
    };

    const columnas = [
        {
            titulo: 'Campaña / Cliente',
            render: (m) => (
                <div>
                    <p className="font-semibold text-texto leading-snug">{m.campania?.nombre ?? '—'}</p>
                    <p className="text-xs text-texto-suave">
                        {m.campania?.proforma?.empresa?.razon_social ?? 'Empresa'}
                    </p>
                </div>
            ),
        },
        {
            titulo: 'Pieza publicitaria',
            render: (m) => {
                const tipoMeta = TIPOS_MATERIAL.find((t) => t.id === m.tipo);
                const IconoTipo = tipoMeta?.icono || FileText;
                return (
                    <div className="flex items-start gap-2.5">
                        <div className="p-2 rounded-tema bg-superficie-2 text-primario shrink-0 mt-0.5">
                            <IconoTipo size={18} />
                        </div>
                        <div className="min-w-0">
                            <p className="font-medium text-sm truncate max-w-xs" title={m.nombre_archivo}>
                                {m.nombre_archivo}
                            </p>
                            <p className="text-xs text-texto-suave">
                                <span className="uppercase font-semibold">{m.formato || m.tipo}</span>
                                {m.duracion_seg ? ` · ${m.duracion_seg} seg` : ''}
                            </p>
                            {(m.url_vista || m.texto_publicacion) && (
                                <button
                                    type="button"
                                    onClick={() => setMaterialVisto(m)}
                                    className="inline-flex items-center gap-1 text-xs text-primario hover:underline mt-0.5"
                                >
                                    <Eye size={12} aria-hidden /> Ver material
                                </button>
                            )}
                        </div>
                    </div>
                );
            },
        },
        {
            titulo: 'Versión',
            className: 'w-24 text-center',
            render: (m) => (
                <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-superficie-2 border border-borde">
                    v{m.version}
                </span>
            ),
        },
        {
            titulo: 'Fecha de carga',
            className: 'text-xs text-texto-suave',
            render: (m) => fechaHora(m.fecha_carga),
        },
        {
            titulo: 'Estado',
            render: (m) => {
                const meta = MAPA_ESTADO[m.estado] || { nombre: m.estado, tono: 'neutro' };
                return <Insignia tono={meta.tono}>{meta.nombre}</Insignia>;
            },
        },
        {
            titulo: 'Acciones',
            className: 'text-right w-44',
            render: (m) => (
                <div className="flex items-center justify-end gap-1">
                    <button
                        type="button"
                        className="btn-icono"
                        onClick={() => abrirHistorial(m)}
                        title="Ver detalle e historial de revisiones"
                    >
                        <History size={16} />
                    </button>

                    {puede('materiales.editar') && m.estado === 'pendiente' && (
                        <button
                            type="button"
                            className="btn-icono"
                            onClick={() => abrirEditar(m)}
                            title="Editar datos del material"
                        >
                            <Pencil size={16} />
                        </button>
                    )}

                    {puede('materiales.crear') && m.estado === 'correccion' && (
                        <button
                            type="button"
                            className="btn-icono text-primario hover:bg-primario-suave"
                            onClick={() => abrirNuevaVersion(m)}
                            title="Subir nueva versión corregida"
                        >
                            <UploadCloud size={16} />
                        </button>
                    )}

                    {puede('revisiones.crear') && !esCliente && ['pendiente', 'correccion'].includes(m.estado) && (
                        <button
                            type="button"
                            className="btn-icono text-exito hover:bg-exito/10"
                            onClick={() => abrirRevision(m)}
                            title="Revisión institucional (Aprobar / Observar)"
                        >
                            <ShieldCheck size={16} />
                        </button>
                    )}

                    {puede('materiales.eliminar') && m.estado !== 'retirado' && (
                        <button
                            type="button"
                            className="btn-icono text-peligro hover:bg-peligro/10"
                            onClick={() => retirarMaterial(m)}
                            title="Retirar material"
                        >
                            <Trash2 size={16} />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            <EncabezadoPagina
                titulo="Material publicitario"
                descripcion="Gestión de piezas publicitarias por campaña, control de versiones y auditoría de revisiones."
                accion={
                    puede('materiales.crear') && (
                        <button type="button" className="btn btn-primario" onClick={abrirCrear}>
                            <Plus size={16} /> Cargar material
                        </button>
                    )
                }
            />

            {/* Barra de Filtros y Búsqueda */}
            <div className="tarjeta p-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center">
                <div className="relative flex-1">
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" />
                    <input
                        type="search"
                        placeholder="Buscar por archivo, campaña o contenido..."
                        value={filtros.q}
                        onChange={(e) => setFiltros((prev) => ({ ...prev, q: e.target.value, page: 1 }))}
                        className="input pl-9"
                    />
                </div>

                <div className="flex flex-wrap gap-2">
                    <select
                        value={filtros.tipo}
                        onChange={(e) => setFiltros((prev) => ({ ...prev, tipo: e.target.value, page: 1 }))}
                        className="input w-auto text-xs sm:text-sm"
                    >
                        <option value="">Todos los tipos</option>
                        {TIPOS_MATERIAL.map((t) => (
                            <option key={t.id} value={t.id}>
                                {t.nombre}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filtros.estado}
                        onChange={(e) => setFiltros((prev) => ({ ...prev, estado: e.target.value, page: 1 }))}
                        className="input w-auto text-xs sm:text-sm"
                    >
                        <option value="">Todos los estados</option>
                        {ESTADOS_MATERIAL.map((e) => (
                            <option key={e.id} value={e.id}>
                                {e.nombre}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Tabla de registros */}
            <Tabla
                columnas={columnas}
                filas={materiales}
                cargando={cargando}
                meta={meta}
                onPagina={(page) => setFiltros((prev) => ({ ...prev, page }))}
                idFila="id_material"
                vacio="No se encontraron piezas de material publicitario."
            />

            {/* Modal: Cargar / Editar Material */}
            <Modal
                titulo={modoForm === 'crear' ? 'Cargar material publicitario' : 'Editar material publicitario'}
                abierto={modalForm}
                onCerrar={() => setModalForm(false)}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModalForm(false)}>
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="btn btn-primario"
                            onClick={guardarMaterial}
                            disabled={enviando}
                        >
                            {enviando ? 'Guardando...' : modoForm === 'crear' ? 'Cargar material' : 'Guardar cambios'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardarMaterial} noValidate className="space-y-3">
                    {modoForm === 'crear' && (
                        <Campo
                            etiqueta="Campaña publicitaria"
                            nombre="id_campania"
                            tipo="select"
                            valor={formData.id_campania}
                            onChange={cambiarCampoForm}
                            opciones={opcionesCampania}
                            error={erroresForm.id_campania?.[0]}
                            requerido
                        />
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Campo
                            etiqueta="Tipo de pieza"
                            nombre="tipo"
                            tipo="select"
                            valor={formData.tipo}
                            onChange={cambiarCampoForm}
                            opciones={TIPOS_MATERIAL.map((t) => ({ id: t.id, nombre: t.nombre }))}
                            error={erroresForm.tipo?.[0]}
                            requerido
                        />

                        <Campo
                            etiqueta="Nombre del archivo"
                            nombre="nombre_archivo"
                            valor={formData.nombre_archivo}
                            onChange={cambiarCampoForm}
                            placeholder="ej. spot_verano_30s.mp4"
                            ayuda={formData.origen === 'archivo' ? 'Se completa al elegir el archivo.' : undefined}
                            error={erroresForm.nombre_archivo?.[0]}
                            requerido={formData.tipo !== 'texto'}
                        />
                    </div>

                    <OrigenArchivo
                        datos={formData}
                        onCambiar={cambiarCampoForm}
                        onArchivo={elegirArchivo(setFormData, formData.tipo)}
                        errores={erroresForm}
                        requerido={formData.tipo !== 'texto'}
                    />

                    {['video', 'audio'].includes(formData.tipo) && (
                        <Campo
                            etiqueta="Duración exacta (segundos)"
                            nombre="duracion_seg"
                            tipo="number"
                            valor={formData.duracion_seg}
                            onChange={cambiarCampoForm}
                            placeholder="ej. 30"
                            error={erroresForm.duracion_seg?.[0]}
                            requerido
                        />
                    )}

                    <Campo
                        etiqueta="Texto / Guion / Copy publicitario"
                        nombre="texto_publicacion"
                        tipo="textarea"
                        valor={formData.texto_publicacion}
                        onChange={cambiarCampoForm}
                        placeholder="Texto que acompaña la publicación o copy del spot publicitario..."
                        error={erroresForm.texto_publicacion?.[0]}
                        requerido={formData.tipo === 'texto'}
                    />
                </form>
            </Modal>

            {/* Modal: Subir Nueva Versión */}
            <Modal
                titulo={`Subir nueva versión (v${(materialActual?.version ?? 0) + 1})`}
                abierto={modalVersion}
                onCerrar={() => setModalVersion(false)}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModalVersion(false)}>
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="btn btn-primario"
                            onClick={guardarNuevaVersion}
                            disabled={enviando}
                        >
                            {enviando ? 'Subiendo versión...' : 'Subir versión'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardarNuevaVersion} noValidate className="space-y-3">
                    <p className="text-sm text-texto-suave">
                        Suba el archivo corregido de acuerdo a las observaciones señaladas por Canal 11. El material
                        volverá al estado <strong className="text-aviso font-medium">Pendiente de revisión</strong>.
                    </p>

                    <OrigenArchivo
                        datos={formVersion}
                        onCambiar={cambiarCampoVersion}
                        onArchivo={elegirArchivo(setFormVersion, formVersion.tipo)}
                        errores={erroresForm}
                        requerido={materialActual?.tipo !== 'texto'}
                    />

                    <Campo
                        etiqueta="Nombre del archivo"
                        nombre="nombre_archivo"
                        valor={formVersion.nombre_archivo}
                        onChange={cambiarCampoVersion}
                        error={erroresForm.nombre_archivo?.[0]}
                        requerido={materialActual?.tipo !== 'texto'}
                    />

                    {['video', 'audio'].includes(materialActual?.tipo) && (
                        <Campo
                            etiqueta="Duración exacta (segundos)"
                            nombre="duracion_seg"
                            tipo="number"
                            valor={formVersion.duracion_seg}
                            onChange={cambiarCampoVersion}
                            error={erroresForm.duracion_seg?.[0]}
                            requerido
                        />
                    )}

                    <Campo
                        etiqueta="Texto / Copy actualizado"
                        nombre="texto_publicacion"
                        tipo="textarea"
                        valor={formVersion.texto_publicacion}
                        onChange={cambiarCampoVersion}
                        error={erroresForm.texto_publicacion?.[0]}
                        requerido={materialActual?.tipo === 'texto'}
                    />
                </form>
            </Modal>

            {/* Modal: Revisión Institucional */}
            <Modal
                titulo="Revisión institucional de material"
                abierto={modalRevision}
                onCerrar={() => setModalRevision(false)}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModalRevision(false)}>
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="btn btn-primario"
                            onClick={guardarRevision}
                            disabled={enviando}
                        >
                            {enviando ? 'Guardando...' : 'Emitir dictamen'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardarRevision} noValidate className="space-y-4">
                    <div>
                        <label className="etiqueta">Resultado de la revisión</label>
                        <div className="grid grid-cols-3 gap-2 mt-1">
                            <button
                                type="button"
                                onClick={() => setFormRevision((prev) => ({ ...prev, resultado: 'aprobado' }))}
                                className={`p-2.5 rounded-tema border text-xs font-semibold flex flex-col items-center gap-1 transition-colors ${
                                    formRevision.resultado === 'aprobado'
                                        ? 'bg-exito/15 border-exito text-exito'
                                        : 'border-borde hover:bg-superficie-2 text-texto-suave'
                                }`}
                            >
                                <CheckCircle2 size={18} />
                                Aprobar
                            </button>
                            <button
                                type="button"
                                onClick={() => setFormRevision((prev) => ({ ...prev, resultado: 'correccion' }))}
                                className={`p-2.5 rounded-tema border text-xs font-semibold flex flex-col items-center gap-1 transition-colors ${
                                    formRevision.resultado === 'correccion'
                                        ? 'bg-aviso/15 border-aviso text-aviso'
                                        : 'border-borde hover:bg-superficie-2 text-texto-suave'
                                }`}
                            >
                                <AlertTriangle size={18} />
                                Pedir corrección
                            </button>
                            <button
                                type="button"
                                onClick={() => setFormRevision((prev) => ({ ...prev, resultado: 'rechazado' }))}
                                className={`p-2.5 rounded-tema border text-xs font-semibold flex flex-col items-center gap-1 transition-colors ${
                                    formRevision.resultado === 'rechazado'
                                        ? 'bg-peligro/15 border-peligro text-peligro'
                                        : 'border-borde hover:bg-superficie-2 text-texto-suave'
                                }`}
                            >
                                <XCircle size={18} />
                                Rechazar
                            </button>
                        </div>
                    </div>

                    <Campo
                        etiqueta="Observaciones y recomendaciones"
                        nombre="observaciones"
                        tipo="textarea"
                        valor={formRevision.observaciones}
                        onChange={(c, v) => setFormRevision((prev) => ({ ...prev, [c]: v }))}
                        placeholder={
                            formRevision.resultado === 'aprobado'
                                ? 'Observaciones opcionales...'
                                : 'Detalle los motivos del rechazo o las correcciones que debe realizar el cliente...'
                        }
                        requerido={formRevision.resultado !== 'aprobado'}
                    />
                </form>
            </Modal>

            {/* Modal: Historial de Revisiones */}
            <Modal
                titulo="Historial de revisiones y detalles"
                abierto={modalHistorial}
                onCerrar={() => setModalHistorial(false)}
                pie={
                    <button type="button" className="btn btn-secundario" onClick={() => setModalHistorial(false)}>
                        Cerrar
                    </button>
                }
            >
                {materialActual && (
                    <div className="space-y-4">
                        <div className="p-3 rounded-tema bg-superficie-2/70 space-y-1 text-sm border border-borde">
                            <p className="font-semibold text-texto">{materialActual.nombre_archivo}</p>
                            <p className="text-xs text-texto-suave">
                                Campaña: <strong className="text-texto font-medium">{materialActual.campania?.nombre}</strong> · Versión: <strong className="text-texto font-medium">v{materialActual.version}</strong>
                            </p>
                            {materialActual.url_vista && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setModalHistorial(false);
                                        setMaterialVisto(materialActual);
                                    }}
                                    className="inline-flex items-center gap-1 text-xs text-primario hover:underline mt-1"
                                >
                                    <Eye size={12} aria-hidden /> Ver material
                                </button>
                            )}
                            {materialActual.texto_publicacion && (
                                <div className="mt-2 p-2 bg-superficie rounded border border-borde text-xs text-texto whitespace-pre-wrap">
                                    {materialActual.texto_publicacion}
                                </div>
                            )}
                        </div>

                        <div>
                            <h4 className="text-sm font-semibold mb-2">Línea de tiempo de revisiones</h4>
                            {cargandoHistorial ? (
                                <p className="text-xs text-texto-suave py-4 text-center">Cargando revisiones...</p>
                            ) : historialRevisiones.length === 0 ? (
                                <p className="text-xs text-texto-suave py-4 text-center">
                                    Esta pieza aún no tiene revisiones registradas.
                                </p>
                            ) : (
                                <div className="space-y-2.5">
                                    {historialRevisiones.map((rev) => {
                                        const metaRev = MAPA_ESTADO[rev.resultado] || { tono: 'neutro', nombre: rev.resultado };
                                        return (
                                            <div
                                                key={rev.id_revision}
                                                className="p-3 rounded-tema border border-borde bg-superficie text-sm space-y-1"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <Insignia tono={metaRev.tono}>{metaRev.nombre}</Insignia>
                                                    <span className="text-xs text-texto-suave">
                                                        {fechaHora(rev.fecha)}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-texto-suave">
                                                    Revisor:{' '}
                                                    <span className="text-texto font-medium">
                                                        {rev.usuario?.nombre_completo ?? 'Personal Canal 11'}
                                                    </span>
                                                </p>
                                                {rev.observaciones && (
                                                    <p className="text-xs text-texto bg-superficie-2/50 p-2 rounded border border-borde/60 mt-1">
                                                        {rev.observaciones}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </Modal>

            {/* Modal: Ver material */}
            <Modal
                titulo={materialVisto ? `${materialVisto.nombre_archivo} · v${materialVisto.version}` : ''}
                abierto={Boolean(materialVisto)}
                onCerrar={() => setMaterialVisto(null)}
                ancho="max-w-3xl"
                pie={
                    <button type="button" className="btn btn-secundario" onClick={() => setMaterialVisto(null)}>
                        Cerrar
                    </button>
                }
            >
                <VisorMaterial material={materialVisto} />
            </Modal>
        </div>
    );
}
