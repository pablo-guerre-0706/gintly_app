@props([
    'title',
    'description',
    'items' => [],
    'note' => null,
])

<section
    class="space-y-6"
    data-hub-root
    aria-labelledby="hub-page-title"
>
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Panel Gintly</p>
        <h1 id="hub-page-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">
            {{ $title }}
        </h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary sm:text-base">
            {{ $description }}
        </p>
    </header>

    @if ($note)
        <aside class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm leading-6 text-sky-950">
            {{ $note }}
        </aside>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-hub-grid hidden>
        @foreach ($items as $item)
            @php
                $url = $item['url'] ?? null;
                $capabilities = implode(',', $item['capabilities'] ?? []);
                $anyCapabilities = implode(',', $item['any_capabilities'] ?? []);
                $roles = implode(',', $item['roles'] ?? []);
                $cardClasses = 'group min-h-48 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition';
            @endphp

            @if ($url)
                <a
                    href="{{ $url }}"
                    class="{{ $cardClasses }} hover:-translate-y-0.5 hover:border-gintly-brand/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2"
                    data-hub-item
                    data-capabilities="{{ $capabilities }}"
                    data-any-capabilities="{{ $anyCapabilities }}"
                    data-roles="{{ $roles }}"
                    hidden
                >
                    <span class="grid size-12 place-items-center rounded-2xl bg-gintly-brand/10 text-xl text-gintly-brand" aria-hidden="true">
                        <i class="fa-solid {{ $item['icon'] }}"></i>
                    </span>
                    <h2 class="mt-5 text-lg font-bold text-gintly-text-primary">{{ $item['title'] }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gintly-text-secondary">{{ $item['description'] }}</p>
                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-gintly-brand">
                        Abrir <i class="fa-solid fa-arrow-right text-xs transition-transform group-hover:translate-x-1" aria-hidden="true"></i>
                    </span>
                </a>
            @else
                <article
                    class="{{ $cardClasses }}"
                    data-hub-item
                    data-capabilities="{{ $capabilities }}"
                    data-any-capabilities="{{ $anyCapabilities }}"
                    data-roles="{{ $roles }}"
                    hidden
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid size-12 place-items-center rounded-2xl bg-slate-100 text-xl text-gintly-brand" aria-hidden="true">
                            <i class="fa-solid {{ $item['icon'] }}"></i>
                        </span>
                        @if (!empty($item['badge']))
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">{{ $item['badge'] }}</span>
                        @endif
                    </div>
                    <h2 class="mt-5 text-lg font-bold text-gintly-text-primary">{{ $item['title'] }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gintly-text-secondary">{{ $item['description'] }}</p>
                    <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ $item['status'] ?? 'Interfaz detallada pendiente' }}
                    </p>
                </article>
            @endif
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-gintly-text-secondary" data-hub-loading role="status">
        <i class="fa-solid fa-circle-notch fa-spin me-2" aria-hidden="true"></i>
        Validando módulos disponibles…
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center" data-hub-empty hidden>
        <i class="fa-solid fa-shield-halved text-2xl text-slate-400" aria-hidden="true"></i>
        <h2 class="mt-3 text-base font-bold text-gintly-text-primary">Sin módulos disponibles</h2>
        <p class="mt-2 text-sm text-gintly-text-secondary">Tu contexto actual no habilita funciones dentro de esta sección.</p>
    </div>
</section>
