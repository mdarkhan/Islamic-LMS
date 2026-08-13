<?php

namespace App\Http\Requests\Admin;

use App\Models\QuizQuestion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuizQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('quizzes.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in([QuizQuestion::TYPE_SINGLE, QuizQuestion::TYPE_MULTIPLE])],
            'marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],

            'options' => ['required', 'array', 'min:2', 'max:12'],
            'options.*.body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $options = $this->optionsData();
            $correct = array_filter($options, fn ($o) => $o['correct']);

            if ($correct === []) {
                $validator->errors()->add('options', 'কমপক্ষে একটি সঠিক উত্তর নির্ধারণ করুন।');

                return;
            }

            // Single-choice must have EXACTLY one correct option. Multiple-choice may
            // have one or more — the explicit type is preserved, never inferred.
            if ($this->input('type') === QuizQuestion::TYPE_SINGLE && count($correct) > 1) {
                $validator->errors()->add('options', 'একক-উত্তর প্রশ্নে ঠিক একটি সঠিক উত্তর থাকতে হবে।');
            }
        });
    }

    public function messages(): array
    {
        return [
            'body.required' => 'প্রশ্নের বিবরণ আবশ্যক।',
            'options.min' => 'কমপক্ষে ২টি অপশন প্রয়োজন।',
            'options.max' => 'সর্বোচ্চ ১২টি অপশন দেওয়া যাবে।',
            'options.*.body.required' => 'অপশনের ঘর খালি রাখা যাবে না।',
            'marks.min' => 'নম্বর কমপক্ষে ১ হতে হবে।',
        ];
    }

    /**
     * Normalised option rows: body + a boolean correct flag (an unchecked box does
     * not post, so absence means false).
     *
     * @return array<int, array{body:string, correct:bool}>
     */
    public function optionsData(): array
    {
        return collect($this->input('options', []))
            ->map(fn ($o) => [
                'body' => trim((string) ($o['body'] ?? '')),
                'correct' => (bool) ($o['correct'] ?? false),
            ])
            ->values()
            ->all();
    }
}
