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

if (! function_exists('csv_safe')) {
    /**
     * Neutralise formula injection in a CSV cell (OWASP CSV Injection). A cell
     * starting with =, +, -, @, tab or CR is interpreted as a formula by Excel,
     * Google Sheets and LibreOffice when the file is opened — e.g. a student's own
     * "phone" field (free text, self- or admin-edited: ProfileController::update,
     * StudentUpdateRequest) or an imported name could carry a payload like
     * `=cmd|' /C calc'!A0`. Prefixing with a single quote keeps the value literal
     * and still human-readable in the spreadsheet.
     */
    function csv_safe(int|string|float|null $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
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
