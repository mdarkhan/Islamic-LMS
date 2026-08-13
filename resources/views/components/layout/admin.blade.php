@props(['title' => null, 'heading' => null])

@php
    $user = auth()->user();
    $nav = [
        ['label' => 'ড্যাশবোর্ড', 'href' => route('admin.dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('admin.dashboard')],
        ['label' => 'শিক্ষার্থী', 'href' => route('admin.students.index'), 'icon' => 'students', 'active' => request()->routeIs('admin.students.*')],
        ['label' => 'পয়েন্ট', 'href' => route('admin.points.index'), 'icon' => 'points', 'active' => request()->routeIs('admin.points.*')],
        ['label' => 'কোর্স', 'href' => route('admin.courses.index'), 'icon' => 'courses', 'active' => request()->routeIs('admin.courses.*')],
        ['label' => 'ক্লাস', 'href' => route('admin.lessons.index'), 'icon' => 'lessons', 'active' => request()->routeIs('admin.lessons.*')],
    ];

    // Quiz section only for users who may view quizzes.
    if ($user?->hasPermission('quizzes.view')) {
        $nav[] = ['label' => 'কুইজ', 'href' => route('admin.quizzes.index'), 'icon' => 'exam', 'active' => request()->routeIs('admin.quizzes.*')];
    }

    $nav[] = ['label' => 'অডিট লগ', 'href' => route('admin.audit.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.audit.*')];
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" context="অ্যাডমিন">
    {{ $slot }}
</x-layout.app>
