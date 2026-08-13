@php
    $current = app()->getLocale();
    $options = ['bn' => 'বাং', 'en' => 'EN'];
@endphp

<div class="inline-flex items-center rounded-lg border border-line p-0.5 bg-surface-raised" role="group" aria-label="ভাষা / Language">
    @foreach ($options as $code => $label)
        <form method="POST" action="{{ route('locale.update') }}">
            @csrf
            <input type="hidden" name="locale" value="{{ $code }}">
            <button type="submit"
                    class="px-2 py-1 rounded-md text-xs font-bold transition-colors {{ $current === $code ? 'bg-brand text-brand-ink' : 'text-muted hover:text-ink' }}"
                    @if ($current === $code) aria-current="true" @endif>
                {{ $label }}
            </button>
        </form>
    @endforeach
</div>
