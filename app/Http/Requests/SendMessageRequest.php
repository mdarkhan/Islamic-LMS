<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of one outgoing message. WHO may post into WHICH thread is not
 * decided here — the student routes are scoped to the sender's own thread, and the admin
 * routes sit behind `perm:messages.view`.
 */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'মেসেজ লিখুন।',
            'body.max' => 'মেসেজ সর্বোচ্চ ৫০০০ অক্ষরের হতে পারে।',
        ];
    }
}
