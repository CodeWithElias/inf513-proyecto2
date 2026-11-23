/** Etiqueta de estado coloreada. tono: exito | peligro | aviso | primario | neutro */
const TONOS = {
    exito: 'bg-exito/15 text-exito',
    peligro: 'bg-peligro/15 text-peligro',
    aviso: 'bg-aviso/15 text-aviso',
    primario: 'bg-primario/15 text-primario',
    neutro: 'bg-superficie-2 text-texto-suave',
};

export default function Insignia({ tono = 'neutro', children }) {
    return <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${TONOS[tono] ?? TONOS.neutro}`}>{children}</span>;
}

export const InsigniaActivo = ({ activo }) => <Insignia tono={activo ? 'exito' : 'peligro'}>{activo ? 'Activo' : 'Inactivo'}</Insignia>;
