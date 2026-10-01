@extends('layouts.panel')

@section('document-title', 'Conciliaciones')
@section('page-title', 'Conciliaciones')
@section('breadcrumb-root', 'Inteligencia y control')
@section('breadcrumb-current', 'Conciliaciones')
@section('page-script', 'administration/reconciliations')

@section('content')
<section class="space-y-6" data-admin-reconciliations aria-labelledby="reconciliations-title" aria-busy="true">
    <header class="rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:p-8"><p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Control administrativo</p><h1 id="reconciliations-title" class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Conciliaciones</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary sm:text-base">Consulta corridas auditables y ejecuta una conciliación manual con alcance contractual.</p></header>

    <form class="rounded-2xl border border-gintly-border bg-white p-5 shadow-sm" data-reconciliation-create hidden>
        <h2 class="font-semibold">Nueva conciliación manual</h2><div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="grid gap-2 text-sm font-semibold">Alcance<select class="min-h-11 rounded-xl border px-3" name="scope" required><option value="">Selecciona un alcance</option><option value="caja">Caja</option><option value="inventario_bodega">Inventario y bodega</option><option value="compras_3way">Compras (3-way)</option><option value="integral">Integral</option></select></label>
            <label class="grid gap-2 text-sm font-semibold">Sucursal opcional<select class="min-h-11 rounded-xl border px-3" name="branch_id" data-reconciliation-branches><option value="">Todo el negocio</option></select></label>
        </div><p class="mt-1 text-sm text-red-700" data-error-for="scope" hidden></p><p class="mt-1 text-sm text-red-700" data-error-for="branch_id" hidden></p>
        <label class="mt-4 flex items-start gap-3 text-sm"><input class="mt-1 size-5" type="checkbox" name="confirmation" required><span>Confirmo que esta corrida puede generar anomalías reales y quedará registrada.</span></label>
        <p class="mt-3 text-sm text-red-700" data-reconciliation-create-error role="alert" hidden></p><button class="mt-5 min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-reconciliation-create-submit>Ejecutar conciliación manual</button>
    </form>

    <form class="flex flex-col gap-4 rounded-2xl border border-gintly-border bg-white p-5 shadow-sm sm:flex-row sm:items-end" data-reconciliation-filters><label class="grid min-w-0 flex-1 gap-2 text-sm font-semibold">Estado<select class="min-h-11 rounded-xl border px-3" name="status"><option value="">Todos</option><option value="en_proceso">En proceso</option><option value="completada">Completada</option><option value="fallida">Fallida</option></select></label><button class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-reconciliation-filter-submit>Filtrar</button></form>

    <div class="rounded-2xl border bg-white p-6" data-reconciliation-loading role="status">Cargando conciliaciones…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900" data-reconciliation-error role="alert" hidden><p data-reconciliation-error-message></p><button class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4" type="button" data-reconciliation-retry>Reintentar</button></div>
    <p class="rounded-2xl border bg-white p-8 text-center text-sm text-gintly-text-secondary" data-reconciliation-empty hidden>No hay conciliaciones para el filtro seleccionado.</p>
    <div class="overflow-hidden rounded-2xl border bg-white shadow-sm" data-reconciliation-content hidden><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><caption class="sr-only">Corridas de conciliación</caption><thead class="border-b bg-slate-50"><tr><th class="px-5 py-4">Alcance</th><th class="px-5 py-4">Tipo</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Sucursal</th><th class="px-5 py-4">Anomalías</th><th class="px-5 py-4">Inicio</th></tr></thead><tbody class="divide-y" data-reconciliation-body></tbody></table></div><nav class="flex items-center justify-between gap-4 border-t px-4 py-3" aria-label="Paginación de conciliaciones"><button class="min-h-11 rounded-xl border px-4 disabled:opacity-40" type="button" data-reconciliation-previous>Anterior</button><p class="text-sm text-gintly-text-secondary" data-reconciliation-page></p><button class="min-h-11 rounded-xl border px-4 disabled:opacity-40" type="button" data-reconciliation-next>Siguiente</button></nav></div>
</section>
@endsection
