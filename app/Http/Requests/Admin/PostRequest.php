<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('posts.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'post_category_id' => ['required', Rule::exists('post_categories', 'id')],
            'slug' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'featured_image' => ['nullable', 'url', 'max:500'],
            'seo_title' => ['nullable', 'string', 'max:250'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in([Post::STATUS_DRAFT, Post::STATUS_PUBLISHED, Post::STATUS_ARCHIVED])],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'শিরোনাম আবশ্যক।',
            'post_category_id.required' => 'একটি বিভাগ নির্বাচন করুন।',
            'body.required' => 'লেখার মূল অংশ আবশ্যক।',
        ];
    }
}
