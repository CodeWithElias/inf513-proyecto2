/**
 * Validación en el cliente con mensajes en español.
 * Las mismas reglas se validan otra vez en el servidor (FormRequest).
 *
 * Uso:
 *   const errores = validar(valores, {
 *     email: { requerido: true, email: true, max: 150, etiqueta: 'correo electrónico' },
 *     password: { requerido: true, min: 6 },
 *   });
 *   // -> { email: 'El correo electrónico no es válido.' }  ({} si todo está bien)
 *
 * Reglas: requerido, min, max (longitud de texto), email, url, entero,
 *         minValor, maxValor, patron + mensajePatron, fecha, hastaHoy, igualA
 */
const EMAIL = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
const URL = /^https?:\/\/[^\s/$.?#].[^\s]*$/i;

const vacio = (v) => v === undefined || v === null || (typeof v === 'string' && v.trim() === '');

export function validarCampo(valor, r = {}, valores = {}) {
    const nombre = r.etiqueta ?? 'este campo';
    const Nombre = nombre.charAt(0).toUpperCase() + nombre.slice(1);

    if (vacio(valor)) {
        return r.requerido ? `El campo ${nombre} es obligatorio.` : null;
    }
    const texto = String(valor).trim();

    if (r.min && texto.length < r.min) return `${Nombre} debe tener al menos ${r.min} caracteres.`;
    if (r.max && texto.length > r.max) return `${Nombre} no puede tener más de ${r.max} caracteres.`;
    if (r.email && !EMAIL.test(texto)) return `${Nombre} debe ser un correo válido (ej. nombre@dominio.com).`;
    if (r.url && !URL.test(texto)) return `${Nombre} debe ser una dirección web que empiece con http:// o https://.`;
    if (r.entero && !/^-?\d+$/.test(texto)) return `${Nombre} debe ser un número entero.`;
    if (r.minValor !== undefined && Number(texto) < r.minValor) return `${Nombre} debe ser al menos ${r.minValor}.`;
    if (r.maxValor !== undefined && Number(texto) > r.maxValor) return `${Nombre} no puede ser mayor que ${r.maxValor}.`;
    if (r.patron && !r.patron.test(texto)) return r.mensajePatron ?? `El formato de ${nombre} no es válido.`;
    if (r.fecha && Number.isNaN(Date.parse(texto))) return `${Nombre} no es una fecha válida.`;
    if (r.hastaHoy && texto > new Date().toISOString().slice(0, 10)) return `${Nombre} no puede ser una fecha futura.`;
    if (r.igualA && texto !== String(valores[r.igualA] ?? '')) return `${Nombre} no coincide.`;
    return null;
}

export function validar(valores, reglas) {
    const errores = {};
    for (const [campo, r] of Object.entries(reglas)) {
        const e = validarCampo(valores[campo], r, valores);
        if (e) errores[campo] = e;
    }
    return errores;
}
