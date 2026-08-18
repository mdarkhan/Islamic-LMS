@props(['title' => null, 'heading' => null])

@php
    $nav = [
        ['label' => __('nav.dashboard'), 'href' => route('student.dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('student.dashboard')],
        ['label' => __('nav.courses'), 'href' => route('student.courses.index'), 'icon' => 'book', 'active' => request()->routeIs('student.courses.*')],
        ['label' => __('nav.exams'), 'href' => route('student.exams.index'), 'icon' => 'exam', 'active' => request()->routeIs('student.exams.*') || request()->routeIs('student.attempts.*')],
        ['label' => __('nav.results'), 'href' => route('student.results.index'), 'icon' => 'results', 'active' => request()->routeIs('student.results.*')],
        ['label' => __('nav.practice'), 'href' => route('student.practice.index'), 'icon' => 'practice', 'active' => request()->routeIs('student.practice.*')],
        ['label' => __('nav.leaderboard'), 'href' => route('student.leaderboards.overall'), 'icon' => 'leaderboard', 'active' => request()->routeIs('student.leaderboards.*')],
        ['label' => __('nav.point_history'), 'href' => route('student.points'), 'icon' => 'points', 'active' => request()->routeIs('student.points')],
        ['label' => __('nav.profile'), 'href' => route('student.profile.edit'), 'icon' => 'profile', 'active' => request()->routeIs('student.profile.*')],
    ];
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" :context="__('nav.context_student')">
    {{ $slot }}
</x-layout.app>
