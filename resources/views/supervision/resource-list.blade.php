@extends('layouts.panel')

@section('document-title', $pageTitle)
@section('page-title', $pageTitle)
@section('breadcrumb-root', $breadcrumbRoot)
@section('breadcrumb-current', $pageTitle)
@section('page-script', 'supervision/resource-list')

@section('content')
<section class="space-y-6" data-supervision-list data-resource-type="{{ $resourceType }}" aria-labelledby="resource-list-title">
    <header class="rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Supervisión</p>
        <h1 id="resource-list-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">{{ $pageTitle }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary sm:text-base">{{ $pageDescription }}</p>
    </header>

    <form class="rounded-2xl border border-gintly-border bg-white p-5 shadow-sm" data-resource-filters>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <label class="grid min-w-0 flex-1 gap-2 text-sm font-semibold text-gintly-text-primary" data-search-field hidden>
                Buscar
                <input class="min-h-11 rounded-xl border border-gintly-border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" type="search" name="search" minlength="2" placeholder="Nombre o identificación fiscal">
            </label>
            <label class="grid min-w-0 flex-1 gap-2 text-sm font-semibold text-gintly-text-primary" data-filter-field hidden>
                <span data-filter-label>Filtro</span>
                <select class="min-h-11 rounded-xl border border-gintly-border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" data-filter-options>
                    <option value="" data-filter-placeholder>Todos</option>
                </select>
            </label>
            <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand disabled:opacity-60" type="submit" data-resource-submit>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                Consultar
            </button>
        </div>
    </form>

    <div class="rounded-2xl border border-gintly-border bg-white p-6" data-resource-loading role="status">
        <i class="fa-solid fa-circle-notch fa-spin me-2 text-gintly-brand" aria-hidden="true"></i>
        Cargando información…
    </div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900" data-resource-error role="alert" hidden>
        <p data-resource-error-message></p>
        <button class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 text-sm font-semibold" type="button" data-resource-retry>Reintentar</button>
    </div>
    <div class="rounded-2xl border border-gintly-border bg-white p-8 text-center text-sm text-gintly-text-secondary" data-resource-empty hidden>No hay registros para los filtros seleccionados.</div>

    <div class="overflow-hidden rounded-2xl border border-gintly-border bg-white shadow-sm" data-resource-content hidden>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <caption class="sr-only">{{ $pageTitle }}</caption>
                <thead class="border-b border-gintly-border bg-slate-50" data-resource-head></thead>
                <tbody class="divide-y divide-slate-200" data-resource-body></tbody>
            </table>
        </div>
        <nav class="flex items-center justify-between gap-4 border-t border-gintly-border px-4 py-3" aria-label="Paginación">
            <button class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-page-previous>Anterior</button>
            <p class="text-sm text-gintly-text-secondary" data-page-status></p>
            <button class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-page-next>Siguiente</button>
        </nav>
    </div>
</section>
@endsection
