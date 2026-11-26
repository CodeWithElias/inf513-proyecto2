import { useState } from 'react';
import { CreditCard, Lock, Pencil, Plus, Power, Search, Star } from 'lucide-react';
import { Elements, CardElement, useElements, useStripe } from '@stripe/react-stripe-js';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia, { InsigniaActivo } from '../../components/Insignia';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { limpiarOpciones, useListado } from '../../hooks/useListado';
import { api, erroresCampos, mensajeError } from '../../lib/api';
import { validar } from '../../lib/validar';
import { estiloTarjeta, obtenerStripe, stripeConfigurado } from '../../lib/stripe';

const REGLAS_TITULAR = { titular: { requerido: true, min: 3, max: 150, etiqueta: 'titular de la tarjeta' } };

/**
 * Métodos de pago: tarjetas registradas con Stripe (modo de prueba).
 * El número y el CVV se escriben en el campo seguro de Stripe: nunca pasan por nuestro servidor.
 */
export default function MetodosPago() {
    const { puede } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const listado = useListado('/metodos-pago', { activo: '' });
    const [modo, setModo] = useState(null); // 'crear' | 'editar'
    const [actual, setActual] = useState(null);

    const alternarEstado = async (m) => {
        const ok = await confirmar('¿Dar de baja esta tarjeta? Se desvincula de Stripe; para volver a usarla habrá que registrarla de nuevo.', {
            titulo: 'Dar de baja', textoAceptar: 'Dar de baja', peligro: true,
        });
        if (!ok) return;
        try {
            const { data } = await api.patch(`/metodos-pago/${m.id_metodo}/estado`, { activo: false });
            exito(data.message);
            limpiarOpciones();
            listado.recargar();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const alGuardar = (mensaje) => {
        exito(mensaje);
        setModo(null);
        limpiarOpciones();
        listado.recargar();
    };

    const columnas = [
        {
            titulo: 'Tarjeta',
            render: (m) => (
                <div className="flex items-center gap-2">
                    <CreditCard size={18} className="text-primario shrink-0" aria-hidden />
                    <div>
                        <p className="font-medium flex flex-wrap items-center gap-1.5">
                            {m.marca ?? m.tipo}
                            {m.ultimos4 && <span className="font-mono text-xs text-texto-suave">•••• {m.ultimos4}</span>}
                            {m.predeterminado && <Insignia tono="primario"><Star size={11} className="mr-0.5 inline" /> Predeterminada</Insignia>}
                        </p>
                        <p className="text-xs text-texto-suave">
                            {m.con_stripe ? 'Registrada en Stripe' : 'Registrada antes de Stripe: no se puede usar para pagar'}
                        </p>
                    </div>
                </div>
            ),
        },
        { titulo: 'Titular', campo: 'titular' },
        { titulo: 'Vencimiento', render: (m) => (m.mes_exp && m.anio_exp ? `${String(m.mes_exp).padStart(2, '0')}/${m.anio_exp}` : '—') },
        { titulo: 'Usuario', render: (m) => m.usuario?.nombre_completo ?? '—' },
        { titulo: 'Estado', render: (m) => <InsigniaActivo activo={m.activo} /> },
        ...(puede('metodos_pago.editar') || puede('metodos_pago.eliminar')
            ? [{
                titulo: <span className="sr-only">Acciones</span>,
                className: 'text-right whitespace-nowrap',
                render: (m) => (
                    <div className="flex justify-end gap-1">
                        {puede('metodos_pago.editar') && m.activo && (
                            <button className="btn-icono" onClick={() => { setActual(m); setModo('editar'); }} title="Editar" aria-label="Editar tarjeta">
                                <Pencil size={16} />
                            </button>
                        )}
                        {puede('metodos_pago.eliminar') && m.activo && (
                            <button className="btn-icono hover:!text-peligro" onClick={() => alternarEstado(m)} title="Dar de baja" aria-label="Dar de baja tarjeta">
                                <Power size={16} />
                            </button>
                        )}
                    </div>
                ),
            }]
            : []),
    ];

    return (
        <div>
            <EncabezadoPagina
                titulo="Métodos de pago"
                descripcion="Tarjetas de crédito o débito procesadas por Stripe (modo de prueba). El número y el CVV nunca se guardan en el sistema."
            >
                {puede('metodos_pago.crear') && (
                    <button className="btn btn-primario" onClick={() => { setActual(null); setModo('crear'); }} disabled={!stripeConfigurado}>
                        <Plus size={16} /> Agregar tarjeta
                    </button>
                )}
            </EncabezadoPagina>

            {!stripeConfigurado && (
                <p className="tarjeta p-3 mb-4 text-sm text-aviso" role="alert">
                    Los pagos en línea aún no están configurados (faltan las claves de Stripe). Avise al administrador.
                </p>
            )}

            <div className="flex flex-col sm:flex-row gap-2 mb-4">
                <label className="relative flex-1">
                    <span className="sr-only">Buscar</span>
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" aria-hidden />
                    <input className="input !pl-9" placeholder="Buscar por titular, marca o últimos 4 dígitos…" value={listado.filtros.q} onChange={(e) => listado.setFiltro('q', e.target.value)} />
                </label>
                <label className="sm:w-48">
                    <span className="sr-only">Estado</span>
                    <select className="input" value={listado.filtros.activo} onChange={(e) => listado.setFiltro('activo', e.target.value)}>
                        <option value="">Estado: todas</option>
                        <option value="true">Activas</option>
                        <option value="false">Dadas de baja</option>
                    </select>
                </label>
            </div>

            {listado.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{listado.error}</p>}

            <Tabla columnas={columnas} filas={listado.filas} cargando={listado.cargando} meta={listado.meta} onPagina={listado.setPagina} idFila="id_metodo" />

            {modo === 'crear' && (
                <Modal titulo="Agregar tarjeta" abierto onCerrar={() => setModo(null)} ancho="max-w-lg">
                    <Elements stripe={obtenerStripe()} options={{ locale: 'es' }}>
                        <FormularioTarjeta alCancelar={() => setModo(null)} alGuardar={alGuardar} />
                    </Elements>
                </Modal>
            )}
            {modo === 'editar' && actual && (
                <EditarTarjeta metodo={actual} alCancelar={() => setModo(null)} alGuardar={alGuardar} />
            )}
        </div>
    );
}

/** Registro: 1) SetupIntent en nuestro servidor, 2) Stripe guarda la tarjeta, 3) se registra el id pm_… */
function FormularioTarjeta({ alCancelar, alGuardar }) {
    const stripe = useStripe();
    const elements = useElements();
    const [titular, setTitular] = useState('');
    const [predeterminado, setPredeterminado] = useState(false);
    const [errores, setErrores] = useState({});
    const [errorTarjeta, setErrorTarjeta] = useState(null);
    const [completa, setCompleta] = useState(false);
    const [enviando, setEnviando] = useState(false);

    const guardar = async (e) => {
        e.preventDefault();
        const loc = validar({ titular }, REGLAS_TITULAR);
        setErrores(loc);
        if (!completa) setErrorTarjeta((x) => x ?? 'Complete los datos de la tarjeta.');
        if (Object.keys(loc).length || !completa || !stripe || !elements) return;

        setEnviando(true);
        try {
            const { data } = await api.post('/metodos-pago/preparar');
            const { error, setupIntent } = await stripe.confirmCardSetup(data.data.client_secret, {
                payment_method: { card: elements.getElement(CardElement), billing_details: { name: titular.trim() } },
            });
            if (error) {
                setErrorTarjeta(error.message);
                return;
            }
            const r = await api.post('/metodos-pago', { payment_method: setupIntent.payment_method, titular: titular.trim(), predeterminado });
            alGuardar(r.data.message);
        } catch (err) {
            setErrores(erroresCampos(err));
            setErrorTarjeta(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <form onSubmit={guardar} noValidate className="space-y-4">
            <Campo etiqueta="Nombre del titular" nombre="titular" valor={titular} onChange={(_, v) => setTitular(v)} error={errores.titular} requerido autoComplete="cc-name" />
            <div>
                <span className="etiqueta" id="etq-tarjeta">Datos de la tarjeta <span className="text-peligro" aria-hidden>*</span></span>
                <div className={`input !py-3 ${errorTarjeta ? 'input-error' : ''}`} aria-labelledby="etq-tarjeta">
                    <CardElement
                        options={{ style: estiloTarjeta(), hidePostalCode: true, disableLink: true }}
                        onChange={(ev) => { setCompleta(ev.complete); setErrorTarjeta(ev.error?.message ?? null); }}
                    />
                </div>
                {errorTarjeta ? (
                    <p className="texto-error" role="alert">{errorTarjeta}</p>
                ) : (
                    <p className="text-xs text-texto-suave mt-1 flex items-start gap-1">
                        <Lock size={12} className="mt-0.5 shrink-0" aria-hidden />
                        Campo seguro de Stripe: el número y el CVV no pasan por nuestro servidor. Pruebas: 4242 4242 4242 4242 (aprobada),
                        4000 0000 0000 0341 (el banco rechaza al pagar), 4000 0027 6000 3184 (pide verificación 3D Secure). Cualquier fecha futura y CVC.
                    </p>
                )}
            </div>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" checked={predeterminado} onChange={(e) => setPredeterminado(e.target.checked)} />
                Usar como tarjeta predeterminada
            </label>
            <div className="flex justify-end gap-2 pt-2 border-t border-borde">
                <button type="button" className="btn btn-secundario" onClick={alCancelar}>Cancelar</button>
                <button type="submit" className="btn btn-primario" disabled={enviando || !stripe}>
                    {enviando ? 'Registrando…' : 'Guardar tarjeta'}
                </button>
            </div>
        </form>
    );
}

/** Edición: solo titular y predeterminada (marca, últimos 4 y vencimiento los informa Stripe). */
function EditarTarjeta({ metodo, alCancelar, alGuardar }) {
    const [titular, setTitular] = useState(metodo.titular);
    const [predeterminado, setPredeterminado] = useState(Boolean(metodo.predeterminado));
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const guardar = async (e) => {
        e.preventDefault();
        const loc = validar({ titular }, REGLAS_TITULAR);
        setErrores(loc);
        if (Object.keys(loc).length) return;
        setEnviando(true);
        try {
            const { data } = await api.put(`/metodos-pago/${metodo.id_metodo}`, { titular: titular.trim(), predeterminado });
            alGuardar(data.message);
        } catch (err) {
            setErrores(erroresCampos(err));
            setErrorGeneral(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <Modal
            titulo={`Editar ${metodo.marca ?? 'tarjeta'} •••• ${metodo.ultimos4 ?? ''}`}
            abierto
            onCerrar={alCancelar}
            pie={
                <>
                    <button type="button" className="btn btn-secundario" onClick={alCancelar}>Cancelar</button>
                    <button type="submit" form="form-editar-tarjeta" className="btn btn-primario" disabled={enviando}>{enviando ? 'Guardando…' : 'Guardar'}</button>
                </>
            }
        >
            <form id="form-editar-tarjeta" onSubmit={guardar} noValidate className="space-y-4">
                {errorGeneral && <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{errorGeneral}</p>}
                <Campo etiqueta="Nombre del titular" nombre="titular" valor={titular} onChange={(_, v) => setTitular(v)} error={errores.titular} requerido />
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" checked={predeterminado} onChange={(e) => setPredeterminado(e.target.checked)} />
                    Usar como tarjeta predeterminada
                </label>
            </form>
        </Modal>
    );
}
