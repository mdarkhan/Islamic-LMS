<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('courses.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['sometimes', 'boolean'],

            // Course-topper rewards: position → points.
            'topper_rewards' => ['nullable', 'array', 'max:50'],
            'topper_rewards.*.position' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'topper_rewards.*.points' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
