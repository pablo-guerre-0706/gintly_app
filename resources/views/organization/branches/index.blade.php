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
    data-users-endpoint="/users"
    data-create-url="{{ route('panel.branches.create') }}"
    data-edit-url-template="{{ url('/organization/branches/__BRANCH__/edit') }}"
    aria-labelledby="branches-title"
>
    <header class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Personal y organización</p>
            <h1 id="branches-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Sucursales</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-gintly-text-secondary">Administra las sucursales acreditadas del negocio y su asignación operativa.</p>
        </div>
        <a href="{{ route('panel.branches.create') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2" data-branches-create hidden>Crear sucursal</a>
    </header>

    <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900" data-branches-notice role="status" tabindex="-1" hidden></p>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" aria-labelledby="branches-list-title">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:p-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 id="branches-list-title" class="text-lg font-bold text-gintly-text-primary">Directorio de sucursales</h2>
                <p class="mt-1 text-sm text-gintly-text-secondary" data-branches-summary aria-live="polite">Cargando sucursales…</p>
            </div>
            <form class="grid w-full gap-3 sm:grid-cols-[minmax(0,1fr)_auto] lg:max-w-2xl" data-branches-search novalidate>
                <div>
                    <label for="branches-search" class="mb-2 block text-sm font-semibold text-gintly-text-primary">Buscar por nombre o dirección</label>
                    <div class="flex gap-2">
                        <input id="branches-search" name="search" type="search" minlength="2" maxlength="150" autocomplete="off" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" placeholder="Mínimo 2 caracteres">
                        <button type="submit" class="min-h-11 rounded-xl border border-gintly-brand px-4 text-sm font-bold text-gintly-brand transition hover:bg-gintly-brand/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand">Buscar</button>
                    </div>
                    <p class="mt-2 text-sm text-red-700" data-branches-search-error role="alert" hidden></p>
                </div>
                <div>
                    <label for="branches-status" class="mb-2 block text-sm font-semibold text-gintly-text-primary">Estado</label>
                    <select id="branches-status" name="is_active" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand">
                        <option value="">Todas</option><option value="1">Activas</option><option value="0">Inactivas</option>
                    </select>
                </div>
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
                        <th scope="col" class="px-5 py-4 font-bold sm:px-6">Acciones</th>
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
    <div id="branch-actions-menu" data-branch-menu role="menu" aria-label="Acciones de sucursal" hidden class="fixed z-[90] min-w-48 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
        <a data-branch-menu-edit role="menuitem" class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold hover:bg-slate-50" href="{{ route('panel.branches.index') }}">Editar</a>
        <button data-branch-toggle type="button" role="menuitem" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold hover:bg-slate-50">Desactivar</button>
        <button data-branch-delete type="button" role="menuitem" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold text-red-700 hover:bg-red-50">Eliminar</button>
    </div>
</section>
@endsection
