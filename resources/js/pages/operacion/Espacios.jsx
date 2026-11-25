import { useState, useEffect, useCallback } from 'react';
import { Plus, Pencil, Trash2, Calendar, Clock, Tv } from 'lucide-react';
import { api, mensajeError } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia from '../../components/Insignia';
import { fecha } from '../../lib/formato';

const ESTADOS_ESPACIO = [
    { id: 'disponible', nombre: 'Disponible', tono: 'exito' },
    { id: 'ocupado', nombre: 'Ocupado', tono: 'aviso' },
    { id: 'bloqueado', nombre: 'Bloqueado', tono: 'neutro' },
];

const MAPA_ESTADO = Object.fromEntries(ESTADOS_ESPACIO.map((e) => [e.id, e]));

export default function Espacios() {
    const { puede } = useAuth();
    const { exito, error, confirmar } = useAvisos();

    const [espacios, setEspacios] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [cargando, setCargando] = useState(false);
    const [filtros, setFiltros] = useState({ id_canal: '', estado: '', fecha: '', page: 1 });

    const [opcionesCanal, setOpcionesCanal] = useState([]);

    // Modal Form
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modo, setModo] = useState('crear');
    const [espacioActual, setEspacioActual] = useState(null);
    const [formData, setFormData] = useState({
        id_canal: '',
        fecha: '',
        hora_inicio: '',
        hora_fin: '',
        estado: 'disponible',
    });
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    const cargarCanales = useCallback(async () => {
        try {
            const { data } = await api.get('/opciones/canales');
            setOpcionesCanal(data.data || []);
        } catch {
            setOpcionesCanal([]);
        }
    }, []);

    const cargarEspacios = useCallback(async () => {
        setCargando(true);
        try {
            const params = {
                page: filtros.page,
                ...(filtros.id_canal ? { id_canal: filtros.id_canal } : {}),
                ...(filtros.estado ? { estado: filtros.estado } : {}),
                ...(filtros.fecha ? { fecha: filtros.fecha } : {}),
            };
            const { data } = await api.get('/espacios', { params });
            setEspacios(data.data || []);
            setMeta(data.meta || { current_page: 1, last_page: 1, total: 0 });
        } catch (err) {
            error(mensajeError(err));
        } finally {
            setCargando(false);
        }
    }, [filtros, error]);

    useEffect(() => {
        cargarCanales();
    }, [cargarCanales]);

    useEffect(() => {
        cargarEspacios();
    }, [cargarEspacios]);

    const abrirCrear = () => {
        setEspacioActual(null);
        setFormData({
            id_canal: opcionesCanal[0]?.id ? String(opcionesCanal[0].id) : '',
            fecha: new Date().toISOString().slice(0, 10),
            hora_inicio: '08:00',
            hora_fin: '08:30',
            estado: 'disponible',
        });
        setErrores({});
        setModo('crear');
        setModalAbierto(true);
    };

    const abrirEditar = (esp) => {
        setEspacioActual(esp);
        setFormData({
            id_canal: String(esp.id_canal),
            fecha: esp.fecha || '',
            hora_inicio: esp.hora_inicio?.slice(0, 5) || '',
            hora_fin: esp.hora_fin?.slice(0, 5) || '',
            estado: esp.estado,
        });
        setErrores({});
        setModo('editar');
        setModalAbierto(true);
    };

    const guardar = async (e) => {
        e.preventDefault();
        setEnviando(true);
        setErrores({});
        try {
            const payload = {
                ...formData,
                id_canal: Number(formData.id_canal),
            };
            if (modo === 'crear') {
                await api.post('/espacios', payload);
                exito('Espacio publicitario creado correctamente.');
            } else {
                await api.put(`/espacios/${espacioActual.id_espacio}`, payload);
                exito('Espacio publicitario actualizado correctamente.');
            }
            setModalAbierto(false);
            cargarEspacios();
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

    const eliminar = async (esp) => {
        if (await confirmar(`¿Eliminar el espacio de ${esp.canal?.nombre} (${esp.fecha} ${esp.hora_inicio?.slice(0, 5)}-${esp.hora_fin?.slice(0, 5)})?`)) {
            try {
                await api.delete(`/espacios/${esp.id_espacio}`);
                exito('Espacio eliminado correctamente.');
                cargarEspacios();
            } catch (err) {
                error(mensajeError(err));
            }
        }
    };

    const columnas = [
        {
            titulo: 'Canal de emisión',
            render: (esp) => (
                <div className="flex items-center gap-2">
                    <div className="p-1.5 rounded-tema bg-superficie-2 text-primario">
                        <Tv size={16} />
                    </div>
                    <div>
                        <p className="font-semibold text-texto leading-snug">{esp.canal?.nombre}</p>
                        <p className="text-xs text-texto-suave uppercase">{esp.canal?.tipo}</p>
                    </div>
                </div>
            ),
        },
        {
            titulo: 'Fecha programada',
            render: (esp) => (
                <div className="flex items-center gap-1.5 text-sm">
                    <Calendar size={14} className="text-texto-suave" />
                    <span>{fecha(esp.fecha)}</span>
                </div>
            ),
        },
        {
            titulo: 'Horario',
            render: (esp) => (
                <div className="flex items-center gap-1.5 text-sm font-medium">
                    <Clock size={14} className="text-texto-suave" />
                    <span>{esp.hora_inicio?.slice(0, 5)} → {esp.hora_fin?.slice(0, 5)}</span>
                </div>
            ),
        },
        {
            titulo: 'Estado',
            render: (esp) => {
                const metaEst = MAPA_ESTADO[esp.estado] || { tono: 'neutro', nombre: esp.estado };
                return <Insignia tono={metaEst.tono}>{metaEst.nombre}</Insignia>;
            },
        },
        {
            titulo: 'Pauta asociada',
            render: (esp) => esp.pauta ? (
                <div>
                    <p className="font-medium text-xs line-clamp-1">{esp.pauta.material?.nombre_archivo}</p>
                    <p className="text-[11px] text-texto-suave">{esp.pauta.material?.campania?.nombre}</p>
                </div>
            ) : (
                <span className="text-xs text-texto-suave italic">Libre</span>
            ),
        },
        {
            titulo: 'Acciones',
            className: 'text-right w-28',
            render: (esp) => (
                <div className="flex items-center justify-end gap-1">
                    {puede('espacios.editar') && esp.estado !== 'ocupado' && (
                        <button
                            type="button"
                            className="btn-icono"
                            onClick={() => abrirEditar(esp)}
                            title="Editar espacio"
                        >
                            <Pencil size={16} />
                        </button>
                    )}
                    {puede('espacios.eliminar') && esp.estado !== 'ocupado' && (
                        <button
                            type="button"
                            className="btn-icono text-peligro hover:bg-peligro/10"
                            onClick={() => eliminar(esp)}
                            title="Eliminar espacio"
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
                titulo="Espacios publicitarios"
                descripcion="Gestión de franjas horarias por canal y control de superposiciones para pautas."
                accion={
                    puede('espacios.crear') && (
                        <button type="button" className="btn btn-primario" onClick={abrirCrear}>
                            <Plus size={16} /> Crear espacio
                        </button>
                    )
                }
            />

            {/* Barra de Filtros */}
            <div className="tarjeta p-4 flex flex-wrap gap-3 items-center">
                <select
                    value={filtros.id_canal}
                    onChange={(e) => setFiltros((prev) => ({ ...prev, id_canal: e.target.value, page: 1 }))}
                    className="input w-auto text-sm"
                >
                    <option value="">Todos los canales</option>
                    {opcionesCanal.map((c) => (
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
                    {ESTADOS_ESPACIO.map((e) => (
                        <option key={e.id} value={e.id}>
                            {e.nombre}
                        </option>
                    ))}
                </select>

                <input
                    type="date"
                    value={filtros.fecha}
                    onChange={(e) => setFiltros((prev) => ({ ...prev, fecha: e.target.value, page: 1 }))}
                    className="input w-auto text-sm"
                />

                {(filtros.id_canal || filtros.estado || filtros.fecha) && (
                    <button
                        type="button"
                        onClick={() => setFiltros({ id_canal: '', estado: '', fecha: '', page: 1 })}
                        className="btn btn-secundario text-xs"
                    >
                        Limpiar filtros
                    </button>
                )}
            </div>

            {/* Tabla de registros */}
            <Tabla
                columnas={columnas}
                filas={espacios}
                cargando={cargando}
                meta={meta}
                onPagina={(page) => setFiltros((prev) => ({ ...prev, page }))}
                idFila="id_espacio"
                vacio="No se encontraron espacios publicitarios."
            />

            {/* Modal de Creación / Edición */}
            <Modal
                titulo={modo === 'crear' ? 'Crear espacio publicitario' : 'Editar espacio publicitario'}
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
                            onClick={guardar}
                            disabled={enviando}
                        >
                            {enviando ? 'Guardando...' : modo === 'crear' ? 'Crear espacio' : 'Guardar cambios'}
                        </button>
                    </>
                }
            >
                <form onSubmit={guardar} className="space-y-3">
                    <Campo
                        etiqueta="Canal de difusión"
                        nombre="id_canal"
                        tipo="select"
                        valor={formData.id_canal}
                        onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                        opciones={opcionesCanal}
                        error={errores.id_canal?.[0]}
                        requerido
                    />

                    <Campo
                        etiqueta="Fecha"
                        nombre="fecha"
                        tipo="date"
                        valor={formData.fecha}
                        onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                        error={errores.fecha?.[0]}
                        requerido
                    />

                    <div className="grid grid-cols-2 gap-3">
                        <Campo
                            etiqueta="Hora de inicio"
                            nombre="hora_inicio"
                            type="time"
                            valor={formData.hora_inicio}
                            onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                            error={errores.hora_inicio?.[0]}
                            requerido
                        />

                        <Campo
                            etiqueta="Hora de fin"
                            nombre="hora_fin"
                            type="time"
                            valor={formData.hora_fin}
                            onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                            error={errores.hora_fin?.[0]}
                            requerido
                        />
                    </div>

                    <Campo
                        etiqueta="Disponibilidad inicial"
                        nombre="estado"
                        tipo="select"
                        valor={formData.estado}
                        onChange={(c, v) => setFormData((prev) => ({ ...prev, [c]: v }))}
                        opciones={[
                            { id: 'disponible', nombre: 'Disponible' },
                            { id: 'bloqueado', nombre: 'Bloqueado (Mantenimiento/Reserva)' },
                        ]}
                    />
                </form>
            </Modal>
        </div>
    );
}
