import { useId } from 'react';

/**
 * Campo de formulario con etiqueta y mensaje de error (cliente o servidor).
 *   <Campo etiqueta="Correo" nombre="email" tipo="email" valor={v.email} onChange={cambiar} error={errores.email} requerido />
 *   tipo: text | email | password | number | date | url | textarea | select (con opciones=[{id, nombre}])
 */
export default function Campo({ etiqueta, nombre, tipo = 'text', valor, onChange, error, requerido, opciones = [], ayuda, placeholder, deshabilitado, vacio = 'Seleccione…', ...resto }) {
    const id = useId();
    const props = {
        id,
        name: nombre,
        value: valor ?? '',
        onChange: (e) => onChange(nombre, e.target.value),
        disabled: deshabilitado,
        placeholder,
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : ayuda ? `${id}-ayuda` : undefined,
        className: `input ${error ? 'input-error' : ''}`,
        ...resto,
    };

    return (
        <div>
            <label htmlFor={id} className="etiqueta">
                {etiqueta} {requerido && <span className="text-peligro" aria-hidden>*</span>}
            </label>
            {tipo === 'select' ? (
                <select {...props}>
                    <option value="">{vacio}</option>
                    {opciones.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.nombre}
                        </option>
                    ))}
                </select>
            ) : tipo === 'textarea' ? (
                <textarea rows={3} {...props} />
            ) : (
                <input type={tipo} {...props} />
            )}
            {error ? (
                <p id={`${id}-error`} className="texto-error" role="alert">{error}</p>
            ) : (
                ayuda && <p id={`${id}-ayuda`} className="text-xs text-texto-suave mt-1">{ayuda}</p>
            )}
        </div>
    );
}
