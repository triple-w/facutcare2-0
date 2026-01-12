<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function create()
    {
        $userId = auth()->id();

        $clientes = DB::table('clientes')
            ->where('users_id', $userId)
            ->orderBy('razon_social')
            ->get();

        // Para que el front siempre pinte __FACTURA_CREATE_OPTS__
        $prefill = true;
        $rfcUsuarioId = $userId;

        // Catálogos SAT mínimo viable
        $metodosPago = [
            ['clave' => 'PUE', 'descripcion' => 'Pago en una sola exhibición'],
            ['clave' => 'PPD', 'descripcion' => 'Pago en parcialidades o diferido'],
        ];

        $formasPago = [
            ['clave' => '01', 'descripcion' => 'Efectivo'],
            ['clave' => '02', 'descripcion' => 'Cheque nominativo'],
            ['clave' => '03', 'descripcion' => 'Transferencia electrónica de fondos'],
            ['clave' => '04', 'descripcion' => 'Tarjeta de crédito'],
            ['clave' => '28', 'descripcion' => 'Tarjeta de débito'],
            ['clave' => '99', 'descripcion' => 'Por definir'],
        ];

        return view('facturas.create', compact('clientes', 'prefill', 'rfcUsuarioId', 'metodosPago', 'formasPago'));
    }

    public function preview(Request $request)
    {
        $payload = json_decode($request->input('payload', ''), true);

        if (!is_array($payload)) {
            return back()->withErrors(['payload' => 'Payload inválido.']);
        }

        $userId = auth()->id();
        $emisor_rfc = auth()->user()->username ?? '—';

        $comprobante = [
            'tipo_comprobante' => $payload['tipo_comprobante'] ?? '',
            'serie'            => $payload['serie'] ?? '',
            'folio'            => $payload['folio'] ?? '',
            'fecha'            => $payload['fecha'] ?? null,
            'metodo_pago'      => $payload['metodo_pago'] ?? '',
            'forma_pago'       => $payload['forma_pago'] ?? '',
            'comentarios_pdf'  => $payload['comentarios_pdf'] ?? '',
        ];

        $clienteId = (int) ($payload['cliente_id'] ?? 0);

        $cliente = DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            return back()->withErrors(['cliente_id' => 'Cliente inválido o no pertenece al usuario.']);
        }

        $conceptos = $payload['conceptos'] ?? [];

        return view('facturas.preview', compact(
            'payload',
            'emisor_rfc',
            'comprobante',
            'cliente',
            'conceptos'
        ));
    }

    /* ==========================
       Helpers
    ========================== */

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
