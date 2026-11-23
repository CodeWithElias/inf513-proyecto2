import { UserMinus } from 'lucide-react';
import PaginaCrud from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { api, mensajeError } from '../../lib/api';
import { limpiarOpciones } from '../../hooks/useListado';

const CI = { patron: /^[0-9]{4,12}([ -][0-9A-Za-z]{1,4})?$/, mensajePatron: 'El CI debe tener de 4 a 12 dígitos y, opcionalmente, una extensión (ej. 7654321 SC).' };

/** Clientes = usuarios registrados como representantes de una empresa anunciante. */
export default function Representantes() {
    const { puede } = useAuth();
    const { confirmar, exito, error } = useAvisos();

    const quitar = async (r, recargar) => {
        const ok = await confirmar(`¿Quitar a ${r.usuario?.nombre_completo} como cliente de ${r.empresa?.razon_social}? Su usuario seguirá existiendo.`, {
            titulo: 'Quitar cliente',
            textoAceptar: 'Quitar',
            peligro: true,
        });
        if (!ok) return;
        try {
            const { data } = await api.delete(`/representantes/${r.id_usuario}`);
            exito(data.message);
            limpiarOpciones();
            recargar();
        } catch (e) {
            error(mensajeError(e));
        }
    };

    return (
        <PaginaCrud
            titulo="Clientes"
            descripcion="Representantes de las empresas anunciantes. Primero registre a la persona como usuario con rol Cliente."
            singular="cliente"
            endpoint="/representantes"
            privilegio="representantes"
            idCampo="id_usuario"
            placeholderBusqueda="Buscar por nombre, correo, CI o empresa…"
            filtros={[{ nombre: 'id_empresa', etiqueta: 'Empresa', opciones: 'empresas' }]}
            columnas={[
                {
                    titulo: 'Cliente',
                    render: (r) => (
                        <div>
                            <p className="font-medium">{r.usuario?.nombre_completo}</p>
                            <p className="text-xs text-texto-suave">{r.usuario?.email}</p>
                        </div>
                    ),
                },
                { titulo: 'CI', campo: 'ci' },
                { titulo: 'Cargo', render: (r) => r.cargo ?? '—' },
                { titulo: 'Empresa', render: (r) => r.empresa?.razon_social },
                { titulo: 'Usuario', render: (r) => <InsigniaActivo activo={r.usuario?.activo} /> },
            ]}
            campos={[
                { nombre: 'id_usuario', etiqueta: 'Usuario', tipo: 'select', opciones: 'usuarios-sin-cliente', soloCrear: true, reglas: { requerido: true } },
                { nombre: 'ci', etiqueta: 'Carnet de identidad', ancho: 'medio', placeholder: '7654321 SC', reglas: { requerido: true, ...CI } },
                { nombre: 'cargo', etiqueta: 'Cargo', ancho: 'medio', placeholder: 'Gerente de marketing', reglas: { max: 100 } },
                { nombre: 'id_empresa', etiqueta: 'Empresa anunciante', tipo: 'select', opciones: 'empresas', reglas: { requerido: true } },
            ]}
            aFormulario={(r) => ({ ci: r.ci, cargo: r.cargo ?? '', id_empresa: String(r.id_empresa) })}
            acciones={(r, recargar) =>
                puede('representantes.eliminar') && (
                    <button className="btn-icono hover:!text-peligro" onClick={() => quitar(r, recargar)} title="Quitar como cliente" aria-label="Quitar como cliente">
                        <UserMinus size={16} />
                    </button>
                )
            }
        />
    );
}
