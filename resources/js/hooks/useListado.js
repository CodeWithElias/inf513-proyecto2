import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
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
    // ?q= en la URL (lo usa la búsqueda global del encabezado) llega como texto de búsqueda inicial
    const [params] = useSearchParams();
    const qUrl = params.get('q') ?? '';
    // Los demás filtros también pueden venir en la URL (ej. ?estado=pendiente desde los pendientes de Inicio)
    const [filtros, setFiltros] = useState(() => ({
        ...Object.fromEntries(Object.entries(filtrosIniciales).map(([k, v]) => [k, params.get(k) ?? v])),
        q: qUrl || filtrosIniciales.q || '',
    }));
    const [pagina, setPagina] = useState(1);
    const [version, setVersion] = useState(0);
    const temporizador = useRef(null);
    const [qDiferida, setQDiferida] = useState(filtros.q);

    // Si se busca otra vez desde el encabezado estando ya en la misma pantalla, cambia ?q= sin desmontar
    const qUrlAnterior = useRef(qUrl);
    useEffect(() => {
        if (qUrl === qUrlAnterior.current) return;
        qUrlAnterior.current = qUrl;
        setFiltros((f) => ({ ...f, q: qUrl }));
        setPagina(1);
    }, [qUrl]);

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

/**
 * Caché de opciones compartido por toda la app: cada lista (/opciones/roles, ...)
 * se pide UNA vez y la reutilizan todos los selects y filtros. Se guarda la promesa,
 * así dos componentes que la piden a la vez comparten la misma petición.
 */
const cacheOpciones = new Map();

function pedirOpciones(tipo) {
    if (!cacheOpciones.has(tipo)) {
        const promesa = api.get(`/opciones/${tipo}`).then(({ data }) => data.data);
        promesa.catch(() => cacheOpciones.delete(tipo)); // si falla, se reintenta la próxima vez
        cacheOpciones.set(tipo, promesa);
    }
    return cacheOpciones.get(tipo);
}

/** Vacía el caché (después de crear o modificar registros que aparecen en los selects). */
export function limpiarOpciones() {
    cacheOpciones.clear();
}

/**
 * Opciones para <select> desde GET /opciones/{tipo} -> [{ id, nombre }].
 *   const { opciones, cargando } = useOpciones('roles');
 */
export function useOpciones(tipo) {
    const [estado, setEstado] = useState({ opciones: [], cargando: Boolean(tipo) });
    useEffect(() => {
        if (!tipo) return;
        let vigente = true;
        setEstado((e) => ({ ...e, cargando: true }));
        pedirOpciones(tipo)
            .then((opciones) => vigente && setEstado({ opciones, cargando: false }))
            .catch(() => vigente && setEstado({ opciones: [], cargando: false }));
        return () => {
            vigente = false;
        };
    }, [tipo]);
    return estado;
}
