<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Messaging\MessageService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly MessageService $messages) {}

    public function index(): View
    {
        $user = auth()->user();
        $now = CarbonImmutable::now();

        $pendingReleaseCount = $user->hasPermission('results.release')
            ? Quiz::query()->where('status', '!=', Quiz::STATUS_DRAFT)->get()
                ->filter(fn (Quiz $quiz) => $quiz->isPendingRelease($now))
                ->count()
            : 0;

        return view('admin.dashboard', [
            'activeStudents' => User::query()->students()->where('status', User::STATUS_ACTIVE)->count(),
            'suspendedStudents' => User::query()->students()->where('status', User::STATUS_SUSPENDED)->count(),
            'courseCount' => Course::query()->count(),
            'lessonCount' => Lesson::query()->count(),
            // Points currently held by students — a real figure straight from the cache.
            'pointsHeld' => (int) User::query()->students()->sum('points_balance'),
            'recentPoints' => PointTransaction::query()
                ->with(['user', 'performedBy'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'unreadMessages' => $user->hasPermission('messages.view') ? $this->messages->unreadCountFor($user) : 0,
            'pendingReleaseCount' => $pendingReleaseCount,
        ]);
    }
}
