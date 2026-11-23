import PaginaCrud from '../../components/PaginaCrud';

export default function Plataformas() {
    return (
        <PaginaCrud
            titulo="Plataformas"
            descripcion="Redes sociales disponibles (Facebook, Instagram, TikTok, YouTube…)."
            singular="plataforma"
            endpoint="/plataformas"
            privilegio="plataformas"
            idCampo="id_plataforma"
            editable={false}
            busqueda={false}
            columnas={[
                { titulo: 'ID', campo: 'id_plataforma', className: 'w-14' },
                { titulo: 'Plataforma', render: (p) => <span className="font-medium">{p.nombre}</span> },
                { titulo: 'Redes registradas', campo: 'redes', className: 'text-center' },
            ]}
            campos={[{ nombre: 'nombre', etiqueta: 'Nombre', reglas: { requerido: true, min: 2, max: 100 } }]}
        />
    );
}
