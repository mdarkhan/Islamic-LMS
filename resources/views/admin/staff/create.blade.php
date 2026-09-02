<x-layout.admin :title="__('staff.new_staff')" :heading="__('staff.new_staff')">
    <x-ui.breadcrumbs :items="[__('nav.staff') => route('admin.staff.index'), __('ui.new') => null]" class="mb-5" />

    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.staff.store') }}" class="space-y-5">
            @csrf
            @include('admin.staff._form')
            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.staff.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('staff.create_staff') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
