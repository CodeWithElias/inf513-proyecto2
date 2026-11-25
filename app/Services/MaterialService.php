<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Campania;
use App\Models\MaterialPublicitario;
use App\Models\RevisionMaterial;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * C1 / CU4: Gestión de Material Publicitario y Revisiones.
 * Portado desde NMaterial.java (Proyecto 1).
 */
class MaterialService
{
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

        return MaterialPublicitario::create([
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
    }

    /** Corrige un material mientras aún esté en estado pendiente. */
    public function actualizar(MaterialPublicitario $material, array $datos, Usuario $actor): MaterialPublicitario
    {
        $this->asegurarAcceso($material, $actor);

        if (! $material->estaPendiente()) {
            throw new NegocioException("El material {$material->id_material} está «{$material->estado}»; solo se puede modificar mientras está pendiente de revisión.");
        }

        $tipo = ! empty($datos['tipo']) ? $this->validarTipo($datos['tipo']) : $material->tipo;
        $contenido = $this->validarContenido($tipo, $datos);

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

        $contenido = $this->validarContenido($material->tipo, $datos);

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
            ->whereIn('estado', ['programada', 'emitida'])
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

    // --- Helpers de validación interna ---

    private function validarTipo(string $tipo): string
    {
        $t = strtolower(trim($tipo));
        if (! in_array($t, MaterialPublicitario::TIPOS, true)) {
            throw new NegocioException("Tipo «{$tipo}» no válido. Opciones: ".implode(', ', MaterialPublicitario::TIPOS));
        }

        return $t;
    }

    private function validarContenido(string $tipo, array $datos): array
    {
        $nombre = trim($datos['nombre_archivo'] ?? '');
        $url = trim($datos['url_archivo'] ?? '');
        $duracion = isset($datos['duracion_seg']) ? (int) $datos['duracion_seg'] : null;
        $texto = trim($datos['texto_publicacion'] ?? '');

        if ($tipo === 'texto') {
            if (empty($nombre)) {
                $nombre = 'publicacion.txt';
            }
            if (empty($texto) && empty($url)) {
                throw new NegocioException('El material de tipo texto requiere el contenido de la publicación o un archivo/enlace.');
            }
        } else {
            if (empty($nombre)) {
                throw new NegocioException('Debe indicar el nombre del archivo.');
            }
            if (empty($url)) {
                throw new NegocioException('Debe indicar la URL o ruta del archivo.');
            }
        }

        // Extraer y validar formato/extensión
        $formato = strtolower(trim($datos['formato'] ?? pathinfo($nombre, PATHINFO_EXTENSION)));
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
            'tamano_bytes' => isset($datos['tamano_bytes']) ? (int) $datos['tamano_bytes'] : null,
            'texto_publicacion' => $texto ?: null,
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

        if ($idEmpresaActor === null || (int) $idEmpresaActor !== (int) $idEmpresaCampania) {
            throw new NegocioException("Usted no es representante de la empresa que contrató la campaña «{$campania->nombre}».", 403);
        }
    }
}
