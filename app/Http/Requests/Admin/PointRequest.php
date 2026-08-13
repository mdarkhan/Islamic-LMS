<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('points.grant') ?? false;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['credit', 'deduct'])],
            'amount' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'কারণ উল্লেখ করা আবশ্যক।',
            'amount.min' => 'পরিমাণ কমপক্ষে ১ হতে হবে।',
        ];
    }
}
