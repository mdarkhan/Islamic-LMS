<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Services\Quiz\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboards) {}

    public function index(): View
    {
        $user = auth()->user();
        $now = CarbonImmutable::now();

        // Overall figures come from the SAME service that powers the leaderboard, so the
        // dashboard can never disagree with it (brief §24). null until a released,
        // counted attempt exists — never fabricated.
        $overall = $this->leaderboards->studentOverall($user, $now);

        // Exams completed = distinct official exams the student has a terminal attempt in.
        $completed = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
            ->distinct()
            ->count('quiz_id');

        return view('student.dashboard', [
            'user' => $user,
            'completed' => $completed,
            'overall' => $overall,
            'recent' => QuizAttempt::query()
                ->where('user_id', $user->getKey())
                ->where('kind', QuizAttempt::KIND_OFFICIAL)
                ->whereIn('status', QuizAttempt::RANKABLE_STATUSES)
                ->with('quiz')
                ->orderByDesc('submitted_at')
                ->limit(5)
                ->get(),
            'recentPoints' => $user->pointTransactions()->latest('id')->limit(5)->get(),
            'courses' => Course::query()
                ->where('is_published', true)
                ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
