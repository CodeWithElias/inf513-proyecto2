import { useState } from 'react';
import { erroresCampos, mensajeError } from '../lib/api';
import { validar } from '../lib/validar';

/**
 * Estado de un formulario con validación en cliente + errores 422 del servidor.
 *   const f = useFormulario(valoresIniciales, reglas);
 *   <Campo nombre="email" valor={f.valores.email} onChange={f.cambiar} error={f.errores.email} />
 *   await f.enviar(async (valores) => api.post('/usuarios', valores));   // devuelve la respuesta o null
 */
export function useFormulario(iniciales, reglas = {}) {
    const [valores, setValores] = useState(iniciales);
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const cambiar = (nombre, valor) => {
        setValores((v) => ({ ...v, [nombre]: valor }));
        if (errores[nombre]) setErrores(({ [nombre]: _, ...resto }) => resto);
    };

    const reiniciar = (nuevos = iniciales) => {
        setValores(nuevos);
        setErrores({});
        setErrorGeneral(null);
    };

    const enviar = async (accion, reglasActuales = reglas) => {
        setErrorGeneral(null);
        const locales = validar(valores, reglasActuales);
        setErrores(locales);
        if (Object.keys(locales).length) {
            setErrorGeneral('Revise los campos marcados en rojo.');
            return null;
        }
        setEnviando(true);
        try {
            return await accion(valores);
        } catch (e) {
            setErrores(erroresCampos(e));
            setErrorGeneral(mensajeError(e));
            return null;
        } finally {
            setEnviando(false);
        }
    };

    return { valores, setValores, errores, errorGeneral, enviando, cambiar, reiniciar, enviar };
}
