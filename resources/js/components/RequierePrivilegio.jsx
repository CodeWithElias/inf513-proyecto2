import { useAuth } from '../context/AuthContext';
import NoAutorizado from '../pages/NoAutorizado';

/** Protege una pantalla en el cliente. El servidor vuelve a verificar con el middleware "permiso". */
export default function RequierePrivilegio({ codigo, children }) {
    const { puede } = useAuth();
    if (codigo && !puede(codigo)) return <NoAutorizado />;
    return children;
}
