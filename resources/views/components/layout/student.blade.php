@props(['title' => null, 'heading' => null])

@php
    $nav = [
        ['label' => __('nav.dashboard'), 'href' => route('student.dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('student.dashboard')],
        ['label' => __('nav.courses'), 'href' => route('student.courses.index'), 'icon' => 'book', 'active' => request()->routeIs('student.courses.*')],
        ['label' => __('nav.exams'), 'href' => route('student.exams.index'), 'icon' => 'exam', 'active' => request()->routeIs('student.exams.*')],
        ['label' => __('nav.results'), 'icon' => 'results', 'disabled' => true],
        ['label' => __('nav.practice'), 'icon' => 'practice', 'disabled' => true],
        ['label' => __('nav.leaderboard'), 'icon' => 'leaderboard', 'disabled' => true],
        ['label' => __('nav.point_history'), 'href' => route('student.points'), 'icon' => 'points', 'active' => request()->routeIs('student.points')],
        ['label' => __('nav.profile'), 'href' => route('student.profile.edit'), 'icon' => 'profile', 'active' => request()->routeIs('student.profile.*')],
    ];
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" :context="__('nav.context_student')">
    {{ $slot }}
</x-layout.app>
