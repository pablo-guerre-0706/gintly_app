@extends('layouts.panel')

@section('document-title', $mode === 'create' ? 'Crear sucursal' : 'Editar sucursal')
@section('page-title', $mode === 'create' ? 'Crear sucursal' : 'Editar sucursal')
@section('breadcrumb-root', 'Sucursales')
@section('breadcrumb-current', $mode === 'create' ? 'Crear sucursal' : 'Editar sucursal')
@section('page-script', 'organization/branches/form')

@section('content')
<section class="mx-auto max-w-4xl space-y-6" data-branch-form-root data-mode="{{ $mode }}"
    data-branch-id="{{ $mode === 'edit' ? request()->route('branch') : '' }}"
    data-branches-endpoint="/branches" data-users-endpoint="/users"
    data-index-url="{{ route('panel.branches.index') }}" aria-labelledby="branch-form-title">
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Personal y organización</p>
        <h1 id="branch-form-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">{{ $mode === 'create' ? 'Crear sucursal' : 'Editar sucursal' }}</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Asigna un responsable activo y registra los datos de acreditación de la sucursal.</p>
    </header>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 text-sm text-gintly-text-secondary" data-branch-loading role="status">Cargando datos autorizados…</div>
    <div class="rounded-3xl border border-red-200 bg-red-50 p-6 text-sm text-red-900" data-branch-fatal role="alert" hidden>
        <p data-branch-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl bg-red-900 px-4 font-bold text-white" data-branch-retry>Reintentar</button>
    </div>

    <form class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-branch-form novalidate hidden>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="branch-name" class="mb-2 block text-sm font-bold">Nombre de la sucursal</label>
                <input id="branch-name" name="name" type="text" required maxlength="150" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" aria-describedby="branch-name-error">
                <p id="branch-name-error" class="mt-2 text-sm text-red-700" data-error-for="name" role="alert" hidden></p>
            </div>
            <div class="sm:col-span-2">
                <label for="branch-address" class="mb-2 block text-sm font-bold">Dirección física</label>
                <input id="branch-address" name="address" type="text" required maxlength="255" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" aria-describedby="branch-address-error">
                <p id="branch-address-error" class="mt-2 text-sm text-red-700" data-error-for="address" role="alert" hidden></p>
            </div>
            <div>
                <label for="branch-manager" class="mb-2 block text-sm font-bold">Responsable</label>
                <select id="branch-manager" name="manager_user_id" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" aria-describedby="branch-manager-help branch-manager-error">
                    <option value="">Selecciona un usuario activo</option>
                </select>
                <p id="branch-manager-help" class="mt-2 text-xs text-gintly-text-secondary">Solo usuarios activos de este negocio.</p>
                <p id="branch-manager-error" class="mt-2 text-sm text-red-700" data-error-for="manager_user_id" role="alert" hidden></p>
            </div>
            <div>
                <label for="branch-opened" class="mb-2 block text-sm font-bold">Fecha de apertura</label>
                <input id="branch-opened" name="opened_at" type="date" required class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" aria-describedby="branch-opened-error">
                <p id="branch-opened-error" class="mt-2 text-sm text-red-700" data-error-for="opened_at" role="alert" hidden></p>
            </div>
        </div>
        <label class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold">
            <input name="is_active" type="checkbox" checked class="size-5 rounded border-slate-300 text-gintly-brand focus:ring-gintly-brand" aria-describedby="branch-active-error">
            Sucursal activa
        </label>
        <p id="branch-active-error" class="text-sm text-red-700" data-error-for="is_active" role="alert" hidden></p>
        <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" data-branch-error role="alert" tabindex="-1" hidden></p>
        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('panel.branches.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold">Cancelar</a>
            <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-bold text-white disabled:opacity-50" data-branch-submit>{{ $mode === 'create' ? 'Crear sucursal' : 'Guardar cambios' }}</button>
        </div>
    </form>
</section>
@endsection
