<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CsdStatus
{
    public static function forUser(?int $userId): array
    {
        if (!$userId || !Schema::hasTable('users_info_factura') || !Schema::hasTable('users_info_factura_documentos')) {
            return self::build('red', 'Sin sellos');
        }

        $info = DB::table('users_info_factura')->where('users_id', $userId)->first();
        if (!$info) {
            return self::build('red', 'Sin sellos');
        }

        $docs = DB::table('users_info_factura_documentos')
            ->where('users_factura_info_id', $info->id)
            ->whereIn('tipo', ['ARCHIVO_CERTIFICADO', 'ARCHIVO_LLAVE'])
            ->get();

        if ($docs->isEmpty()) {
            return self::build('red', 'Sin sellos');
        }

        if ($docs->contains(fn ($doc) => (int)($doc->validado ?? 0) !== 1)) {
            return self::build('red', 'Sellos no validados');
        }

        $dates = $docs
            ->pluck('vigencia')
            ->filter()
            ->map(function ($value) {
                try {
                    return Carbon::parse((string) $value)->endOfDay();
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->values();

        if ($dates->isEmpty()) {
            return self::build('red', 'Vigencia no disponible');
        }

        $expiresAt = $dates->sort()->first();
        $days = Carbon::now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);

        if ($days < 0) {
            return self::build('red', 'Sellos vencidos', $days, $expiresAt);
        }

        if ($days <= 7) {
            return self::build('red', "{$days} días", $days, $expiresAt);
        }

        if ($days <= 30) {
            return self::build('yellow', "{$days} días", $days, $expiresAt);
        }

        return self::build('green', "{$days} días", $days, $expiresAt);
    }

    private static function build(string $tone, string $text, ?int $days = null, ?Carbon $expiresAt = null): array
    {
        return [
            'tone' => $tone,
            'text' => $text,
            'days' => $days,
            'expires_at' => $expiresAt?->format('Y-m-d'),
        ];
    }
}
