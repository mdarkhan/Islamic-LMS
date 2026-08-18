<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveAnswerRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Services\Quiz\AttemptNotAllowedException;
use App\Services\Quiz\InvalidAnswerSelectionException;
use App\Services\Quiz\QuizAttemptService;
use App\Support\AttemptReviewPresenter;
use App\Support\ExamAttemptPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Practice Mode. Reuses the secure attempt machinery (QuizAttemptService, the live
 * screen, the answer-key allow-list) but with practice rules: free, untimed, unranked,
 * repeatable, and reviewable immediately after submit.
 *
 * SAFETY (brief §10): practice becomes available only when the official answer key is
 * already safe to reveal — Quiz::practiceAvailableAt() requires the official window to
 * be closed AND results released. That rule is enforced on every start here, so a
 * student can never open practice mid-exam and read the key. Practice is untimed: it
 * never reuses the official ends_at (the attempt has expires_at = null).
 */
class PracticeController extends Controller
{
    public function __construct(private readonly QuizAttemptService $attempts) {}

    /** The practice library: quizzes whose key is safe to reveal, grouped by course. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $now = CarbonImmutable::now();

        $quizzes = Quiz::query()
            ->where('practice_enabled', true)
            ->where('status', '!=', Quiz::STATUS_DRAFT)
            ->with('course')
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('title')
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->practiceAvailableAt($now));

        // Per-quiz practice stats for this student (count + best terminal score).
        $stats = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_PRACTICE)
            ->selectRaw('quiz_id, count(*) as attempts, max(case when status = ? then final_score end) as best', [QuizAttempt::STATUS_SUBMITTED])
            ->groupBy('quiz_id')
            ->get()
            ->keyBy('quiz_id');

        $byCourse = $quizzes->groupBy(fn (Quiz $quiz) => $quiz->course?->title ?? '—');

        $history = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_PRACTICE)
            ->whereIn('status', [QuizAttempt::STATUS_SUBMITTED])
            ->with('quiz.course')
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->get();

        return view('student.practice.index', [
            'byCourse' => $byCourse,
            'stats' => $stats,
            'history' => $history,
        ]);
    }

    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $now = CarbonImmutable::now();

        // The safety gate — never start practice while the official key is still secret.
        if (! $quiz->practiceAvailableAt($now)) {
            return back()->with('error', __('practice.unavailable'));
        }

        try {
            $attempt = $this->attempts->startPractice($quiz, $request->user(), $now);
        } catch (AttemptNotAllowedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('student.practice.show', $attempt);
    }

    /** The live practice screen (shared with the official exam), untimed and badged. */
    public function show(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        $this->guard($request, $attempt);

        if ($attempt->isTerminal()) {
            return redirect()->route('student.practice.result', $attempt);
        }

        return view('student.exams.live', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'questions' => ExamAttemptPresenter::questions($attempt),
            'selections' => ExamAttemptPresenter::selections($attempt),
            'remaining' => null,   // practice is untimed
            'practiceLabel' => __('practice.badge'),
            'submitAction' => route('student.practice.submit', $attempt),
            'endpoints' => [
                'answer' => route('student.practice.answer', ['attempt' => $attempt->id, 'question' => '__Q__']),
                'status' => null,   // untimed → the browser never polls
                'result' => route('student.practice.result', $attempt),
            ],
        ]);
    }

    public function saveAnswer(SaveAnswerRequest $request, QuizAttempt $attempt, QuizQuestion $question): JsonResponse
    {
        $this->guard($request, $attempt);

        if ((int) $question->quiz_id !== (int) $attempt->quiz_id) {
            abort(404);
        }

        try {
            $answer = $this->attempts->saveAnswer($attempt, (int) $question->id, $request->optionIds());
        } catch (AttemptNotAllowedException $e) {
            return response()->json([
                'ok' => false, 'expired' => true, 'message' => $e->getMessage(),
                'redirect' => route('student.practice.result', $attempt),
            ], 409);
        } catch (InvalidAnswerSelectionException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'answered' => $answer !== null, 'remaining_seconds' => null]);
    }

    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $this->guard($request, $attempt);
        $this->attempts->submit($attempt);

        return redirect()->route('student.practice.result', $attempt);
    }

    /**
     * The practice result: full score and review shown immediately (no release delay —
     * practice was only available because the key is already safe).
     */
    public function result(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        $this->guard($request, $attempt);

        if ($attempt->isInProgress()) {
            return redirect()->route('student.practice.show', $attempt);
        }

        return view('student.practice.result', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'rows' => AttemptReviewPresenter::questions($attempt),
        ]);
    }

    /** Ownership + practice-kind guard shared by every attempt-scoped action. */
    private function guard(Request $request, QuizAttempt $attempt): void
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id, 403);
        abort_unless($attempt->kind === QuizAttempt::KIND_PRACTICE, 404);
    }
}
