@extends('layouts.panel')

@section('document-title', 'Mis aperturas y cierres')
@section('page-title', 'Mis aperturas y cierres')
@section('breadcrumb-root', 'Mi caja')
@section('breadcrumb-current', 'Historial')
@section('page-script', 'operations/cash-history')

@section('content')
<section data-cash-history class="space-y-6" aria-labelledby="cash-history-title" aria-busy="true">
    <header class="rounded-3xl bg-gintly-sidebar p-6 text-white sm:p-8"><p class="text-sm font-semibold text-gintly-active">Evidencia inmutable</p><h1 id="cash-history-title" class="mt-2 text-3xl font-bold">Mis aperturas y cierres</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Cada registro corresponde a una sesión propia. Apertura y cierre se presentan por separado; las diferencias solo aparecen tras el cierre.</p></header>
    <p data-cash-notice tabindex="-1" role="alert" hidden class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm"></p>
    <form data-cash-filters class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 xl:grid-cols-3 xl:items-end">
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Mostrar<select name="view" class="min-h-11 min-w-0 rounded-xl border border-slate-300 px-3"><option value="all">Todas</option><option value="openings">Aperturas</option><option value="closings">Cierres</option><option value="open">Abiertas</option><option value="unbalanced">Descuadradas</option></select></label>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Desde la apertura<input name="from" type="date" class="min-h-11 min-w-0 rounded-xl border border-slate-300 px-3"></label>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Hasta la apertura<input name="to" type="date" class="min-h-11 min-w-0 rounded-xl border border-slate-300 px-3"></label>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Caja<select name="cash_register_id" class="min-h-11 min-w-0 rounded-xl border border-slate-300 px-3"><option value="">Todas las cajas</option></select></label>
        <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-50">Filtrar</button>
        <p class="text-sm leading-6 text-gintly-text-secondary sm:col-span-2 xl:col-span-3">El período filtra la fecha de apertura de las sesiones. Cierres y descuadres se ordenan por fecha de cierre.</p>
    </form>
    <p data-cash-loading role="status" class="rounded-2xl border border-slate-200 bg-white p-6">Cargando historial…</p>
    <p data-cash-empty hidden class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-gintly-text-secondary">No hay sesiones para la vista y los filtros seleccionados.</p>
    <div data-cash-results hidden class="space-y-3">
        <div data-cash-cards class="grid min-w-0 gap-4"></div>
        <nav aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-4"><button data-cash-prev type="button" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 disabled:opacity-40">Anterior</button><span data-cash-page role="status" class="text-sm"></span><button data-cash-next type="button" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 disabled:opacity-40">Siguiente</button></nav>
    </div>
    <button data-cash-retry type="button" hidden class="min-h-11 rounded-xl border border-slate-300 bg-white px-5">Reintentar</button>
    <dialog data-cash-detail-dialog aria-labelledby="cash-history-detail-title" class="m-auto max-h-[94dvh] w-[min(760px,calc(100%-2rem))] overflow-y-auto rounded-3xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60"><section class="space-y-5 p-5 sm:p-8" aria-busy="false"><header class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold text-gintly-brand">Historial inmutable</p><h2 id="cash-history-detail-title" class="text-xl font-bold">Detalle de sesión</h2></div><button data-cash-detail-close type="button" class="grid size-11 place-items-center rounded-xl border border-slate-300" aria-label="Cerrar detalle">✕</button></header><p data-cash-detail-state role="status">Cargando…</p><div data-cash-detail-content hidden><dl data-cash-detail-fields class="grid gap-3 text-sm sm:grid-cols-2"></dl><section class="mt-5"><h3 class="font-semibold">Denominaciones de cierre</h3><ul data-cash-detail-denominations class="mt-2 divide-y divide-slate-200"></ul></section><section class="mt-5"><h3 class="font-semibold">Arqueos independientes</h3><p data-cash-detail-counts-state role="status" class="mt-2 text-sm">Cargando…</p><ol data-cash-detail-counts class="mt-2 divide-y divide-slate-200"></ol><button data-cash-detail-counts-retry type="button" hidden class="mt-3 min-h-11 rounded-xl border px-4">Reintentar arqueos</button></section><section class="mt-5"><h3 class="font-semibold">Movimientos</h3><ul data-cash-detail-movements class="mt-2 divide-y divide-slate-200"></ul><button data-cash-detail-more type="button" hidden class="mt-3 min-h-11 rounded-xl border px-4">Cargar más movimientos</button></section></div></section></dialog>
</section>
@endsection
