<?php

namespace App\Support;

use Illuminate\Support\Str;

class SeoText
{
    public static function plain(string $value): string
    {
        $value = (string) preg_replace('/<\s*(script|style)\b[^>]*>.*?<\/\s*\1\s*>/is', '', $value);
        $value = (string) preg_replace('/<\/?(?:p|div|h[1-6]|li|ul|ol|blockquote|br)\b[^>]*>/i', ' ', $value);

        return Str::squish(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
