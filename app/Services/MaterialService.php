<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Campania;
use App\Models\MaterialPublicitario;
use App\Models\ProgramacionPauta;
use App\Models\RevisionMaterial;
use App\Models\Usuario;
use App\Support\ArchivoMaterial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * C1 / CU4: Gestión de Material Publicitario y Revisiones.
 * Portado desde NMaterial.java (Proyecto 1).
 */
class MaterialService
{
    public function __construct(private readonly AvisoService $avisos)
    {
    }

    /** Listado paginado con filtros y aislamiento por empresa para rol Cliente. */
    public function listar(array $filtros, int $porPagina, ?Usuario $actor = null): LengthAwarePaginator
    {
        $q = MaterialPublicitario::query()
            ->with([
                'campania:id_campania,nombre,id_proforma,estado',
                'campania.proforma:id_proforma,id_empresa',
                'campania.proforma.empresa:id_empresa,razon_social',
                'usuario:id_usuario,nombre_completo',
                'ultimaRevision.usuario:id_usuario,nombre_completo',
            ]);

        // Aislamiento: si es Cliente, solo ve campañas de su empresa
        if ($actor?->esCliente()) {
            $idEmpresa = $actor->idEmpresa() ?? 0;
            $q->whereHas('campania.proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
        }

        // Filtro por campaña
        if (! empty($filtros['id_campania'])) {
            $q->where('id_campania', (int) $filtros['id_campania']);
        }

        // Filtro por estado
        if (! empty($filtros['estado']) && in_array($filtros['estado'], MaterialPublicitario::ESTADOS, true)) {
            $q->where('estado', $filtros['estado']);
        }

        // Filtro por tipo
        if (! empty($filtros['tipo']) && in_array($filtros['tipo'], MaterialPublicitario::TIPOS, true)) {
            $q->where('tipo', $filtros['tipo']);
        }

        // Búsqueda por texto (nombre de archivo, URL o nombre de campaña)
        if (! empty($filtros['q'])) {
            $busqueda = '%'.trim($filtros['q']).'%';
            $q->where(fn (Builder $b) => $b
                ->where('nombre_archivo', 'ilike', $busqueda)
                ->orWhere('url_archivo', 'ilike', $busqueda)
                ->orWhere('texto_publicacion', 'ilike', $busqueda)
                ->orWhereHas('campania', fn (Builder $bc) => $bc->where('nombre', 'ilike', $busqueda))
            );
        }

        return $q->orderByDesc('id_material')->paginate($porPagina);
    }

    /** Obtiene el detalle de un material con validación de acceso. */
    public function obtener(MaterialPublicitario $material, ?Usuario $actor = null): MaterialPublicitario
    {
        $this->asegurarAcceso($material, $actor);

        return $material->load([
            'campania.proforma.empresa',
            'usuario:id_usuario,nombre_completo,email',
            'revisiones.usuario:id_usuario,nombre_completo,email',
        ]);
    }

    /** RF08: Carga de material publicitario. Inicializa en estado pendiente y versión 1. */
    public function crear(array $datos, Usuario $actor): MaterialPublicitario
    {
        $campania = Campania::with('proforma')->findOrFail($datos['id_campania']);
        $this->validarCampaniaAbierta($campania);
        $this->asegurarRepresentanteEmpresa($campania, $actor);

        $tipo = $this->validarTipo($datos['tipo'] ?? '');
        $contenido = $this->validarContenido($tipo, $datos);

        return DB::transaction(function () use ($campania, $actor, $tipo, $contenido) {
            $material = MaterialPublicitario::create([
                'id_campania' => $campania->id_campania,
                'id_usuario' => $actor->id_usuario,
                'tipo' => $tipo,
                'nombre_archivo' => $contenido['nombre_archivo'],
                'url_archivo' => $contenido['url_archivo'],
                'formato' => $contenido['formato'],
                'duracion_seg' => $contenido['duracion_seg'],
                'tamano_bytes' => $contenido['tamano_bytes'] ?? null,
                'texto_publicacion' => $contenido['texto_publicacion'],
                'version' => 1,
                'estado' => MaterialPublicitario::ESTADO_PENDIENTE,
                'fecha_carga' => now(),
            ]);
            // Archivo subido: se guarda en storage y la BD queda con su ruta
            if ($contenido['archivo']) {
                $material->update(['url_archivo' => ArchivoMaterial::guardar($contenido['archivo'], $material->id_material, 1, $contenido['formato'])]);
            }
            $this->avisarParaRevision($material, $campania, $actor, false);

            return $material;
        });
    }

    /** Corrige un material mientras aún esté en estado pendiente. */
    public function actualizar(MaterialPublicitario $material, array $datos, Usuario $actor): MaterialPublicitario
    {
        $this->asegurarAcceso($material, $actor);

        if (! $material->estaPendiente()) {
            throw new NegocioException("El material {$material->id_material} está «{$material->estado}»; solo se puede modificar mientras está pendiente de revisión.");
        }

        $tipo = ! empty($datos['tipo']) ? $this->validarTipo($datos['tipo']) : $material->tipo;
        $contenido = $this->validarContenido($tipo, $datos, $material->url_archivo);

        // Nuevo archivo (o nuevo enlace) para la misma versión: reemplaza al anterior
        $anterior = $material->url_archivo;
        if ($contenido['archivo']) {
            $contenido['url_archivo'] = ArchivoMaterial::guardar($contenido['archivo'], $material->id_material, $material->version, $contenido['formato']);
        }
        if ($anterior !== $contenido['url_archivo']) {
            ArchivoMaterial::borrar($anterior);
        }

        $material->update([
            'tipo' => $tipo,
            'nombre_archivo' => $contenido['nombre_archivo'],
            'url_archivo' => $contenido['url_archivo'],
            'formato' => $contenido['formato'],
            'duracion_seg' => $contenido['duracion_seg'],
            'tamano_bytes' => $contenido['tamano_bytes'] ?? $material->tamano_bytes,
            'texto_publicacion' => $contenido['texto_publicacion'],
        ]);

        return $material;
    }

    /** Sube una nueva versión cuando el material fue observado con correcciones. */
    public function subirNuevaVersion(MaterialPublicitario $material, array $datos, Usuario $actor): MaterialPublicitario
    {
        $this->asegurarAcceso($material, $actor);

        if (! $material->puedeSubirNuevaVersion()) {
            throw new NegocioException("El material {$material->id_material} está «{$material->estado}»; solo se sube una nueva versión cuando se solicitaron correcciones.");
        }

        $contenido = $this->validarContenido($material->tipo, $datos, $material->url_archivo);

        // El archivo de la versión anterior se conserva (historial); el nuevo se guarda con la nueva versión
        if ($contenido['archivo']) {
            $contenido['url_archivo'] = ArchivoMaterial::guardar($contenido['archivo'], $material->id_material, $material->version + 1, $contenido['formato']);
        }

        $material->update([
            'nombre_archivo' => $contenido['nombre_archivo'],
            'url_archivo' => $contenido['url_archivo'],
            'formato' => $contenido['formato'],
            'duracion_seg' => $contenido['duracion_seg'],
            'tamano_bytes' => $contenido['tamano_bytes'] ?? $material->tamano_bytes,
            'texto_publicacion' => $contenido['texto_publicacion'],
            'version' => $material->version + 1,
            'estado' => MaterialPublicitario::ESTADO_PENDIENTE,
            'fecha_carga' => now(),
        ]);
        $this->avisarParaRevision($material, $material->campania, $actor, true);

        return $material;
    }

    /** Retiro de material (baja lógica). No se permite si está programado en alguna pauta. */
    public function retirar(MaterialPublicitario $material, Usuario $actor): MaterialPublicitario
    {
        $this->asegurarAcceso($material, $actor);

        if ($material->estaRetirado()) {
            throw new NegocioException("El material {$material->id_material} ya fue retirado previamente.");
        }

        // Validar que no esté programado en una pauta
        $pautasProgramadas = DB::table('programacion_pauta')
            ->where('id_material', $material->id_material)
            ->whereIn('estado', [ProgramacionPauta::ESTADO_PROGRAMADA, ProgramacionPauta::ESTADO_EMITIDA])
            ->count();

        if ($pautasProgramadas > 0) {
            throw new NegocioException("No se puede retirar el material {$material->id_material}: tiene {$pautasProgramadas} pauta(s) programada(s) o emitida(s).");
        }

        $material->update(['estado' => MaterialPublicitario::ESTADO_RETIRADO]);

        return $material;
    }

    /** RF09: Revisión de material por personal de Canal 11 (aprobar, rechazar, pedir corrección). */
    public function revisar(MaterialPublicitario $material, array $datos, Usuario $revisor): RevisionMaterial
    {
        if ($material->estaRetirado()) {
            throw new NegocioException("El material {$material->id_material} está retirado y no puede ser revisado.");
        }

        if (! $material->puedeRevisarse()) {
            throw new NegocioException("El material {$material->id_material} se encuentra en estado «{$material->estado}»; solo se revisa material pendiente o en corrección.");
        }

        // Un cliente/representante no puede revisar material; la revisión es del personal institucional
        if ($revisor->esCliente() || $revisor->idEmpresa() !== null) {
            throw new NegocioException('Usted es representante de una empresa anunciante; la revisión la realiza el personal de Canal 11.', 403);
        }

        $resultado = $datos['resultado'] ?? '';
        if (! in_array($resultado, RevisionMaterial::RESULTADOS, true)) {
            throw new NegocioException("El resultado de revisión «{$resultado}» no es válido. Debe ser: aprobado, rechazado o correccion.");
        }

        $observaciones = trim($datos['observaciones'] ?? '');
        if (in_array($resultado, [RevisionMaterial::RESULTADO_RECHAZADO, RevisionMaterial::RESULTADO_CORRECCION], true) && empty($observaciones)) {
            throw new NegocioException('Debe detallar las observaciones al rechazar o solicitar correcciones al material.');
        }

        return DB::transaction(function () use ($material, $revisor, $resultado, $observaciones) {
            $revision = RevisionMaterial::create([
                'id_material' => $material->id_material,
                'id_usuario' => $revisor->id_usuario,
                'fecha' => now(),
                'resultado' => $resultado,
                'observaciones' => $observaciones ?: null,
            ]);

            $material->update(['estado' => $resultado]);
            $this->avisarRevision($material, $resultado, $observaciones, $revisor);

            return $revision;
        });
    }

    /** Historial de revisiones del material. */
    public function listarRevisiones(MaterialPublicitario $material, ?Usuario $actor = null): array
    {
        $this->asegurarAcceso($material, $actor);

        return $material->revisiones()
            ->with('usuario:id_usuario,nombre_completo,email')
            ->orderByDesc('fecha')
            ->get()
            ->all();
    }

    // --- Avisos del flujo ---

    private function avisarParaRevision(MaterialPublicitario $m, Campania $campania, Usuario $actor, bool $esVersion): void
    {
        $campania->loadMissing('proforma.empresa:id_empresa,razon_social');
        if ($campania->proforma?->estado === 'pendiente') {
            return; // contratación en curso: el aviso sale al confirmarla
        }
        $quien = $campania->proforma?->empresa?->razon_social ?? 'El cliente';
        $this->avisos->alPersonal('revisiones.crear',
            ($esVersion ? "Nueva versión v{$m->version} por revisar" : 'Material nuevo por revisar').": {$m->nombre_archivo}",
            "{$quien} cargó «{$m->nombre_archivo}» ({$m->tipo}) para la campaña «{$campania->nombre}». Revíselo en «Material publicitario» y apruébelo, pida correcciones o recházelo.",
            $actor);
        // Si lo cargó el personal en nombre del cliente, el cliente también se entera
        if (! $actor->esCliente()) {
            $this->avisos->alCliente($campania->proforma, "Material cargado: {$m->nombre_archivo}",
                "Canal 11 cargó «{$m->nombre_archivo}» en su campaña «{$campania->nombre}». Quedó pendiente de revisión.", $actor);
        }
    }

    private function avisarRevision(MaterialPublicitario $m, string $resultado, string $observaciones, Usuario $revisor): void
    {
        $m->loadMissing('campania.proforma');
        $campania = $m->campania;
        $obs = $observaciones !== '' ? " Observaciones: {$observaciones}" : '';
        [$titulo, $mensaje] = match ($resultado) {
            RevisionMaterial::RESULTADO_APROBADO => [
                "Material aprobado: {$m->nombre_archivo}",
                "Su material «{$m->nombre_archivo}» de la campaña «{$campania->nombre}» fue aprobado.".$obs.' '.
                ($campania->proforma?->situacionPago()['habilita_pautas']
                    ? 'Canal 11 programará sus pautas en los espacios contratados.'
                    : 'Para que se programen sus pautas falta completar el pago de la proforma en «Pagos».'),
            ],
            RevisionMaterial::RESULTADO_CORRECCION => [
                "Corrija su material: {$m->nombre_archivo}",
                "Canal 11 pidió correcciones a «{$m->nombre_archivo}».".$obs.' Suba la versión corregida desde «Material publicitario» (botón «Subir nueva versión»).',
            ],
            default => [
                "Material rechazado: {$m->nombre_archivo}",
                "Su material «{$m->nombre_archivo}» fue rechazado y no se emitirá.".$obs.' Puede cargar otra pieza para la campaña.',
            ],
        };
        $destinos = array_merge($this->avisos->cliente($campania->proforma), [(int) $m->id_usuario]);
        $this->avisos->enviar($destinos, $titulo, $mensaje, null, $revisor);
        // Aprobado y pagado: el personal que programa ya puede crear las pautas
        if ($resultado === RevisionMaterial::RESULTADO_APROBADO && $campania->proforma?->situacionPago()['habilita_pautas']) {
            $this->avisos->alPersonal('pautas.crear', "Listo para programar: {$m->nombre_archivo}",
                "El material de la campaña «{$campania->nombre}» está aprobado y la proforma pagada. Ya puede programar sus pautas.", $revisor);
        }
    }

    // --- Helpers de validación interna ---

    private function validarTipo(string $tipo): string
    {
        $t = strtolower(trim($tipo));
        if (! in_array($t, MaterialPublicitario::TIPOS, true)) {
            throw new NegocioException("Tipo «{$tipo}» no válido. Opciones: ".implode(', ', MaterialPublicitario::TIPOS));
        }

        return $t;
    }

    /** @param  string|null  $urlActual  url_archivo que ya tiene el material (al editar o subir versión) */
    private function validarContenido(string $tipo, array $datos, ?string $urlActual = null): array
    {
        $nombre = trim($datos['nombre_archivo'] ?? '');
        $url = trim($datos['url_archivo'] ?? '');
        // Una ruta interna (archivo en storage) solo vale si es la que el material ya tiene:
        // así nadie puede apuntar su material al archivo de otro. Lo demás debe ser un enlace web.
        if ($url !== '' && $url !== $urlActual && ! preg_match('#^https?://\S+$#i', $url)) {
            throw new NegocioException('El enlace del archivo debe ser una dirección web que empiece con http:// o https://, o suba el archivo.');
        }
        $duracion = isset($datos['duracion_seg']) ? (int) $datos['duracion_seg'] : null;
        $texto = trim($datos['texto_publicacion'] ?? '');

        // Archivo subido desde la web (alternativa al enlace)
        $archivo = ($datos['archivo'] ?? null) instanceof UploadedFile ? $datos['archivo'] : null;
        $extArchivo = null;
        if ($archivo) {
            [$extArchivo] = ArchivoMaterial::validar($archivo, $tipo);
            if ($nombre === '') {
                $nombre = $archivo->getClientOriginalName();
            }
        }

        if ($tipo === 'texto') {
            if (empty($nombre)) {
                $nombre = 'publicacion.txt';
            }
            if (empty($texto) && empty($url) && ! $archivo) {
                throw new NegocioException('El material de tipo texto requiere el contenido de la publicación, un archivo o un enlace.');
            }
        } else {
            if (empty($nombre)) {
                throw new NegocioException('Debe indicar el nombre del archivo.');
            }
            if (empty($url) && ! $archivo) {
                throw new NegocioException('Suba el archivo del material o indique un enlace (URL) donde se encuentre.');
            }
        }

        // Extraer y validar formato/extensión (si se subió un archivo, manda su extensión real)
        $formato = $extArchivo ?? strtolower(trim($datos['formato'] ?? pathinfo($nombre, PATHINFO_EXTENSION)));
        $formatosValidos = MaterialPublicitario::FORMATOS_VALIDOS[$tipo] ?? [];

        if (! empty($formatosValidos) && ! in_array($formato, $formatosValidos, true)) {
            throw new NegocioException("Formato «{$formato}» incompatible con tipo «{$tipo}». Permitidos: ".implode(', ', $formatosValidos));
        }

        // Validar duración obligatoria para video y audio
        if (in_array($tipo, ['video', 'audio'], true)) {
            if ($duracion === null || $duracion <= 0) {
                throw new NegocioException("El material de tipo {$tipo} requiere especificar una duración mayor a cero segundos.");
            }
        }

        return [
            'nombre_archivo' => $nombre,
            'url_archivo' => $url ?: null,
            'formato' => $formato ?: null,
            'duracion_seg' => $duracion,
            'tamano_bytes' => $archivo ? $archivo->getSize() : (isset($datos['tamano_bytes']) ? (int) $datos['tamano_bytes'] : null),
            'texto_publicacion' => $texto ?: null,
            'archivo' => $archivo,
        ];
    }

    private function validarCampaniaAbierta(Campania $campania): void
    {
        if (! in_array($campania->estado, ['activa', 'pausada'], true)) {
            throw new NegocioException("La campaña «{$campania->nombre}» se encuentra {$campania->estado}; solo se puede asociar material a campañas activas o pausadas.");
        }
    }

    private function asegurarAcceso(MaterialPublicitario $material, ?Usuario $actor): void
    {
        if (! $actor || ! $actor->esCliente()) {
            return;
        }

        $idEmpresaActor = $actor->idEmpresa();
        $idEmpresaCampania = $material->campania?->proforma?->id_empresa;

        if ($idEmpresaActor === null || (int) $idEmpresaActor !== (int) $idEmpresaCampania) {
            throw new NegocioException('No tiene acceso al material de esta empresa.', 403);
        }
    }

    private function asegurarRepresentanteEmpresa(Campania $campania, Usuario $actor): void
    {
        if (! $actor->esCliente()) {
            return;
        }

        $idEmpresaActor = $actor->idEmpresa();
        $idEmpresaCampania = $campania->proforma?->id_empresa;

        if ($idEmpresaActor === null) {
            throw new NegocioException('Para cargar material primero registre su empresa en «Mi perfil»: las proformas que hizo como visitante pasarán a su empresa.', 403);
        }
        if ((int) $idEmpresaActor !== (int) $idEmpresaCampania) {
            throw new NegocioException("Usted no es representante de la empresa que contrató la campaña «{$campania->nombre}».", 403);
        }
    }
}
