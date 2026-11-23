import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { Eye, EyeOff, LogIn } from 'lucide-react';
import Logo from '../components/Logo';
import { useAuth } from '../context/AuthContext';
import { useFormulario } from '../hooks/useFormulario';
import Campo from '../components/Campo';
import Footer from '../components/Footer';
import PanelAccesibilidad from '../components/PanelAccesibilidad';

const REGLAS = {
    email: { requerido: true, email: true, max: 150, etiqueta: 'correo electrónico' },
    password: { requerido: true, max: 100, etiqueta: 'contraseña' },
};

export default function Login() {
    const { usuario, login } = useAuth();
    const navegar = useNavigate();
    const desde = useLocation().state?.desde ?? '/';
    const [verClave, setVerClave] = useState(false);
    const f = useFormulario({ email: '', password: '' }, REGLAS);

    if (usuario) return <Navigate to={desde} replace />;

    const ingresar = async (e) => {
        e.preventDefault();
        const r = await f.enviar((v) => login(v.email, v.password));
        if (r) navegar(desde, { replace: true });
    };

    return (
        <div className="min-h-screen flex flex-col bg-fondo">
            <div className="flex justify-end p-3">
                <PanelAccesibilidad />
            </div>
            <main className="flex-1 flex items-center justify-center p-4">
                <div className="tarjeta w-full max-w-md overflow-hidden">
                    <div className="franja-marca" aria-hidden />
                    <div className="p-8">
                    <div className="flex flex-col items-center text-center gap-3 mb-6">
                        <Logo className="size-28 shadow-md" />
                        <div>
                            <h1 className="text-xl font-bold">TVU · Canal 11 UAGRM</h1>
                            <p className="text-sm text-texto-suave">Sistema de Gestión Publicitaria</p>
                        </div>
                    </div>

                    <form onSubmit={ingresar} noValidate className="space-y-4">
                        {f.errorGeneral && (
                            <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{f.errorGeneral}</p>
                        )}
                        <Campo etiqueta="Correo electrónico" nombre="email" tipo="email" valor={f.valores.email} onChange={f.cambiar} error={f.errores.email} requerido autoComplete="username" autoFocus />
                        <div className="relative">
                            <Campo etiqueta="Contraseña" nombre="password" tipo={verClave ? 'text' : 'password'} valor={f.valores.password} onChange={f.cambiar} error={f.errores.password} requerido autoComplete="current-password" />
                            <button type="button" className="btn-icono absolute right-1.5 top-7" onClick={() => setVerClave((v) => !v)} aria-label={verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'}>
                                {verClave ? <EyeOff size={16} /> : <Eye size={16} />}
                            </button>
                        </div>
                        <button type="submit" className="btn btn-primario w-full" disabled={f.enviando}>
                            <LogIn size={16} /> {f.enviando ? 'Ingresando…' : 'Ingresar'}
                        </button>
                    </form>
                    <p className="text-xs text-texto-suave mt-6 text-center">
                        Por seguridad, después de 5 intentos fallidos se bloquea el acceso durante un minuto.
                    </p>
                    </div>
                </div>
            </main>
            <Footer />
        </div>
    );
}
