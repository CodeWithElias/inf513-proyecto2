import { useEffect, useState } from 'react';
import { Building2, KeyRound, Save, UserRound } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useAvisos } from '../context/AvisosContext';
import { useFormulario } from '../hooks/useFormulario';
import { api } from '../lib/api';
import { fechaHora } from '../lib/formato';
import EncabezadoPagina from '../components/EncabezadoPagina';
import Campo from '../components/Campo';

const TELEFONO = { patron: /^[0-9+\- ]{6,30}$/, mensajePatron: 'El teléfono solo puede tener números, espacios, + y - (de 6 a 30 caracteres).' };
const CI = { patron: /^[0-9]{4,12}([ -][0-9A-Za-z]{1,4})?$/, mensajePatron: 'El CI debe tener de 4 a 12 dígitos y, opcionalmente, una extensión (ej. 7654321 SC).' };
const NIT = { patron: /^[0-9A-Za-z-]+$/, mensajePatron: 'El NIT solo puede tener letras, números y guiones.' };

function Seccion({ icono: I, titulo, descripcion, children }) {
    return (
        <section className="tarjeta p-5">
            <h2 className="font-semibold flex items-center gap-2"><I size={18} className="text-primario" aria-hidden /> {titulo}</h2>
            {descripcion && <p className="text-sm text-texto-suave mt-1 mb-4">{descripcion}</p>}
            {children}
        </section>
    );
}

export default function Perfil() {
    const { usuario } = useAuth();
    return (
        <div className="max-w-3xl space-y-5">
            <EncabezadoPagina titulo="Mi perfil" descripcion={`${usuario.email} · ${usuario.rol}${usuario.unidad ? ` · ${usuario.unidad}` : ''} · Último acceso: ${fechaHora(usuario.ultimo_acceso)}`} />
            <DatosPersonales />
            <CambiarPassword />
            {usuario.rol === 'Cliente' && <MiEmpresa />}
        </div>
    );
}

function DatosPersonales() {
    const { usuario, recargar } = useAuth();
    const { exito } = useAvisos();
    const f = useFormulario({ nombre_completo: usuario.nombre_completo, telefono: usuario.telefono ?? '' });

    const guardar = async (e) => {
        e.preventDefault();
        const r = await f.enviar((v) => api.put('/auth/perfil', v), {
            nombre_completo: { requerido: true, min: 3, max: 200, etiqueta: 'nombre completo' },
            telefono: { ...TELEFONO, etiqueta: 'teléfono' },
        });
        if (r) {
            exito(r.data.message);
            recargar();
        }
    };

    return (
        <Seccion icono={UserRound} titulo="Datos personales" descripcion="El correo identifica su cuenta y no se puede cambiar desde aquí.">
            <form onSubmit={guardar} noValidate className="grid gap-4 sm:grid-cols-2">
                {f.errorGeneral && <p className="sm:col-span-2 text-sm text-peligro" role="alert">{f.errorGeneral}</p>}
                <div className="sm:col-span-2">
                    <Campo etiqueta="Nombre completo" nombre="nombre_completo" valor={f.valores.nombre_completo} onChange={f.cambiar} error={f.errores.nombre_completo} requerido />
                </div>
                <Campo etiqueta="Correo electrónico" nombre="email" valor={usuario.email} onChange={() => {}} deshabilitado />
                <Campo etiqueta="Teléfono" nombre="telefono" valor={f.valores.telefono} onChange={f.cambiar} error={f.errores.telefono} />
                <div className="sm:col-span-2 flex justify-end">
                    <button type="submit" className="btn btn-primario" disabled={f.enviando}><Save size={16} /> Guardar datos</button>
                </div>
            </form>
        </Seccion>
    );
}

function CambiarPassword() {
    const { exito } = useAvisos();
    const vacio = { password_actual: '', password: '', password_confirmation: '' };
    const f = useFormulario(vacio);

    const guardar = async (e) => {
        e.preventDefault();
        const r = await f.enviar((v) => api.put('/auth/password', v), {
            password_actual: { requerido: true, etiqueta: 'contraseña actual' },
            password: { requerido: true, min: 6, max: 100, etiqueta: 'nueva contraseña' },
            password_confirmation: { requerido: true, igualA: 'password', etiqueta: 'confirmación de la nueva contraseña' },
        });
        if (r) {
            exito(r.data.message);
            f.reiniciar(vacio);
        }
    };

    return (
        <Seccion icono={KeyRound} titulo="Cambiar contraseña" descripcion="Al cambiarla se cierran sus sesiones abiertas en otros dispositivos.">
            <form onSubmit={guardar} noValidate className="grid gap-4 sm:grid-cols-3">
                {f.errorGeneral && <p className="sm:col-span-3 text-sm text-peligro" role="alert">{f.errorGeneral}</p>}
                <Campo etiqueta="Contraseña actual" nombre="password_actual" tipo="password" valor={f.valores.password_actual} onChange={f.cambiar} error={f.errores.password_actual} requerido autoComplete="current-password" />
                <Campo etiqueta="Nueva contraseña" nombre="password" tipo="password" valor={f.valores.password} onChange={f.cambiar} error={f.errores.password} requerido autoComplete="new-password" />
                <Campo etiqueta="Repita la nueva" nombre="password_confirmation" tipo="password" valor={f.valores.password_confirmation} onChange={f.cambiar} error={f.errores.password_confirmation} requerido autoComplete="new-password" />
                <div className="sm:col-span-3 flex justify-end">
                    <button type="submit" className="btn btn-primario" disabled={f.enviando}><KeyRound size={16} /> Cambiar contraseña</button>
                </div>
            </form>
        </Seccion>
    );
}

function MiEmpresa() {
    const { recargar } = useAuth();
    const { exito } = useAvisos();
    const [representante, setRepresentante] = useState(undefined); // undefined = cargando
    const vacio = { razon_social: '', nit: '', nombre_comercial: '', direccion_facturacion: '', email_facturacion: '', telefono: '', ci: '', cargo: '' };
    const f = useFormulario(vacio);

    useEffect(() => {
        api.get('/mi-empresa').then(({ data }) => setRepresentante(data.data)).catch(() => setRepresentante(null));
    }, []);

    const registrar = async (e) => {
        e.preventDefault();
        const r = await f.enviar((v) => api.post('/mi-empresa', v), {
            razon_social: { requerido: true, min: 2, max: 200, etiqueta: 'razón social' },
            nit: { requerido: true, min: 4, max: 30, ...NIT, etiqueta: 'NIT' },
            nombre_comercial: { max: 200, etiqueta: 'nombre comercial' },
            direccion_facturacion: { max: 255, etiqueta: 'dirección de facturación' },
            email_facturacion: { email: true, max: 150, etiqueta: 'correo de facturación' },
            telefono: { ...TELEFONO, etiqueta: 'teléfono de la empresa' },
            ci: { requerido: true, ...CI, etiqueta: 'su carnet de identidad' },
            cargo: { max: 100, etiqueta: 'cargo' },
        });
        if (r) {
            exito(r.data.message);
            const { data } = await api.get('/mi-empresa');
            setRepresentante(data.data);
            recargar(); // actualiza la sesión (empresa) en toda la app
        }
    };

    if (representante === undefined) {
        return <Seccion icono={Building2} titulo="Mi empresa"><p className="text-sm text-texto-suave">Cargando…</p></Seccion>;
    }

    if (representante) {
        const e = representante.empresa ?? {};
        const filas = [
            ['Razón social', e.razon_social],
            ['NIT', e.nit],
            ['Nombre comercial', e.nombre_comercial],
            ['Dirección de facturación', e.direccion_facturacion],
            ['Correo de facturación', e.email_facturacion],
            ['Su CI', representante.ci],
            ['Su cargo', representante.cargo],
        ];
        return (
            <Seccion icono={Building2} titulo="Mi empresa" descripcion="Usted es representante de esta empresa. Para cambiar sus datos, comuníquese con Canal 11.">
                <dl className="divide-y divide-borde text-sm">
                    {filas.map(([k, v]) => (
                        <div key={k} className="grid grid-cols-3 gap-4 py-2">
                            <dt className="text-texto-suave">{k}</dt>
                            <dd className="col-span-2 font-medium break-words">{v || '—'}</dd>
                        </div>
                    ))}
                </dl>
            </Seccion>
        );
    }

    return (
        <Seccion
            icono={Building2}
            titulo="Registrar mi empresa"
            descripcion="Registre la empresa que representa para contratar campañas, cargar material, pagar en línea y ver el impacto de sus anuncios. Sus proformas anteriores pasarán a estar a nombre de la empresa."
        >
            <form onSubmit={registrar} noValidate className="grid gap-4 sm:grid-cols-2">
                {f.errorGeneral && <p className="sm:col-span-2 text-sm text-peligro" role="alert">{f.errorGeneral}</p>}
                <div className="sm:col-span-2">
                    <Campo etiqueta="Razón social" nombre="razon_social" valor={f.valores.razon_social} onChange={f.cambiar} error={f.errores.razon_social} requerido placeholder="Mi Empresa S.R.L." />
                </div>
                <Campo etiqueta="NIT" nombre="nit" valor={f.valores.nit} onChange={f.cambiar} error={f.errores.nit} requerido />
                <Campo etiqueta="Nombre comercial" nombre="nombre_comercial" valor={f.valores.nombre_comercial} onChange={f.cambiar} error={f.errores.nombre_comercial} />
                <div className="sm:col-span-2">
                    <Campo etiqueta="Dirección de facturación" nombre="direccion_facturacion" valor={f.valores.direccion_facturacion} onChange={f.cambiar} error={f.errores.direccion_facturacion} />
                </div>
                <Campo etiqueta="Correo de facturación" nombre="email_facturacion" tipo="email" valor={f.valores.email_facturacion} onChange={f.cambiar} error={f.errores.email_facturacion} />
                <Campo etiqueta="Teléfono de la empresa" nombre="telefono" valor={f.valores.telefono} onChange={f.cambiar} error={f.errores.telefono} />
                <Campo etiqueta="Su carnet de identidad" nombre="ci" valor={f.valores.ci} onChange={f.cambiar} error={f.errores.ci} requerido placeholder="7654321 SC" ayuda="Queda registrado como representante." />
                <Campo etiqueta="Su cargo en la empresa" nombre="cargo" valor={f.valores.cargo} onChange={f.cambiar} error={f.errores.cargo} placeholder="Gerente de marketing" />
                <div className="sm:col-span-2 flex justify-end">
                    <button type="submit" className="btn btn-primario" disabled={f.enviando}><Building2 size={16} /> Registrar mi empresa</button>
                </div>
            </form>
        </Seccion>
    );
}
