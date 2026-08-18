<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZakatRequest;
use App\Services\Settings\SettingService;
use App\Services\Zakat\ZakatCalculatorService;
use Illuminate\View\View;

/**
 * Public Zakat calculator. No login, and NO persistence — entered financial values are
 * used to compute a result for the request and then discarded; nothing is stored or
 * logged (brief §64). The authoritative arithmetic is ZakatCalculatorService; the page
 * may mirror it client-side for a live preview using the same reference constants.
 */
class ZakatController extends Controller
{
    public function __construct(
        private readonly ZakatCalculatorService $zakat,
        private readonly SettingService $settings,
    ) {}

    public function index(): View
    {
        return view('public.zakat', $this->pageData());
    }

    public function calculate(ZakatRequest $request): View
    {
        $result = $this->zakat->calculate(
            $request->assets(),
            $request->input('liabilities'),
            $request->input('basis'),
        );

        return view('public.zakat', array_merge($this->pageData(), [
            'result' => $result,
            'old' => $request->all(),
        ]));
    }

    /** Reference data the form + client preview need (rates, constants, currency). */
    private function pageData(): array
    {
        return [
            'basis' => (string) $this->settings->get('nisab_basis', ZakatCalculatorService::BASIS_SILVER),
            'currency' => (string) $this->settings->get('currency_label', '৳'),
            'goldRate' => $this->settings->get('gold_price_per_gram'),
            'silverRate' => $this->settings->get('silver_price_per_gram'),
            'goldUpdatedAt' => $this->settings->updatedAt('gold_price_per_gram'),
            'silverUpdatedAt' => $this->settings->updatedAt('silver_price_per_gram'),
            'goldNisab' => $this->zakat->nisabValue(ZakatCalculatorService::BASIS_GOLD),
            'silverNisab' => $this->zakat->nisabValue(ZakatCalculatorService::BASIS_SILVER),
            'constants' => [
                'gold_nisab_grams' => ZakatCalculatorService::NISAB_GOLD_GRAMS,
                'silver_nisab_grams' => ZakatCalculatorService::NISAB_SILVER_GRAMS,
                'vori_to_gram' => ZakatCalculatorService::VORI_TO_GRAM,
                'rate' => ZakatCalculatorService::RATE,
            ],
        ];
    }
}
