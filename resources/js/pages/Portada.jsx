import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowRight, BadgePercent, FileCheck2, LogIn, Megaphone, Package, Radio, Search, ShoppingCart, Tv, Globe, Share2 } from 'lucide-react';
import Logo from '../components/Logo';
import Footer from '../components/Footer';
import PanelAccesibilidad from '../components/PanelAccesibilidad';
import Insignia from '../components/Insignia';
import { api, mensajeError } from '../lib/api';
import { moneda } from '../lib/formato';

/** Texto sin tildes y en minúsculas, para buscar "television" y encontrar "Televisión". */
const normalizar = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const TIPOS = {
    television: { icono: Tv, nombre: 'Televisión' },
    radio: { icono: Radio, nombre: 'Radio' },
    web: { icono: Globe, nombre: 'Web' },
    red_social: { icono: Share2, nombre: 'Redes sociales' },
};
const tipoServicio = (tipo) => TIPOS[normalizar(tipo).replace(/\s+/g, '_')] ?? { icono: Megaphone, nombre: tipo };

/**
 * Portada pública: primera vista del Visitante (sin sesión).
 * Muestra la oferta comercial vigente, lo que se puede hacer sin registrarse
 * y la búsqueda predictiva del catálogo en el encabezado.
 */
export default function Portada() {
    const [catalogo, setCatalogo] = useState({ servicios: [], paquetes: [] });
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        api.get('/publico/catalogo')
            .then(({ data }) => setCatalogo(data.data))
            .catch((e) => setError(mensajeError(e)))
            .finally(() => setCargando(false));
    }, []);

    // Servicios con su tarifa vigente más baja ("desde Bs ...")
    const servicios = useMemo(
        () =>
            catalogo.servicios.map((s) => {
                const tarifas = s.tarifas ?? [];
                const minima = tarifas.reduce((min, t) => (min === null || Number(t.monto) < Number(min.monto) ? t : min), null);
                return { ...s, minima, cantidadTarifas: tarifas.length };
            }),
        [catalogo.servicios],
    );

    return (
        <div className="min-h-screen flex flex-col bg-fondo">
            <a href="#contenido" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[80] btn btn-primario">
                Saltar al contenido
            </a>

            {/* ------------------------------ Encabezado ------------------------------ */}
            <header className="sticky top-0 z-20 bg-superficie">
                <div className="max-w-6xl mx-auto px-4 h-16 flex items-center gap-3">
                    <Link to="/" className="flex items-center gap-3 shrink-0" aria-label="TVU Canal 11 UAGRM, inicio">
                        <Logo className="size-10" />
                        <span className="hidden md:block">
                            <span className="block font-bold leading-tight">TVU · Canal 11</span>
                            <span className="block text-xs text-texto-suave">UAGRM</span>
                        </span>
                    </Link>

                    <BuscadorCatalogo paquetes={catalogo.paquetes} servicios={servicios} cargando={cargando} error={error} />

                    <PanelAccesibilidad />
                    <Link to="/cotizar" className="btn btn-secundario hidden sm:inline-flex">
                        <ShoppingCart size={16} /> Cotizar
                    </Link>
                    <Link to="/login" className="btn btn-primario">
                        <LogIn size={16} /> <span className="hidden sm:inline">Iniciar sesión</span>
                    </Link>
                </div>
                <div className="franja-marca" aria-hidden />
            </header>

            <main id="contenido" className="flex-1" tabIndex={-1}>
                {/* ------------------------------ Presentación ------------------------------ */}
                <section className="bg-primario-suave border-b border-borde">
                    <div className="max-w-6xl mx-auto px-4 py-12 sm:py-16 grid md:grid-cols-[1fr_auto] gap-8 items-center">
                        <div>
                            <p className="text-sm font-semibold text-acento uppercase tracking-wide">Publicidad institucional y comercial</p>
                            <h1 className="text-3xl sm:text-4xl font-bold mt-2 leading-tight">
                                Llegue a toda la comunidad universitaria con <span className="text-primario">TVU · Canal 11 UAGRM</span>
                            </h1>
                            <p className="text-texto-suave mt-4 max-w-2xl">
                                Spots de televisión, menciones, cintillos y publicaciones en las redes sociales oficiales de la universidad.
                                Revise nuestras tarifas vigentes y arme su cotización en línea, sin registrarse.
                            </p>
                            <div className="flex flex-wrap gap-3 mt-6">
                                <Link to="/cotizar" className="btn btn-primario !px-5 !py-2.5">
                                    <ShoppingCart size={18} /> Cotizar ahora
                                </Link>
                                <a href="#paquetes" className="btn btn-secundario !px-5 !py-2.5">
                                    Ver paquetes y tarifas <ArrowRight size={16} />
                                </a>
                            </div>
                        </div>
                        <Logo className="hidden md:block size-48 shadow-lg" />
                    </div>
                </section>

                {/* ------------------------------ Qué puede hacer ------------------------------ */}
                <section className="max-w-6xl mx-auto px-4 py-10" aria-labelledby="titulo-pasos">
                    <h2 id="titulo-pasos" className="text-2xl font-bold mb-1">Sin registrarse, usted puede</h2>
                    <p className="text-texto-suave mb-6">Cotizar es gratis y no lo compromete a contratar.</p>
                    <ol className="grid gap-4 md:grid-cols-3">
                        <Paso numero="1" icono={Search} titulo="Explorar la oferta">
                            Busque y compare paquetes promocionales y servicios con sus tarifas vigentes.
                        </Paso>
                        <Paso numero="2" icono={ShoppingCart} titulo="Armar su cotización">
                            Agregue los servicios que necesita, indique las cantidades y deje sus datos de contacto.
                        </Paso>
                        <Paso numero="3" icono={FileCheck2} titulo="Recibir su proforma">
                            El sistema calcula el total y genera una proforma numerada, válida por 15 días.
                        </Paso>
                    </ol>
                    <div className="tarjeta mt-6 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <p className="text-sm">
                            <strong>¿Su empresa ya es cliente de Canal 11?</strong>{' '}
                            <span className="text-texto-suave">Inicie sesión para aprobar proformas, pagar en línea, cargar su material y ver el impacto de sus campañas.</span>
                        </p>
                        <Link to="/login" className="btn btn-secundario shrink-0">
                            <LogIn size={16} /> Acceso de clientes y personal
                        </Link>
                    </div>
                </section>

                {error && (
                    <p className="max-w-6xl mx-auto px-4 text-peligro text-sm" role="alert">
                        No se pudo cargar el catálogo: {error}
                    </p>
                )}

                {/* ------------------------------ Paquetes ------------------------------ */}
                <section id="paquetes" className="max-w-6xl mx-auto px-4 py-6 scroll-mt-20" aria-labelledby="titulo-paquetes">
                    <h2 id="titulo-paquetes" className="text-2xl font-bold mb-1">Paquetes promocionales</h2>
                    <p className="text-texto-suave mb-5">Combos de servicios con descuento, listos para contratar.</p>
                    {cargando ? (
                        <Esqueleto />
                    ) : catalogo.paquetes.length === 0 ? (
                        <p className="text-texto-suave">No hay paquetes disponibles por el momento.</p>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {catalogo.paquetes.map((pq) => (
                                <article key={pq.id_paquete} className="tarjeta p-5 flex flex-col">
                                    <div className="flex items-start justify-between gap-2">
                                        <span className="size-10 rounded-tema bg-primario-suave text-primario flex items-center justify-center shrink-0">
                                            <Package size={20} aria-hidden />
                                        </span>
                                        {Number(pq.descuento) > 0 && (
                                            <Insignia tono="exito">
                                                <BadgePercent size={12} className="mr-1" aria-hidden /> {Number(pq.descuento)}% de descuento
                                            </Insignia>
                                        )}
                                    </div>
                                    <h3 className="font-semibold text-lg mt-3">{pq.nombre}</h3>
                                    {pq.descripcion && <p className="text-sm text-texto-suave mt-1">{pq.descripcion}</p>}
                                    {pq.items?.length > 0 && (
                                        <ul className="text-sm mt-3 space-y-1 flex-1">
                                            {pq.items.map((it, i) => (
                                                <li key={it.id_tarifa ?? i} className="flex gap-2">
                                                    <span className="text-primario" aria-hidden>•</span>
                                                    <span>
                                                        {it.cantidad ? `${it.cantidad} × ` : ''}
                                                        {it.tarifa?.servicio?.nombre ?? 'Servicio'}
                                                        {it.tarifa?.unidad_cobro ? ` (${it.tarifa.unidad_cobro})` : ''}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    <div className="flex items-center justify-between gap-2 pt-4 mt-4 border-t border-borde">
                                        <span className="text-xl font-bold text-primario">{moneda(pq.precio_total)}</span>
                                        <Link to={`/cotizar?paquete=${pq.id_paquete}`} className="btn btn-primario !py-1.5 !px-3 text-sm">
                                            Cotizar <ArrowRight size={14} />
                                        </Link>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                {/* ------------------------------ Servicios ------------------------------ */}
                <section id="servicios" className="max-w-6xl mx-auto px-4 py-10 scroll-mt-20" aria-labelledby="titulo-servicios">
                    <h2 id="titulo-servicios" className="text-2xl font-bold mb-1">Servicios publicitarios</h2>
                    <p className="text-texto-suave mb-5">Precios por unidad, según el tarifario vigente de Canal 11.</p>
                    {cargando ? (
                        <Esqueleto />
                    ) : servicios.length === 0 ? (
                        <p className="text-texto-suave">No hay servicios disponibles por el momento.</p>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {servicios.map((s) => {
                                const { icono: I, nombre: tipo } = tipoServicio(s.tipo);
                                return (
                                    <article key={s.id_servicio} className="tarjeta p-5 flex flex-col">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="size-10 rounded-tema bg-primario-suave text-primario flex items-center justify-center shrink-0">
                                                <I size={20} aria-hidden />
                                            </span>
                                            {s.tipo && <Insignia tono="primario">{tipo}</Insignia>}
                                        </div>
                                        <h3 className="font-semibold text-lg mt-3">{s.nombre}</h3>
                                        {s.descripcion && <p className="text-sm text-texto-suave mt-1 flex-1">{s.descripcion}</p>}
                                        <div className="flex items-center justify-between gap-2 pt-4 mt-4 border-t border-borde">
                                            {s.minima ? (
                                                <span>
                                                    <span className="text-xs text-texto-suave block">
                                                        {s.cantidadTarifas > 1 ? `Desde (${s.cantidadTarifas} tarifas)` : s.minima.unidad_cobro ?? 'Tarifa'}
                                                    </span>
                                                    <span className="text-xl font-bold text-primario">{moneda(s.minima.monto)}</span>
                                                </span>
                                            ) : (
                                                <span className="text-sm text-texto-suave">Consultar precio</span>
                                            )}
                                            {s.minima && (
                                                <Link to={`/cotizar?tarifa=${s.minima.id_tarifa}`} className="btn btn-secundario !py-1.5 !px-3 text-sm">
                                                    Cotizar <ArrowRight size={14} />
                                                </Link>
                                            )}
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
            </main>

            <Footer />
        </div>
    );
}

function Paso({ numero, icono: I, titulo, children }) {
    return (
        <li className="tarjeta p-5 flex gap-4">
            <span className="size-11 rounded-full bg-primario text-primario-texto flex items-center justify-center font-bold shrink-0" aria-hidden>
                {numero}
            </span>
            <div>
                <h3 className="font-semibold flex items-center gap-2">
                    <I size={16} className="text-primario" aria-hidden /> {titulo}
                </h3>
                <p className="text-sm text-texto-suave mt-1">{children}</p>
            </div>
        </li>
    );
}

function Esqueleto() {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-hidden>
            {[0, 1, 2].map((i) => (
                <div key={i} className="tarjeta p-5 h-44 animate-pulse">
                    <div className="h-4 bg-superficie-2 rounded w-1/3 mb-4" />
                    <div className="h-5 bg-superficie-2 rounded w-2/3 mb-2" />
                    <div className="h-4 bg-superficie-2 rounded w-full" />
                </div>
            ))}
        </div>
    );
}

/**
 * Búsqueda predictiva del catálogo público (encabezado de la página principal).
 * Busca mientras se escribe, sin distinguir tildes; se maneja con flechas, Enter y Esc.
 */
function BuscadorCatalogo({ paquetes, servicios, cargando, error }) {
    const navegar = useNavigate();
    const id = useId();
    const [texto, setTexto] = useState('');
    const [abierto, setAbierto] = useState(false);
    const [activo, setActivo] = useState(0);
    const caja = useRef(null);

    const resultados = useMemo(() => {
        const q = normalizar(texto.trim());
        if (q.length < 2) return [];
        const coincide = (...campos) => campos.some((c) => normalizar(c).includes(q));
        return [
            ...paquetes
                .filter((p) => coincide(p.nombre, p.descripcion))
                .map((p) => ({ clave: `p-${p.id_paquete}`, grupo: 'Paquetes', titulo: p.nombre, detalle: moneda(p.precio_total), ruta: `/cotizar?paquete=${p.id_paquete}` })),
            ...servicios
                .filter((s) => s.minima && coincide(s.nombre, s.descripcion, s.tipo, tipoServicio(s.tipo).nombre))
                .map((s) => ({ clave: `s-${s.id_servicio}`, grupo: 'Servicios', titulo: s.nombre, detalle: `desde ${moneda(s.minima.monto)}`, ruta: `/cotizar?tarifa=${s.minima.id_tarifa}` })),
        ].slice(0, 8);
    }, [texto, paquetes, servicios]);

    useEffect(() => {
        const cerrar = (e) => caja.current && !caja.current.contains(e.target) && setAbierto(false);
        document.addEventListener('mousedown', cerrar);
        return () => document.removeEventListener('mousedown', cerrar);
    }, []);

    const elegir = (r) => {
        setAbierto(false);
        setTexto('');
        navegar(r.ruta);
    };

    const teclas = (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setAbierto(true);
            setActivo((a) => Math.min(a + 1, resultados.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActivo((a) => Math.max(a - 1, 0));
        } else if (e.key === 'Enter' && abierto && resultados[activo]) {
            e.preventDefault();
            elegir(resultados[activo]);
        } else if (e.key === 'Escape') {
            setAbierto(false);
        }
    };

    const mostrar = abierto && texto.trim().length >= 2;

    return (
        <div className="relative flex-1 min-w-0 max-w-xl mx-auto" ref={caja}>
            <label htmlFor={id} className="sr-only">Buscar en el catálogo</label>
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave pointer-events-none" aria-hidden />
            <input
                id={id}
                type="search"
                className="input !pl-9"
                placeholder="Buscar servicios y paquetes…"
                value={texto}
                onChange={(e) => {
                    setTexto(e.target.value);
                    setActivo(0);
                    setAbierto(true);
                }}
                onFocus={() => setAbierto(true)}
                onKeyDown={teclas}
                role="combobox"
                aria-expanded={mostrar}
                aria-controls={`${id}-lista`}
                aria-activedescendant={mostrar && resultados[activo] ? `${id}-${resultados[activo].clave}` : undefined}
                autoComplete="off"
            />
            {mostrar && (
                <ul id={`${id}-lista`} role="listbox" className="tarjeta absolute left-0 right-0 mt-2 py-1 z-50 max-h-96 overflow-y-auto">
                    {cargando ? (
                        <li className="px-4 py-3 text-sm text-texto-suave">Cargando el catálogo…</li>
                    ) : error ? (
                        <li className="px-4 py-3 text-sm text-peligro" role="alert">
                            No se pudo cargar el catálogo, así que no podemos buscar ahora. Intente de nuevo en unos minutos.
                        </li>
                    ) : resultados.length === 0 ? (
                        <li className="px-4 py-3 text-sm text-texto-suave">No encontramos servicios ni paquetes con «{texto.trim()}».</li>
                    ) : (
                        resultados.map((r, i) => (
                            <li
                                key={r.clave}
                                id={`${id}-${r.clave}`}
                                role="option"
                                aria-selected={i === activo}
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    elegir(r);
                                }}
                                onMouseEnter={() => setActivo(i)}
                                className={`px-4 py-2 cursor-pointer flex items-center justify-between gap-3 ${i === activo ? 'bg-primario-suave' : ''}`}
                            >
                                <span className="min-w-0">
                                    <span className="block text-xs text-texto-suave">{r.grupo}</span>
                                    <span className="block font-medium truncate">{r.titulo}</span>
                                </span>
                                <span className="text-sm font-semibold text-primario shrink-0">{r.detalle}</span>
                            </li>
                        ))
                    )}
                </ul>
            )}
        </div>
    );
}
