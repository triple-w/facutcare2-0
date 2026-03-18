<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $userId = (int) auth()->id();

        $perfil = $this->buildPerfilData($userId);
        $infoFactura = $this->getInfoFactura($userId);
        $documentos = $this->getDocumentos($infoFactura?->id);
        $logoUrl = $this->getLogoUrl($userId);

        return view('configuracion.index', compact('perfil', 'infoFactura', 'documentos', 'logoUrl'));
    }

    public function updatePerfil(Request $request)
    {
        $data = $request->validate([
            'rfc' => ['required', 'string', 'max:30'],
            'razon_social' => ['required', 'string', 'max:200'],
            'calle' => ['nullable', 'string', 'max:100'],
            'no_ext' => ['nullable', 'string', 'max:20'],
            'no_int' => ['nullable', 'string', 'max:20'],
            'colonia' => ['nullable', 'string', 'max:50'],
            'municipio' => ['nullable', 'string', 'max:50'],
            'localidad' => ['nullable', 'string', 'max:50'],
            'estado' => ['nullable', 'string', 'max:50'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'nombre_contacto' => ['nullable', 'string', 'max:150'],
            'regimen_fiscal' => ['required', 'string', 'max:5', Rule::in(array_keys(config('sat.regimenes_fiscales')))],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'logo_cropped' => ['nullable', 'string'],
        ]);

        $userId = (int) auth()->id();
        $rfc = strtoupper(trim((string) $data['rfc']));
        $razonSocial = trim((string) $data['razon_social']);
        $regimen = trim((string) $data['regimen_fiscal']);

        DB::transaction(function () use ($userId, $data, $rfc, $razonSocial, $regimen) {
            $this->upsertByUser('users_perfil', $userId, [
                'rfc' => $rfc,
                'razon_social' => $razonSocial,
                'calle' => $data['calle'] ?? '',
                'no_ext' => $data['no_ext'] ?? '',
                'no_int' => $data['no_int'] ?? '',
                'colonia' => $data['colonia'] ?? '',
                'municipio' => $data['municipio'] ?? '',
                'localidad' => $data['localidad'] ?? '',
                'estado' => $data['estado'] ?? '',
                'codigo_postal' => $data['codigo_postal'] ?? '',
                'pais' => $data['pais'] ?? 'MEX',
                'telefono' => $data['telefono'] ?? '',
                'nombre_contacto' => $data['nombre_contacto'] ?? '',
                'numero_regimen33' => $regimen,
                'nombre_regimen33' => config('sat.regimenes_fiscales.' . $regimen, ''),
                'numero_regimen' => $regimen,
                'nombre_regimen' => config('sat.regimenes_fiscales.' . $regimen, ''),
                'regimen_fiscal' => $regimen,
                'cp' => $data['codigo_postal'] ?? '',
            ]);

            $this->upsertInfoFactura($userId, [
                'rfc' => $rfc,
                'razon_social' => $razonSocial,
                'nombre' => $razonSocial,
                'codigo_postal' => $data['codigo_postal'] ?? '',
                'cp' => $data['codigo_postal'] ?? '',
                'numero_regimen33' => $regimen,
                'nombre_regimen33' => config('sat.regimenes_fiscales.' . $regimen, ''),
                'numero_regimen' => $regimen,
                'nombre_regimen' => config('sat.regimenes_fiscales.' . $regimen, ''),
                'regimen_fiscal' => $regimen,
            ]);
        });

        if (!empty($data['logo_cropped'])) {
            $this->storeLogoFromBase64($userId, $data['logo_cropped']);
        } elseif ($request->hasFile('logo')) {
            $this->storeLogo($userId, $request->file('logo'));
        }

        return redirect()
            ->route('configuracion.index')
            ->with('status', 'Información fiscal actualizada correctamente.');
    }

    public function uploadCsd(Request $request)
    {
        $data = $request->validate([
            'rfc' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
            'archivo_certificado' => ['required', 'file', 'mimes:cer', 'max:5120'],
            'archivo_llave' => ['required', 'file', 'mimes:key', 'max:5120'],
        ]);

        $userId = (int) auth()->id();
        $infoFactura = $this->ensureInfoFactura($userId, ['password' => $data['password']]);

        $stored = [];

        try {
            $stored['cer'] = $this->storeDocumentoArchivo($request->file('archivo_certificado'), $userId, 'cer');
            $stored['key'] = $this->storeDocumentoArchivo($request->file('archivo_llave'), $userId, 'key');

            $response = $this->validarCsd(
                strtoupper(trim((string) $data['rfc'])),
                (string) $data['password'],
                $stored['cer']['path'],
                $stored['key']['path']
            );

            $this->convertCerToPem($stored['cer']['path']);
            $this->convertKeyToPem($stored['key']['path'], (string) $data['password']);

            DB::transaction(function () use ($userId, $infoFactura, $data, $response, $stored) {
                $regimenDefault = strlen((string) $response->rfc) === 12 ? '601' : '612';

                $this->upsertByUser('users_perfil', $userId, [
                    'rfc' => strtoupper((string) ($response->rfc ?? $data['rfc'])),
                    'razon_social' => (string) ($response->name ?? ''),
                    'numero_regimen33' => $regimenDefault,
                    'nombre_regimen33' => config('sat.regimenes_fiscales.' . $regimenDefault, ''),
                    'numero_regimen' => $regimenDefault,
                    'nombre_regimen' => config('sat.regimenes_fiscales.' . $regimenDefault, ''),
                    'regimen_fiscal' => $regimenDefault,
                ]);

                $this->upsertInfoFactura($userId, [
                    'password' => (string) $data['password'],
                    'rfc' => strtoupper((string) ($response->rfc ?? $data['rfc'])),
                    'razon_social' => (string) ($response->name ?? ''),
                    'nombre' => (string) ($response->name ?? ''),
                    'numero_regimen33' => $regimenDefault,
                    'nombre_regimen33' => config('sat.regimenes_fiscales.' . $regimenDefault, ''),
                    'numero_regimen' => $regimenDefault,
                    'nombre_regimen' => config('sat.regimenes_fiscales.' . $regimenDefault, ''),
                    'regimen_fiscal' => $regimenDefault,
                ]);

                $this->replaceDocumento($infoFactura->id, 'ARCHIVO_CERTIFICADO', $stored['cer'], [
                    'numero_certificado' => (string) ($response->csd_serie ?? ''),
                    'vigencia' => (string) ($response->vigencia_hasta ?? ''),
                    'validado' => 1,
                    'revisado' => 1,
                ]);

                $this->replaceDocumento($infoFactura->id, 'ARCHIVO_LLAVE', $stored['key'], [
                    'vigencia' => (string) ($response->vigencia_hasta ?? ''),
                    'validado' => 1,
                    'revisado' => 1,
                ]);
            });
        } catch (\Throwable $e) {
            foreach ($stored as $doc) {
                $this->deletePhysicalDocumento($doc['path']);
            }

            return redirect()
                ->route('configuracion.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('configuracion.index')
            ->with('status', 'Sellos validados y almacenados correctamente.');
    }

    public function destroyLogo()
    {
        $userId = (int) auth()->id();

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $main = public_path("uploads/users_logos/{$userId}.{$ext}");
            if (is_file($main)) {
                @unlink($main);
            }
        }

        $thumb = public_path("uploads/users_logos/thumbnails/{$userId}.png");
        if (is_file($thumb)) {
            @unlink($thumb);
        }

        return redirect()
            ->route('configuracion.index')
            ->with('status', 'Logo eliminado correctamente.');
    }

    public function destroyDocumento(int $id)
    {
        $doc = DB::table('users_info_factura_documentos')->where('id', $id)->first();
        if (!$doc) {
            abort(404);
        }

        $infoFactura = $this->getInfoFactura((int) auth()->id());
        abort_unless($infoFactura && (int) $doc->users_factura_info_id === (int) $infoFactura->id, 403);

        DB::table('users_info_factura_documentos')->where('id', $id)->delete();
        $this->deletePhysicalDocumento($this->resolveDocumentoPath($doc));

        return redirect()
            ->route('configuracion.index')
            ->with('status', 'Documento eliminado correctamente.');
    }

    private function buildPerfilData(int $userId): array
    {
        $perfil = Schema::hasTable('users_perfil')
            ? DB::table('users_perfil')->where('users_id', $userId)->first()
            : null;

        $info = $this->getInfoFactura($userId);

        return [
            'rfc' => old('rfc', $perfil->rfc ?? $info->rfc ?? ''),
            'razon_social' => old('razon_social', $perfil->razon_social ?? $info->razon_social ?? $info->nombre ?? ''),
            'calle' => old('calle', $perfil->calle ?? ''),
            'no_ext' => old('no_ext', $perfil->no_ext ?? ''),
            'no_int' => old('no_int', $perfil->no_int ?? ''),
            'colonia' => old('colonia', $perfil->colonia ?? ''),
            'municipio' => old('municipio', $perfil->municipio ?? ''),
            'localidad' => old('localidad', $perfil->localidad ?? ''),
            'estado' => old('estado', $perfil->estado ?? ''),
            'codigo_postal' => old('codigo_postal', $perfil->codigo_postal ?? $perfil->cp ?? $info->codigo_postal ?? $info->cp ?? ''),
            'pais' => old('pais', $perfil->pais ?? 'MEX'),
            'telefono' => old('telefono', $perfil->telefono ?? ''),
            'nombre_contacto' => old('nombre_contacto', $perfil->nombre_contacto ?? ''),
            'regimen_fiscal' => old('regimen_fiscal', $perfil->numero_regimen33 ?? $perfil->numero_regimen ?? $info->numero_regimen33 ?? $info->numero_regimen ?? $info->regimen_fiscal ?? ''),
        ];
    }

    private function getInfoFactura(int $userId): ?object
    {
        if (!Schema::hasTable('users_info_factura')) {
            return null;
        }

        return DB::table('users_info_factura')->where('users_id', $userId)->first();
    }

    private function getDocumentos(?int $infoFacturaId): array
    {
        if (!$infoFacturaId || !Schema::hasTable('users_info_factura_documentos')) {
            return [];
        }

        $docs = DB::table('users_info_factura_documentos')
            ->where('users_factura_info_id', $infoFacturaId)
            ->orderByDesc('id')
            ->get()
            ->map(function ($doc) {
                $doc->resolved_path = $this->resolveDocumentoPath($doc);
                return $doc;
            });

        $byType = [];
        foreach ($docs as $doc) {
            if (!array_key_exists($doc->tipo, $byType)) {
                $byType[$doc->tipo] = $doc;
            }
        }

        return $byType;
    }

    private function upsertInfoFactura(int $userId, array $data): object
    {
        $row = $this->ensureInfoFactura($userId);
        $this->updateTableRow('users_info_factura', (int) $row->id, $data);

        return DB::table('users_info_factura')->where('id', $row->id)->first();
    }

    private function ensureInfoFactura(int $userId, array $defaults = []): object
    {
        if (!Schema::hasTable('users_info_factura')) {
            throw new \RuntimeException('No existe la tabla users_info_factura en esta base.');
        }

        $row = DB::table('users_info_factura')->where('users_id', $userId)->first();
        if ($row) {
            if ($defaults !== []) {
                $this->updateTableRow('users_info_factura', (int) $row->id, $defaults);
                return DB::table('users_info_factura')->where('id', $row->id)->first();
            }

            return $row;
        }

        $payload = $this->filterColumns('users_info_factura', array_merge(['users_id' => $userId], $defaults));
        if ($payload === []) {
            throw new \RuntimeException('No fue posible crear users_info_factura: faltan columnas compatibles.');
        }

        $id = DB::table('users_info_factura')->insertGetId($payload);

        return DB::table('users_info_factura')->where('id', $id)->first();
    }

    private function upsertByUser(string $table, int $userId, array $data): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $row = DB::table($table)->where('users_id', $userId)->first();
        if ($row) {
            $this->updateTableRow($table, (int) $row->id, $data);
            return;
        }

        $payload = $this->filterColumns($table, array_merge(['users_id' => $userId], $data));
        if ($payload !== []) {
            DB::table($table)->insert($payload);
        }
    }

    private function updateTableRow(string $table, int $id, array $data): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $payload = $this->filterColumns($table, $data);
        if ($payload !== []) {
            DB::table($table)->where('id', $id)->update($payload);
        }
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(fn ($value, $key) => in_array($key, $columns, true))
            ->all();
    }

    private function storeLogo(int $userId, UploadedFile $file): void
    {
        $binary = file_get_contents($file->getRealPath());
        if ($binary === false) {
            throw new \RuntimeException('No pude leer el archivo del logo.');
        }

        $this->storeLogoBinary($userId, $binary);
    }

    private function storeLogoFromBase64(int $userId, string $dataUrl): void
    {
        if (!preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', $dataUrl)) {
            throw new \RuntimeException('El recorte del logo no tiene un formato válido.');
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($binary === false) {
            throw new \RuntimeException('No pude procesar el logo recortado.');
        }

        $this->storeLogoBinary($userId, $binary);
    }

    private function storeLogoBinary(int $userId, string $binary): void
    {
        $baseDir = public_path('uploads/users_logos');
        $thumbDir = public_path('uploads/users_logos/thumbnails');

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0775, true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'logo_');
        if ($tempPath === false || file_put_contents($tempPath, $binary) === false) {
            throw new \RuntimeException('No pude preparar temporalmente el logo.');
        }

        try {
            $this->deleteExistingLogoFiles($userId);
            $this->processLogoWithUpload($tempPath, $baseDir, (string) $userId, 'jpg', 320, 320, 90);
            $this->processLogoWithUpload($tempPath, $thumbDir, (string) $userId, 'png', 320, 320, 95);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function processLogoWithUpload(string $sourcePath, string $destination, string $filename, string $format, int $width, int $height, int $quality): void
    {
        if (!class_exists(\Verot\Upload\Upload::class)) {
            $uploadClass = base_path('vendor/verot/class.upload.php/src/class.upload.php');
            if (is_file($uploadClass)) {
                require_once $uploadClass;
            }
        }

        if (!class_exists(\Verot\Upload\Upload::class)) {
            $this->processLogoWithGd($sourcePath, $destination, $filename, $format, $width, $height, $quality);
            return;
        }

        $handle = new \Verot\Upload\Upload($sourcePath);
        if (!$handle->uploaded) {
            throw new \RuntimeException('No pude abrir la imagen del logo para procesarla.');
        }

        $handle->allowed = ['image/*'];
        $handle->file_overwrite = true;
        $handle->file_auto_rename = false;
        $handle->file_new_name_body = $filename;
        $handle->image_resize = true;
        $handle->image_ratio_crop = true;
        $handle->image_x = $width;
        $handle->image_y = $height;
        $handle->image_convert = $format;
        $handle->jpeg_quality = $quality;
        $handle->png_compression = 9;
        $handle->image_background_color = '#FFFFFF';
        $handle->process($destination);

        if (!$handle->processed) {
            $error = trim((string) $handle->error);
            $handle->clean();
            throw new \RuntimeException($error !== '' ? $error : 'No pude guardar el logo procesado.');
        }

        $handle->clean();
    }

    private function processLogoWithGd(string $sourcePath, string $destination, string $filename, string $format, int $width, int $height, int $quality): void
    {
        $binary = file_get_contents($sourcePath);
        if ($binary === false) {
            throw new \RuntimeException('No pude leer temporalmente el logo.');
        }

        $source = @imagecreatefromstring($binary);
        if (!$source) {
            throw new \RuntimeException('No pude abrir la imagen del logo para procesarla.');
        }

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        $scale = min($width / max(1, $srcWidth), $height / max(1, $srcHeight));
        $drawWidth = max(1, (int) round($srcWidth * $scale));
        $drawHeight = max(1, (int) round($srcHeight * $scale));
        $dstX = (int) floor(($width - $drawWidth) / 2);
        $dstY = (int) floor(($height - $drawHeight) / 2);

        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas) {
            imagedestroy($source);
            throw new \RuntimeException('No pude preparar la imagen final del logo.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $drawWidth, $drawHeight, $srcWidth, $srcHeight);

        $target = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename . '.' . $format;
        $saved = match (strtolower($format)) {
            'jpg', 'jpeg' => imagejpeg($canvas, $target, $quality),
            'png' => imagepng($canvas, $target, 9),
            default => false,
        };

        imagedestroy($canvas);
        imagedestroy($source);

        if (!$saved) {
            throw new \RuntimeException('No pude guardar el logo procesado.');
        }
    }

    private function deleteExistingLogoFiles(int $userId): void
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $main = public_path("uploads/users_logos/{$userId}.{$ext}");
            if (is_file($main)) {
                @unlink($main);
            }

            $thumb = public_path("uploads/users_logos/thumbnails/{$userId}.{$ext}");
            if (is_file($thumb)) {
                @unlink($thumb);
            }
        }
    }

    private function getLogoUrl(int $userId): ?string
    {
        $path = public_path("uploads/users_logos/thumbnails/{$userId}.png");
        if (!is_file($path)) {
            return null;
        }

        return asset("uploads/users_logos/thumbnails/{$userId}.png") . '?v=' . filemtime($path);
    }

    private function storeDocumentoArchivo(UploadedFile $file, int $userId, string $extension): array
    {
        $dir = public_path('uploads/users_documentos');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $base = $userId . '_' . now()->format('YmdHis') . '_' . Str::random(8) . '.' . $extension;
        $file->move($dir, $base);

        return [
            'name' => $base,
            'path' => $dir . DIRECTORY_SEPARATOR . $base,
            'relative_dir' => 'uploads/users_documentos',
        ];
    }

    private function validarCsd(string $rfc, string $password, string $cerPath, string $keyPath): object
    {
        $response = Http::timeout(60)
            ->withoutVerifying()
            ->attach('cer', fopen($cerPath, 'r'), basename($cerPath))
            ->attach('key', fopen($keyPath, 'r'), basename($keyPath))
            ->post('https://app.totalnot.mx/validador/api/valida.php', [
                'rfc' => $rfc,
                'clave' => $password,
            ]);

        if (!$response->ok()) {
            throw new \RuntimeException('No se pudo validar el CSD contra el servicio remoto.');
        }

        $json = json_decode($response->body());
        if (!$json) {
            throw new \RuntimeException('El validador respondió un formato no reconocido.');
        }

        if (isset($json->isError) && $json->isError) {
            throw new \RuntimeException((string) ($json->texto ?? 'El validador rechazó los sellos.'));
        }

        return $json;
    }

    private function convertCerToPem(string $cerPath): void
    {
        $output = $cerPath . '.pem';
        $command = sprintf(
            'openssl x509 -inform DER -outform PEM -in %s -pubkey -out %s 2>&1',
            escapeshellarg($cerPath),
            escapeshellarg($output)
        );

        exec($command, $lines, $code);
        if ($code !== 0 || !is_file($output)) {
            throw new \RuntimeException('No se pudo convertir el certificado a PEM.');
        }
    }

    private function convertKeyToPem(string $keyPath, string $password): void
    {
        $output = $keyPath . '.pem';
        $command = sprintf(
            'openssl pkcs8 -inform DER -in %s -out %s -passin pass:%s 2>&1',
            escapeshellarg($keyPath),
            escapeshellarg($output),
            escapeshellarg($password)
        );

        exec($command, $lines, $code);
        if ($code !== 0 || !is_file($output)) {
            throw new \RuntimeException('No se pudo convertir la llave a PEM. Verifica la contraseña del CSD.');
        }
    }

    private function replaceDocumento(int $infoFacturaId, string $tipo, array $stored, array $extra = []): void
    {
        $existing = DB::table('users_info_factura_documentos')
            ->where('users_factura_info_id', $infoFacturaId)
            ->where('tipo', $tipo)
            ->get();

        foreach ($existing as $doc) {
            DB::table('users_info_factura_documentos')->where('id', $doc->id)->delete();
            $this->deletePhysicalDocumento($this->resolveDocumentoPath($doc));
        }

        $payload = $this->filterColumns('users_info_factura_documentos', array_merge([
            'users_factura_info_id' => $infoFacturaId,
            'tipo' => $tipo,
            'revisado' => 1,
            'validado' => 1,
            '_name' => $stored['name'],
            '_path' => $stored['relative_dir'],
            'path' => $stored['path'],
            'numero_certificado' => pathinfo($stored['name'], PATHINFO_FILENAME),
        ], $extra));

        DB::table('users_info_factura_documentos')->insert($payload);
    }

    private function resolveDocumentoPath(object $doc): string
    {
        $path = trim((string) ($doc->_path ?? $doc->path ?? ''));
        $name = trim((string) ($doc->_name ?? ''));

        if ($path === '' && $name !== '') {
            return public_path('uploads/users_documentos' . DIRECTORY_SEPARATOR . $name);
        }

        if ($path === '') {
            return '';
        }

        if ($path[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $path)) {
            if ($name !== '' && basename($path) !== $name) {
                return rtrim($path, "/\\") . DIRECTORY_SEPARATOR . $name;
            }

            return $path;
        }

        $full = public_path(ltrim($path, "/\\"));
        if ($name !== '' && basename($full) !== $name) {
            return rtrim($full, "/\\") . DIRECTORY_SEPARATOR . $name;
        }

        return $full;
    }

    private function deletePhysicalDocumento(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }

        if ($path !== '' && is_file($path . '.pem')) {
            @unlink($path . '.pem');
        }
    }
}
