@props(['type' => 'info', 'title' => null])
@php
    $map = [
        'success' => ['bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-200', 'M5 13l4 4L19 7'],
        'error'   => ['bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-200', 'M6 18L18 6M6 6l12 12'],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-200', 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
        'info'    => ['bg-sky-50 border-sky-200 text-sky-800 dark:bg-sky-500/10 dark:border-sky-500/30 dark:text-sky-200', 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
    ];
    [$cls, $path] = $map[$type] ?? $map['info'];
@endphp
<div role="alert" {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border px-4 py-3 text-sm $cls"]) }}>
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
    <div class="space-y-0.5">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div class="opacity-90">{{ $slot }}</div>
    </div>
</div>
