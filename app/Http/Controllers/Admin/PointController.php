<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkPointRequest;
use App\Http\Requests\Admin\PointRequest;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Points\InsufficientPointsException;
use App\Services\Points\PointService;
use App\Support\StudentSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as BaseStreamedResponse;

class PointController extends Controller
{
    public function __construct(
        private readonly PointService $points,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        [$sort, $dir] = StudentSort::resolve($request);

        $query = User::query()->students()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('roll', 'like', "%{$search}%")));

        StudentSort::apply($query, $sort, $dir);

        $students = $query->paginate(20)->withQueryString();

        return view('admin.points.index', compact('students', 'search', 'sort', 'dir'));
    }

    /** Filtered CSV export of the balance overview — not the full ledger. */
    public function export(Request $request): BaseStreamedResponse
    {
        $search = trim((string) $request->query('q', ''));

        $query = User::query()->students()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('roll', 'like', "%{$search}%")))
            ->orderBy('name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Roll', 'Status', 'Balance']);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $student) {
                    fputcsv($out, [$student->name, $student->roll, $student->status, $student->points_balance]);
                }
            });

            fclose($out);
        }, 'points-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(User $student): View
    {
        abort_unless($student->isStudent(), 404);

        return view('admin.points.show', [
            'student' => $student,
            'transactions' => $student->pointTransactions()->with('performedBy')->latest('id')->paginate(20),
        ]);
    }

    public function store(PointRequest $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $data = $request->validated();

        try {
            $tx = $data['direction'] === 'credit'
                ? $this->points->credit($student, $data['amount'], PointTransaction::TYPE_GRANT, $data['reason'], $request->user())
                : $this->points->debit($student, $data['amount'], PointTransaction::TYPE_DEDUCTION, $data['reason'], $request->user());
        } catch (InsufficientPointsException $e) {
            return back()->withErrors(['amount' => "শিক্ষার্থীর পর্যাপ্ত পয়েন্ট নেই (বর্তমান: {$e->available})।"])->withInput();
        }

        $this->audit->log('points.'.$data['direction'], $student, after: [
            'amount' => $tx->amount, 'balance_after' => $tx->balance_after, 'reason' => $data['reason'],
        ]);

        return back()->with('success', 'পয়েন্ট সফলভাবে হালনাগাদ করা হয়েছে।');
    }

    public function bulkForm(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $students = User::query()->students()->where('status', User::STATUS_ACTIVE)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('roll', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get();

        return view('admin.points.bulk', compact('students', 'search'));
    }

    /**
     * Bulk grant/deduct. Behaviour is ALL-OR-NOTHING and validated up front: for a
     * deduction, if any selected student lacks the balance the whole batch is
     * rejected before any change is made, and the blocking students are listed. This
     * is deterministic — the admin never has to reason about a partial result.
     */
    public function bulkStore(BulkPointRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $students = User::query()->students()->whereIn('id', $data['student_ids'])->get();

        if ($data['direction'] === 'deduct') {
            $blocked = $students->where('points_balance', '<', $data['amount']);

            if ($blocked->isNotEmpty()) {
                return back()->withErrors([
                    'amount' => 'নিচের শিক্ষার্থীদের পর্যাপ্ত পয়েন্ট নেই: '.$blocked->pluck('name')->implode(', '),
                ])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($students, $data, $request) {
                foreach ($students as $student) {
                    $data['direction'] === 'credit'
                        ? $this->points->credit($student, $data['amount'], PointTransaction::TYPE_GRANT, $data['reason'], $request->user())
                        : $this->points->debit($student, $data['amount'], PointTransaction::TYPE_DEDUCTION, $data['reason'], $request->user());
                }

                $this->audit->log('points.bulk_'.$data['direction'], after: [
                    'students' => $students->count(),
                    'amount' => $data['amount'],
                    'reason' => $data['reason'],
                ], actor: $request->user());
            });
        } catch (InsufficientPointsException) {
            // A concurrent debit drained a balance after the pre-check. The whole
            // transaction rolled back, so no partial movement survives — surface a
            // clean Bengali message instead of a 500.
            return back()->withErrors([
                'amount' => 'অন্য একটি লেনদেনের কারণে কোনো শিক্ষার্থীর ব্যালেন্স অপর্যাপ্ত হয়ে গেছে। কোনো পরিবর্তন প্রয়োগ করা হয়নি — অনুগ্রহ করে আবার চেষ্টা করুন।',
            ])->withInput();
        }

        return redirect()->route('admin.points.index')
            ->with('success', $students->count().' জন শিক্ষার্থীর পয়েন্ট হালনাগাদ করা হয়েছে।');
    }
}
