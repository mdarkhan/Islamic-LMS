<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public Contact submission. Collects only what is needed to email the
 * message (name, email, optional mobile, optional subject, the message) — no more
 * (data minimisation, same as AskUstazRequest). The submission is never persisted.
 */
class ContactRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'আপনার নাম লিখুন।',
            'email.required' => 'ইমেইল ঠিকানা লিখুন।',
            'email.email' => 'সঠিক ইমেইল ঠিকানা লিখুন।',
            'message.required' => 'আপনার বার্তাটি লিখুন।',
            'message.min' => 'বার্তাটি আরও একটু বিস্তারিত লিখুন।',
        ];
    }
}
