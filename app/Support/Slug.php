<?php

namespace App\Support;

use App\Services\Import\BengaliText;

/**
 * Unicode-safe slugs.
 *
 * Unlike Str::slug (which strips every non-ASCII character and would reduce a
 * Bengali title to nothing), this preserves Bengali/Arabic letters and digits so an
 * authored title like "সীরাত-২৮ হিজরতের পূর্বপ্রস্তুতি" becomes a real, readable slug
 * rather than a generic "lesson-2". English titles still get ordinary lowercase
 * Latin slugs.
 *
 * The result is deterministic and safe to place in a URL path (Laravel matches
 * percent-encoded UTF-8 route segments against the stored slug).
 */
class Slug
{
    /**
     * Build a base slug. Keeps Unicode letters, combining marks (Bengali matra /
     * nukta) and numbers; every run of anything else collapses to a single hyphen.
     */
    public static function make(string $text, string $fallback = 'item'): string
    {
        // NFC first so য়/ড়/ঢ় and their matras compare and render consistently.
        $text = BengaliText::normalise($text);
        $text = mb_strtolower($text, 'UTF-8');

        // \p{L} letters, \p{M} combining marks, \p{N} numbers — everything else → "-".
        $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $text) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }

    /**
     * Build a slug that does not yet exist, appending -2, -3, … on collision.
     *
     * @param  callable(string):bool  $exists  returns true if the slug is taken
     */
    public static function unique(string $text, callable $exists, string $fallback = 'item'): string
    {
        $base = self::make($text, $fallback);
        $slug = $base;
        $n = 1;

        while ($exists($slug)) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
