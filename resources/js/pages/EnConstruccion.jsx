import { Hammer } from 'lucide-react';
import { useLocation } from 'react-router-dom';

/** Marcador para los módulos de Persona B y C mientras se implementan. */
export default function EnConstruccion() {
    const { pathname } = useLocation();
    return (
        <div className="tarjeta max-w-lg mx-auto mt-10 p-8 text-center">
            <Hammer size={44} className="mx-auto text-primario mb-3" aria-hidden />
            <h1 className="text-xl font-bold mb-2">Módulo en construcción</h1>
            <p className="text-texto-suave">La pantalla <code>{pathname}</code> estará disponible pronto.</p>
        </div>
    );
}
