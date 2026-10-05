<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Amol;
use App\Services\Amol\AmolService;
use App\Services\Amol\AmolStatsService;
use App\Services\Calendar\PrayerTimeService;
use App\Services\Notifications\NotificationService;
use App\Support\AmolDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A student's own daily amol checklist. Always resolved from the authenticated user —
 * there is no student id in these routes, so there is nothing to tamper with.
 */
class AmolController extends Controller
{
    public function __construct(
        private readonly AmolService $amol,
        private readonly NotificationService $notifications,
        private readonly PrayerTimeService $prayers,
        private readonly AmolStatsService $stats,
    ) {}

    public function index(Request $request): View
    {
        $student = $request->user();
        $date = AmolDate::resolve($request->query('date'));
        $today = $this->amol->today();
        $note = $this->amol->noteFor($student, $date);

        // Opening this date is what reads its comment — in the sidebar badge and the
        // bell-icon feed alike.
        if ($note !== null) {
            $this->amol->markNoteSeen($note);
            $this->notifications->markReadForSubject($student, 'amol_day_note', $note->getKey());
        }

        return view('student.amol.index', [
            'date' => $date,
            'isToday' => $date->equalTo($today),
            'today' => $today,
            'checklist' => $this->amol->checklistFor($student, $date),
            'note' => $note,
            'prayer' => $this->prayers->status(),
            'stats' => $this->stats->forStudent($student, $this->month($request->query('month')), $today),
        ]);
    }

    /** `?month=YYYY-MM`, clamped to a real month no later than the current one. */
    private function month(mixed $raw): CarbonImmutable
    {
        $current = CarbonImmutable::now()->startOfMonth();

        if (! is_string($raw) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $raw)) {
            return $current;
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $raw);

        return $month->greaterThan($current) ? $current : $month;
    }

    /** Toggle one deed. Always today — the service never accepts a date to write to. */
    public function toggle(Request $request, Amol $amol): JsonResponse
    {
        abort_unless($amol->is_active, 404);

        $entry = $this->amol->toggle($request->user(), $amol);

        return response()->json(['is_done' => $entry->is_done]);
    }
}
