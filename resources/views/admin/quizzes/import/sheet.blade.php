<x-layout.admin :title="__('quizzes.import_worksheet_heading')" :heading="__('quizzes.import_worksheet_heading')">
    <x-ui.breadcrumbs :items="[__('nav.quizzes') => route('admin.quizzes.index'), __('dashboard.import') => route('admin.quizzes.import.form'), __('quizzes.import_worksheet_heading') => null]" class="mb-5" />

    <x-ui.card class="max-w-lg">
        <p class="text-sm text-muted mb-4">{{ __('quizzes.import_worksheet_prompt') }}</p>
        <div class="space-y-2">
            @foreach ($sheets as $sheet)
                <form method="POST" action="{{ route('admin.quizzes.import.preview') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="sheet" value="{{ $sheet }}">
                    <button class="w-full flex items-center justify-between px-4 py-3 rounded-xl border border-line hover:border-brand/50 hover:bg-brand-tint/40 text-left transition-colors">
                        <span class="font-medium text-ink">{{ $sheet }}</span>
                        <x-ui.icon name="chevron" class="w-5 h-5 text-muted" />
                    </button>
                </form>
            @endforeach
        </div>
    </x-ui.card>
</x-layout.admin>
