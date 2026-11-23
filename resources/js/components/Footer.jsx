import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { Eye } from 'lucide-react';
import { api } from '../lib/api';

/** Pie de página con el contador de visitas persistente de la página actual. */
export default function Footer() {
    const { pathname } = useLocation();
    const [total, setTotal] = useState(null);

    useEffect(() => {
        let vigente = true;
        api.post('/visitas', { ruta: pathname })
            .then(({ data }) => vigente && setTotal(data.data.total))
            .catch(() => vigente && setTotal(null));
        return () => {
            vigente = false;
        };
    }, [pathname]);

    return (
        <footer className="border-t border-borde bg-superficie px-4 sm:px-6 py-3 text-xs text-texto-suave flex flex-col sm:flex-row gap-1 sm:items-center sm:justify-between">
            <p>© {new Date().getFullYear()} Canal 11 · UAGRM — Grupo 13 SA · INF513 Tecnología Web</p>
            <p className="flex items-center gap-1.5" aria-live="polite">
                <Eye size={14} aria-hidden />
                {total === null ? 'Contando visitas…' : `Esta página ha sido visitada ${total.toLocaleString('es-BO')} ${total === 1 ? 'vez' : 'veces'}`}
            </p>
        </footer>
    );
}
