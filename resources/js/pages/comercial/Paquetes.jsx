import { useState } from 'react';
import { Pencil, Plus, Power, Search, Trash2 } from 'lucide-react';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import { InsigniaActivo } from '../../components/Insignia';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { limpiarOpciones, useListado, useOpciones } from '../../hooks/useListado';
import { api, erroresCampos, mensajeError } from '../../lib/api';
import { moneda } from '../../lib/formato';
import { validar } from '../../lib/validar';

const INICIAL = {
    nombre: '',
    descripcion: '',
    descuento: '0',
    items: [{ id_tarifa: '', cantidad: '1' }],
};

export default function Paquetes() {
    const { puede } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const listado = useListado('/paquetes', { activo: '' });
    const { opciones: tarifas } = useOpciones('tarifas');
    const [modo, setModo] = useState(null);
    const [actual, setActual] = useState(null);
    const [form, setForm] = useState(INICIAL);
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const mapaTarifas = Object.fromEntries(tarifas.map((t) => [String(t.id), Number(t.monto ?? 0)]));

    const subtotal = form.items.reduce((acc, it) => {
        const precio = mapaTarifas[String(it.id_tarifa)] ?? 0;
        const cant = Math.max(0, Number(it.cantidad) || 0);
        return acc + precio * cant;
    }, 0);
    const descPct = Math.min(100, Math.max(0, Number(form.descuento) || 0));
    const precioTotal = subtotal * (1 - descPct / 100);

    const abrirCrear = () => {
        setActual(null);
        setForm({ ...INICIAL, items: [{ id_tarifa: '', cantidad: '1' }] });
        setErrores({});
        setErrorGeneral(null);
        setModo('crear');
    };

    const abrirEditar = (p) => {
        setActual(p);
        setForm({
            nombre: p.nombre,
            descripcion: p.descripcion ?? '',
            descuento: String(p.descuento ?? 0),
            items: p.items?.length
                ? p.items.map((i) => ({ id_tarifa: String(i.id_tarifa), cantidad: String(i.cantidad) }))
                : [{ id_tarifa: '', cantidad: '1' }],
        });
        setErrores({});
        setErrorGeneral(null);
        setModo('editar');
    };

    const cambiarCampo = (nombre, valor) => {
        setForm((f) => ({ ...f, [nombre]: valor }));
        if (errores[nombre]) setErrores(({ [nombre]: _, ...r }) => r);
    };

    const cambiarItem = (idx, campo, valor) => {
        setForm((f) => ({
            ...f,
            items: f.items.map((it, i) => (i === idx ? { ...it, [campo]: valor } : it)),
        }));
    };

    const agregarItem = () => setForm((f) => ({ ...f, items: [...f.items, { id_tarifa: '', cantidad: '1' }] }));
    const quitarItem = (idx) => setForm((f) => ({ ...f, items: f.items.filter((_, i) => i !== idx) }));

    const guardar = async (e) => {
        e.preventDefault();
        setErrorGeneral(null);
        const locales = validar(form, {
            nombre: { requerido: true, min: 2, max: 150, etiqueta: 'nombre' },
            descripcion: { max: 500, etiqueta: 'descripción' },
            descuento: { minValor: 0, maxValor: 100, etiqueta: 'descuento' },
        });
        if (!form.items.length || form.items.some((i) => !i.id_tarifa || Number(i.cantidad) < 1)) {
            locales.items = 'Seleccione una tarifa y cantidad válida (mínimo 1) en cada fila.';
        }
        setErrores(locales);
        if (Object.keys(locales).length) {
            setErrorGeneral('Revise los campos marcados en rojo.');
            return;
        }

        const payload = {
            nombre: form.nombre,
            descripcion: form.descripcion,
            descuento: Number(form.descuento || 0),
            items: form.items.map((i) => ({ id_tarifa: Number(i.id_tarifa), cantidad: Number(i.cantidad) })),
        };

        setEnviando(true);
        try {
            const { data } = modo === 'crear'
                ? await api.post('/paquetes', payload)
                : await api.put(`/paquetes/${actual.id_paquete}`, payload);
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

    const alternarEstado = async (p) => {
        const ok = await confirmar(
            p.activo ? '¿Dar de baja este paquete? Podrá reactivarlo después.' : '¿Reactivar este paquete?',
            { titulo: p.activo ? 'Dar de baja' : 'Reactivar', textoAceptar: p.activo ? 'Dar de baja' : 'Reactivar', peligro: p.activo },
        );
        if (!ok) return;
        try {
            const { data } = await api.patch(`/paquetes/${p.id_paquete}/estado`, { activo: !p.activo });
            exito(data.message);
            limpiarOpciones();
            listado.recargar();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const columnas = [
        { titulo: 'ID', campo: 'id_paquete', className: 'w-14' },
        {
            titulo: 'Paquete',
            render: (p) => (
                <div>
                    <p className="font-medium">{p.nombre}</p>
                    {p.descripcion && <p className="text-xs text-texto-suave line-clamp-2">{p.descripcion}</p>}
                </div>
            ),
        },
        {
            titulo: 'Tarifas incluidas',
            render: (p) => (
                <ul className="text-xs space-y-0.5">
                    {(p.items ?? []).map((it) => (
                        <li key={`${it.id_paquete}-${it.id_tarifa}`}>
                            {it.cantidad}× {it.tarifa?.servicio?.nombre ?? `Tarifa #${it.id_tarifa}`} ({moneda(it.tarifa?.monto)})
                        </li>
                    ))}
                </ul>
            ),
        },
        { titulo: 'Descuento', render: (p) => `${Number(p.descuento ?? 0)}%`, className: 'text-center' },
        { titulo: 'Precio total', render: (p) => <span className="font-semibold">{moneda(p.precio_total)}</span> },
        { titulo: 'Estado', render: (p) => <InsigniaActivo activo={p.activo} /> },
        ...(puede('paquetes.editar') || puede('paquetes.eliminar')
            ? [{
                titulo: <span className="sr-only">Acciones</span>,
                className: 'text-right whitespace-nowrap',
                render: (p) => (
                    <div className="flex justify-end gap-1">
                        {puede('paquetes.editar') && (
                            <button className="btn-icono" onClick={() => abrirEditar(p)} title="Editar" aria-label="Editar paquete">
                                <Pencil size={16} />
                            </button>
                        )}
                        {puede('paquetes.eliminar') && (
                            <button
                                className={`btn-icono ${p.activo ? 'hover:!text-peligro' : 'hover:!text-exito'}`}
                                onClick={() => alternarEstado(p)}
                                title={p.activo ? 'Dar de baja' : 'Reactivar'}
                                aria-label={p.activo ? 'Dar de baja paquete' : 'Reactivar paquete'}
                            >
                                <Power size={16} />
                            </button>
                        )}
                    </div>
                ),
            }]
            : []),
    ];

    return (
        <div>
            <EncabezadoPagina titulo="Paquetes publicitarios" descripcion="Combos de tarifas con descuento automático y precio total calculado.">
                {puede('paquetes.crear') && (
                    <button className="btn btn-primario" onClick={abrirCrear}>
                        <Plus size={16} /> Nuevo paquete
                    </button>
                )}
            </EncabezadoPagina>

            <div className="flex flex-col sm:flex-row gap-2 mb-4">
                <label className="relative flex-1">
                    <span className="sr-only">Buscar</span>
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" aria-hidden />
                    <input className="input !pl-9" placeholder="Buscar paquete por nombre o descripción…" value={listado.filtros.q} onChange={(e) => listado.setFiltro('q', e.target.value)} />
                </label>
                <label className="sm:w-48">
                    <span className="sr-only">Estado</span>
                    <select className="input" value={listado.filtros.activo} onChange={(e) => listado.setFiltro('activo', e.target.value)}>
                        <option value="">Estado: todos</option>
                        <option value="true">Activos</option>
                        <option value="false">Inactivos</option>
                    </select>
                </label>
            </div>

            {listado.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{listado.error}</p>}

            <Tabla columnas={columnas} filas={listado.filas} cargando={listado.cargando} meta={listado.meta} onPagina={listado.setPagina} idFila="id_paquete" />

            <Modal
                titulo={modo === 'crear' ? 'Nuevo paquete' : 'Editar paquete'}
                abierto={modo !== null}
                onCerrar={() => setModo(null)}
                ancho="max-w-2xl"
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModo(null)}>Cancelar</button>
                        <button type="submit" form="form-paquete" className="btn btn-primario" disabled={enviando}>
                            {enviando ? 'Guardando…' : 'Guardar'}
                        </button>
                    </>
                }
            >
                <form id="form-paquete" onSubmit={guardar} noValidate className="space-y-4">
                    {errorGeneral && <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{errorGeneral}</p>}
                    <div className="grid sm:grid-cols-2 gap-4">
                        <Campo etiqueta="Nombre del paquete" nombre="nombre" valor={form.nombre} onChange={cambiarCampo} error={errores.nombre} requerido />
                        <Campo etiqueta="Descuento (%)" nombre="descuento" tipo="number" valor={form.descuento} onChange={cambiarCampo} error={errores.descuento} min={0} max={100} step="0.01" />
                    </div>
                    <Campo etiqueta="Descripción" nombre="descripcion" tipo="textarea" valor={form.descripcion} onChange={cambiarCampo} error={errores.descripcion} />

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <span className="etiqueta !mb-0">Tarifas incluidas <span className="text-peligro">*</span></span>
                            <button type="button" className="btn btn-secundario !py-1 !px-2.5 text-xs" onClick={agregarItem}>
                                <Plus size={14} /> Agregar tarifa
                            </button>
                        </div>
                        {errores.items && <p className="texto-error mb-2" role="alert">{errores.items}</p>}
                        <div className="space-y-2">
                            {form.items.map((it, idx) => (
                                <div key={idx} className="grid grid-cols-12 gap-2 items-center">
                                    <select
                                        className="input col-span-8"
                                        value={it.id_tarifa}
                                        onChange={(e) => cambiarItem(idx, 'id_tarifa', e.target.value)}
                                        aria-label={`Tarifa fila ${idx + 1}`}
                                    >
                                        <option value="">Seleccione una tarifa…</option>
                                        {tarifas.map((t) => (
                                            <option key={t.id} value={t.id}>{t.nombre}</option>
                                        ))}
                                    </select>
                                    <input
                                        type="number"
                                        min={1}
                                        className="input col-span-3"
                                        value={it.cantidad}
                                        onChange={(e) => cambiarItem(idx, 'cantidad', e.target.value)}
                                        aria-label={`Cantidad fila ${idx + 1}`}
                                    />
                                    <div className="col-span-1 flex justify-end">
                                        {form.items.length > 1 && (
                                            <button type="button" className="btn-icono hover:!text-peligro" onClick={() => quitarItem(idx)} aria-label="Quitar fila">
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
                        <span>Descuento ({descPct}%): <strong>-{moneda(subtotal - precioTotal)}</strong></span>
                        <span className="text-primario font-bold">Precio total: {moneda(precioTotal)}</span>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

