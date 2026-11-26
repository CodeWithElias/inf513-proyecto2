import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Check, ChevronRight, CircleDot } from 'lucide-react';
import { api } from '../lib/api';
import { moneda } from '../lib/formato';

const TONOS = {
    aviso: 'border-l-aviso',
    peligro: 'border-l-peligro',
    exito: 'border-l-exito',
    neutro: 'border-l-borde',
};

/**
 * Inicio: avance de cada contratación del cliente (proforma → pago → material → revisión → pautas → emisión)
 * y tareas pendientes del personal. Datos de GET /flujo.
 */
export default function FlujoInicio() {
    const [datos, setDatos] = useState(null);

    useEffect(() => {
        let vigente = true;
        api.get('/flujo')
            .then(({ data }) => vigente && setDatos(data.data))
            .catch(() => vigente && setDatos({ contrataciones: [], pendientes: [] }));
        return () => {
            vigente = false;
        };
    }, []);

    if (!datos) return null;
    const { contrataciones = [], pendientes = [] } = datos;

    return (
        <>
            {pendientes.length > 0 && (
                <section className="mb-6" aria-labelledby="tit-pendientes">
                    <h2 id="tit-pendientes" className="text-lg font-semibold mb-3">Pendientes de atención</h2>
                    <div className="grid gap-3 grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
                        {pendientes.map((p) => (
                            <Link key={p.titulo} to={p.ruta} className={`tarjeta p-4 border-l-4 ${TONOS[p.tono] ?? TONOS.neutro} hover:border-primario transition-colors flex items-start gap-3`}>
                                <span className="text-2xl font-bold leading-none min-w-8 text-center">{p.cantidad}</span>
                                <span className="flex-1 min-w-0">
                                    <span className="block font-semibold">{p.titulo}</span>
                                    <span className="block text-xs text-texto-suave mt-0.5">{p.detalle}</span>
                                </span>
                                <ChevronRight size={18} className="text-texto-suave shrink-0 self-center" aria-hidden />
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            {contrataciones.length > 0 && (
                <section className="mb-6" aria-labelledby="tit-contrataciones">
                    <h2 id="tit-contrataciones" className="text-lg font-semibold mb-1">Mis contrataciones</h2>
                    <p className="text-sm text-texto-suave mb-3">Cada proforma pasa por estos pasos hasta que su anuncio sale al aire.</p>
                    <div className="space-y-3">
                        {contrataciones.map((c) => (
                            <article key={c.id_proforma} className="tarjeta p-4">
                                <div className="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                                    <p className="font-semibold">
                                        {c.nro_proforma} <span className="text-texto-suave font-normal">· {moneda(c.total)}</span>
                                    </p>
                                    {c.campania && <p className="text-xs text-texto-suave">Campaña: {c.campania.nombre}</p>}
                                </div>

                                <ol className="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-3" aria-label={`Avance de ${c.nro_proforma}`}>
                                    {c.pasos.map((paso, i) => {
                                        const actual = !paso.hecho && c.pasos.slice(0, i).every((x) => x.hecho);
                                        return (
                                            <li key={paso.clave} className="flex flex-col items-center text-center gap-1" title={paso.detalle ?? undefined}>
                                                <span
                                                    className={`size-7 rounded-full flex items-center justify-center text-xs font-bold ${
                                                        paso.hecho ? 'bg-exito text-white' : actual ? 'bg-primario text-white' : 'bg-superficie-2 text-texto-suave'
                                                    }`}
                                                    aria-hidden
                                                >
                                                    {paso.hecho ? <Check size={14} /> : actual ? <CircleDot size={14} /> : i + 1}
                                                </span>
                                                <span className={`text-[11px] leading-tight ${actual ? 'font-semibold' : 'text-texto-suave'}`}>
                                                    {paso.titulo}
                                                    <span className="sr-only">{paso.hecho ? ' (hecho)' : actual ? ' (paso actual)' : ' (pendiente)'}</span>
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ol>

                                <div className={`flex flex-wrap items-center justify-between gap-2 rounded-tema p-2.5 text-sm ${c.le_toca ? 'bg-primario-suave' : 'bg-superficie-2'}`}>
                                    <p>
                                        <strong>{c.le_toca ? 'Le toca a usted: ' : 'En manos de Canal 11: '}</strong>
                                        {c.siguiente}
                                    </p>
                                    <Link to={c.ruta} className={`btn ${c.le_toca ? 'btn-primario' : 'btn-secundario'} !py-1 !px-3 text-xs shrink-0`}>
                                        {c.le_toca ? 'Hacerlo ahora' : 'Ver'}
                                    </Link>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
