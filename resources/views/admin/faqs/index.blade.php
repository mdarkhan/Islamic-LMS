<x-layout.admin :title="__('nav.faqs')" :heading="__('faqs.heading')">
    {{-- Add --}}
    <x-ui.card class="mb-5">
        <form method="POST" action="{{ route('admin.faqs.store') }}" class="space-y-3">
            @csrf
            <x-ui.field :label="__('faqs.question')" name="question" :required="true">
                <x-ui.input name="question" value="{{ old('question') }}" required />
            </x-ui.field>
            <x-ui.field :label="__('faqs.answer')" name="answer" :required="true">
                <x-ui.textarea name="answer" rows="3" required>{{ old('answer') }}</x-ui.textarea>
            </x-ui.field>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1" checked class="rounded border-line text-brand"> {{ __('admin.published') }}</label>
                <x-ui.button type="submit">{{ __('faqs.add') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if ($faqs->isEmpty())
        <x-ui.card><x-ui.empty :title="__('faqs.none')" /></x-ui.card>
    @else
        <div class="space-y-2">
            @foreach ($faqs as $faq)
                <x-ui.card class="!p-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <x-ui.badge :color="$faq->is_published ? 'success' : 'neutral'">{{ $faq->is_published ? __('admin.published') : __('admin.draft') }}</x-ui.badge>
                        <div class="flex items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.faqs.toggle', $faq) }}">@csrf @method('PUT')
                                <x-ui.button type="submit" variant="secondary" size="sm">{{ $faq->is_published ? __('admin.unpublish') : __('admin.publish') }}</x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="{{ __('faqs.delete_confirm') }}">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm">{{ __('ui.delete') }}</x-ui.button>
                            </form>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.faqs.update', $faq) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <x-ui.input name="question" value="{{ $faq->question }}" />
                        <x-ui.textarea name="answer" rows="3">{{ $faq->answer }}</x-ui.textarea>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted">{{ __('admin.order') }}</span>
                                <input type="number" name="sort_order" value="{{ $faq->sort_order }}" class="w-20 rounded-lg border border-line bg-surface-raised px-2 py-1.5 text-sm text-ink outline-none focus:border-brand">
                            </div>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1" @checked($faq->is_published) class="rounded border-line text-brand"> {{ __('admin.published') }}</label>
                            <x-ui.button type="submit" variant="secondary" size="sm">{{ __('admin.save_changes') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layout.admin>
