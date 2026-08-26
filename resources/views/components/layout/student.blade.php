@props(['title' => null, 'heading' => null])

@php
    // Unread ustaz replies — drives the sidebar badge so a student sees on any page
    // that something is waiting for them.
    $unreadMessages = auth()->check()
        ? app(\App\Services\Messaging\MessageService::class)->unreadCountFor(auth()->user())
        : 0;

    $nav = [
        ['label' => __('nav.dashboard'), 'href' => route('student.dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('student.dashboard')],
        ['label' => __('nav.courses'), 'href' => route('student.courses.index'), 'icon' => 'book', 'active' => request()->routeIs('student.courses.*')],
        ['label' => __('nav.exams'), 'href' => route('student.exams.index'), 'icon' => 'exam', 'active' => request()->routeIs('student.exams.*') || request()->routeIs('student.attempts.*')],
        ['label' => __('nav.results'), 'href' => route('student.results.index'), 'icon' => 'results', 'active' => request()->routeIs('student.results.*')],
        ['label' => __('nav.practice'), 'href' => route('student.practice.index'), 'icon' => 'practice', 'active' => request()->routeIs('student.practice.*')],
        ['label' => __('nav.leaderboard'), 'href' => route('student.leaderboards.overall'), 'icon' => 'leaderboard', 'active' => request()->routeIs('student.leaderboards.*')],
        ['label' => __('nav.point_history'), 'href' => route('student.points'), 'icon' => 'points', 'active' => request()->routeIs('student.points')],
        ['label' => __('nav.messages'), 'href' => route('student.messages.index'), 'icon' => 'message', 'active' => request()->routeIs('student.messages.*'), 'badge' => $unreadMessages ?: null],
        ['label' => __('nav.profile'), 'href' => route('student.profile.edit'), 'icon' => 'profile', 'active' => request()->routeIs('student.profile.*')],
    ];

    // Pending bonus-point rewards → the congratulations screen (shown after login).
    $pendingRewards = auth()->check()
        ? app(\App\Services\Rewards\RewardService::class)->pendingFor(auth()->user())
        : collect();
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" :context="__('nav.context_student')">
    {{ $slot }}

    @if ($pendingRewards->isNotEmpty())
        @include('student.partials.congrats', ['rewards' => $pendingRewards])
    @endif
</x-layout.app>
