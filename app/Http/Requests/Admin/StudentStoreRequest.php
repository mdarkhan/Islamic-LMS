<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.create') ?? false;
    }

    /**
     * Roll is normalised (Bengali → Latin) before the uniqueness check so "২৫" and
     * "25" cannot both be created.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('roll')) {
            $this->merge(['roll' => User::normaliseRoll($this->input('roll'))]);
        }
    }

    public function rules(): array
    {
        return [
            'roll' => ['required', 'string', 'max:50', Rule::unique('users', 'roll')],
            'name' => ['required', 'string', 'max:150'],
            'guardian_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password_mode' => ['required', Rule::in(['manual', 'generate'])],
            'password' => ['exclude_unless:password_mode,manual', 'required', Password::min(6)],
        ];
    }

    public function messages(): array
    {
        return [
            'roll.unique' => 'এই রোল নম্বরটি ইতিমধ্যে ব্যবহৃত হয়েছে।',
            'roll.required' => 'রোল নম্বর আবশ্যক।',
            'name.required' => 'নাম আবশ্যক।',
            'guardian_name.required' => 'পিতা/স্বামীর নাম আবশ্যক।',
        ];
    }
}
