<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Amol;
use App\Services\Amol\AmolService;
use App\Services\Notifications\NotificationService;
use App\Support\AmolDate;
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
        ]);
    }

    /** Toggle one deed. Always today — the service never accepts a date to write to. */
    public function toggle(Request $request, Amol $amol): JsonResponse
    {
        abort_unless($amol->is_active, 404);

        $entry = $this->amol->toggle($request->user(), $amol);

        return response()->json(['is_done' => $entry->is_done]);
    }
}
