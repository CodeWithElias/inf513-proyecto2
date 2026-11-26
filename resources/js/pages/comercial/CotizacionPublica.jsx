import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ArrowLeft, CheckCircle2, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import Logo from '../../components/Logo';
import Campo from '../../components/Campo';
import Footer from '../../components/Footer';
import PanelAccesibilidad from '../../components/PanelAccesibilidad';
import Insignia from '../../components/Insignia';
import { api, erroresCampos, mensajeError } from '../../lib/api';
import { fecha, moneda } from '../../lib/formato';
import { validar } from '../../lib/validar';

const TELEFONO = { patron: /^[0-9+\- ]{6,30}$/, mensajePatron: 'El teléfono solo puede tener números, espacios, + y - (6 a 30 caracteres).' };

/** B3 - Catálogo público y solicitud de cotización sin inicio de sesión. */
export default function CotizacionPublica() {
    const [catalogo, setCatalogo] = useState({ servicios: [], paquetes: [] });
    const [cargando, setCargando] = useState(true);
    const [carrito, setCarrito] = useState([]);
    const [datos, setDatos] = useState({ nombre: '', email: '', telefono: '', crear_cuenta: false, password: '', password_confirmation: '' });
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [proformaCreada, setProformaCreada] = useState(null);

    useEffect(() => {
        api.get('/publico/catalogo')
            .then(({ data }) => setCatalogo(data.data))
            .catch((e) => setErrorGeneral(mensajeError(e)))
            .finally(() => setCargando(false));
    }, []);

    // Si se llega desde la portada con ?paquete=ID o ?tarifa=ID, ese ítem se agrega al carrito una sola vez.
    const [params] = useSearchParams();
    const preseleccionado = useRef(false);
    useEffect(() => {
        if (cargando || preseleccionado.current) return;
        preseleccionado.current = true;
        const idPaquete = Number(params.get('paquete'));
        const idTarifa = Number(params.get('tarifa'));
        const pq = idPaquete ? catalogo.paquetes.find((p) => p.id_paquete === idPaquete) : null;
        if (pq) {
            agregarAlCarrito({ clave: `p-${pq.id_paquete}`, id_paquete: pq.id_paquete, nombre: `Paquete: ${pq.nombre}`, precio: Number(pq.precio_total) });
        }
        if (idTarifa) {
            for (const s of catalogo.servicios) {
                const t = s.tarifas?.find((x) => x.id_tarifa === idTarifa);
                if (t) {
                    agregarAlCarrito({ clave: `t-${t.id_tarifa}`, id_tarifa: t.id_tarifa, nombre: `${s.nombre} (${t.unidad_cobro ?? 'unidad'})`, precio: Number(t.monto) });
                    break;
                }
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cargando]);

    const agregarAlCarrito = (item) => {
        setCarrito((prev) => {
            const idx = prev.findIndex((x) => x.clave === item.clave);
            if (idx >= 0) {
                return prev.map((x, i) => (i === idx ? { ...x, cantidad: x.cantidad + 1 } : x));
            }
            return [...prev, { ...item, cantidad: 1 }];
        });
    };

    const cambiarCantidad = (clave, cantidad) => {
        const n = Math.max(1, Number(cantidad) || 1);
        setCarrito((prev) => prev.map((x) => (x.clave === clave ? { ...x, cantidad: n } : x)));
    };

    const quitar = (clave) => setCarrito((prev) => prev.filter((x) => x.clave !== clave));

    const total = carrito.reduce((acc, x) => acc + x.precio * x.cantidad, 0);

    const enviar = async (e) => {
        e.preventDefault();
        setErrorGeneral(null);
        const locales = validar(datos, {
            nombre: { requerido: true, min: 3, max: 150, etiqueta: 'nombre completo' },
            email: { requerido: true, email: true, max: 150, etiqueta: 'correo electrónico' },
            telefono: { ...TELEFONO, etiqueta: 'teléfono' },
            ...(datos.crear_cuenta
                ? {
                      password: { requerido: true, min: 6, max: 100, etiqueta: 'contraseña' },
                      password_confirmation: { requerido: true, igualA: 'password', etiqueta: 'confirmación de la contraseña' },
                  }
                : {}),
        });
        if (carrito.length === 0) {
            locales.carrito = 'Agregue al menos una tarifa o paquete a su cotización.';
        }
        setErrores(locales);
        if (Object.keys(locales).length) {
            setErrorGeneral('Revise los datos antes de enviar su solicitud.');
            return;
        }

        setEnviando(true);
        try {
            const { crear_cuenta, password, password_confirmation, ...contacto } = datos;
            const { data } = await api.post('/publico/cotizar', {
                ...contacto,
                ...(crear_cuenta ? { crear_cuenta: true, password, password_confirmation } : {}),
                detalles: carrito.map((c) => ({
                    id_tarifa: c.id_tarifa ?? null,
                    id_paquete: c.id_paquete ?? null,
                    cantidad: c.cantidad,
                })),
            });
            setProformaCreada(data.data);
            setCarrito([]);
            setDatos((d) => ({ ...d, password: '', password_confirmation: '' })); // no dejar la contraseña en memoria
        } catch (err) {
            setErrores(erroresCampos(err));
            setErrorGeneral(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <div className="min-h-screen flex flex-col bg-fondo">
            <header className="bg-superficie border-b border-borde">
                <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-3">
                    <Link to="/" className="flex items-center gap-3" aria-label="Volver a la página principal">
                        <Logo className="size-10" />
                        <div>
                            <p className="font-bold leading-tight">TVU · Canal 11 UAGRM</p>
                            <p className="text-xs text-texto-suave">Catálogo publicitario y cotización en línea</p>
                        </div>
                    </Link>
                    <div className="flex items-center gap-2">
                        <PanelAccesibilidad />
                        <Link to="/" className="btn btn-secundario text-sm">
                            <ArrowLeft size={16} /> <span className="hidden sm:inline">Volver al</span> inicio
                        </Link>
                        <Link to="/login" className="btn btn-primario text-sm hidden sm:inline-flex">
                            Iniciar sesión
                        </Link>
                    </div>
                </div>
                <div className="franja-marca" aria-hidden />
            </header>

            <main className="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6">
                {proformaCreada ? (
                    <div className="tarjeta p-6 max-w-xl mx-auto text-center space-y-4">
                        <CheckCircle2 size={48} className="mx-auto text-exito" aria-hidden />
                        <h1 className="text-2xl font-bold">¡Cotización generada!</h1>
                        <p className="text-sm text-texto-suave">
                            Su solicitud quedó registrada con el número <strong>{proformaCreada.nro_proforma}</strong>.
                            Es válida hasta el <strong>{fecha(proformaCreada.fecha_validez)}</strong>.
                        </p>
                        <div className="bg-superficie-2 rounded-tema p-4 text-left text-sm space-y-1">
                            <p>Solicitante: <strong>{proformaCreada.visitante?.nombre}</strong></p>
                            <p>Correo: <strong>{proformaCreada.visitante?.email}</strong></p>
                            <p>Total estimado: <strong className="text-primario">{moneda(proformaCreada.total)}</strong></p>
                        </div>
                        {proformaCreada.solicitud_cuenta && (
                            <div className="bg-primario-suave rounded-tema p-4 text-left text-sm" role="status">
                                <p className="font-semibold mb-1">Su solicitud de cuenta quedó pendiente</p>
                                <p className="text-texto-suave">
                                    Canal 11 revisará su solicitud. Cuando sea aprobada podrá iniciar sesión con <strong>{proformaCreada.visitante?.email}</strong> y
                                    la contraseña que eligió, y verá esta proforma en su cuenta.
                                </p>
                            </div>
                        )}
                        <div className="flex justify-center gap-3">
                            <button className="btn btn-primario" onClick={() => setProformaCreada(null)}>Hacer otra cotización</button>
                            <Link to="/login" className="btn btn-secundario">Ir al acceso de clientes</Link>
                        </div>
                    </div>
                ) : (
                    <div className="grid lg:grid-cols-3 gap-6">
                        <div className="lg:col-span-2 space-y-6">
                            <section>
                                <h1 className="text-xl font-bold mb-1">Paquetes promocionales</h1>
                                <p className="text-sm text-texto-suave mb-3">Combos con descuento especial listos para contratar.</p>
                                {cargando ? (
                                    <p className="text-sm text-texto-suave">Cargando catálogo…</p>
                                ) : catalogo.paquetes.length === 0 ? (
                                    <p className="text-sm text-texto-suave">No hay paquetes disponibles por el momento.</p>
                                ) : (
                                    <div className="grid sm:grid-cols-2 gap-3">
                                        {catalogo.paquetes.map((pq) => (
                                            <div key={pq.id_paquete} className="tarjeta p-4 flex flex-col justify-between gap-3">
                                                <div>
                                                    <div className="flex items-start justify-between gap-2">
                                                        <h2 className="font-semibold">{pq.nombre}</h2>
                                                        {Number(pq.descuento) > 0 && <Insignia tono="exito">-{pq.descuento}%</Insignia>}
                                                    </div>
                                                    {pq.descripcion && <p className="text-xs text-texto-suave mt-1">{pq.descripcion}</p>}
                                                </div>
                                                <div className="flex items-center justify-between pt-2 border-t border-borde">
                                                    <span className="font-bold text-primario">{moneda(pq.precio_total)}</span>
                                                    <button
                                                        type="button"
                                                        className="btn btn-primario !py-1 !px-3 text-xs"
                                                        onClick={() => agregarAlCarrito({ clave: `p-${pq.id_paquete}`, id_paquete: pq.id_paquete, nombre: `Paquete: ${pq.nombre}`, precio: Number(pq.precio_total) })}
                                                    >
                                                        <Plus size={14} /> Agregar
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </section>

                            <section>
                                <h2 className="text-xl font-bold mb-1">Servicios y tarifas vigentes</h2>
                                <p className="text-sm text-texto-suave mb-3">Seleccione las tarifas individuales que necesite.</p>
                                {!cargando && catalogo.servicios.length === 0 && (
                                    <p className="text-sm text-texto-suave">No hay servicios disponibles por el momento.</p>
                                )}
                                <div className="space-y-3">
                                    {catalogo.servicios.map((s) => (
                                        <div key={s.id_servicio} className="tarjeta p-4">
                                            <div className="flex items-center justify-between gap-2 mb-2">
                                                <div>
                                                    <h3 className="font-semibold">{s.nombre}</h3>
                                                    {s.descripcion && <p className="text-xs text-texto-suave">{s.descripcion}</p>}
                                                </div>
                                                {s.tipo && <Insignia tono="primario">{s.tipo}</Insignia>}
                                            </div>
                                            {!s.tarifas?.length ? (
                                                <p className="text-xs text-texto-suave">Sin tarifas vigentes registradas.</p>
                                            ) : (
                                                <div className="divide-y divide-borde">
                                                    {s.tarifas.map((t) => (
                                                        <div key={t.id_tarifa} className="py-2 flex items-center justify-between gap-2 text-sm">
                                                            <div>
                                                                <span>{t.unidad_cobro ?? 'Tarifa estándar'}</span>
                                                                {t.duracion_seg && <span className="text-xs text-texto-suave ml-2">({t.duracion_seg} s)</span>}
                                                            </div>
                                                            <div className="flex items-center gap-3">
                                                                <span className="font-semibold">{moneda(t.monto)}</span>
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-secundario !py-1 !px-2.5 text-xs"
                                                                    onClick={() => agregarAlCarrito({ clave: `t-${t.id_tarifa}`, id_tarifa: t.id_tarifa, nombre: `${s.nombre} (${t.unidad_cobro ?? 'unidad'})`, precio: Number(t.monto) })}
                                                                >
                                                                    <Plus size={14} /> Agregar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>

                        <aside className="tarjeta p-5 h-fit sticky top-6 space-y-4">
                            <div className="flex items-center gap-2 font-bold text-base">
                                <ShoppingCart size={18} aria-hidden /> Mi cotización ({carrito.length})
                            </div>

                            {errores.carrito && <p className="texto-error" role="alert">{errores.carrito}</p>}

                            {carrito.length === 0 ? (
                                <p className="text-xs text-texto-suave">Agregue tarifas o paquetes desde el catálogo para calcular su proforma.</p>
                            ) : (
                                <ul className="divide-y divide-borde text-sm">
                                    {carrito.map((c) => (
                                        <li key={c.clave} className="py-2 flex items-center justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium truncate">{c.nombre}</p>
                                                <p className="text-xs text-texto-suave">{moneda(c.precio)} c/u</p>
                                            </div>
                                            <input
                                                type="number"
                                                min={1}
                                                className="input !w-16 !py-1 text-center"
                                                value={c.cantidad}
                                                onChange={(e) => cambiarCantidad(c.clave, e.target.value)}
                                            />
                                            <button type="button" className="btn-icono hover:!text-peligro" onClick={() => quitar(c.clave)} aria-label="Quitar">
                                                <Trash2 size={15} />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className="flex justify-between items-center pt-2 border-t border-borde font-bold">
                                <span>Total estimado</span>
                                <span className="text-primario text-lg">{moneda(total)}</span>
                            </div>

                            <form onSubmit={enviar} noValidate className="space-y-3 pt-2 border-t border-borde">
                                <h2 className="font-semibold text-sm">Sus datos de contacto</h2>
                                {errorGeneral && <p className="text-xs text-peligro bg-peligro/10 rounded-tema p-2.5" role="alert">{errorGeneral}</p>}
                                <Campo etiqueta="Nombre completo" nombre="nombre" valor={datos.nombre} onChange={(n, v) => setDatos((d) => ({ ...d, [n]: v }))} error={errores.nombre} requerido />
                                <Campo etiqueta="Correo electrónico" nombre="email" tipo="email" valor={datos.email} onChange={(n, v) => setDatos((d) => ({ ...d, [n]: v }))} error={errores.email} requerido />
                                <Campo etiqueta="Teléfono" nombre="telefono" valor={datos.telefono} onChange={(n, v) => setDatos((d) => ({ ...d, [n]: v }))} error={errores.telefono} />
                                <label className="flex items-start gap-2 text-sm cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="size-4 mt-0.5 accent-[var(--primario)]"
                                        checked={datos.crear_cuenta}
                                        onChange={(e) => setDatos((d) => ({ ...d, crear_cuenta: e.target.checked }))}
                                    />
                                    <span>
                                        <strong>Quiero una cuenta</strong> para seguir mi proforma.
                                        <span className="block text-xs text-texto-suave">Canal 11 revisará su solicitud; al aprobarla podrá iniciar sesión como cliente.</span>
                                    </span>
                                </label>
                                {datos.crear_cuenta && (
                                    <div className="space-y-3 border-l-2 border-primario pl-3">
                                        <Campo etiqueta="Contraseña" nombre="password" tipo="password" valor={datos.password} onChange={(n, v) => setDatos((d) => ({ ...d, [n]: v }))} error={errores.password} requerido autoComplete="new-password" ayuda="Mínimo 6 caracteres. Canal 11 no la conoce." />
                                        <Campo etiqueta="Repita la contraseña" nombre="password_confirmation" tipo="password" valor={datos.password_confirmation} onChange={(n, v) => setDatos((d) => ({ ...d, [n]: v }))} error={errores.password_confirmation} requerido autoComplete="new-password" />
                                    </div>
                                )}
                                <button type="submit" className="btn btn-primario w-full" disabled={enviando}>
                                    {enviando ? 'Generando proforma…' : 'Solicitar proforma'}
                                </button>
                            </form>
                        </aside>
                    </div>
                )}
            </main>
            <Footer />
        </div>
    );
}

