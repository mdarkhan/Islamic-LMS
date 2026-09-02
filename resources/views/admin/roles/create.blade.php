<x-layout.admin :title="__('roles.new_role')" :heading="__('roles.new_role')">
    <x-ui.breadcrumbs :items="[__('nav.roles') => route('admin.roles.index'), __('ui.new') => null]" class="mb-5" />

    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-5">
            @csrf
            @include('admin.roles._form')
            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.roles.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('roles.create_role') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
