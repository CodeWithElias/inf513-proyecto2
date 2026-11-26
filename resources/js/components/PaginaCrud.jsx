import { useState } from 'react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useAvisos } from '../context/AvisosContext';
import { limpiarOpciones, useListado, useOpciones } from '../hooks/useListado';
import { useFormulario } from '../hooks/useFormulario';
import { api, mensajeError } from '../lib/api';
import EncabezadoPagina from './EncabezadoPagina';
import Tabla from './Tabla';
import Modal from './Modal';
import Campo from './Campo';

/**
 * Pantalla CRUD configurable (plantilla para todos los módulos).
 *
 * <PaginaCrud
 *   titulo="Usuarios" descripcion="..." singular="usuario"
 *   endpoint="/usuarios" privilegio="usuarios" idCampo="id_usuario"
 *   columnas={[{ titulo: 'Nombre', campo: 'nombre_completo' }, ...]}
 *   campos={[{ nombre: 'email', etiqueta: 'Correo', tipo: 'email', reglas: { requerido: true, email: true } },
 *            { nombre: 'id_rol', etiqueta: 'Rol', tipo: 'select', opciones: 'roles', reglas: { requerido: true } }]}
 *   aFormulario={(fila) => ({ ...valores para editar })}
 *   estado={{ activo: (fila) => fila.activo, endpoint: (fila) => `/usuarios/${fila.id_usuario}/estado` }}
 *   filtros={[{ nombre: 'activo', etiqueta: 'Estado', opciones: [{ id: 'true', nombre: 'Activos' }] }]}
 *   acciones={(fila, recargar) => <button .../>}
 * />
 * Privilegios usados: {privilegio}.crear, .editar, .eliminar (para el cambio de estado).
 * campos[].reglas puede ser una función (modo) => reglas, con modo 'crear' | 'editar'.
 * campos[].soloCrear / soloEditar ocultan el campo en el otro modo.
 */
export default function PaginaCrud({
    titulo,
    descripcion,
    singular = 'registro',
    endpoint,
    privilegio,
    idCampo = 'id',
    columnas,
    campos = [],
    aFormulario,
    estado,
    filtros = [],
    acciones,
    placeholderBusqueda = 'Buscar…',
    busqueda = true,
    editable = true,
    creable = true,
    anchoModal,
    botonesEncabezado = null,
}) {
    const { puede } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const listado = useListado(endpoint, Object.fromEntries(filtros.map((f) => [f.nombre, f.valorInicial ?? ''])));
    const [modo, setModo] = useState(null); // null | 'crear' | 'editar'
    const [fila, setFila] = useState(null);

    const vacios = Object.fromEntries(campos.map((c) => [c.nombre, c.valorInicial ?? '']));
    const form = useFormulario(vacios);

    const camposVisibles = campos.filter((c) => !(modo === 'editar' && c.soloCrear) && !(modo === 'crear' && c.soloEditar));
    const reglas = Object.fromEntries(
        camposVisibles.map((c) => {
            const r = typeof c.reglas === 'function' ? c.reglas(modo) : (c.reglas ?? {});
            return [c.nombre, { etiqueta: c.etiqueta.toLowerCase(), ...r }];
        }),
    );

    const abrirCrear = () => {
        form.reiniciar(vacios);
        setFila(null);
        setModo('crear');
    };
    const abrirEditar = (f) => {
        form.reiniciar({ ...vacios, ...(aFormulario ? aFormulario(f) : f) });
        setFila(f);
        setModo('editar');
    };
    const cerrar = () => setModo(null);

    const guardar = async (e) => {
        e.preventDefault();
        const r = await form.enviar(
            (v) => (modo === 'crear' ? api.post(endpoint, v) : api.put(`${endpoint}/${fila[idCampo]}`, v)),
            reglas,
        );
        if (r) {
            exito(r.data.message ?? 'Guardado correctamente.');
            cerrar();
            limpiarOpciones(); // el registro nuevo o editado puede aparecer en otros selects
            listado.recargar();
        }
    };

    const alternarEstado = async (f) => {
        const activo = estado.activo(f);
        const ok = await confirmar(activo ? `¿Dar de baja este ${singular}? Podrá reactivarlo después.` : `¿Reactivar este ${singular}?`, {
            titulo: activo ? 'Dar de baja' : 'Reactivar',
            textoAceptar: activo ? 'Dar de baja' : 'Reactivar',
            peligro: activo,
        });
        if (!ok) return;
        try {
            const { data } = await api.patch(estado.endpoint(f), { activo: !activo });
            exito(data.message);
            limpiarOpciones(); // las listas de opciones solo muestran registros activos
            listado.recargar();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const puedeEditar = editable && campos.length > 0 && puede(`${privilegio}.editar`);
    const puedeEstado = estado && puede(`${privilegio}.eliminar`);
    const columnasFinales = [
        ...columnas,
        ...(puedeEditar || puedeEstado || acciones
            ? [
                  {
                      titulo: <span className="sr-only">Acciones</span>,
                      className: 'text-right whitespace-nowrap',
                      render: (f) => (
                          <div className="flex justify-end gap-1">
                              {acciones?.(f, listado.recargar)}
                              {puedeEditar && (
                                  <button className="btn-icono" onClick={() => abrirEditar(f)} aria-label={`Editar ${singular}`} title="Editar">
                                      <Pencil size={16} />
                                  </button>
                              )}
                              {puedeEstado && (
                                  <button
                                      className={`btn-icono ${estado.activo(f) ? 'hover:!text-peligro' : 'hover:!text-exito'}`}
                                      onClick={() => alternarEstado(f)}
                                      aria-label={estado.activo(f) ? `Dar de baja ${singular}` : `Reactivar ${singular}`}
                                      title={estado.activo(f) ? 'Dar de baja' : 'Reactivar'}
                                  >
                                      <Power size={16} />
                                  </button>
                              )}
                          </div>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <div>
            <EncabezadoPagina titulo={titulo} descripcion={descripcion}>
                {botonesEncabezado}
                {creable && campos.length > 0 && puede(`${privilegio}.crear`) && (
                    <button className="btn btn-primario" onClick={abrirCrear}>
                        <Plus size={16} /> Nuevo {singular}
                    </button>
                )}
            </EncabezadoPagina>

            <div className="flex flex-col sm:flex-row gap-2 mb-4">
                {busqueda ? (
                    <label className="relative flex-1">
                        <span className="sr-only">Buscar</span>
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" aria-hidden />
                        <input className="input !pl-9" placeholder={placeholderBusqueda} value={listado.filtros.q} onChange={(e) => listado.setFiltro('q', e.target.value)} />
                    </label>
                ) : (
                    <div className="flex-1" />
                )}
                {filtros.map((f) => (
                    <FiltroSelect key={f.nombre} filtro={f} valor={listado.filtros[f.nombre]} onChange={(v) => listado.setFiltro(f.nombre, v)} />
                ))}
            </div>

            {listado.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{listado.error}</p>}

            <Tabla columnas={columnasFinales} filas={listado.filas} cargando={listado.cargando} meta={listado.meta} onPagina={listado.setPagina} idFila={idCampo} />

            <Modal
                titulo={modo === 'crear' ? `Nuevo ${singular}` : `Editar ${singular}`}
                abierto={modo !== null}
                onCerrar={cerrar}
                ancho={anchoModal}
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={cerrar}>Cancelar</button>
                        <button type="submit" form="form-crud" className="btn btn-primario" disabled={form.enviando}>
                            {form.enviando ? 'Guardando…' : 'Guardar'}
                        </button>
                    </>
                }
            >
                <form id="form-crud" onSubmit={guardar} noValidate className="grid gap-4 sm:grid-cols-2">
                    {form.errorGeneral && (
                        <p className="sm:col-span-2 text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{form.errorGeneral}</p>
                    )}
                    {camposVisibles.map((c) => (
                        <div key={c.nombre} className={c.ancho === 'medio' ? '' : 'sm:col-span-2'}>
                            <CampoCrud campo={c} modo={modo} form={form} requerido={reglas[c.nombre]?.requerido} />
                        </div>
                    ))}
                </form>
            </Modal>
        </div>
    );
}

function CampoCrud({ campo, modo, form, requerido }) {
    const remoto = typeof campo.opciones === 'string';
    const { opciones: remotas, cargando } = useOpciones(remoto ? campo.opciones : null);
    const opciones = remoto ? remotas : (campo.opciones ?? []);
    return (
        <Campo
            etiqueta={campo.etiqueta}
            nombre={campo.nombre}
            tipo={campo.tipo}
            valor={form.valores[campo.nombre]}
            onChange={form.cambiar}
            error={form.errores[campo.nombre]}
            requerido={requerido}
            opciones={opciones}
            ayuda={typeof campo.ayuda === 'function' ? campo.ayuda(modo) : campo.ayuda}
            placeholder={campo.placeholder}
            vacio={remoto && cargando ? 'Cargando opciones…' : campo.vacio}
            deshabilitado={(modo === 'editar' && campo.bloqueadoAlEditar) || (remoto && cargando)}
            autoComplete={campo.tipo === 'password' ? 'new-password' : undefined}
        />
    );
}

function FiltroSelect({ filtro, valor, onChange }) {
    const { opciones: remotas } = useOpciones(typeof filtro.opciones === 'string' ? filtro.opciones : null);
    const opciones = typeof filtro.opciones === 'string' ? remotas : filtro.opciones;
    return (
        <label className="sm:w-48">
            <span className="sr-only">{filtro.etiqueta}</span>
            <select className="input" value={valor ?? ''} onChange={(e) => onChange(e.target.value)}>
                <option value="">{filtro.todos ?? `${filtro.etiqueta}: todos`}</option>
                {opciones.map((o) => (
                    <option key={o.id} value={o.id}>{o.nombre}</option>
                ))}
            </select>
        </label>
    );
}

/** Filtro estándar activo / inactivo. */
export const FILTRO_ESTADO = {
    nombre: 'activo',
    etiqueta: 'Estado',
    opciones: [
        { id: 'true', nombre: 'Activos' },
        { id: 'false', nombre: 'Inactivos' },
    ],
};
