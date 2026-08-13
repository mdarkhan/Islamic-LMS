@props(['title' => null, 'heading' => null])

@php
    $nav = [
        ['label' => 'ড্যাশবোর্ড', 'href' => route('student.dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('student.dashboard')],
        ['label' => 'কোর্স', 'href' => route('student.courses.index'), 'icon' => 'book', 'active' => request()->routeIs('student.courses.*')],
        ['label' => 'পরীক্ষা', 'href' => route('student.exams.index'), 'icon' => 'exam', 'active' => request()->routeIs('student.exams.*')],
        ['label' => 'ফলাফল', 'icon' => 'results', 'disabled' => true],
        ['label' => 'অনুশীলন', 'icon' => 'practice', 'disabled' => true],
        ['label' => 'মেধাতালিকা', 'icon' => 'leaderboard', 'disabled' => true],
        ['label' => 'পয়েন্ট হিস্ট্রি', 'href' => route('student.points'), 'icon' => 'points', 'active' => request()->routeIs('student.points')],
        ['label' => 'প্রোফাইল', 'href' => route('student.profile.edit'), 'icon' => 'profile', 'active' => request()->routeIs('student.profile.*')],
    ];
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" context="শিক্ষার্থী">
    {{ $slot }}
</x-layout.app>
