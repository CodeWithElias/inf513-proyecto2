<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\DetalleProforma;
use App\Models\EmpresaAnunciante;
use App\Models\Paquete;
use App\Models\Proforma;
use App\Models\ServicioPublicitario;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\Visitante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * CU3 - Proformas (portado de NProforma):
 * cálculo de subtotal, descuento y total; correlativo PRO-AAAA-NNNNN;
 * control de vigencia/vencimiento; aprobación/rechazo; y cotización pública de visitantes.
 */
class ProformaService
{
    public function __construct(
        private readonly AvisoService $avisos,
        private readonly CampaniaService $campanias,
    ) {
    }

    private const RELACIONES_LISTA = [
        'empresa:id_empresa,razon_social,nit',
        'visitante:id_visitante,nombre,email,telefono',
        'campania:id_campania,id_proforma,nombre,estado',
    ];

    private const RELACIONES_DETALLE = [
        'empresa:id_empresa,razon_social,nit,nombre_comercial,email_facturacion,telefono',
        'visitante:id_visitante,nombre,email,telefono',
        'detalles.tarifa:id_tarifa,monto,unidad_cobro,duracion_seg,id_servicio',
        'detalles.tarifa.servicio:id_servicio,nombre,tipo',
        'detalles.paquete:id_paquete,nombre,descuento,precio_total',
        'campania:id_campania,id_proforma,nombre,fecha_inicio,fecha_fin,estado',
        'planesPago.cuotas',
        'transacciones.metodo:id_metodo,tipo,marca,ultimos4',
    ];

    public function listar(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        $this->marcarVencidas();

        return Proforma::query()
            ->with(self::RELACIONES_LISTA)
            ->withCount('detalles')
            ->when($actor->esCliente(), fn ($q) => $actor->filtrarProformas($q))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nro_proforma', 'ilike', "%{$v}%")
                ->orWhereHas('empresa', fn ($e) => $e->where('razon_social', 'ilike', "%{$v}%"))
                ->orWhereHas('visitante', fn ($vis) => $vis->where('nombre', 'ilike', "%{$v}%"))))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['id_empresa'] ?? null, fn ($q, $v) => $q->where('id_empresa', $v))
            ->orderByDesc('id_proforma')
            ->paginate($porPagina)
            ->through(fn (Proforma $p) => $this->conSituacionPago($p));
    }

    public function obtener(Proforma $proforma, Usuario $actor): Proforma
    {
        $this->exigirAcceso($proforma, $actor);
        $this->verificarVencimiento($proforma);

        return $this->conSituacionPago($proforma->load(self::RELACIONES_DETALLE));
    }

    /** Las aprobadas llevan su situación de pago (paso previo a programar pautas). */
    private function conSituacionPago(Proforma $p): Proforma
    {
        return $p->setAttribute('situacion_pago', $p->estado === 'aprobada' ? $p->situacionPago() : null);
    }

    public function crear(array $d, Usuario $actor): Proforma
    {
        $idEmpresa = $this->resolverEmpresa($d, $actor);
        $idVisitante = $actor->esCliente() ? null : ($d['id_visitante'] ?? null);

        if (! $idEmpresa && ! $idVisitante) {
            throw new NegocioException('Debe indicar una empresa anunciante o un visitante para la proforma.');
        }
        if ($idEmpresa && $idVisitante) {
            throw new NegocioException('Una proforma debe pertenecer a una empresa o a un visitante, no a ambos.');
        }

        $descuento = round((float) ($d['descuento'] ?? 0), 2);
        if ($descuento > 0 && ! $actor->tienePrivilegio('proformas.descuento')) {
            throw new NegocioException('No tiene permiso para aplicar descuentos a proformas.', 403);
        }

        $fechaEmision = now()->toDateString();
        $fechaValidez = $d['fecha_validez'] ?? now()->addDays(15)->toDateString();
        if ($fechaValidez < $fechaEmision) {
            throw new NegocioException('La fecha de validez no puede ser anterior a la fecha de emisión.');
        }

        $lineas = $this->prepararDetalles($d['detalles']);
        $subtotal = round(array_sum(array_column($lineas, 'subtotal')), 2);
        $this->validarDescuento($descuento, $subtotal);
        $total = round($subtotal - $descuento, 2);

        return DB::transaction(function () use ($fechaEmision, $fechaValidez, $descuento, $subtotal, $total, $idEmpresa, $idVisitante, $lineas, $actor) {
            $proforma = Proforma::create([
                'nro_proforma' => $this->generarNumero(),
                'fecha_emision' => $fechaEmision,
                'fecha_validez' => $fechaValidez,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
                'estado' => 'pendiente',
                'id_empresa' => $idEmpresa,
                'id_visitante' => $idVisitante,
            ]);

            foreach ($lineas as $linea) {
                DetalleProforma::create([...$linea, 'id_proforma' => $proforma->id_proforma]);
            }

            // Siguiente paso: la otra parte revisa y aprueba la proforma
            $monto = number_format($total, 2, ',', '.');
            if ($actor->esCliente()) {
                $this->avisos->alPersonal('proformas.aprobar', "Nueva proforma {$proforma->nro_proforma} por revisar",
                    "Un cliente generó la proforma {$proforma->nro_proforma} por Bs {$monto}. Revísela y apruébela o recházela.", $actor);
            } else {
                $this->avisos->alCliente($proforma, "Tiene una proforma nueva: {$proforma->nro_proforma}",
                    "Canal 11 le preparó la proforma {$proforma->nro_proforma} por Bs {$monto}. Revísela en «Proformas» y apruébela para continuar.", $actor);
            }

            return $proforma->load(self::RELACIONES_DETALLE);
        });
    }

    public function actualizar(Proforma $proforma, array $d, Usuario $actor): Proforma
    {
        $this->exigirAcceso($proforma, $actor);
        $this->verificarVencimiento($proforma);

        if ($proforma->estado !== 'pendiente') {
            throw new NegocioException("Solo se pueden modificar proformas en estado «pendiente» (estado actual: {$proforma->estado}).");
        }

        $descuento = array_key_exists('descuento', $d) ? round((float) $d['descuento'], 2) : $proforma->descuento;
        if ($descuento !== (float) $proforma->descuento && ! $actor->tienePrivilegio('proformas.descuento')) {
            throw new NegocioException('No tiene permiso para aplicar o modificar descuentos en proformas.', 403);
        }

        $fechaEmision = $proforma->fecha_emision->toDateString();
        $fechaValidez = $d['fecha_validez'] ?? $proforma->fecha_validez?->toDateString();
        if ($fechaValidez && $fechaValidez < $fechaEmision) {
            throw new NegocioException('La fecha de validez no puede ser anterior a la fecha de emisión.');
        }

        $lineas = $this->prepararDetalles($d['detalles']);
        $subtotal = round(array_sum(array_column($lineas, 'subtotal')), 2);
        $this->validarDescuento($descuento, $subtotal);
        $total = round($subtotal - $descuento, 2);

        return DB::transaction(function () use ($proforma, $fechaValidez, $descuento, $subtotal, $total, $lineas, $actor) {
            $proforma->update([
                'fecha_validez' => $fechaValidez,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
            ]);

            $proforma->detalles()->delete();
            foreach ($lineas as $linea) {
                DetalleProforma::create([...$linea, 'id_proforma' => $proforma->id_proforma]);
            }

            // La otra parte se entera del cambio antes de aprobar
            $monto = number_format($total, 2, ',', '.');
            $mensaje = "La proforma {$proforma->nro_proforma} fue modificada; nuevo total Bs {$monto}. Revísela antes de aprobarla.";
            $actor->esCliente()
                ? $this->avisos->alPersonal('proformas.aprobar', "Proforma {$proforma->nro_proforma} modificada", $mensaje, $actor)
                : $this->avisos->alCliente($proforma, "Proforma {$proforma->nro_proforma} modificada", $mensaje, $actor);

            return $proforma->load(self::RELACIONES_DETALLE);
        });
    }

    public function cambiarEstado(Proforma $proforma, string $estado, Usuario $actor): Proforma
    {
        $this->exigirAcceso($proforma, $actor);
        $this->verificarVencimiento($proforma);

        if ($proforma->estado === 'vencida') {
            throw new NegocioException("La proforma {$proforma->nro_proforma} está vencida y no puede ser {$estado}.");
        }
        if ($proforma->estado !== 'pendiente') {
            throw new NegocioException("La proforma {$proforma->nro_proforma} ya se encuentra en estado «{$proforma->estado}».");
        }
        if (! in_array($estado, ['aprobada', 'rechazada'], true)) {
            throw new NegocioException('Solo puede aprobar o rechazar una proforma pendiente.');
        }
        if ($estado === 'aprobada') {
            throw new NegocioException("La proforma {$proforma->nro_proforma} se aprueba completando la contratación: datos de la campaña, material de cada servicio, días y horarios, y pago. Use «Contratar».");
        }

        DB::transaction(function () use ($proforma, $estado, $actor) {
            $proforma->update(['estado' => $estado]);

            // Solo llega aquí el rechazo: se suelta lo que la contratación hubiera apartado
            $this->liberarContratacion([$proforma->id_proforma]);
            $this->avisarCambioEstado($proforma, $estado, $actor, null);
        });

        return $this->conSituacionPago($proforma->load(self::RELACIONES_DETALLE));
    }

    /** Último paso de la contratación guiada (ContratacionService::confirmar), dentro de su transacción. */
    public function aprobarPorContratacion(Proforma $proforma): void
    {
        $proforma->update(['estado' => 'aprobada']);
        $this->campanias->crearDesdeProforma($proforma); // por si no tuviera (no debería)
    }

    /**
     * Proforma rechazada o vencida con la contratación a medias: se sueltan los horarios apartados
     * y la campaña en preparación se cancela (el material queda como historial).
     */
    private function liberarContratacion(array $idsProforma): void
    {
        if (! $idsProforma) {
            return;
        }
        DB::table('contratacion_espacio')->whereNull('id_programacion')
            ->whereIn('id_linea', DB::table('contratacion_servicio')->whereIn('id_proforma', $idsProforma)->select('id_linea'))
            ->delete();
        DB::table('campania')->whereIn('id_proforma', $idsProforma)->whereIn('estado', ['activa', 'pausada'])
            ->update(['estado' => 'cancelada']);
    }

    /** Avisa a la otra parte (cliente o personal) qué pasó y cuál es el siguiente paso. */
    private function avisarCambioEstado(Proforma $proforma, string $estado, Usuario $actor, ?\App\Models\Campania $campania): void
    {
        $nro = $proforma->nro_proforma;
        if ($estado === 'aprobada') {
            $siguiente = $campania ? "Se abrió la campaña «{$campania->nombre}». " : '';
            $this->avisos->alCliente($proforma, "Proforma {$nro} aprobada",
                $siguiente.'Siguientes pasos: 1) pague la proforma en «Pagos» (al contado o en cuotas) y 2) cargue el material publicitario de la campaña para su revisión.', $actor);
            $this->avisos->alPersonal('campanias.editar', "Proforma {$nro} aprobada",
                $siguiente.'Revise el nombre y las fechas de la campaña. Las pautas se podrán programar cuando la proforma esté pagada y el material aprobado.', $actor);

            return;
        }
        $this->avisos->alCliente($proforma, "Proforma {$nro} rechazada",
            'La proforma fue rechazada. Si necesita otra cotización, genere una nueva desde «Proformas» o desde la portada.', $actor);
        if ($actor->esCliente()) {
            $this->avisos->alPersonal('proformas.aprobar', "El cliente rechazó la proforma {$nro}", 'El cliente no aceptó la cotización.', $actor);
        }
    }

    public function eliminar(Proforma $proforma, Usuario $actor): void
    {
        $this->exigirAcceso($proforma, $actor);

        // Trazabilidad: las rechazadas y vencidas quedan como historial (en el Proyecto 1 no se borraban)
        if ($proforma->estado !== 'pendiente') {
            throw new NegocioException("Solo se puede eliminar una proforma pendiente; la {$proforma->nro_proforma} está «{$proforma->estado}» y se conserva como historial.");
        }
        if ($proforma->campania()->exists()) {
            throw new NegocioException('No se puede eliminar la proforma porque tiene una campaña asociada.');
        }
        if ($proforma->planesPago()->exists() || $proforma->transacciones()->exists()) {
            throw new NegocioException('No se puede eliminar la proforma porque tiene pagos o planes asociados.');
        }

        $proforma->delete();
    }

    /** B3 - Catálogo público de servicios con tarifas vigentes y paquetes activos. */
    public function catalogoPublico(): array
    {
        $hoy = now()->toDateString();

        $servicios = ServicioPublicitario::query()
            ->where('activo', true)
            ->with(['tarifas' => fn ($q) => $q
                ->where('activo', true)
                ->where(fn ($w) => $w->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $hoy))
                ->where(fn ($w) => $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy))
                ->orderBy('monto')])
            ->orderBy('nombre')
            ->get();

        $paquetes = Paquete::query()
            ->where('activo', true)
            ->with([
                'items.tarifa:id_tarifa,monto,unidad_cobro,duracion_seg,id_servicio',
                'items.tarifa.servicio:id_servicio,nombre,tipo',
            ])
            ->orderBy('nombre')
            ->get();

        return [
            'servicios' => $servicios,
            'paquetes' => $paquetes,
        ];
    }

    /** B3 - Cotización pública sin login: registra visitante y genera proforma pendiente. */
    public function cotizarPublica(array $d): Proforma
    {
        $lineas = $this->prepararDetalles($d['detalles']);
        $subtotal = round(array_sum(array_column($lineas, 'subtotal')), 2);
        $fechaEmision = now()->toDateString();
        $fechaValidez = now()->addDays(15)->toDateString();

        // Visitante → Cliente: validar el correo ANTES de crear la proforma
        $quiereCuenta = filter_var($d['crear_cuenta'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $solicitudes = app(SolicitudCuentaService::class);
        if ($quiereCuenta) {
            $solicitudes->validarCorreoDisponible($d['email']);
        }

        return DB::transaction(function () use ($d, $lineas, $subtotal, $fechaEmision, $fechaValidez, $quiereCuenta, $solicitudes) {
            $email = isset($d['email']) ? mb_strtolower($d['email']) : null;
            $visitante = $email ? Visitante::whereRaw('lower(email) = lower(?)', [$email])->first() : null;

            if ($visitante) {
                $visitante->update([
                    'nombre' => $d['nombre'],
                    'telefono' => $d['telefono'] ?? $visitante->telefono,
                ]);
            } else {
                $visitante = Visitante::create([
                    'nombre' => $d['nombre'],
                    'email' => $email,
                    'telefono' => $d['telefono'] ?? null,
                ]);
            }

            $proforma = Proforma::create([
                'nro_proforma' => $this->generarNumero(),
                'fecha_emision' => $fechaEmision,
                'fecha_validez' => $fechaValidez,
                'subtotal' => $subtotal,
                'descuento' => 0,
                'total' => $subtotal,
                'estado' => 'pendiente',
                'id_visitante' => $visitante->id_visitante,
            ]);

            foreach ($lineas as $linea) {
                DetalleProforma::create([...$linea, 'id_proforma' => $proforma->id_proforma]);
            }

            $proforma->load(self::RELACIONES_DETALLE);
            $monto = number_format($subtotal, 2, ',', '.');
            $this->avisos->alPersonal('proformas.aprobar', "Cotización en línea {$proforma->nro_proforma}",
                "El visitante {$visitante->nombre} cotizó por Bs {$monto} desde la portada. Revise la proforma y apruébela o recházela.");
            if ($quiereCuenta) {
                $proforma->setAttribute('solicitud_cuenta', $solicitudes->registrar($visitante, $d['password']));
            }

            return $proforma;
        });
    }

    public function exigirAcceso(Proforma $proforma, Usuario $actor): void
    {
        if ($actor->esCliente() && ! $actor->esSuProforma($proforma)) {
            throw new NegocioException('No tiene acceso a esta proforma: no es suya ni de su empresa.', 403);
        }
    }

    private function resolverEmpresa(array $d, Usuario $actor): ?int
    {
        if ($actor->esCliente()) {
            $id = $actor->idEmpresa();
            if (! $id) {
                throw new NegocioException('Para cotizar desde su cuenta primero registre su empresa en «Mi perfil». Mientras tanto puede seguir viendo las proformas que generó como visitante.');
            }

            return $id;
        }

        if (! empty($d['id_empresa'])) {
            $empresa = EmpresaAnunciante::find($d['id_empresa']);
            if (! $empresa) {
                throw new NegocioException('La empresa anunciante seleccionada no existe.');
            }
            if (! $empresa->activo) {
                throw new NegocioException("La empresa {$empresa->razon_social} está inactiva.");
            }

            return $empresa->id_empresa;
        }

        return null;
    }

    private function prepararDetalles(array $detalles): array
    {
        if (count($detalles) === 0) {
            throw new NegocioException('La proforma debe tener al menos un ítem (tarifa o paquete).');
        }

        $hoy = now()->toDateString();
        $lineas = [];

        foreach ($detalles as $item) {
            $idTarifa = $item['id_tarifa'] ?? null;
            $idPaquete = $item['id_paquete'] ?? null;
            $cantidad = (int) ($item['cantidad'] ?? 1);

            if (($idTarifa && $idPaquete) || (! $idTarifa && ! $idPaquete)) {
                throw new NegocioException('Cada ítem de la proforma debe elegir una tarifa o un paquete (no ambos).');
            }
            if ($cantidad < 1) {
                throw new NegocioException('La cantidad de cada ítem debe ser al menos 1.');
            }

            if ($idTarifa) {
                $tarifa = Tarifa::with('servicio')->find($idTarifa);
                if (! $tarifa || ! $tarifa->activo || ! $tarifa->servicio?->activo) {
                    throw new NegocioException("La tarifa #{$idTarifa} no existe o está inactiva (o su servicio fue dado de baja).");
                }
                if (($tarifa->vigente_desde && $tarifa->vigente_desde->toDateString() > $hoy)
                    || ($tarifa->vigente_hasta && $tarifa->vigente_hasta->toDateString() < $hoy)) {
                    throw new NegocioException("La tarifa #{$idTarifa} no se encuentra vigente actualmente.");
                }
                $precioUnitario = $tarifa->monto;
            } else {
                $paquete = Paquete::find($idPaquete);
                if (! $paquete || ! $paquete->activo) {
                    throw new NegocioException("El paquete #{$idPaquete} no existe o está inactivo.");
                }
                $precioUnitario = $paquete->precio_total;
            }

            $lineas[] = [
                'cantidad' => $cantidad,
                'precio_unitario' => round((float) $precioUnitario, 2),
                'subtotal' => round((float) $precioUnitario * $cantidad, 2),
                'frecuencia' => $item['frecuencia'] ?? null,
                'id_tarifa' => $idTarifa ? (int) $idTarifa : null,
                'id_paquete' => $idPaquete ? (int) $idPaquete : null,
            ];
        }

        return $lineas;
    }

    private function validarDescuento(float $descuento, float $subtotal): void
    {
        if ($descuento < 0) {
            throw new NegocioException('El descuento no puede ser negativo.');
        }
        if ($descuento > $subtotal) {
            throw new NegocioException('El descuento no puede superar el subtotal de la proforma.');
        }
    }

    private function generarNumero(): string
    {
        $anio = now()->format('Y');
        $prefijo = "PRO-{$anio}-";
        $ultimo = Proforma::where('nro_proforma', 'like', "{$prefijo}%")
            ->orderByDesc('nro_proforma')
            ->value('nro_proforma');

        $correlativo = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        return sprintf('%s%05d', $prefijo, $correlativo);
    }

    private function marcarVencidas(): void
    {
        $vencidas = Proforma::where('estado', 'pendiente')
            ->whereNotNull('fecha_validez')
            ->where('fecha_validez', '<', now()->toDateString())
            ->pluck('id_proforma')->all();
        if ($vencidas) {
            Proforma::whereIn('id_proforma', $vencidas)->update(['estado' => 'vencida']);
            $this->liberarContratacion($vencidas);
        }
    }

    private function verificarVencimiento(Proforma $proforma): void
    {
        if ($proforma->estado === 'pendiente'
            && $proforma->fecha_validez
            && $proforma->fecha_validez->toDateString() < now()->toDateString()) {
            $proforma->update(['estado' => 'vencida']);
            $this->liberarContratacion([$proforma->id_proforma]);
        }
    }
}

