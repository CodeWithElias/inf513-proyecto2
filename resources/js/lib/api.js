import axios from 'axios';

const meta = (nombre) => document.querySelector(`meta[name="${nombre}"]`)?.content ?? '';

/** Subdirectorio donde está desplegada la app ('' en local, '/inf513/grupo13sa/proyecto2' en tecnoweb). */
export const BASE = meta('app-base');

/**
 * Cliente HTTP único para consumir la API RESTful (/api/v1).
 * Todos los módulos deben usar `api` (no axios directamente).
 */
export const api = axios.create({
    baseURL: meta('api-base') || '/api/v1',
    headers: { Accept: 'application/json' },
});

const CLAVE_TOKEN = 'token';
export const token = {
    obtener: () => localStorage.getItem(CLAVE_TOKEN),
    guardar: (t) => localStorage.setItem(CLAVE_TOKEN, t),
    borrar: () => localStorage.removeItem(CLAVE_TOKEN),
};

api.interceptors.request.use((config) => {
    const t = token.obtener();
    if (t) config.headers.Authorization = `Bearer ${t}`;
    return config;
});

api.interceptors.response.use(
    (r) => r,
    (error) => {
        // Token vencido o inválido: se avisa a AuthContext para volver al login.
        if (error.response?.status === 401 && token.obtener() && !error.config?.url?.includes('auth/login')) {
            token.borrar();
            window.dispatchEvent(new Event('sesion-expirada'));
        }
        return Promise.reject(error);
    },
);

/** Mensaje en español para mostrar al usuario a partir de un error de Axios. */
export function mensajeError(error) {
    if (error?.response?.data?.message) return error.response.data.message;
    if (error?.request && !error?.response) return 'No se pudo conectar con el servidor. Revise su conexión a internet.';
    return 'Ocurrió un error inesperado. Inténtelo nuevamente.';
}

/** Errores de validación del servidor (422) como { campo: "primer mensaje" }. */
export function erroresCampos(error) {
    const errores = error?.response?.data?.errors ?? {};
    return Object.fromEntries(Object.entries(errores).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
}
