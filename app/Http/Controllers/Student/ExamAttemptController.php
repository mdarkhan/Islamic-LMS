<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveAnswerRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Services\Points\InsufficientPointsException;
use App\Services\Quiz\AttemptNotAllowedException;
use App\Services\Quiz\InvalidAnswerSelectionException;
use App\Services\Quiz\QuizAttemptService;
use App\Support\ExamAttemptPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The secure live OFFICIAL exam flow (Phase 7): start/resume, per-question autosave,
 * the authoritative server timer, submission and the released-result summary.
 *
 * This controller only ORCHESTRATES. Every rule that matters — the atomic
 * point debit, the one-way terminal states, expiry, fail-closed answer validation
 * and scoring — lives in QuizAttemptService / QuizScoringService and is never
 * re-implemented here or in the browser. The server clock is authoritative; nothing
 * trusts a client-supplied score, id, expiry or elapsed time.
 */
class ExamAttemptController extends Controller
{
    public function __construct(private readonly QuizAttemptService $attempts) {}

    /**
     * Start a new official attempt, or resume the one already in progress. The
     * service makes this idempotent: resuming never debits a second point.
     */
    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        try {
            $attempt = $this->attempts->startOfficial($quiz, $request->user());
        } catch (InsufficientPointsException) {
            return back()->with('error', __('exams.err_insufficient_points'));
        } catch (AttemptNotAllowedException $e) {
            // The service raises a clear Bengali reason (not open, already attempted,
            // account inactive); surface it as-is.
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('student.attempts.show', $attempt);
    }

    /**
     * The live exam screen. Finalises opportunistically if the deadline has passed,
     * and sends a terminal attempt straight to its result instead of re-rendering it.
     */
    public function show(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        $this->guardOwnership($request, $attempt);

        // Practice-mode taking UI is a later phase; only official attempts run here.
        if (! $attempt->isOfficial()) {
            abort(404);
        }

        $now = CarbonImmutable::now();
        $attempt = $this->finaliseIfExpired($attempt, $now);

        if ($attempt->isTerminal()) {
            return redirect()->route('student.attempts.result', $attempt);
        }

        return view('student.exams.live', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'questions' => ExamAttemptPresenter::questions($attempt),
            'selections' => ExamAttemptPresenter::selections($attempt),
            'remaining' => ExamAttemptPresenter::remainingSeconds($attempt, $now),
        ]);
    }

    /**
     * Autosave one question. Returns JSON so the browser can show per-question save
     * state and re-synchronise the timer. A save past the deadline is refused (the
     * attempt is finalised as expired) — the browser timer is never trusted.
     */
    public function saveAnswer(SaveAnswerRequest $request, QuizAttempt $attempt, QuizQuestion $question): JsonResponse
    {
        $this->guardOwnership($request, $attempt);

        // Defence in depth: the service also rejects foreign options, but refusing a
        // question from another quiz here keeps a crafted URL from reaching it at all.
        if ((int) $question->quiz_id !== (int) $attempt->quiz_id) {
            abort(404);
        }

        $now = CarbonImmutable::now();

        try {
            $answer = $this->attempts->saveAnswer($attempt, (int) $question->id, $request->optionIds(), $now);
        } catch (AttemptNotAllowedException $e) {
            // Deadline passed (or the attempt is otherwise closed): tell the client to
            // move to the result. finaliseIfExpired already ran inside saveAnswer.
            return response()->json([
                'ok' => false,
                'expired' => true,
                'message' => $e->getMessage(),
                'redirect' => route('student.attempts.result', $attempt),
            ], 409);
        } catch (InvalidAnswerSelectionException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'answered' => $answer !== null,
            'remaining_seconds' => ExamAttemptPresenter::remainingSeconds($attempt->fresh(), $now),
        ]);
    }

    /**
     * Lightweight timer/terminal poll. Recalibrates the browser countdown against the
     * authoritative deadline and finalises an expired attempt so the client is told to
     * leave. Never returns any answer-key data.
     */
    public function status(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->guardOwnership($request, $attempt);

        $now = CarbonImmutable::now();
        $attempt = $this->finaliseIfExpired($attempt, $now);

        return response()->json([
            'terminal' => $attempt->isTerminal(),
            'remaining_seconds' => ExamAttemptPresenter::remainingSeconds($attempt, $now),
            'redirect' => $attempt->isTerminal()
                ? route('student.attempts.result', $attempt)
                : null,
        ]);
    }

    /**
     * Submit the attempt. Idempotent and terminal-safe in the service: a second
     * submit (double click, retried request) returns the same terminal attempt
     * unchanged — it never rescores, recharges or rewrites the timestamp.
     */
    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $this->guardOwnership($request, $attempt);

        $this->attempts->submit($attempt);

        return redirect()->route('student.attempts.result', $attempt);
    }

    /**
     * The result summary. Score is shown ONLY once the quiz's results are released;
     * before that the student sees a "pending" state with no number, so an early
     * fetch cannot leak the score. A still-live attempt is sent back to the exam.
     */
    public function result(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        $this->guardOwnership($request, $attempt);

        $now = CarbonImmutable::now();

        if ($attempt->isInProgress()) {
            $attempt = $this->finaliseIfExpired($attempt, $now);

            // Genuinely still running: there is no result yet — return to the exam.
            if ($attempt->isInProgress()) {
                return redirect()->route('student.attempts.show', $attempt);
            }
        }

        return view('student.exams.result', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'released' => $attempt->quiz->resultsReleasedAt($now),
        ]);
    }

    /**
     * Finalise the attempt as expired if its deadline has passed, otherwise return it
     * untouched. Reuses submit(), which routes a past-deadline finalisation to EXPIRED.
     */
    private function finaliseIfExpired(QuizAttempt $attempt, CarbonImmutable $now): QuizAttempt
    {
        if ($attempt->isInProgress() && $attempt->hasExpiredAt($now)) {
            return $this->attempts->submit($attempt, $now);
        }

        return $attempt;
    }

    /**
     * Reject any attempt that is not the signed-in student's own (IDOR). The user id
     * comes from the session, never from the request.
     */
    private function guardOwnership(Request $request, QuizAttempt $attempt): void
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id, 403);
    }
}
