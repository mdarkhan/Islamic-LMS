<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuizQuestionRequest;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Question builder. Every scoring-sensitive mutation is blocked once the quiz has
 * official attempts (Part 9) — the answer key, marks and question set are then
 * frozen and corrections must go through the (later) Regrade workflow. Question
 * reordering stays allowed because it does not affect any score.
 */
class QuizQuestionController extends Controller
{
    private const LOCK_MESSAGE = 'এই কুইজে অফিসিয়াল পরীক্ষা জমা পড়েছে। সঠিক উত্তর/নম্বর পরিবর্তনের জন্য Regrade ব্যবস্থা ব্যবহার করতে হবে।';

    public function __construct(private readonly AuditLogger $audit) {}

    public function create(Quiz $quiz): View|RedirectResponse
    {
        return $this->guard($quiz) ?? view('admin.quizzes.questions.create', ['quiz' => $quiz]);
    }

    public function store(QuizQuestionRequest $request, Quiz $quiz): RedirectResponse
    {
        if ($guard = $this->guard($quiz)) {
            return $guard;
        }

        $question = DB::transaction(function () use ($request, $quiz) {
            $question = QuizQuestion::query()->create([
                'quiz_id' => $quiz->getKey(),
                'sort_order' => (int) $quiz->questions()->max('sort_order') + 1,
                'type' => $request->input('type'),
                'body' => $request->input('body'),
                'explanation' => $request->input('explanation'),
                'marks' => $request->input('marks'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncOptions($question, $request->optionsData());
            $quiz->recalculateTotalMarks();

            return $question;
        });

        $this->audit->log('question.created', $question, after: ['quiz_id' => $quiz->id, 'marks' => $question->marks]);

        return redirect()->route('admin.quizzes.edit', $quiz)->with('success', 'প্রশ্ন যোগ করা হয়েছে।');
    }

    public function edit(Quiz $quiz, QuizQuestion $question): View|RedirectResponse
    {
        $this->ensureOwner($quiz, $question);

        return $this->guard($quiz) ?? view('admin.quizzes.questions.edit', [
            'quiz' => $quiz,
            'question' => $question->load('options'),
        ]);
    }

    public function update(QuizQuestionRequest $request, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->ensureOwner($quiz, $question);
        if ($guard = $this->guard($quiz)) {
            return $guard;
        }

        DB::transaction(function () use ($request, $quiz, $question) {
            $question->update([
                'type' => $request->input('type'),
                'body' => $request->input('body'),
                'explanation' => $request->input('explanation'),
                'marks' => $request->input('marks'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncOptions($question, $request->optionsData());
            $quiz->recalculateTotalMarks();
        });

        $this->audit->log('question.updated', $question, after: ['quiz_id' => $quiz->id, 'marks' => $question->marks]);

        return redirect()->route('admin.quizzes.edit', $quiz)->with('success', 'প্রশ্ন হালনাগাদ করা হয়েছে।');
    }

    public function destroy(Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->ensureOwner($quiz, $question);
        if ($guard = $this->guard($quiz)) {
            return $guard;
        }

        $this->audit->log('question.removed', $question, before: ['quiz_id' => $quiz->id, 'body' => $question->body]);
        $question->delete();   // options cascade
        $quiz->recalculateTotalMarks();

        return redirect()->route('admin.quizzes.edit', $quiz)->with('success', 'প্রশ্ন মুছে ফেলা হয়েছে।');
    }

    public function duplicate(Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->ensureOwner($quiz, $question);
        if ($guard = $this->guard($quiz)) {
            return $guard;
        }

        DB::transaction(function () use ($quiz, $question) {
            $copy = QuizQuestion::query()->create([
                'quiz_id' => $quiz->getKey(),
                'sort_order' => (int) $quiz->questions()->max('sort_order') + 1,
                'type' => $question->type,
                'body' => $question->body.' (কপি)',
                'explanation' => $question->explanation,
                'marks' => $question->marks,
                'is_active' => $question->is_active,
            ]);

            foreach ($question->options as $option) {
                QuizOption::query()->create([
                    'question_id' => $copy->getKey(),
                    'sort_order' => $option->sort_order,
                    'body' => $option->body,
                    'is_correct' => $option->is_correct,
                ]);
            }

            $quiz->recalculateTotalMarks();
        });

        return redirect()->route('admin.quizzes.edit', $quiz)->with('success', 'প্রশ্নের কপি তৈরি হয়েছে।');
    }

    /**
     * Reorder questions. Allowed even with official attempts — order does not affect
     * any score.
     */
    public function reorder(Request $request, Quiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('quiz_questions', 'id')->where('quiz_id', $quiz->id)],
        ]);

        foreach ($data['order'] as $position => $id) {
            QuizQuestion::query()->whereKey($id)->where('quiz_id', $quiz->id)->update(['sort_order' => $position]);
        }

        return back()->with('success', 'প্রশ্নের ক্রম পরিবর্তন করা হয়েছে।');
    }

    /**
     * @param  array<int, array{body:string, correct:bool}>  $options
     */
    private function syncOptions(QuizQuestion $question, array $options): void
    {
        $question->options()->delete();

        foreach ($options as $i => $option) {
            QuizOption::query()->create([
                'question_id' => $question->getKey(),
                'sort_order' => $i,
                'body' => $option['body'],
                'is_correct' => $option['correct'],
            ]);
        }
    }

    private function guard(Quiz $quiz): ?RedirectResponse
    {
        return $quiz->scoringLocked()
            ? redirect()->route('admin.quizzes.edit', $quiz)->with('error', self::LOCK_MESSAGE)
            : null;
    }

    private function ensureOwner(Quiz $quiz, QuizQuestion $question): void
    {
        abort_unless($question->quiz_id === $quiz->id, 404);
    }
}
