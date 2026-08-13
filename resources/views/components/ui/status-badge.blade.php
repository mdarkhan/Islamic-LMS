@props(['status'])
@php
    $map = [
        'active' => ['success', __('admin.status_active')],
        'suspended' => ['warning', __('admin.status_suspended')],
        'archived' => ['neutral', __('admin.status_archived')],
    ];
    [$color, $label] = $map[$status] ?? ['neutral', $status];
@endphp
<x-ui.badge :color="$color">{{ $label }}</x-ui.badge>
