@php
    // Shared page for both roles — the sidebar/chrome must still match where the
    // viewer actually is, so the layout is picked at render time rather than the
    // controller needing a Student\ and Admin\ copy of this same list.
    $layout = auth()->user()->isAdmin() ? 'layout.admin' : 'layout.student';
@endphp

<x-dynamic-component :component="$layout" :title="__('notifications.heading')" :heading="__('notifications.heading')">
    @if ($notifications->isEmpty())
        <x-ui.card><x-ui.empty :title="__('notifications.empty')" /></x-ui.card>
    @else
        <div class="flex justify-end mb-4">
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf @method('PUT')
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('notifications.mark_all_read') }}</x-ui.button>
            </form>
        </div>

        <div class="space-y-2">
            @foreach ($notifications as $n)
                <div @class([
                    'flex items-start gap-2 rounded-2xl border p-4',
                    'border-brand/40 bg-brand-tint/30' => ! $n->read_at,
                    'border-line bg-surface' => $n->read_at,
                ])>
                    <a href="{{ route('notifications.open', $n) }}" class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-ink">{{ $n->title }}</p>
                        @if ($n->body)<p class="text-sm text-muted mt-0.5">{{ $n->body }}</p>@endif
                        <p class="text-xs text-muted mt-1.5">{{ $n->updated_at?->format('d/m/Y H:i') }}</p>
                    </a>
                    <form method="POST" action="{{ route('notifications.destroy', $n) }}">
                        @csrf @method('DELETE')
                        <button class="p-2 text-muted hover:text-rose-600 transition-colors" aria-label="{{ __('notifications.delete') }}">
                            <x-ui.icon name="trash" class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $notifications->links('components.pagination') }}</div>
    @endif
</x-dynamic-component>
