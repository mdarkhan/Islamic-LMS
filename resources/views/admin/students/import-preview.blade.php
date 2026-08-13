@php
    $statusMeta = [
        'import' => ['success', __('students.status_will_import')],
        'exists' => ['warning', __('students.status_exists')],
        'duplicate' => ['warning', __('students.status_duplicate')],
        'error' => ['danger', __('students.status_error')],
    ];
@endphp
<x-layout.admin :title="__('students.preview_heading')" :heading="__('students.preview_heading')">
    <x-ui.breadcrumbs :items="[__('nav.students') => route('admin.students.index'), __('dashboard.import') => route('admin.students.import.form'), __('ui.view') => null]" class="mb-5" />

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-ui.stat :label="__('students.stat_will_import')" :value="bn($summary['import'])" tone="brand" />
        <x-ui.stat :label="__('students.stat_existing')" :value="bn($summary['exists'])" tone="amber" />
        <x-ui.stat :label="__('students.stat_duplicate')" :value="bn($summary['duplicate'])" tone="amber" />
        <x-ui.stat :label="__('students.stat_error')" :value="bn($summary['error'])" tone="rose" />
    </div>

    <x-ui.alert type="info" class="mb-6">
        {{ __('students.preview_security_note') }}
    </x-ui.alert>

    <x-ui.table>
        <x-slot:head>
            <th class="px-4 py-3">{{ __('students.row_col') }}</th>
            <th class="px-4 py-3">{{ __('students.roll_col') }}</th>
            <th class="px-4 py-3">{{ __('students.name_col') }}</th>
            <th class="px-4 py-3 hidden sm:table-cell">{{ __('students.guardian_col') }}</th>
            <th class="px-4 py-3">{{ __('ui.status') }}</th>
        </x-slot:head>
        @foreach ($rows as $row)
            @php [$color, $label] = $statusMeta[$row['status']]; @endphp
            <tr class="{{ $row['status'] === 'error' ? 'bg-rose-500/[0.03]' : '' }}">
                <td class="px-4 py-3 text-muted tabular-nums">{{ bn($row['row']) }}</td>
                <td class="px-4 py-3 text-ink tabular-nums">{{ $row['roll'] ? bn($row['roll']) : '—' }}</td>
                <td class="px-4 py-3 text-ink">{{ $row['name'] ?? '—' }}</td>
                <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $row['guardian_name'] ?? '—' }}</td>
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
        <x-ui.button :href="route('admin.students.import.form')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
        <form method="POST" action="{{ route('admin.students.import.confirm') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-ui.button type="submit" :disabled="$summary['import'] === 0">
                {{ __('students.confirm_import_count', ['count' => bn($summary['import'])]) }}
            </x-ui.button>
        </form>
    </div>
</x-layout.admin>
