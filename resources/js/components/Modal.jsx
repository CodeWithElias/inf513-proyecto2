import { useEffect, useId } from 'react';
import { X } from 'lucide-react';

/** Ventana modal accesible (Esc para cerrar, aria-modal). */
export default function Modal({ titulo, abierto, onCerrar, children, pie, ancho = 'max-w-lg' }) {
    const id = useId();

    useEffect(() => {
        if (!abierto) return;
        const esc = (e) => e.key === 'Escape' && onCerrar();
        document.addEventListener('keydown', esc);
        return () => document.removeEventListener('keydown', esc);
    }, [abierto, onCerrar]);

    if (!abierto) return null;

    return (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-start sm:items-center justify-center p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby={id}>
            <div className={`tarjeta w-full ${ancho} my-8`}>
                <div className="flex items-center justify-between px-5 py-4 border-b border-borde">
                    <h2 id={id} className="text-lg font-semibold">{titulo}</h2>
                    <button className="btn-icono" onClick={onCerrar} aria-label="Cerrar">
                        <X size={18} />
                    </button>
                </div>
                <div className="p-5">{children}</div>
                {pie && <div className="px-5 py-4 border-t border-borde flex justify-end gap-2">{pie}</div>}
            </div>
        </div>
    );
}
