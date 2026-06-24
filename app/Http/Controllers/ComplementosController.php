<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Traits\PacMultipacTrait;
use Carbon\Carbon;

class ComplementosController extends Controller
{

    use PacMultipacTrait;
    // =========================
    // LISTADO cambio para visualizarlo en el comit
    // =========================
    public function index(Request $request)
    {
        $userId = auth()->id();
        $perPage = 300;

        $rows = DB::table('complementos')
            ->where('users_id', $userId)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = $rows->getCollection();
        foreach ($items as $r) {
            $xml = (string)($r->xml ?? '');
            if ($xml === '') continue;

            $meta = $this->parseCfdiBasicsFromXml($xml);

            if (empty($r->serie) && !empty($meta['serie'])) $r->serie = $meta['serie'];
            if (empty($r->folio) && !empty($meta['folio'])) $r->folio = $meta['folio'];
            if (empty($r->uuid) && !empty($meta['uuid'])) $r->uuid = $meta['uuid'];

            $monto = $this->parseMontoTotalPagosFromXml($xml);
            if (!isset($r->total_pagos) || (float)$r->total_pagos <= 0) {
                $r->total_pagos = $monto;
            }
        }
        $rows->setCollection($items);

        if ($request->ajax()) {
            $rowsHtml = view('documentos.complementos.partials.rows', compact('rows'))->render();

            return response()->json([
                'rows_html' => $rowsHtml,
                'meta' => [
                    'current_page' => $rows->currentPage(),
                    'last_page'    => $rows->lastPage(),
                    'total'        => $rows->total(),
                    'per_page'     => $rows->perPage(),
                    'count'        => $rows->count(),
                ],
            ]);
        }

        return view('documentos.complementos.index', compact('rows'));
    }

    // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Nuevo complemento: limpia draft completo
    public function nueva()
    {
        session()->forget('complemento_draft');
        return redirect()->route('complementos.create');
    }

    // =========================
    // CREATE
    // =========================
    public function create()
    {
        session()->forget('complemento_draft');
        $userId = auth()->id();

        $clientes = DB::table('clientes')
            ->where('users_id', $userId)
            ->orderBy('razon_social')
            ->get(['id','rfc','razon_social','codigo_postal','regimen_fiscal']);

        $draft = session('complemento_draft', []);

        $formasPago = $this->catalogoFormasPago();
        $monedas    = $this->catalogoMonedas();

        // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ folios tipo PAGO
        $foliosPago = $this->foliosPago($userId);

        $endpoints = [
            'facturasPendientes' => route('complementos.facturasPendientes'),
            'preview'            => route('complementos.preview'),
        ];

        if (\Route::has('complementos.timbrar')) {
            $endpoints['timbrar'] = route('complementos.timbrar');
        }

        $opts = [
            'csrf'       => csrf_token(),
            'clientes'   => $clientes,
            'formasPago' => $formasPago,
            'monedas'    => $monedas,
            'foliosPago' => $foliosPago,
            'endpoints'  => $endpoints,
            'prefill'    => is_array($draft) ? $draft : [],
        ];

        return view('documentos.complementos.create', compact('opts'));
    }

    // =========================
    // PREVIEW
    // =========================
    public function preview(Request $request)
    {
        $payload = json_decode((string)$request->input('payload', ''), true);

        if (!is_array($payload) || empty($payload)) {
            return redirect()->route('complementos.create')->with('error', 'Payload invÃƒÆ’Ã‚Â¡lido.');
        }

        // guarda borrador para volver a create con info
        session(['complemento_draft' => $payload]);
        return $this->renderPreviewFromPayload($payload);
    }

    private function renderPreviewFromPayload(array $payload, ?string $flashError = null)
    {
        $userId = auth()->id();
        $clienteId = (int)($payload['cliente_id'] ?? 0);

        $cliente = DB::table('clientes')
            ->where('users_id', $userId)
            ->where('id', $clienteId)
            ->first();

        if (!$cliente) {
            Log::warning('Complementos.timbrar invalid cliente', [
                'user_id' => $userId,
                'cliente_id' => $clienteId,
            ]);
            return redirect()->route('complementos.create')->with('error', 'Cliente invÃƒÆ’Ã‚Â¡lido.');
        }

        $payload['serie_pago'] = (string)($payload['serie_pago'] ?? '');
        $payload['folio_pago'] = (int)($payload['folio_pago'] ?? 0);
        $payload['fecha_documento'] = (string)($payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? ''));
        $payload['fecha_pago'] = (string)($payload['fecha_pago'] ?? ($payload['fecha_documento'] ?? ''));
        $payload['forma_pago_p'] = (string)($payload['forma_pago_p'] ?? '03');
        $payload['moneda_p'] = (string)($payload['moneda_p'] ?? 'MXN');
        $payload['tipo_cambio_p'] = (float)($payload['tipo_cambio_p'] ?? 1);
        $payload['num_operacion'] = (string)($payload['num_operacion'] ?? '');
        $payload['rfc_banco_emisor'] = (string)($payload['rfc_banco_emisor'] ?? '');
        $payload['cuenta_ordenante'] = (string)($payload['cuenta_ordenante'] ?? '');
        $payload['banco_receptor'] = (string)($payload['banco_receptor'] ?? '');
        $payload['cuenta_beneficiaria'] = (string)($payload['cuenta_beneficiaria'] ?? '');

        $payload = $this->normalizePayloadPagos($payload);

        $pagos = $payload['pagos'] ?? [];
        if (!is_array($pagos)) $pagos = [];

        foreach ($pagos as $i => $p) {
            if (!is_array($p)) $p = [];

            $p['saldo_anterior'] = $this->moneyString($p['saldo_anterior'] ?? '0');
            $p['monto_pago'] = $this->moneyString($p['monto_pago'] ?? '0');
            $p['saldo_insoluto'] = $this->moneyString($p['saldo_insoluto'] ?? '0');
            $p['num_parcialidad'] = (int)($p['num_parcialidad'] ?? 1);
            $p['moneda_dr'] = (string)($p['moneda_dr'] ?? 'MXN');
            $p['metodo_pago_dr'] = (string)($p['metodo_pago_dr'] ?? 'PPD');
            $p['objeto_imp'] = (bool)($p['objeto_imp'] ?? false);
            $p['impuestos'] = is_array($p['impuestos'] ?? null) ? $p['impuestos'] : [];

            $pagos[$i] = $p;
        }

        $payload['pagos'] = $pagos;
        $this->validatePagos20TaxConsistency($payload);
        [$montoTotal, $totalesSat, $impPSums] = $this->calculatePagos20Totals($payload);

        $subtotal = '0.00';
        foreach (($impPSums['traslados'] ?? []) as $row) {
            $subtotal = $this->sumMoneyStrings($subtotal, $row['base'] ?? '0');
        }

        $traslados = '0.00';
        foreach (($impPSums['traslados'] ?? []) as $row) {
            $traslados = $this->sumMoneyStrings($traslados, $row['importe'] ?? '0');
        }

        $retenciones = '0.00';
        foreach (($impPSums['retenciones'] ?? []) as $row) {
            $retenciones = $this->sumMoneyStrings($retenciones, $row['importe'] ?? '0');
        }

        $totales = [
            'subtotal' => $subtotal,
            'traslados' => $traslados,
            'retenciones' => $retenciones,
            'total' => $montoTotal,
            'pagos20' => $totalesSat,
            'impuestos_p' => $impPSums,
        ];
        if ($flashError !== null) {
            session()->flash('error', $flashError);
        }

        return view('documentos.complementos.preview', compact('payload', 'cliente', 'totales'));
    }



    // =========================
    // AJAX: FACTURAS PENDIENTES
    // =========================
    public function facturasPendientes(Request $request)
    {
        $userId = auth()->id();
        $clienteId = (int)$request->query('cliente_id', 0);

        try {
            if ($clienteId <= 0) {
                return response()->json([], 422);
            }

            $cliente = DB::table('clientes')
                ->where('users_id', $userId)
                ->where('id', $clienteId)
                ->first(['id','rfc','razon_social']);

            if (!$cliente) {
                return response()->json([], 422);
            }

            $rfcCliente = strtoupper(trim((string)($cliente->rfc ?? '')));
            if ($rfcCliente === '') {
                return response()->json([], 422);
            }

            $facturas = DB::table('facturas as f')
                ->where('f.users_id', $userId)
                ->whereNotNull('f.uuid')
                ->where('f.uuid', '<>', '')
                ->whereRaw('UPPER(TRIM(f.rfc)) = ?', [$rfcCliente])
                ->orderByDesc('f.id')
                ->limit(300)
                ->get([
                    'f.id',
                    'f.uuid',
                    'f.rfc',
                    'f.razon_social',
                    'f.estatus',
                    'f.fecha_factura',
                    'f.fecha',
                    'f.xml',
                ]);

            $items = [];

            foreach ($facturas as $f) {
                $estatus = strtoupper(trim((string)($f->estatus ?? '')));
                if (in_array($estatus, ['CANCELADA','CANCELADO'], true)) {
                    continue;
                }

                $meta = $this->parseCfdiBasicsFromXml((string)($f->xml ?? ''));

                $uuid  = strtoupper(trim((string)($f->uuid ?? $meta['uuid'] ?? '')));
                $total = (float)($meta['total'] ?? 0);

                if ($uuid === '' || $total <= 0) {
                    continue;
                }

                $saldoInsoluto = $this->saldoInsolutoPorUuidFactuCare($userId, $uuid, $total);

                if ($saldoInsoluto <= 0.009) {
                    continue;
                }

                $numParcialidad = $this->siguienteParcialidadPorUuidFactuCare($userId, $uuid);

                $items[] = [
                    'id' => (int)$f->id,
                    'uuid' => $uuid,
                    'serie' => (string)($meta['serie'] ?? ''),
                    'folio' => (string)($meta['folio'] ?? ''),
                    'fecha' => (string)($meta['fecha'] ?? ($f->fecha_factura ?? $f->fecha ?? '')),
                    'moneda_dr' => (string)($meta['moneda'] ?? 'MXN'),
                    'metodo_pago_dr' => (string)($meta['metodo_pago'] ?? 'PPD'),

                    'total' => round($total, 2),
                    'saldo_insoluto' => round($saldoInsoluto, 2),
                    'num_parcialidad' => $numParcialidad,

                    'razon_social' => (string)($f->razon_social ?? ''),
                    'rfc' => (string)($f->rfc ?? ''),
                ];
            }

            return response()->json($items);

        } catch (\Throwable $e) {
            Log::error('facturasPendientes ERROR: '.$e->getMessage(), [
                'user_id' => $userId,
                'cliente_id' => $clienteId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([], 500);
        }
    }

    // =========================
    // VER (PDF/XML/VISTA)
    // =========================
    public function ver(int $id)
    {
        $userId = auth()->id();

        $comp = DB::table('complementos')
            ->where('users_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$comp) {
            return redirect()->route('complementos.index')->with('error', 'Complemento no encontrado.');
        }

        $pagos = DB::table('complementos_pagos')
            ->where('users_complementos_id', $comp->id)
            ->orderBy('id')
            ->get();
        $pagos20 = $this->parsePagos20DetailsFromXml((string)($comp->xml ?? ''));

        return view('documentos.complementos.invoice', compact('comp', 'pagos', 'pagos20'));
    }

    public function downloadXml(int $id)
    {
        $comp = $this->complementoOrFail($id);

        $xml = (string)($comp->xml ?? '');
        abort_if(trim($xml) === '', 404, 'XML no disponible');

        $cfdi = $this->parseCfdiBasicsFromXml($xml);
        $uuid = $cfdi['uuid'] ?: ($comp->uuid ?? $comp->id);

        $name = trim(($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? ''));
        if ($name === '') $name = 'Complemento';

        $filename = "{$name} - {$uuid}.xml";

        return response($xml)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function downloadPdf(int $id)
    {
        $comp = $this->complementoOrFail($id);

        $pdfB64 = (string)($comp->pdf ?? '');
        abort_if(trim($pdfB64) === '', 404, 'PDF no disponible');

        $bin = base64_decode($pdfB64, true);
        if ($bin === false) {
            $bin = $pdfB64;
        }

        $xml = (string)($comp->xml ?? '');
        $cfdi = $xml ? $this->parseCfdiBasicsFromXml($xml) : [];
        $uuid = ($cfdi['uuid'] ?? null) ?: ($comp->uuid ?? $comp->id);

        $name = trim((($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? '')));
        if ($name === '') $name = 'Complemento';

        $filename = "{$name} - {$uuid}.pdf";

        return response($bin)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function regenerarPdf(int $id)
    {
        $userId = auth()->id();
        $comp = $this->complementoOrFail($id);

        $xml = (string)($comp->xml ?? '');
        if (trim($xml) === '') {
            return back()->with('error', 'No hay XML para regenerar el PDF.');
        }

        $pdfB64 = '';

        try {
            $payloadMin = [
                'tipo_comprobante' => 'P',
                'serie_pago'       => $comp->serie ?? null,
                'folio_pago'       => $comp->folio ?? null,
                'fecha_pago'       => $comp->fecha_pago ?? null,
                'forma_pago_p'     => $comp->forma_pago_p ?? null,
                'moneda_p'         => $comp->moneda_p ?? null,
                'num_operacion'    => $comp->num_operacion ?? null,
            ];

            $clienteMin = (object) [
                'rfc'          => $comp->rfc ?? '',
                'razon_social' => $comp->razon_social ?? '',
            ];

            $pdfB64 = $this->generarPdfBase64ComplementoPagos2($userId, $xml, $payloadMin, $clienteMin);
        } catch (\Throwable $e) {
            $pdfB64 = $this->generarPdfBase64FallbackDompdfComplemento($xml);
        }

        if (!is_string($pdfB64)) {
            $pdfB64 = '';
        }
        if (trim($pdfB64) === '') {
            return back()->with('error', 'No fue posible regenerar el PDF (PAC y fallback fallaron).');
        }

        DB::table('complementos')
            ->where('id', $comp->id)
            ->where('users_id', $userId)
            ->update(['pdf' => $pdfB64]);

        return back()->with('success', 'PDF regenerado correctamente.');
    }

    public function cancelar(Request $request, int $id)
    {
        $userId = auth()->id();

        $motivo = (string) $request->input('motivo', '');
        $folioSustitucion = (string) $request->input('folioSustitucion', $request->input('foliosustitucion', ''));

        if (!in_array($motivo, ['01', '02', '03', '04'], true)) {
            return back()->with('error', 'Motivo invalido.');
        }
        if ($motivo === '01' && trim($folioSustitucion) === '') {
            return back()->with('error', 'Para motivo 01 es obligatorio el UUID de sustitucion.');
        }

        $comp = $this->complementoOrFail($id);

        if (strtoupper((string)$comp->estatus) === 'CANCELADA') {
            return back()->with('error', 'El complemento ya esta cancelado.');
        }

        $xml = (string)($comp->xml ?? '');
        if (trim($xml) === '') {
            return back()->with('error', 'No hay XML timbrado para cancelar.');
        }

        $meta = $this->parseCfdiBasicsFromXml($xml);
        $parties = $this->parseCfdiPartiesFromXml($xml);

        $uuid = (string)($meta['uuid'] ?? $comp->uuid ?? '');
        $rfcEmisor = (string)($parties['emisor_rfc'] ?? '');
        $rfcReceptor = (string)($parties['receptor_rfc'] ?? '');

        $totalRaw = (string)($meta['total'] ?? '0');
        $totalRaw = str_replace([',', ' '], '', $totalRaw);
        $totalNum = round((float)$totalRaw, 2);
        if ($totalNum <= 0) {
            $totalNum = round($this->parseMontoTotalPagosFromXml($xml), 2);
        }
        $totalPac = number_format($totalNum, 2, '.', '');

        if ($uuid === '' || $rfcEmisor === '' || $rfcReceptor === '') {
            return back()->with('error', 'No pude obtener UUID/RFCs desde el XML.');
        }

        try {
            $csd = $this->cargarCsdParaTimbrado($userId);

            $keyPem = (string)($csd['key_pem'] ?? '');
            $certB64 = (string)($csd['cert_b64'] ?? '');

            if (trim($keyPem) === '' || trim($certB64) === '') {
                throw new \RuntimeException('CSD incompleto: falta key_pem o cert_b64.');
            }

            $cerPem = "-----BEGIN CERTIFICATE-----\n"
                . chunk_split($certB64, 64, "\n")
                . "-----END CERTIFICATE-----\n";

            $mp = new \App\Extensions\MultiPac\MultiPac();

            $resp = $mp->callCancelarPEM([
                'keyPEM' => $keyPem,
                'cerPEM' => $cerPem,
                'uuid' => $uuid,
                'rfcEmisor' => $rfcEmisor,
                'rfcReceptor' => $rfcReceptor,
                'total' => $totalPac,
                'motivo' => $motivo,
                'folioSustitucion' => $motivo === '01' ? $folioSustitucion : '',
            ]);

            if (is_string($resp)) {
                throw new \RuntimeException('PAC (respuesta): ' . mb_substr($resp, 0, 600));
            }

            $status  = strtolower((string)($resp->status ?? $resp->STATUS ?? ''));
            $code    = (string)($resp->code ?? $resp->codigo ?? $resp->CODIGO ?? '');
            $message = (string)($resp->message ?? $resp->mensaje ?? $resp->MENSAJE ?? '');
            $acuse   = (string)($resp->data ?? $resp->acuse ?? $resp->ACUSE ?? '');

            $ok = ($status === 'success') || ($code === '0' || $code === 0);

            if (!$ok) {
                $msgHumano = $this->traducirCodigoPac('cancelar', (string)$code, $message);
                throw new \RuntimeException($msgHumano ?: ($message ?: 'Cancelacion rechazada por el PAC.'));
            }

            DB::transaction(function () use ($comp, $userId, $acuse) {
                DB::table('complementos')
                    ->where('id', $comp->id)
                    ->where('users_id', $userId)
                    ->update([
                        'estatus' => 'CANCELADA',
                        'acuse' => $acuse !== '' ? $acuse : (string)($comp->acuse ?? ''),
                    ]);

                if (Schema::hasTable('complementos_pagos')) {
                    $updates = [
                        'saldo_insoluto' => DB::raw('saldo_anterior'),
                    ];
                    if (Schema::hasColumn('complementos_pagos', 'updated_at')) {
                        $updates['updated_at'] = now();
                    }
                    DB::table('complementos_pagos')
                        ->where('users_complementos_id', $comp->id)
                        ->update($updates);
                }

                $this->consumirTimbre($userId);
            });

            return back()->with('success', 'Complemento cancelado correctamente.');

        } catch (\Throwable $e) {
            return back()->with('error', 'Error al cancelar: ' . $e->getMessage());
        }
    }

    private function complementoOrFail(int $id): object
    {
        $userId = auth()->id();

        $comp = DB::table('complementos')
            ->where('users_id', $userId)
            ->where('id', $id)
            ->first();

        abort_if(!$comp, 404, 'Complemento no encontrado');

        return $comp;
    }

    private function parseCfdiPartiesFromXml(string $xmlString): array
    {
        $out = [
            'emisor_rfc' => '',
            'receptor_rfc' => '',
        ];

        $xmlString = trim($xmlString);
        if ($xmlString === '') return $out;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlString, LIBXML_NONET)) return $out;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');

        $em = $xp->query('//cfdi4:Emisor | //cfdi3:Emisor')->item(0);
        if ($em instanceof \DOMElement) {
            $out['emisor_rfc'] = $em->getAttribute('Rfc') ?: $em->getAttribute('rfc');
        }

        $re = $xp->query('//cfdi4:Receptor | //cfdi3:Receptor')->item(0);
        if ($re instanceof \DOMElement) {
            $out['receptor_rfc'] = $re->getAttribute('Rfc') ?: $re->getAttribute('rfc');
        }

        return $out;
    }

    private function traducirCodigoPac(string $operacion, string $code, ?string $mensajeApi = null): string
    {
        $dic = config('timbradorxpress_errors', []);

        $msg = null;

        if (isset($dic[$operacion]) && is_array($dic[$operacion]) && isset($dic[$operacion][$code])) {
            $msg = $dic[$operacion][$code];
        } elseif (isset($dic['general']) && is_array($dic['general']) && isset($dic['general'][$code])) {
            $msg = $dic['general'][$code];
        } elseif (!empty($mensajeApi)) {
            $msg = $mensajeApi;
        } else {
            $msg = "Codigo desconocido: {$code}";
        }

        return $msg;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Folios tipo PAGO para serie/folio del complemento
     * Ajusta columnas si tu tabla folios usa otros nombres.
     */
    private function foliosPago(int $userId): array
    {
        if (!Schema::hasTable('folios')) return [];

        $rows = DB::table('folios')
            ->where('users_id', $userId)
            ->where(function ($q) {
                $q->where('tipo', 'PAGO')
                  ->orWhere('tipo', 'P');
            })
            ->orderBy('serie')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $serie = (string)($r->serie ?? '');
            if ($serie === '') continue;

            // Fallbacks por si cambia el nombre de la columna en tu BD
            $actual = (int)($r->folio_actual ?? $r->consecutivo ?? $r->folio ?? $r->ultimo_folio ?? 0);

            $out[] = [
                'id' => (int)($r->id ?? 0),
                'serie' => $serie,
                'siguiente' => max(1, $actual),
            ];
        }

        return $out;
    }

    /**
     * FactuCare: saldo insoluto por UUID
     */
    private function saldoInsolutoPorUuidFactuCare(int $userId, string $uuid, float $totalFactura): float
    {
        $uuid = strtoupper(trim($uuid));
        if ($uuid === '') return 0.0;

        if (!Schema::hasTable('complementos_pagos')) {
            return round($totalFactura, 2);
        }

        $q = DB::table('complementos_pagos as cp')
            ->whereRaw('UPPER(TRIM(cp.documento_id)) = ?', [$uuid]);

        if (Schema::hasTable('complementos')) {
            $q->join('complementos as c', 'c.id', '=', 'cp.users_complementos_id')
              ->where('c.users_id', $userId)
              ->whereNotIn(DB::raw('UPPER(c.estatus)'), ['CANCELADA', 'CANCELADO']);
        }

        $last = $q->orderByDesc('cp.id')->first(['cp.saldo_insoluto']);

        if (!$last) {
            return round($totalFactura, 2);
        }

        return max(0.0, round((float)$last->saldo_insoluto, 2));
    }

    // ============================================================
    // HELPERS TIMBRADO
    // ============================================================

    private function normalizePayloadPagos(array $payload): array
    {
        // Compatibilidad con drafts anteriores.
        $payload['fecha_documento'] = (string)($payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? ''));
        $payload['fecha_pago']      = (string)($payload['fecha_pago'] ?? ($payload['fecha_documento'] ?? ''));

        $payload['forma_pago_p']  = (string)($payload['forma_pago_p'] ?? '03');
        $payload['moneda_p']      = (string)($payload['moneda_p'] ?? 'MXN');
        $payload['tipo_cambio_p'] = (float)($payload['tipo_cambio_p'] ?? 1);

        $payload['num_operacion']       = (string)($payload['num_operacion'] ?? '');
        $payload['rfc_banco_emisor']    = (string)($payload['rfc_banco_emisor'] ?? '');
        $payload['cuenta_ordenante']    = (string)($payload['cuenta_ordenante'] ?? '');
        $payload['banco_receptor']      = (string)($payload['banco_receptor'] ?? '');
        $payload['cuenta_beneficiaria'] = (string)($payload['cuenta_beneficiaria'] ?? '');

        $pagos = $payload['pagos'] ?? [];
        if (!is_array($pagos)) $pagos = [];

        foreach ($pagos as $i => $p) {
            if (!is_array($p)) $p = [];
            $saldoAnt = (float)($p['saldo_anterior'] ?? 0);
            $pagado   = max((float)($p['monto_pago'] ?? 0), 0);
            $saldoInsolutoInput = array_key_exists('saldo_insoluto', $p)
                ? (float)($p['saldo_insoluto'] ?? 0)
                : null;

            $p['saldo_anterior'] = round($saldoAnt, 2);
            $p['monto_pago']     = round($pagado, 2);
            if ($saldoInsolutoInput === null) {
                $saldoInsolutoInput = $saldoAnt - $pagado;
            }
            $p['saldo_insoluto'] = max(round($saldoInsolutoInput, 2), 0);

            $p['num_parcialidad'] = (int)($p['num_parcialidad'] ?? 1);
            $p['moneda_dr']       = (string)($p['moneda_dr'] ?? 'MXN');
            $p['metodo_pago_dr']  = (string)($p['metodo_pago_dr'] ?? 'PPD');
            $p['uuid']            = strtoupper(trim((string)($p['uuid'] ?? '')));

            $p['objeto_imp'] = (bool)($p['objeto_imp'] ?? false);
            $p['impuestos']  = is_array($p['impuestos'] ?? null) ? $p['impuestos'] : [];

            if ($p['objeto_imp']) {
                $p['impuestos'] = $this->normalizePago20DocumentTaxes($p);
            } else {
                $p['impuestos'] = [];
            }

            $pagos[$i] = $p;
        }

        $payload['pagos'] = $pagos;
        return $payload;
    }

    private function normalizePago20DocumentTaxes(array $p): array
    {
        $items = is_array($p['impuestos'] ?? null) ? $p['impuestos'] : [];
        $impPagado = $this->moneyString($p['monto_pago'] ?? '0');
        if ($this->moneyToCents($impPagado) <= 0) return [];

        $original = $this->getOriginalPago20TaxRows($p);
        if (!empty($original['rows'])) {
            $saldoAnt = $this->moneyString($p['saldo_anterior'] ?? '0');
            if ($this->moneyToCents($saldoAnt) <= 0) {
                $saldoAnt = $this->moneyString($original['total'] ?? '0');
            }

            $paidCents = $this->moneyToCents($impPagado);
            $saldoCents = $this->moneyToCents($saldoAnt);
            $isFullPayment = $saldoCents > 0 && $paidCents === $saldoCents;
            $normalized = [];

            foreach ($original['rows'] as $row) {
                $tipoFactor = $this->mapFactorToSat((string)($row['factor'] ?? 'Tasa'));
                $rate = $this->rateString($row['tasa_cuota'] ?? $this->rateFromPercentString($row['tasa'] ?? '0'));
                $base = ($isFullPayment || $saldoCents <= 0)
                    ? $this->moneyString($row['base'] ?? '0')
                    : $this->prorateMoney($row['base'] ?? '0', $impPagado, $saldoAnt);
                $importe = $tipoFactor === 'Exento' ? null : $this->taxAmountFromBase($base, $rate);

                $normalized[] = [
                    'tipo' => strtoupper((string)($row['tipo'] ?? 'T')) === 'R' ? 'R' : 'T',
                    'impuesto' => $this->mapSatCodeToImpuesto((string)($row['impuesto_sat'] ?? $this->mapImpuestoToSatCode((string)($row['impuesto'] ?? 'IVA')))),
                    'impuesto_sat' => (string)($row['impuesto_sat'] ?? $this->mapImpuestoToSatCode((string)($row['impuesto'] ?? 'IVA'))),
                    'factor' => $tipoFactor,
                    'tasa' => $this->percentFromRateString($rate),
                    'tasa_cuota' => $rate,
                    'base' => $base,
                    'importe' => $importe,
                    'base_original' => $this->moneyString($row['base'] ?? '0'),
                    'importe_original' => $tipoFactor === 'Exento' ? null : $this->moneyString($row['importe_original'] ?? '0'),
                    'origen_xml' => true,
                ];
            }

            return $normalized;
        }

        if (empty($items)) return [];

        foreach ($items as $k => $it) {
            if (!is_array($it)) $it = [];

            $tipo = strtoupper(trim((string)($it['tipo'] ?? 'T')));
            $imp = strtoupper(trim((string)($it['impuesto'] ?? 'IVA')));
            $factor = $this->mapFactorToSat((string)($it['factor'] ?? 'Tasa'));
            $rate = $this->rateString($it['tasa_cuota'] ?? $this->rateFromPercentString($it['tasa'] ?? 0));
            $base = $this->moneyString($it['base'] ?? '0');

            if ($this->moneyToCents($base) <= 0) {
                $base = $impPagado;
            }

            $it['tipo'] = in_array($tipo, ['R', 'RET', 'RETENCION', 'RETENCIÃƒâ€œN'], true) ? 'R' : 'T';
            $it['impuesto'] = $imp;
            $it['impuesto_sat'] = $this->mapImpuestoToSatCode($imp);
            $it['factor'] = $factor;
            $it['base'] = $base;
            $it['tasa_cuota'] = $rate;
            $it['tasa'] = $this->percentFromRateString($rate);
            $it['importe'] = $factor === 'Exento' ? null : $this->taxAmountFromBase($base, $rate);
            $items[$k] = $it;
        }

        return $items;
    }

    private function getOriginalPago20TaxRows(array $p): array
    {
        $facturaId = (int)($p['factura_id'] ?? 0);
        $uuid = strtoupper(trim((string)($p['uuid'] ?? '')));

        if ($facturaId <= 0 && $uuid === '') {
            return ['total' => '0.00', 'rows' => []];
        }

        $userId = (int)auth()->id();
        $q = DB::table('facturas')->where('users_id', $userId);

        if ($facturaId > 0) {
            $q->where('id', $facturaId);
        } else {
            $q->whereRaw('UPPER(TRIM(uuid)) = ?', [$uuid]);
        }

        $factura = $q->first($this->facturaOriginalSelectColumns());
        if (!$factura) {
            return ['total' => '0.00', 'rows' => []];
        }

        $xml = property_exists($factura, 'xml') ? (string)($factura->xml ?? '') : '';
        $parsed = $this->extractPago20SourceTaxesFromFacturaXml($xml);
        $total = $this->getFacturaTotalFromRecord($factura) ?? ($parsed['total'] ?? '0.00');
        $parsed['total'] = $this->moneyString($total);

        return $parsed;
    }

    private function facturaOriginalSelectColumns(): array
    {
        $columns = ['id'];
        if (Schema::hasColumn('facturas', 'xml')) {
            $columns[] = 'xml';
        }

        foreach ($this->facturaTotalColumnCandidates() as $column) {
            if (Schema::hasColumn('facturas', $column)) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    private function facturaTotalColumnCandidates(): array
    {
        return [
            'total',
            'total_factura',
            'importe_total',
            'total_cfdi',
            'total_comprobante',
            'monto_total',
        ];
    }

    private function getFacturaTotalFromRecord($factura): ?string
    {
        if (!$factura) {
            return null;
        }

        foreach ($this->facturaTotalColumnCandidates() as $column) {
            if (property_exists($factura, $column) && $this->moneyToCents($factura->{$column} ?? '0') > 0) {
                return $this->moneyString($factura->{$column});
            }
        }

        if (property_exists($factura, 'xml')) {
            $xmlTotals = $this->extractPago20SourceTaxesFromFacturaXml((string)($factura->xml ?? ''));
            if ($this->moneyToCents($xmlTotals['total'] ?? '0') > 0) {
                return $this->moneyString($xmlTotals['total']);
            }
        }

        return null;
    }

    private function extractPago20SourceTaxesFromFacturaXml(?string $xml): array
    {
        $out = [
            'total' => '0.00',
            'subtotal' => '0.00',
            'moneda' => 'MXN',
            'metodo_pago' => '',
            'uuid' => '',
            'serie' => '',
            'folio' => '',
            'rows' => [],
        ];

        $xml = trim((string)$xml);
        if ($xml === '') return $out;

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        if (!$ok) return $out;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xp->registerNamespace('cfdi33', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $comp = $xp->query('/*[local-name()="Comprobante"]')->item(0);
        if ($comp instanceof \DOMElement) {
            $out['total'] = $this->moneyString($comp->getAttribute('Total') ?: $comp->getAttribute('total'));
            $out['subtotal'] = $this->moneyString($comp->getAttribute('SubTotal') ?: $comp->getAttribute('subTotal') ?: $comp->getAttribute('subtotal'));
            $out['moneda'] = (string)($comp->getAttribute('Moneda') ?: $comp->getAttribute('moneda') ?: 'MXN');
            $out['metodo_pago'] = (string)($comp->getAttribute('MetodoPago') ?: $comp->getAttribute('metodoPago'));
            $out['serie'] = (string)($comp->getAttribute('Serie') ?: $comp->getAttribute('serie'));
            $out['folio'] = (string)($comp->getAttribute('Folio') ?: $comp->getAttribute('folio'));
        }

        $tfd = $xp->query('//*[local-name()="TimbreFiscalDigital"]')->item(0);
        if ($tfd instanceof \DOMElement) {
            $out['uuid'] = strtoupper((string)($tfd->getAttribute('UUID') ?: $tfd->getAttribute('uuid')));
        }

        $rows = [];
        foreach ($xp->query('//*[local-name()="Concepto"]/*[local-name()="Impuestos"]/*[local-name()="Traslados"]/*[local-name()="Traslado"]') as $n) {
            if ($n instanceof \DOMElement) $rows[] = $this->taxRowFromFacturaXmlNode($n, 'T');
        }
        foreach ($xp->query('//*[local-name()="Concepto"]/*[local-name()="Impuestos"]/*[local-name()="Retenciones"]/*[local-name()="Retencion"]') as $n) {
            if ($n instanceof \DOMElement) $rows[] = $this->taxRowFromFacturaXmlNode($n, 'R');
        }

        if (empty($rows)) {
            foreach ($xp->query('/*[local-name()="Comprobante"]/*[local-name()="Impuestos"]/*[local-name()="Traslados"]/*[local-name()="Traslado"]') as $n) {
                if ($n instanceof \DOMElement) $rows[] = $this->taxRowFromFacturaXmlNode($n, 'T');
            }
            foreach ($xp->query('/*[local-name()="Comprobante"]/*[local-name()="Impuestos"]/*[local-name()="Retenciones"]/*[local-name()="Retencion"]') as $n) {
                if ($n instanceof \DOMElement) $rows[] = $this->taxRowFromFacturaXmlNode($n, 'R');
            }
        }

        $grouped = [];
        foreach ($rows as $row) {
            $key = implode('|', [$row['tipo'], $row['impuesto_sat'], $row['factor'], $row['tasa_cuota']]);
            if (!isset($grouped[$key])) {
                $grouped[$key] = $row;
                continue;
            }
            $grouped[$key]['base'] = $this->sumMoneyStrings($grouped[$key]['base'], $row['base']);
            if ($row['factor'] !== 'Exento') {
                $grouped[$key]['importe_original'] = $this->sumMoneyStrings($grouped[$key]['importe_original'] ?? '0.00', $row['importe_original'] ?? '0.00');
            }
        }

        $out['rows'] = array_values($grouped);
        return $out;
    }

    private function taxRowFromFacturaXmlNode(\DOMElement $n, string $tipo): array
    {
        $factor = $this->mapFactorToSat((string)($n->getAttribute('TipoFactor') ?: 'Tasa'));
        $rate = $factor === 'Exento' ? '0.000000' : $this->rateString((string)$n->getAttribute('TasaOCuota'));
        $impuestoSat = (string)$n->getAttribute('Impuesto');

        return [
            'tipo' => $tipo,
            'impuesto' => $this->mapSatCodeToImpuesto($impuestoSat),
            'impuesto_sat' => $impuestoSat !== '' ? $impuestoSat : '002',
            'factor' => $factor,
            'tasa' => $this->percentFromRateString($rate),
            'tasa_cuota' => $rate,
            'base' => $this->moneyString($n->getAttribute('Base')),
            'importe_original' => $factor === 'Exento' ? null : $this->moneyString($n->getAttribute('Importe')),
        ];
    }
    private function getEmisorDataForUser(int $userId): array
    {
        // Intento 1: tabla "users_perfil" (origen real en facturas)
        if (\Schema::hasTable('users_perfil')) {
            $row = DB::table('users_perfil')->where('users_id', $userId)->first();
            if ($row) {
                return [
                    'rfc'     => strtoupper(trim((string)($row->rfc ?? ''))),
                    'nombre'  => (string)($row->razon_social ?? $row->nombre ?? ''),
                    'regimen' => (string)($row->numero_regimen33 ?? $row->numero_regimen ?? $row->regimen_fiscal ?? $row->regimen ?? ''),
                    'cp'      => (string)($row->codigo_postal ?? $row->cp ?? ''),
                ];
            }
        }
        // Intento 1: tabla "informacion" (muy comÃƒÆ’Ã‚Âºn en FactuCare/iKontrol)
        if (\Schema::hasTable('informacion')) {
            $row = DB::table('informacion')->where('users_id', $userId)->first();
            if ($row) {
                return [
                    'rfc'     => strtoupper(trim((string)($row->rfc ?? ''))),
                    'nombre'  => (string)($row->razon_social ?? $row->nombre ?? ''),
                    'regimen' => (string)($row->regimen_fiscal ?? $row->regimen ?? ''),
                    'cp'      => (string)($row->codigo_postal ?? $row->cp ?? ''),
                ];
            }
        }

        // Intento 2: tabla users (si guardas ahÃƒÆ’Ã‚Â­ algo)
        $u = DB::table('users')->where('id', $userId)->first();
        if ($u) {
            return [
                'rfc'     => strtoupper(trim((string)($u->rfc ?? ''))),
                'nombre'  => (string)($u->razon_social ?? $u->name ?? ''),
                'regimen' => (string)($u->regimen_fiscal ?? ''),
                'cp'      => (string)($u->codigo_postal ?? ''),
            ];
        }

        return [];
    }

    /**
     * Intenta localizar:
     * - .cer (para noCertificado + Certificado base64)
     * - .key.pem (o key.pem) para firmar con MultiPac
     *
     * Ajusta aquÃƒÆ’Ã‚Â­ a TU fuente real (tabla sellos, storage, etc.).
     */
    private function getCsdForUser(int $userId): array
    {
        // OpciÃƒÆ’Ã‚Â³n A: storage/app/csd/{userId}/
        $base = storage_path("app/csd/{$userId}");
        $cer  = $base.'/csd.cer';
        $keyp = $base.'/csd.key.pem';

        // OpciÃƒÆ’Ã‚Â³n B: public/uploads/users_documentos/ (como el legacy)
        $legacy = public_path('uploads/users_documentos');
        $cer2   = $legacy."/{$userId}_csd.cer";
        $keyp2  = $legacy."/{$userId}_csd.key.pem";

        $cerPath = null;
        $keyPemPath = null;

        if (file_exists($cer) && file_exists($keyp)) {
            $cerPath = $cer;
            $keyPemPath = $keyp;
        } elseif (file_exists($cer2) && file_exists($keyp2)) {
            $cerPath = $cer2;
            $keyPemPath = $keyp2;
        }

        if (!$cerPath || !$keyPemPath) {
            throw new \RuntimeException('No se encontraron archivos CSD (.cer) y/o KEY PEM para timbrar. Configura la ruta en getCsdForUser().');
        }

        $noCert = $this->getNoCertificadoFromCer($cerPath);

        return [
            'cer_path'        => $cerPath,
            'key_pem_path'    => $keyPemPath,
            'no_certificado'  => $noCert,
        ];
    }

    private function getNoCertificadoFromCer(string $cerPath): string
    {
        $cer = file_get_contents($cerPath);
        if ($cer === false) return '';

        $cert = @openssl_x509_read($cer);
        if (!$cert) return '';

        $info = openssl_x509_parse($cert);
        // El nÃƒÆ’Ã‚Âºmero de certificado SAT normalmente viene como serialNumberHex o serialNumber
        // A veces hay que convertir hex a ascii. Dejamos ambas rutas.
        $serialHex = $info['serialNumberHex'] ?? null;
        if ($serialHex) {
            // convierte hex a ascii (ej: "3030..." -> "00...")
            $bin = hex2bin($serialHex);
            if ($bin !== false) {
                $ascii = preg_replace('/[^0-9]/', '', $bin);
                if ($ascii) return $ascii;
            }
        }

        $serial = $info['serialNumber'] ?? '';
        return preg_replace('/\D+/', '', (string)$serial);
    }

    private function getFolioPagoForUser(int $userId): array
    {
        if (!\Schema::hasTable('folios')) return ['', 0];

        // intenta columnas tÃƒÆ’Ã‚Â­picas
        $q = DB::table('folios')->where('users_id', $userId);

        if (\Schema::hasColumn('folios', 'tipo')) {
            $q->where('tipo', 'PAGO');
        } elseif (\Schema::hasColumn('folios', 'tipo_documento')) {
            $q->where('tipo_documento', 'PAGO');
        }

        $row = $q->first();
        if (!$row) return ['', 0];

        $serie = (string)($row->serie ?? '');
        $folio = (int)($row->folio ?? 0);

        return [$serie, $folio];
    }

    private function incrementFolioPagoForUser(int $userId, string $serie): void
    {
        if (!\Schema::hasTable('folios')) return;

        $q = DB::table('folios')->where('users_id', $userId);

        if (\Schema::hasColumn('folios', 'tipo')) {
            $q->where('tipo', 'PAGO');
        } elseif (\Schema::hasColumn('folios', 'tipo_documento')) {
            $q->where('tipo_documento', 'PAGO');
        }

        if (\Schema::hasColumn('folios', 'serie') && $serie !== '') {
            $q->where('serie', $serie);
        }

        $row = $q->lockForUpdate()->first();
        if (!$row) return;

        $folio = (int)($row->folio ?? 0);
        $folio++;

        DB::table('folios')->where('id', $row->id)->update(['folio' => $folio]);
    }

private function calculatePagos20Totals(array $payload): array
{
    $montoTotal = '0.00';
    $totalesSat = [];
    $sumTras = [];
    $sumRet = [];
    $retTotals = ['001' => '0.00', '002' => '0.00', '003' => '0.00'];

    foreach (($payload['pagos'] ?? []) as $dr) {
        $montoTotal = $this->sumMoneyStrings($montoTotal, $dr['monto_pago'] ?? '0');

        if (empty($dr['objeto_imp'])) continue;

        foreach (($dr['impuestos'] ?? []) as $it) {
            if (!is_array($it)) continue;

            $tipo = strtoupper((string)($it['tipo'] ?? 'T')) === 'R' ? 'R' : 'T';
            $impCode = (string)($it['impuesto_sat'] ?? $this->mapImpuestoToSatCode((string)($it['impuesto'] ?? 'IVA')));
            $factorSat = $this->mapFactorToSat((string)($it['factor'] ?? 'Tasa'));
            $tasaSat = $factorSat === 'Exento' ? '0.000000' : $this->rateString($it['tasa_cuota'] ?? $this->rateFromPercentString($it['tasa'] ?? 0));
            $base = $this->moneyString($it['base'] ?? '0');
            $importe = $factorSat === 'Exento' ? '0.00' : $this->moneyString($it['importe'] ?? '0');
            $key = $tipo.'|'.$impCode.'|'.$factorSat.'|'.$tasaSat;

            if ($tipo === 'R') {
                if (!isset($sumRet[$key])) $sumRet[$key] = ['base'=>'0.00','importe'=>'0.00','impuesto'=>$impCode,'factor'=>$factorSat,'tasa'=>$tasaSat];
                $sumRet[$key]['base'] = $this->sumMoneyStrings($sumRet[$key]['base'], $base);
                $sumRet[$key]['importe'] = $this->sumMoneyStrings($sumRet[$key]['importe'], $importe);
                if (isset($retTotals[$impCode])) $retTotals[$impCode] = $this->sumMoneyStrings($retTotals[$impCode], $importe);
            } else {
                if (!isset($sumTras[$key])) $sumTras[$key] = ['base'=>'0.00','importe'=>'0.00','impuesto'=>$impCode,'factor'=>$factorSat,'tasa'=>$tasaSat];
                $sumTras[$key]['base'] = $this->sumMoneyStrings($sumTras[$key]['base'], $base);
                $sumTras[$key]['importe'] = $this->sumMoneyStrings($sumTras[$key]['importe'], $importe);
            }
        }
    }

    foreach ($sumTras as $row) {
        if ($row['impuesto'] !== '002') continue;

        if ($row['factor'] === 'Exento') {
            $totalesSat['TotalTrasladosBaseIVAExento'] = $this->sumMoneyStrings($totalesSat['TotalTrasladosBaseIVAExento'] ?? '0.00', $row['base']);
            continue;
        }

        if ($row['factor'] !== 'Tasa') continue;

        $suffix = match ($row['tasa']) {
            '0.160000' => 'IVA16',
            '0.080000' => 'IVA8',
            '0.000000' => 'IVA0',
            default => null,
        };

        if ($suffix !== null) {
            $totalesSat['TotalTrasladosBase'.$suffix] = $this->sumMoneyStrings($totalesSat['TotalTrasladosBase'.$suffix] ?? '0.00', $row['base']);
            $totalesSat['TotalTrasladosImpuesto'.$suffix] = $this->sumMoneyStrings($totalesSat['TotalTrasladosImpuesto'.$suffix] ?? '0.00', $row['importe']);
        }
    }

    if ($this->moneyToCents($retTotals['002']) > 0) $totalesSat['TotalRetencionesIVA'] = $retTotals['002'];
    if ($this->moneyToCents($retTotals['001']) > 0) $totalesSat['TotalRetencionesISR'] = $retTotals['001'];
    if ($this->moneyToCents($retTotals['003']) > 0) $totalesSat['TotalRetencionesIEPS'] = $retTotals['003'];

    return [$montoTotal, $totalesSat, [
        'traslados' => array_values($sumTras),
        'retenciones' => array_values($sumRet),
    ]];
}

private function logPagos20TaxValidation(array $payload, $montoTotal, array $totalesSat, array $impPSums): void
{
    $docs = [];
    foreach (($payload['pagos'] ?? []) as $p) {
        $items = [];
        foreach (($p['impuestos'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $items[] = [
                'tipo' => $it['tipo'] ?? null,
                'impuesto' => $it['impuesto_sat'] ?? $this->mapImpuestoToSatCode((string)($it['impuesto'] ?? 'IVA')),
                'factor' => $it['factor'] ?? null,
                'tasa' => $it['tasa_cuota'] ?? null,
                'base_original' => $it['base_original'] ?? null,
                'importe_original' => $it['importe_original'] ?? null,
                'base_dr' => $it['base'] ?? null,
                'importe_dr' => $it['importe'] ?? null,
                'origen_xml' => !empty($it['origen_xml']),
            ];
        }

        $saldoAnt = $this->moneyString($p['saldo_anterior'] ?? '0');
        $impPagado = $this->moneyString($p['monto_pago'] ?? '0');
        $docs[] = [
            'uuid' => $p['uuid'] ?? '',
            'imp_saldo_ant' => $saldoAnt,
            'imp_pagado' => $impPagado,
            'factor_prorrateo' => $this->ratioString($impPagado, $saldoAnt),
            'impuestos' => $items,
        ];
    }

    Log::debug('Pagos20 tax normalization before PAC', [
        'monto_total_pagos' => $this->moneyString($montoTotal),
        'documentos' => $docs,
        'impuestos_p' => $impPSums,
        'totales_pagos20' => $totalesSat,
    ]);
}

private function moneyString($value): string
{
    return $this->formatScaledInt($this->decimalToScaledInt($value, 2), 2);
}

private function rateString($value): string
{
    $value = trim((string)$value);
    if ($value === '') return '0.000000';
    return $this->formatScaledInt($this->decimalToScaledInt($value, 6), 6);
}

private function rateFromPercentString($percent): string
{
    $micros = $this->decimalToScaledInt($percent, 6);
    return $this->formatScaledInt($this->roundDivide($micros, 100), 6);
}

private function percentFromRateString($rate): string
{
    return $this->formatScaledInt($this->decimalToScaledInt($rate, 6) * 100, 6);
}

private function moneyToCents($value): int
{
    return $this->decimalToScaledInt($value, 2);
}

private function rateToMicros($value): int
{
    return $this->decimalToScaledInt($value, 6);
}

private function sumMoneyStrings($a, $b): string
{
    return $this->formatScaledInt($this->moneyToCents($a) + $this->moneyToCents($b), 2);
}

private function prorateMoney($amount, $paid, $saldo): string
{
    $saldoCents = $this->moneyToCents($saldo);
    if ($saldoCents <= 0) return $this->moneyString($amount);
    $cents = $this->roundDivide($this->moneyToCents($amount) * $this->moneyToCents($paid), $saldoCents);
    return $this->formatScaledInt($cents, 2);
}

private function taxAmountFromBase($base, $rate): string
{
    $cents = $this->roundDivide($this->moneyToCents($base) * $this->rateToMicros($rate), 1000000);
    return $this->formatScaledInt($cents, 2);
}

private function ratioString($paid, $saldo): string
{
    $saldoCents = $this->moneyToCents($saldo);
    if ($saldoCents <= 0) return '0.000000';
    return $this->formatScaledInt($this->roundDivide($this->moneyToCents($paid) * 1000000, $saldoCents), 6);
}

private function decimalToScaledInt($value, int $scale): int
{
    $value = trim((string)($value ?? '0'));
    $value = str_replace([',', ' '], '', $value);
    if ($value === '' || $value === '-') return 0;

    $negative = str_starts_with($value, '-');
    if ($negative) $value = substr($value, 1);

    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $whole = preg_replace('/\D/', '', $whole) ?: '0';
    $fraction = preg_replace('/\D/', '', $fraction) ?: '';
    $roundDigit = strlen($fraction) > $scale ? (int)$fraction[$scale] : 0;
    $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');

    $scaled = ((int)$whole * (10 ** $scale)) + (int)$fraction;
    if ($roundDigit >= 5) $scaled++;

    return $negative ? -$scaled : $scaled;
}

private function formatScaledInt(int $value, int $scale): string
{
    $negative = $value < 0;
    $value = abs($value);
    $factor = 10 ** $scale;
    $whole = intdiv($value, $factor);
    $fraction = $value % $factor;
    return ($negative ? '-' : '') . $whole . '.' . str_pad((string)$fraction, $scale, '0', STR_PAD_LEFT);
}

private function roundDivide(int $numerator, int $denominator): int
{
    if ($denominator <= 0) return 0;
    if ($numerator >= 0) return intdiv($numerator + intdiv($denominator, 2), $denominator);
    return -intdiv(abs($numerator) + intdiv($denominator, 2), $denominator);
}

private function validatePagos20TaxConsistency(array $payload): void
{
    foreach (($payload['pagos'] ?? []) as $p) {
        $uuid = strtoupper(trim((string)($p['uuid'] ?? '')));
        foreach (($p['impuestos'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $tipo = strtoupper((string)($it['tipo'] ?? 'T')) === 'R' ? 'R' : 'T';
            $factor = $this->mapFactorToSat((string)($it['factor'] ?? 'Tasa'));
            $base = $this->moneyString($it['base'] ?? '0');
            if ($this->moneyToCents($base) < 0) {
                throw new \RuntimeException("Impuesto inconsistente en UUID {$uuid}: BaseDR no puede ser negativa ({$base}).");
            }
            if ($factor === 'Exento') continue;

            if (!array_key_exists('tasa_cuota', $it) && !array_key_exists('tasa', $it)) {
                throw new \RuntimeException("Impuesto inconsistente en UUID {$uuid}: TasaOCuotaDR es obligatoria para TipoFactorDR {$factor}.");
            }
            if (!array_key_exists('importe', $it)) {
                throw new \RuntimeException("Impuesto inconsistente en UUID {$uuid}: ImporteDR es obligatorio para TipoFactorDR {$factor}.");
            }

            $rate = $this->rateString($it['tasa_cuota'] ?? $this->rateFromPercentString($it['tasa'] ?? 0));
            $sent = $this->moneyString($it['importe'] ?? '0');
            $calculated = $this->taxAmountFromBase($base, $rate);
            if ($sent !== $calculated) {
                throw new \RuntimeException("Impuesto inconsistente en UUID {$uuid}: BaseDR {$base} x tasa {$rate} = {$calculated}, pero se intentaba enviar {$sent}.");
            }
        }
    }
}

private function mapImpuestoToSatCode(string $imp): string
{
    $imp = strtoupper(trim($imp));
    return match ($imp) {
        'ISR'  => '001',
        'IVA'  => '002',
        'IEPS' => '003',
        default => '002',
    };
}

private function mapSatCodeToImpuesto(string $code): string
{
    return match (trim($code)) {
        '001' => 'ISR',
        '003' => 'IEPS',
        default => 'IVA',
    };
}

private function mapFactorToSat(string $fac): string
{
    $fac = strtolower(trim($fac));
    if ($fac === 'exento') return 'Exento';
    if ($fac === 'cuota') return 'Cuota';
    return 'Tasa';
}

private function fmtMoney($n): string
{
    return $this->moneyString($n);
}

private function fmtTc(float $n): string
{
    // Tipo de cambio con 6 decimales usualmente es aceptado
    return number_format((float)$n, 6, '.', '');
}

private function fmtRateFromPercent($percent): string
{
    return $this->rateFromPercentString($percent);
}

private function extractUuidFromTimbrado(string $xmlTimbrado): string
{
    libxml_use_internal_errors(true);

    $dom = new \DOMDocument();
    if (!$dom->loadXML($xmlTimbrado, LIBXML_NONET)) return '';

    $xp = new \DOMXPath($dom);
    $xp->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

    $tfd = $xp->query('//tfd:TimbreFiscalDigital')->item(0);
    if ($tfd instanceof \DOMElement) {
        return strtoupper(trim($tfd->getAttribute('UUID')));
    }
    return '';
}

private function getLogoBase64ForUser(int $userId): ?string
{
    // Ajusta si tu logo estÃƒÆ’Ã‚Â¡ en otra parte.
    // Lo dejo seguro: si no existe, regresa null.
    $path1 = public_path("uploads/users_logos/thumbnails/{$userId}.png");
    if (file_exists($path1)) return base64_encode(file_get_contents($path1));

    return null;
}

private function insertComplementoDb(int $userId, array $payload, $cliente, array $emisor, array $extra): int
{
    $data = [
        'users_id' => $userId,
        'uuid'     => $extra['uuid'] ?? null,
        'estatus'  => $extra['estatus'] ?? 'TIMBRADA',

        'serie'    => (string)($payload['serie_pago'] ?? ''),
        'folio'    => (int)($payload['folio_pago'] ?? 0),

        'fecha_documento' => (string)($payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? '')),
        'fecha_pago' => (string)($payload['fecha_pago'] ?? ''),
        'cliente_id' => (int)($payload['cliente_id'] ?? 0),

        'forma_pago_p'  => (string)($payload['forma_pago_p'] ?? '03'),
        'moneda_p'      => (string)($payload['moneda_p'] ?? 'MXN'),
        'tipo_cambio_p' => (float)($payload['tipo_cambio_p'] ?? 1),

        'num_operacion'       => (string)($payload['num_operacion'] ?? ''),
        'rfc_banco_emisor'    => (string)($payload['rfc_banco_emisor'] ?? ''),
        'cuenta_ordenante'    => (string)($payload['cuenta_ordenante'] ?? ''),
        'banco_receptor'      => (string)($payload['banco_receptor'] ?? ''),
        'cuenta_beneficiaria' => (string)($payload['cuenta_beneficiaria'] ?? ''),

        'xml_solicitud' => $extra['xml_solicitud'] ?? null,
        'xml'           => $extra['xml_timbrado'] ?? null,
        'pdf'           => $extra['pdf_b64'] ?? null,

        'created_at' => now(),
        'updated_at' => now(),
    ];

    // Inserta solo columnas existentes (para no tronar)
    $cols = \Schema::hasTable('complementos') ? \Schema::getColumnListing('complementos') : [];
    if ($cols) {
        $data = array_filter(
            $data,
            fn($v, $k) => in_array($k, $cols, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    return (int)DB::table('complementos')->insertGetId($data);
}

private function insertComplementoPagosDb(int $complementoId, array $payload): void
{
    if (!\Schema::hasTable('complementos_pagos')) return;

    $cols = \Schema::getColumnListing('complementos_pagos');

    foreach (($payload['pagos'] ?? []) as $p) {
        $row = [
            'users_complementos_id' => $complementoId,
            'documento_id'          => (string)($p['uuid'] ?? ''), // legacy usa documento_id=UUID
            'parcialidad'           => (int)($p['num_parcialidad'] ?? 1),
            'saldo_anterior'        => (float)($p['saldo_anterior'] ?? 0),
            'monto_pago'            => (float)($p['monto_pago'] ?? 0),
            'saldo_insoluto'        => (float)($p['saldo_insoluto'] ?? 0),
            'created_at'            => now(),
            'updated_at'            => now(),
        ];

        $row = array_filter(
            $row,
            fn($v, $k) => in_array($k, $cols, true),
            ARRAY_FILTER_USE_BOTH
        );

        DB::table('complementos_pagos')->insert($row);
    }
}


    /**
     * FactuCare: siguiente parcialidad sugerida por UUID
     */
    private function siguienteParcialidadPorUuidFactuCare(int $userId, string $uuid): int
    {
        $uuid = strtoupper(trim($uuid));
        if ($uuid === '') return 1;

        if (!Schema::hasTable('complementos_pagos')) {
            return 1;
        }

        $q = DB::table('complementos_pagos as cp')
            ->whereRaw('UPPER(TRIM(cp.documento_id)) = ?', [$uuid]);

        if (Schema::hasTable('complementos')) {
            $q->join('complementos as c', 'c.id', '=', 'cp.users_complementos_id')
              ->where('c.users_id', $userId)
              ->whereNotIn(DB::raw('UPPER(c.estatus)'), ['CANCELADA', 'CANCELADO']);
        }

        $maxPar = (int)($q->max('cp.parcialidad') ?? 0);
        return max(1, $maxPar + 1);
    }

    /**
     * Parser bÃƒÆ’Ã‚Â¡sico CFDI 3.3 / 4.0
     */
    private function parseCfdiBasicsFromXml(string $xmlString): array
    {
        $out = [
            'serie' => '',
            'folio' => '',
            'total' => 0.0,
            'fecha' => '',
            'uuid'  => '',
            'moneda'=> 'MXN',
            'metodo_pago' => 'PPD',
        ];

        $xmlString = trim($xmlString);
        if ($xmlString === '') return $out;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlString, LIBXML_NONET)) return $out;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');
        $xp->registerNamespace('tfd',   'http://www.sat.gob.mx/TimbreFiscalDigital');

        $comp = $xp->query('//cfdi4:Comprobante | //cfdi3:Comprobante')->item(0);
        if ($comp instanceof \DOMElement) {
            $out['serie'] = $comp->getAttribute('Serie') ?: $comp->getAttribute('serie');
            $out['folio'] = $comp->getAttribute('Folio') ?: $comp->getAttribute('folio');

            $totalRaw = $comp->getAttribute('Total') ?: $comp->getAttribute('total');
            $totalRaw = str_replace([',',' '], '', (string)$totalRaw);
            $out['total'] = (float)$totalRaw;

            $out['fecha'] = $comp->getAttribute('Fecha') ?: $comp->getAttribute('fecha');
            $out['moneda'] = $comp->getAttribute('Moneda') ?: $comp->getAttribute('moneda') ?: 'MXN';
            $out['metodo_pago'] = $comp->getAttribute('MetodoPago') ?: $comp->getAttribute('metodoPago') ?: 'PPD';
        }

        $tfd = $xp->query('//tfd:TimbreFiscalDigital')->item(0);
        if ($tfd instanceof \DOMElement) {
            $out['uuid'] = $tfd->getAttribute('UUID') ?: $tfd->getAttribute('uuid');
        }

        return $out;
    }

    private function parseMontoTotalPagosFromXml(string $xmlString): float
    {
        $xmlString = trim($xmlString);
        if ($xmlString === '') return 0.0;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlString, LIBXML_NONET)) return 0.0;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');

        $tot = $xp->query('//pago20:Totales')->item(0);
        if (!$tot instanceof \DOMElement) return 0.0;

        $raw = (string)$tot->getAttribute('MontoTotalPagos');
        $raw = str_replace([',', ' '], '', $raw);
        return (float)$raw;
    }

    private function parsePagos20DetailsFromXml(string $xmlString): array
    {
        $out = ['totales' => [], 'pagos' => []];

        $xmlString = trim($xmlString);
        if ($xmlString === '') return $out;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xmlString, LIBXML_NONET);
        libxml_clear_errors();
        if (!$ok) return $out;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');

        $tot = $xp->query('//pago20:Totales')->item(0);
        if ($tot instanceof \DOMElement) {
            foreach (['MontoTotalPagos', 'TotalTrasladosBaseIVA16', 'TotalTrasladosImpuestoIVA16'] as $attr) {
                if ($tot->hasAttribute($attr)) {
                    $out['totales'][$attr] = (string)$tot->getAttribute($attr);
                }
            }
        }

        foreach ($xp->query('//pago20:Pago') as $pagoNode) {
            if (!$pagoNode instanceof \DOMElement) continue;

            $pago = [
                'FechaPago' => (string)$pagoNode->getAttribute('FechaPago'),
                'MonedaP' => (string)$pagoNode->getAttribute('MonedaP'),
                'TipoCambioP' => (string)$pagoNode->getAttribute('TipoCambioP'),
                'Monto' => (string)$pagoNode->getAttribute('Monto'),
                'doctos' => [],
                'traslados_p' => [],
            ];

            foreach ($xp->query('pago20:DoctoRelacionado', $pagoNode) as $docNode) {
                if (!$docNode instanceof \DOMElement) continue;

                $doc = [
                    'IdDocumento' => (string)$docNode->getAttribute('IdDocumento'),
                    'ObjetoImpDR' => (string)$docNode->getAttribute('ObjetoImpDR'),
                    'ImpPagado' => (string)$docNode->getAttribute('ImpPagado'),
                    'MonedaDR' => (string)$docNode->getAttribute('MonedaDR'),
                    'EquivalenciaDR' => (string)$docNode->getAttribute('EquivalenciaDR'),
                    'traslados_dr' => [],
                ];

                foreach ($xp->query('pago20:ImpuestosDR/pago20:TrasladosDR/pago20:TrasladoDR', $docNode) as $tdr) {
                    if ($tdr instanceof \DOMElement) {
                        $doc['traslados_dr'][] = [
                            'BaseDR' => (string)$tdr->getAttribute('BaseDR'),
                            'ImpuestoDR' => (string)$tdr->getAttribute('ImpuestoDR'),
                            'TipoFactorDR' => (string)$tdr->getAttribute('TipoFactorDR'),
                            'TasaOCuotaDR' => (string)$tdr->getAttribute('TasaOCuotaDR'),
                            'ImporteDR' => (string)$tdr->getAttribute('ImporteDR'),
                        ];
                    }
                }

                $pago['doctos'][] = $doc;
            }

            foreach ($xp->query('pago20:ImpuestosP/pago20:TrasladosP/pago20:TrasladoP', $pagoNode) as $tp) {
                if ($tp instanceof \DOMElement) {
                    $pago['traslados_p'][] = [
                        'BaseP' => (string)$tp->getAttribute('BaseP'),
                        'ImpuestoP' => (string)$tp->getAttribute('ImpuestoP'),
                        'TipoFactorP' => (string)$tp->getAttribute('TipoFactorP'),
                        'TasaOCuotaP' => (string)$tp->getAttribute('TasaOCuotaP'),
                        'ImporteP' => (string)$tp->getAttribute('ImporteP'),
                    ];
                }
            }

            $out['pagos'][] = $pago;
        }

        return $out;
    }

    private function catalogoFormasPago(): array
    {
        return [
            ['id'=>'01','text'=>'01 - Efectivo'],
            ['id'=>'02','text'=>'02 - Cheque nominativo'],
            ['id'=>'03','text'=>'03 - Transferencia electrÃƒÆ’Ã‚Â³nica de fondos'],
            ['id'=>'04','text'=>'04 - Tarjeta de crÃƒÆ’Ã‚Â©dito'],
            ['id'=>'05','text'=>'05 - Monedero electrÃƒÆ’Ã‚Â³nico'],
            ['id'=>'06','text'=>'06 - Dinero electrÃƒÆ’Ã‚Â³nico'],
            ['id'=>'08','text'=>'08 - Vales de despensa'],
            ['id'=>'12','text'=>'12 - DaciÃƒÆ’Ã‚Â³n en pago'],
            ['id'=>'13','text'=>'13 - Pago por subrogaciÃƒÆ’Ã‚Â³n'],
            ['id'=>'14','text'=>'14 - Pago por consignaciÃƒÆ’Ã‚Â³n'],
            ['id'=>'15','text'=>'15 - CondonaciÃƒÆ’Ã‚Â³n'],
            ['id'=>'17','text'=>'17 - CompensaciÃƒÆ’Ã‚Â³n'],
            ['id'=>'23','text'=>'23 - NovaciÃƒÆ’Ã‚Â³n'],
            ['id'=>'24','text'=>'24 - ConfusiÃƒÆ’Ã‚Â³n'],
            ['id'=>'25','text'=>'25 - RemisiÃƒÆ’Ã‚Â³n de deuda'],
            ['id'=>'26','text'=>'26 - PrescripciÃƒÆ’Ã‚Â³n o caducidad'],
            ['id'=>'27','text'=>'27 - A satisfacciÃƒÆ’Ã‚Â³n del acreedor'],
            ['id'=>'28','text'=>'28 - Tarjeta de dÃƒÆ’Ã‚Â©bito'],
            ['id'=>'29','text'=>'29 - Tarjeta de servicios'],
            ['id'=>'30','text'=>'30 - AplicaciÃƒÆ’Ã‚Â³n de anticipos'],
            ['id'=>'31','text'=>'31 - Intermediario pagos'],
            ['id'=>'99','text'=>'99 - Por definir'],
        ];
    }

    private function catalogoMonedas(): array
    {
        return [
            ['id'=>'MXN','text'=>'MXN - Peso Mexicano'],
            ['id'=>'USD','text'=>'USD - DÃƒÆ’Ã‚Â³lar'],
            ['id'=>'EUR','text'=>'EUR - Euro'],
        ];
    }


    //////////////////////////////////////////timbrado


    public function timbrar(Request $request)
    {
        $userId = auth()->id();
        $modo = (string) $request->input('modo', 'timbrar');
        Log::info('Complementos.timbrar start', [
            'user_id' => $userId,
            'modo' => $modo,
            'has_payload' => $request->has('payload'),
        ]);

        // Payload: POST o sesiÃƒÆ’Ã‚Â³n
        $payload = $request->input('payload');
        if (!$payload) {
            $payload = session('complemento_draft', []);
        }
        if (!is_array($payload)) {
            $payload = (array) json_decode((string)$payload, true);
        }
        if (empty($payload)) {
            Log::warning('Complementos.timbrar empty payload', [
                'user_id' => $userId,
            ]);
            return back()->with('error', 'No hay datos del complemento en sesiÃƒÆ’Ã‚Â³n. Regresa a crear el complemento.');
        }

        // Normaliza folio/serie (y alinea serie_pago/folio_pago con serie/folio)
        $payload = $this->normalizarFolioComplementoEnPayload($userId, $payload);
        session(['complemento_draft' => $payload]);

        // Cliente
        $clienteId = (int)($payload['cliente_id'] ?? 0);
        $cliente = DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            return back()->with('error', 'Cliente invÃƒÆ’Ã‚Â¡lido.');
        }

        try {
            Log::info('Complementos.timbrar before xml', [
                'user_id' => $userId,
                'cliente_id' => $clienteId,
            ]);
            // 1) Generar XML Pagos 2.0
            $xmlOriginal = $this->generarXmlPagos20DesdePayload($userId, $payload, $cliente);
            Log::info('Complementos.timbrar xml generated', [
                'user_id' => $userId,
                'xml_len' => strlen($xmlOriginal),
            ]);

            if ($modo === 'debug') {
                return response($xmlOriginal, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
            }

            // 2) Timbrar con PAC
            $resp = $this->timbrarConPacMultipac($userId, $xmlOriginal);
            Log::info('Complementos.timbrar pac response', [
                'user_id' => $userId,
                'has_xml' => !empty($resp['xml'] ?? ''),
                'uuid' => $resp['uuid'] ?? null,
                'code' => $resp['code'] ?? null,
                'mensaje' => $resp['mensaje'] ?? null,
            ]);

            $xmlTimbrado = (string)($resp['xml'] ?? '');
            $uuid        = (string)($resp['uuid'] ?? '');
            $acuseXml    = isset($resp['acuse']) ? (string)$resp['acuse'] : null;

            if (trim($xmlTimbrado) === '') {
                throw new \RuntimeException((string)($resp['mensaje'] ?? 'PAC no devolviÃƒÆ’Ã‚Â³ XML timbrado.'));
            }

            // 3) PDF: usa plantilla pagos2 (NO la de facturas)
            $pdfB64 = '';
            try {
                $pdfB64 = $this->generarPdfBase64ComplementoPagos2($userId, $xmlTimbrado, $payload, $cliente);
            } catch (\Throwable $e) {
                Log::warning('Complementos.timbrar pdf error', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                if (method_exists($this, 'generarPdfBase64FallbackDompdf')) {
                    $pdfB64 = $this->generarPdfBase64FallbackDompdf($xmlTimbrado);
                }
            }

            // 4) Guardar + avanzar folio (atÃƒÆ’Ã‚Â³mico)
            $compId = DB::transaction(function () use (
                $userId, $payload, $cliente, $xmlOriginal, $xmlTimbrado, $uuid, $pdfB64, $acuseXml
            ) {
                $compId = $this->guardarComplementoTimbrado(
                    $userId,
                    $payload,
                    $cliente,
                    $xmlOriginal,
                    $xmlTimbrado,
                    $uuid,
                    $pdfB64 ?: null,
                    $acuseXml
                );

                // Avanzar folio en tabla folios
                $folioId = (int)($payload['folio_id'] ?? 0);
                if ($folioId > 0) {
                    $this->avanzarFolioComplemento($userId, $folioId);
                }

                $this->consumirTimbre($userId);

                return (int)$compId;
            });

            session()->forget('complemento_draft');

            return redirect()
                ->route('complementos.ver', $compId)
                ->with('success', 'Complemento timbrado correctamente.');

        } catch (\Throwable $e) {
            Log::error('Complementos.timbrar error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session(['complemento_draft' => $payload]);
            return $this->renderPreviewFromPayload($payload, 'Error al timbrar complemento: ' . $e->getMessage());
        }
    }

    private function normalizarFolioComplementoEnPayload(int $userId, array $payload): array
    {
        // 1) Si UI manda serie_pago/folio_pago, los usamos como fuente de verdad
        $serieUI = trim((string)($payload['serie_pago'] ?? ''));
        $folioUI = (string)($payload['folio_pago'] ?? '');

        // 2) Alias backwards: serie/folio (por si algo viejo lo usa)
        if ($serieUI !== '') $payload['serie'] = $serieUI;
        if ($folioUI !== '' && $folioUI !== '0') $payload['folio'] = (string)$folioUI;

        // Si YA hay serie/folio, aseguramos los _pago y resolvemos folio_id para avanzar +1 al timbrar.
        $serie = trim((string)($payload['serie'] ?? ''));
        $folio = trim((string)($payload['folio'] ?? ''));

        if ($serie !== '' && $folio !== '' && $folio !== '0') {
            if (empty($payload['folio_id']) && Schema::hasTable('folios')) {
                $qFind = DB::table('folios')->where('users_id', $userId)->where('serie', $serie);
                if (Schema::hasColumn('folios', 'tipo_documento')) {
                    $qFind->whereIn('tipo_documento', ['PAGO', 'P']);
                } elseif (Schema::hasColumn('folios', 'tipo')) {
                    $qFind->whereIn('tipo', ['PAGO', 'P']);
                }
                $folioDb = $qFind->orderBy('id')->first(['id']);
                if ($folioDb) {
                    $payload['folio_id'] = (int)$folioDb->id;
                }
            }
            $payload['serie_pago'] = $serie;
            $payload['folio_pago'] = (int)$folio;
            return $payload;
        }

        // 3) Resolver desde tabla folios tipo PAGO
        if (!Schema::hasTable('folios')) {
            return $payload;
        }

        $folioId = (int)($payload['folio_id'] ?? 0);

        $q = DB::table('folios')->where('users_id', $userId);

        if (Schema::hasColumn('folios', 'tipo_documento')) {
            $q->whereIn('tipo_documento', ['PAGO', 'P']);
        } elseif (Schema::hasColumn('folios', 'tipo')) {
            $q->whereIn('tipo', ['PAGO', 'P']);
        }

        if ($folioId > 0) $q->where('id', $folioId);

        $f = $q->orderBy('id')->first();

        if (!$f) {
            return $payload;
        }

        // Detectar columna de folio actual
        $folioActual = null;
        foreach (['folio_actual', 'consecutivo', 'folio', 'ultimo_folio'] as $col) {
            if (isset($f->$col)) { $folioActual = (int)$f->$col; break; }
        }
        if ($folioActual === null) $folioActual = 0;

        $serie = (string)($f->serie ?? '');
        $folio = $folioActual;

        $payload['folio_id']   = (int)$f->id;
        $payload['serie']      = $serie;
        $payload['folio']      = (string)$folio;

        // fuente principal UI
        $payload['serie_pago'] = $serie;
        $payload['folio_pago'] = (int)$folio;

        return $payload;
    }

    private function avanzarFolioComplemento(int $userId, int $folioId): void
    {
        if (!Schema::hasTable('folios')) return;

        DB::transaction(function () use ($userId, $folioId) {

            $row = DB::table('folios')
                ->where('users_id', $userId)
                ->where('id', $folioId)
                ->lockForUpdate()
                ->first();

            if (!$row) return;

            // detecta columna correcta a incrementar
            $colToInc = null;
            foreach (['folio_actual', 'consecutivo', 'folio', 'ultimo_folio'] as $c) {
                if (property_exists($row, $c)) { $colToInc = $c; break; }
            }
            if (!$colToInc) return;

            DB::table('folios')
                ->where('id', $row->id)
                ->update([$colToInc => DB::raw($colToInc.' + 1')]);
        });
    }

    private function consumirTimbre(int $userId): void
    {
        $u = DB::table('users')
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$u) {
            throw new \RuntimeException("No existe el usuario {$userId}.");
        }

        if (!isset($u->timbres_disponibles)) {
            throw new \RuntimeException('La columna users.timbres_disponibles no existe o no esta disponible.');
        }

        $actual = (int)$u->timbres_disponibles;
        if ($actual <= 0) {
            throw new \RuntimeException('No tienes timbres disponibles para timbrar.');
        }

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'timbres_disponibles' => $actual - 1,
            ]);
    }

    private function appendImpuestosDR(\DOMDocument $dom, \DOMElement $docRel, array $items): void
    {
        $pagoNs = 'http://www.sat.gob.mx/Pagos20';
        if (!is_array($items) || !count($items)) return;

        $impDR = $dom->createElementNS($pagoNs, 'pago20:ImpuestosDR');
        $trasDR = null;
        $retDR  = null;

        foreach ($items as $it) {
            if (!is_array($it)) continue;

            $tipo = strtoupper(trim((string)($it['tipo'] ?? 'T'))) === 'R' ? 'R' : 'T';
            $impCode = (string)($it['impuesto_sat'] ?? $this->mapImpuestoToSatCode((string)($it['impuesto'] ?? 'IVA')));
            $tipoFactor = $this->mapFactorToSat((string)($it['factor'] ?? 'Tasa'));
            $base = $this->moneyString($it['base'] ?? '0');
            $rate = $tipoFactor === 'Exento' ? '0.000000' : $this->rateString($it['tasa_cuota'] ?? $this->rateFromPercentString($it['tasa'] ?? 0));
            $importe = $tipoFactor === 'Exento' ? null : $this->taxAmountFromBase($base, $rate);

            if ($tipo === 'R') {
                if (!$retDR) $retDR = $dom->createElementNS($pagoNs, 'pago20:RetencionesDR');
                $n = $dom->createElementNS($pagoNs, 'pago20:RetencionDR');
                $n->setAttribute('BaseDR', $base);
                $n->setAttribute('ImpuestoDR', $impCode);
                $n->setAttribute('TipoFactorDR', $tipoFactor);
                if ($tipoFactor !== 'Exento') {
                    $n->setAttribute('TasaOCuotaDR', $rate);
                    $n->setAttribute('ImporteDR', $importe);
                }
                $retDR->appendChild($n);
            } else {
                if (!$trasDR) $trasDR = $dom->createElementNS($pagoNs, 'pago20:TrasladosDR');
                $n = $dom->createElementNS($pagoNs, 'pago20:TrasladoDR');
                $n->setAttribute('BaseDR', $base);
                $n->setAttribute('ImpuestoDR', $impCode);
                $n->setAttribute('TipoFactorDR', $tipoFactor);
                if ($tipoFactor !== 'Exento') {
                    $n->setAttribute('TasaOCuotaDR', $rate);
                    $n->setAttribute('ImporteDR', $importe);
                }
                $trasDR->appendChild($n);
            }
        }

        if ($retDR)  $impDR->appendChild($retDR);
        if ($trasDR) $impDR->appendChild($trasDR);
        if (!$retDR && !$trasDR) return;
        $docRel->appendChild($impDR);
    }
    private function generarPdfBase64ComplementoPagos2(int $userId, string $xmlTimbrado, array $payload, object $cliente): string
    {
        $xmlB64 = base64_encode($xmlTimbrado);

        // Plantilla legacy para complemento de pago
        $plantilla = 'pagos20';

        $logoB64 = $this->getLogoBase64ForUser($userId) ?? '';

        $jsonArr = [
            'tipoComprobante' => 'Pago',
            'receptor_rfc' => (string)($cliente->rfc ?? ''),
            'receptor_razon_social' => (string)($cliente->razon_social ?? ''),
            'serie' => (string)($payload['serie_pago'] ?? ($payload['serie'] ?? '')),
            'folio' => (string)($payload['folio_pago'] ?? ($payload['folio'] ?? '')),
        ];

        $jsonB64 = base64_encode(json_encode($jsonArr, JSON_UNESCAPED_UNICODE));

        $mp = new \App\Extensions\MultiPac\MultiPac();

        $resp = $mp->generatePDFV33([
            'xmlB64' => $xmlB64,
            'plantilla' => $plantilla,
            'json' => $jsonB64,
            'logo' => $logoB64,
        ]);

        if (is_string($resp)) {
            throw new \RuntimeException('PAC PDF (SOAP): ' . mb_substr(strip_tags($resp), 0, 500));
        }

        $code = (string)($resp->code ?? $resp->codigo ?? $resp->CODIGO ?? '');
        $msg  = (string)($resp->message ?? $resp->mensaje ?? $resp->MENSAJE ?? '');
        $pdf  = (string)($resp->pdf ?? $resp->PDF ?? '');

        if ($code !== '' && $code !== '210' && trim($pdf) === '') {
            throw new \RuntimeException($msg ?: "CÃƒÆ’Ã‚Â³digo PAC: {$code}");
        }

        if (trim($pdf) === '') {
            throw new \RuntimeException('PAC no devolviÃƒÆ’Ã‚Â³ PDF (base64) para plantilla pagos2.');
        }

        return $pdf;
    }

    private function generarPdfBase64FallbackDompdfComplemento(string $xmlTimbrado): string
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return '';
        }

        $meta = $this->parseCfdiBasicsFromXml($xmlTimbrado);
        $parties = $this->parseCfdiPartiesFromXml($xmlTimbrado);
        $logoB64 = $this->getLogoBase64ForUser((int) auth()->id());

        $pdfBinary = \Barryvdh\DomPDF\Facade\Pdf::loadView('documentos.complementos.pdf', [
            'meta' => $meta,
            'parties' => $parties,
            'pagos20' => $this->parsePagos20DetailsFromXml($xmlTimbrado),
            'xml' => $xmlTimbrado,
            'logoB64' => $logoB64,
        ])->output();

        return base64_encode($pdfBinary);
    }




    private function generarXmlPagos20DesdePayload(int $userId, array $payload, object $cliente): string
    {
        // Normaliza payload (saldos, impuestos, tasas, etc.)
        if (method_exists($this, 'normalizePayloadPagos')) {
            $payload = $this->normalizePayloadPagos($payload);
        }

        // ===== Emisor (de users_info_factura) =====
        $em = DB::table('users_info_factura')->where('users_id', $userId)->first();
        if (!$em) throw new \RuntimeException('Falta users_info_factura (emisor).');

        $emRfc = trim((string)($em->rfc ?? ''));
        $emNom = trim((string)($em->razon_social ?? $em->nombre ?? ''));
        $emReg = trim((string)($em->regimen_fiscal ?? $em->regimen ?? ''));
        $emCp  = trim((string)($em->codigo_postal ?? $em->cp ?? ''));

        if ($emRfc === '' || $emNom === '' || $emReg === '' || $emCp === '') {
            $fallback = $this->getEmisorDataForUser($userId);
            if (!empty($fallback)) {
                $emRfc = $emRfc !== '' ? $emRfc : trim((string)($fallback['rfc'] ?? ''));
                $emNom = $emNom !== '' ? $emNom : trim((string)($fallback['nombre'] ?? ''));
                $emReg = $emReg !== '' ? $emReg : trim((string)($fallback['regimen'] ?? ''));
                $emCp  = $emCp !== '' ? $emCp : trim((string)($fallback['cp'] ?? ''));
            }
        }

        if ($emRfc === '' || $emNom === '' || $emReg === '' || $emCp === '') {
            Log::error('Emisor incompleto para timbrado de complemento', [
                'user_id' => $userId,
                'em_rfc' => $emRfc,
                'em_nombre' => $emNom,
                'em_regimen' => $emReg,
                'em_cp' => $emCp,
            ]);
            throw new \RuntimeException('Emisor incompleto (RFC / Razon social / Regimen / CP).');
        }

        // ===== Receptor (cliente) =====
        $reRfc = trim((string)($cliente->rfc ?? ''));
        $reNom = trim((string)($cliente->razon_social ?? ''));
        $reCp  = trim((string)($cliente->codigo_postal ?? ''));
        $reReg = trim((string)($cliente->regimen_fiscal ?? ''));

        if ($reRfc === '' || $reNom === '' || $reCp === '' || $reReg === '') {
            throw new \RuntimeException('Cliente incompleto (RFC / RazÃƒÆ’Ã‚Â³n social / CP / RÃƒÆ’Ã‚Â©gimen fiscal).');
        }

        // ===== Header CFDI =====
        // Usa serie_pago/folio_pago como fuente principal y mantiene serie/folio como alias
        $serie = trim((string)($payload['serie_pago'] ?? ($payload['serie'] ?? '')));
        $folio = trim((string)($payload['folio_pago'] ?? ($payload['folio'] ?? '')));

        $fechaDocumentoRaw = (string)($payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? ''));
        $fechaPagoRaw      = (string)($payload['fecha_pago'] ?? ($payload['fecha_documento'] ?? ''));

        if ($fechaDocumentoRaw === '') throw new \RuntimeException('Falta fecha_documento.');
        if ($fechaPagoRaw === '') throw new \RuntimeException('Falta fecha_pago.');

        $fechaCfdi = date('Y-m-d\TH:i:s', strtotime($fechaDocumentoRaw));
        $fechaPago = date('Y-m-d\TH:i:s', strtotime($fechaPagoRaw));

        $formaPagoP  = (string)($payload['forma_pago_p'] ?? '03');
        $monedaP     = (string)($payload['moneda_p'] ?? 'MXN');
        $tipoCambioP = (float)($payload['tipo_cambio_p'] ?? 1);

        if ($monedaP !== 'MXN' && $tipoCambioP <= 0) {
            throw new \RuntimeException('TipoCambioP invÃƒÆ’Ã‚Â¡lido para moneda distinta a MXN.');
        }

        $pagos = $payload['pagos'] ?? [];
        if (!is_array($pagos) || !count($pagos)) {
            throw new \RuntimeException('No hay doctos relacionados.');
        }

        // ===== Totales SAT correctos + sumatorias para ImpuestosP =====
        // Esto te arma:
        // - MontoTotalPagos
        // - atributos de pago20:Totales (IVA16, retenciones, etc.)
        // - arreglo de ImpuestosP agregados (traslados/retenciones)
        if (!method_exists($this, 'calculatePagos20Totals')) {
            throw new \RuntimeException('Falta helper calculatePagos20Totals() en el controlador.');
        }

        [$montoTotalPagos, $totalesSat, $impPSums] = $this->calculatePagos20Totals($payload);
        $this->validatePagos20TaxConsistency($payload);
        $this->logPagos20TaxValidation($payload, $montoTotalPagos, $totalesSat, $impPSums);

        // ===== DOM =====
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $cfdiNs = 'http://www.sat.gob.mx/cfd/4';
        $pagoNs = 'http://www.sat.gob.mx/Pagos20';
        $xsiNs  = 'http://www.w3.org/2001/XMLSchema-instance';

        $c = $dom->createElementNS($cfdiNs, 'cfdi:Comprobante');
        $dom->appendChild($c);

        $c->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $c->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:pago20', $pagoNs);

        $c->setAttribute('Version', '4.0');
        if ($serie !== '') $c->setAttribute('Serie', $serie);
        if ($folio !== '' && $folio !== '0') $c->setAttribute('Folio', $folio);
        $c->setAttribute('Fecha', $fechaCfdi);

        // El PAC inyecta Sello/Cert/NoCert via trait
        $c->setAttribute('Sello', '');
        $c->setAttribute('NoCertificado', '');
        $c->setAttribute('Certificado', '');

        $c->setAttribute('SubTotal', '0');
        $c->setAttribute('Moneda', 'XXX');
        $c->setAttribute('Total', '0');
        $c->setAttribute('TipoDeComprobante', 'P');
        $c->setAttribute('Exportacion', '01');
        $c->setAttribute('LugarExpedicion', $emCp);

        $c->setAttributeNS($xsiNs, 'xsi:schemaLocation',
            'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd ' .
            'http://www.sat.gob.mx/Pagos20 http://www.sat.gob.mx/sitio_internet/cfd/Pagos/Pagos20.xsd'
        );

        // Emisor
        $emisor = $dom->createElement('cfdi:Emisor');
        $emisor->setAttribute('Rfc', $emRfc);
        $emisor->setAttribute('Nombre', $emNom);
        $emisor->setAttribute('RegimenFiscal', $emReg);
        $c->appendChild($emisor);

        // Receptor
        $receptor = $dom->createElement('cfdi:Receptor');
        $receptor->setAttribute('Rfc', $reRfc);
        $receptor->setAttribute('Nombre', $reNom);
        $receptor->setAttribute('UsoCFDI', 'CP01');
        $receptor->setAttribute('DomicilioFiscalReceptor', $reCp);
        $receptor->setAttribute('RegimenFiscalReceptor', $reReg);
        $c->appendChild($receptor);

        // Conceptos (1 concepto pago)
        $conceptos = $dom->createElement('cfdi:Conceptos');
        $con = $dom->createElement('cfdi:Concepto');
        $con->setAttribute('ClaveProdServ', '84111506');
        $con->setAttribute('Cantidad', '1');
        $con->setAttribute('ClaveUnidad', 'ACT');
        $con->setAttribute('Descripcion', 'Pago');
        $con->setAttribute('ValorUnitario', '0');
        $con->setAttribute('Importe', '0');
        $con->setAttribute('ObjetoImp', '01');
        $conceptos->appendChild($con);
        $c->appendChild($conceptos);

        // Complemento Pagos
        $compl = $dom->createElement('cfdi:Complemento');
        $c->appendChild($compl);

        $pagos20 = $dom->createElementNS($pagoNs, 'pago20:Pagos');
        $pagos20->setAttribute('Version', '2.0');
        $compl->appendChild($pagos20);

        // Totales (MontoTotalPagos + atributos SAT si aplican)
        $tot = $dom->createElementNS($pagoNs, 'pago20:Totales');
        $tot->setAttribute('MontoTotalPagos', $this->fmtMoney($montoTotalPagos));

        if (is_array($totalesSat)) {
            foreach ($totalesSat as $k => $v) {
                $tot->setAttribute($k, $this->fmtMoney($v));
            }
        }

        $pagos20->appendChild($tot);

        // Pago (un solo Pago que contiene mÃƒÆ’Ã‚Âºltiples DoctoRelacionado)
        $pagoNode = $dom->createElementNS($pagoNs, 'pago20:Pago');
        $pagoNode->setAttribute('FechaPago', $fechaPago);
        $pagoNode->setAttribute('FormaDePagoP', $formaPagoP);
        $pagoNode->setAttribute('MonedaP', $monedaP);
        $pagoNode->setAttribute('TipoCambioP', ($monedaP !== 'MXN') ? $this->fmtTc($tipoCambioP) : '1');
        $pagoNode->setAttribute('Monto', $this->fmtMoney($montoTotalPagos));

        // Datos bancarios con TUS nombres (y soporta tambiÃƒÆ’Ã‚Â©n los alternos)
        $numOp = trim((string)($payload['num_operacion'] ?? ''));

        $rfcOrd = trim((string)($payload['rfc_banco_emisor'] ?? ($payload['rfc_emisor_cta_ord'] ?? '')));
        $ctaOrd = trim((string)($payload['cuenta_ordenante'] ?? ($payload['cta_ordenante'] ?? '')));

        $rfcBen = trim((string)($payload['banco_receptor'] ?? ($payload['rfc_emisor_cta_ben'] ?? '')));
        $ctaBen = trim((string)($payload['cuenta_beneficiaria'] ?? ($payload['cta_beneficiario'] ?? '')));

        if ($numOp !== '') $pagoNode->setAttribute('NumOperacion', $numOp);
        if ($rfcOrd !== '') $pagoNode->setAttribute('RfcEmisorCtaOrd', $rfcOrd);
        if ($ctaOrd !== '') $pagoNode->setAttribute('CtaOrdenante', $ctaOrd);
        if ($rfcBen !== '') $pagoNode->setAttribute('RfcEmisorCtaBen', $rfcBen);
        if ($ctaBen !== '') $pagoNode->setAttribute('CtaBeneficiario', $ctaBen);

        $pagos20->appendChild($pagoNode);

        // Doctos relacionados + ImpuestosDR por docto
        foreach ($pagos as $p) {
            if (!is_array($p)) continue;

            $uuidDoc = strtoupper(trim((string)($p['uuid'] ?? '')));
            if ($uuidDoc === '') continue;

            $doc = $dom->createElementNS($pagoNs, 'pago20:DoctoRelacionado');
            $doc->setAttribute('IdDocumento', $uuidDoc);

            $monedaDR = (string)($p['moneda_dr'] ?? 'MXN');
            $doc->setAttribute('MonedaDR', $monedaDR);

            // EquivalenciaDR: si misma moneda, 1. Si distinta, tambiÃƒÆ’Ã‚Â©n 1 (simplificado vÃƒÆ’Ã‚Â¡lido si tu TipoCambioP ya corresponde)
            $doc->setAttribute('EquivalenciaDR', '1');

            if (strtoupper($monedaDR) !== strtoupper($monedaP)) {
                if ($tipoCambioP <= 0) throw new \RuntimeException('No hay tipo de cambio vÃƒÆ’Ã‚Â¡lido para MonedaDR != MonedaP.');
                $doc->setAttribute('TipoCambioDR', $this->fmtTc($tipoCambioP));
            }

            $doc->setAttribute('NumParcialidad', (string)max(1, (int)($p['num_parcialidad'] ?? 1)));
            $doc->setAttribute('ImpSaldoAnt', $this->fmtMoney($p['saldo_anterior'] ?? '0'));
            $doc->setAttribute('ImpPagado', $this->fmtMoney($p['monto_pago'] ?? '0'));
            $doc->setAttribute('ImpSaldoInsoluto', $this->fmtMoney($p['saldo_insoluto'] ?? '0'));

            $obj = !empty($p['objeto_imp']) ? '02' : '01';
            $doc->setAttribute('ObjetoImpDR', $obj);

            if ($obj === '02') {
                $items = is_array($p['impuestos'] ?? null) ? $p['impuestos'] : [];
                $this->appendImpuestosDR($dom, $doc, $items);
            }

            $pagoNode->appendChild($doc);
        }

        // ImpuestosP AGREGADOS (UNA SOLA VEZ) dentro de Pago
        if (is_array($impPSums) && (!empty($impPSums['traslados']) || !empty($impPSums['retenciones']))) {
            $impP = $dom->createElementNS($pagoNs, 'pago20:ImpuestosP');

            if (!empty($impPSums['retenciones'])) {
                $retsP = $dom->createElementNS($pagoNs, 'pago20:RetencionesP');
                foreach ($impPSums['retenciones'] as $row) {
                    $n = $dom->createElementNS($pagoNs, 'pago20:RetencionP');
                    $n->setAttribute('ImpuestoP', (string)($row['impuesto'] ?? '002'));
                    $n->setAttribute('ImporteP', $this->fmtMoney($row['importe'] ?? '0'));
                    $retsP->appendChild($n);
                }
                $impP->appendChild($retsP);
            }

            if (!empty($impPSums['traslados'])) {
                $trasP = $dom->createElementNS($pagoNs, 'pago20:TrasladosP');
                foreach ($impPSums['traslados'] as $row) {
                    $n = $dom->createElementNS($pagoNs, 'pago20:TrasladoP');
                    $n->setAttribute('BaseP', $this->fmtMoney($row['base'] ?? '0'));
                    $n->setAttribute('ImpuestoP', (string)($row['impuesto'] ?? '002'));
                    $n->setAttribute('TipoFactorP', (string)($row['factor'] ?? 'Tasa'));
                    if ((string)($row['factor'] ?? 'Tasa') !== 'Exento') {
                        $n->setAttribute('TasaOCuotaP', (string)($row['tasa'] ?? '0.000000'));
                        $n->setAttribute('ImporteP', $this->fmtMoney($row['importe'] ?? '0'));
                    }
                    $trasP->appendChild($n);
                }
                $impP->appendChild($trasP);
            }

            $pagoNode->appendChild($impP);
        }

        return $dom->saveXML();
    }


    private function guardarComplementoTimbrado(
        int $userId,
        array $payload,
        object $cliente,
        string $xmlOriginal,
        string $xmlTimbrado,
        string $uuid,
        ?string $pdfB64,
        ?string $acuse
    ): int {
        $tabla = 'complementos';

        $insert = [
            'users_id' => $userId,
            'uuid' => $uuid,
            'estatus' => 'TIMBRADA',
            'xml' => $xmlTimbrado,
            'pdf' => $pdfB64 ?: '',
            'acuse' => $acuse,
            'solicitud_timbre' => $xmlOriginal,
            'fecha' => date('Y-m-d H:i:s'),
        ];

        // opcionales si existen
        $opc = [
            'cliente_id' => (int)($payload['cliente_id'] ?? 0),
            'rfc' => (string)($cliente->rfc ?? ''),
            'razon_social' => (string)($cliente->razon_social ?? ''),
            'codigo_postal' => (string)($cliente->codigo_postal ?? ''),
            'serie' => (string)($payload['serie'] ?? ''),
            'folio' => (string)($payload['folio'] ?? ''),
            'fecha_documento' => (string)($payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? '')),
            'fecha_pago' => (string)($payload['fecha_pago'] ?? ''),
            'forma_pago_p' => (string)($payload['forma_pago_p'] ?? ''),
            'moneda_p' => (string)($payload['moneda_p'] ?? ''),
            'tipo_cambio_p' => (string)($payload['tipo_cambio_p'] ?? ''),
            'num_operacion' => (string)($payload['num_operacion'] ?? ''),
        ];

        foreach ($opc as $col => $val) {
            if (Schema::hasColumn($tabla, $col)) {
                $insert[$col] = $val;
            }
        }

        if (Schema::hasColumn($tabla, 'serie') && !empty($payload['serie_pago'])) {
            $insert['serie'] = (string)$payload['serie_pago'];
        }
        if (Schema::hasColumn($tabla, 'folio') && !empty($payload['folio_pago'])) {
            $insert['folio'] = (string)$payload['folio_pago'];
        }

        $compId = DB::table('complementos')->insertGetId($insert);

        // Guardar detalle doctos en complementos_pagos
        if (Schema::hasTable('complementos_pagos')) {
            foreach (($payload['pagos'] ?? []) as $p) {
                $row = [
                    'users_complementos_id' => $compId,
                    'documento_id' => (string)($p['uuid'] ?? ''),
                    'parcialidad' => (int)($p['num_parcialidad'] ?? 1),
                    'saldo_anterior' => (float)($p['saldo_anterior'] ?? 0),
                    'monto_pago' => (float)($p['monto_pago'] ?? 0),
                    'saldo_insoluto' => (float)($p['saldo_insoluto'] ?? 0),
                ];

                // opcionales
                $opc2 = [
                    'fecha_pago' => (string)($payload['fecha_pago'] ?? ''),
                    'factura_id' => (int)($p['factura_id'] ?? 0),
                    'moneda_dr' => (string)($p['moneda_dr'] ?? ''),
                    'metodo_pago_dr' => (string)($p['metodo_pago_dr'] ?? ''),
                ];

                foreach ($opc2 as $col => $val) {
                    if (Schema::hasColumn('complementos_pagos', $col)) {
                        $row[$col] = $val;
                    }
                }

                DB::table('complementos_pagos')->insert($row);
            }
        }

        return (int)$compId;
    }

}
