<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EjecutarMonitoreoRequest;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\PaginaRequest;
use App\Http\Requests\RevisarPaginaRequest;
use App\Http\Requests\TerminoRequest;
use App\Models\PaginaNoAutorizada;
use App\Models\TerminoMonitoreo;
use App\Services\MonitoreoService;
use App\Services\ScrapingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU6 - términos, páginas no autorizadas y ejecuciones. */
class MonitoreoController extends Controller
{
    public function __construct(private readonly MonitoreoService $monitoreo)
    {
    }

    public function terminos(Request $request): JsonResponse
    {
        return $this->paginado($this->monitoreo->listarTerminos($request->query(), $this->porPagina($request)));
    }

    public function crearTermino(TerminoRequest $request): JsonResponse
    {
        return $this->creado($this->monitoreo->crearTermino($request->validated()), 'Término registrado correctamente.');
    }

    public function estadoTermino(EstadoRequest $request, TerminoMonitoreo $termino): JsonResponse
    {
        $activo = $request->boolean('activo');

        return $this->ok($this->monitoreo->cambiarEstadoTermino($termino, $activo), $activo ? 'Término activado.' : 'Término desactivado.');
    }

    public function paginas(Request $request): JsonResponse
    {
        return $this->paginado($this->monitoreo->listarPaginas($request->query(), $this->porPagina($request)));
    }

    public function pagina(PaginaNoAutorizada $pagina): JsonResponse
    {
        return $this->ok($this->monitoreo->obtenerPagina($pagina));
    }

    public function registrarPagina(PaginaRequest $request): JsonResponse
    {
        return $this->creado($this->monitoreo->registrarPagina($request->validated()), 'Página registrada como detectada.');
    }

    public function revisarPagina(RevisarPaginaRequest $request, PaginaNoAutorizada $pagina): JsonResponse
    {
        $p = $this->monitoreo->revisarPagina($pagina, $request->validated('estado'), $request->user());

        return $this->ok($p, "La página pasó a estado «{$p->estado}».");
    }

    public function ejecuciones(Request $request): JsonResponse
    {
        return $this->paginado($this->monitoreo->listarEjecuciones($request->query(), $this->porPagina($request)));
    }

    /** POST /ejecuciones { id_plataforma, id_termino? } -> ejecuta el monitoreo ahora (ejeScr del Proyecto 1). */
    public function ejecutar(EjecutarMonitoreoRequest $request, ScrapingService $scraping): JsonResponse
    {
        $r = $scraping->ejecutar((int) $request->validated('id_plataforma'), $request->validated('id_termino') ? (int) $request->validated('id_termino') : null);

        $mensaje = $r['estado'] === 'error'
            ? "El monitoreo terminó con errores: {$r['errores']}"
            : "Monitoreo completado: {$r['cuentas_analizadas']} cuenta(s) analizada(s), {$r['nuevas']} página(s) nueva(s).";

        return $this->creado($r, $mensaje);
    }
}
