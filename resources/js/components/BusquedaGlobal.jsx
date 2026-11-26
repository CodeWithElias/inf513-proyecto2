import { useState, useEffect, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    Search,
    Loader2,
    X,
    Building2,
    Briefcase,
    Package,
    FileText,
    Megaphone,
    LayoutGrid,
    Users,
    UserSquare,
    Network,
    Share2,
    Film,
    AlertTriangle,
} from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../context/AuthContext';

/** Orden en que se muestran los grupos. "pantallas" se calcula con el menú del rol (sin servidor). */
const CATEGORIAS = [
    { clave: 'pantallas', titulo: 'Pantallas', icono: LayoutGrid },
    { clave: 'usuarios', titulo: 'Usuarios', icono: Users },
    { clave: 'clientes', titulo: 'Clientes', icono: UserSquare },
    { clave: 'empresas', titulo: 'Empresas', icono: Building2 },
    { clave: 'proformas', titulo: 'Proformas', icono: FileText },
    { clave: 'campanias', titulo: 'Campañas', icono: Megaphone },
    { clave: 'servicios', titulo: 'Servicios', icono: Briefcase },
    { clave: 'paquetes', titulo: 'Paquetes', icono: Package },
    { clave: 'materiales', titulo: 'Material publicitario', icono: Film },
    { clave: 'redes', titulo: 'Redes sociales', icono: Share2 },
    { clave: 'unidades', titulo: 'Unidades', icono: Network },
    { clave: 'paginas', titulo: 'Páginas no autorizadas', icono: AlertTriangle },
];
const VACIO = Object.fromEntries(CATEGORIAS.map((c) => [c.clave, []]));

/** Texto sin tildes y en minúsculas ("bitacora" encuentra "Bitácora"). */
const normalizar = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * C5 - Barra de búsqueda predictiva en el encabezado.
 * Debounce de 300 ms, agrupación por tipo, navegación por teclado (flechas/Enter/Escape)
 * y respeto a los privilegios del usuario (filtrado desde backend).
 */
export default function BusquedaGlobal() {
    const [termino, setTermino] = useState('');
    const [cargando, setCargando] = useState(false);
    const [abierto, setAbierto] = useState(false);
    const [respuesta, setRespuesta] = useState(VACIO);
    const [indiceActivo, setIndiceActivo] = useState(-1);

    const contenedorRef = useRef(null);
    const inputRef = useRef(null);
    const navegar = useNavigate();
    const { menu } = useAuth();

    // Pantallas del menú del rol que coinciden con el texto (ej. "bitácora", "pagos")
    const pantallas = useMemo(() => {
        const q = normalizar(termino.trim());
        if (q.length < 2) return [];
        return menu
            .flatMap((m) => (m.hijos?.length ? m.hijos.map((h) => ({ ...h, grupo: m.titulo })) : m.ruta ? [{ ...m, grupo: null }] : []))
            .filter((m) => normalizar(m.titulo).includes(q) || normalizar(m.grupo).includes(q))
            .slice(0, 5)
            .map((m) => ({ id: m.id, titulo: m.titulo, subtitulo: m.grupo ? `Menú · ${m.grupo}` : 'Menú', ruta: m.ruta }));
    }, [termino, menu]);

    const resultados = useMemo(() => ({ ...respuesta, pantallas }), [respuesta, pantallas]);

    // Aplanar los resultados para permitir navegación secuencial con teclado
    const listaAplanada = useMemo(() => {
        const items = [];
        for (const cat of CATEGORIAS) {
            const lista = resultados[cat.clave] || [];
            for (const item of lista) {
                items.push({ ...item, categoriaClave: cat.clave, categoriaTitulo: cat.titulo });
            }
        }
        return items;
    }, [resultados]);

    // Debounce de 300 ms al escribir
    useEffect(() => {
        const texto = termino.trim();
        if (texto.length < 2) {
            setRespuesta(VACIO);
            setCargando(false);
            setIndiceActivo(-1);
            return;
        }

        setCargando(true);
        const timer = setTimeout(async () => {
            try {
                const res = await api.get('/buscar', { params: { q: texto } });
                setRespuesta({ ...VACIO, ...(res.data?.data || {}) });
                setIndiceActivo(-1);
            } catch {
                setRespuesta(VACIO);
            } finally {
                setCargando(false);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [termino]);

    // Cerrar desplegable al hacer clic fuera del componente
    useEffect(() => {
        const clickFuera = (e) => {
            if (contenedorRef.current && !contenedorRef.current.contains(e.target)) {
                setAbierto(false);
            }
        };
        document.addEventListener('mousedown', clickFuera);
        return () => document.removeEventListener('mousedown', clickFuera);
    }, []);

    // Seleccionar y navegar al ítem
    const irA = (item) => {
        setAbierto(false);
        setTermino('');
        if (item.ruta) {
            navegar(item.ruta);
        }
    };

    // Manejo de eventos de teclado (Accesibilidad)
    const alPresionarTecla = (e) => {
        if (!abierto && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            setAbierto(true);
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (listaAplanada.length > 0) {
                setIndiceActivo((prev) => (prev + 1) % listaAplanada.length);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (listaAplanada.length > 0) {
                setIndiceActivo((prev) => (prev <= 0 ? listaAplanada.length - 1 : prev - 1));
            }
        } else if (e.key === 'Enter') {
            if (indiceActivo >= 0 && indiceActivo < listaAplanada.length) {
                e.preventDefault();
                irA(listaAplanada[indiceActivo]);
            }
        } else if (e.key === 'Escape') {
            setAbierto(false);
            inputRef.current?.blur();
        }
    };

    const hayResultados = listaAplanada.length > 0;
    const textoValido = termino.trim().length >= 2;

    return (
        <div ref={contenedorRef} className="relative w-full max-w-md sm:max-w-lg">
            <div className="relative flex items-center">
                <Search
                    size={18}
                    className="absolute left-3 text-texto-suave pointer-events-none"
                    aria-hidden="true"
                />
                <input
                    ref={inputRef}
                    type="search"
                    role="combobox"
                    aria-expanded={abierto && (hayResultados || (!cargando && textoValido))}
                    aria-autocomplete="list"
                    aria-controls="resultados-busqueda"
                    value={termino}
                    onChange={(e) => {
                        setTermino(e.target.value);
                        setAbierto(true);
                    }}
                    onFocus={() => setAbierto(true)}
                    onKeyDown={alPresionarTecla}
                    placeholder="Buscar en todo el sistema… (mín. 2 letras)"
                    aria-label="Búsqueda global"
                    className="w-full pl-9 pr-14 py-1.5 text-sm rounded-tema border border-borde bg-superficie-2/70 text-texto placeholder:text-texto-suave/80 focus:bg-superficie focus:outline-none focus:ring-2 focus:ring-primario transition-colors"
                />
                <div className="absolute right-2 flex items-center gap-1">
                    {cargando && (
                        <Loader2
                            size={16}
                            className="animate-spin text-primario"
                            aria-label="Buscando..."
                        />
                    )}
                    {termino && (
                        <button
                            type="button"
                            onClick={() => {
                                setTermino('');
                                setRespuesta(VACIO);
                                inputRef.current?.focus();
                            }}
                            className="p-1 rounded hover:bg-superficie text-texto-suave hover:text-texto transition-colors"
                            aria-label="Limpiar búsqueda"
                        >
                            <X size={14} />
                        </button>
                    )}
                </div>
            </div>

            {/* Panel de resultados predictivos */}
            {abierto && (hayResultados || (!cargando && textoValido)) && (
                <div
                    id="resultados-busqueda"
                    role="listbox"
                    className="absolute left-0 right-0 top-full mt-1.5 max-h-96 overflow-y-auto bg-superficie border border-borde rounded-tema shadow-lg z-50 divide-y divide-borde/60"
                >
                    {hayResultados ? (
                        CATEGORIAS.map((cat) => {
                            const items = resultados[cat.clave] || [];
                            if (items.length === 0) return null;
                            const IconoCat = cat.icono;

                            return (
                                <div key={cat.clave} className="py-1">
                                    <div className="px-3 py-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-texto-suave bg-superficie-2/40">
                                        <IconoCat size={13} className="text-primario" />
                                        <span>{cat.titulo}</span>
                                        <span className="ml-auto text-[11px] font-normal lowercase opacity-75">
                                            {items.length} {items.length === 1 ? 'coincidencia' : 'coincidencias'}
                                        </span>
                                    </div>
                                    <div>
                                        {items.map((item) => {
                                            const indiceGlobal = listaAplanada.findIndex(
                                                (x) => x.categoriaClave === cat.clave && x.id === item.id,
                                            );
                                            const estaActivo = indiceGlobal === indiceActivo;

                                            return (
                                                <div
                                                    key={`${cat.clave}-${item.id}`}
                                                    role="option"
                                                    aria-selected={estaActivo}
                                                    onClick={() => irA(item)}
                                                    onMouseEnter={() => setIndiceActivo(indiceGlobal)}
                                                    className={`px-3 py-2 cursor-pointer flex flex-col transition-colors ${
                                                        estaActivo
                                                            ? 'bg-primario-suave text-texto font-medium'
                                                            : 'hover:bg-superficie-2 text-texto'
                                                    }`}
                                                >
                                                    <span className="text-sm font-medium leading-snug">
                                                        {item.titulo}
                                                    </span>
                                                    {item.subtitulo && (
                                                        <span className="text-xs text-texto-suave line-clamp-1">
                                                            {item.subtitulo}
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <div className="p-4 text-center text-sm text-texto-suave">
                            No se encontraron resultados para &ldquo;<strong className="text-texto font-medium">{termino}</strong>&rdquo;.
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
