@props([
    'type' => 'info',
    'text',
])

@php
    $classes = match ($type) {
        'danger'  => 'bg-red-50 text-red-700 ring-red-600/10',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/10',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/10',
        'info'    => 'bg-blue-50 text-blue-700 ring-blue-600/10',
        default   => 'bg-blue-50 text-blue-700 ring-blue-600/10',
    };
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center rounded-full px-2.5 py-1',
        'text-xs font-medium leading-none',
        'ring-1 ring-inset',
        $classes,
    ]) }}
>
    {{ $text }}
</span>
