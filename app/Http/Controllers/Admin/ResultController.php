<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScoreAdjustmentRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Quiz\AdjustmentOutOfBoundsException;
use App\Services\Quiz\ScoreAdjustmentService;
use App\Support\AttemptReviewPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as BaseStreamedResponse;

/**
 * Admin results management. An admin is authorized to inspect any attempt's answers
 * regardless of the student-facing release gate (brief §27). Adjusting a score requires
 * the results.adjust permission (enforced on the route and in the form request).
 */
class ResultController extends Controller
{
    public function __construct(private readonly ScoreAdjustmentService $adjustments) {}

    public function index(Request $request): View
    {
        $filters = [
            'quiz_id' => $request->integer('quiz_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'kind' => $request->string('kind')->toString() ?: null,
            'q' => trim($request->string('q')->toString()),
        ];

        $attempts = $this->filteredQuery($filters)
            ->with(['quiz:id,title', 'user:id,roll,name'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.results.index', [
            'attempts' => $attempts,
            'filters' => $filters,
            'quizzes' => Quiz::query()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function show(QuizAttempt $attempt): View
    {
        $attempt->load(['quiz.course', 'user', 'scoreAdjustments.admin', 'regradeEntries']);

        return view('admin.results.show', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'rows' => $attempt->answer_details_available ? AttemptReviewPresenter::questions($attempt) : [],
            'legacy' => $attempt->is_legacy_import || ! $attempt->answer_details_available,
        ]);
    }

    public function adjust(ScoreAdjustmentRequest $request, QuizAttempt $attempt): RedirectResponse
    {
        try {
            $this->adjustments->adjust(
                $attempt,
                (int) $request->integer('manual_adjustment'),
                $request->string('reason')->toString(),
                $request->user(),
            );
        } catch (AdjustmentOutOfBoundsException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.results.show', $attempt)->with('success', __('results_admin.adjust_saved'));
    }

    /** Filtered CSV export — attempt-level only, never answer details (brief §50). */
    public function export(Request $request): BaseStreamedResponse
    {
        $filters = [
            'quiz_id' => $request->integer('quiz_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'kind' => $request->string('kind')->toString() ?: null,
            'q' => trim($request->string('q')->toString()),
        ];

        $query = $this->filteredQuery($filters)->with(['quiz:id,title', 'user:id,roll,name'])->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads Bengali (utf8mb4) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Quiz', 'Roll', 'Student', 'Kind', 'Status', 'Calculated', 'Manual', 'Final', 'Total', 'Time (s)', 'Submitted']);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $a) {
                    fputcsv($out, [
                        $a->quiz->title, $a->user->roll, $a->user->name, $a->kind, $a->status,
                        $a->calculated_score, $a->manual_adjustment, $a->final_score,
                        $a->total_marks_snapshot, $a->time_taken_seconds,
                        $a->submitted_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, 'results-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<QuizAttempt>
     */
    private function filteredQuery(array $filters)
    {
        return QuizAttempt::query()
            ->when($filters['quiz_id'], fn ($q, $id) => $q->where('quiz_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['kind'], fn ($q, $k) => $q->where('kind', $k))
            ->when($filters['q'] !== '', fn ($q) => $q->whereHas('user', function ($u) use ($filters) {
                $u->where('name', 'like', '%'.$filters['q'].'%')
                    ->orWhere('roll', 'like', '%'.$filters['q'].'%');
            }));
    }
}
