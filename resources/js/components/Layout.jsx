import { useState } from 'react';
import { Link, Outlet, useNavigate } from 'react-router-dom';
import { LogOut, Menu, UserCircle2 } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useAvisos } from '../context/AvisosContext';
import Sidebar from './Sidebar';
import Footer from './Footer';
import PanelAccesibilidad from './PanelAccesibilidad';
import BusquedaGlobal from './BusquedaGlobal';
import Breadcrumbs from './Breadcrumbs';
import CampanaNotificaciones from './CampanaNotificaciones';

/** Estructura de todas las pantallas con sesión: menú lateral, encabezado, contenido y pie. */
export default function Layout() {
    const { usuario, logout } = useAuth();
    const { confirmar } = useAvisos();
    const navegar = useNavigate();
    const [menuAbierto, setMenuAbierto] = useState(false);

    const salir = async () => {
        if (await confirmar('¿Desea cerrar su sesión?', { titulo: 'Cerrar sesión', textoAceptar: 'Cerrar sesión' })) {
            await logout();
            navegar('/', { replace: true }); // vuelve a la portada pública
        }
    };

    return (
        <div className="min-h-screen flex bg-fondo">
            <a href="#contenido" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[80] btn btn-primario">
                Saltar al contenido
            </a>
            <Sidebar abierto={menuAbierto} onCerrar={() => setMenuAbierto(false)} />

            <div className="flex-1 min-w-0 flex flex-col">
                <div className="sticky top-0 z-20">
                <header className="bg-superficie px-4 sm:px-6 h-16 flex items-center gap-3">
                    <button className="btn-icono lg:hidden" onClick={() => setMenuAbierto(true)} aria-label="Abrir menú">
                        <Menu size={22} />
                    </button>

                    {/* Persona C: búsqueda predictiva en el encabezado (C5) */}
                    <div className="flex-1 min-w-0 flex items-center justify-start max-w-lg" id="slot-busqueda">
                        <BusquedaGlobal />
                    </div>

                    <CampanaNotificaciones />
                    <PanelAccesibilidad />
                    <Link to="/perfil" className="hidden sm:flex items-center gap-2 text-sm hover:underline" title={usuario?.email}>
                        <UserCircle2 size={22} aria-hidden />
                        <span className="max-w-40 truncate">{usuario?.nombre_completo}</span>
                    </Link>
                    <button className="btn-icono" onClick={salir} aria-label="Cerrar sesión" title="Cerrar sesión">
                        <LogOut size={20} />
                    </button>
                </header>
                <div className="franja-marca" aria-hidden />
                <Breadcrumbs />
                </div>

                <main id="contenido" className="flex-1 p-4 sm:p-6" tabIndex={-1}>
                    <Outlet />
                </main>
                <Footer />
            </div>
        </div>
    );
}
