import { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { ChevronDown } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import Icono from './Icono';
import Logo from './Logo';

/** Menú lateral renderizado desde la BD (menu_item) según los privilegios del rol. */
export default function Sidebar({ abierto, onCerrar }) {
    const { menu, usuario } = useAuth();
    const { pathname } = useLocation();
    const grupoActivo = menu.find((g) => g.hijos?.some((h) => pathname.startsWith(h.ruta)))?.id;
    const [abiertos, setAbiertos] = useState(() => new Set(grupoActivo ? [grupoActivo] : []));

    const alternar = (id) =>
        setAbiertos((s) => {
            const n = new Set(s);
            n.has(id) ? n.delete(id) : n.add(id);
            return n;
        });

    const claseEnlace = ({ isActive }) =>
        `flex items-center gap-3 px-3 py-2 rounded-tema text-sm transition-colors ${
            isActive ? 'bg-primario text-primario-texto font-semibold' : 'text-lateral-texto/85 hover:bg-white/10'
        }`;

    return (
        <>
            {abierto && <div className="fixed inset-0 bg-black/40 z-30 lg:hidden" onClick={onCerrar} aria-hidden />}
            <aside
                className={`fixed lg:sticky top-0 left-0 z-40 h-screen w-64 shrink-0 bg-lateral text-lateral-texto flex flex-col transition-transform lg:translate-x-0 ${
                    abierto ? 'translate-x-0' : '-translate-x-full'
                }`}
                aria-label="Menú principal"
            >
                <div className="px-4 py-4 border-b border-white/10 flex items-center gap-3">
                    <Logo className="size-11" />
                    <div>
                        <p className="text-lg font-bold leading-tight">TVU Canal 11</p>
                        <p className="text-xs opacity-75">Gestión Publicitaria · UAGRM</p>
                    </div>
                </div>
                <div className="franja-marca" aria-hidden />

                <nav className="flex-1 overflow-y-auto p-3 space-y-1">
                    {menu.map((item) =>
                        item.hijos?.length ? (
                            <div key={item.id}>
                                <button
                                    type="button"
                                    onClick={() => alternar(item.id)}
                                    className="w-full flex items-center gap-3 px-3 py-2 rounded-tema text-sm font-semibold hover:bg-white/10 cursor-pointer"
                                    aria-expanded={abiertos.has(item.id)}
                                >
                                    <Icono nombre={item.icono} />
                                    <span className="flex-1 text-left">{item.titulo}</span>
                                    <ChevronDown size={16} className={`transition-transform ${abiertos.has(item.id) ? 'rotate-180' : ''}`} aria-hidden />
                                </button>
                                {abiertos.has(item.id) && (
                                    <div className="ml-4 mt-1 space-y-1 border-l border-white/15 pl-2">
                                        {item.hijos.map((h) => (
                                            <NavLink key={h.id} to={h.ruta} className={claseEnlace} onClick={onCerrar}>
                                                <Icono nombre={h.icono} size={16} />
                                                {h.titulo}
                                            </NavLink>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ) : (
                            <NavLink key={item.id} to={item.ruta} end={item.ruta === '/'} className={claseEnlace} onClick={onCerrar}>
                                <Icono nombre={item.icono} />
                                {item.titulo}
                            </NavLink>
                        ),
                    )}
                </nav>

                <div className="px-5 py-3 border-t border-white/10 text-xs opacity-80">
                    Rol: <span className="font-semibold">{usuario?.rol}</span>
                </div>
            </aside>
        </>
    );
}
