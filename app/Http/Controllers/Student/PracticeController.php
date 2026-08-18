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
 * screen, the answer-key allow-list) but with practice rules: free, unranked, reviewable
 * immediately after submit, and NOT retained as history — a student sees only the score
 * and answer sheet of their current practice; nothing is browsable afterward.
 *
 * SAFETY (brief §10): practice becomes available only when the official answer key is
 * already safe to reveal — Quiz::practiceAvailableAt() requires the official window to
 * be closed AND results released. That rule is enforced on every start here, so a
 * student can never open practice mid-exam and read the key.
 *
 * Timer: untimed unless the admin enabled `practice_timer_enabled` on the quiz, in which
 * case the attempt counts down from the quiz's own duration; the official ends_at is
 * never reused.
 */
class PracticeController extends Controller
{
    public function __construct(private readonly QuizAttemptService $attempts) {}

    /** The practice library: quizzes whose key is safe to reveal, grouped by course. */
    public function index(Request $request): View
    {
        $now = CarbonImmutable::now();

        $quizzes = Quiz::query()
            ->where('practice_enabled', true)
            ->where('status', '!=', Quiz::STATUS_DRAFT)
            ->with('course')
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('title')
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->practiceAvailableAt($now));

        return view('student.practice.index', [
            'byCourse' => $quizzes->groupBy(fn (Quiz $quiz) => $quiz->course?->title ?? '—'),
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

    /** The live practice screen (shared with the official exam); badged, optionally timed. */
    public function show(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        $this->guard($request, $attempt);

        $now = CarbonImmutable::now();
        $attempt = $this->finaliseIfExpired($attempt, $now);

        if ($attempt->isTerminal()) {
            return redirect()->route('student.practice.result', $attempt);
        }

        // Timed practice re-uses the live timer + status poll; untimed leaves both inert.
        $timed = $attempt->expires_at !== null;

        return view('student.exams.live', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'questions' => ExamAttemptPresenter::questions($attempt),
            'selections' => ExamAttemptPresenter::selections($attempt),
            'remaining' => ExamAttemptPresenter::remainingSeconds($attempt, $now),
            'practiceLabel' => __('practice.badge'),
            'submitAction' => route('student.practice.submit', $attempt),
            'endpoints' => [
                'answer' => route('student.practice.answer', ['attempt' => $attempt->id, 'question' => '__Q__']),
                'status' => $timed ? route('student.practice.status', $attempt) : null,
                'result' => route('student.practice.result', $attempt),
            ],
        ]);
    }

    /** Timer/terminal poll for timed practice (mirrors the official status endpoint). */
    public function status(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->guard($request, $attempt);

        $now = CarbonImmutable::now();
        $attempt = $this->finaliseIfExpired($attempt, $now);

        return response()->json([
            'terminal' => $attempt->isTerminal(),
            'remaining_seconds' => ExamAttemptPresenter::remainingSeconds($attempt, $now),
            'redirect' => $attempt->isTerminal() ? route('student.practice.result', $attempt) : null,
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

        return response()->json([
            'ok' => true,
            'answered' => $answer !== null,
            'remaining_seconds' => ExamAttemptPresenter::remainingSeconds($attempt->fresh(), CarbonImmutable::now()),
        ]);
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
            $attempt = $this->finaliseIfExpired($attempt, CarbonImmutable::now());
            if ($attempt->isInProgress()) {
                return redirect()->route('student.practice.show', $attempt);
            }
        }

        return view('student.practice.result', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'rows' => AttemptReviewPresenter::questions($attempt),
        ]);
    }

    /** Finalise a timed practice attempt whose deadline has passed (scores it as terminal). */
    private function finaliseIfExpired(QuizAttempt $attempt, CarbonImmutable $now): QuizAttempt
    {
        if ($attempt->isInProgress() && $attempt->hasExpiredAt($now)) {
            return $this->attempts->submit($attempt, $now);
        }

        return $attempt;
    }

    /** Ownership + practice-kind guard shared by every attempt-scoped action. */
    private function guard(Request $request, QuizAttempt $attempt): void
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id, 403);
        abort_unless($attempt->kind === QuizAttempt::KIND_PRACTICE, 404);
    }
}
