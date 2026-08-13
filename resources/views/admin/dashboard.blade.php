<x-layout.admin title="ড্যাশবোর্ড" heading="অ্যাডমিন ড্যাশবোর্ড">
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
        <x-ui.stat label="সক্রিয় শিক্ষার্থী" :value="bn($activeStudents)" tone="brand" />
        <x-ui.stat label="স্থগিত শিক্ষার্থী" :value="bn($suspendedStudents)" tone="rose" />
        <x-ui.stat label="কোর্স" :value="bn($courseCount)" sub="{{ bn($lessonCount) }} টি ক্লাস" tone="ink" />
        <x-ui.stat label="মোট পয়েন্ট (শিক্ষার্থী)" :value="bn($pointsHeld)" tone="amber" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Quick actions --}}
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">দ্রুত কাজ</h3>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.button :href="route('admin.students.create')" variant="secondary" class="justify-start"><x-ui.icon name="plus" class="w-4 h-4" /> নতুন শিক্ষার্থী</x-ui.button>
                <x-ui.button :href="route('admin.students.import.form')" variant="secondary" class="justify-start"><x-ui.icon name="import" class="w-4 h-4" /> ইমপোর্ট</x-ui.button>
                <x-ui.button :href="route('admin.points.bulk.form')" variant="secondary" class="justify-start"><x-ui.icon name="points" class="w-4 h-4" /> বাল্ক পয়েন্ট</x-ui.button>
                <x-ui.button :href="route('admin.lessons.create')" variant="secondary" class="justify-start"><x-ui.icon name="plus" class="w-4 h-4" /> নতুন ক্লাস</x-ui.button>
            </div>
        </x-ui.card>

        {{-- Recent point transactions --}}
        <x-ui.card :padded="false">
            <div class="p-5 pb-3 flex items-center justify-between">
                <h3 class="font-bold text-ink">সাম্প্রতিক পয়েন্ট লেনদেন</h3>
                <a href="{{ route('admin.points.index') }}" class="text-sm text-brand font-semibold hover:underline">সব</a>
            </div>
            <div class="divide-y divide-line">
                @forelse ($recentPoints as $tx)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $tx->user?->name ?? '—' }}</p>
                            <p class="text-xs text-muted truncate">{{ $tx->reason ?? '—' }} · {{ $tx->performedBy?->name ?? 'সিস্টেম' }}</p>
                        </div>
                        <span class="font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}</span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-muted">এখনো কোনো লেনদেন নেই।</div>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-layout.admin>
