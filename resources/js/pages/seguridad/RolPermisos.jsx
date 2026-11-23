import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, Save } from 'lucide-react';
import { api, mensajeError } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import EncabezadoPagina from '../../components/EncabezadoPagina';

const ACCIONES = [
    ['ver', 'Ver'],
    ['crear', 'Registrar'],
    ['editar', 'Modificar'],
    ['eliminar', 'Baja / reactivar'],
    ['otros', 'Otros'],
];

/** Matriz de control de acceso: recursos (filas) × acciones (columnas) para un rol. */
export default function RolPermisos() {
    const { id } = useParams();
    const { puede, usuario, recargar } = useAuth();
    const { exito, error } = useAvisos();
    const [rol, setRol] = useState(null);
    const [modulos, setModulos] = useState([]);
    const [marcados, setMarcados] = useState(new Set());
    const [guardando, setGuardando] = useState(false);
    const editable = puede('roles.permisos');

    const aplicar = (d) => {
        setRol(d.rol);
        setModulos(d.modulos);
        setMarcados(new Set(d.modulos.flatMap((m) => m.privilegios.filter((p) => p.asignado).map((p) => p.id_privilegio))));
    };

    useEffect(() => {
        api.get(`/roles/${id}/privilegios`).then(({ data }) => aplicar(data.data)).catch((e) => error(mensajeError(e)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    // Agrupa: módulo -> recurso -> { ver, crear, ..., otros: [] }
    const tabla = useMemo(
        () =>
            modulos.map((m) => {
                const recursos = {};
                for (const p of m.privilegios) {
                    const r = (recursos[p.recurso] ??= { recurso: p.recurso, nombre: p.recurso.replace('_', ' '), acciones: {}, otros: [] });
                    if (['ver', 'crear', 'editar', 'eliminar'].includes(p.accion)) r.acciones[p.accion] = p;
                    else r.otros.push(p);
                    if (p.accion === 'ver') r.nombre = p.descripcion.replace(/^Ver /, '');
                }
                return { modulo: m.modulo, recursos: Object.values(recursos) };
            }),
        [modulos],
    );

    const alternar = (idp) =>
        setMarcados((s) => {
            const n = new Set(s);
            n.has(idp) ? n.delete(idp) : n.add(idp);
            return n;
        });

    const alternarModulo = (m, valor) =>
        setMarcados((s) => {
            const n = new Set(s);
            modulos.find((x) => x.modulo === m)?.privilegios.forEach((p) => (valor ? n.add(p.id_privilegio) : n.delete(p.id_privilegio)));
            return n;
        });

    const guardar = async () => {
        setGuardando(true);
        try {
            const { data } = await api.put(`/roles/${id}/privilegios`, { privilegios: [...marcados] });
            aplicar(data.data);
            exito(data.message);
            if (Number(id) === usuario.id_rol) recargar(); // actualiza el propio menú
        } catch (e) {
            error(mensajeError(e));
        } finally {
            setGuardando(false);
        }
    };

    if (!rol) return <p className="text-texto-suave">Cargando permisos…</p>;

    const casilla = (p, etiqueta) =>
        p ? (
            <input
                type="checkbox"
                className="size-4 cursor-pointer accent-[var(--primario)]"
                checked={marcados.has(p.id_privilegio)}
                onChange={() => alternar(p.id_privilegio)}
                disabled={!editable}
                aria-label={etiqueta}
                title={p.descripcion}
            />
        ) : (
            <span className="text-texto-suave" aria-hidden>·</span>
        );

    return (
        <div>
            <EncabezadoPagina titulo={`Permisos del rol: ${rol.nombre}`} descripcion={`${marcados.size} permisos seleccionados. El menú del usuario se arma con estos permisos.`}>
                <Link to="/roles" className="btn btn-secundario"><ArrowLeft size={16} /> Volver</Link>
                {editable && (
                    <button className="btn btn-primario" onClick={guardar} disabled={guardando}>
                        <Save size={16} /> {guardando ? 'Guardando…' : 'Guardar cambios'}
                    </button>
                )}
            </EncabezadoPagina>

            {!editable && <p className="tarjeta p-3 mb-4 text-sm text-aviso">Solo lectura: su rol no puede modificar permisos.</p>}

            <div className="space-y-5">
                {tabla.map((m) => (
                    <section key={m.modulo} className="tarjeta overflow-hidden">
                        <div className="flex items-center justify-between px-4 py-3 bg-superficie-2">
                            <h2 className="font-semibold">{m.modulo}</h2>
                            {editable && (
                                <div className="flex gap-2 text-xs">
                                    <button className="underline cursor-pointer" onClick={() => alternarModulo(m.modulo, true)}>Marcar todo</button>
                                    <button className="underline cursor-pointer" onClick={() => alternarModulo(m.modulo, false)}>Quitar todo</button>
                                </div>
                            )}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-texto-suave">
                                        <th scope="col" className="px-4 py-2 font-medium">Recurso</th>
                                        {ACCIONES.map(([k, t]) => (
                                            <th key={k} scope="col" className="px-3 py-2 font-medium text-center whitespace-nowrap">{t}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {m.recursos.map((r) => (
                                        <tr key={r.recurso} className="border-t border-borde">
                                            <th scope="row" className="px-4 py-2 font-medium text-left first-letter:uppercase">{r.nombre}</th>
                                            {['ver', 'crear', 'editar', 'eliminar'].map((a) => (
                                                <td key={a} className="px-3 py-2 text-center">{casilla(r.acciones[a], `${a} ${r.nombre}`)}</td>
                                            ))}
                                            <td className="px-3 py-2">
                                                <div className="flex flex-col gap-1">
                                                    {r.otros.map((p) => (
                                                        <label key={p.id_privilegio} className="flex items-center gap-2 text-xs whitespace-nowrap">
                                                            {casilla(p, p.descripcion)} {p.descripcion}
                                                        </label>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}
            </div>
        </div>
    );
}
