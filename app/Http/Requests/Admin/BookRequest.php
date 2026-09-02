<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('books.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'slug' => ['nullable', 'string', 'max:200'],
            'author' => ['required', 'string', 'max:200'],
            'publisher' => ['nullable', 'string', 'max:200'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'details' => ['nullable', 'string', 'max:5000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_published' => ['sometimes', 'boolean'],

            // Inline purchase links. A row with no website name is dropped by the
            // controller, so it is simply optional here; only the URL format is checked.
            'purchase_links' => ['array'],
            'purchase_links.*.website_name' => ['nullable', 'string', 'max:150'],
            'purchase_links.*.url' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_links.*.url.url' => 'কেনার লিংকটি সঠিক নয়।',
        ];
    }
}
