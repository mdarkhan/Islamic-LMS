<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Notice;
use App\Models\Post;
use App\Services\Calendar\CalendarService;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function __construct(private readonly CalendarService $calendar) {}

    public function home(): View
    {
        return view('public.home', [
            'calendar' => $this->calendar->all(),
            'courses' => Course::query()
                ->where('is_published', true)
                ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
                ->orderBy('sort_order')
                ->get(),
            'lessonCount' => Lesson::query()->where('is_published', true)->count(),
            'notices' => Notice::query()
                ->activeAt()
                ->forAudience(Notice::AUDIENCE_PUBLIC)
                ->orderByDesc('priority')
                ->limit(3)
                ->get(),
            'recentPosts' => Post::query()
                ->public()
                ->with('category:id,name,slug')
                ->latest('published_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
