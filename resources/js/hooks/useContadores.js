import { useCallback, useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../context/AuthContext';

/**
 * Contadores de pendientes que se muestran en el menú lateral, por ruta del menú.
 *   { '/solicitudes': 3 }
 * Se actualizan al entrar, al cambiar de pantalla, cada minuto y cuando una pantalla
 * avisa un cambio con:  window.dispatchEvent(new Event('contadores-cambiaron'))
 * Para agregar otro contador: sumar una entrada a FUENTES.
 */
const FUENTES = [
    { ruta: '/solicitudes', privilegio: 'solicitudes.ver', endpoint: '/solicitudes/contador', campo: 'pendientes' },
];
const CADA_MS = 60_000;

export function avisarCambioContadores() {
    window.dispatchEvent(new Event('contadores-cambiaron'));
}

export function useContadores() {
    const { puede } = useAuth();
    const { pathname } = useLocation();
    const [contadores, setContadores] = useState({});
    const fuentes = FUENTES.filter((f) => puede(f.privilegio));
    const clave = fuentes.map((f) => f.ruta).join(',');

    const actualizar = useCallback(async () => {
        const pares = await Promise.all(
            fuentes.map((f) =>
                api.get(f.endpoint)
                    .then(({ data }) => [f.ruta, Number(data.data?.[f.campo] ?? 0)])
                    .catch(() => [f.ruta, null]),
            ),
        );
        setContadores(Object.fromEntries(pares.filter(([, n]) => n !== null)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clave]);

    // Cada minuto y cuando una pantalla avisa un cambio (la carga inicial la hace el efecto de abajo)
    useEffect(() => {
        if (!fuentes.length) return undefined;
        const id = setInterval(actualizar, CADA_MS);
        window.addEventListener('contadores-cambiaron', actualizar);
        return () => {
            clearInterval(id);
            window.removeEventListener('contadores-cambiaron', actualizar);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [actualizar]);

    // Al entrar y al cambiar de pantalla
    useEffect(() => {
        if (fuentes.length) actualizar();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pathname]);

    return contadores;
}
