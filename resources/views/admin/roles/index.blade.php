<x-layout.admin :title="__('nav.roles')" :heading="__('roles.admin_heading')">
    <div class="flex justify-end mb-6">
        <x-ui.button :href="route('admin.roles.create')"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('roles.new_role') }}</x-ui.button>
    </div>

    @if ($roles->isEmpty())
        <x-ui.card><x-ui.empty :title="__('roles.no_roles')" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('roles.label_col') }}</th>
                <th class="px-4 py-3 hidden sm:table-cell">{{ __('roles.name_col') }}</th>
                <th class="px-4 py-3 text-center">{{ __('roles.permissions_col') }}</th>
                <th class="px-4 py-3 text-center">{{ __('roles.users_col') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($roles as $role)
                <tr>
                    <td class="px-4 py-3 font-semibold text-ink">{{ $role->label }}</td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell font-mono text-xs">{{ $role->name }}</td>
                    <td class="px-4 py-3 text-center tabular-nums">{{ $role->isProtected() ? '—' : bn($role->permissions_count) }}</td>
                    <td class="px-4 py-3 text-center tabular-nums">{{ bn($role->users_count) }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-3">
                            @if ($role->isProtected())
                                <span class="text-xs text-muted">{{ __('roles.protected') }}</span>
                            @else
                                <a href="{{ route('admin.roles.edit', $role) }}" class="text-brand font-semibold hover:underline text-sm">{{ __('ui.edit') }}</a>
                            @endif
                            @if (! $role->isBuiltIn() && $role->users_count === 0)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="{{ __('roles.confirm_delete') }}">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 font-semibold hover:underline text-sm">{{ __('roles.delete') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.admin>
