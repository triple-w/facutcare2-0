<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataFeed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $range = (string)$request->query('range', 'month');

        [$start, $end, $startPrev, $endPrev] = $this->resolveRange($range);

        $ttl = max(60, Carbon::now()->diffInSeconds(Carbon::now()->endOfDay()));
        $cacheKey = 'dashboard.kpis.' . $userId . '.' . $range . '.' . $start->format('Ymd') . '.' . $end->format('Ymd');

        $kpis = Cache::remember($cacheKey, $ttl, function () use ($userId, $start, $end, $startPrev, $endPrev) {
            $ingresosActual = $this->sumFacturasPorTipo($userId, 'I', $start, $end);
            $ingresosPrev = $this->sumFacturasPorTipo($userId, 'I', $startPrev, $endPrev);
            $ingresosTop = $this->topClienteFacturas($userId, 'I', $start, $end);

            $egresosActual = $this->sumFacturasPorTipo($userId, 'E', $start, $end);
            $egresosPrev = $this->sumFacturasPorTipo($userId, 'E', $startPrev, $endPrev);
            $egresosTop = $this->topClienteFacturas($userId, 'E', $start, $end);

            $complementosActual = $this->sumComplementosPagos($userId, $start, $end);
            $complementosPrev = $this->sumComplementosPagos($userId, $startPrev, $endPrev);
            $complementosTop = $this->topClienteComplementos($userId, $start, $end);

            return [
                'ingresos' => $this->buildKpi($ingresosActual, $ingresosPrev, $ingresosTop),
                'complementos' => $this->buildKpi($complementosActual, $complementosPrev, $complementosTop),
                'egresos' => $this->buildKpi($egresosActual, $egresosPrev, $egresosTop),
            ];
        });

        if ($request->boolean('debug')) {
            Log::info('Dashboard debug', [
                'user_id' => $userId,
                'range' => $range,
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
                'start_prev' => $startPrev->format('Y-m-d H:i:s'),
                'end_prev' => $endPrev->format('Y-m-d H:i:s'),
                'counts' => [
                    'ingresos_actual' => $this->countFacturas($userId, 'I', $start, $end),
                    'ingresos_prev' => $this->countFacturas($userId, 'I', $startPrev, $endPrev),
                    'egresos_actual' => $this->countFacturas($userId, 'E', $start, $end),
                    'egresos_prev' => $this->countFacturas($userId, 'E', $startPrev, $endPrev),
                    'complementos_actual' => $this->countComplementosPagos($userId, $start, $end),
                    'complementos_prev' => $this->countComplementosPagos($userId, $startPrev, $endPrev),
                ],
                'kpis' => $kpis,
            ]);
        }

        $dataFeed = new DataFeed();

        return view('pages/dashboard/dashboard', compact('dataFeed', 'kpis', 'range'));
    }

    /**
     * Displays the analytics screen
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function analytics()
    {
        return view('pages/dashboard/analytics');
    }

    /**
     * Displays the fintech screen
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function fintech()
    {
        return view('pages/dashboard/fintech');
    }

    private function resolveRange(string $range): array
    {
        $now = Carbon::now();

        switch ($range) {
            case '3m':
                $start = $now->copy()->subMonths(3)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case '6m':
                $start = $now->copy()->subMonths(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case '12m':
                $start = $now->copy()->subYear()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfDay();
                break;
        }

        $startPrev = $start->copy()->subYear();
        $endPrev = $end->copy()->subYear();

        return [$start, $end, $startPrev, $endPrev];
    }

    private function sumFacturasPorTipo(int $userId, string $tipo, Carbon $start, Carbon $end): float
    {
        $base = DB::table('facturas')
            ->where('users_id', $userId)
            ->whereNotIn('estatus', ['CANCELADA', 'CANCELADO']);

        $this->applyFacturasTipoFilter($base, $tipo);
        $this->applyFacturasDateFilter($base, $start, $end);

        if (Schema::hasColumn('facturas', 'total')) {
            $sum = $base->sum('total');
            return round((float)$sum, 2);
        }

        $sum = 0.0;
        foreach ($base->get(['xml']) as $row) {
            $sum += $this->parseTotalFromXml((string)($row->xml ?? ''));
        }

        return round($sum, 2);
    }

    private function sumComplementosPagos(int $userId, Carbon $start, Carbon $end): float
    {
        $sum = DB::table('complementos_pagos as cp')
            ->join('complementos as c', 'c.id', '=', 'cp.users_complementos_id')
            ->where('c.users_id', $userId)
            ->whereNotIn(DB::raw('UPPER(c.estatus)'), ['CANCELADA', 'CANCELADO'])
            ->whereBetween('cp.fecha_pago', [
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
            ])
            ->sum('cp.monto_pago');

        return round((float)$sum, 2);
    }

    private function topClienteFacturas(int $userId, string $tipo, Carbon $start, Carbon $end): array
    {
        $base = DB::table('facturas')
            ->where('users_id', $userId)
            ->whereNotIn('estatus', ['CANCELADA', 'CANCELADO']);

        $this->applyFacturasTipoFilter($base, $tipo);
        $this->applyFacturasDateFilter($base, $start, $end);

        if (Schema::hasColumn('facturas', 'total')) {
            $row = $base
                ->select([
                    DB::raw('COALESCE(razon_social, "") as nombre'),
                    DB::raw('SUM(total) as total'),
                ])
                ->groupBy('nombre')
                ->orderByDesc('total')
                ->first();

            return [
                'nombre' => (string)($row->nombre ?? ''),
                'total' => round((float)($row->total ?? 0), 2),
            ];
        }

        $totals = [];
        foreach ($base->get(['razon_social', 'xml']) as $row) {
            $nombre = (string)($row->razon_social ?? '');
            $totals[$nombre] = ($totals[$nombre] ?? 0) + $this->parseTotalFromXml((string)($row->xml ?? ''));
        }

        if (empty($totals)) {
            return ['nombre' => '', 'total' => 0.0];
        }

        arsort($totals);
        $topNombre = (string)array_key_first($totals);

        return [
            'nombre' => $topNombre,
            'total' => round((float)$totals[$topNombre], 2),
        ];
    }

    private function topClienteComplementos(int $userId, Carbon $start, Carbon $end): array
    {
        $row = DB::table('complementos_pagos as cp')
            ->join('complementos as c', 'c.id', '=', 'cp.users_complementos_id')
            ->select([
                DB::raw('COALESCE(c.razon_social, "") as nombre'),
                DB::raw('SUM(cp.monto_pago) as total'),
            ])
            ->where('c.users_id', $userId)
            ->whereNotIn(DB::raw('UPPER(c.estatus)'), ['CANCELADA', 'CANCELADO'])
            ->whereBetween('cp.fecha_pago', [
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
            ])
            ->groupBy('nombre')
            ->orderByDesc('total')
            ->first();

        return [
            'nombre' => (string)($row->nombre ?? ''),
            'total' => round((float)($row->total ?? 0), 2),
        ];
    }

    private function buildKpi(float $actual, float $previo, array $topCliente): array
    {
        $deltaPct = null;
        if ($previo > 0) {
            $deltaPct = round((($actual - $previo) / $previo) * 100, 1);
        } elseif ($actual > 0) {
            $deltaPct = 100.0;
        }

        return [
            'actual' => $actual,
            'previo' => $previo,
            'delta_pct' => $deltaPct,
            'top_cliente' => $topCliente,
        ];
    }

    private function parseTotalFromXml(string $xmlString): float
    {
        $xmlString = trim($xmlString);
        if ($xmlString === '') return 0.0;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlString, LIBXML_NONET)) {
            return 0.0;
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');

        $comp = $xp->query('//cfdi4:Comprobante | //cfdi3:Comprobante')->item(0);
        if (!$comp instanceof \DOMElement) {
            return 0.0;
        }

        $totalRaw = $comp->getAttribute('Total') ?: $comp->getAttribute('total');
        $totalRaw = str_replace([',', ' '], '', (string)$totalRaw);

        return (float)$totalRaw;
    }

    private function applyFacturasDateFilter($query, Carbon $start, Carbon $end): void
    {
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $cols = [];
        if (Schema::hasColumn('facturas', 'fecha_factura')) $cols[] = 'fecha_factura';
        if (Schema::hasColumn('facturas', 'fecha')) $cols[] = 'fecha';
        if (Schema::hasColumn('facturas', 'created_at')) $cols[] = 'created_at';

        if (empty($cols)) {
            return;
        }

        $coalesce = 'COALESCE(' . implode(', ', $cols) . ')';
        $query->whereBetween(DB::raw($coalesce), [$startStr, $endStr]);
    }

    private function applyFacturasTipoFilter($query, string $tipo): void
    {
        $tipo = strtoupper(trim($tipo));
        $values = [$tipo];
        if ($tipo === 'I') {
            $values[] = 'INGRESO';
            $values[] = 'INGRESOS';
        } elseif ($tipo === 'E') {
            $values[] = 'EGRESO';
            $values[] = 'EGRESOS';
        }

        $query->whereRaw('UPPER(tipo_comprobante) IN ('.implode(',', array_fill(0, count($values), '?')).')', $values);
    }

    private function countFacturas(int $userId, string $tipo, Carbon $start, Carbon $end): int
    {
        $q = DB::table('facturas')
            ->where('users_id', $userId)
            ->whereNotIn('estatus', ['CANCELADA', 'CANCELADO']);

        $this->applyFacturasTipoFilter($q, $tipo);
        $this->applyFacturasDateFilter($q, $start, $end);

        return (int)$q->count();
    }

    private function countComplementosPagos(int $userId, Carbon $start, Carbon $end): int
    {
        $q = DB::table('complementos_pagos as cp')
            ->join('complementos as c', 'c.id', '=', 'cp.users_complementos_id')
            ->where('c.users_id', $userId)
            ->whereNotIn('c.estatus', ['CANCELADA', 'CANCELADO'])
            ->whereBetween('cp.fecha_pago', [
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
            ]);

        return (int)$q->count();
    }
}
