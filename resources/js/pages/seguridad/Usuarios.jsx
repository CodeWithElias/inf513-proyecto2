import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';
import { fechaHora } from '../../lib/formato';

const TELEFONO = { patron: /^[0-9+\- ]{6,30}$/, mensajePatron: 'El teléfono solo puede tener números, espacios, + y - (de 6 a 30 caracteres).' };

export default function Usuarios() {
    return (
        <PaginaCrud
            titulo="Usuarios"
            descripcion="Personal de Canal 11, encargados de unidades y clientes que pueden ingresar al sistema."
            singular="usuario"
            endpoint="/usuarios"
            privilegio="usuarios"
            idCampo="id_usuario"
            placeholderBusqueda="Buscar por nombre o correo…"
            filtros={[FILTRO_ESTADO, { nombre: 'id_rol', etiqueta: 'Rol', opciones: 'roles' }]}
            columnas={[
                { titulo: 'ID', campo: 'id_usuario', className: 'w-14' },
                {
                    titulo: 'Usuario',
                    render: (u) => (
                        <div>
                            <p className="font-medium">{u.nombre_completo}</p>
                            <p className="text-xs text-texto-suave">{u.email}</p>
                        </div>
                    ),
                },
                { titulo: 'Rol', render: (u) => u.rol?.nombre },
                { titulo: 'Unidad', render: (u) => u.unidad?.nombre ?? '—' },
                { titulo: 'Último acceso', render: (u) => fechaHora(u.ultimo_acceso) },
                { titulo: 'Estado', render: (u) => <InsigniaActivo activo={u.activo} /> },
            ]}
            campos={[
                { nombre: 'nombre_completo', etiqueta: 'Nombre completo', reglas: { requerido: true, min: 3, max: 200 } },
                { nombre: 'email', etiqueta: 'Correo electrónico', tipo: 'email', ancho: 'medio', reglas: { requerido: true, email: true, max: 150 } },
                { nombre: 'telefono', etiqueta: 'Teléfono', ancho: 'medio', reglas: TELEFONO },
                {
                    nombre: 'password',
                    etiqueta: 'Contraseña',
                    tipo: 'password',
                    reglas: (modo) => (modo === 'crear' ? { requerido: true, min: 6, max: 100 } : { min: 6, max: 100 }),
                    ayuda: (modo) => (modo === 'editar' ? 'Déjela vacía para mantener la contraseña actual.' : 'Mínimo 6 caracteres.'),
                },
                { nombre: 'id_rol', etiqueta: 'Rol', tipo: 'select', opciones: 'roles', ancho: 'medio', reglas: { requerido: true } },
                { nombre: 'id_unidad', etiqueta: 'Unidad universitaria', tipo: 'select', opciones: 'unidades', ancho: 'medio', vacio: 'Sin unidad' },
            ]}
            aFormulario={(u) => ({
                nombre_completo: u.nombre_completo,
                email: u.email,
                telefono: u.telefono ?? '',
                password: '',
                id_rol: String(u.id_rol),
                id_unidad: u.id_unidad ? String(u.id_unidad) : '',
            })}
            estado={{ activo: (u) => u.activo, endpoint: (u) => `/usuarios/${u.id_usuario}/estado` }}
        />
    );
}
