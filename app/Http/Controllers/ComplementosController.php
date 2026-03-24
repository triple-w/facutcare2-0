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

    // ✅ Nuevo complemento: limpia draft completo
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

        // ✅ folios tipo PAGO
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
            return redirect()->route('complementos.create')->with('error', 'Payload inválido.');
        }

        // guarda borrador para volver a create con info
        session(['complemento_draft' => $payload]);

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
            return redirect()->route('complementos.create')->with('error', 'Cliente inválido.');
        }

        // =========================
        // Normaliza datos nuevos
        // =========================
        $payload['serie_pago'] = (string)($payload['serie_pago'] ?? '');
        $payload['folio_pago'] = (int)($payload['folio_pago'] ?? 0);

        // Compatibilidad: si falta una fecha, toma la otra.
        $payload['fecha_documento'] = (string)($payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? ''));
        $payload['fecha_pago']      = (string)($payload['fecha_pago'] ?? ($payload['fecha_documento'] ?? ''));

        $payload['forma_pago_p']   = (string)($payload['forma_pago_p'] ?? '03');
        $payload['moneda_p']       = (string)($payload['moneda_p'] ?? 'MXN');
        $payload['tipo_cambio_p']  = (float)($payload['tipo_cambio_p'] ?? 1);

        // bancarios opcionales
        $payload['num_operacion']       = (string)($payload['num_operacion'] ?? '');
        $payload['rfc_banco_emisor']    = (string)($payload['rfc_banco_emisor'] ?? '');
        $payload['cuenta_ordenante']    = (string)($payload['cuenta_ordenante'] ?? '');
        $payload['banco_receptor']      = (string)($payload['banco_receptor'] ?? '');
        $payload['cuenta_beneficiaria'] = (string)($payload['cuenta_beneficiaria'] ?? '');

        // doctos
        $pagos = $payload['pagos'] ?? [];
        if (!is_array($pagos)) $pagos = [];

        // =========================
        // Recalcula impuestos y totales en backend
        // =========================
        $subtotal = 0.0;
        $traslados = 0.0;
        $retenciones = 0.0;

        foreach ($pagos as $i => $p) {
            if (!is_array($p)) $p = [];

            $saldoAnterior = (float)($p['saldo_anterior'] ?? 0);
            $montoPago     = (float)($p['monto_pago'] ?? 0);
            $montoPago     = max($montoPago, 0);
            $saldoInsoluto = array_key_exists('saldo_insoluto', $p)
                ? (float)($p['saldo_insoluto'] ?? 0)
                : ($saldoAnterior - $montoPago);
            $saldoInsoluto = max(round($saldoInsoluto, 2), 0);

            $p['saldo_anterior']  = round($saldoAnterior, 2);
            $p['monto_pago']      = round($montoPago, 2);
            $p['saldo_insoluto']  = $saldoInsoluto;

            $p['num_parcialidad'] = (int)($p['num_parcialidad'] ?? 1);
            $p['moneda_dr']       = (string)($p['moneda_dr'] ?? 'MXN');
            $p['metodo_pago_dr']  = (string)($p['metodo_pago_dr'] ?? 'PPD');

            $p['objeto_imp'] = (bool)($p['objeto_imp'] ?? false);
            $p['impuestos']  = is_array($p['impuestos'] ?? null) ? $p['impuestos'] : [];

            $subtotal += $montoPago;

            // si hay impuestos, recalcular importes por seguridad
            if ($p['objeto_imp'] && is_array($p['impuestos'])) {
                foreach ($p['impuestos'] as $k => $it) {
                    if (!is_array($it)) $it = [];

                    $tipo   = (string)($it['tipo'] ?? 'T'); // T o R
                    $factor = (string)($it['factor'] ?? 'Tasa');

                    $base = isset($it['base']) ? (float)$it['base'] : 0.0;
                    if ($base <= 0) $base = $montoPago;

                    $tasa = (float)($it['tasa'] ?? 0); // porcentaje (16)
                    $importe = 0.0;

                    if (strtolower($factor) === 'exento') {
                        $importe = 0.0;
                    } else {
                        $importe = round($base * ($tasa / 100), 2);
                    }

                    $it['tipo']   = $tipo;
                    $it['factor'] = $factor;
                    $it['base']   = round($base, 2);
                    $it['tasa']   = round($tasa, 6);
                    $it['importe'] = round($importe, 2);

                    if (strtoupper($tipo) === 'R') $retenciones += $importe;
                    else $traslados += $importe;

                    $p['impuestos'][$k] = $it;
                }
            }

            $pagos[$i] = $p;
        }

        $subtotal = round($subtotal, 2);
        $traslados = round($traslados, 2);
        $retenciones = round($retenciones, 2);

        $total = round($subtotal, 2);
        $subtotalNeto = round($total - $traslados - $retenciones, 2);

        $totales = [
            'subtotal'    => $subtotalNeto,
            'traslados'   => $traslados,
            'retenciones' => $retenciones,
            'total'       => $total,
        ];

        // reinyecta pagos normalizados
        $payload['pagos'] = $pagos;

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

        return view('documentos.complementos.invoice', compact('comp', 'pagos'));
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

            // recalcular impuestos importes (tasa en %)
            if ($p['objeto_imp']) {
                foreach ($p['impuestos'] as $k => $it) {
                    if (!is_array($it)) $it = [];
                    $factor = (string)($it['factor'] ?? 'Tasa');
                    $base   = (float)($it['base'] ?? $p['monto_pago']);
                    $tasa   = (float)($it['tasa'] ?? 0);

                    $importe = 0.0;
                    if (strtolower($factor) !== 'exento') {
                        $importe = round($base * ($tasa / 100), 2);
                    }

                    $it['base']    = round($base, 2);
                    $it['tasa']    = round($tasa, 6);
                    $it['importe'] = round($importe, 2);

                    $p['impuestos'][$k] = $it;
                }
            } else {
                $p['impuestos'] = [];
            }

            $pagos[$i] = $p;
        }

        $payload['pagos'] = $pagos;
        return $payload;
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
        // Intento 1: tabla "informacion" (muy común en FactuCare/iKontrol)
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

        // Intento 2: tabla users (si guardas ahí algo)
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
     * Ajusta aquí a TU fuente real (tabla sellos, storage, etc.).
     */
    private function getCsdForUser(int $userId): array
    {
        // Opción A: storage/app/csd/{userId}/
        $base = storage_path("app/csd/{$userId}");
        $cer  = $base.'/csd.cer';
        $keyp = $base.'/csd.key.pem';

        // Opción B: public/uploads/users_documentos/ (como el legacy)
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
        // El número de certificado SAT normalmente viene como serialNumberHex o serialNumber
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

        // intenta columnas típicas
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

    private function buildCfdiPagos20Xml(array $payload, $cliente, array $emisor, string $serie, int $folio, array $csd): string
    {
        $fechaDocumento = Carbon::parse((string)($payload['fecha_documento'] ?? ($payload['fecha_pago'] ?? '')))->format('Y-m-d\TH:i:s');
        $fechaPago      = Carbon::parse((string)($payload['fecha_pago'] ?? ($payload['fecha_documento'] ?? '')))->format('Y-m-d\TH:i:s');

        $certB64 = base64_encode(file_get_contents($csd['cer_path']));
        $noCert  = (string)($csd['no_certificado'] ?? '');

        $formaPagoP  = (string)($payload['forma_pago_p'] ?? '03');
        $monedaP     = (string)($payload['moneda_p'] ?? 'MXN');
        $tipoCambioP = (float)($payload['tipo_cambio_p'] ?? 1);

        // Totales Pago (MontoTotalPagos) y totales de impuestos por SAT (básico IVA16 + retenciones)
        [$montoTotal, $totalesSat, $impPSums] = $this->calculatePagos20Totals($payload);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $cfdiNS  = 'http://www.sat.gob.mx/cfd/4';
        $pagoNS  = 'http://www.sat.gob.mx/Pagos20';
        $xsiNS   = 'http://www.w3.org/2001/XMLSchema-instance';

        $comprobante = $dom->createElementNS($cfdiNS, 'cfdi:Comprobante');
        $dom->appendChild($comprobante);

        $comprobante->setAttributeNS($xsiNS, 'xsi:schemaLocation',
            'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd '.
            'http://www.sat.gob.mx/Pagos20 http://www.sat.gob.mx/sitio_internet/cfd/Pagos/Pagos20.xsd'
        );

        $comprobante->setAttribute('Version', '4.0');
        $comprobante->setAttribute('Serie', $serie);
        $comprobante->setAttribute('Folio', (string)$folio);
        $comprobante->setAttribute('Fecha', $fechaDocumento);

        $comprobante->setAttribute('Sello', ''); // MultiPac lo genera con keyPEM
        $comprobante->setAttribute('NoCertificado', $noCert);
        $comprobante->setAttribute('Certificado', preg_replace("/\r|\n/", "", $certB64));

        $comprobante->setAttribute('SubTotal', '0');
        $comprobante->setAttribute('Moneda', 'XXX');
        $comprobante->setAttribute('Total', '0');
        $comprobante->setAttribute('TipoDeComprobante', 'P');
        $comprobante->setAttribute('Exportacion', '01');
        $comprobante->setAttribute('LugarExpedicion', (string)$emisor['cp']);

        // Emisor
        $em = $dom->createElement('cfdi:Emisor');
        $em->setAttribute('Rfc', (string)$emisor['rfc']);
        $em->setAttribute('Nombre', (string)$emisor['nombre']);
        $em->setAttribute('RegimenFiscal', (string)$emisor['regimen']);
        $comprobante->appendChild($em);

        // Receptor
        $re = $dom->createElement('cfdi:Receptor');
        $re->setAttribute('Rfc', strtoupper(trim((string)($cliente->rfc ?? ''))));
        $re->setAttribute('Nombre', (string)($cliente->razon_social ?? ''));
        $re->setAttribute('UsoCFDI', 'CP01');
        $re->setAttribute('DomicilioFiscalReceptor', (string)($cliente->codigo_postal ?? ''));
        $re->setAttribute('RegimenFiscalReceptor', (string)($cliente->regimen_fiscal ?? ''));
        $comprobante->appendChild($re);

        // Conceptos
        $conceptos = $dom->createElement('cfdi:Conceptos');
        $c = $dom->createElement('cfdi:Concepto');
        $c->setAttribute('ClaveProdServ', '84111506');
        $c->setAttribute('Cantidad', '1');
        $c->setAttribute('ClaveUnidad', 'ACT');
        $c->setAttribute('Descripcion', 'Pago');
        $c->setAttribute('ValorUnitario', '0');
        $c->setAttribute('Importe', '0');
        $c->setAttribute('ObjetoImp', '01');
        $conceptos->appendChild($c);
        $comprobante->appendChild($conceptos);

        // Complemento Pagos20
        $complemento = $dom->createElement('cfdi:Complemento');
        $pagosNode = $dom->createElementNS($pagoNS, 'pago20:Pagos');
        $pagosNode->setAttribute('Version', '2.0');

        $tot = $dom->createElement('pago20:Totales');
        $tot->setAttribute('MontoTotalPagos', $this->fmtMoney($montoTotal));

        // Totales SAT (básicos)
        foreach ($totalesSat as $k => $v) {
            $tot->setAttribute($k, $this->fmtMoney($v));
        }
        $pagosNode->appendChild($tot);

        // Un solo nodo Pago (multi-docto)
        $pago = $dom->createElement('pago20:Pago');
        $pago->setAttribute('FechaPago', $fechaPago);
        $pago->setAttribute('FormaDePagoP', $formaPagoP);
        $pago->setAttribute('MonedaP', $monedaP);

        if (strtoupper($monedaP) !== 'MXN') {
            $pago->setAttribute('TipoCambioP', $this->fmtTc($tipoCambioP));
        } else {
            $pago->setAttribute('TipoCambioP', '1');
        }

        // Monto del pago (sumatoria en moneda P)
        $pago->setAttribute('Monto', $this->fmtMoney($montoTotal));

        // Bancarios opcionales (si al menos hay num_operacion o alguno de los otros)
        $numOp = trim((string)($payload['num_operacion'] ?? ''));
        $rfcOrd = trim((string)($payload['rfc_banco_emisor'] ?? ''));
        $ctaOrd = trim((string)($payload['cuenta_ordenante'] ?? ''));
        $rfcBen = trim((string)($payload['banco_receptor'] ?? ''));
        $ctaBen = trim((string)($payload['cuenta_beneficiaria'] ?? ''));

        if ($numOp !== '') $pago->setAttribute('NumOperacion', $numOp);
        if ($rfcOrd !== '') $pago->setAttribute('RfcEmisorCtaOrd', $rfcOrd);
        if ($ctaOrd !== '') $pago->setAttribute('CtaOrdenante', $ctaOrd);
        if ($rfcBen !== '') $pago->setAttribute('RfcEmisorCtaBen', $rfcBen);
        if ($ctaBen !== '') $pago->setAttribute('CtaBeneficiario', $ctaBen);

        // Doctos relacionados
        foreach (($payload['pagos'] ?? []) as $dr) {
            $uuidRel = strtoupper(trim((string)($dr['uuid'] ?? '')));
            if ($uuidRel === '') continue;

            $docto = $dom->createElement('pago20:DoctoRelacionado');
            $docto->setAttribute('IdDocumento', $uuidRel);

            $monedaDR = (string)($dr['moneda_dr'] ?? 'MXN');
            $docto->setAttribute('MonedaDR', $monedaDR);

            // EquivalenciaDR / TipoCambioDR solo si difiere (simplificado)
            if (strtoupper($monedaDR) !== strtoupper($monedaP)) {
                $docto->setAttribute('EquivalenciaDR', '1');
                $docto->setAttribute('TipoCambioDR', $this->fmtTc($tipoCambioP));
            } else {
                $docto->setAttribute('EquivalenciaDR', '1');
            }

            $docto->setAttribute('NumParcialidad', (string)((int)($dr['num_parcialidad'] ?? 1)));
            $docto->setAttribute('ImpSaldoAnt', $this->fmtMoney((float)($dr['saldo_anterior'] ?? 0)));
            $docto->setAttribute('ImpPagado', $this->fmtMoney((float)($dr['monto_pago'] ?? 0)));
            $docto->setAttribute('ImpSaldoInsoluto', $this->fmtMoney((float)($dr['saldo_insoluto'] ?? 0)));

            // ObjetoImpDR
            $obj = (bool)($dr['objeto_imp'] ?? false);
            $docto->setAttribute('ObjetoImpDR', $obj ? '02' : '01');

            // ImpuestosDR si aplica
            if ($obj && is_array($dr['impuestos'] ?? null) && count($dr['impuestos']) > 0) {
                $impDR = $dom->createElement('pago20:ImpuestosDR');

                $trasDR = null;
                $retDR  = null;

                foreach ($dr['impuestos'] as $it) {
                    if (!is_array($it)) continue;

                    $tipo = strtoupper((string)($it['tipo'] ?? 'T')); // T/R
                    $imp  = strtoupper((string)($it['impuesto'] ?? 'IVA')); // IVA/ISR/IEPS
                    $fac  = (string)($it['factor'] ?? 'Tasa');
                    $base = (float)($it['base'] ?? 0);
                    $tasaPrc = (float)($it['tasa'] ?? 0);
                    $importe = (float)($it['importe'] ?? 0);

                    $impCode = $this->mapImpuestoToSatCode($imp);
                    $tipoFactor = $this->mapFactorToSat($fac);
                    $tasaCuota = $this->fmtRateFromPercent($tasaPrc); // 16 => 0.160000

                    if ($tipo === 'R') {
                        if (!$retDR) $retDR = $dom->createElement('pago20:RetencionesDR');
                        $n = $dom->createElement('pago20:RetencionDR');
                        $n->setAttribute('BaseDR', $this->fmtMoney($base));
                        $n->setAttribute('ImpuestoDR', $impCode);
                        $n->setAttribute('TipoFactorDR', $tipoFactor);
                        if ($tipoFactor !== 'Exento') {
                            $n->setAttribute('TasaOCuotaDR', $tasaCuota);
                            $n->setAttribute('ImporteDR', $this->fmtMoney($importe));
                        }
                        $retDR->appendChild($n);
                    } else {
                        if (!$trasDR) $trasDR = $dom->createElement('pago20:TrasladosDR');
                        $n = $dom->createElement('pago20:TrasladoDR');
                        $n->setAttribute('BaseDR', $this->fmtMoney($base));
                        $n->setAttribute('ImpuestoDR', $impCode);
                        $n->setAttribute('TipoFactorDR', $tipoFactor);
                        if ($tipoFactor !== 'Exento') {
                            $n->setAttribute('TasaOCuotaDR', $tasaCuota);
                            $n->setAttribute('ImporteDR', $this->fmtMoney($importe));
                        }
                        $trasDR->appendChild($n);
                    }
                }

                if ($retDR) $impDR->appendChild($retDR);
                if ($trasDR) $impDR->appendChild($trasDR);

                $docto->appendChild($impDR);
            }

            $pago->appendChild($docto);
        }

        // ImpuestosP (sumatoria a nivel Pago) si hubo impuestos
        if (!empty($impPSums['traslados']) || !empty($impPSums['retenciones'])) {
            $impP = $dom->createElement('pago20:ImpuestosP');

            if (!empty($impPSums['retenciones'])) {
                $retsP = $dom->createElement('pago20:RetencionesP');
                foreach ($impPSums['retenciones'] as $row) {
                    $n = $dom->createElement('pago20:RetencionP');
                    $n->setAttribute('BaseP', $this->fmtMoney($row['base']));
                    $n->setAttribute('ImpuestoP', $row['impuesto']);
                    $n->setAttribute('TipoFactorP', $row['factor']);
                    if ($row['factor'] !== 'Exento') {
                        $n->setAttribute('TasaOCuotaP', $row['tasa']);
                        $n->setAttribute('ImporteP', $this->fmtMoney($row['importe']));
                    }
                    $retsP->appendChild($n);
                }
                $impP->appendChild($retsP);
            }

            if (!empty($impPSums['traslados'])) {
                $trasP = $dom->createElement('pago20:TrasladosP');
                foreach ($impPSums['traslados'] as $row) {
                    $n = $dom->createElement('pago20:TrasladoP');
                    $n->setAttribute('BaseP', $this->fmtMoney($row['base']));
                    $n->setAttribute('ImpuestoP', $row['impuesto']);
                    $n->setAttribute('TipoFactorP', $row['factor']);
                    if ($row['factor'] !== 'Exento') {
                        $n->setAttribute('TasaOCuotaP', $row['tasa']);
                        $n->setAttribute('ImporteP', $this->fmtMoney($row['importe']));
                    }
                    $trasP->appendChild($n);
                }
                $impP->appendChild($trasP);
            }

            $pago->appendChild($impP);
        }

        $pagosNode->appendChild($pago);
        $complemento->appendChild($pagosNode);
        $comprobante->appendChild($complemento);

        return $dom->saveXML();
    }

private function calculatePagos20Totals(array $payload): array
{
    $montoTotal = 0.0;

    // SAT Totales básicos
    $totalesSat = []; // atributos en pago20:Totales

    // Sumatorias para ImpuestosP
    $sumTras = []; // key => ['base'=>, 'importe'=>, 'impuesto'=>code, 'factor'=>, 'tasa'=>]
    $sumRet  = [];

    $retIVA = 0.0; $retISR = 0.0; $retIEPS = 0.0;

    $baseIVA16 = 0.0; $impIVA16 = 0.0;

    foreach (($payload['pagos'] ?? []) as $dr) {
        $monto = (float)($dr['monto_pago'] ?? 0);
        $montoTotal += $monto;

        $obj = (bool)($dr['objeto_imp'] ?? false);
        if (!$obj) continue;

        foreach (($dr['impuestos'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $tipo = strtoupper((string)($it['tipo'] ?? 'T'));
            $imp  = strtoupper((string)($it['impuesto'] ?? 'IVA'));
            $fac  = (string)($it['factor'] ?? 'Tasa');
            $factorSat = $this->mapFactorToSat($fac);

            $base = (float)($it['base'] ?? 0);
            $importe = (float)($it['importe'] ?? 0);
            $tasaPrc = (float)($it['tasa'] ?? 0);
            $tasaSat = $this->fmtRateFromPercent($tasaPrc);

            $impCode = $this->mapImpuestoToSatCode($imp);

            $key = $tipo.'|'.$impCode.'|'.$factorSat.'|'.$tasaSat;

            if ($tipo === 'R') {
                if (!isset($sumRet[$key])) $sumRet[$key] = ['base'=>0.0,'importe'=>0.0,'impuesto'=>$impCode,'factor'=>$factorSat,'tasa'=>$tasaSat];
                $sumRet[$key]['base'] += $base;
                $sumRet[$key]['importe'] += $importe;

                if ($impCode === '002') $retIVA += $importe;
                if ($impCode === '001') $retISR += $importe;
                if ($impCode === '003') $retIEPS += $importe;
            } else {
                if (!isset($sumTras[$key])) $sumTras[$key] = ['base'=>0.0,'importe'=>0.0,'impuesto'=>$impCode,'factor'=>$factorSat,'tasa'=>$tasaSat];
                $sumTras[$key]['base'] += $base;
                $sumTras[$key]['importe'] += $importe;

                // SAT tiene atributos específicos IVA16; si cae ahí, lo alimentamos
                if ($impCode === '002' && $factorSat === 'Tasa' && abs((float)$tasaSat - 0.160000) < 0.000001) {
                    $baseIVA16 += $base;
                    $impIVA16  += $importe;
                }
            }
        }
    }

    $montoTotal = round($montoTotal, 2);

    // Arma atributos SAT mínimos si existen
    if ($baseIVA16 > 0.0) {
        $totalesSat['TotalTrasladosBaseIVA16'] = round($baseIVA16, 2);
        $totalesSat['TotalTrasladosImpuestoIVA16'] = round($impIVA16, 2);
    }

    if ($retIVA > 0.0) $totalesSat['TotalRetencionesIVA'] = round($retIVA, 2);
    if ($retISR > 0.0) $totalesSat['TotalRetencionesISR'] = round($retISR, 2);
    if ($retIEPS > 0.0) $totalesSat['TotalRetencionesIEPS'] = round($retIEPS, 2);

    $impPSums = [
        'traslados' => array_values(array_map(function($r){
            $r['base'] = round($r['base'], 2);
            $r['importe'] = round($r['importe'], 2);
            return $r;
        }, $sumTras)),
        'retenciones' => array_values(array_map(function($r){
            $r['base'] = round($r['base'], 2);
            $r['importe'] = round($r['importe'], 2);
            return $r;
        }, $sumRet)),
    ];

    return [$montoTotal, $totalesSat, $impPSums];
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

private function mapFactorToSat(string $fac): string
{
    $fac = strtolower(trim($fac));
    if ($fac === 'exento') return 'Exento';
    if ($fac === 'cuota') return 'Cuota';
    return 'Tasa';
}

private function fmtMoney(float $n): string
{
    return number_format((float)$n, 2, '.', '');
}

private function fmtTc(float $n): string
{
    // Tipo de cambio con 6 decimales usualmente es aceptado
    return number_format((float)$n, 6, '.', '');
}

private function fmtRateFromPercent(float $percent): string
{
    // 16 => 0.160000
    $rate = $percent / 100;
    return number_format((float)$rate, 6, '.', '');
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
    // Ajusta si tu logo está en otra parte.
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
     * Parser básico CFDI 3.3 / 4.0
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

    private function catalogoFormasPago(): array
    {
        return [
            ['id'=>'01','text'=>'01 - Efectivo'],
            ['id'=>'02','text'=>'02 - Cheque nominativo'],
            ['id'=>'03','text'=>'03 - Transferencia electrónica de fondos'],
            ['id'=>'04','text'=>'04 - Tarjeta de crédito'],
            ['id'=>'05','text'=>'05 - Monedero electrónico'],
            ['id'=>'06','text'=>'06 - Dinero electrónico'],
            ['id'=>'08','text'=>'08 - Vales de despensa'],
            ['id'=>'12','text'=>'12 - Dación en pago'],
            ['id'=>'13','text'=>'13 - Pago por subrogación'],
            ['id'=>'14','text'=>'14 - Pago por consignación'],
            ['id'=>'15','text'=>'15 - Condonación'],
            ['id'=>'17','text'=>'17 - Compensación'],
            ['id'=>'23','text'=>'23 - Novación'],
            ['id'=>'24','text'=>'24 - Confusión'],
            ['id'=>'25','text'=>'25 - Remisión de deuda'],
            ['id'=>'26','text'=>'26 - Prescripción o caducidad'],
            ['id'=>'27','text'=>'27 - A satisfacción del acreedor'],
            ['id'=>'28','text'=>'28 - Tarjeta de débito'],
            ['id'=>'29','text'=>'29 - Tarjeta de servicios'],
            ['id'=>'30','text'=>'30 - Aplicación de anticipos'],
            ['id'=>'31','text'=>'31 - Intermediario pagos'],
            ['id'=>'99','text'=>'99 - Por definir'],
        ];
    }

    private function catalogoMonedas(): array
    {
        return [
            ['id'=>'MXN','text'=>'MXN - Peso Mexicano'],
            ['id'=>'USD','text'=>'USD - Dólar'],
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

        // Payload: POST o sesión
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
            return back()->with('error', 'No hay datos del complemento en sesión. Regresa a crear el complemento.');
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
            return back()->with('error', 'Cliente inválido.');
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
                throw new \RuntimeException((string)($resp['mensaje'] ?? 'PAC no devolvió XML timbrado.'));
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

            // 4) Guardar + avanzar folio (atómico)
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
            return back()->with('error', 'Error al timbrar complemento: ' . $e->getMessage());
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

            $tipo   = strtoupper(trim((string)($it['tipo'] ?? 'T'))); // T/R
            $impNom = strtoupper(trim((string)($it['impuesto'] ?? 'IVA'))); // IVA/ISR/IEPS
            $factor = (string)($it['factor'] ?? 'Tasa'); // Tasa/Cuota/Exento
            $tasaPct = (float)($it['tasa'] ?? 0); // 16
            $base   = (float)($it['base'] ?? 0);
            $importe= (float)($it['importe'] ?? 0);

            $impCode    = $this->mapImpuestoToSatCode($impNom);     // 001/002/003
            $tipoFactor = $this->mapFactorToSat($factor);           // Tasa/Cuota/Exento
            $isExento   = ($tipoFactor === 'Exento');

            if ($tipo === 'R') {
                if (!$retDR) $retDR = $dom->createElementNS($pagoNs, 'pago20:RetencionesDR');

                $n = $dom->createElementNS($pagoNs, 'pago20:RetencionDR');
                $n->setAttribute('BaseDR', $this->fmtMoney($base));
                $n->setAttribute('ImpuestoDR', $impCode);
                $n->setAttribute('TipoFactorDR', $tipoFactor);

                if (!$isExento) {
                    $n->setAttribute('TasaOCuotaDR', $this->fmtRateFromPercent($tasaPct)); // 16 => 0.160000
                    $n->setAttribute('ImporteDR', $this->fmtMoney($importe));
                }

                $retDR->appendChild($n);
            } else {
                if (!$trasDR) $trasDR = $dom->createElementNS($pagoNs, 'pago20:TrasladosDR');

                $n = $dom->createElementNS($pagoNs, 'pago20:TrasladoDR');
                $n->setAttribute('BaseDR', $this->fmtMoney($base));
                $n->setAttribute('ImpuestoDR', $impCode);
                $n->setAttribute('TipoFactorDR', $tipoFactor);

                if (!$isExento) {
                    $n->setAttribute('TasaOCuotaDR', $this->fmtRateFromPercent($tasaPct));
                    $n->setAttribute('ImporteDR', $this->fmtMoney($importe));
                }

                $trasDR->appendChild($n);
            }
        }

        if ($trasDR) $impDR->appendChild($trasDR);
        if ($retDR)  $impDR->appendChild($retDR);

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
            throw new \RuntimeException($msg ?: "Código PAC: {$code}");
        }

        if (trim($pdf) === '') {
            throw new \RuntimeException('PAC no devolvió PDF (base64) para plantilla pagos2.');
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
            throw new \RuntimeException('Cliente incompleto (RFC / Razón social / CP / Régimen fiscal).');
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
            throw new \RuntimeException('TipoCambioP inválido para moneda distinta a MXN.');
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
        $tot->setAttribute('MontoTotalPagos', $this->fmtMoney((float)$montoTotalPagos));

        if (is_array($totalesSat)) {
            foreach ($totalesSat as $k => $v) {
                $tot->setAttribute($k, $this->fmtMoney((float)$v));
            }
        }

        $pagos20->appendChild($tot);

        // Pago (un solo Pago que contiene múltiples DoctoRelacionado)
        $pagoNode = $dom->createElementNS($pagoNs, 'pago20:Pago');
        $pagoNode->setAttribute('FechaPago', $fechaPago);
        $pagoNode->setAttribute('FormaDePagoP', $formaPagoP);
        $pagoNode->setAttribute('MonedaP', $monedaP);
        $pagoNode->setAttribute('TipoCambioP', ($monedaP !== 'MXN') ? $this->fmtTc($tipoCambioP) : '1');
        $pagoNode->setAttribute('Monto', $this->fmtMoney((float)$montoTotalPagos));

        // Datos bancarios con TUS nombres (y soporta también los alternos)
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

            // EquivalenciaDR: si misma moneda, 1. Si distinta, también 1 (simplificado válido si tu TipoCambioP ya corresponde)
            $doc->setAttribute('EquivalenciaDR', '1');

            if (strtoupper($monedaDR) !== strtoupper($monedaP)) {
                if ($tipoCambioP <= 0) throw new \RuntimeException('No hay tipo de cambio válido para MonedaDR != MonedaP.');
                $doc->setAttribute('TipoCambioDR', $this->fmtTc($tipoCambioP));
            }

            $doc->setAttribute('NumParcialidad', (string)max(1, (int)($p['num_parcialidad'] ?? 1)));
            $doc->setAttribute('ImpSaldoAnt', $this->fmtMoney((float)($p['saldo_anterior'] ?? 0)));
            $doc->setAttribute('ImpPagado', $this->fmtMoney((float)($p['monto_pago'] ?? 0)));
            $doc->setAttribute('ImpSaldoInsoluto', $this->fmtMoney((float)($p['saldo_insoluto'] ?? 0)));

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
                    $n->setAttribute('BaseP', $this->fmtMoney((float)($row['base'] ?? 0)));
                    $n->setAttribute('ImpuestoP', (string)($row['impuesto'] ?? '002'));
                    $n->setAttribute('TipoFactorP', (string)($row['factor'] ?? 'Tasa'));
                    if ((string)($row['factor'] ?? 'Tasa') !== 'Exento') {
                        $n->setAttribute('TasaOCuotaP', (string)($row['tasa'] ?? '0.000000'));
                        $n->setAttribute('ImporteP', $this->fmtMoney((float)($row['importe'] ?? 0)));
                    }
                    $retsP->appendChild($n);
                }
                $impP->appendChild($retsP);
            }

            if (!empty($impPSums['traslados'])) {
                $trasP = $dom->createElementNS($pagoNs, 'pago20:TrasladosP');
                foreach ($impPSums['traslados'] as $row) {
                    $n = $dom->createElementNS($pagoNs, 'pago20:TrasladoP');
                    $n->setAttribute('BaseP', $this->fmtMoney((float)($row['base'] ?? 0)));
                    $n->setAttribute('ImpuestoP', (string)($row['impuesto'] ?? '002'));
                    $n->setAttribute('TipoFactorP', (string)($row['factor'] ?? 'Tasa'));
                    if ((string)($row['factor'] ?? 'Tasa') !== 'Exento') {
                        $n->setAttribute('TasaOCuotaP', (string)($row['tasa'] ?? '0.000000'));
                        $n->setAttribute('ImporteP', $this->fmtMoney((float)($row['importe'] ?? 0)));
                    }
                    $trasP->appendChild($n);
                }
                $impP->appendChild($trasP);
            }

            $pagoNode->appendChild($impP);
        }

        return $dom->saveXML();
    }


    private function appendImpuestosDRyP(\DOMDocument $dom, \DOMElement $docRel, \DOMElement $pagoNode, array $p): void
    {
        $pagoNs = 'http://www.sat.gob.mx/Pagos20';

        $items = $p['impuestos'] ?? [];
        if (!is_array($items) || !count($items)) return;

        // ImpuestosDR
        $impDR = $dom->createElementNS($pagoNs, 'pago20:ImpuestosDR');
        $trasDR = null;
        $retDR  = null;

        // ImpuestosP
        $impP = $dom->createElementNS($pagoNs, 'pago20:ImpuestosP');
        $trasP = null;
        $retP  = null;

        foreach ($items as $it) {
            $tipo   = strtoupper(trim((string)($it['tipo'] ?? 'T'))); // T/R
            $impNom = strtoupper(trim((string)($it['impuesto'] ?? 'IVA'))); // IVA/ISR/IEPS
            $factor = (string)($it['factor'] ?? 'Tasa'); // Tasa/Cuota/Exento
            $tasaPct = (float)($it['tasa'] ?? 0); // UI: 16
            $base   = (float)($it['base'] ?? 0);
            $importe= (float)($it['importe'] ?? 0);

            $impCode = match ($impNom) {
                'ISR' => '001',
                'IVA' => '002',
                'IEPS'=> '003',
                default => '002',
            };

            $isExento = (strcasecmp($factor, 'Exento') === 0);

            if ($tipo === 'R') {
                if (!$retDR) $retDR = $dom->createElementNS($pagoNs, 'pago20:RetencionesDR');
                if (!$retP)  $retP  = $dom->createElementNS($pagoNs, 'pago20:RetencionesP');

                $rdr = $dom->createElementNS($pagoNs, 'pago20:RetencionDR');
                $rdr->setAttribute('ImpuestoDR', $impCode);
                $rdr->setAttribute('ImporteDR', number_format($importe, 2, '.', ''));
                $retDR->appendChild($rdr);

                $rp = $dom->createElementNS($pagoNs, 'pago20:RetencionP');
                $rp->setAttribute('ImpuestoP', $impCode);
                $rp->setAttribute('ImporteP', number_format($importe, 2, '.', ''));
                $retP->appendChild($rp);

            } else {
                if (!$trasDR) $trasDR = $dom->createElementNS($pagoNs, 'pago20:TrasladosDR');
                if (!$trasP)  $trasP  = $dom->createElementNS($pagoNs, 'pago20:TrasladosP');

                $tdr = $dom->createElementNS($pagoNs, 'pago20:TrasladoDR');
                $tdr->setAttribute('BaseDR', number_format($base, 2, '.', ''));
                $tdr->setAttribute('ImpuestoDR', $impCode);
                $tdr->setAttribute('TipoFactorDR', $isExento ? 'Exento' : 'Tasa');

                if (!$isExento) {
                    $tdr->setAttribute('TasaOCuotaDR', number_format(($tasaPct / 100), 6, '.', ''));
                    $tdr->setAttribute('ImporteDR', number_format($importe, 2, '.', ''));
                }
                $trasDR->appendChild($tdr);

                $tp = $dom->createElementNS($pagoNs, 'pago20:TrasladoP');
                $tp->setAttribute('BaseP', number_format($base, 2, '.', ''));
                $tp->setAttribute('ImpuestoP', $impCode);
                $tp->setAttribute('TipoFactorP', $isExento ? 'Exento' : 'Tasa');

                if (!$isExento) {
                    $tp->setAttribute('TasaOCuotaP', number_format(($tasaPct / 100), 6, '.', ''));
                    $tp->setAttribute('ImporteP', number_format($importe, 2, '.', ''));
                }
                $trasP->appendChild($tp);
            }
        }

        if ($trasDR || $retDR) {
            if ($trasDR) $impDR->appendChild($trasDR);
            if ($retDR)  $impDR->appendChild($retDR);
            $docRel->appendChild($impDR);
        }

        if ($trasP || $retP) {
            if ($trasP) $impP->appendChild($trasP);
            if ($retP)  $impP->appendChild($retP);
            $pagoNode->appendChild($impP);
        }
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
