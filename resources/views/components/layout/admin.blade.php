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

    if ($user?->hasPermission('books.manage')) {
        $nav[] = ['label' => __('nav.books'), 'href' => route('admin.books.index'), 'icon' => 'book', 'active' => request()->routeIs('admin.books.*')];
    }

    // Quiz section only for users who may view quizzes.
    if ($user?->hasPermission('quizzes.view')) {
        $nav[] = ['label' => __('nav.quizzes'), 'href' => route('admin.quizzes.index'), 'icon' => 'exam', 'active' => request()->routeIs('admin.quizzes.*')];
    }

    if ($user?->hasPermission('results.view')) {
        $nav[] = ['label' => __('nav.results'), 'href' => route('admin.results.index'), 'icon' => 'results', 'active' => request()->routeIs('admin.results.*')];
    }

    if ($user?->hasPermission('posts.manage')) {
        $nav[] = ['label' => __('nav.posts'), 'href' => route('admin.posts.index'), 'icon' => 'book', 'active' => request()->routeIs('admin.posts.*')];
    }

    // Unread student messages across every thread — the shared-inbox badge.
    if ($user?->hasPermission('messages.view')) {
        $unreadMessages = app(\App\Services\Messaging\MessageService::class)->unreadCountFor($user);
        $nav[] = ['label' => __('nav.messages'), 'href' => route('admin.messages.index'), 'icon' => 'message', 'active' => request()->routeIs('admin.messages.*'), 'badge' => $unreadMessages ?: null];
    }

    if ($user?->hasPermission('amol.view')) {
        $nav[] = ['label' => __('nav.amol'), 'href' => route('admin.amol.index'), 'icon' => 'checklist', 'active' => request()->routeIs('admin.amol.*')];
    }

    if ($user?->hasPermission('notices.manage')) {
        $nav[] = ['label' => __('nav.notices'), 'href' => route('admin.notices.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.notices.*')];
    }

    if ($user?->hasPermission('faqs.manage')) {
        $nav[] = ['label' => __('nav.faqs'), 'href' => route('admin.faqs.index'), 'icon' => 'message', 'active' => request()->routeIs('admin.faqs.*')];
    }

    if ($user?->hasPermission('settings.manage')) {
        $nav[] = ['label' => __('nav.settings'), 'href' => route('admin.settings.edit'), 'icon' => 'settings', 'active' => request()->routeIs('admin.settings.*')];
    }

    $nav[] = ['label' => __('nav.audit'), 'href' => route('admin.audit.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.audit.*')];

    // Staff & role management — super_admin only, deliberately not a perm: gate (see
    // routes/web.php): a regular admin must never see or reach this area.
    if ($user?->hasRole(\App\Models\Role::SUPER_ADMIN)) {
        $nav[] = ['label' => __('nav.staff'), 'href' => route('admin.staff.index'), 'icon' => 'students', 'active' => request()->routeIs('admin.staff.*')];
        $nav[] = ['label' => __('nav.roles'), 'href' => route('admin.roles.index'), 'icon' => 'key', 'active' => request()->routeIs('admin.roles.*')];
    }
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" :context="__('nav.context_admin')" :search-route="route('admin.search.index')">
    {{ $slot }}
</x-layout.app>
