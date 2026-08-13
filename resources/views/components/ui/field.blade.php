@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'required' => false,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="block text-sm font-semibold text-ink">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    @endif
</div>
