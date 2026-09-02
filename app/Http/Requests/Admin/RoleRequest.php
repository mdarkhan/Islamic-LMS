<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::SUPER_ADMIN) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'label' => ['required', 'string', 'max:100'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];

        // name (slug) is set on create only — never editable afterward, since
        // Role::ADMIN-style constants and hasRole() calls reference it by name
        // throughout the codebase.
        if (! $this->route('role')) {
            $rules['name'] = ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('roles', 'name')];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'label.required' => 'রোলের নাম (লেবেল) আবশ্যক।',
            'name.required' => 'রোল আইডি আবশ্যক।',
            'name.alpha_dash' => 'রোল আইডিতে শুধু ইংরেজি অক্ষর, সংখ্যা, - ও _ ব্যবহার করা যাবে।',
            'name.unique' => 'এই রোল আইডি ইতিমধ্যে ব্যবহৃত হয়েছে।',
        ];
    }
}
