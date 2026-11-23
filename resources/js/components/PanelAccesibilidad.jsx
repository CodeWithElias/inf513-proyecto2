import { useEffect, useRef, useState } from 'react';
import { Accessibility, Sun, Moon, Clock, Contrast, RotateCcw } from 'lucide-react';
import { FUENTE_MAX, FUENTE_MIN, TEMAS, usePreferencias } from '../context/PreferenciasContext';

/** Selector de tema, modo día/noche, tamaño de letra y contraste (encabezado). */
export default function PanelAccesibilidad() {
    const p = usePreferencias();
    const [abierto, setAbierto] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const cerrar = (e) => ref.current && !ref.current.contains(e.target) && setAbierto(false);
        const esc = (e) => e.key === 'Escape' && setAbierto(false);
        document.addEventListener('mousedown', cerrar);
        document.addEventListener('keydown', esc);
        return () => {
            document.removeEventListener('mousedown', cerrar);
            document.removeEventListener('keydown', esc);
        };
    }, []);

    const opcion = (activo) =>
        `btn flex-1 !px-2 !py-1.5 text-xs ${activo ? 'btn-primario' : 'btn-secundario'}`;

    return (
        <div className="relative" ref={ref}>
            <button className="btn-icono" onClick={() => setAbierto((a) => !a)} aria-expanded={abierto} aria-label="Apariencia y accesibilidad" title="Apariencia y accesibilidad">
                <Accessibility size={22} />
            </button>
            {abierto && (
                <div className="tarjeta absolute right-0 mt-2 w-72 p-4 z-50 space-y-4" role="dialog" aria-label="Apariencia y accesibilidad">
                    <fieldset>
                        <legend className="etiqueta">Tema</legend>
                        <div className="flex gap-1">
                            {TEMAS.map((t) => (
                                <button key={t.id} className={opcion(p.tema === t.id)} onClick={() => p.cambiar({ tema: t.id })} aria-pressed={p.tema === t.id}>
                                    {t.nombre}
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="etiqueta">Modo</legend>
                        <div className="flex gap-1">
                            <button className={opcion(p.modo === 'auto')} onClick={() => p.cambiar({ modo: 'auto' })} aria-pressed={p.modo === 'auto'}>
                                <Clock size={14} /> Auto
                            </button>
                            <button className={opcion(p.modo === 'dia')} onClick={() => p.cambiar({ modo: 'dia' })} aria-pressed={p.modo === 'dia'}>
                                <Sun size={14} /> Día
                            </button>
                            <button className={opcion(p.modo === 'noche')} onClick={() => p.cambiar({ modo: 'noche' })} aria-pressed={p.modo === 'noche'}>
                                <Moon size={14} /> Noche
                            </button>
                        </div>
                        {p.modo === 'auto' && (
                            <p className="text-xs text-texto-suave mt-1">Según la hora: día de 06:00 a 18:59 (ahora: {p.modoEfectivo === 'dia' ? 'día' : 'noche'}).</p>
                        )}
                    </fieldset>

                    <div>
                        <label htmlFor="tam-fuente" className="etiqueta">
                            Tamaño de letra: {Math.round(p.fuente)}%
                        </label>
                        <div className="flex items-center gap-2">
                            <button className="btn btn-secundario !px-2 !py-1" onClick={() => p.cambiar({ fuente: Math.max(FUENTE_MIN, p.fuente - 12.5) })} aria-label="Reducir letra">
                                A−
                            </button>
                            <input
                                id="tam-fuente"
                                type="range"
                                min={FUENTE_MIN}
                                max={FUENTE_MAX}
                                step={12.5}
                                value={p.fuente}
                                onChange={(e) => p.cambiar({ fuente: Number(e.target.value) })}
                                className="flex-1 accent-[var(--primario)]"
                            />
                            <button className="btn btn-secundario !px-2 !py-1" onClick={() => p.cambiar({ fuente: Math.min(FUENTE_MAX, p.fuente + 12.5) })} aria-label="Aumentar letra">
                                A+
                            </button>
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" checked={p.contraste} onChange={(e) => p.cambiar({ contraste: e.target.checked })} className="size-4" />
                        <Contrast size={16} aria-hidden /> Alto contraste
                    </label>

                    <button className="btn btn-secundario w-full" onClick={p.restablecer}>
                        <RotateCcw size={14} /> Restablecer
                    </button>
                </div>
            )}
        </div>
    );
}
