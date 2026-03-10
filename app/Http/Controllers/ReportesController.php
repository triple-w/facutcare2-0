<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportesController extends Controller
{
    public function index(Request $request)
    {
        [$filters, $rows] = $this->resolveReport($request);

        return view('reportes.index', compact('filters', 'rows'));
    }

    public function exportExcel(Request $request)
    {
        [$filters, $rows] = $this->resolveReport($request);
        $filename = 'reporte_' . $filters['tipo'] . '_' . now()->format('Ymd_His') . '.xls';

        return response()
            ->view('reportes.export-excel', compact('filters', 'rows'))
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function exportPdf(Request $request)
    {
        [$filters, $rows] = $this->resolveReport($request);
        $filename = 'reporte_' . $filters['tipo'] . '_' . now()->format('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('reportes.export-pdf', compact('filters', 'rows'));

        return $pdf->download($filename);
    }

    private function resolveReport(Request $request): array
    {
        $filters = $request->validate([
            'tipo' => ['nullable', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
        ]);

        $filters['tipo'] = $filters['tipo'] ?? 'facturas';
        $filters['fecha_inicio'] = $filters['fecha_inicio'] ?? now()->startOfMonth()->toDateString();
        $filters['fecha_fin'] = $filters['fecha_fin'] ?? now()->toDateString();

        $rows = $this->buildRows(
            (int) auth()->id(),
            $filters['tipo'],
            Carbon::parse($filters['fecha_inicio'])->startOfDay(),
            Carbon::parse($filters['fecha_fin'])->endOfDay()
        );

        return [$filters, $rows];
    }

    private function buildRows(int $userId, string $tipo, Carbon $from, Carbon $to): Collection
    {
        return match ($tipo) {
            'complementos' => $this->queryComplementos($userId, $from, $to, false),
            'notas_credito' => $this->queryFacturas($userId, $from, $to, 'E', false),
            'canceladas' => $this->queryFacturas($userId, $from, $to, null, true)
                ->merge($this->queryComplementos($userId, $from, $to, true))
                ->sortByDesc('fecha')
                ->values(),
            default => $this->queryFacturas($userId, $from, $to, 'I', false),
        };
    }

    private function queryFacturas(int $userId, Carbon $from, Carbon $to, ?string $tipo, bool $canceladas): Collection
    {
        $q = DB::table('facturas')->where('users_id', $userId);
        $this->applyFacturasDateFilter($q, $from, $to);
        $this->applyFacturasStatusFilter($q, $canceladas);

        if ($tipo !== null) {
            $this->applyFacturasTipoFilter($q, $tipo);
        }

        return $q->orderByDesc('id')
            ->get($this->facturasReportColumns())
            ->map(function ($row) {
                $row->documento = strtoupper((string) ($row->tipo_comprobante ?? '')) === 'E' ? 'Nota de crédito' : 'Factura';
                $row->fecha = $row->fecha_factura ?? $row->fecha ?? $row->created_at ?? null;
                $row->total_calculado = $this->extractFacturaTotal($row);
                return $row;
            });
    }

    private function queryComplementos(int $userId, Carbon $from, Carbon $to, bool $canceladas): Collection
    {
        $q = DB::table('complementos as c')->where('c.users_id', $userId);
        $this->applyComplementosDateFilter($q, $from, $to);
        $this->applyComplementosStatusFilter($q, $canceladas);

        return $q->orderByDesc('c.id')
            ->get(['c.id', 'c.serie', 'c.folio', 'c.uuid', 'c.rfc', 'c.razon_social', 'c.estatus', 'c.fecha_pago', 'c.fecha_documento', 'c.created_at', 'c.xml'])
            ->map(function ($row) {
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
            });
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

    private function applyFacturasStatusFilter($query, bool $canceladas): void
    {
        $sql = 'UPPER(TRIM(COALESCE(estatus, "")))';
        if ($canceladas) {
            $query->whereRaw($sql . ' IN (?, ?)', ['CANCELADA', 'CANCELADO']);
        } else {
            $query->whereRaw($sql . ' NOT IN (?, ?)', ['CANCELADA', 'CANCELADO']);
        }
    }

    private function applyComplementosStatusFilter($query, bool $canceladas): void
    {
        $sql = 'UPPER(TRIM(COALESCE(c.estatus, "")))';
        if ($canceladas) {
            $query->whereRaw($sql . ' IN (?, ?)', ['CANCELADA', 'CANCELADO']);
        } else {
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
        $columns = ['id', 'serie', 'folio', 'uuid', 'rfc', 'razon_social', 'estatus', 'tipo_comprobante', 'fecha', 'fecha_factura', 'created_at', 'xml'];
        if (Schema::hasColumn('facturas', 'total')) {
            $columns[] = 'total';
        }

        return $columns;
    }

    private function extractFacturaTotal(object $row): float
    {
        $total = property_exists($row, 'total') ? (float) ($row->total ?? 0) : 0.0;
        if ($total <= 0) {
            $total = $this->parseFacturaTotal((string) ($row->xml ?? ''));
        }

        return $total;
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
