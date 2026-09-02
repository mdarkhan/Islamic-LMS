<x-layout.admin :title="__('roles.edit_role')" :heading="__('roles.edit_role')">
    <x-ui.breadcrumbs :items="[__('nav.roles') => route('admin.roles.index'), $role->label => null]" class="mb-5" />

    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-5">
            @csrf @method('PUT')
            @include('admin.roles._form')
            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.roles.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('admin.save_changes') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
