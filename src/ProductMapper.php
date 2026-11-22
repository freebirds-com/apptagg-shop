<?php

namespace Freebirds\ApptaggShop;

use Freebirds\ApptaggShop\Contracts\ProductMapper as Contract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Base class for a shop's product mapper with text and image helpers.
 * Implement query() and map().
 */
abstract class ProductMapper implements Contract
{
    public function prepare(Collection $products): void
    {
    }

    /** Relative path -> absolute URL on APPTAGG_IMAGE_BASE; full URLs pass through. */
    protected function imageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim((string) config('apptagg.image_base'), '/') . '/' . ltrim($path, '/');
    }

    /** Up to 10 unique image URLs, relative paths made absolute. */
    protected function images(array $paths): array
    {
        $urls = array_filter(array_map(function ($p) {
            return $this->imageUrl($p);
        }, $paths));

        return array_values(array_slice(array_unique($urls), 0, 10));
    }

    /** Rich-text HTML -> readable plain text. */
    protected function plainText(?string $html): ?string
    {
        if (!$html) {
            return null;
        }
        $text = preg_replace('#<\s*(br|/p|/li|/h[1-6]|/div)\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = preg_replace("/\n\s*\n+/", "\n\n", $text);

        return trim($text) ?: null;
    }

    /** HTML list or text lines -> up to $max short bullet strings. */
    protected function lines(?string $html, int $max = 20): array
    {
        $text = $this->plainText($html);
        if (!$text) {
            return [];
        }

        return collect(preg_split('/\n+/', $text))
            ->map(function ($line) {
                return Str::limit(trim($line, " \t-•*"), 188, '');
            })
            ->filter()
            ->take($max)
            ->values()
            ->all();
    }

    protected function limit(?string $value, int $max): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : Str::limit($value, $max, '');
    }
}
