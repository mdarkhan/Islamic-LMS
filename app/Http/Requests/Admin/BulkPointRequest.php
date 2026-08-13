<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('points.grant') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'direction' => ['required', Rule::in(['credit', 'deduct'])],
            'amount' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'কমপক্ষে একজন শিক্ষার্থী নির্বাচন করুন।',
            'reason.required' => 'কারণ উল্লেখ করা আবশ্যক।',
        ];
    }
}
