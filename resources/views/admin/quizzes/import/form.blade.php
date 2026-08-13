<x-layout.admin :title="__('quizzes.import_heading')" :heading="__('quizzes.import_heading')">
    <x-ui.breadcrumbs :items="[__('nav.quizzes') => route('admin.quizzes.index'), __('dashboard.import') => null]" class="mb-5" />

    @isset($parseError)
        <x-ui.alert type="error" :title="__('quizzes.file_parse_error')" class="mb-6">{{ $parseError }}</x-ui.alert>
    @endisset

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.quizzes.import.upload') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <x-ui.field :label="__('quizzes.file_label')" name="file" :hint="__('quizzes.file_hint')" required>
                    <input type="file" name="file" accept=".csv,.xlsx,text/csv"
                           class="block w-full text-sm text-muted file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-brand file:text-brand-ink file:font-semibold hover:file:bg-brand-strong file:cursor-pointer">
                </x-ui.field>
                <x-ui.button type="submit"><x-ui.icon name="import" class="w-4 h-4" /> {{ __('quizzes.upload_and_preview') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card>
            <h3 class="font-bold text-ink mb-3">{{ __('quizzes.easy_method') }}</h3>
            <ol class="text-sm text-muted space-y-2 list-decimal list-inside">
                <li>{{ __('quizzes.easy_step_1') }}</li>
                <li>File → Download → <span class="font-medium text-ink">Microsoft Excel (.xlsx)</span></li>
                <li>{{ __('quizzes.easy_step_3') }}</li>
                <li>{{ __('quizzes.easy_step_4') }}</li>
            </ol>
            <p class="text-xs text-muted mt-4 border-t border-line pt-3 leading-relaxed">
                {{ __('quizzes.legacy_format_note') }}
            </p>
        </x-ui.card>
    </div>
</x-layout.admin>
