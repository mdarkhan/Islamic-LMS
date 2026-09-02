<x-layout.admin :title="__('staff.edit_staff')" :heading="__('staff.edit_staff')">
    <x-ui.breadcrumbs :items="[
        __('nav.staff') => route('admin.staff.index'),
        $staff->name => null,
    ]" class="mb-5" />

    <x-temp-password />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.staff.update', $staff) }}" class="space-y-5">
                @csrf @method('PUT')
                @include('admin.staff._form')
                <div class="flex justify-end gap-3 border-t border-line pt-5">
                    <x-ui.button :href="route('admin.staff.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                    <x-ui.button type="submit">{{ __('admin.save_changes') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($staff->id !== auth()->id())
            <x-ui.card>
                <h3 class="font-bold text-ink mb-3">{{ __('staff.account_heading') }}</h3>
                <div class="space-y-2">
                    @if ($staff->status === 'active')
                        <form method="POST" action="{{ route('admin.staff.status', $staff) }}">
                            @csrf @method('PUT')<input type="hidden" name="status" value="suspended">
                            <x-ui.button type="submit" variant="secondary" class="w-full justify-center text-amber-600">{{ __('staff.suspend') }}</x-ui.button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.staff.status', $staff) }}">
                            @csrf @method('PUT')<input type="hidden" name="status" value="active">
                            <x-ui.button type="submit" variant="secondary" class="w-full justify-center text-brand">{{ __('staff.reactivate') }}</x-ui.button>
                        </form>
                    @endif
                    @if ($staff->status !== 'archived')
                        <form method="POST" action="{{ route('admin.staff.status', $staff) }}" data-confirm="{{ __('staff.confirm_archive') }}">
                            @csrf @method('PUT')<input type="hidden" name="status" value="archived">
                            <x-ui.button type="submit" variant="ghost" class="w-full justify-center">{{ __('staff.archive_staff') }}</x-ui.button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.staff.reset-password', $staff) }}" data-confirm="{{ __('staff.confirm_reset_password') }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" class="w-full justify-center"><x-ui.icon name="key" class="w-4 h-4" /> {{ __('staff.reset_password') }}</x-ui.button>
                    </form>
                </div>
                @error('status')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror
                @error('role_id')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror
            </x-ui.card>
        @endif
    </div>
</x-layout.admin>
