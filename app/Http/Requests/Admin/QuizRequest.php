<?php

namespace App\Http\Requests\Admin;

use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perm = $this->routeIs('*.update', '*.store') ? 'quizzes.update' : 'quizzes.create';

        // create uses quizzes.create; edit/update uses quizzes.update.
        $perm = $this->isMethod('post') ? 'quizzes.create' : 'quizzes.update';

        return $this->user()?->hasPermission($perm) ?? false;
    }

    public function rules(): array
    {
        $quiz = $this->route('quiz');

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('quizzes', 'slug')->ignore($quiz?->id)],
            'course_id' => ['nullable', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in([Quiz::STATUS_DRAFT, Quiz::STATUS_SCHEDULED, Quiz::STATUS_PUBLISHED, Quiz::STATUS_ARCHIVED])],

            'point_cost' => ['required', 'integer', 'min:0', 'max:100000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'result_release_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'practice_enabled' => ['sometimes', 'boolean'],
            'practice_timer_enabled' => ['sometimes', 'boolean'],
            'leaderboard_visible' => ['sometimes', 'boolean'],
            'max_official_attempts' => ['required', 'integer', 'min:1', 'max:100'],

            'bonus_enabled' => ['sometimes', 'boolean'],
            'bonus_threshold_type' => ['nullable', Rule::in([Quiz::BONUS_THRESHOLD_FULL, Quiz::BONUS_THRESHOLD_MARKS])],
            'bonus_threshold_marks' => ['nullable', 'integer', 'min:1', 'required_if:bonus_threshold_type,marks'],
            'bonus_points' => ['nullable', 'integer', 'min:1', 'max:100000', 'required_if:bonus_enabled,1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A chosen lesson must belong to the chosen course.
            $lessonId = $this->input('lesson_id');
            $courseId = $this->input('course_id');

            if ($lessonId && $courseId) {
                $belongs = Lesson::query()->whereKey($lessonId)->where('course_id', $courseId)->exists();
                if (! $belongs) {
                    $validator->errors()->add('lesson_id', 'নির্বাচিত ক্লাসটি নির্বাচিত কোর্সের অন্তর্ভুক্ত নয়।');
                }
            }

            if ($lessonId && ! $courseId) {
                $validator->errors()->add('lesson_id', 'ক্লাস নির্বাচন করতে হলে আগে কোর্স নির্বাচন করুন।');
            }

            // Result release before the exam ends is nonsensical.
            if ($this->filled('result_release_at') && $this->filled('ends_at')
                && strtotime($this->input('result_release_at')) < strtotime($this->input('ends_at'))) {
                $validator->errors()->add('result_release_at', 'ফলাফল প্রকাশের সময় অবশ্যই পরীক্ষা শেষ হওয়ার সময় বা তার পরে হতে হবে।');
            }
        });
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => 'শেষ সময় অবশ্যই শুরুর সময়ের পরে হতে হবে।',
            'title.required' => 'শিরোনাম আবশ্যক।',
        ];
    }

    /** Canonical duration in seconds, or null. */
    public function durationSeconds(): ?int
    {
        $minutes = $this->input('duration_minutes');

        return $minutes ? (int) $minutes * 60 : null;
    }
}
