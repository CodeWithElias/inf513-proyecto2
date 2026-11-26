import { loadStripe } from '@stripe/stripe-js';

/**
 * Stripe en el navegador (modo de prueba).
 * La clave PUBLICABLE llega en <meta name="stripe-key"> (config/services.php). La secreta nunca sale del servidor.
 */
const clave = document.querySelector('meta[name="stripe-key"]')?.content ?? '';
let promesa = null;

export const stripeConfigurado = clave.startsWith('pk_');

export function obtenerStripe() {
    if (!stripeConfigurado) return Promise.resolve(null);
    promesa ??= loadStripe(clave, { locale: 'es' });
    return promesa;
}

/**
 * Ejecuta un pago contra la API. Si el banco pide verificar la tarjeta (3D Secure), la API responde 202 con
 * { requiere_accion, client_secret, payment_intent }: se muestra la ventana de Stripe y se vuelve a enviar
 * la misma operación con payment_intent para que el servidor verifique y registre el resultado
 * (también cuando la verificación falla, así el intento queda registrado).
 *   const r = await pagarConVerificacion((extra) => api.post('/pagos', { ...datos, ...extra }));
 */
export async function pagarConVerificacion(enviar) {
    const r = await enviar({});
    const d = r.data?.data;
    if (!d?.requiere_accion) return r;
    const stripe = await obtenerStripe();
    if (!stripe) throw new Error('No se pudo cargar Stripe para verificar la tarjeta. Recargue la página.');
    await stripe.handleNextAction({ clientSecret: d.client_secret });
    return enviar({ payment_intent: d.payment_intent });
}

/** Colores del tema actual (día/noche, alto contraste) para el campo de tarjeta de Stripe. */
export function estiloTarjeta() {
    const css = getComputedStyle(document.documentElement);
    const v = (n, defecto) => css.getPropertyValue(n).trim() || defecto;
    return {
        base: {
            color: v('--texto', '#1f2937'),
            fontFamily: 'system-ui, "Segoe UI", sans-serif',
            fontSize: '16px',
            '::placeholder': { color: v('--texto-suave', '#6b7280') },
            iconColor: v('--primario', '#0a35a8'),
        },
        invalid: { color: v('--peligro', '#dc2626'), iconColor: v('--peligro', '#dc2626') },
    };
}
