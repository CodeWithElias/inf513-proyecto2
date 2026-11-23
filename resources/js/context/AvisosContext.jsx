import { createContext, useCallback, useContext, useRef, useState } from 'react';
import { CheckCircle2, AlertTriangle, X } from 'lucide-react';

/**
 * Avisos flotantes y confirmaciones.
 *   const { exito, error, confirmar } = useAvisos();
 *   exito('Usuario registrado.');
 *   if (await confirmar('¿Dar de baja al usuario?')) { ... }
 */
const AvisosContext = createContext(null);

export function AvisosProvider({ children }) {
    const [avisos, setAvisos] = useState([]);
    const [dialogo, setDialogo] = useState(null);
    const contador = useRef(0);

    const quitar = useCallback((id) => setAvisos((a) => a.filter((x) => x.id !== id)), []);
    const agregar = useCallback(
        (tipo, texto) => {
            const id = ++contador.current;
            setAvisos((a) => [...a, { id, tipo, texto }]);
            setTimeout(() => quitar(id), tipo === 'error' ? 7000 : 4000);
        },
        [quitar],
    );

    const confirmar = useCallback(
        (mensaje, { titulo = 'Confirmar acción', textoAceptar = 'Sí, continuar', peligro = false } = {}) =>
            new Promise((resolver) => setDialogo({ mensaje, titulo, textoAceptar, peligro, resolver })),
        [],
    );

    const cerrarDialogo = (resultado) => {
        dialogo?.resolver(resultado);
        setDialogo(null);
    };

    const valor = {
        exito: (t) => agregar('exito', t),
        error: (t) => agregar('error', t),
        confirmar,
    };

    return (
        <AvisosContext.Provider value={valor}>
            {children}

            <div className="fixed top-4 right-4 z-[60] flex flex-col gap-2 w-[min(24rem,calc(100vw-2rem))]" aria-live="polite">
                {avisos.map((a) => (
                    <div
                        key={a.id}
                        role={a.tipo === 'error' ? 'alert' : 'status'}
                        className={`tarjeta flex items-start gap-3 p-3 border-l-4 ${a.tipo === 'error' ? 'border-l-peligro' : 'border-l-exito'}`}
                    >
                        {a.tipo === 'error' ? (
                            <AlertTriangle className="text-peligro shrink-0" size={20} aria-hidden />
                        ) : (
                            <CheckCircle2 className="text-exito shrink-0" size={20} aria-hidden />
                        )}
                        <p className="text-sm flex-1">{a.texto}</p>
                        <button className="btn-icono" onClick={() => quitar(a.id)} aria-label="Cerrar aviso">
                            <X size={16} />
                        </button>
                    </div>
                ))}
            </div>

            {dialogo && (
                <div className="fixed inset-0 z-[70] bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="confirmar-titulo">
                    <div className="tarjeta w-full max-w-md p-6">
                        <h2 id="confirmar-titulo" className="text-lg font-semibold mb-2">{dialogo.titulo}</h2>
                        <p className="text-texto-suave mb-6">{dialogo.mensaje}</p>
                        <div className="flex justify-end gap-2">
                            <button className="btn btn-secundario" onClick={() => cerrarDialogo(false)} autoFocus>
                                Cancelar
                            </button>
                            <button className={`btn ${dialogo.peligro ? 'btn-peligro' : 'btn-primario'}`} onClick={() => cerrarDialogo(true)}>
                                {dialogo.textoAceptar}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AvisosContext.Provider>
    );
}

export const useAvisos = () => useContext(AvisosContext);
