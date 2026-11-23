import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, token } from '../lib/api';

const AuthContext = createContext(null);

/**
 * Sesión del usuario: datos, privilegios (RBAC) y menú dinámico que llegan de /auth/me.
 *   const { usuario, puede, menu, login, logout } = useAuth();
 *   puede('usuarios.crear')  -> true/false
 */
export function AuthProvider({ children }) {
    const [sesion, setSesion] = useState(null); // { usuario, privilegios, menu }
    const [cargando, setCargando] = useState(Boolean(token.obtener()));

    const recargar = useCallback(async () => {
        if (!token.obtener()) {
            setSesion(null);
            setCargando(false);
            return;
        }
        try {
            const { data } = await api.get('/auth/me');
            setSesion(data.data);
        } catch {
            token.borrar();
            setSesion(null);
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => {
        recargar();
        const expirada = () => setSesion(null);
        window.addEventListener('sesion-expirada', expirada);
        return () => window.removeEventListener('sesion-expirada', expirada);
    }, [recargar]);

    const login = useCallback(async (email, password) => {
        const { data } = await api.post('/auth/login', { email, password });
        token.guardar(data.data.token);
        setSesion(data.data.sesion);
        return data;
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // aunque falle, se cierra localmente
        }
        token.borrar();
        setSesion(null);
    }, []);

    const valor = useMemo(() => {
        const privilegios = new Set(sesion?.privilegios ?? []);
        return {
            usuario: sesion?.usuario ?? null,
            menu: sesion?.menu ?? [],
            privilegios,
            puede: (codigo) => privilegios.has(codigo),
            cargando,
            login,
            logout,
            recargar,
        };
    }, [sesion, cargando, login, logout, recargar]);

    return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);
