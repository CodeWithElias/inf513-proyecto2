import PaginaCrud, { FILTRO_ESTADO } from '../../components/PaginaCrud';
import { InsigniaActivo } from '../../components/Insignia';
import { fecha } from '../../lib/formato';

const NIT = { patron: /^[0-9A-Za-z\-]{4,30}$/, mensajePatron: 'El NIT solo puede tener números, letras y guiones (4 a 30 caracteres).' };
const TELEFONO = { patron: /^[0-9+\- ]{6,30}$/, mensajePatron: 'El teléfono solo puede tener números, espacios, + y - (6 a 30 caracteres).' };

export default function Empresas() {
    return (
        <PaginaCrud
            titulo="Empresas anunciantes"
            descripcion="Empresas registradas que contratan espacios y servicios publicitarios en Canal 11."
            singular="empresa"
            endpoint="/empresas"
            privilegio="empresas"
            idCampo="id_empresa"
            placeholderBusqueda="Buscar por razón social, NIT o nombre comercial…"
            filtros={[FILTRO_ESTADO]}
            columnas={[
                { titulo: 'ID', campo: 'id_empresa', className: 'w-14' },
                {
                    titulo: 'Empresa',
                    render: (e) => (
                        <div>
                            <p className="font-medium">{e.razon_social}</p>
                            {e.nombre_comercial && <p className="text-xs text-texto-suave">{e.nombre_comercial}</p>}
                        </div>
                    ),
                },
                { titulo: 'NIT', campo: 'nit' },
                {
                    titulo: 'Facturación / Contacto',
                    render: (e) => (
                        <div className="text-xs">
                            <p>{e.email_facturacion ?? '—'}</p>
                            <p className="text-texto-suave">{e.telefono ?? '—'}</p>
                        </div>
                    ),
                },
                { titulo: 'Proformas', campo: 'proformas_count', className: 'text-center' },
                { titulo: 'Registro', render: (e) => fecha(e.fecha_registro) },
                { titulo: 'Estado', render: (e) => <InsigniaActivo activo={e.activo} /> },
            ]}
            campos={[
                { nombre: 'razon_social', etiqueta: 'Razón social', reglas: { requerido: true, min: 2, max: 200 } },
                { nombre: 'nit', etiqueta: 'NIT', ancho: 'medio', reglas: { requerido: true, ...NIT } },
                { nombre: 'nombre_comercial', etiqueta: 'Nombre comercial', ancho: 'medio', reglas: { max: 200 } },
                { nombre: 'email_facturacion', etiqueta: 'Correo de facturación', tipo: 'email', ancho: 'medio', reglas: { email: true, max: 150 } },
                { nombre: 'telefono', etiqueta: 'Teléfono', ancho: 'medio', reglas: TELEFONO },
                { nombre: 'direccion_facturacion', etiqueta: 'Dirección de facturación', reglas: { max: 255 } },
            ]}
            aFormulario={(e) => ({
                razon_social: e.razon_social,
                nit: e.nit,
                nombre_comercial: e.nombre_comercial ?? '',
                email_facturacion: e.email_facturacion ?? '',
                telefono: e.telefono ?? '',
                direccion_facturacion: e.direccion_facturacion ?? '',
            })}
            estado={{ activo: (e) => e.activo, endpoint: (e) => `/empresas/${e.id_empresa}/estado` }}
        />
    );
}

