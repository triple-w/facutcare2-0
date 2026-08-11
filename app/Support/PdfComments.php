<?php

namespace App\Support;

class PdfComments
{
    public static function combine(string $manual, string $forced, bool $enabled): string
    {
        if (!$enabled) {
            return $manual;
        }

        $manual = trim($manual);
        $forced = trim(str_replace(["\r\n", "\r"], "\n", $forced));

        if ($forced === '') {
            return $manual;
        }

        if ($manual === '') {
            return $forced;
        }

        return $manual . "\n\n" . $forced;
    }
}
