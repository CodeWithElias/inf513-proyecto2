import { useState, useEffect, useCallback } from 'react';
import {
    Plus,
    BarChart3,
    Eye,
    Users,
    MousePointerClick,
    Share2,
    Calendar,
    Tv,
    TrendingUp,
} from 'lucide-react';
import { api, mensajeError } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import { fecha, fechaHora, numero } from '../../lib/formato';
import { validarLista } from '../../lib/validar';

/** Reglas en el cliente (las mismas de MetricaRequest en el servidor). */
const CONTEO = { entero: true, minValor: 0, maxValor: 2147483647 };
const REGLAS_METRICA = {
    id_programacion: { requerido: true, etiqueta: 'pauta' },
    alcance: { requerido: true, ...CONTEO, etiqueta: 'alcance' },
    impresiones: { requerido: true, ...CONTEO, etiqueta: 'impresiones' },
    interacciones: { ...CONTEO, etiqueta: 'interacciones' },
    clics: { ...CONTEO, etiqueta: 'clics' },
    compartidos: { ...CONTEO, etiqueta: 'compartidos' },
};

export default function Metricas() {
    const { puede } = useAuth();
    const { exito, error } = useAvisos();

    const [metricas, setMetricas] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [resumen, setResumen] = useState({
        total_alcance: 0,
        total_impresiones: 0,
        total_interacciones: 0,
        total_clics: 0,
        total_compartidos: 0,
    });
    const [cargando, setCargando] = useState(false);
    const [pagina, setPagina] = useState(1);

    // Opciones para modal
    const [opcionesPauta, setOpcionesPauta] = useState([]);

    // Modal Registrar
    const [modalAbierto, setModalAbierto] = useState(false);
    const [formData, setFormData] = useState({
        id_programacion: '',
        alcance: '0',
        impresiones: '0',
        interacciones: '0',
        clics: '0',
        compartidos: '0',
    });
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    const cargarOpciones = useCallback(async () => {
        try {
            const { data } = await api.get('/opciones/pautas-emitidas');
            setOpcionesPauta(data.data || []);
        } catch {
            setOpcionesPauta([]);
        }
    }, []);

    const cargarDatos = useCallback(async () => {
        setCargando(true);
        try {
            const [rList, rRes] = await Promise.all([
                api.get('/metricas', { params: { page: pagina } }),
                api.get('/metricas/resumen'),
            ]);
            setMetricas(rList.data?.data || []);
            setMeta(rList.data?.meta || { current_page: 1, last_page: 1, total: 0 });
            setResumen(rRes.data?.data || {});
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setCargando(false);
        }
    }, [pagina, error]);

    useEffect(() => {
        cargarOpciones();
    }, [cargarOpciones]);

    useEffect(() => {
        cargarDatos();
    }, [cargarDatos]);

    const abrirRegistrar = () => {
        setFormData({
            id_programacion: opcionesPauta[0]?.id ? String(opcionesPauta[0].id) : '',
            alcance: '100',
            impresiones: '120',
            interacciones: '15',
            clics: '10',
            compartidos: '2',
        });
        setErrores({});
        setModalAbierto(true);
    };

    const guardarMetrica = async (e) => {
        e.preventDefault();
        const locales = validarLista(formData, REGLAS_METRICA);
        if (Object.keys(locales).length) {
            setErrores(locales);
            error('Revise los campos marcados en rojo.');
            return;
        }
        setEnviando(true);
        setErrores({});
        try {
            await api.post('/metricas', {
                id_programacion: Number(formData.id_programacion),
                alcance: Number(formData.alcance),
                impresiones: Number(formData.impresiones),
                interacciones: Number(formData.interacciones),
                clics: Number(formData.clics),
                compartidos: Number(formData.compartidos),
            });
            exito('Medición de impacto registrada correctamente.');
            setModalAbierto(false);
            cargarDatos();
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

    const columnas = [
        {
            titulo: 'Pauta / Material',
            render: (m) => (
                <div>
                    <p className="font-semibold text-texto leading-snug">
                        {m.pauta?.material?.nombre_archivo ?? `Pauta #${m.id_programacion}`}
                    </p>
                    <p className="text-xs text-texto-suave">
                        {m.pauta?.material?.campania?.nombre}
                        {m.pauta?.espacio?.canal && ` · ${m.pauta.espacio.canal.nombre}`}
                    </p>
                </div>
            ),
        },
        {
            titulo: 'Fecha medición',
            className: 'text-xs text-texto-suave',
            render: (m) => fechaHora(m.fecha_medicion),
        },
        {
            titulo: 'Impresiones',
            className: 'text-right font-semibold',
            render: (m) => numero(m.impresiones),
        },
        {
            titulo: 'Alcance',
            className: 'text-right font-medium',
            render: (m) => numero(m.alcance),
        },
        {
            titulo: 'Interacciones',
            className: 'text-right text-primario font-semibold',
            render: (m) => numero(m.interacciones),
        },
        {
            titulo: 'Clics',
            className: 'text-right',
            render: (m) => numero(m.clics),
        },
        {
            titulo: 'Compartidos',
            className: 'text-right',
            render: (m) => numero(m.compartidos),
        },
        {
            titulo: 'Tasa engagement',
            className: 'text-right',
            render: (m) => {
                const tasa = m.impresiones > 0 ? ((m.interacciones / m.impresiones) * 100).toFixed(1) : 0;
                return (
                    <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-superficie-2 border border-borde">
                        {tasa}%
                    </span>
                );
            },
        },
    ];

    return (
        <div className="space-y-6">
            <EncabezadoPagina
                titulo="Métricas y análisis de impacto"
                descripcion="Seguimiento de alcance, visualizaciones, clics y repercusión de las pautas publicitarias emitidas."
                accion={
                    puede('metricas.crear') && (
                        <button type="button" className="btn btn-primario" onClick={abrirRegistrar}>
                            <Plus size={16} /> Registrar medición
                        </button>
                    )
                }
            />

            {/* Tarjetas KPI de impacto acumulado */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="tarjeta p-4 flex items-center gap-3">
                    <div className="p-3 rounded-tema bg-primario-suave text-primario">
                        <Users size={22} />
                    </div>
                    <div>
                        <p className="text-xs text-texto-suave uppercase font-semibold">Alcance total</p>
                        <p className="text-xl font-bold text-texto">{numero(resumen.total_alcance)}</p>
                    </div>
                </div>

                <div className="tarjeta p-4 flex items-center gap-3">
                    <div className="p-3 rounded-tema bg-superficie-2 text-texto">
                        <Eye size={22} />
                    </div>
                    <div>
                        <p className="text-xs text-texto-suave uppercase font-semibold">Impresiones / Vistas</p>
                        <p className="text-xl font-bold text-texto">{numero(resumen.total_impresiones)}</p>
                    </div>
                </div>

                <div className="tarjeta p-4 flex items-center gap-3">
                    <div className="p-3 rounded-tema bg-exito/15 text-exito">
                        <TrendingUp size={22} />
                    </div>
                    <div>
                        <p className="text-xs text-texto-suave uppercase font-semibold">Interacciones</p>
                        <p className="text-xl font-bold text-texto">{numero(resumen.total_interacciones)}</p>
                    </div>
                </div>

                <div className="tarjeta p-4 flex items-center gap-3">
                    <div className="p-3 rounded-tema bg-aviso/15 text-aviso">
                        <MousePointerClick size={22} />
                    </div>
                    <div>
                        <p className="text-xs text-texto-suave uppercase font-semibold">Clics generados</p>
                        <p className="text-xl font-bold text-texto">{numero(resumen.total_clics)}</p>
                    </div>
                </div>
            </div>

            {/* Tabla de registros */}
            <Tabla
                columnas={columnas}
                filas={metricas}
                cargando={cargando}
                meta={meta}
                onPagina={(p) => setPagina(p)}
                idFila="id_metrica"
                vacio="No se encontraron mediciones de impacto registradas."
            />

            {/* Modal Registrar Medición */}
            <Modal
                titulo="Registrar medición de impacto"
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModalAbierto(false)}>
                            Cancelar
                        </button>
                        <button
                            type="button"
                            className="btn btn-primario"
                            onClick={guardarMetrica}
                            disabled={enviando}
                        >
                            {enviando ? 'Guardando...' : 'Guardar medición'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardarMetrica} noValidate className="space-y-3">
                    <Campo
                        etiqueta="Pauta publicitaria"
                        nombre="id_programacion"
                        tipo="select"
                        valor={formData.id_programacion}
                        onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                        opciones={opcionesPauta}
                        error={errores.id_programacion?.[0]}
                        requerido
                    />

                    <div className="grid grid-cols-2 gap-3">
                        <Campo
                            etiqueta="Impresiones / Visualizaciones"
                            nombre="impresiones"
                            type="number"
                            valor={formData.impresiones}
                            onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                            error={errores.impresiones?.[0]}
                            requerido
                        />

                        <Campo
                            etiqueta="Alcance (usuarios únicos)"
                            nombre="alcance"
                            type="number"
                            valor={formData.alcance}
                            onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                            ayuda="Debe ser menor o igual a las impresiones"
                            error={errores.alcance?.[0]}
                            requerido
                        />
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <Campo
                            etiqueta="Interacciones (Likes/Comentarios)"
                            nombre="interacciones"
                            type="number"
                            valor={formData.interacciones}
                            onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                            error={errores.interacciones?.[0]}
                        />

                        <Campo
                            etiqueta="Clics generados"
                            nombre="clics"
                            type="number"
                            valor={formData.clics}
                            onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                            error={errores.clics?.[0]}
                        />

                        <Campo
                            etiqueta="Compartidos"
                            nombre="compartidos"
                            type="number"
                            valor={formData.compartidos}
                            onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                            error={errores.compartidos?.[0]}
                        />
                    </div>
                </form>
            </Modal>
        </div>
    );
}
