<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialRequest;
use App\Http\Requests\RevisionMaterialRequest;
use App\Models\MaterialPublicitario;
use App\Services\MaterialService;
use App\Support\ArchivoMaterial;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(private readonly MaterialService $materiales)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado(
            $this->materiales->listar($request->query(), $this->porPagina($request), $request->user())
        );
    }

    public function show(Request $request, MaterialPublicitario $material): JsonResponse
    {
        return $this->ok(
            $this->materiales->obtener($material, $request->user())
        );
    }

    public function store(MaterialRequest $request): JsonResponse
    {
        return $this->creado(
            $this->materiales->crear($request->validated(), $request->user()),
            'Material publicitario registrado correctamente.'
        );
    }

    public function update(MaterialRequest $request, MaterialPublicitario $material): JsonResponse
    {
        return $this->ok(
            $this->materiales->actualizar($material, $request->validated(), $request->user()),
            'Material publicitario modificado correctamente.'
        );
    }

    public function nuevaVersion(MaterialRequest $request, MaterialPublicitario $material): JsonResponse
    {
        return $this->ok(
            $this->materiales->subirNuevaVersion($material, $request->validated(), $request->user()),
            'Nueva versión de material cargada correctamente.'
        );
    }

    /**
     * GET /materiales/{material}/archivo?v=..&expires=..&signature=..  (enlace firmado, sin token)
     * Lo usan <video>, <audio> e <img>, que no pueden enviar el token. El enlace solo se genera para quien
     * puede ver el material y vence en 2 horas. Soporta Range para adelantar/retroceder videos.
     */
    public function archivo(MaterialPublicitario $material): BinaryFileResponse
    {
        $ruta = ArchivoMaterial::rutaAbsoluta($material->url_archivo);
        if (! $ruta) {
            abort(404, 'El archivo del material no está disponible.');
        }
        $nombre = $material->nombre_archivo ?: basename($ruta);

        return response()->file($ruta, [
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $nombre).'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, MaterialPublicitario $material): JsonResponse
    {
        return $this->ok(
            $this->materiales->retirar($material, $request->user()),
            'Material publicitario retirado correctamente.'
        );
    }

    public function revisar(RevisionMaterialRequest $request, MaterialPublicitario $material): JsonResponse
    {
        return $this->creado(
            $this->materiales->revisar($material, $request->validated(), $request->user()),
            'Revisión registrada correctamente.'
        );
    }

    public function revisiones(Request $request, MaterialPublicitario $material): JsonResponse
    {
        return $this->ok(
            $this->materiales->listarRevisiones($material, $request->user())
        );
    }
}
