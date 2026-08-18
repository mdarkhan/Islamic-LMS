<?php

namespace App\Http\Requests;

use App\Services\Zakat\ZakatCalculatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates Zakat calculator input. Values are optional (blank = 0) and must be
 * non-negative numbers. Nothing here is persisted — the request exists only to feed the
 * calculation (brief §64).
 */
class ZakatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999999'];

        return [
            'cash' => $money,
            'bank' => $money,
            'gold_value' => $money,
            'silver_value' => $money,
            'inventory' => $money,
            'investments' => $money,
            'receivable' => $money,
            'other' => $money,
            'liabilities' => $money,
            'basis' => ['required', Rule::in([ZakatCalculatorService::BASIS_GOLD, ZakatCalculatorService::BASIS_SILVER])],
        ];
    }

    /** @return array<string, mixed> */
    public function assets(): array
    {
        return $this->only(ZakatCalculatorService::ASSET_KEYS);
    }
}
