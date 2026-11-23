import { Link } from 'react-router-dom';
import { KeyRound } from 'lucide-react';
import PaginaCrud from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';

export default function Roles() {
    return (
        <PaginaCrud
            titulo="Roles y permisos"
            descripcion="Cada rol tiene una matriz de permisos que define qué menús y acciones ve el usuario."
            singular="rol"
            endpoint="/roles"
            privilegio="roles"
            idCampo="id_rol"
            placeholderBusqueda="Buscar rol…"
            columnas={[
                { titulo: 'ID', campo: 'id_rol', className: 'w-14' },
                { titulo: 'Rol', render: (r) => <span className="font-medium">{r.nombre}</span> },
                { titulo: 'Descripción', render: (r) => <span className="text-texto-suave">{r.descripcion ?? '—'}</span> },
                { titulo: 'Usuarios activos', campo: 'usuarios_activos', className: 'text-center' },
                { titulo: 'Permisos', campo: 'privilegios_count', className: 'text-center' },
                { titulo: 'Estado', render: (r) => <InsigniaActivo activo={r.activo} /> },
            ]}
            campos={[
                { nombre: 'nombre', etiqueta: 'Nombre', reglas: { requerido: true, min: 3, max: 100 } },
                { nombre: 'descripcion', etiqueta: 'Descripción', tipo: 'textarea', reglas: { max: 255 } },
            ]}
            aFormulario={(r) => ({ nombre: r.nombre, descripcion: r.descripcion ?? '' })}
            estado={{ activo: (r) => r.activo, endpoint: (r) => `/roles/${r.id_rol}/estado` }}
            acciones={(r) => (
                <Link to={`/roles/${r.id_rol}/permisos`} className="btn-icono" title="Matriz de permisos" aria-label={`Permisos del rol ${r.nombre}`}>
                    <KeyRound size={16} />
                </Link>
            )}
        />
    );
}
