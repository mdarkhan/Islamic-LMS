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
     * Render a number in Bengali digits, for display only.
     */
    function bn(int|string|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return BengaliText::toBengaliDigits((string) $value);
    }
}
