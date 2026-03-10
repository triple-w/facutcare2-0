<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) auth()->id();
        $range = (string) $request->query('range', 'month');

        [$start, $end, $startPrev, $endPrev, $bucket] = $this->resolveRange($range);

        $ttl = max(60, Carbon::now()->diffInSeconds(Carbon::now()->endOfDay()));
        $cacheKey = implode('.', [
            'dashboard.v2',
            $userId,
            $range,
            $start->format('Ymd'),
            $end->format('Ymd'),
        ]);

        $dashboard = Cache::remember($cacheKey, $ttl, function () use ($userId, $start, $end, $startPrev, $endPrev, $bucket) {
            $kpis = [
                'ingresos' => $this->buildFacturaKpi($userId, 'I', $start, $end, $startPrev, $endPrev, $bucket),
                'complementos' => $this->buildComplementosKpi($userId, $start, $end, $startPrev, $endPrev, $bucket),
                'egresos' => $this->buildFacturaKpi($userId, 'E', $start, $end, $startPrev, $endPrev, $bucket),
            ];

            return [
                'kpis' => $kpis,
                'documentCards' => $this->buildDocumentCards($userId, $start, $end),
                'monthlyChart' => $this->buildMonthlyChart($userId, $start, $end),
            ];
        });

        if ($request->boolean('debug')) {
            Log::info('Dashboard debug', [
                'user_id' => $userId,
                'range' => $range,
                'dashboard' => $dashboard,
            ]);
        }

        return view('pages/dashboard/dashboard', [
            'kpis' => $dashboard['kpis'],
            'documentCards' => $dashboard['documentCards'],
            'monthlyChart' => $dashboard['monthlyChart'],
            'range' => $range,
        ]);
    }

    public function analytics()
    {
        return view('pages/dashboard/analytics');
    }

    public function fintech()
    {
        return view('pages/dashboard/fintech');
    }

    private function resolveRange(string $range): array
    {
        $now = Carbon::now();

        switch ($range) {
            case '3m':
                $start = $now->copy()->subMonths(2)->startOfMonth();
                $end = $now->copy()->endOfDay();
                $bucket = 'month';
                break;
            case '6m':
                $start = $now->copy()->subMonths(5)->startOfMonth();
                $end = $now->copy()->endOfDay();
                $bucket = 'month';
                break;
            case '12m':
                $start = $now->copy()->subMonths(11)->startOfMonth();
                $end = $now->copy()->endOfDay();
                $bucket = 'month';
                break;
            case 'month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfDay();
                $bucket = 'day';
                break;
        }

        $startPrev = $start->copy()->subYear();
        $endPrev = $end->copy()->subYear();

        return [$start, $end, $startPrev, $endPrev, $bucket];
    }

    private function buildFacturaKpi(int $userId, string $tipo, Carbon $start, Carbon $end, Carbon $startPrev, Carbon $endPrev, string $bucket): array
    {
        $actual = $this->sumFacturasPorTipo($userId, $tipo, $start, $end, false);
        $previo = $this->sumFacturasPorTipo($userId, $tipo, $startPrev, $endPrev, false);
        $series = $this->buildSeries(function (Carbon $from, Carbon $to) use ($userId, $tipo) {
            return $this->sumFacturasPorTipo($userId, $tipo, $from, $to, false);
        }, $start, $end, $startPrev, $endPrev, $bucket);

        return $this->buildKpi($actual, $previo, $this->topClienteFacturas($userId, $tipo, $start, $end), $series);
    }

    private function buildComplementosKpi(int $userId, Carbon $start, Carbon $end, Carbon $startPrev, Carbon $endPrev, string $bucket): array
    {
        $actual = $this->sumComplementosPagos($userId, $start, $end, false);
        $previo = $this->sumComplementosPagos($userId, $startPrev, $endPrev, false);
        $series = $this->buildSeries(function (Carbon $from, Carbon $to) use ($userId) {
            return $this->sumComplementosPagos($userId, $from, $to, false);
        }, $start, $end, $startPrev, $endPrev, $bucket);

        return $this->buildKpi($actual, $previo, $this->topClienteComplementos($userId, $start, $end), $series);
    }

    private function buildDocumentCards(int $userId, Carbon $start, Carbon $end): array
    {
        $facturas = [
            'title' => 'Facturas',
            'count' => $this->countFacturas($userId, 'I', $start, $end, false),
            'amount' => $this->sumFacturasPorTipo($userId, 'I', $start, $end, false),
            'tone' => 'violet',
        ];

        $complementos = [
            'title' => 'Complementos',
            'count' => $this->countComplementos($userId, $start, $end, false),
            'amount' => $this->sumComplementosPagos($userId, $start, $end, false),
            'tone' => 'sky',
        ];

        $notas = [
            'title' => 'Notas de crédito',
            'count' => $this->countFacturas($userId, 'E', $start, $end, false),
            'amount' => $this->sumFacturasPorTipo($userId, 'E', $start, $end, false),
            'tone' => 'amber',
        ];

        $canceladasFacturas = $this->countFacturas($userId, null, $start, $end, true);
        $canceladasComplementos = $this->countComplementos($userId, $start, $end, true);

        $canceladas = [
            'title' => 'Canceladas',
            'count' => $canceladasFacturas + $canceladasComplementos,
            'amount' => $this->sumFacturasPorTipo($userId, null, $start, $end, true) + $this->sumComplementosPagos($userId, $start, $end, true),
            'tone' => 'red',
        ];

        return [$facturas, $complementos, $notas, $canceladas];
    }

    private function buildMonthlyChart(int $userId, Carbon $start, Carbon $end): array
    {
        $labels = [];
        $facturas = [];
        $complementos = [];
        $notas = [];

        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor <= $last) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            if ($bucketEnd->greaterThan($end)) {
                $bucketEnd = $end->copy();
            }

            $labels[] = $bucketStart->locale('es')->translatedFormat('M y');
            $facturas[] = $this->sumFacturasPorTipo($userId, 'I', $bucketStart, $bucketEnd, false);
            $complementos[] = $this->sumComplementosPagos($userId, $bucketStart, $bucketEnd, false);
            $notas[] = $this->sumFacturasPorTipo($userId, 'E', $bucketStart, $bucketEnd, false);

            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'facturas' => $facturas,
            'complementos' => $complementos,
            'notas_credito' => $notas,
        ];
    }

    private function buildSeries(callable $resolver, Carbon $start, Carbon $end, Carbon $startPrev, Carbon $endPrev, string $bucket): array
    {
        $labels = [];
        $actual = [];
        $previo = [];

        if ($bucket === 'day') {
            $current = $start->copy()->startOfDay();
            $currentPrev = $startPrev->copy()->startOfDay();

            while ($current <= $end) {
                $labels[] = $current->format('d M');
                $actual[] = $resolver($current->copy()->startOfDay(), $current->copy()->endOfDay());
                $previo[] = $resolver($currentPrev->copy()->startOfDay(), $currentPrev->copy()->endOfDay());
                $current->addDay();
                $currentPrev->addDay();
            }
        } else {
            $current = $start->copy()->startOfMonth();
            $currentPrev = $startPrev->copy()->startOfMonth();

            while ($current <= $end) {
                $labels[] = $current->locale('es')->translatedFormat('M y');
                $actualEnd = $current->copy()->endOfMonth()->min($end);
                $prevEnd = $currentPrev->copy()->endOfMonth()->min($endPrev);
                $actual[] = $resolver($current->copy()->startOfMonth(), $actualEnd);
                $previo[] = $resolver($currentPrev->copy()->startOfMonth(), $prevEnd);
                $current->addMonth();
                $currentPrev->addMonth();
            }
        }

        return [
            'labels' => $labels,
            'actual' => $actual,
            'previo' => $previo,
        ];
    }

    private function sumFacturasPorTipo(int $userId, ?string $tipo, Carbon $start, Carbon $end, bool $canceladas): float
    {
        $base = DB::table('facturas')->where('users_id', $userId);
        $this->applyFacturasStatusFilter($base, $canceladas);
        $this->applyFacturasDateFilter($base, $start, $end);

        if ($tipo !== null) {
            $this->applyFacturasTipoFilter($base, $tipo);
        }

        $sum = 0.0;
        foreach ($base->get($this->facturasSelectColumns()) as $row) {
            $total = $this->extractFacturaTotal($row);
            $sum += $total;
        }

        return round($sum, 2);
    }

    private function facturasSelectColumns(): array
    {
        $columns = ['xml'];
        if (Schema::hasColumn('facturas', 'total')) {
            array_unshift($columns, 'total');
        }

        return $columns;
    }

    private function extractFacturaTotal(object $row): float
    {
        $total = property_exists($row, 'total') ? (float) ($row->total ?? 0) : 0.0;
        if ($total <= 0) {
            $total = $this->parseTotalFromXml((string) ($row->xml ?? ''));
        }

        return $total;
    }

    private function sumComplementosPagos(int $userId, Carbon $start, Carbon $end, bool $canceladas): float
    {
        $complementos = DB::table('complementos as c')
            ->where('c.users_id', $userId);

        $this->applyComplementosStatusFilter($complementos, $canceladas);
        $this->applyComplementosDateFilter($complementos, $start, $end);

        $sum = 0.0;
        foreach ($complementos->get(['c.id', 'c.xml']) as $row) {
            $cpSum = 0.0;
            if (Schema::hasTable('complementos_pagos')) {
                $cpSum = (float) DB::table('complementos_pagos')
                    ->where('users_complementos_id', $row->id)
                    ->sum('monto_pago');
            }

            $sum += $cpSum > 0 ? $cpSum : $this->parseComplementoTotalFromXml((string) ($row->xml ?? ''));
        }

        return round($sum, 2);
    }

    private function topClienteFacturas(int $userId, string $tipo, Carbon $start, Carbon $end): array
    {
        $base = DB::table('facturas')
            ->where('users_id', $userId);

        $this->applyFacturasStatusFilter($base, false);
        $this->applyFacturasTipoFilter($base, $tipo);
        $this->applyFacturasDateFilter($base, $start, $end);

        $totals = [];
        foreach ($base->get(array_merge(['razon_social'], $this->facturasSelectColumns())) as $row) {
            $nombre = trim((string) ($row->razon_social ?? ''));
            $total = $this->extractFacturaTotal($row);
            $totals[$nombre] = ($totals[$nombre] ?? 0.0) + $total;
        }

        if (empty($totals)) {
            return ['nombre' => '', 'total' => 0.0];
        }

        arsort($totals);
        $topNombre = (string) array_key_first($totals);

        return [
            'nombre' => $topNombre,
            'total' => round((float) $totals[$topNombre], 2),
        ];
    }

    private function topClienteComplementos(int $userId, Carbon $start, Carbon $end): array
    {
        $base = DB::table('complementos as c')
            ->where('c.users_id', $userId);

        $this->applyComplementosStatusFilter($base, false);
        $this->applyComplementosDateFilter($base, $start, $end);

        $totals = [];
        foreach ($base->get(['c.id', 'c.razon_social', 'c.xml']) as $row) {
            $nombre = trim((string) ($row->razon_social ?? ''));
            $total = 0.0;
            if (Schema::hasTable('complementos_pagos')) {
                $total = (float) DB::table('complementos_pagos')
                    ->where('users_complementos_id', $row->id)
                    ->sum('monto_pago');
            }
            if ($total <= 0) {
                $total = $this->parseComplementoTotalFromXml((string) ($row->xml ?? ''));
            }
            $totals[$nombre] = ($totals[$nombre] ?? 0.0) + $total;
        }

        if (empty($totals)) {
            return ['nombre' => '', 'total' => 0.0];
        }

        arsort($totals);
        $topNombre = (string) array_key_first($totals);

        return [
            'nombre' => $topNombre,
            'total' => round((float) $totals[$topNombre], 2),
        ];
    }

    private function buildKpi(float $actual, float $previo, array $topCliente, array $series): array
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
            'series' => $series,
        ];
    }

    private function parseTotalFromXml(string $xmlString): float
    {
        $xmlString = trim($xmlString);
        if ($xmlString === '') {
            return 0.0;
        }

        if (strpos($xmlString, '<') === false) {
            $decoded = base64_decode($xmlString, true);
            if ($decoded !== false && strpos($decoded, '<') !== false) {
                $xmlString = $decoded;
            }
        }

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
        return (float) str_replace([',', ' '], '', (string) $totalRaw);
    }

    private function parseComplementoTotalFromXml(string $xmlString): float
    {
        $xmlString = trim($xmlString);
        if ($xmlString === '') {
            return 0.0;
        }

        if (strpos($xmlString, '<') === false) {
            $decoded = base64_decode($xmlString, true);
            if ($decoded !== false && strpos($decoded, '<') !== false) {
                $xmlString = $decoded;
            }
        }

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlString, LIBXML_NONET)) {
            return 0.0;
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');

        $tot = $xp->query('//pago20:Totales')->item(0);
        if (!$tot instanceof \DOMElement) {
            return 0.0;
        }

        $raw = (string) $tot->getAttribute('MontoTotalPagos');
        return (float) str_replace([',', ' '], '', $raw);
    }

    private function applyFacturasDateFilter($query, Carbon $start, Carbon $end): void
    {
        $cols = [];
        if (Schema::hasColumn('facturas', 'fecha_factura')) {
            $cols[] = 'fecha_factura';
        }
        if (Schema::hasColumn('facturas', 'fecha')) {
            $cols[] = 'fecha';
        }
        if (Schema::hasColumn('facturas', 'created_at')) {
            $cols[] = 'created_at';
        }

        if (empty($cols)) {
            return;
        }

        $coalesce = 'COALESCE(' . implode(', ', $cols) . ')';
        $query->whereBetween(DB::raw($coalesce), [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);
    }

    private function applyComplementosDateFilter($query, Carbon $start, Carbon $end): void
    {
        $cols = [];
        if (Schema::hasColumn('complementos', 'fecha_pago')) {
            $cols[] = 'c.fecha_pago';
        }
        if (Schema::hasColumn('complementos', 'fecha_documento')) {
            $cols[] = 'c.fecha_documento';
        }
        if (Schema::hasColumn('complementos', 'fecha')) {
            $cols[] = 'c.fecha';
        }
        if (Schema::hasColumn('complementos', 'created_at')) {
            $cols[] = 'c.created_at';
        }

        if (empty($cols)) {
            return;
        }

        $coalesce = 'COALESCE(' . implode(', ', $cols) . ')';
        $query->whereBetween(DB::raw($coalesce), [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);
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

        $query->whereRaw('UPPER(TRIM(COALESCE(tipo_comprobante, ""))) IN (' . implode(',', array_fill(0, count($values), '?')) . ')', $values);
    }

    private function applyFacturasStatusFilter($query, bool $canceladas): void
    {
        $sql = 'UPPER(TRIM(COALESCE(estatus, "")))';
        if ($canceladas) {
            $query->whereRaw($sql . ' IN (?, ?)', ['CANCELADA', 'CANCELADO']);
            return;
        }

        $query->whereRaw($sql . ' NOT IN (?, ?)', ['CANCELADA', 'CANCELADO']);
    }

    private function applyComplementosStatusFilter($query, bool $canceladas): void
    {
        $sql = 'UPPER(TRIM(COALESCE(c.estatus, "")))';
        if ($canceladas) {
            $query->whereRaw($sql . ' IN (?, ?)', ['CANCELADA', 'CANCELADO']);
            return;
        }

        $query->whereRaw($sql . ' NOT IN (?, ?)', ['CANCELADA', 'CANCELADO']);
    }

    private function countFacturas(int $userId, ?string $tipo, Carbon $start, Carbon $end, bool $canceladas): int
    {
        $q = DB::table('facturas')->where('users_id', $userId);
        $this->applyFacturasStatusFilter($q, $canceladas);
        $this->applyFacturasDateFilter($q, $start, $end);

        if ($tipo !== null) {
            $this->applyFacturasTipoFilter($q, $tipo);
        }

        return (int) $q->count();
    }

    private function countComplementos(int $userId, Carbon $start, Carbon $end, bool $canceladas): int
    {
        $q = DB::table('complementos as c')->where('c.users_id', $userId);
        $this->applyComplementosStatusFilter($q, $canceladas);
        $this->applyComplementosDateFilter($q, $start, $end);

        return (int) $q->count();
    }
}
