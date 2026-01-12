<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacturasController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $q = trim((string) $request->get('q', ''));

        $facturas = DB::table('facturas as f')
            ->where('f.users_id', $userId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('f.razon_social', 'like', "%{$q}%")
                        ->orWhere('f.rfc', 'like', "%{$q}%")
                        ->orWhere('f.uuid', 'like', "%{$q}%")
                        ->orWhere('f.nombre_comprobante', 'like', "%{$q}%")
                        ->orWhere('f.estatus', 'like', "%{$q}%");
                });
            })
            ->select([
                'f.*',
                DB::raw('(SELECT COUNT(*) FROM factura_detalles d WHERE d.users_facturas_id = f.id) as detalles_count'),
            ])
            ->orderByDesc('f.id')
            ->paginate(20)
            ->withQueryString();

        return view('facturas.index', compact('facturas', 'q'));
    }

    private function getRfcActivo(): ?string
    {
        // 1) Si en FC2 ya guardas RFC activo en sesión (como iKontrol), úsalo
        $rfc = session('rfc_activo_rfc') ?? session('rfc_activo') ?? null;

        // 2) Fallback “FactuCare clásico”: username suele ser el RFC
        if (!$rfc) {
            $rfc = auth()->user()->username ?? null;
        }

        return $rfc;
    }

    public function create()
    {
        $userId = auth()->id();
        $rfcActivo = $this->getRfcActivo();

        // Clientes
        $clientes = DB::table('clientes')
            ->where('users_id', $userId)
            ->orderBy('razon_social')
            ->get();

        // Folios (FC1)
        $folios = collect();
        if (Schema::hasTable('folios')) {
            $folios = DB::table('folios')
                ->where('users_id', $userId)
                ->orderBy('id', 'desc')
                ->get();
        }

        // Ventana SAT (como tu blade iKontrol lo maneja)
        $minFecha = now()->copy()->subHours(72)->format('Y-m-d\TH:i');
        $maxFecha = now()->format('Y-m-d\TH:i');

        // Para que el blade pinte la variable JS
        $prefill = true;

        // Si tu blade usa rfcUsuarioId (id interno), manda userId por ahora
        $rfcUsuarioId = (int)($userId);

        return view('facturas.create', [
            'prefill' => $prefill,
            'clientes' => $clientes,
            'folios' => $folios,
            'rfcActivo' => $rfcActivo,
            'rfcUsuarioId' => $rfcUsuarioId,
            'minFecha' => $minFecha,
            'maxFecha' => $maxFecha,
        ]);
    }


    public function preview(Request $request)
    {
        $userId = auth()->id();

        $payload = json_decode((string)$request->input('payload', ''), true);
        if (!is_array($payload)) {
            return back()->with('error', 'Payload inválido.');
        }

        $clienteId = (int)($payload['cliente_id'] ?? 0);

        $cliente = DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            return back()->with('error', 'Cliente inválido o no pertenece al usuario.');
        }

        $conceptos = $payload['conceptos'] ?? [];
        if (!is_array($conceptos)) $conceptos = [];

        // Totales server-side para que preview sea confiable
        $subtotal = 0.0;
        $iva = 0.0;
        $descuento = (float)($payload['descuento'] ?? 0);

        $conceptosLimpios = [];

        foreach ($conceptos as $c) {
            $cantidad = (float)($c['cantidad'] ?? 0);
            $precio = (float)($c['precio'] ?? 0);
            $desc = (float)($c['descuento'] ?? 0);

            $importe = max(0, ($cantidad * $precio) - $desc);
            $subtotal += $importe;

            $aplicaIva = (bool)($c['aplica_iva'] ?? true);
            $tasaIva = (float)($c['iva_tasa'] ?? 0.16);

            $ivaConcepto = $aplicaIva ? ($importe * $tasaIva) : 0;
            $iva += $ivaConcepto;

            $conceptosLimpios[] = [
                'cantidad' => $cantidad,
                'unidad' => (string)($c['unidad'] ?? 'SERV'),
                'descripcion' => (string)($c['descripcion'] ?? ''),
                'clave_prod_serv' => (string)($c['clave_prod_serv'] ?? ''),
                'clave_unidad' => (string)($c['clave_unidad'] ?? ''),
                'precio' => $precio,
                'descuento' => $desc,
                'importe' => $importe,
                'aplica_iva' => $aplicaIva,
                'iva_tasa' => $tasaIva,
                'iva' => $ivaConcepto,
            ];
        }

        $total = max(0, ($subtotal - $descuento) + $iva);

        $comprobante = [
            'rfc_activo' => (string)($payload['rfc_activo'] ?? ''),
            'folio_id' => (int)($payload['folio_id'] ?? 0),
            'tipo_comprobante' => (string)($payload['tipo_comprobante'] ?? 'I'),
            'metodo_pago' => (string)($payload['metodo_pago'] ?? 'PUE'),
            'forma_pago' => (string)($payload['forma_pago'] ?? '99'),
            'moneda' => (string)($payload['moneda'] ?? 'MXN'),
            'comentarios_pdf' => (string)($payload['comentarios_pdf'] ?? ''),
        ];

        $totales = [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'total' => $total,
        ];

        return view('facturas.preview', compact('cliente', 'conceptosLimpios', 'comprobante', 'totales'));
    }

    /* ==========================
       Helpers
    ========================== */

    public function apiSeriesNext(Request $request)
    {
        $userId = auth()->id();

        $tipo = strtoupper((string)$request->get('tipo', 'I')); // I/E/T
        $folioId = (int)$request->get('folio_id', 0);

        // 1) Si mandan folio_id, úsalo directo
        if ($folioId > 0) {
            $f = DB::table('folios')
                ->where('users_id', $userId)
                ->where('id', $folioId)
                ->first();

            if ($f) {
                return response()->json([
                    'ok' => true,
                    'folio_id' => (int)$f->id,
                    'serie' => (string)$f->serie,
                    'folio' => (int)$f->folio,
                    'tipo' => (string)$f->tipo,
                ]);
            }
        }

        // 2) Si no hay folio_id, intenta encontrar por tipo
        // La columna tipo en FC1 varía (ingreso/egreso/traslado/factura/etc).
        $patterns = match ($tipo) {
            'E' => ['%egreso%', '%nota%', '%credito%', '%nc%'],
            'T' => ['%traslado%'],
            default => ['%fact%', '%ingreso%', '%factura%'],
        };

        $where = "LOWER(tipo) LIKE ? OR LOWER(tipo) LIKE ? OR LOWER(tipo) LIKE ? OR LOWER(tipo) LIKE ?";
        $params = array_pad($patterns, 4, $patterns[0]);

        $f = DB::table('folios')
            ->where('users_id', $userId)
            ->whereRaw($where, $params)
            ->orderBy('id', 'desc')
            ->first();

        // 3) fallback: el último folio del usuario
        if (!$f) {
            $f = DB::table('folios')
                ->where('users_id', $userId)
                ->orderBy('id', 'desc')
                ->first();
        }

        if (!$f) {
            return response()->json(['ok' => false, 'message' => 'No hay folios configurados.'], 404);
        }

        return response()->json([
            'ok' => true, 
            'folio_id' => (int)$f->id,
            'serie' => (string)$f->serie,
            'folio' => (int)$f->folio,
            'tipo' => (string)$f->tipo,
        ]);
    }

    public function apiProductosBuscar(Request $request)
    {
        $userId = auth()->id();
        $q = trim((string)$request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('productos')
            ->where('users_id', $userId)
            ->where(function ($qq) use ($q) {
                $qq->where('descripcion', 'like', "%{$q}%")
                ->orWhere('clave', 'like', "%{$q}%");
            })
            ->orderBy('descripcion')
            ->limit(15)
            ->get();

        return response()->json(['items' => $items]);
    }

    public function apiSatProdServ(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('clave_prod_serv')
            ->where('clave', 'like', "{$q}%")
            ->orWhere('descripcion', 'like', "%{$q}%")
            ->limit(20)
            ->get();

        return response()->json(['items' => $items]);
    }

    public function apiSatUnidad(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('clave_unidad')
            ->where('clave', 'like', "{$q}%")
            ->orWhere('descripcion', 'like', "%{$q}%")
            ->limit(20)
            ->get();

        return response()->json(['items' => $items]);
    }



    private function facturaOrFail(int $id): object
    {
        $userId = auth()->id();

        $factura = DB::table('facturas')
            ->where('id', $id)
            ->where('users_id', $userId)
            ->first();

        abort_if(!$factura, 404, 'Factura no encontrada');

        return $factura;
    }

    /**
     * Parser “básico” del CFDI desde el XML (para invoice y nombres).
     */
    private function parseCfdiBasics(string $xml): array
    {
        $out = [
            'serie' => null,
            'folio' => null,
            'fecha' => null,
            'subtotal' => null,
            'descuento' => null,
            'total' => null,
            'moneda' => null,
            'forma_pago' => null,
            'metodo_pago' => null,
            'tipo_comprobante' => null,
            'uuid' => null,
        ];

        if (trim($xml) === '') return $out;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok = $dom->loadXML($xml);

        if (!$ok) {
            $xml2 = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, ISO-8859-15');
            $dom->loadXML($xml2);
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xp->registerNamespace('tfd',  'http://www.sat.gob.mx/TimbreFiscalDigital');

        $c = $xp->query('//cfdi:Comprobante')->item(0);
        if ($c instanceof \DOMElement) {
            $out['serie'] = $c->getAttribute('Serie') ?: $c->getAttribute('serie') ?: null;
            $out['folio'] = $c->getAttribute('Folio') ?: $c->getAttribute('folio') ?: null;
            $out['fecha'] = $c->getAttribute('Fecha') ?: $c->getAttribute('fecha') ?: null;

            $out['subtotal'] = $c->getAttribute('SubTotal') ?: $c->getAttribute('subTotal') ?: $c->getAttribute('subtotal') ?: null;
            $out['descuento'] = $c->getAttribute('Descuento') ?: $c->getAttribute('descuento') ?: null;
            $out['total'] = $c->getAttribute('Total') ?: $c->getAttribute('total') ?: null;

            $out['moneda'] = $c->getAttribute('Moneda') ?: $c->getAttribute('moneda') ?: null;
            $out['forma_pago'] = $c->getAttribute('FormaPago') ?: $c->getAttribute('formaPago') ?: null;
            $out['metodo_pago'] = $c->getAttribute('MetodoPago') ?: $c->getAttribute('metodoPago') ?: null;
            $out['tipo_comprobante'] = $c->getAttribute('TipoDeComprobante') ?: $c->getAttribute('tipoDeComprobante') ?: null;
        }

        $t = $xp->query('//tfd:TimbreFiscalDigital')->item(0);
        if ($t instanceof \DOMElement) {
            $out['uuid'] = $t->getAttribute('UUID') ?: $t->getAttribute('Uuid') ?: $t->getAttribute('uuid') ?: null;
        }

        return $out;
    }

    /**
     * Normaliza IVA desde detalles si viene "escalado" (centavos/milésimas, etc.)
     * Si existe IVA objetivo (derivado del XML), intenta acercarse lo más posible.
     */
    private function normalizeIvaFromDetalles(float $ivaRaw, float $subtotal, ?float $ivaObjetivo = null): float
    {
        if ($ivaRaw <= 0) return 0.0;

        // Caso típico: IVA inflado brutal
        if ($subtotal > 0 && $ivaRaw <= $subtotal * 2) {
            return $ivaRaw;
        }

        $divisores = [1, 100, 1000, 10000, 1000000];
        $best = $ivaRaw;
        $bestScore = INF;

        foreach ($divisores as $d) {
            $v = $ivaRaw / $d;

            // Score: si hay objetivo, distancia al objetivo; si no hay, que no sea absurdo vs subtotal
            $score = $ivaObjetivo !== null
                ? abs($v - $ivaObjetivo)
                : ($subtotal > 0 ? max(0, $v - ($subtotal * 2)) : $v);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $v;
            }
        }

        return $best;
    }

    /* ==========================
       Acciones: XML / PDF / Acuse
    ========================== */

    public function downloadXml(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $xml = (string) ($factura->xml ?? '');
        abort_if(trim($xml) === '', 404, 'XML no disponible');

        $cfdi = $this->parseCfdiBasics($xml);
        $uuid = $cfdi['uuid'] ?: ($factura->uuid ?? $factura->id);

        $name = trim(($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? ''));
        if ($name === '') $name = 'Factura';

        $filename = "{$name} - {$uuid}.xml";

        return response($xml)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function downloadPdf(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $pdfB64 = (string) ($factura->pdf ?? '');
        abort_if(trim($pdfB64) === '', 404, 'PDF no disponible');

        $bin = base64_decode($pdfB64, true);
        if ($bin === false) {
            $bin = $pdfB64; // por si viniera binario
        }

        $xml = (string) ($factura->xml ?? '');
        $cfdi = $xml ? $this->parseCfdiBasics($xml) : [];
        $uuid = ($cfdi['uuid'] ?? null) ?: ($factura->uuid ?? $factura->id);

        $name = trim((($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? '')));
        if ($name === '') $name = 'Factura';

        $filename = "{$name} - {$uuid}.pdf";

        return response($bin)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function downloadAcuse(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $acuse = (string) ($factura->acuse ?? '');
        abort_if(trim($acuse) === '', 404, 'Acuse no disponible');

        $xml = (string) ($factura->xml ?? '');
        $cfdi = $xml ? $this->parseCfdiBasics($xml) : [];
        $uuid = ($cfdi['uuid'] ?? null) ?: ($factura->uuid ?? $factura->id);

        $name = trim((($cfdi['serie'] ?? '') . ($cfdi['folio'] ?? '')));
        if ($name === '') $name = 'Factura';

        $filename = "Cancelado {$name} - {$uuid}.xml";

        return response($acuse)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /* ==========================
       Invoice (VER)
    ========================== */

    public function show(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $detalles = DB::table('factura_detalles')
            ->where('users_facturas_id', $factura->id)
            ->orderBy('id')
            ->get();

        $impuestos = DB::table('facturas_impuestos')
            ->where('users_facturas_id', $factura->id)
            ->orderBy('id')
            ->get();

        $xml = (string) ($factura->xml ?? '');
        $cfdi = $xml !== '' ? $this->parseCfdiBasics($xml) : [];

        // Subtotal / descuento / total desde XML si existen (son los más confiables)
        $subtotalXml  = is_numeric($cfdi['subtotal'] ?? null) ? (float)$cfdi['subtotal'] : null;
        $descuentoXml = is_numeric($cfdi['descuento'] ?? null) ? (float)$cfdi['descuento'] : null;
        $totalXml     = is_numeric($cfdi['total'] ?? null) ? (float)$cfdi['total'] : null;

        // Fallbacks DB
        $subtotalDb = (float)$detalles->sum('importe');
        $subtotal = $subtotalXml ?? $subtotalDb;

        $descuento = $descuentoXml ?? (float)($factura->descuento ?? 0);

        // Impuestos desde tabla (si existe)
        $impuestosTotal = (float)$impuestos->sum('monto');

        // IVA derivado del XML: Total - (SubTotal - Descuento)
        $ivaDerivadoXml = null;
        if ($totalXml !== null && $subtotalXml !== null) {
            $desc = $descuentoXml ?? 0.0;
            $ivaDerivadoXml = max(0, $totalXml - ($subtotalXml - $desc));
        }

        // IVA desde detalles (puede venir escalado)
        $ivaRawDetalles = (float)$detalles->sum('iva');
        $ivaDetalles = $this->normalizeIvaFromDetalles($ivaRawDetalles, $subtotal, $ivaDerivadoXml);

        // IVA final: preferimos tabla -> XML derivado -> detalles normalizados
        if ($impuestosTotal > 0) {
            $iva = $impuestosTotal;
        } elseif ($ivaDerivadoXml !== null) {
            $iva = $ivaDerivadoXml;
        } else {
            $iva = $ivaDetalles;
        }

        // Total final: preferimos XML, si no: subtotal - descuento + iva
        $total = $totalXml ?? max(0, ($subtotal - $descuento + $iva));

        $totales = [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'total' => $total,
        ];

        // OJO: ya NO mandamos "emisor" porque dijiste que quieres quitar esa sección.
        return view('facturas.invoice', compact('factura', 'detalles', 'impuestos', 'cfdi', 'totales'));
    }

    /* ==========================
       Regenerar PDF (opcional)
    ========================== */

    public function regenerarPdf(int $id)
    {
        $factura = $this->facturaOrFail($id);

        $xml = (string) ($factura->xml ?? '');
        if (trim($xml) === '') {
            return back()->with('error', 'No hay XML para regenerar el PDF.');
        }

        // Si no tienes dompdf instalado, evitamos fatal error
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'Dompdf no está instalado. Ejecuta: composer require barryvdh/laravel-dompdf');
        }

        $meta = $this->parseCfdiBasics($xml);

        $pdfBinary = \Barryvdh\DomPDF\Facade\Pdf::loadView('facturas.pdf', [
            'factura' => $factura,
            'meta' => $meta,
            'xml' => $xml,
        ])->output();

        DB::table('facturas')
            ->where('id', $factura->id)
            ->update(['pdf' => base64_encode($pdfBinary)]);

        return back()->with('success', 'PDF regenerado correctamente.');
    }

    public function cancelar(int $id)
    {
        $this->facturaOrFail($id);
        return back()->with('error', 'Cancelación aún no implementada en FC2. (Después integramos MultiPac como en FC1).');
    }
}
