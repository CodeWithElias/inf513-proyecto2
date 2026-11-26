import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { fechaHora } from '../lib/formato';
import Icono from '../components/Icono';

/** Página de inicio: bienvenida y accesos rápidos según el menú del rol. */
export default function Inicio() {
    const { usuario, menu } = useAuth();
    const accesos = menu.flatMap((m) => (m.hijos?.length ? m.hijos : m.ruta && m.ruta !== '/' ? [m] : []));

    return (
        <div>
            <section className="tarjeta p-6 mb-6 bg-primario-suave">
                <h1 className="text-2xl font-bold">Hola, {usuario.nombre_completo}</h1>
                <p className="text-texto-suave mt-1">
                    Ingresó como <strong>{usuario.rol}</strong>
                    {usuario.unidad && <> de la unidad <strong>{usuario.unidad}</strong></>}
                    {usuario.empresa && <> de la empresa <strong>{usuario.empresa}</strong></>}.
                </p>
                {usuario.ultimo_acceso && <p className="text-xs text-texto-suave mt-2">Último acceso: {fechaHora(usuario.ultimo_acceso)}</p>}
            </section>

            {usuario.rol === 'Cliente' && !usuario.id_empresa && (
                <section className="tarjeta p-5 mb-6 border-l-4 border-l-acento flex flex-col sm:flex-row sm:items-center gap-3 justify-between" role="status">
                    <div>
                        <p className="font-semibold">Registre su empresa para contratar campañas</p>
                        <p className="text-sm text-texto-suave">
                            Ya puede ver las proformas que generó como visitante. Para cotizar desde su cuenta, cargar material, pagar en línea y ver el impacto de sus anuncios, registre la empresa que representa.
                        </p>
                    </div>
                    <Link to="/perfil" className="btn btn-primario shrink-0">Registrar mi empresa</Link>
                </section>
            )}

            <h2 className="text-lg font-semibold mb-3">Accesos rápidos</h2>
            <div className="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {accesos.map((a) => (
                    <Link key={a.id} to={a.ruta} className="tarjeta p-4 flex items-center gap-3 hover:border-primario transition-colors">
                        <span className="size-10 rounded-tema bg-primario-suave text-primario flex items-center justify-center shrink-0">
                            <Icono nombre={a.icono} size={20} />
                        </span>
                        <span className="font-medium">{a.titulo}</span>
                    </Link>
                ))}
            </div>
        </div>
    );
}
