@props([
    'id',
    'title',
    'description' => null,
    'class' => '',
])

<section
    {{ $attributes->merge([
        'class' => trim('rounded-2xl border border-gintly-border bg-white p-5 sm:p-6 '.$class),
    ]) }}
    data-dashboard-section="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    aria-busy="true"
>
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="{{ $id }}-title" class="text-sm font-semibold text-gintly-text-primary">
                {{ $title }}
            </h2>
            @if ($description)
                <p class="mt-1 text-sm leading-5 text-gintly-text-secondary">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="shrink-0">{{ $actions }}</div>
        @endisset
    </header>

    <div class="mt-5 space-y-3" data-section-loading>
        <span class="sr-only">Cargando {{ Str::lower($title) }}</span>
        <div class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
        <div class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
        <div class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
    </div>

    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" data-section-error role="alert" hidden>
        <p data-section-error-message>No fue posible cargar esta sección.</p>
        <button
            type="button"
            class="mt-3 min-h-11 rounded-xl border border-red-300 bg-white px-4 py-2 font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
            data-dashboard-retry="{{ $id }}"
        >
            Reintentar
        </button>
    </div>

    <p class="mt-5 rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-gintly-text-secondary" data-section-empty hidden>
        No hay datos disponibles para esta sección.
    </p>

    <div class="mt-5" data-section-content hidden>
        {{ $slot }}
    </div>
</section>
