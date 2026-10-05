@extends('layouts.panel')

@section('document-title', 'Bodegas')
@section('page-title', 'Bodegas')
@section('breadcrumb-root', 'Catálogo e inventario')
@section('breadcrumb-current', 'Bodegas')
@section('page-script', 'administration/warehouses')

@section('content')
<section data-warehouses-admin class="space-y-6" aria-labelledby="warehouses-title">
    <header class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-gintly-brand">Catálogo e inventario</p>
            <h1 id="warehouses-title" class="mt-2 text-2xl font-bold sm:text-3xl">Bodegas</h1>
            <p class="mt-2 text-sm text-gintly-text-secondary">Administra bodegas por sucursal. Solo una puede ser predeterminada en cada sucursal.</p>
        </div>
        <button type="button" data-warehouse-create class="min-h-11 shrink-0 rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white" hidden>Crear bodega</button>
    </header>

    <p data-warehouse-notice role="status" tabindex="-1" hidden class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"></p>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm" aria-labelledby="warehouses-list-title">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
            <div><h2 id="warehouses-list-title" class="text-lg font-bold">Directorio de bodegas</h2><p data-warehouse-total class="mt-1 text-sm text-gintly-text-secondary" aria-live="polite"></p></div>
            <form data-warehouse-filters class="grid gap-3 sm:grid-cols-2" novalidate>
                <div><label for="warehouse-filter-branch" class="block text-sm font-semibold">Sucursal</label><select id="warehouse-filter-branch" name="branch_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Todas</option></select></div>
                <div><label for="warehouse-filter-active" class="block text-sm font-semibold">Estado</label><select id="warehouse-filter-active" name="is_active" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="">Todos</option><option value="1">Activas</option><option value="0">Inactivas</option></select></div>
                <button type="submit" class="min-h-11 rounded-xl border border-gintly-brand px-4 text-sm font-bold text-gintly-brand sm:col-span-2">Aplicar filtros</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[690px] w-full text-left text-sm"><caption class="sr-only">Bodegas del negocio</caption><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th scope="col" class="p-4">Bodega</th><th scope="col" class="p-4">Sucursal</th><th scope="col" class="p-4">Predeterminada</th><th scope="col" class="p-4">Estado</th><th scope="col" class="p-4">Acciones</th></tr></thead><tbody data-warehouse-rows class="divide-y divide-slate-100"></tbody></table>
        </div>
        <div data-warehouse-state role="status" class="p-5 text-sm text-gintly-text-secondary">Cargando bodegas…</div>
        <nav data-warehouse-pages hidden aria-label="Paginación de bodegas" class="flex items-center justify-between gap-4 border-t border-slate-200 p-4"><button type="button" data-warehouse-prev class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm disabled:opacity-50">Anterior</button><span data-warehouse-page class="text-sm"></span><button type="button" data-warehouse-next class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm disabled:opacity-50">Siguiente</button></nav>
    </section>

    <section data-warehouse-form-region hidden class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="warehouse-form-title">
        <h2 id="warehouse-form-title" data-warehouse-form-title class="text-xl font-bold">Crear bodega</h2>
        <p class="mt-2 text-sm text-gintly-text-secondary">Cambiar la bodega predeterminada puede afectar las operaciones de esta sucursal.</p>
        <form data-warehouse-form novalidate class="mt-6 grid gap-5 sm:grid-cols-2">
            <p data-form-error-summary role="alert" tabindex="-1" hidden class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 sm:col-span-2"></p>
            <div><label for="warehouse-name" class="block text-sm font-semibold">Nombre · Obligatorio</label><input id="warehouse-name" name="name" required maxlength="120" autocomplete="off" aria-describedby="warehouse-name-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm"><p id="warehouse-name-error" data-error-for="name" class="mt-1 text-sm text-red-700"></p></div>
            <div><label for="warehouse-branch" class="block text-sm font-semibold">Sucursal · Obligatorio</label><select id="warehouse-branch" name="branch_id" required aria-describedby="warehouse-branch-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm"><option value="">Selecciona una sucursal</option></select><p id="warehouse-branch-error" data-error-for="branch_id" class="mt-1 text-sm text-red-700"></p></div>
            <label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input name="is_default" type="checkbox" class="size-5">Predeterminada de la sucursal</label>
            <label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input name="is_active" type="checkbox" class="size-5" checked>Activa</label>
            <div class="flex flex-wrap gap-3 sm:col-span-2"><button type="submit" data-warehouse-save class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white disabled:opacity-60">Guardar bodega</button><button type="button" data-warehouse-cancel class="min-h-11 rounded-xl border border-slate-300 px-5 text-sm font-semibold">Cancelar</button></div>
        </form>
    </section>
</section>
@endsection
