<?php

namespace App\Http\Requests\Admin;

use App\Models\Notice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('notices.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            // Times are Asia/Dhaka (the app timezone). ends_at must be after starts_at
            // when both are given (brief §13).
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'priority' => ['required', 'integer', 'between:-100,100'],
            'audience' => ['required', Rule::in([Notice::AUDIENCE_PUBLIC, Notice::AUDIENCE_STUDENTS, Notice::AUDIENCE_ALL])],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'নোটিশের বিষয়বস্তু আবশ্যক।',
            'ends_at.after' => 'শেষ সময় অবশ্যই শুরুর সময়ের পরে হতে হবে।',
        ];
    }
}
