@props(['title' => null, 'heading' => null])

@php
    $user = auth()->user();
    $nav = [
        ['label' => __('nav.dashboard'), 'href' => route('admin.dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('admin.dashboard')],
        ['label' => __('nav.students'), 'href' => route('admin.students.index'), 'icon' => 'students', 'active' => request()->routeIs('admin.students.*')],
        ['label' => __('nav.points'), 'href' => route('admin.points.index'), 'icon' => 'points', 'active' => request()->routeIs('admin.points.*')],
        ['label' => __('nav.courses'), 'href' => route('admin.courses.index'), 'icon' => 'courses', 'active' => request()->routeIs('admin.courses.*')],
        ['label' => __('nav.lessons'), 'href' => route('admin.lessons.index'), 'icon' => 'lessons', 'active' => request()->routeIs('admin.lessons.*')],
    ];

    // Quiz section only for users who may view quizzes.
    if ($user?->hasPermission('quizzes.view')) {
        $nav[] = ['label' => __('nav.quizzes'), 'href' => route('admin.quizzes.index'), 'icon' => 'exam', 'active' => request()->routeIs('admin.quizzes.*')];
    }

    $nav[] = ['label' => __('nav.audit'), 'href' => route('admin.audit.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.audit.*')];
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" :context="__('nav.context_admin')">
    {{ $slot }}
</x-layout.app>
