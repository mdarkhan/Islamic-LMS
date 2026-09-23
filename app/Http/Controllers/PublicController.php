<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Course;
use App\Models\Faq;
use App\Models\Lesson;
use App\Models\Notice;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Calendar\CalendarService;
use App\Services\Settings\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly SettingService $settings,
    ) {}

    public function home(): View
    {
        $courses = Course::query()
            ->where('is_published', true)
            ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('sort_order')
            ->get();

        return view('public.home', [
            'calendar' => $this->calendar->all(),
            'upcomingEvents' => $this->calendar->upcomingIslamicOccasions(),
            'books' => Book::query()->published()->with('purchaseLinks')->orderBy('sort_order')->limit(10)->get(),
            'courses' => $courses,
            // The hero strip highlights numbers that are reliably non-zero for a young
            // site (a fresh install may have zero articles for a while, which reads as
            // a weakness); the full article count still has its own section below.
            'stats' => [
                'students' => User::students()->count(),
                'courses' => $courses->count(),
                'lessons' => Lesson::query()->where('is_published', true)->count(),
                'books' => Book::query()->published()->count(),
            ],
            'about' => $this->settings->group('about'),
            'telegramUrl' => $this->settings->get('telegram_url'),
            'upcomingQuiz' => $this->nextOfficialQuiz(),
            'notices' => Notice::query()
                ->activeAt()
                ->forAudience(Notice::AUDIENCE_PUBLIC)
                ->orderByDesc('priority')
                ->limit(3)
                ->get(),
            'featuredPost' => Post::query()->public()->where('is_featured', true)->latest('published_at')->first()
                ?? Post::query()->public()->whereNotNull('question')->latest('published_at')->first(),
            'recentPosts' => Post::query()
                ->public()
                ->with('category:id,name,slug')
                ->latest('published_at')
                ->limit(3)
                ->get(),
            'faqs' => Faq::query()->published()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * The single most relevant official quiz to announce publicly: an already-open one
     * first, else the soonest upcoming one. Only title/course/schedule are ever touched —
     * never marks, questions, or the answer key (CLAUDE.md #2, #7).
     */
    private function nextOfficialQuiz(): ?Quiz
    {
        $now = Carbon::now();

        return Quiz::query()
            ->whereIn('status', [Quiz::STATUS_SCHEDULED, Quiz::STATUS_PUBLISHED])
            ->with('course:id,title')
            ->get(['id', 'course_id', 'slug', 'title', 'status', 'starts_at', 'ends_at'])
            ->filter(fn (Quiz $quiz) => in_array($quiz->officialState($now), [Quiz::STATE_OPEN, Quiz::STATE_UPCOMING], true))
            ->sort(function (Quiz $a, Quiz $b) use ($now) {
                $aOpen = $a->officialState($now) === Quiz::STATE_OPEN;
                $bOpen = $b->officialState($now) === Quiz::STATE_OPEN;

                return ($bOpen <=> $aOpen) ?: ($a->starts_at?->getTimestamp() ?? 0) <=> ($b->starts_at?->getTimestamp() ?? 0);
            })
            ->first();
    }
}
