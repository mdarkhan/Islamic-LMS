<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('roll')) {
            $this->merge(['roll' => User::normaliseRoll($this->input('roll'))]);
        }
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'roll' => ['required', 'string', 'max:50', Rule::unique('users', 'roll')->ignore($student->id)],
            'name' => ['required', 'string', 'max:150'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($student->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
