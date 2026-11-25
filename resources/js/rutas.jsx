/**
 * Tabla de pantallas de la SPA: ruta -> componente + privilegio requerido.
 * Las rutas coinciden con menu_item.ruta de la BD. Cada integrante agrega aquí
 * sus pantallas reemplazando EnConstruccion por su componente.
 */
import Inicio from './pages/Inicio';
import Perfil from './pages/Perfil';
import EnConstruccion from './pages/EnConstruccion';
// Persona A
import Usuarios from './pages/seguridad/Usuarios';
import Roles from './pages/seguridad/Roles';
import RolPermisos from './pages/seguridad/RolPermisos';
import Bitacora from './pages/seguridad/Bitacora';
import Unidades from './pages/organizacion/Unidades';
import Representantes from './pages/organizacion/Representantes';
import Redes from './pages/redes/Redes';
import Plataformas from './pages/redes/Plataformas';
import Canales from './pages/redes/Canales';
import Terminos from './pages/monitoreo/Terminos';
import Paginas from './pages/monitoreo/Paginas';
import Ejecuciones from './pages/monitoreo/Ejecuciones';
// Persona B
import Empresas from './pages/comercial/Empresas';
import Visitantes from './pages/comercial/Visitantes';
import Servicios from './pages/comercial/Servicios';
import Tarifas from './pages/comercial/Tarifas';
import Paquetes from './pages/comercial/Paquetes';
import Proformas from './pages/comercial/Proformas';
import Campanias from './pages/comercial/Campanias';
import MetodosPago from './pages/pagos/MetodosPago';
import Pagos from './pages/pagos/Pagos';
import PlanesPago from './pages/pagos/PlanesPago';
// Persona C
import Materiales from './pages/operacion/Materiales';
import Espacios from './pages/operacion/Espacios';
import Pautas from './pages/operacion/Pautas';
import Notificaciones from './pages/operacion/Notificaciones';

export const RUTAS = [
    { ruta: '/', componente: Inicio },
    { ruta: '/perfil', componente: Perfil },

    // ---------------- Persona A: seguridad, organización, redes, monitoreo ----------------
    { ruta: '/usuarios', componente: Usuarios, privilegio: 'usuarios.ver' },
    { ruta: '/roles', componente: Roles, privilegio: 'roles.ver' },
    { ruta: '/roles/:id/permisos', componente: RolPermisos, privilegio: 'roles.ver' },
    { ruta: '/bitacora', componente: Bitacora, privilegio: 'bitacora.ver' },
    { ruta: '/unidades', componente: Unidades, privilegio: 'unidades.ver' },
    { ruta: '/representantes', componente: Representantes, privilegio: 'representantes.ver' },
    { ruta: '/redes', componente: Redes, privilegio: 'redes.ver' },
    { ruta: '/plataformas', componente: Plataformas, privilegio: 'plataformas.ver' },
    { ruta: '/canales', componente: Canales, privilegio: 'canales.ver' },
    { ruta: '/terminos', componente: Terminos, privilegio: 'terminos.ver' },
    { ruta: '/paginas', componente: Paginas, privilegio: 'paginas.ver' },
    { ruta: '/ejecuciones', componente: Ejecuciones, privilegio: 'ejecuciones.ver' },

    // ---------------- Persona B: comercial y pagos ----------------
    { ruta: '/empresas', componente: Empresas, privilegio: 'empresas.ver' },
    { ruta: '/visitantes', componente: Visitantes, privilegio: 'visitantes.ver' },
    { ruta: '/servicios', componente: Servicios, privilegio: 'servicios.ver' },
    { ruta: '/tarifas', componente: Tarifas, privilegio: 'tarifas.ver' },
    { ruta: '/paquetes', componente: Paquetes, privilegio: 'paquetes.ver' },
    { ruta: '/proformas', componente: Proformas, privilegio: 'proformas.ver' },
    { ruta: '/campanias', componente: Campanias, privilegio: 'campanias.ver' },
    { ruta: '/metodos-pago', componente: MetodosPago, privilegio: 'metodos_pago.ver' },
    { ruta: '/pagos', componente: Pagos, privilegio: 'pagos.ver' },
    { ruta: '/planes-pago', componente: PlanesPago, privilegio: 'planes_pago.ver' },

    // ---------------- Persona C: operación y estadísticas ----------------
    { ruta: '/dashboard', componente: EnConstruccion, privilegio: 'dashboard.ver' },
    { ruta: '/materiales', componente: Materiales, privilegio: 'materiales.ver' },
    { ruta: '/espacios', componente: Espacios, privilegio: 'espacios.ver' },
    { ruta: '/pautas', componente: Pautas, privilegio: 'pautas.ver' },
    { ruta: '/notificaciones', componente: Notificaciones, privilegio: 'notificaciones.ver' },
    { ruta: '/metricas', componente: EnConstruccion, privilegio: 'metricas.ver' },
    { ruta: '/reportes', componente: EnConstruccion, privilegio: 'reportes.ver' },
];
