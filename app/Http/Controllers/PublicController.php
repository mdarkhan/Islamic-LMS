<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Notice;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'courses' => Course::query()
                ->where('is_published', true)
                ->withCount(['lessons' => fn ($q) => $q->where('is_published', true)])
                ->orderBy('sort_order')
                ->get(),
            'lessonCount' => Lesson::query()->where('is_published', true)->count(),
            'notice' => Notice::query()
                ->where('is_active', true)
                ->whereIn('audience', ['public', 'all'])
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderByDesc('priority')
                ->first(),
        ]);
    }
}
