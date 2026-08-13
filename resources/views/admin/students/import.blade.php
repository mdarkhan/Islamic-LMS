<x-layout.admin :title="__('students.import_heading')" :heading="__('students.import_heading')">
    <x-ui.breadcrumbs :items="[__('nav.students') => route('admin.students.index'), __('dashboard.import') => null]" class="mb-5" />

    @isset($parseError)
        <x-ui.alert type="error" :title="__('students.parse_error_title')" class="mb-6">{{ $parseError }}</x-ui.alert>
    @endisset

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.students.import.preview') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <x-ui.field :label="__('students.file_label')" name="file" :hint="__('students.file_hint')" required>
                    <input type="file" name="file" accept=".csv,.xlsx,text/csv"
                           class="block w-full text-sm text-muted file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-brand file:text-brand-ink file:font-semibold hover:file:bg-brand-strong file:cursor-pointer">
                </x-ui.field>
                <x-ui.button type="submit"><x-ui.icon name="import" class="w-4 h-4" /> {{ __('students.preview_button') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card>
            <h3 class="font-bold text-ink mb-3">{{ __('students.columns_heading') }}</h3>
            <ul class="text-sm text-muted space-y-2">
                <li class="flex items-center gap-2"><x-ui.icon name="check" class="w-4 h-4 text-brand" /> {{ __('students.col_roll_required') }}</li>
                <li class="flex items-center gap-2"><x-ui.icon name="check" class="w-4 h-4 text-brand" /> {{ __('students.col_name_required') }}</li>
                <li class="flex items-center gap-2"><x-ui.icon name="check" class="w-4 h-4 text-brand" /> {{ __('students.col_guardian') }}</li>
            </ul>
            <p class="text-xs text-muted mt-4 border-t border-line pt-3 leading-relaxed">
                {{ __('students.import_security_note') }}
            </p>
        </x-ui.card>
    </div>
</x-layout.admin>
