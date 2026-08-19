<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('lessons.manage') ?? false;
    }

    /**
     * The legacy placeholder "#" is not a real URL — normalise it (and blanks) to an
     * empty string so it passes the nullable url rule and the controller can store
     * it as NULL, rather than being rejected as an invalid URL.
     */
    protected function prepareForValidation(): void
    {
        $resources = $this->input('resources', []);

        foreach ($resources as $i => $row) {
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '#') {
                $resources[$i]['url'] = '';
            }
        }

        if ($resources !== []) {
            $this->merge(['resources' => $resources]);
        }
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'syllabus' => ['nullable', 'string', 'max:5000'],
            // date_label is not submitted: it is always derived from held_on
            // (BengaliText::formatDateLabel) so the calendar is the single source
            // of truth for what students see.
            'held_on' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:6000'],
            'duration_label' => ['nullable', 'string', 'max:100'],
            'media_provider' => ['required', Rule::in(['google_drive', 'external', 'none'])],
            'media_url' => ['nullable', 'url', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],

            // Inline resources. A row with no label is dropped by the controller, so
            // label is simply optional here; only the URL format and kind are checked.
            'resources' => ['array'],
            'resources.*.label' => ['nullable', 'string', 'max:500'],
            'resources.*.url' => ['nullable', 'url', 'max:500'],
            'resources.*.kind' => ['nullable', Rule::in(['book', 'link', 'file', 'note'])],
        ];
    }

    public function messages(): array
    {
        return [
            'resources.*.url.url' => 'রিসোর্স লিংকটি সঠিক নয়।',
        ];
    }
}
