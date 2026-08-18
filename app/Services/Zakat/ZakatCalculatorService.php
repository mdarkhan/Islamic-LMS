<?php

namespace App\Services\Zakat;

use App\Services\Settings\SettingService;

/**
 * The authoritative Zakat calculation. Money arithmetic is done with BCMath on decimal
 * STRINGS — never binary floats — so totals are exact to the currency's minor unit.
 * The browser may mirror these formulas for a live preview, but this class is the truth
 * and the thing the tests exercise (brief §27, §31).
 *
 * Standard rule only: 2.5% of net zakatable wealth once it reaches Nisab. It does not
 * pretend to resolve fiqh edge cases — that is what the disclaimer is for.
 *
 * Documented constants (kept here, never scattered through Blade/JS):
 *   - Nisab of gold  = 20 mithqāl ≈ 87.48 g
 *   - Nisab of silver = 200 dirham ≈ 612.36 g
 *   - 1 ভরি (bhori/tola) = 11.664 g  (16 ana), the value used in Bangladesh
 *   - Zakat rate = 2.5% = 0.025
 */
class ZakatCalculatorService
{
    public const NISAB_GOLD_GRAMS = '87.48';
    public const NISAB_SILVER_GRAMS = '612.36';
    public const VORI_TO_GRAM = '11.664';
    public const RATE = '0.025';

    /** Working precision; results are rounded to money scale for presentation. */
    private const CALC_SCALE = 6;
    private const MONEY_SCALE = 2;

    /** The asset fields the calculator sums, in display order. */
    public const ASSET_KEYS = [
        'cash', 'bank', 'gold_value', 'silver_value',
        'inventory', 'investments', 'receivable', 'other',
    ];

    public const BASIS_GOLD = 'gold';
    public const BASIS_SILVER = 'silver';

    public function __construct(private readonly SettingService $settings) {}

    /**
     * @param  array<string, string|float|int|null>  $assets   keyed by ASSET_KEYS (currency values)
     * @param  string|float|int|null  $liabilities             eligible short-term liabilities
     * @param  string|null  $basis                             'gold'|'silver'; defaults to the configured basis
     * @return array{
     *     gross: string, deductible: string, net: string, basis: string,
     *     nisab: ?string, nisab_reached: bool, rate: string, due: string,
     *     rates_missing: bool, currency: string, updated_at: ?string
     * }
     */
    public function calculate(array $assets, string|float|int|null $liabilities, ?string $basis = null): array
    {
        $basis = in_array($basis, [self::BASIS_GOLD, self::BASIS_SILVER], true)
            ? $basis
            : (string) $this->settings->get('nisab_basis', self::BASIS_SILVER);

        $gross = '0';
        foreach (self::ASSET_KEYS as $key) {
            $gross = bcadd($gross, $this->decimal($assets[$key] ?? null), self::CALC_SCALE);
        }

        $liab = $this->decimal($liabilities);
        // Liabilities never make net negative — deductible is capped at the gross.
        $deductible = bccomp($liab, $gross, self::CALC_SCALE) > 0 ? $gross : $liab;
        $net = bcsub($gross, $deductible, self::CALC_SCALE);

        $nisab = $this->nisabValue($basis);
        $ratesMissing = $nisab === null;

        $reached = ! $ratesMissing && bccomp($net, $nisab, self::CALC_SCALE) >= 0;
        $due = $reached ? bcmul($net, self::RATE, self::CALC_SCALE) : '0';

        return [
            'gross' => $this->money($gross),
            'deductible' => $this->money($deductible),
            'net' => $this->money($net),
            'basis' => $basis,
            'nisab' => $nisab === null ? null : $this->money($nisab),
            'nisab_reached' => $reached,
            'rate' => self::RATE,
            'due' => $this->money($due),
            'rates_missing' => $ratesMissing,
            'currency' => (string) $this->settings->get('currency_label', '৳'),
            'updated_at' => $this->settings->updatedAt($basis === self::BASIS_GOLD ? 'gold_price_per_gram' : 'silver_price_per_gram')?->toIso8601String(),
        ];
    }

    /**
     * Nisab value in currency for the given basis, or null when the relevant metal rate
     * is not configured (or zero) — the calculator then fails safe with a warning rather
     * than inventing a threshold.
     */
    public function nisabValue(string $basis): ?string
    {
        [$grams, $rateKey] = $basis === self::BASIS_GOLD
            ? [self::NISAB_GOLD_GRAMS, 'gold_price_per_gram']
            : [self::NISAB_SILVER_GRAMS, 'silver_price_per_gram'];

        $rate = $this->settings->get($rateKey);
        if ($rate === null || $rate === '' || bccomp($this->decimal($rate), '0', self::CALC_SCALE) <= 0) {
            return null;
        }

        return bcmul($grams, $this->decimal($rate), self::CALC_SCALE);
    }

    /** Value of a metal weight at a per-gram rate (for the "weight × rate" input mode). */
    public function metalValueFromGrams(string|float|int|null $grams, string $basis): string
    {
        $rate = $this->settings->get($basis === self::BASIS_GOLD ? 'gold_price_per_gram' : 'silver_price_per_gram');

        if ($rate === null) {
            return '0.00';
        }

        return $this->money(bcmul($this->decimal($grams), $this->decimal($rate), self::CALC_SCALE));
    }

    /** Convert ভরি to grams using the documented Bangladesh constant. */
    public function voriToGrams(string|float|int|null $vori): string
    {
        return bcmul($this->decimal($vori), self::VORI_TO_GRAM, 4);
    }

    /**
     * Normalise any input to a safe, non-negative decimal string. Commas and spaces are
     * stripped; non-numeric or negative values become '0' so a crafted input can never
     * corrupt the arithmetic (brief §56).
     */
    private function decimal(string|float|int|null $value): string
    {
        if ($value === null) {
            return '0';
        }

        $clean = str_replace([',', ' '], '', (string) $value);

        if (! is_numeric($clean) || bccomp($clean, '0', self::CALC_SCALE) < 0) {
            return '0';
        }

        return $clean;
    }

    private function money(string $value): string
    {
        // Round half-up to the money scale (bcmath truncates, so add half a unit first).
        $half = '0.'.str_repeat('0', self::MONEY_SCALE).'5';

        return bcadd($value, bccomp($value, '0', self::CALC_SCALE) >= 0 ? $half : "-$half", self::MONEY_SCALE);
    }
}
