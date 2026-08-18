<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public Ask Ustaz submission. Collects only what is needed to email the
 * question (name, email, optional mobile, optional subject, the question) — no more
 * (data minimisation, brief §65). The submission is never persisted.
 */
class AskUstazRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:150'],
            'question' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'আপনার নাম লিখুন।',
            'email.required' => 'ইমেইল ঠিকানা লিখুন।',
            'email.email' => 'সঠিক ইমেইল ঠিকানা লিখুন।',
            'question.required' => 'আপনার প্রশ্নটি লিখুন।',
            'question.min' => 'প্রশ্নটি আরও একটু বিস্তারিত লিখুন।',
        ];
    }
}
