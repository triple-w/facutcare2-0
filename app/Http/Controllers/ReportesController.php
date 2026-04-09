<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReportesController extends Controller
{
    public function index(Request $request)
    {
        [$filters, $rows, $summary] = $this->resolveReport($request);
        $clientes = DB::table('clientes')
            ->where('users_id', (int) auth()->id())
            ->orderBy('razon_social')
            ->get(['id', 'rfc', 'razon_social'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'rfc' => (string) ($c->rfc ?? ''),
                'razon_social' => (string) ($c->razon_social ?? ''),
                'label' => trim(((string) ($c->razon_social ?? '')) . ' - ' . ((string) ($c->rfc ?? '')), ' -'),
            ])
            ->values();

        return view('reportes.index', compact('filters', 'rows', 'summary', 'clientes'));
    }

    public function exportExcel(Request $request)
    {
        [$filters, $rows, $summary] = $this->resolveReport($request);
        $filename = $this->buildFilename($filters, 'xls');

        return response()
            ->view('reportes.export-excel', compact('filters', 'rows', 'summary'))
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function exportPdf(Request $request)
    {
        [$filters, $rows, $summary] = $this->resolveReport($request);
        $filename = $this->buildFilename($filters, 'pdf');

        $pdf = Pdf::loadView('reportes.export-pdf', compact('filters', 'rows', 'summary'));

        return $pdf->download($filename);
    }

    private function resolveReport(Request $request): array
    {
        $filters = $request->validate([
            'tipo' => ['nullable', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'cliente' => ['nullable', 'string', 'max:255'],
            'estatus' => ['nullable', 'string'],
        ]);

        $filters['tipo'] = $filters['tipo'] ?? 'facturas';
        $filters['fecha_inicio'] = $filters['fecha_inicio'] ?? now()->startOfMonth()->toDateString();
        $filters['fecha_fin'] = $filters['fecha_fin'] ?? now()->toDateString();
        $filters['cliente'] = trim((string) ($filters['cliente'] ?? ''));
        $filters['estatus'] = $this->normalizeStatusFilter((string) ($filters['estatus'] ?? 'todos'));
        $filters['tipo_label'] = $this->tipoLabel($filters['tipo']);
        $filters['estatus_label'] = $this->estatusLabel($filters['estatus']);
        $filters['cliente_label'] = $filters['cliente'] !== '' ? $filters['cliente'] : 'Todos';

        $from = Carbon::parse($filters['fecha_inicio'])->startOfDay();
        $to = Carbon::parse($filters['fecha_fin'])->endOfDay();

        $rows = $this->buildRows(
            (int) auth()->id(),
            $filters,
            $from,
            $to
        );

        $summary = $this->buildSummary((int) auth()->id(), $filters, $from, $to, $rows);

        return [$filters, $rows, $summary];
    }

    private function buildRows(int $userId, array $filters, Carbon $from, Carbon $to): Collection
    {
        $tipo = $filters['tipo'];
        $estatus = $filters['estatus'];

        $rows = match ($tipo) {
            'complementos' => $this->queryComplementos($userId, $from, $to, $estatus),
            'notas_credito' => $this->queryFacturas($userId, $from, $to, 'E', $estatus),
            'canceladas' => $this->queryFacturas($userId, $from, $to, null, 'canceladas')
                ->merge($this->queryComplementos($userId, $from, $to, 'canceladas'))
                ->sortByDesc('fecha')
                ->values(),
            default => $this->queryFacturas($userId, $from, $to, 'I', $estatus),
        };

        return $rows
            ->filter(fn ($row) => $this->matchesClientFilter($row, $filters['cliente']))
            ->filter(fn ($row) => $this->matchesDateFilter($row, $from, $to))
            ->values();
    }

    private function buildSummary(int $userId, array $filters, Carbon $from, Carbon $to, Collection $rows): array
    {
        $summaryFilters = $filters;
        $summaryFilters['tipo'] = 'facturas';
        $ingresos = $this->buildRows($userId, $summaryFilters, $from, $to)
            ->filter(fn ($row) => strtoupper(trim((string) ($row->tipo_comprobante ?? ''))) !== 'E')
            ->sum(fn ($row) => (float) ($row->total_calculado ?? 0));

        $summaryFilters['tipo'] = 'notas_credito';
        $egresos = $this->buildRows($userId, $summaryFilters, $from, $to)
            ->sum(fn ($row) => (float) ($row->total_calculado ?? 0));

        $summaryFilters['tipo'] = 'complementos';
        $pagos = $this->buildRows($userId, $summaryFilters, $from, $to)
            ->sum(fn ($row) => (float) ($row->total_calculado ?? 0));

        $client = $this->resolveClientSummary($rows);

        return [
            'fecha_reporte' => now()->format('d/m/Y H:i'),
            'cliente_rfc' => $client['rfc'],
            'cliente_razon_social' => $client['razon_social'],
            'totales' => [
                'ingresos' => round((float) $ingresos, 2),
                'egresos' => round((float) $egresos, 2),
                'pagos' => round((float) $pagos, 2),
            ],
        ];
    }

    private function resolveClientSummary(Collection $rows): array
    {
        $clientes = $rows
            ->map(function ($row) {
                return [
                    'rfc' => trim((string) ($row->rfc ?? '')),
                    'razon_social' => trim((string) ($row->razon_social ?? '')),
                ];
            })
            ->filter(fn ($cliente) => $cliente['rfc'] !== '' || $cliente['razon_social'] !== '')
            ->unique(fn ($cliente) => strtoupper($cliente['rfc'] . '|' . $cliente['razon_social']))
            ->values();

        if ($clientes->count() === 1) {
            return $clientes->first();
        }

        if ($clientes->count() > 1) {
            return [
                'rfc' => 'Varios',
                'razon_social' => 'Varios clientes',
            ];
        }

        return [
            'rfc' => '—',
            'razon_social' => 'Sin cliente identificado',
        ];
    }

    private function queryFacturas(int $userId, Carbon $from, Carbon $to, ?string $tipo, string $estatus): Collection
    {
        $q = DB::table('facturas')->where('users_id', $userId);
        $this->applyFacturasDateFilter($q, $from, $to);
        $this->applyFacturasStatusFilter($q, $estatus);

        if ($tipo !== null) {
            $this->applyFacturasTipoFilter($q, $tipo);
        }

        return $q->orderByDesc('id')
            ->get($this->facturasReportColumns())
            ->map(function ($row) {
                $this->hydrateFacturaMetadata($row);
                $row->documento = strtoupper((string) ($row->tipo_comprobante ?? '')) === 'E' ? 'Nota de crédito' : 'Factura';
                $row->fecha = $row->fecha_factura ?? $row->fecha ?? $row->created_at ?? null;
                $row->total_calculado = $this->extractFacturaTotal($row);
                return $row;
            })
            ->filter(fn ($row) => $this->matchesStatusFilter($row, $estatus))
            ->values();
    }

    private function queryComplementos(int $userId, Carbon $from, Carbon $to, string $estatus): Collection
    {
        $q = DB::table('complementos as c')->where('c.users_id', $userId);
        $this->applyComplementosDateFilter($q, $from, $to);
        $this->applyComplementosStatusFilter($q, $estatus);

        return $q->orderByDesc('c.id')
            ->get($this->complementosReportColumns())
            ->map(function ($row) {
                $this->hydrateComplementoMetadata($row);
                $row->documento = 'Complemento de pago';
                $row->fecha = $row->fecha_pago ?? $row->fecha_documento ?? $row->created_at ?? null;
                $row->total_calculado = 0.0;
                if (Schema::hasTable('complementos_pagos')) {
                    $row->total_calculado = (float) DB::table('complementos_pagos')
                        ->where('users_complementos_id', $row->id)
                        ->sum('monto_pago');
                }
                if ($row->total_calculado <= 0) {
                    $row->total_calculado = $this->parseComplementoTotal((string) ($row->xml ?? ''));
                }
                return $row;
            })
            ->filter(fn ($row) => $this->matchesStatusFilter($row, $estatus))
            ->values();
    }

    private function applyFacturasDateFilter($query, Carbon $start, Carbon $end): void
    {
        $cols = [];
        if (Schema::hasColumn('facturas', 'fecha_factura')) $cols[] = 'fecha_factura';
        if (Schema::hasColumn('facturas', 'fecha')) $cols[] = 'fecha';
        if (Schema::hasColumn('facturas', 'created_at')) $cols[] = 'created_at';
        if (empty($cols)) return;

        $query->whereBetween(DB::raw('COALESCE(' . implode(', ', $cols) . ')'), [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);
    }

    private function applyComplementosDateFilter($query, Carbon $start, Carbon $end): void
    {
        $cols = [];
        if (Schema::hasColumn('complementos', 'fecha_pago')) $cols[] = 'c.fecha_pago';
        if (Schema::hasColumn('complementos', 'fecha_documento')) $cols[] = 'c.fecha_documento';
        if (Schema::hasColumn('complementos', 'created_at')) $cols[] = 'c.created_at';
        if (empty($cols)) return;

        $query->whereBetween(DB::raw('COALESCE(' . implode(', ', $cols) . ')'), [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);
    }

    private function applyFacturasStatusFilter($query, string $estatus): void
    {
        $sql = 'UPPER(TRIM(COALESCE(estatus, "")))';
        if ($estatus === 'canceladas') {
            $query->whereRaw($sql . ' IN (?, ?)', ['CANCELADA', 'CANCELADO']);
        } elseif ($estatus === 'vigentes') {
            $query->whereRaw($sql . ' NOT IN (?, ?)', ['CANCELADA', 'CANCELADO']);
        }
    }

    private function applyComplementosStatusFilter($query, string $estatus): void
    {
        $sql = 'UPPER(TRIM(COALESCE(c.estatus, "")))';
        if ($estatus === 'canceladas') {
            $query->whereRaw($sql . ' IN (?, ?)', ['CANCELADA', 'CANCELADO']);
        } elseif ($estatus === 'vigentes') {
            $query->whereRaw($sql . ' NOT IN (?, ?)', ['CANCELADA', 'CANCELADO']);
        }
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

    private function facturasReportColumns(): array
    {
        $preferred = [
            'id',
            'serie',
            'folio',
            'uuid',
            'rfc',
            'razon_social',
            'estatus',
            'tipo_comprobante',
            'fecha',
            'fecha_factura',
            'created_at',
            'xml',
            'total',
        ];

        return array_values(array_filter($preferred, fn ($column) => Schema::hasColumn('facturas', $column)));
    }

    private function extractFacturaTotal(object $row): float
    {
        $total = property_exists($row, 'total') ? (float) ($row->total ?? 0) : 0.0;
        if ($total <= 0) {
            $total = $this->parseFacturaTotal((string) ($row->xml ?? ''));
        }

        return $total;
    }

    private function complementosReportColumns(): array
    {
        $preferred = [
            'id' => 'c.id',
            'serie' => 'c.serie',
            'folio' => 'c.folio',
            'uuid' => 'c.uuid',
            'rfc' => 'c.rfc',
            'razon_social' => 'c.razon_social',
            'estatus' => 'c.estatus',
            'fecha_pago' => 'c.fecha_pago',
            'fecha_documento' => 'c.fecha_documento',
            'created_at' => 'c.created_at',
            'xml' => 'c.xml',
        ];

        $columns = [];
        foreach ($preferred as $column => $select) {
            if (Schema::hasColumn('complementos', $column)) {
                $columns[] = $select;
            }
        }

        return $columns;
    }

    private function hydrateFacturaMetadata(object $row): void
    {
        $xml = $this->normalizeXml((string) ($row->xml ?? ''));
        if ($xml === '') {
            return;
        }

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            return;
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');

        $comprobante = $xp->query('//cfdi4:Comprobante | //cfdi3:Comprobante')->item(0);
        $receptor = $xp->query('//cfdi4:Receptor | //cfdi3:Receptor')->item(0);
        $timbre = $xp->query('//*[local-name()="TimbreFiscalDigital"]')->item(0);

        if ($comprobante instanceof \DOMElement) {
            $row->serie = $row->serie ?? $this->emptyToNull($comprobante->getAttribute('Serie') ?: $comprobante->getAttribute('serie'));
            $row->folio = $row->folio ?? $this->emptyToNull($comprobante->getAttribute('Folio') ?: $comprobante->getAttribute('folio'));
            $row->tipo_comprobante = $row->tipo_comprobante ?? $this->emptyToNull($comprobante->getAttribute('TipoDeComprobante') ?: $comprobante->getAttribute('tipoDeComprobante'));
            $row->fecha_factura = $row->fecha_factura ?? $this->emptyToNull($comprobante->getAttribute('Fecha') ?: $comprobante->getAttribute('fecha'));
        }

        if ($receptor instanceof \DOMElement) {
            $row->rfc = $row->rfc ?? $this->emptyToNull($receptor->getAttribute('Rfc') ?: $receptor->getAttribute('rfc'));
            $row->razon_social = $row->razon_social ?? $this->emptyToNull($receptor->getAttribute('Nombre') ?: $receptor->getAttribute('nombre'));
        }

        if ($timbre instanceof \DOMElement) {
            $row->uuid = $row->uuid ?? $this->emptyToNull($timbre->getAttribute('UUID') ?: $timbre->getAttribute('Uuid') ?: $timbre->getAttribute('uuid'));
        }
    }

    private function hydrateComplementoMetadata(object $row): void
    {
        $xml = $this->normalizeXml((string) ($row->xml ?? ''));
        if ($xml === '') {
            return;
        }

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            return;
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');
        $xp->registerNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
        $xp->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');

        $comprobante = $xp->query('//cfdi4:Comprobante | //cfdi3:Comprobante')->item(0);
        $receptor = $xp->query('//cfdi4:Receptor | //cfdi3:Receptor')->item(0);
        $timbre = $xp->query('//*[local-name()="TimbreFiscalDigital"]')->item(0);
        $pago = $xp->query('//pago20:Pago | //pago10:Pago')->item(0);

        if ($comprobante instanceof \DOMElement) {
            $row->serie = $row->serie ?? $this->emptyToNull($comprobante->getAttribute('Serie') ?: $comprobante->getAttribute('serie'));
            $row->folio = $row->folio ?? $this->emptyToNull($comprobante->getAttribute('Folio') ?: $comprobante->getAttribute('folio'));
            $row->fecha_documento = $row->fecha_documento ?? $this->emptyToNull($comprobante->getAttribute('Fecha') ?: $comprobante->getAttribute('fecha'));
        }

        if ($receptor instanceof \DOMElement) {
            $row->rfc = $row->rfc ?? $this->emptyToNull($receptor->getAttribute('Rfc') ?: $receptor->getAttribute('rfc'));
            $row->razon_social = $row->razon_social ?? $this->emptyToNull($receptor->getAttribute('Nombre') ?: $receptor->getAttribute('nombre'));
        }

        if ($timbre instanceof \DOMElement) {
            $row->uuid = $row->uuid ?? $this->emptyToNull($timbre->getAttribute('UUID') ?: $timbre->getAttribute('Uuid') ?: $timbre->getAttribute('uuid'));
        }

        if ($pago instanceof \DOMElement) {
            $row->fecha_pago = $row->fecha_pago ?? $this->emptyToNull($pago->getAttribute('FechaPago') ?: $pago->getAttribute('fechaPago'));
        }
    }

    private function emptyToNull(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeStatusFilter(string $estatus): string
    {
        $estatus = Str::lower(trim($estatus));

        return in_array($estatus, ['todos', 'vigentes', 'canceladas'], true) ? $estatus : 'todos';
    }

    private function matchesStatusFilter(object $row, string $estatus): bool
    {
        if ($estatus === 'todos') {
            return true;
        }

        $normalized = Str::upper(trim((string) ($row->estatus ?? '')));
        $cancelada = in_array($normalized, ['CANCELADA', 'CANCELADO'], true);

        return $estatus === 'canceladas' ? $cancelada : !$cancelada;
    }

    private function matchesClientFilter(object $row, string $cliente): bool
    {
        $cliente = trim($cliente);
        if ($cliente === '') {
            return true;
        }

        $needle = Str::upper(Str::ascii($cliente));
        $haystack = Str::upper(Str::ascii(trim((string) ($row->razon_social ?? '')) . ' ' . trim((string) ($row->rfc ?? ''))));

        return str_contains($haystack, $needle);
    }

    private function matchesDateFilter(object $row, Carbon $from, Carbon $to): bool
    {
        if (empty($row->fecha)) {
            return false;
        }

        try {
            $fecha = Carbon::parse($row->fecha);
        } catch (\Throwable $e) {
            return false;
        }

        return $fecha->betweenIncluded($from, $to);
    }

    private function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'complementos' => 'Complementos',
            'notas_credito' => 'Notas de crédito',
            'canceladas' => 'Canceladas',
            default => 'Facturas',
        };
    }

    private function estatusLabel(string $estatus): string
    {
        return match ($estatus) {
            'vigentes' => 'Vigentes',
            'canceladas' => 'Canceladas',
            default => 'Todos',
        };
    }

    private function buildFilename(array $filters, string $extension): string
    {
        $fecha = ($filters['fecha_inicio'] ?? 'sin-fecha') . '_a_' . ($filters['fecha_fin'] ?? 'sin-fecha');
        $estatus = $filters['estatus'] ?? 'todos';
        $cliente = $filters['cliente'] !== '' ? Str::slug(Str::limit($filters['cliente'], 40, ''), '-') : 'todos';
        $tipo = Str::slug($filters['tipo'] ?? 'documentos', '-');

        return 'Reporte-' . $tipo . '-' . $fecha . '-' . $estatus . '-' . $cliente . '.' . $extension;
    }

    private function parseFacturaTotal(string $xml): float
    {
        $xml = $this->normalizeXml($xml);
        if ($xml === '') return 0.0;

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) return 0.0;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');
        $node = $xp->query('//cfdi4:Comprobante | //cfdi3:Comprobante')->item(0);
        if (!$node instanceof \DOMElement) return 0.0;

        return (float) str_replace([',', ' '], '', (string) ($node->getAttribute('Total') ?: $node->getAttribute('total')));
    }

    private function parseComplementoTotal(string $xml): float
    {
        $xml = $this->normalizeXml($xml);
        if ($xml === '') return 0.0;

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) return 0.0;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
        $node = $xp->query('//pago20:Totales')->item(0);
        if (!$node instanceof \DOMElement) return 0.0;

        return (float) str_replace([',', ' '], '', (string) $node->getAttribute('MontoTotalPagos'));
    }

    private function normalizeXml(string $xml): string
    {
        $xml = trim($xml);
        if ($xml === '') return '';

        if (strpos($xml, '<') === false) {
            $decoded = base64_decode($xml, true);
            if ($decoded !== false && strpos($decoded, '<') !== false) {
                return $decoded;
            }
        }

        return $xml;
    }
}
