import { useCallback, useEffect, useRef, useState } from 'react';
import { api, mensajeError } from '../lib/api';

/**
 * Carga un listado (paginado o no) con búsqueda y filtros.
 *   const l = useListado('/usuarios', { activo: '' });
 *   l.filas, l.meta, l.cargando, l.error, l.filtros, l.setFiltro('q', 'ana'), l.setPagina(2), l.recargar()
 */
export function useListado(endpoint, filtrosIniciales = {}) {
    const [filas, setFilas] = useState([]);
    const [meta, setMeta] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [filtros, setFiltros] = useState({ q: '', ...filtrosIniciales });
    const [pagina, setPagina] = useState(1);
    const [version, setVersion] = useState(0);
    const temporizador = useRef(null);
    const [qDiferida, setQDiferida] = useState(filtros.q);

    // Espera 300 ms después de escribir antes de buscar.
    useEffect(() => {
        clearTimeout(temporizador.current);
        temporizador.current = setTimeout(() => setQDiferida(filtros.q), 300);
        return () => clearTimeout(temporizador.current);
    }, [filtros.q]);

    useEffect(() => {
        if (!endpoint) return;
        let vigente = true;
        setCargando(true);
        const params = Object.fromEntries(Object.entries({ ...filtros, q: qDiferida, page: pagina }).filter(([, v]) => v !== '' && v !== null && v !== undefined));
        api.get(endpoint, { params })
            .then(({ data }) => {
                if (!vigente) return;
                setFilas(Array.isArray(data.data) ? data.data : []);
                setMeta(data.meta ?? null);
                setError(null);
            })
            .catch((e) => vigente && setError(mensajeError(e)))
            .finally(() => vigente && setCargando(false));
        return () => {
            vigente = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [endpoint, qDiferida, pagina, version, JSON.stringify({ ...filtros, q: undefined })]);

    const setFiltro = useCallback((nombre, valor) => {
        setFiltros((f) => ({ ...f, [nombre]: valor }));
        setPagina(1);
    }, []);

    return { filas, meta, cargando, error, filtros, setFiltro, pagina, setPagina, recargar: () => setVersion((v) => v + 1) };
}

/** Opciones para <select> desde GET /opciones/{tipo} -> [{ id, nombre }]. */
export function useOpciones(tipo) {
    const [opciones, setOpciones] = useState([]);
    useEffect(() => {
        if (!tipo) return;
        let vigente = true;
        api.get(`/opciones/${tipo}`)
            .then(({ data }) => vigente && setOpciones(data.data))
            .catch(() => vigente && setOpciones([]));
        return () => {
            vigente = false;
        };
    }, [tipo]);
    return opciones;
}
