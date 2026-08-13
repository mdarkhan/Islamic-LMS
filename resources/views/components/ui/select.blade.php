@props(['name' => null])
@php $hasError = $name ? $errors->has($name) : false; $ring = $hasError ? 'border-rose-400 focus:border-rose-500' : 'border-line focus:border-brand'; @endphp
<select
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->merge(['class' => "w-full rounded-xl bg-surface-raised text-ink border $ring px-4 py-2.5 text-sm outline-none transition-colors appearance-none bg-no-repeat"]) }}
>{{ $slot }}</select>
