<?php

use App\Services\Import\BengaliText;

if (! function_exists('resource_label_html')) {
    /**
     * Render a lesson-resource label safely. Legacy labels embed literal <br /> for
     * line breaks; we keep only those breaks and escape everything else, so a
     * malicious label can never inject markup.
     */
    function resource_label_html(?string $label): string
    {
        $withBreaks = preg_replace('#<br\s*/?>#i', "\n", (string) $label);

        return nl2br(e($withBreaks));
    }
}

if (! function_exists('bn')) {
    /**
     * Render a number for display, locale-aware: Bengali digits in the Bengali
     * interface, Latin digits in the English interface. Used for interface figures
     * (counts, points, scores); content strings are unaffected.
     */
    function bn(int|string|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        return app()->getLocale() === 'en'
            ? BengaliText::toLatinDigits($value)
            : BengaliText::toBengaliDigits($value);
    }
}
