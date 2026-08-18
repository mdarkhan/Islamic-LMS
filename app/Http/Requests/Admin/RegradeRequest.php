<?php

namespace App\Http\Requests\Admin;

use App\Models\QuizQuestion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A single-question answer-key / marks correction. The question must belong to the quiz
 * being regraded, and every proposed correct option must belong to that question — the
 * regrade re-parses these from the request, never trusting any posted preview (brief §33).
 */
class RegradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('results.regrade') ?? false;
    }

    public function rules(): array
    {
        $quiz = $this->route('quiz');

        return [
            'question_id' => ['required', 'integer', Rule::exists('quiz_questions', 'id')->where('quiz_id', $quiz->id)],
            'type' => ['required', Rule::in([QuizQuestion::TYPE_SINGLE, QuizQuestion::TYPE_MULTIPLE])],
            'marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'correct_option_ids' => ['required', 'array', 'min:1'],
            'correct_option_ids.*' => ['integer', Rule::exists('quiz_options', 'id')->where('question_id', $this->input('question_id'))],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $correct = array_unique(array_map('intval', (array) $this->input('correct_option_ids', [])));

            // Single-choice must have exactly one correct option; the type is explicit
            // and never inferred from the count (matches the builder's rule).
            if ($this->input('type') === QuizQuestion::TYPE_SINGLE && count($correct) !== 1) {
                $validator->errors()->add('correct_option_ids', 'একক-উত্তর প্রশ্নে ঠিক একটি সঠিক উত্তর থাকতে হবে।');
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'পুনর্মূল্যায়নের কারণ উল্লেখ করা আবশ্যক।',
            'correct_option_ids.required' => 'কমপক্ষে একটি সঠিক উত্তর নির্বাচন করুন।',
        ];
    }

    /** @return array<int, int> */
    public function correctOptionIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('correct_option_ids', []))));
    }
}
