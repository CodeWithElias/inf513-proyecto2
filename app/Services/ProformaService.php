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
            ->paginate($porPagina);
    }

    public function obtener(Proforma $proforma, Usuario $actor): Proforma
    {
        $this->exigirAcceso($proforma, $actor);
        $this->verificarVencimiento($proforma);

        return $proforma->load(self::RELACIONES_DETALLE);
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

        return DB::transaction(function () use ($fechaEmision, $fechaValidez, $descuento, $subtotal, $total, $idEmpresa, $idVisitante, $lineas) {
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

        return DB::transaction(function () use ($proforma, $fechaValidez, $descuento, $subtotal, $total, $lineas) {
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

        $proforma->update(['estado' => $estado]);

        return $proforma->load(self::RELACIONES_DETALLE);
    }

    public function eliminar(Proforma $proforma, Usuario $actor): void
    {
        $this->exigirAcceso($proforma, $actor);

        if ($proforma->estado === 'aprobada') {
            throw new NegocioException('No se puede eliminar una proforma aprobada.');
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
                if (! $tarifa || ! $tarifa->activo) {
                    throw new NegocioException("La tarifa #{$idTarifa} no existe o está inactiva.");
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
        Proforma::where('estado', 'pendiente')
            ->whereNotNull('fecha_validez')
            ->where('fecha_validez', '<', now()->toDateString())
            ->update(['estado' => 'vencida']);
    }

    private function verificarVencimiento(Proforma $proforma): void
    {
        if ($proforma->estado === 'pendiente'
            && $proforma->fecha_validez
            && $proforma->fecha_validez->toDateString() < now()->toDateString()) {
            $proforma->update(['estado' => 'vencida']);
        }
    }
}

