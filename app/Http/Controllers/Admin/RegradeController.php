<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegradeRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\Quiz\QuizRegradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The controlled answer-key / marks correction workflow (brief §32–34, §42).
 *
 * This is the ONLY sanctioned way to change scoring-sensitive fields once a quiz has
 * official attempts — the ordinary Question Editor stays locked (QuizQuestionController).
 * Preview never persists; apply re-parses the change from the request and regrades
 * transactionally.
 */
class RegradeController extends Controller
{
    public function __construct(private readonly QuizRegradeService $regrade) {}

    public function create(Quiz $quiz): View|RedirectResponse
    {
        // Regrade is meaningful only once scores are locked by official attempts; before
        // that, the normal builder edits the key directly.
        if (! $quiz->scoringLocked()) {
            return redirect()->route('admin.quizzes.edit', $quiz)
                ->with('error', __('results_admin.regrade_not_locked'));
        }

        return view('admin.regrade.create', [
            'quiz' => $quiz->load(['questions.options']),
        ]);
    }

    /** Dry-run impact for the UI. Persists nothing (brief §33). */
    public function preview(Request $request, Quiz $quiz): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', Rule::exists('quiz_questions', 'id')->where('quiz_id', $quiz->id)],
            'marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'correct_option_ids' => ['required', 'array', 'min:1'],
            'correct_option_ids.*' => ['integer', Rule::exists('quiz_options', 'id')->where('question_id', $request->input('question_id'))],
        ]);

        $question = QuizQuestion::query()->whereKey($data['question_id'])->with('options')->firstOrFail();
        $correct = array_map('intval', $data['correct_option_ids']);

        return response()->json($this->regrade->preview($question, $correct, (int) $data['marks']));
    }

    public function apply(RegradeRequest $request, Quiz $quiz): RedirectResponse
    {
        if (! $quiz->scoringLocked()) {
            return redirect()->route('admin.quizzes.edit', $quiz)->with('error', __('results_admin.regrade_not_locked'));
        }

        $question = QuizQuestion::query()
            ->where('quiz_id', $quiz->id)
            ->whereKey($request->integer('question_id'))
            ->firstOrFail();

        $run = $this->regrade->apply(
            question: $question,
            newCorrectIds: $request->correctOptionIds(),
            newMarks: (int) $request->integer('marks'),
            newType: $request->string('type')->toString(),
            explanation: $request->input('explanation'),
            admin: $request->user(),
            reason: $request->string('reason')->toString(),
        );

        return redirect()->route('admin.quizzes.edit', $quiz)
            ->with('success', __('results_admin.regrade_done', ['count' => $run->attempts_affected]));
    }
}
