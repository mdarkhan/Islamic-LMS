@php $me = auth()->user(); @endphp
<x-layout.admin :title="$student->name" :heading="$student->name">
    <x-ui.breadcrumbs :items="['শিক্ষার্থী' => route('admin.students.index'), $student->name => null]" class="mb-5" />

    <x-temp-password />

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Identity + actions --}}
        <div class="space-y-6">
            <x-ui.card>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-brand-tint text-brand grid place-items-center shrink-0"><x-ui.icon name="profile" class="w-6 h-6" /></div>
                    <div class="min-w-0">
                        <h2 class="font-bold text-ink truncate">{{ $student->name }}</h2>
                        <p class="text-sm text-muted">রোল: {{ bn($student->roll) }}</p>
                    </div>
                </div>
                <dl class="mt-5 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">অভিভাবক</dt><dd class="text-ink text-right">{{ $student->guardian_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">ইমেইল</dt><dd class="text-ink text-right truncate">{{ $student->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">মোবাইল</dt><dd class="text-ink text-right">{{ $student->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">স্ট্যাটাস</dt><dd><x-ui.status-badge :status="$student->status" /></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">পয়েন্ট</dt><dd class="font-bold text-brand">{{ bn($student->points_balance) }}</dd></div>
                </dl>
                @if ($me->hasPermission('students.update'))
                    <x-ui.button :href="route('admin.students.edit', $student)" variant="secondary" class="w-full mt-5"><x-ui.icon name="edit" class="w-4 h-4" /> সম্পাদনা</x-ui.button>
                @endif
            </x-ui.card>

            {{-- Account actions --}}
            @if ($me->hasPermission('students.suspend') || $me->hasPermission('students.reset_password'))
                <x-ui.card>
                    <h3 class="font-bold text-ink mb-3">অ্যাকাউন্ট</h3>
                    <div class="space-y-2">
                        @if ($me->hasPermission('students.suspend') && $student->id !== $me->id)
                            @if ($student->status === 'active')
                                <form method="POST" action="{{ route('admin.students.status', $student) }}">
                                    @csrf @method('PUT')<input type="hidden" name="status" value="suspended">
                                    <x-ui.button type="submit" variant="secondary" class="w-full justify-center text-amber-600">স্থগিত করুন</x-ui.button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.students.status', $student) }}">
                                    @csrf @method('PUT')<input type="hidden" name="status" value="active">
                                    <x-ui.button type="submit" variant="secondary" class="w-full justify-center text-brand">পুনরায় সক্রিয় করুন</x-ui.button>
                                </form>
                            @endif
                            @if ($student->status !== 'archived')
                                <form method="POST" action="{{ route('admin.students.status', $student) }}"
                                      onsubmit="return confirm('এই শিক্ষার্থীকে সংরক্ষণাগারভুক্ত করবেন? পরীক্ষার ইতিহাস অক্ষত থাকবে।')">
                                    @csrf @method('PUT')<input type="hidden" name="status" value="archived">
                                    <x-ui.button type="submit" variant="ghost" class="w-full justify-center">সংরক্ষণাগারভুক্ত করুন</x-ui.button>
                                </form>
                            @endif
                        @endif
                        @if ($me->hasPermission('students.reset_password'))
                            <form method="POST" action="{{ route('admin.students.reset-password', $student) }}"
                                  onsubmit="return confirm('নতুন অস্থায়ী পাসওয়ার্ড তৈরি করবেন? বর্তমান সেশন বাতিল হবে।')">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" class="w-full justify-center"><x-ui.icon name="key" class="w-4 h-4" /> পাসওয়ার্ড রিসেট</x-ui.button>
                            </form>
                        @endif
                    </div>
                    @error('status')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror
                </x-ui.card>
            @endif
        </div>

        {{-- History --}}
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card :padded="false">
                <div class="p-5 pb-3 flex items-center justify-between">
                    <h3 class="font-bold text-ink">সাম্প্রতিক পয়েন্ট</h3>
                    @if ($me->hasPermission('points.view'))
                        <a href="{{ route('admin.points.show', $student) }}" class="text-sm text-brand font-semibold hover:underline">সম্পূর্ণ লেজার</a>
                    @endif
                </div>
                <div class="divide-y divide-line">
                    @forelse ($student->pointTransactions as $tx)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm text-ink truncate">{{ $tx->reason ?? 'সমন্বয়' }}</p>
                                <p class="text-xs text-muted">{{ $tx->created_at->format('d/m/Y H:i') }} · {{ $tx->performedBy?->name ?? 'সিস্টেম' }}</p>
                            </div>
                            <span class="font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}</span>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">কোনো পয়েন্ট লেনদেন নেই।</div>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card :padded="false">
                <div class="p-5 pb-3"><h3 class="font-bold text-ink">পরীক্ষার ইতিহাস</h3></div>
                <div class="divide-y divide-line">
                    @forelse ($attempts as $attempt)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm text-ink truncate">{{ $attempt->quiz?->title ?? '—' }}</p>
                                <p class="text-xs text-muted">{{ $attempt->submitted_at?->format('d/m/Y') ?? '—' }}</p>
                            </div>
                            <span class="text-sm font-semibold text-ink tabular-nums">{{ bn($attempt->final_score) }}/{{ bn($attempt->total_marks_snapshot) }}</span>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">এখনো কোনো পরীক্ষা দেয়নি।</div>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layout.admin>
