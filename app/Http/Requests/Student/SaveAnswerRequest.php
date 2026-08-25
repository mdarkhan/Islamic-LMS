<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of an autosave payload only. Ownership of the attempt and
 * the legality of the selection (including foreign options) is enforced
 * downstream — ownership in the controller, selection in
 * QuizAttemptService::saveAnswer, which fails closed.
 */
class SaveAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Attempt ownership is checked in the controller; a signed-in student may
        // reach this far. Never trust a user id from the payload.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // An empty array is legal: it clears the answer (unanswered). The key is
            // optional so a bare clear request is still accepted.
            'option_ids' => ['sometimes', 'array'],
            'option_ids.*' => ['integer'],
        ];
    }

    /**
     * The requested option ids, normalised to ints. Absent => empty (clear).
     *
     * @return array<int, int>
     */
    public function optionIds(): array
    {
        return array_map('intval', (array) $this->input('option_ids', []));
    }
}
