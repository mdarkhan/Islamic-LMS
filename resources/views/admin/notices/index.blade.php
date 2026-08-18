@php
    $now = now();
    $statusOf = function ($n) use ($now) {
        if (! $n->is_active) return ['status_inactive', 'neutral'];
        if ($n->starts_at && $now->lt($n->starts_at)) return ['status_scheduled', 'info'];
        if ($n->ends_at && $now->gt($n->ends_at)) return ['status_expired', 'warning'];
        return ['status_live', 'success'];
    };
    $audiences = ['all', 'public', 'students'];
    $dt = fn ($d) => $d?->format('Y-m-d\TH:i');
@endphp

<x-layout.admin :title="__('notices.heading')" :heading="__('notices.heading')">
    <p class="text-xs text-muted mb-3">{{ __('notices.window_hint') }}</p>

    {{-- Add --}}
    <x-ui.card class="mb-5">
        <form method="POST" action="{{ route('admin.notices.store') }}" class="space-y-3">
            @csrf
            <x-ui.field :label="__('notices.body')" name="body" :required="true">
                <x-ui.textarea name="body" rows="2" required>{{ old('body') }}</x-ui.textarea>
            </x-ui.field>
            <div class="grid sm:grid-cols-4 gap-3">
                <x-ui.field :label="__('notices.audience')" name="audience">
                    <x-ui.select name="audience">
                        @foreach ($audiences as $a)<option value="{{ $a }}">{{ __('notices.audience_'.$a) }}</option>@endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field :label="__('notices.priority')" name="priority">
                    <x-ui.input type="number" name="priority" value="{{ old('priority', 0) }}" />
                </x-ui.field>
                <x-ui.field :label="__('notices.starts_at')" name="starts_at">
                    <x-ui.input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" />
                </x-ui.field>
                <x-ui.field :label="__('notices.ends_at')" name="ends_at">
                    <x-ui.input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" />
                </x-ui.field>
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded border-line text-brand"> {{ __('notices.active') }}</label>
                <x-ui.button type="submit">{{ __('notices.add') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if ($notices->isEmpty())
        <x-ui.card><x-ui.empty :title="__('notices.none')" /></x-ui.card>
    @else
        <div class="space-y-2">
            @foreach ($notices as $notice)
                @php [$statusKey, $tone] = $statusOf($notice); @endphp
                <x-ui.card class="!p-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <x-ui.badge :color="$tone">{{ __('notices.'.$statusKey) }}</x-ui.badge>
                        <div class="flex items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.notices.toggle', $notice) }}">@csrf @method('PUT')
                                <x-ui.button type="submit" variant="secondary" size="sm">{{ $notice->is_active ? __('notices.deactivate') : __('notices.activate') }}</x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('admin.notices.destroy', $notice) }}" onsubmit="return confirm('{{ __('notices.delete_confirm') }}')">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm">{{ __('notices.delete') }}</x-ui.button>
                            </form>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.notices.update', $notice) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <x-ui.textarea name="body" rows="2">{{ $notice->body }}</x-ui.textarea>
                        <div class="grid sm:grid-cols-4 gap-3">
                            <x-ui.select name="audience">
                                @foreach ($audiences as $a)<option value="{{ $a }}" @selected($notice->audience === $a)>{{ __('notices.audience_'.$a) }}</option>@endforeach
                            </x-ui.select>
                            <x-ui.input type="number" name="priority" value="{{ $notice->priority }}" />
                            <x-ui.input type="datetime-local" name="starts_at" value="{{ $dt($notice->starts_at) }}" />
                            <x-ui.input type="datetime-local" name="ends_at" value="{{ $dt($notice->ends_at) }}" />
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($notice->is_active) class="rounded border-line text-brand"> {{ __('notices.active') }}</label>
                            <x-ui.button type="submit" variant="secondary" size="sm">{{ __('notices.save') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endforeach
        </div>
        <div class="mt-4">{{ $notices->links() }}</div>
    @endif
</x-layout.admin>
