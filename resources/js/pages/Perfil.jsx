import { useAuth } from '../context/AuthContext';
import { fechaHora } from '../lib/formato';
import EncabezadoPagina from '../components/EncabezadoPagina';

export default function Perfil() {
    const { usuario, privilegios } = useAuth();
    const filas = [
        ['Nombre', usuario.nombre_completo],
        ['Correo', usuario.email],
        ['Teléfono', usuario.telefono || '—'],
        ['Rol', usuario.rol],
        ['Unidad', usuario.unidad || '—'],
        ['Empresa', usuario.empresa || '—'],
        ['Último acceso', fechaHora(usuario.ultimo_acceso)],
        ['Permisos del rol', `${privilegios.size} permisos`],
    ];
    return (
        <div className="max-w-2xl">
            <EncabezadoPagina titulo="Mi perfil" descripcion="Sus datos de usuario. Para modificarlos, comuníquese con el administrador." />
            <dl className="tarjeta divide-y divide-borde">
                {filas.map(([k, v]) => (
                    <div key={k} className="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                        <dt className="text-texto-suave">{k}</dt>
                        <dd className="col-span-2 font-medium break-words">{v}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
