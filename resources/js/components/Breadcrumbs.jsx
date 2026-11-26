import { useMemo } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Home, ChevronRight } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const RUTAS_ESPECIALES = {
    '/perfil': { grupo: 'Cuenta', pagina: 'Mi perfil' },
    '/cotizar': { grupo: 'Comercial', pagina: 'Cotización en línea' },
    '/notificaciones': { grupo: 'Cuenta', pagina: 'Notificaciones' },
};

/**
 * C7 - Componente de Migas de Pan (Breadcrumbs)
 * Muestra la jerarquía de navegación: Inicio > Módulo > Pantalla actual.
 */
export default function Breadcrumbs() {
    const { pathname } = useLocation();
    const { menu } = useAuth();

    const infoRuta = useMemo(() => {
        if (pathname === '/') return null;

        if (RUTAS_ESPECIALES[pathname]) {
            return RUTAS_ESPECIALES[pathname];
        }

        // Buscar en el árbol dinámico del menú cargado desde la BD
        for (const item of menu || []) {
            if (item.hijos?.length) {
                const hijo = item.hijos.find(
                    (h) => h.ruta === pathname || (h.ruta !== '/' && pathname.startsWith(h.ruta)),
                );
                if (hijo) {
                    return { grupo: item.titulo, pagina: hijo.titulo };
                }
            } else if (item.ruta && (item.ruta === pathname || (item.ruta !== '/' && pathname.startsWith(item.ruta)))) {
                return { grupo: null, pagina: item.titulo };
            }
        }

        // Respaldo en caso de rutas dinámicas o no catalogadas en el menú
        const partes = pathname.split('/').filter(Boolean);
        if (partes.length === 0) return null;

        const nombreLimpio = partes[partes.length - 1]
            .replace(/-/g, ' ')
            .replace(/\b\w/g, (l) => l.toUpperCase());

        return {
            grupo: partes.length > 1 ? partes[0].replace(/-/g, ' ').toUpperCase() : null,
            pagina: nombreLimpio,
        };
    }, [pathname, menu]);

    if (!infoRuta) {
        return null;
    }

    const { grupo, pagina } = infoRuta;

    return (
        <nav
            aria-label="Migas de pan"
            className="px-4 sm:px-6 py-2 text-xs flex items-center gap-1.5 text-texto-suave bg-superficie/60 border-b border-borde/40 overflow-x-auto whitespace-nowrap"
        >
            <Link
                to="/"
                className="flex items-center gap-1 hover:text-primario transition-colors font-medium"
                title="Ir a Inicio"
            >
                <Home size={13} />
                <span>Inicio</span>
            </Link>

            {grupo && (
                <>
                    <ChevronRight size={12} className="opacity-50 shrink-0" aria-hidden="true" />
                    <span className="font-medium text-texto-suave">{grupo}</span>
                </>
            )}

            <ChevronRight size={12} className="opacity-50 shrink-0" aria-hidden="true" />
            <span className="text-texto font-semibold truncate max-w-xs" aria-current="page">
                {pagina}
            </span>
        </nav>
    );
}
