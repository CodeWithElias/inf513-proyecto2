import { useState } from 'react';
import { UserCheck, UserX } from 'lucide-react';
import PaginaCrud from '../../components/PaginaCrud';
import Insignia from '../../components/Insignia';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { useFormulario } from '../../hooks/useFormulario';
import { avisarCambioContadores } from '../../hooks/useContadores';
import { api, mensajeError } from '../../lib/api';
import { fechaHora, moneda } from '../../lib/formato';

const ESTADOS = {
    pendiente: ['Pendiente', 'aviso'],
    aprobada: ['Aprobada', 'exito'],
    rechazada: ['Rechazada', 'peligro'],
};

/** Visitantes que cotizaron y pidieron una cuenta: el Director/Administrador la aprueba (crea un Cliente) o la rechaza. */
export default function Solicitudes() {
    const { puede } = useAuth();
    const { confirmar, exito, error } = useAvisos();
    const [rechazo, setRechazo] = useState(null); // { solicitud, recargar }

    const aprobar = async (s, recargar) => {
        const ok = await confirmar(
            `Se creará la cuenta de cliente de ${s.visitante?.nombre} (${s.visitante?.email}) con la contraseña que eligió al cotizar. Podrá iniciar sesión y ver sus proformas.`,
            { titulo: 'Aprobar solicitud', textoAceptar: 'Aprobar y crear cuenta' },
        );
        if (!ok) return;
        try {
            const { data } = await api.patch(`/solicitudes/${s.id_solicitud}/aprobar`);
            exito(data.message);
            recargar();
            avisarCambioContadores(); // actualiza el contador del menú al instante
        } catch (e) {
            error(mensajeError(e));
        }
    };

    return (
        <>
            <PaginaCrud
                titulo="Solicitudes de cuenta"
                descripcion="Visitantes que cotizaron en la web y pidieron una cuenta. Al aprobar se crea su usuario con rol Cliente y conserva sus proformas."
                singular="solicitud"
                endpoint="/solicitudes"
                privilegio="solicitudes"
                idCampo="id_solicitud"
                editable={false}
                creable={false}
                placeholderBusqueda="Buscar por nombre o correo…"
                filtros={[{ nombre: 'estado', etiqueta: 'Estado', valorInicial: 'pendiente', opciones: Object.entries(ESTADOS).map(([id, [nombre]]) => ({ id, nombre })) }]}
                columnas={[
                    {
                        titulo: 'Visitante',
                        render: (s) => (
                            <div>
                                <p className="font-medium">{s.visitante?.nombre}</p>
                                <p className="text-xs text-texto-suave">{s.visitante?.email}{s.visitante?.telefono ? ` · ${s.visitante.telefono}` : ''}</p>
                            </div>
                        ),
                    },
                    { titulo: 'Proformas', render: (s) => <span>{s.proformas} <span className="text-xs text-texto-suave">({moneda(s.total_cotizado)})</span></span> },
                    { titulo: 'Solicitada', render: (s) => fechaHora(s.fecha_solicitud) },
                    { titulo: 'Estado', render: (s) => <Insignia tono={ESTADOS[s.estado]?.[1]}>{ESTADOS[s.estado]?.[0] ?? s.estado}</Insignia> },
                    {
                        titulo: 'Revisión',
                        render: (s) =>
                            s.revisor ? (
                                <span className="text-xs">
                                    {s.revisor.nombre_completo} · {fechaHora(s.fecha_revision)}
                                    {s.motivo_rechazo && <span className="block text-peligro">Motivo: {s.motivo_rechazo}</span>}
                                </span>
                            ) : (
                                '—'
                            ),
                    },
                ]}
                acciones={(s, recargar) =>
                    s.estado === 'pendiente' &&
                    puede('solicitudes.editar') && (
                        <>
                            <button className="btn-icono hover:!text-exito" onClick={() => aprobar(s, recargar)} title="Aprobar y crear cuenta" aria-label={`Aprobar solicitud de ${s.visitante?.nombre}`}>
                                <UserCheck size={18} />
                            </button>
                            <button className="btn-icono hover:!text-peligro" onClick={() => setRechazo({ solicitud: s, recargar })} title="Rechazar" aria-label={`Rechazar solicitud de ${s.visitante?.nombre}`}>
                                <UserX size={18} />
                            </button>
                        </>
                    )
                }
            />
            {rechazo && <ModalRechazo {...rechazo} onCerrar={() => setRechazo(null)} />}
        </>
    );
}

function ModalRechazo({ solicitud, recargar, onCerrar }) {
    const { exito } = useAvisos();
    const f = useFormulario({ motivo: '' });

    const rechazar = async (e) => {
        e.preventDefault();
        const r = await f.enviar((v) => api.patch(`/solicitudes/${solicitud.id_solicitud}/rechazar`, v), {
            motivo: { requerido: true, min: 5, max: 300, etiqueta: 'motivo del rechazo' },
        });
        if (r) {
            exito(r.data.message);
            recargar();
            avisarCambioContadores();
            onCerrar();
        }
    };

    return (
        <Modal
            titulo={`Rechazar solicitud de ${solicitud.visitante?.nombre}`}
            abierto
            onCerrar={onCerrar}
            pie={
                <>
                    <button type="button" className="btn btn-secundario" onClick={onCerrar}>Cancelar</button>
                    <button type="submit" form="form-rechazo" className="btn btn-peligro" disabled={f.enviando}>Rechazar</button>
                </>
            }
        >
            <form id="form-rechazo" onSubmit={rechazar} noValidate className="space-y-3">
                {f.errorGeneral && <p className="text-sm text-peligro" role="alert">{f.errorGeneral}</p>}
                <Campo etiqueta="Motivo del rechazo" nombre="motivo" tipo="textarea" valor={f.valores.motivo} onChange={f.cambiar} error={f.errores.motivo} requerido placeholder="Ej.: no pudimos verificar sus datos de contacto." />
                <p className="text-xs text-texto-suave">Sus proformas se conservan. Podrá volver a pedir una cuenta en una próxima cotización.</p>
            </form>
        </Modal>
    );
}
