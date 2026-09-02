<x-layout.admin :title="__('nav.staff')" :heading="__('staff.admin_heading')">
    <div class="flex justify-end mb-6">
        <x-ui.button :href="route('admin.staff.create')"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('staff.new_staff') }}</x-ui.button>
    </div>

    @if ($staff->isEmpty())
        <x-ui.card><x-ui.empty :title="__('staff.no_staff')" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('staff.name_col') }}</th>
                <th class="px-4 py-3 hidden sm:table-cell">{{ __('staff.email_col') }}</th>
                <th class="px-4 py-3">{{ __('staff.role_col') }}</th>
                <th class="px-4 py-3">{{ __('staff.status_col') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($staff as $member)
                <tr class="hover:bg-ink/[0.02]">
                    <td class="px-4 py-3 font-semibold text-ink">
                        {{ $member->name }}
                        @if ($member->id === auth()->id())
                            <span class="text-xs text-muted font-normal">({{ __('ui.you') }})</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $member->email }}</td>
                    <td class="px-4 py-3">
                        @foreach ($member->roles as $role)
                            <x-ui.badge color="brand">{{ $role->label }}</x-ui.badge>
                        @endforeach
                    </td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$member->status" /></td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.staff.edit', $member) }}" class="text-brand font-semibold hover:underline text-sm">{{ __('ui.edit') }}</a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $staff->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
