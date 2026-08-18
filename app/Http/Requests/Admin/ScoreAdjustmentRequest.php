<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('results.adjust') ?? false;
    }

    public function rules(): array
    {
        return [
            // The new absolute manual delta (signed). Bounds against total are enforced
            // by ScoreAdjustmentService, which knows the attempt's total.
            'manual_adjustment' => ['required', 'integer', 'between:-1000,1000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'সমন্বয়ের কারণ উল্লেখ করা আবশ্যক।',
            'manual_adjustment.required' => 'সমন্বয়ের মান দিন।',
        ];
    }
}
