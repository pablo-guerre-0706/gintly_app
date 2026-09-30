@extends('layouts.panel')

@section('document-title', 'Sucursales')
@section('page-title', 'Sucursales')
@section('breadcrumb-root', 'Personal y organización')
@section('breadcrumb-current', 'Sucursales')
@section('page-script', 'organization/branches/index')

@section('content')
<section
    class="space-y-6"
    data-branches-root
    data-branches-endpoint="/branches"
    aria-labelledby="branches-title"
>
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Personal y organización</p>
        <h1 id="branches-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Sucursales</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-gintly-text-secondary">Consulta las sucursales del negocio disponibles para la asignación operativa.</p>
    </header>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" aria-labelledby="branches-list-title">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:p-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 id="branches-list-title" class="text-lg font-bold text-gintly-text-primary">Directorio de sucursales</h2>
                <p class="mt-1 text-sm text-gintly-text-secondary" data-branches-summary aria-live="polite">Cargando sucursales…</p>
            </div>
            <form class="w-full lg:max-w-md" data-branches-search novalidate>
                <label for="branches-search" class="mb-2 block text-sm font-semibold text-gintly-text-primary">Buscar por nombre o dirección</label>
                <div class="flex gap-2">
                    <input id="branches-search" name="search" type="search" minlength="2" maxlength="150" autocomplete="off" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" placeholder="Mínimo 2 caracteres">
                    <button type="submit" class="min-h-11 rounded-xl border border-gintly-brand px-4 text-sm font-bold text-gintly-brand transition hover:bg-gintly-brand/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand">Buscar</button>
                </div>
                <p class="mt-2 text-sm text-red-700" data-branches-search-error role="alert" hidden></p>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-5 py-4 font-bold sm:px-6">Sucursal</th>
                        <th scope="col" class="px-5 py-4 font-bold">Dirección</th>
                        <th scope="col" class="px-5 py-4 font-bold">Responsable</th>
                        <th scope="col" class="px-5 py-4 font-bold">Apertura</th>
                        <th scope="col" class="px-5 py-4 font-bold sm:px-6">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" data-branches-body></tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-5 py-5 sm:px-6" data-branches-state role="status">
            <p class="text-sm text-gintly-text-secondary">Cargando sucursales…</p>
        </div>

        <nav class="flex items-center justify-between gap-4 border-t border-slate-200 px-5 py-4 sm:px-6" data-branches-pagination aria-label="Paginación de sucursales" hidden>
            <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50" data-page-previous>Anterior</button>
            <span class="text-sm text-gintly-text-secondary" data-page-label></span>
            <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50" data-page-next>Siguiente</button>
        </nav>
    </section>
</section>
@endsection
