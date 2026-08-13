<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuizAttempt;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // Real figures only. With no exam engine yet these are genuinely zero for a
        // new account — they are not fabricated, and grow as official attempts land.
        $official = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->where('status', QuizAttempt::STATUS_SUBMITTED)
            ->where('counts_toward_cumulative', true);

        $completed = (clone $official)->count();
        $obtained = (int) (clone $official)->sum('final_score');
        $possible = (int) (clone $official)->sum('total_marks_snapshot');

        return view('student.dashboard', [
            'user' => $user,
            'completed' => $completed,
            'obtained' => $obtained,
            'possible' => $possible,
            'percentage' => $possible > 0 ? round($obtained / $possible * 100, 1) : null,
            'recentPoints' => $user->pointTransactions()->latest('id')->limit(5)->get(),
            'courses' => Course::query()
                ->where('is_published', true)
                ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
