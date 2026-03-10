<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillFacturasTotalCommand extends Command
{
    protected $signature = 'facturas:backfill-total
        {--chunk=200 : Registros por bloque}
        {--sleep-ms=250 : Pausa entre bloques en milisegundos}
        {--from-id=0 : ID inicial}
        {--to-id=0 : ID final opcional}
        {--only-null : Solo procesa facturas con total NULL o 0}';

    protected $description = 'Rellena la columna facturas.total leyendo el XML de forma lenta y segura';

    public function handle(): int
    {
        if (!Schema::hasTable('facturas')) {
            $this->error('No existe la tabla facturas.');
            return self::FAILURE;
        }

        if (!Schema::hasColumn('facturas', 'total')) {
            $this->error('No existe la columna facturas.total. Corre primero la migración.');
            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $fromId = max(0, (int) $this->option('from-id'));
        $toId = max(0, (int) $this->option('to-id'));
        $onlyNull = (bool) $this->option('only-null');

        $query = DB::table('facturas')
            ->select(['id', 'xml', 'total'])
            ->where('id', '>', $fromId)
            ->orderBy('id');

        if ($toId > 0) {
            $query->where('id', '<=', $toId);
        }

        if ($onlyNull) {
            $query->where(function ($q) {
                $q->whereNull('total')->orWhere('total', '<=', 0);
            });
        }

        $processed = 0;
        $updated = 0;
        $lastId = $fromId;

        $this->info('Iniciando backfill de facturas.total ...');

        do {
            $rows = (clone $query)
                ->where('id', '>', $lastId)
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $processed++;
                $lastId = (int) $row->id;

                $total = $this->parseTotalFromXml((string) ($row->xml ?? ''));
                if ($total <= 0) {
                    continue;
                }

                DB::table('facturas')
                    ->where('id', $row->id)
                    ->update(['total' => $total]);

                $updated++;
            }

            $this->line("Procesadas: {$processed} | Actualizadas: {$updated} | Ultimo ID: {$lastId}");

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        } while (true);

        $this->info("Backfill terminado. Procesadas: {$processed}. Actualizadas: {$updated}.");

        return self::SUCCESS;
    }

    private function parseTotalFromXml(string $xml): float
    {
        $xml = trim($xml);
        if ($xml === '') {
            return 0.0;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            return 0.0;
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cfdi3', 'http://www.sat.gob.mx/cfd/3');
        $xp->registerNamespace('cfdi4', 'http://www.sat.gob.mx/cfd/4');
        $node = $xp->query('//cfdi4:Comprobante | //cfdi3:Comprobante')->item(0);

        if (!$node instanceof \DOMElement) {
            return 0.0;
        }

        $raw = (string) ($node->getAttribute('Total') ?: $node->getAttribute('total'));
        return round((float) str_replace([',', ' '], '', $raw), 2);
    }
}
