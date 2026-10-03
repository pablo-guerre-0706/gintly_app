@extends('layouts.panel')

@section('document-title', $pageTitle)
@section('page-title', $pageTitle)
@section('breadcrumb-root', $breadcrumbRoot)
@section('breadcrumb-current', $pageTitle)
@section('page-script', 'operations/resource-list')

@section('content')
<section
    class="space-y-6"
    data-operative-resource-list
    data-resource-type="{{ $resourceType }}"
    aria-labelledby="operative-resource-title"
    aria-busy="true"
>
    <header>
        <p class="text-sm font-semibold text-gintly-brand">Sucursal asignada</p>
        <h1 id="operative-resource-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">{{ $pageTitle }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary">{{ $pageDescription }}</p>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="operative-list-title">
        <header class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
            <div>
                <h2 id="operative-list-title" class="font-semibold text-gintly-text-primary">Detalle operativo</h2>
                <p class="mt-1 text-sm text-gintly-text-secondary" data-operative-summary aria-live="polite">Cargando…</p>
            </div>
            <label class="w-full sm:max-w-sm" data-operative-search-wrap hidden>
                <span class="sr-only" data-operative-search-label>Buscar</span>
                <input type="search" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" data-operative-search>
            </label>
        </header>

        <div class="p-5 sm:p-6" data-operative-state role="status">Cargando información…</div>
        <div class="max-w-full overflow-x-auto" data-operative-table-wrap hidden>
            <table class="min-w-[720px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-gintly-text-secondary"><tr data-operative-head></tr></thead>
                <tbody class="divide-y divide-slate-200" data-operative-body></tbody>
            </table>
        </div>
        <nav class="flex items-center justify-between gap-4 border-t border-slate-200 px-5 py-4" data-operative-pagination aria-label="Paginación" hidden>
            <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold disabled:opacity-50" data-page-direction="previous">Anterior</button>
            <span class="text-sm text-gintly-text-secondary" data-page-label></span>
            <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold disabled:opacity-50" data-page-direction="next">Siguiente</button>
        </nav>
    </section>

</section>
@endsection
