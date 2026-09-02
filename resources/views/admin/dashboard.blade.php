<x-layout.admin :title="__('nav.dashboard')" :heading="__('dashboard.admin_heading')">
    @php $me = auth()->user(); @endphp

    {{-- Needs attention --}}
    @if ($unreadMessages > 0 || $pendingReleaseCount > 0)
        <div class="space-y-3 mb-6">
            @if ($unreadMessages > 0 && $me->hasPermission('messages.view'))
                <x-ui.alert type="info" :title="__('dashboard.attention_messages_heading')">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span>{{ __('dashboard.attention_messages_body', ['count' => bn($unreadMessages)]) }}</span>
                        <x-ui.button :href="route('admin.messages.index')" size="sm" variant="secondary">{{ __('nav.messages') }}</x-ui.button>
                    </div>
                </x-ui.alert>
            @endif
            @if ($pendingReleaseCount > 0 && $me->hasPermission('results.release'))
                <x-ui.alert type="warning" :title="__('dashboard.attention_release_heading')">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span>{{ __('dashboard.attention_release_body', ['count' => bn($pendingReleaseCount)]) }}</span>
                        <x-ui.button :href="route('admin.results.index')" size="sm" variant="secondary">{{ __('nav.results') }}</x-ui.button>
                    </div>
                </x-ui.alert>
            @endif
        </div>
    @endif

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
        <x-ui.stat :label="__('dashboard.active_students')" :value="bn($activeStudents)" tone="brand" />
        <x-ui.stat :label="__('dashboard.suspended_students')" :value="bn($suspendedStudents)" tone="rose" />
        <x-ui.stat :label="__('nav.courses')" :value="bn($courseCount)" :sub="__('dashboard.lessons_count', ['count' => bn($lessonCount)])" tone="ink" />
        <x-ui.stat :label="__('dashboard.total_points_held')" :value="bn($pointsHeld)" tone="amber" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Quick actions --}}
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">{{ __('dashboard.quick_actions') }}</h3>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.button :href="route('admin.students.create')" variant="secondary" class="justify-start"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('dashboard.new_student') }}</x-ui.button>
                <x-ui.button :href="route('admin.students.import.form')" variant="secondary" class="justify-start"><x-ui.icon name="import" class="w-4 h-4" /> {{ __('dashboard.import') }}</x-ui.button>
                <x-ui.button :href="route('admin.points.bulk.form')" variant="secondary" class="justify-start"><x-ui.icon name="points" class="w-4 h-4" /> {{ __('dashboard.bulk_points') }}</x-ui.button>
                <x-ui.button :href="route('admin.lessons.create')" variant="secondary" class="justify-start"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('dashboard.new_lesson') }}</x-ui.button>
                @if ($me->hasPermission('quizzes.create'))
                    <x-ui.button :href="route('admin.quizzes.create')" variant="secondary" class="justify-start"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('dashboard.new_quiz') }}</x-ui.button>
                @endif
                @if ($me->hasPermission('notices.manage'))
                    <x-ui.button :href="route('admin.notices.index')" variant="secondary" class="justify-start"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('dashboard.new_notice') }}</x-ui.button>
                @endif
            </div>
        </x-ui.card>

        {{-- Recent point transactions --}}
        <x-ui.card :padded="false">
            <div class="p-5 pb-3 flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.recent_point_transactions') }}</h3>
                <a href="{{ route('admin.points.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('ui.all') }}</a>
            </div>
            <div class="divide-y divide-line">
                @forelse ($recentPoints as $tx)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $tx->user?->name ?? '—' }}</p>
                            <p class="text-xs text-muted truncate">{{ $tx->reason ?? '—' }} · {{ $tx->performedBy?->name ?? __('dashboard.system') }}</p>
                        </div>
                        <span class="font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}</span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-muted">{{ __('dashboard.no_transactions') }}</div>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-layout.admin>
