<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already gates this (role:super_admin); re-checked here as
        // defense in depth, matching every other admin Form Request in this codebase.
        return $this->user()?->hasRole(Role::SUPER_ADMIN) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            // A staff account is never assignable the student role from here — that
            // population is managed entirely by the Students area.
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where(fn ($q) => $q->where('name', '!=', Role::STUDENT))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'নাম আবশ্যক।',
            'email.required' => 'ইমেইল আবশ্যক।',
            'email.unique' => 'এই ইমেইলটি ইতিমধ্যে ব্যবহৃত হয়েছে।',
            'role_id.required' => 'একটি রোল বেছে নিন।',
        ];
    }
}
