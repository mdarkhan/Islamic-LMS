@props(['status'])
@php
    $map = [
        'active' => ['success', 'সক্রিয়'],
        'suspended' => ['warning', 'স্থগিত'],
        'archived' => ['neutral', 'সংরক্ষণাগার'],
    ];
    [$color, $label] = $map[$status] ?? ['neutral', $status];
@endphp
<x-ui.badge :color="$color">{{ $label }}</x-ui.badge>
