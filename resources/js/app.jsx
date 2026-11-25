import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { BASE } from './lib/api';
import { AuthProvider, useAuth } from './context/AuthContext';
import { PreferenciasProvider } from './context/PreferenciasContext';
import { AvisosProvider } from './context/AvisosContext';
import Layout from './components/Layout';
import RequierePrivilegio from './components/RequierePrivilegio';
import Login from './pages/Login';
import Portada from './pages/Portada';
import CotizacionPublica from './pages/comercial/CotizacionPublica';
import NoEncontrado from './pages/NoEncontrado';
import { RUTAS } from './rutas';

/**
 * Exige sesión iniciada. Sin sesión:
 *  - en "/" se muestra la portada pública (primera vista del Visitante);
 *  - en cualquier otra ruta privada se envía al login recordando a dónde iba.
 */
function RutaPrivada({ children }) {
    const { usuario, cargando } = useAuth();
    const location = useLocation();
    if (cargando) {
        return (
            <div className="min-h-screen flex items-center justify-center text-texto-suave">
                <Loader2 className="animate-spin mr-2" aria-hidden /> Cargando…
            </div>
        );
    }
    if (!usuario) {
        if (location.pathname === '/') return <Portada />;
        return <Navigate to="/login" replace state={{ desde: location.pathname }} />;
    }
    return children;
}

function App() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/cotizar" element={<CotizacionPublica />} />
            <Route
                element={
                    <RutaPrivada>
                        <Layout />
                    </RutaPrivada>
                }
            >
                {RUTAS.map(({ ruta, componente: C, privilegio }) => (
                    <Route
                        key={ruta}
                        path={ruta}
                        element={
                            <RequierePrivilegio codigo={privilegio}>
                                <C />
                            </RequierePrivilegio>
                        }
                    />
                ))}
                <Route path="*" element={<NoEncontrado />} />
            </Route>
        </Routes>
    );
}

createRoot(document.getElementById('app')).render(
    <StrictMode>
        <BrowserRouter basename={BASE || '/'}>
            <PreferenciasProvider>
                <AvisosProvider>
                    <AuthProvider>
                        <App />
                    </AuthProvider>
                </AvisosProvider>
            </PreferenciasProvider>
        </BrowserRouter>
    </StrictMode>,
);
