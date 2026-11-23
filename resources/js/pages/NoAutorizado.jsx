import { Link } from 'react-router-dom';
import { ShieldAlert } from 'lucide-react';

export default function NoAutorizado() {
    return (
        <div className="tarjeta max-w-lg mx-auto mt-10 p-8 text-center">
            <ShieldAlert size={48} className="mx-auto text-peligro mb-3" aria-hidden />
            <h1 className="text-xl font-bold mb-2">No tiene permiso para ver esta página</h1>
            <p className="text-texto-suave mb-6">Su rol no tiene acceso a este módulo. Si lo necesita, solicítelo al administrador del sistema.</p>
            <Link to="/" className="btn btn-primario">Volver al inicio</Link>
        </div>
    );
}
