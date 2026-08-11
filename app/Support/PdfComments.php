<?php

namespace App\Support;

class PdfComments
{
    public static function combine(string $manual, string $forced, bool $enabled): string
    {
        if (!$enabled) {
            return $manual;
        }

        $forced = str_replace(["\r\n", "\r"], "\n", $forced);
        if (trim($forced) === '') {
            return $manual;
        }

        if (trim($manual) === '') {
            return $forced;
        }

        return $manual . "\n\n" . $forced;
    }
}
