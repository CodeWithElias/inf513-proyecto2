import { createContext, useContext, useEffect, useMemo, useState } from 'react';

/**
 * Tema (niños, jóvenes, adultos), modo día/noche (auto según la hora o manual),
 * tamaño de fuente y alto contraste. Se guarda en localStorage y se aplica como
 * atributos de <html> (ver resources/css/app.css). Base creada por Persona A;
 * Persona C la amplía y le da el estilo final.
 */
const PreferenciasContext = createContext(null);

const POR_DEFECTO = { tema: 'adultos', modo: 'auto', fuente: 100, contraste: false };
export const TEMAS = [
    { id: 'ninos', nombre: 'Niños' },
    { id: 'jovenes', nombre: 'Jóvenes' },
    { id: 'adultos', nombre: 'Adultos' },
];
export const FUENTE_MIN = 87.5;
export const FUENTE_MAX = 150;

const esDeDia = () => {
    const h = new Date().getHours();
    return h >= 6 && h < 19;
};

function leer() {
    try {
        return { ...POR_DEFECTO, ...JSON.parse(localStorage.getItem('preferencias') || '{}') };
    } catch {
        return POR_DEFECTO;
    }
}

export function PreferenciasProvider({ children }) {
    const [pref, setPref] = useState(leer);
    const [reloj, setReloj] = useState(0);

    // En modo automático se revisa la hora cada minuto.
    useEffect(() => {
        const id = setInterval(() => setReloj((x) => x + 1), 60_000);
        return () => clearInterval(id);
    }, []);

    const modoEfectivo = pref.modo === 'auto' ? (esDeDia() ? 'dia' : 'noche') : pref.modo;

    useEffect(() => {
        const r = document.documentElement;
        r.dataset.tema = pref.tema;
        r.dataset.modo = modoEfectivo;
        if (pref.contraste) r.dataset.contraste = 'alto';
        else delete r.dataset.contraste;
        r.style.fontSize = `${pref.fuente}%`;
        try {
            localStorage.setItem('preferencias', JSON.stringify(pref));
        } catch {
            // almacenamiento no disponible: se aplica igual en esta sesión
        }
    }, [pref, modoEfectivo, reloj]);

    const valor = useMemo(
        () => ({
            ...pref,
            modoEfectivo,
            cambiar: (cambios) => setPref((p) => ({ ...p, ...cambios })),
            restablecer: () => setPref(POR_DEFECTO),
        }),
        [pref, modoEfectivo],
    );

    return <PreferenciasContext.Provider value={valor}>{children}</PreferenciasContext.Provider>;
}

export const usePreferencias = () => useContext(PreferenciasContext);
