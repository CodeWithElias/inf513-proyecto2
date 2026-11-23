import { Link } from 'react-router-dom';
import { MapPinOff } from 'lucide-react';

export default function NoEncontrado() {
    return (
        <div className="tarjeta max-w-lg mx-auto mt-10 p-8 text-center">
            <MapPinOff size={48} className="mx-auto text-aviso mb-3" aria-hidden />
            <h1 className="text-xl font-bold mb-2">Página no encontrada</h1>
            <p className="text-texto-suave mb-6">La dirección que buscó no existe o fue movida.</p>
            <Link to="/" className="btn btn-primario">Volver al inicio</Link>
        </div>
    );
}
