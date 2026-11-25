import { useState } from 'react';
import { CreditCard, Pencil, Plus, Power, Search, Star } from 'lucide-react';
import EncabezadoPagina from '../../components/EncabezadoPagina';
import Tabla from '../../components/Tabla';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import Insignia, { InsigniaActivo } from '../../components/Insignia';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { limpiarOpciones, useListado } from '../../hooks/useListado';
import { api, erroresCampos, mensajeError } from '../../lib/api';

const TIPOS = [
    { id: 'tarjeta', nombre: 'Tarjeta de crédito / débito' },
    { id: 'qr', nombre: 'QR Simple' },
    { id: 'billetera', nombre: 'Billetera digital' },
];
const ETIQUETA_TIPO = Object.fromEntries(TIPOS.map((t) => [t.id, t.nombre]));

function validarLuhn(numero) {
    const digitos = String(numero).replace(/\D+/g, '');
    if (digitos.length < 13 || digitos.length > 19) return false;
    let suma = 0;
    let duplicar = false;
    for (let i = digitos.length - 1; i >= 0; i--) {
        let d = Number(digitos[i]);
        if (duplicar) {
            d *= 2;
            if (d > 9) d -= 9;
        }
        suma += d;
        duplicar = !duplicar;
    }
    return suma % 10 === 0;
}

const INICIAL = {
    tipo: 'tarjeta',
    titular: '',
    marca: '',
    numero: '',
    cvv: '',
    mes_exp: '12',
    anio_exp: String(new Date().getFullYear() + 2),
    predeterminado: false,
};

export default function MetodosPago() {
    const { puede } = useAuth();
    const { exito, error, confirmar } = useAvisos();
    const listado = useListado('/metodos-pago', { tipo: '', activo: '' });
    const [modo, setModo] = useState(null);
    const [actual, setActual] = useState(null);
    const [form, setForm] = useState(INICIAL);
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const abrirCrear = () => {
        setActual(null);
        setForm(INICIAL);
        setErrores({});
        setErrorGeneral(null);
        setModo('crear');
    };

    const abrirEditar = (m) => {
        setActual(m);
        setForm({
            tipo: m.tipo,
            titular: m.titular,
            marca: m.marca ?? '',
            numero: '',
            cvv: '',
            mes_exp: m.mes_exp ? String(m.mes_exp) : '',
            anio_exp: m.anio_exp ? String(m.anio_exp) : '',
            predeterminado: Boolean(m.predeterminado),
        });
        setErrores({});
        setErrorGeneral(null);
        setModo('editar');
    };

    const cambiar = (n, v) => {
        setForm((f) => ({ ...f, [n]: v }));
        if (errores[n]) setErrores(({ [n]: _, ...r }) => r);
    };

    const guardar = async (e) => {
        e.preventDefault();
        setErrorGeneral(null);
        const locales = {};

        if (!form.titular.trim() || form.titular.trim().length < 3) {
            locales.titular = 'El nombre del titular debe tener al menos 3 caracteres.';
        }
        if (modo === 'crear' && form.tipo === 'tarjeta') {
            if (!validarLuhn(form.numero)) {
                locales.numero = 'Número de tarjeta inválido (no pasa verificación Luhn).';
            }
            if (!/^\d{3,4}$/.test(form.cvv.trim())) {
                locales.cvv = 'El CVV debe tener 3 o 4 dígitos.';
            }
        }
        if (form.tipo === 'tarjeta') {
            const mes = Number(form.mes_exp);
            const anio = Number(form.anio_exp);
            const hoy = new Date();
            if (!mes || mes < 1 || mes > 12) locales.mes_exp = 'Mes inválido (1 a 12).';
            if (!anio || anio < hoy.getFullYear() || (anio === hoy.getFullYear() && mes < hoy.getMonth() + 1)) {
                locales.anio_exp = 'La tarjeta se encuentra vencida.';
            }
        }

        setErrores(locales);
        if (Object.keys(locales).length) {
            setErrorGeneral('Revise los campos marcados en rojo.');
            return;
        }

        const payload = modo === 'crear'
            ? {
                tipo: form.tipo,
                titular: form.titular,
                marca: form.marca || null,
                ...(form.tipo === 'tarjeta' ? {
                    numero: form.numero,
                    cvv: form.cvv,
                    mes_exp: Number(form.mes_exp),
                    anio_exp: Number(form.anio_exp),
                } : {}),
                predeterminado: Boolean(form.predeterminado),
            }
            : {
                titular: form.titular,
                marca: form.marca || null,
                ...(form.tipo === 'tarjeta' ? {
                    mes_exp: Number(form.mes_exp),
                    anio_exp: Number(form.anio_exp),
                } : {}),
                predeterminado: Boolean(form.predeterminado),
            };

        setEnviando(true);
        try {
            const { data } = modo === 'crear'
                ? await api.post('/metodos-pago', payload)
                : await api.put(`/metodos-pago/${actual.id_metodo}`, payload);
            exito(data.message);
            setModo(null);
            limpiarOpciones();
            listado.recargar();
        } catch (err) {
            setErrores(erroresCampos(err));
            setErrorGeneral(mensajeError(err));
        } finally {
            setEnviando(false);
        }
    };

    const alternarEstado = async (m) => {
        const ok = await confirmar(
            m.activo ? '¿Dar de baja este método de pago?' : '¿Reactivar este método de pago?',
            { titulo: m.activo ? 'Dar de baja' : 'Reactivar', textoAceptar: m.activo ? 'Dar de baja' : 'Reactivar', peligro: m.activo },
        );
        if (!ok) return;
        try {
            const { data } = await api.patch(`/metodos-pago/${m.id_metodo}/estado`, { activo: !m.activo });
            exito(data.message);
            limpiarOpciones();
            listado.recargar();
        } catch (err) {
            error(mensajeError(err));
        }
    };

    const columnas = [
        {
            titulo: 'Método',
            render: (m) => (
                <div className="flex items-center gap-2">
                    <CreditCard size={18} className="text-primario shrink-0" aria-hidden />
                    <div>
                        <p className="font-medium flex items-center gap-1.5">
                            {m.marca ?? ETIQUETA_TIPO[m.tipo] ?? m.tipo}
                            {m.ultimos4 && <span className="font-mono text-xs text-texto-suave">•••• {m.ultimos4}</span>}
                            {m.predeterminado && <Insignia tono="primario"><Star size={11} className="mr-0.5 inline" /> Predeterminado</Insignia>}
                        </p>
                        <p className="text-xs text-texto-suave">{ETIQUETA_TIPO[m.tipo] ?? m.tipo}</p>
                    </div>
                </div>
            ),
        },
        { titulo: 'Titular', campo: 'titular' },
        {
            titulo: 'Vencimiento',
            render: (m) => (m.mes_exp && m.anio_exp ? `${String(m.mes_exp).padStart(2, '0')}/${m.anio_exp}` : '—'),
        },
        { titulo: 'Usuario', render: (m) => m.usuario?.nombre_completo ?? '—' },
        { titulo: 'Estado', render: (m) => <InsigniaActivo activo={m.activo} /> },
        ...(puede('metodos_pago.editar') || puede('metodos_pago.eliminar')
            ? [{
                titulo: <span className="sr-only">Acciones</span>,
                className: 'text-right whitespace-nowrap',
                render: (m) => (
                    <div className="flex justify-end gap-1">
                        {puede('metodos_pago.editar') && (
                            <button className="btn-icono" onClick={() => abrirEditar(m)} title="Editar" aria-label="Editar método">
                                <Pencil size={16} />
                            </button>
                        )}
                        {puede('metodos_pago.eliminar') && (
                            <button
                                className={`btn-icono ${m.activo ? 'hover:!text-peligro' : 'hover:!text-exito'}`}
                                onClick={() => alternarEstado(m)}
                                title={m.activo ? 'Dar de baja' : 'Reactivar'}
                                aria-label={m.activo ? 'Dar de baja método' : 'Reactivar método'}
                            >
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
                descripcion="Tarjetas (validadas con Luhn; solo se guardan los últimos 4 dígitos), QR Simple y billeteras digitales."
            >
                {puede('metodos_pago.crear') && (
                    <button className="btn btn-primario" onClick={abrirCrear}>
                        <Plus size={16} /> Nuevo método
                    </button>
                )}
            </EncabezadoPagina>

            <div className="flex flex-col sm:flex-row gap-2 mb-4">
                <label className="relative flex-1">
                    <span className="sr-only">Buscar</span>
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-texto-suave" aria-hidden />
                    <input className="input !pl-9" placeholder="Buscar por titular, marca o últimos 4 dígitos…" value={listado.filtros.q} onChange={(e) => listado.setFiltro('q', e.target.value)} />
                </label>
                <label className="sm:w-48">
                    <span className="sr-only">Tipo</span>
                    <select className="input" value={listado.filtros.tipo} onChange={(e) => listado.setFiltro('tipo', e.target.value)}>
                        <option value="">Tipo: todos</option>
                        {TIPOS.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                    </select>
                </label>
            </div>

            {listado.error && <p className="tarjeta p-3 mb-4 text-peligro text-sm" role="alert">{listado.error}</p>}

            <Tabla columnas={columnas} filas={listado.filas} cargando={listado.cargando} meta={listado.meta} onPagina={listado.setPagina} idFila="id_metodo" />

            <Modal
                titulo={modo === 'crear' ? 'Registrar método de pago' : 'Editar método de pago'}
                abierto={modo !== null}
                onCerrar={() => setModo(null)}
                ancho="max-w-lg"
                pie={
                    <>
                        <button type="button" className="btn btn-secundario" onClick={() => setModo(null)}>Cancelar</button>
                        <button type="submit" form="form-metodo" className="btn btn-primario" disabled={enviando}>
                            {enviando ? 'Guardando…' : 'Guardar'}
                        </button>
                    </>
                }
            >
                <form id="form-metodo" onSubmit={guardar} noValidate className="space-y-4">
                    {errorGeneral && <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{errorGeneral}</p>}

                    {modo === 'crear' && (
                        <Campo etiqueta="Tipo de método" nombre="tipo" tipo="select" opciones={TIPOS} valor={form.tipo} onChange={cambiar} requerido />
                    )}

                    <Campo etiqueta="Nombre del titular" nombre="titular" valor={form.titular} onChange={cambiar} error={errores.titular} requerido />

                    {modo === 'crear' && form.tipo === 'tarjeta' && (
                        <>
                            <Campo
                                etiqueta="Número de tarjeta"
                                nombre="numero"
                                placeholder="Ej. 4111 1111 1111 4242"
                                valor={form.numero}
                                onChange={cambiar}
                                error={errores.numero}
                                ayuda="Por seguridad nunca se guarda el número completo ni el CVV. Pruebas: terminada en 0002 (fondos insuficientes) o 0069 (vencida)."
                                requerido
                            />
                            <div className="grid grid-cols-3 gap-3">
                                <Campo etiqueta="Mes exp." nombre="mes_exp" tipo="number" valor={form.mes_exp} onChange={cambiar} error={errores.mes_exp} min={1} max={12} requerido />
                                <Campo etiqueta="Año exp." nombre="anio_exp" tipo="number" valor={form.anio_exp} onChange={cambiar} error={errores.anio_exp} min={2024} max={2099} requerido />
                                <Campo etiqueta="CVV" nombre="cvv" tipo="password" placeholder="123" valor={form.cvv} onChange={cambiar} error={errores.cvv} maxLength={4} requerido />
                            </div>
                        </>
                    )}

                    {modo === 'editar' && form.tipo === 'tarjeta' && (
                        <div className="grid grid-cols-2 gap-3">
                            <Campo etiqueta="Mes exp." nombre="mes_exp" tipo="number" valor={form.mes_exp} onChange={cambiar} error={errores.mes_exp} min={1} max={12} requerido />
                            <Campo etiqueta="Año exp." nombre="anio_exp" tipo="number" valor={form.anio_exp} onChange={cambiar} error={errores.anio_exp} min={2024} max={2099} requerido />
                        </div>
                    )}

                    {form.tipo !== 'tarjeta' && (
                        <Campo etiqueta="Proveedor / Marca" nombre="marca" placeholder={form.tipo === 'qr' ? 'QR Simple' : 'Tigo Money / Yape'} valor={form.marca} onChange={cambiar} />
                    )}

                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                            type="checkbox"
                            checked={form.predeterminado}
                            onChange={(e) => cambiar('predeterminado', e.target.checked)}
                        />
                        Establecer como método de pago predeterminado
                    </label>
                </form>
            </Modal>
        </div>
    );
}

