import { useState } from 'react';
import { CreditCard, Search } from 'lucide-react';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia from '../../components/Insignia';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { limpiarOpciones, useListado, useOpciones } from '../../hooks/useListado';
import { api, erroresCampos, mensajeError } from '../../lib/api';
import { fechaHora, moneda } from '../../lib/formato';

export default function Pagos() {
    const { puede } = useAuth();
    const { exito, error } = useAvisos();
    const listado = useListado('/pagos', { estado: '' });
    const { opciones: proformas } = useOpciones('proformas-pagables');
    const { opciones: metodos } = useOpciones('metodos-pago');

    const [abierto, setAbierto] = useState(false);
    const [form, setForm] = useState({ id_proforma: '', id_metodo: '' });
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const proformaSel = proformas.find((p) => String(p.id) === String(form.id_proforma));

    const abrirModal = () => {
        const pred = metodos.find((m) => m.predeterminado);
        setForm({ id_proforma: '', id_metodo: pred ? String(pred.id) : '' });
        setErrores({});
        setErrorGeneral(null);
        setAbierto(true);
    };

    const pagar = async (e) => {
        e.preventDefault();
        setErrorGeneral(null);
        const locales = {};
        if (!form.id_proforma) locales.id_proforma = 'Seleccione la proforma a pagar.';
        if (!form.id_metodo) locales.id_metodo = 'Seleccione un método de pago.';
        setErrores(locales);
        if (Object.keys(locales).length) return;

        setEnviando(true);
        try {
            const { data } = await api.post('/pagos', {
                id_proforma: Number(form.id_proforma),
                id_metodo: Number(form.id_metodo),
            });
            const tx = data.data;
            if (tx.estado === 'aprobada') {
                exito(data.message);
                setAbierto(false);
                limpiarOpciones();
                listado.recargar();
            } else {
                error(data.message);
                setErrorGeneral(data.message);
                listado.recargar();
            }
        } catch (err) {
            setErrores(erroresCampos(err));
            setErrorGeneral(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    const columnas = [
        { titulo: 'ID', campo: 'id_transaccion', className: 'w-14' },
        { titulo: 'Fecha', render: (t) => fechaHora(t.fecha) },
        {
            titulo: 'Concepto',
            render: (t) => (
                <div>
                    <p className="font-medium">{t.proforma?.nro_proforma ?? '—'}</p>
                    <p className="text-xs text-texto-suave">
                        {t.cuota ? `Cuota #${t.cuota.nro} (Plan #${t.cuota.id_plan})` : 'Pago único de proforma'}
                    </p>
                </div>
            ),
        },
        {
            titulo: 'Método',
            render: (t) => (t.metodo ? `${t.metodo.marca ?? t.metodo.tipo}${t.metodo.ultimos4 ? ` •••• ${t.metodo.ultimos4}` : ''}` : '—'),
        },
        { titulo: 'Monto', render: (t) => <span className="font-semibold">{moneda(t.monto)}</span> },
        {
            titulo: 'Estado / Autorización',
            render: (t) => (
                <div>
                    <Insignia tono={t.estado === 'aprobada' ? 'exito' : 'peligro'}>
                        {t.estado === 'aprobada' ? 'Aprobada' : 'Rechazada'}
                    </Insignia>
                    <p className="text-xs text-texto-suave mt-0.5">
                        {t.codigo_autorizacion ? <span className="font-mono">{t.codigo_autorizacion}</span> : t.mensaje}
                    </p>
                </div>
            ),
        },
    ];

    return (
        <div>
            <EncabezadoPagina titulo="Pagos y transacciones" descripcion="Historial de pagos procesados por la pasarela simulada y pago único de proformas aprobadas.">
                {puede('pagos.crear') && (
                    <button className="btn btn-primario" onClick={abrirModal}>
                        <CreditCard size={16} /> Pagar proforma
                    </button>
                )}
            </EncabezadoPagina>

            <div className="flex flex-col sm:flex-row gap-2 mb-4">
                <label className="relative flex-1">
                    <span className="sr-only">Buscar</span>
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" aria-hidden />
                    <input className="input !pl-9" placeholder="Buscar por código de autorización, proforma o mensaje…" value={listado.filtros.q} onChange={(e) => listado.setFiltro('q', e.target.value)} />
                </label>
                <label className="sm:w-48">
                    <span className="sr-only">Estado</span>
                    <select className="input" value={listado.filtros.estado} onChange={(e) => listado.setFiltro('estado', e.target.value)}>
                        <option value="">Estado: todos</option>
                        <option value="aprobada">Aprobadas</option>
                        <option value="rechazada">Rechazadas</option>
                    </select>
                </label>
            </div>

            {listado.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{listado.error}</p>}

            <Tabla columnas={columnas} filas={listado.filas} cargando={listado.cargando} meta={listado.meta} onPagina={listado.setPagina} idFila="id_transaccion" />

            <Modal
                titulo="Pago único de proforma"
                abierto={abierto}
                onCerrar={() => setAbierto(false)}
                ancho="max-w-lg"
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setAbierto(false)}>Cancelar</button>
                        <button type="submit" form="form-pago" className="btn btn-primario" disabled={enviando}>
                            {enviando ? 'Procesando pago…' : 'Confirmar pago'}
                        </button>
                    </>
                }
            >
                <form id="form-pago" onSubmit={pagar} noValidate className="space-y-4">
                    {errorGeneral && <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{errorGeneral}</p>}
                    <Campo
                        etiqueta="Proforma aprobada"
                        nombre="id_proforma"
                        tipo="select"
                        opciones={proformas}
                        valor={form.id_proforma}
                        onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))}
                        error={errores.id_proforma}
                        vacio={proformas.length ? 'Seleccione una proforma…' : 'No hay proformas aprobadas pendientes de pago'}
                        requerido
                    />
                    <Campo
                        etiqueta="Método de pago"
                        nombre="id_metodo"
                        tipo="select"
                        opciones={metodos}
                        valor={form.id_metodo}
                        onChange={(n, v) => setForm((f) => ({ ...f, [n]: v }))}
                        error={errores.id_metodo}
                        vacio={metodos.length ? 'Seleccione un método…' : 'Registre primero un método en Métodos de pago'}
                        requerido
                    />
                    {proformaSel && (
                        <div className="bg-superficie-2 rounded-tema p-3 flex justify-between items-center text-sm">
                            <span>Total a debitar</span>
                            <strong className="text-lg text-primario">{moneda(proformaSel.monto)}</strong>
                        </div>
                    )}
                </form>
            </Modal>
        </div>
    );
}

