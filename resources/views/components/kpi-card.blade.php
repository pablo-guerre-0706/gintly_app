@props([
    'title',
    'value',
    'subtext' => null,
    'icon' => null,
    'trend' => null,
    'trendType' => 'up',
])

@php
    $isUp = $trendType !== 'down';

    $trendClasses = $isUp
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/10'
        : 'bg-red-50 text-red-700 ring-red-600/10';
@endphp

<article
    {{ $attributes->class([
        'rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm'
    ]) }}
>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium leading-5 text-slate-500">
                {{ $title }}
            </p>

            <div class="mt-2 flex flex-wrap items-end gap-x-3 gap-y-2">
                <p class="text-2xl font-semibold tracking-tight text-slate-900">
                    {{ $value }}
                </p>

                @if ($trend)
                    <span
                        @class([
                            'inline-flex items-center gap-1 rounded-full px-2 py-1',
                            'text-xs font-semibold ring-1 ring-inset',
                            $trendClasses,
                        ])
                    >
                        <span aria-hidden="true">
                            {{ $isUp ? '↑' : '↓' }}
                        </span>

                        {{ $trend }}
                    </span>
                @endif
            </div>

            @if ($subtext)
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    {{ $subtext }}
                </p>
            @endif
        </div>

        @if ($icon)
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-600 ring-1 ring-slate-200/80"
                aria-hidden="true"
            >
                {!! $icon !!}
            </div>
        @endif
    </div>
</article>
