<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BitacoraService
{
    /** Registra un evento. Nunca interrumpe la petición si la bitácora falla. */
    public function registrar(Request $request, string $evento, ?Usuario $usuario = null, array $extra = []): void
    {
        try {
            Bitacora::create(array_merge([
                'id_usuario' => $usuario?->id_usuario,
                'email' => $usuario?->email,
                'evento' => $evento,
                'metodo' => $request->method(),
                'ruta' => Str::limit($request->fullUrl(), 495, ''),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 295, ''),
            ], $extra));
        } catch (Throwable $e) {
            Log::error('No se pudo registrar la bitácora: '.$e->getMessage());
        }
    }

    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return Bitacora::query()
            ->with('usuario:id_usuario,nombre_completo')
            ->when($f['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->when($f['ip'] ?? null, fn ($q, $v) => $q->where('ip', 'ilike', "%{$v}%"))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('email', 'ilike', "%{$v}%")
                ->orWhere('recurso', 'ilike', "%{$v}%")))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->orderByDesc('id_bitacora')
            ->paginate($porPagina);
    }

    /** Métricas de acceso para la pantalla de bitácora y el dashboard. */
    public function resumen(int $dias = 14): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();

        $porEvento = Bitacora::where('fecha', '>=', $desde)
            ->select('evento', DB::raw('count(*) as total'))
            ->groupBy('evento')
            ->pluck('total', 'evento');

        $porDia = Bitacora::where('fecha', '>=', $desde)
            ->selectRaw("to_char(fecha, 'YYYY-MM-DD') as dia")
            ->selectRaw("count(*) filter (where evento = 'acceso') as accesos")
            ->selectRaw("count(*) filter (where evento = 'login_ok') as logins_ok")
            ->selectRaw("count(*) filter (where evento = 'login_fallido') as logins_fallidos")
            ->selectRaw("count(*) filter (where evento = 'denegado') as denegados")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        // Serie completa (días sin actividad en 0) para los gráficos.
        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $k = $desde->copy()->addDays($i)->format('Y-m-d');
            $fila = $porDia->get($k);
            $serie[] = [
                'dia' => $k,
                'accesos' => (int) ($fila->accesos ?? 0),
                'logins_ok' => (int) ($fila->logins_ok ?? 0),
                'logins_fallidos' => (int) ($fila->logins_fallidos ?? 0),
                'denegados' => (int) ($fila->denegados ?? 0),
            ];
        }

        return [
            'dias' => $dias,
            'totales' => [
                'accesos' => (int) ($porEvento['acceso'] ?? 0),
                'logins_ok' => (int) ($porEvento['login_ok'] ?? 0),
                'logins_fallidos' => (int) ($porEvento['login_fallido'] ?? 0),
                'denegados' => (int) ($porEvento['denegado'] ?? 0),
                'errores' => (int) ($porEvento['error'] ?? 0),
            ],
            'por_dia' => $serie,
            'top_recursos' => DB::table('contador_recurso')
                ->orderByDesc('total')->limit(10)
                ->get(['recurso', 'total', 'ultimo_acceso']),
            'top_ips' => Bitacora::where('fecha', '>=', $desde)
                ->select('ip', DB::raw('count(*) as total'),
                    DB::raw("count(*) filter (where evento = 'login_fallido') as fallidos"))
                ->groupBy('ip')->orderByDesc('total')->limit(10)->get(),
            'ultimos_fallidos' => Bitacora::where('evento', Bitacora::LOGIN_FALLIDO)
                ->orderByDesc('id_bitacora')->limit(5)
                ->get(['email', 'ip', 'detalle', 'fecha']),
        ];
    }
}
