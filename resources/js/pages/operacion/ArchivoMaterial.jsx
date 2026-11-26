import { useEffect, useId, useState } from 'react';
import { UploadCloud, Link2, ExternalLink, Download, FileText, Paperclip } from 'lucide-react';

/**
 * Piezas del material publicitario relacionadas con su archivo:
 *  - OrigenArchivo: elegir entre subir el archivo (se guarda en storage) o indicar un enlace web.
 *  - VisorMaterial: reproduce / muestra el material (video, audio, imagen, texto o PDF).
 * Las reglas son las mismas que valida el servidor (App\Support\ArchivoMaterial).
 */

export const MAX_MB = 20;

/** Extensiones permitidas por tipo (MaterialPublicitario::FORMATOS_VALIDOS). */
export const FORMATOS = {
    video: ['mp4', 'mov', 'avi', 'mkv'],
    audio: ['mp3', 'wav', 'aac', 'ogg'],
    imagen: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    banner: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    texto: ['txt', 'pdf', 'docx'],
};

export const extension = (nombre = '') => (nombre.includes('.') ? nombre.split('.').pop().toLowerCase() : '');

export const tamanoLegible = (bytes) => {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
};

/** Mensaje de error del archivo elegido, o null si es válido. */
export function validarArchivo(archivo, tipo) {
    if (!archivo) return null;
    const permitidos = FORMATOS[tipo] ?? [];
    const ext = extension(archivo.name);
    if (!permitidos.includes(ext)) {
        return `El archivo «${archivo.name}» no corresponde a un material de tipo ${tipo}. Formatos permitidos: ${permitidos.join(', ')}.`;
    }
    if (archivo.size > MAX_MB * 1048576) {
        return `El archivo pesa ${tamanoLegible(archivo.size)}; el máximo permitido es ${MAX_MB} MB.`;
    }
    if (archivo.size === 0) return 'El archivo está vacío.';
    return null;
}

/** Duración (segundos, redondeada) de un video o audio elegido en el equipo; null si el navegador no la puede leer. */
export function leerDuracion(archivo) {
    return new Promise((resolver) => {
        const url = URL.createObjectURL(archivo);
        const medio = document.createElement(archivo.type.startsWith('audio/') ? 'audio' : 'video');
        const fin = (valor) => {
            URL.revokeObjectURL(url);
            resolver(valor);
        };
        medio.preload = 'metadata';
        medio.onloadedmetadata = () => fin(Number.isFinite(medio.duration) && medio.duration > 0 ? Math.max(1, Math.round(medio.duration)) : null);
        medio.onerror = () => fin(null);
        setTimeout(() => fin(null), 8000);
        medio.src = url;
    });
}

/**
 * Bloque "Archivo del material" de los formularios.
 *   datos: { tipo, origen: 'archivo'|'enlace', archivo: File|null, url_archivo, interno: bool, nombre_actual }
 *   onCambiar(campo, valor) · onArchivo(File|null) · errores: { archivo, url_archivo }
 */
export function OrigenArchivo({ datos, onCambiar, onArchivo, errores = {}, requerido = true }) {
    const id = useId();
    const permitidos = FORMATOS[datos.tipo] ?? [];
    const accept = permitidos.map((e) => `.${e}`).join(',');
    const errorArchivo = errores.archivo?.[0] ?? errores.archivo;
    const errorUrl = errores.url_archivo?.[0] ?? errores.url_archivo;

    const pestana = (valor, Icono, texto) => (
        <button
            type="button"
            role="radio"
            aria-checked={datos.origen === valor}
            onClick={() => onCambiar('origen', valor)}
            className={`flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium rounded-tema border transition-colors ${
                datos.origen === valor ? 'bg-primario text-white border-primario' : 'border-borde text-texto-suave hover:bg-superficie-2'
            }`}
        >
            <Icono size={15} aria-hidden /> {texto}
        </button>
    );

    return (
        <fieldset className="space-y-2">
            <legend className="etiqueta">
                Archivo del material {requerido && <span className="text-peligro" aria-hidden>*</span>}
            </legend>
            <div role="radiogroup" aria-label="Origen del archivo" className="flex gap-2">
                {pestana('archivo', UploadCloud, 'Subir archivo')}
                {pestana('enlace', Link2, 'Enlace web')}
            </div>

            {datos.origen === 'archivo' ? (
                <div>
                    {datos.interno && !datos.archivo && (
                        <p className="text-xs text-texto-suave mb-1.5 flex items-center gap-1">
                            <Paperclip size={12} aria-hidden /> Archivo actual: <strong className="text-texto">{datos.nombre_actual}</strong>. Elija otro solo si desea reemplazarlo.
                        </p>
                    )}
                    <label
                        htmlFor={id}
                        className={`flex flex-col items-center justify-center gap-1 p-4 rounded-tema border-2 border-dashed cursor-pointer text-center transition-colors hover:bg-superficie-2 ${
                            errorArchivo ? 'border-peligro' : 'border-borde'
                        }`}
                    >
                        <UploadCloud size={22} className="text-primario" aria-hidden />
                        {datos.archivo ? (
                            <span className="text-sm font-medium break-all">
                                {datos.archivo.name} <span className="text-texto-suave font-normal">· {tamanoLegible(datos.archivo.size)}</span>
                            </span>
                        ) : (
                            <span className="text-sm">Haga clic para elegir el archivo</span>
                        )}
                        <span className="text-xs text-texto-suave">
                            Formatos: {permitidos.join(', ')} · máximo {MAX_MB} MB
                        </span>
                    </label>
                    <input
                        id={id}
                        type="file"
                        accept={accept}
                        className="sr-only"
                        aria-invalid={Boolean(errorArchivo)}
                        aria-describedby={errorArchivo ? `${id}-error` : undefined}
                        onChange={(e) => {
                            onArchivo(e.target.files?.[0] ?? null);
                            e.target.value = '';
                        }}
                    />
                    {datos.archivo && (
                        <button type="button" className="text-xs text-peligro hover:underline mt-1" onClick={() => onArchivo(null)}>
                            Quitar archivo elegido
                        </button>
                    )}
                    {errorArchivo && (
                        <p id={`${id}-error`} className="texto-error" role="alert">{errorArchivo}</p>
                    )}
                </div>
            ) : (
                <div>
                    <label htmlFor={`${id}-url`} className="sr-only">Enlace del archivo</label>
                    <input
                        id={`${id}-url`}
                        type="url"
                        className={`input ${errorUrl ? 'input-error' : ''}`}
                        placeholder="https://drive.google.com/…"
                        value={datos.url_archivo}
                        onChange={(e) => onCambiar('url_archivo', e.target.value)}
                        aria-invalid={Boolean(errorUrl)}
                        aria-describedby={errorUrl ? `${id}-url-error` : undefined}
                    />
                    {errorUrl ? (
                        <p id={`${id}-url-error`} className="texto-error" role="alert">{errorUrl}</p>
                    ) : (
                        <p className="text-xs text-texto-suave mt-1">Use esta opción si el archivo ya está publicado en otro sitio (Drive, YouTube, etc.).</p>
                    )}
                </div>
            )}
        </fieldset>
    );
}

const EXT_IMAGEN = /\.(png|jpe?g|gif|webp)(\?|#|$)/i;
const EXT_VIDEO = /\.(mp4|webm|mov)(\?|#|$)/i;
const EXT_AUDIO = /\.(mp3|wav|aac|ogg)(\?|#|$)/i;

/** Muestra el material dentro de la página según su tipo y formato. */
export function VisorMaterial({ material }) {
    const url = material?.url_vista;
    const interno = Boolean(material?.archivo_interno);
    const formato = (material?.formato || extension(material?.nombre_archivo)).toLowerCase();
    const [texto, setTexto] = useState(null);
    const [fallo, setFallo] = useState(false);

    // Si el archivo es externo, solo se incrusta cuando el enlace apunta directo a un archivo multimedia
    const clase = interno
        ? { video: 'video', audio: 'audio', imagen: 'imagen', banner: 'imagen' }[material.tipo] ?? (formato === 'txt' ? 'txt' : formato === 'pdf' ? 'pdf' : 'otro')
        : url && EXT_IMAGEN.test(url) ? 'imagen' : url && EXT_VIDEO.test(url) ? 'video' : url && EXT_AUDIO.test(url) ? 'audio' : 'externo';

    useEffect(() => {
        setTexto(null);
        setFallo(false);
        if (clase !== 'txt' || !url) return;
        let vigente = true;
        fetch(url)
            .then((r) => (r.ok ? r.text() : Promise.reject(r)))
            .then((t) => vigente && setTexto(t))
            .catch(() => vigente && setFallo(true));
        return () => {
            vigente = false;
        };
    }, [url, clase]);

    if (!material) return null;
    const titulo = `${material.nombre_archivo} (v${material.version})`;

    let contenido;
    if (!url) {
        contenido = <p className="text-sm text-texto-suave text-center py-8">Este material no tiene archivo ni enlace registrado.</p>;
    } else if (fallo) {
        contenido = <p className="text-sm text-peligro text-center py-8" role="alert">No se pudo cargar el archivo. Cierre esta ventana y vuelva a abrirla.</p>;
    } else if (clase === 'video') {
        contenido = <video key={url} src={url} controls preload="metadata" className="w-full max-h-[60vh] rounded-tema bg-black" aria-label={`Video: ${titulo}`} onError={() => setFallo(true)} />;
    } else if (clase === 'audio') {
        contenido = <audio key={url} src={url} controls className="w-full" aria-label={`Audio: ${titulo}`} onError={() => setFallo(true)} />;
    } else if (clase === 'imagen') {
        contenido = (
            <div className="rounded-tema bg-superficie-2 p-2 flex justify-center">
                <img src={url} alt={`Vista del material ${titulo}`} className="max-w-full max-h-[60vh] object-contain" onError={() => setFallo(true)} />
            </div>
        );
    } else if (clase === 'txt') {
        contenido = (
            <pre className="p-3 rounded-tema bg-superficie-2 border border-borde text-sm whitespace-pre-wrap max-h-[60vh] overflow-auto">
                {texto ?? 'Cargando…'}
            </pre>
        );
    } else if (clase === 'pdf') {
        contenido = <iframe src={url} title={`Documento: ${titulo}`} className="w-full h-[60vh] rounded-tema border border-borde" />;
    } else {
        contenido = (
            <div className="flex flex-col items-center gap-2 py-8 text-center">
                <FileText size={36} className="text-texto-suave" aria-hidden />
                <p className="text-sm text-texto-suave">
                    {clase === 'externo' ? 'El material está publicado en un sitio externo.' : 'Este formato no se puede mostrar en el navegador; descárguelo para verlo.'}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {contenido}
            {material.texto_publicacion && (
                <div>
                    <p className="etiqueta">Texto de la publicación</p>
                    <div className="p-3 rounded-tema bg-superficie-2/60 border border-borde text-sm whitespace-pre-wrap">{material.texto_publicacion}</div>
                </div>
            )}
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-texto-suave">
                <span className="uppercase font-semibold">{formato || material.tipo}</span>
                {material.duracion_seg ? <span>{material.duracion_seg} s</span> : null}
                {material.tamano_bytes ? <span>{tamanoLegible(material.tamano_bytes)}</span> : null}
                <span>{interno ? 'Guardado en el sistema' : 'Enlace externo'}</span>
                {url && (
                    <span className="ml-auto flex gap-3">
                        <a href={url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-primario hover:underline">
                            Abrir en otra pestaña <ExternalLink size={12} aria-hidden />
                        </a>
                        {interno && (
                            <a href={url} download={material.nombre_archivo} className="inline-flex items-center gap-1 text-primario hover:underline">
                                Descargar <Download size={12} aria-hidden />
                            </a>
                        )}
                    </span>
                )}
            </div>
        </div>
    );
}
