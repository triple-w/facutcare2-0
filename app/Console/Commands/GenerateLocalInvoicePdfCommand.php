<?php

namespace App\Console\Commands;

use App\Support\CfdiPdfParser;
use App\Support\PdfComments;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class GenerateLocalInvoicePdfCommand extends Command
{
    protected $signature = 'factura:test-pdf-local {factura_id : ID de la factura timbrada}';

    protected $description = 'Genera un PDF local de prueba sin modificar la factura';

    public function handle(CfdiPdfParser $parser): int
    {
        $id = (int) $this->argument('factura_id');
        $factura = DB::table('facturas')->where('id', $id)->first();

        if (!$factura) {
            $this->error("No existe la factura {$id}.");
            return self::FAILURE;
        }

        $xml = (string) ($factura->xml ?? '');
        if (trim($xml) === '') {
            $this->error("La factura {$id} no tiene XML timbrado.");
            return self::FAILURE;
        }

        $configuracion = null;
        if (Schema::hasTable('users_info_factura')
            && Schema::hasColumn('users_info_factura', 'forzar_comentario_pdf')
            && Schema::hasColumn('users_info_factura', 'comentario_forzado_pdf')) {
            $configuracion = DB::table('users_info_factura')
                ->where('users_id', $factura->users_id)
                ->first(['forzar_comentario_pdf', 'comentario_forzado_pdf']);
        }

        $comentariosPdf = PdfComments::combine(
            (string) ($factura->comentarios_pdf ?? ''),
            (string) ($configuracion->comentario_forzado_pdf ?? ''),
            $configuracion ? (bool) $configuracion->forzar_comentario_pdf : false
        );

        try {
            $cfdi = $parser->parse($xml);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (empty($cfdi['qr_data_uri'])) {
            $this->error('No fue posible generar localmente el código QR SAT.');
            return self::FAILURE;
        }

        $logoB64 = $this->logoBase64((int) $factura->users_id);
        $binary = Pdf::loadView('facturas.pdf', compact('cfdi', 'logoB64', 'comentariosPdf'))
            ->setPaper('letter')
            ->output();

        $directory = storage_path('app/test-pdf');
        File::ensureDirectoryExists($directory);
        $path = $directory . DIRECTORY_SEPARATOR . "factura-{$id}.pdf";
        File::put($path, $binary);

        $this->info("PDF local generado: {$path}");
        $this->line('La base de datos no fue modificada.');

        return self::SUCCESS;
    }

    private function logoBase64(int $userId): ?string
    {
        $path = public_path("uploads/users_logos/thumbnails/{$userId}.png");
        return is_file($path) ? base64_encode((string) file_get_contents($path)) : null;
    }
}
