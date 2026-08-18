<?php

namespace Tests\Feature\Zakat;

use App\Services\Settings\SettingService;
use App\Services\Zakat\ZakatCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZakatCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private ZakatCalculatorService $zakat;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zakat = app(ZakatCalculatorService::class);
        $this->settings = app(SettingService::class);
        // Clean, known rates: silver nisab = 612.36 × 100 = 61,236; gold = 87.48 × 8000 = 699,840.
        $this->settings->set([
            'silver_price_per_gram' => '100',
            'gold_price_per_gram' => '8000',
            'nisab_basis' => 'silver',
        ]);
    }

    private function assets(array $overrides = []): array
    {
        return array_merge(['cash' => '0'], $overrides);
    }

    public function test_below_nisab_owes_nothing_but_still_shows_the_maths(): void
    {
        $r = $this->zakat->calculate($this->assets(['cash' => '50000']), '0', 'silver');

        $this->assertSame('50000.00', $r['net']);
        $this->assertSame('61236.00', $r['nisab']);
        $this->assertFalse($r['nisab_reached']);
        $this->assertSame('0.00', $r['due']);
    }

    public function test_exactly_at_nisab_qualifies(): void
    {
        $r = $this->zakat->calculate($this->assets(['cash' => '61236']), '0', 'silver');

        $this->assertTrue($r['nisab_reached']);
        $this->assertSame('1530.90', $r['due']);   // 61236 × 2.5%
    }

    public function test_above_nisab_owes_two_and_a_half_percent(): void
    {
        $r = $this->zakat->calculate($this->assets(['cash' => '100000']), '0', 'silver');

        $this->assertSame('2500.00', $r['due']);
    }

    public function test_liabilities_are_subtracted(): void
    {
        $r = $this->zakat->calculate($this->assets(['cash' => '200000']), '50000', 'silver');

        $this->assertSame('200000.00', $r['gross']);
        $this->assertSame('50000.00', $r['deductible']);
        $this->assertSame('150000.00', $r['net']);
        $this->assertSame('3750.00', $r['due']);
    }

    public function test_liabilities_never_push_net_below_zero(): void
    {
        $r = $this->zakat->calculate($this->assets(['cash' => '10000']), '99999', 'silver');

        $this->assertSame('10000.00', $r['deductible']);   // capped at gross
        $this->assertSame('0.00', $r['net']);
        $this->assertSame('0.00', $r['due']);
    }

    public function test_gold_and_silver_nisab_use_their_own_rate(): void
    {
        $gold = $this->zakat->calculate($this->assets(['cash' => '700000']), '0', 'gold');
        $this->assertSame('699840.00', $gold['nisab']);
        $this->assertTrue($gold['nisab_reached']);
        $this->assertSame('17500.00', $gold['due']);

        // The same wealth is above the (much lower) silver nisab too.
        $silver = $this->zakat->calculate($this->assets(['cash' => '700000']), '0', 'silver');
        $this->assertSame('61236.00', $silver['nisab']);
    }

    public function test_money_is_decimal_safe(): void
    {
        // 0.10 + 0.20 must be exactly 0.30, not 0.30000000000000004.
        $r = $this->zakat->calculate(['cash' => '0.10', 'bank' => '0.20'], '0', 'silver');
        $this->assertSame('0.30', $r['gross']);
    }

    public function test_missing_or_zero_metal_rate_fails_safe(): void
    {
        $this->settings->set(['silver_price_per_gram' => '0']);
        $r = $this->zakat->calculate($this->assets(['cash' => '100000']), '0', 'silver');

        $this->assertTrue($r['rates_missing']);
        $this->assertNull($r['nisab']);
        $this->assertFalse($r['nisab_reached']);
        $this->assertSame('0.00', $r['due']);
    }

    public function test_negative_and_garbage_inputs_are_normalised_to_zero(): void
    {
        $r = $this->zakat->calculate(['cash' => '-500', 'bank' => 'abc', 'other' => '1,000'], '-9', 'silver');

        // -500 and "abc" → 0; "1,000" → 1000; liabilities -9 → 0.
        $this->assertSame('1000.00', $r['gross']);
        $this->assertSame('0.00', $r['deductible']);
    }

    public function test_vori_converts_with_the_documented_constant(): void
    {
        $this->assertSame('11.6640', $this->zakat->voriToGrams('1'));
        $this->assertSame('58.3200', $this->zakat->voriToGrams('5'));
    }

    public function test_metal_value_from_grams_uses_the_configured_rate(): void
    {
        $this->assertSame('80000.00', $this->zakat->metalValueFromGrams('10', 'gold'));   // 10g × 8000
    }
}
