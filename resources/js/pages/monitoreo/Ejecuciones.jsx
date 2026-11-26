import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Loader2, PlayCircle, Radar } from 'lucide-react';
import PaginaCrud from '../../components/PaginaCrud';
import Insignia from '../../components/Insignia';
import Modal from '../../components/Modal';
import Campo from '../../components/Campo';
import { useAuth } from '../../context/AuthContext';
import { useAvisos } from '../../context/AvisosContext';
import { useFormulario } from '../../hooks/useFormulario';
import { useOpciones } from '../../hooks/useListado';
import { api } from '../../lib/api';
import { fechaHora } from '../../lib/formato';

const ESTADOS = {
    en_proceso: ['En proceso', 'aviso'],
    completada: ['Completada', 'exito'],
    error: ['Error', 'peligro'],
};

/** Ejecuciones del monitoreo de YouTube y ejecución manual (ejeScr del Proyecto 1). */
export default function Ejecuciones() {
    const { puede } = useAuth();
    const [abierto, setAbierto] = useState(false);
    const [version, setVersion] = useState(0); // remonta la lista tras una ejecución

    return (
        <>
            <PaginaCrud
                key={version}
                titulo="Ejecuciones de monitoreo"
                descripcion="Búsquedas en YouTube de cuentas que usan los términos de monitoreo y no son redes oficiales (CU2)."
                singular="ejecución"
                endpoint="/ejecuciones"
                privilegio="ejecuciones"
                idCampo="id_ejecucion"
                busqueda={false}
                botonesEncabezado={
                    puede('ejecuciones.crear') && (
                        <button className="btn btn-primario" onClick={() => setAbierto(true)}>
                            <PlayCircle size={16} /> Ejecutar monitoreo
                        </button>
                    )
                }
                filtros={[{ nombre: 'estado', etiqueta: 'Estado', opciones: Object.entries(ESTADOS).map(([id, [nombre]]) => ({ id, nombre })) }]}
                columnas={[
                    { titulo: 'ID', campo: 'id_ejecucion', className: 'w-14' },
                    { titulo: 'Plataforma', render: (e) => e.plataforma?.nombre },
                    { titulo: 'Inicio', render: (e) => fechaHora(e.fecha_inicio) },
                    { titulo: 'Fin', render: (e) => fechaHora(e.fecha_fin) },
                    { titulo: 'Detecciones', campo: 'detecciones_count', className: 'text-center' },
                    { titulo: 'Estado', render: (e) => <Insignia tono={ESTADOS[e.estado]?.[1]}>{ESTADOS[e.estado]?.[0] ?? e.estado}</Insignia> },
                    { titulo: 'Mensaje', render: (e) => <span className="text-xs text-peligro">{e.mensaje_error ?? ''}</span> },
                ]}
            />
            {abierto && (
                <ModalEjecutar
                    onCerrar={() => setAbierto(false)}
                    onTerminado={() => setVersion((v) => v + 1)}
                />
            )}
        </>
    );
}

function ModalEjecutar({ onCerrar, onTerminado }) {
    const { exito } = useAvisos();
    const { opciones: plataformas } = useOpciones('plataformas');
    const { opciones: terminos } = useOpciones('terminos');
    const youtube = plataformas.find((p) => p.nombre.toLowerCase() === 'youtube');
    const f = useFormulario({ id_plataforma: '', id_termino: '' });
    const [resultado, setResultado] = useState(null);
    const idPlataforma = f.valores.id_plataforma || (youtube ? String(youtube.id) : '');

    const ejecutar = async (e) => {
        e.preventDefault();
        const r = await f.enviar(
            (v) => api.post('/ejecuciones', { ...v, id_plataforma: idPlataforma }),
            { id_plataforma: { requerido: !idPlataforma, etiqueta: 'plataforma' } },
        );
        if (r) {
            setResultado(r.data.data);
            exito(r.data.message);
            onTerminado();
        }
    };

    return (
        <Modal
            titulo="Ejecutar monitoreo de páginas no autorizadas"
            abierto
            onCerrar={f.enviando ? () => {} : onCerrar}
            ancho="max-w-xl"
            pie={
                resultado ? (
                    <button className="btn btn-primario" onClick={onCerrar}>Cerrar</button>
                ) : (
                    <>
                        <button type="button" className="btn btn-secundario" onClick={onCerrar} disabled={f.enviando}>Cancelar</button>
                        <button type="submit" form="form-ejecutar" className="btn btn-primario" disabled={f.enviando}>
                            {f.enviando ? <><Loader2 size={16} className="animate-spin" /> Buscando en YouTube…</> : <><Radar size={16} /> Ejecutar ahora</>}
                        </button>
                    </>
                )
            }
        >
            {resultado ? (
                <Resultado r={resultado} />
            ) : (
                <form id="form-ejecutar" onSubmit={ejecutar} noValidate className="space-y-4">
                    {f.errorGeneral && <p className="text-sm text-peligro bg-peligro/10 rounded-tema p-3" role="alert">{f.errorGeneral}</p>}
                    <Campo
                        etiqueta="Plataforma"
                        nombre="id_plataforma"
                        tipo="select"
                        valor={idPlataforma}
                        onChange={f.cambiar}
                        error={f.errores.id_plataforma}
                        opciones={plataformas}
                        requerido
                        ayuda="Por ahora el monitoreo automático solo está disponible para YouTube (Facebook y TikTok bloquean las búsquedas automáticas)."
                        deshabilitado={f.enviando}
                    />
                    <Campo
                        etiqueta="Término"
                        nombre="id_termino"
                        tipo="select"
                        valor={f.valores.id_termino}
                        onChange={f.cambiar}
                        error={f.errores.id_termino}
                        opciones={terminos}
                        vacio={`Todos los términos activos (${terminos.length})`}
                        ayuda="Con todos los términos puede tardar hasta un par de minutos. Si es urgente, elija uno solo."
                        deshabilitado={f.enviando}
                    />
                    {f.enviando && (
                        <p className="text-sm text-texto-suave flex items-center gap-2" role="status">
                            <Loader2 size={16} className="animate-spin" aria-hidden /> Buscando canales y videos en YouTube y comparando con las redes oficiales… No cierre esta ventana.
                        </p>
                    )}
                </form>
            )}
        </Modal>
    );
}

function Resultado({ r }) {
    const fila = (k, v, tono) => (
        <div className="flex justify-between gap-3 py-1.5 border-b border-borde last:border-0">
            <dt className="text-texto-suave">{k}</dt>
            <dd className={`font-semibold ${tono ?? ''}`}>{v}</dd>
        </div>
    );
    return (
        <div className="space-y-4 text-sm">
            <p>
                Ejecución <strong>#{r.id_ejecucion}</strong> en {r.plataforma}:{' '}
                <Insignia tono={r.estado === 'completada' ? 'exito' : 'peligro'}>{r.estado === 'completada' ? 'Completada' : 'Con errores'}</Insignia>
            </p>
            <dl className="tarjeta px-4 py-2">
                {fila('Términos buscados', r.terminos.join(', '))}
                {fila('Cuentas analizadas', r.cuentas_analizadas)}
                {fila('Redes oficiales encontradas (se ignoran)', r.oficiales)}
                {fila('Páginas nuevas no autorizadas', r.nuevas, r.nuevas > 0 ? 'text-peligro' : 'text-exito')}
                {fila('Ya registradas (se agregó la detección)', r.ya_registradas)}
            </dl>
            {r.paginas_nuevas.length > 0 && (
                <div>
                    <h3 className="font-semibold mb-1">Páginas nuevas</h3>
                    <ul className="space-y-1 max-h-40 overflow-y-auto">
                        {r.paginas_nuevas.map((p) => (
                            <li key={p.url}>
                                <a href={p.url} target="_blank" rel="noreferrer" className="text-primario hover:underline">{p.nombre}</a>
                                <span className="text-texto-suave"> — {p.url}</span>
                            </li>
                        ))}
                    </ul>
                    <p className="text-xs text-texto-suave mt-2">Los administradores recibieron un aviso. Revíselas en <Link to="/paginas" className="underline">Páginas no autorizadas</Link>.</p>
                </div>
            )}
            {r.errores && <p className="text-peligro text-xs" role="alert">{r.errores}</p>}
        </div>
    );
}
