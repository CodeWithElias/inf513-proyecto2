/** Formatos para mostrar datos (Bolivia, español). */
const ZONA = 'America/La_Paz';

export function fecha(valor) {
    if (!valor) return '—';
    const d = new Date(String(valor).length === 10 ? `${valor}T12:00:00` : valor);
    return Number.isNaN(d.getTime()) ? valor : d.toLocaleDateString('es-BO', { timeZone: ZONA });
}

export function fechaHora(valor) {
    if (!valor) return '—';
    const d = new Date(valor);
    return Number.isNaN(d.getTime()) ? valor : d.toLocaleString('es-BO', { timeZone: ZONA, dateStyle: 'short', timeStyle: 'short' });
}

export const numero = (n) => (n === null || n === undefined ? '—' : Number(n).toLocaleString('es-BO'));

export const moneda = (n) =>
    n === null || n === undefined ? '—' : `Bs ${Number(n).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
