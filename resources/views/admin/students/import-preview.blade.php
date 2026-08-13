@php
    $statusMeta = [
        'import' => ['success', 'ইমপোর্ট হবে'],
        'exists' => ['warning', 'বিদ্যমান — বাদ'],
        'duplicate' => ['warning', 'ডুপ্লিকেট — বাদ'],
        'error' => ['danger', 'ত্রুটি — বাদ'],
    ];
@endphp
<x-layout.admin title="ইমপোর্ট প্রিভিউ" heading="ইমপোর্ট প্রিভিউ">
    <x-ui.breadcrumbs :items="['শিক্ষার্থী' => route('admin.students.index'), 'ইমপোর্ট' => route('admin.students.import.form'), 'প্রিভিউ' => null]" class="mb-5" />

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-ui.stat label="ইমপোর্ট হবে" :value="bn($summary['import'])" tone="brand" />
        <x-ui.stat label="বিদ্যমান" :value="bn($summary['exists'])" tone="amber" />
        <x-ui.stat label="ডুপ্লিকেট" :value="bn($summary['duplicate'])" tone="amber" />
        <x-ui.stat label="ত্রুটি" :value="bn($summary['error'])" tone="rose" />
    </div>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">সারি</th>
            <th class="px-4 py-3">রোল</th>
            <th class="px-4 py-3">নাম</th>
            <th class="px-4 py-3 hidden sm:table-cell">অভিভাবক</th>
            <th class="px-4 py-3">পাসওয়ার্ড</th>
            <th class="px-4 py-3">অবস্থা</th>
        </x-slot:head>
        @foreach ($rows as $row)
            @php [$color, $label] = $statusMeta[$row['status']]; @endphp
            <tr class="{{ $row['status'] === 'error' ? 'bg-rose-500/[0.03]' : '' }}">
                <td class="px-4 py-3 text-muted tabular-nums">{{ bn($row['row']) }}</td>
                <td class="px-4 py-3 text-ink tabular-nums">{{ $row['roll'] ? bn($row['roll']) : '—' }}</td>
                <td class="px-4 py-3 text-ink">{{ $row['name'] ?? '—' }}</td>
                <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $row['guardian_name'] ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if ($row['has_password'])<span class="text-muted">••••</span>@else<span class="text-xs text-amber-600">স্বয়ংক্রিয়</span>@endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :color="$color">{{ $label }}</x-ui.badge>
                    @if (! empty($row['errors']))
                        <p class="text-xs text-muted mt-1">{{ implode(' ', $row['errors']) }}</p>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-6 flex items-center justify-between gap-3">
        <x-ui.button :href="route('admin.students.import.form')" variant="ghost">বাতিল</x-ui.button>
        <form method="POST" action="{{ route('admin.students.import.confirm') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-ui.button type="submit" :disabled="$summary['import'] === 0">
                {{ bn($summary['import']) }} জন ইমপোর্ট নিশ্চিত করুন
            </x-ui.button>
        </form>
    </div>
</x-layout.admin>
