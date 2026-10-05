@extends('layouts.panel')

@section('document-title', 'Sesiones de caja')
@section('page-title', 'Sesiones de caja')
@section('breadcrumb-root', 'Caja y finanzas')
@section('breadcrumb-current', 'Sesiones y cierres')
@section('page-script', 'administration/cash-sessions')

@section('content')
<section class="space-y-6" data-admin-cash-sessions aria-labelledby="cash-sessions-title" aria-busy="true">
    <header class="rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Supervisión y contingencia</p>
        <h1 id="cash-sessions-title" class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Sesiones de caja</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary sm:text-base">Consulta sesiones del negocio. El cierre administrativo está disponible únicamente para contingencias y mantiene el arqueo ciego hasta persistir el resultado.</p>
    </header>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950" data-cash-result role="status" tabindex="-1" hidden></div>

    <form class="grid gap-5 rounded-2xl border border-gintly-border bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-3 xl:items-end" data-cash-filters>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Mostrar
            <select class="min-h-11 min-w-0 rounded-xl border border-gintly-border px-3" name="view"><option value="all">Todas</option><option value="openings">Aperturas</option><option value="closings">Cierres</option><option value="open">Abiertas</option><option value="unbalanced">Descuadradas</option></select>
        </label>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Desde la apertura<input class="min-h-11 min-w-0 rounded-xl border border-gintly-border px-3" type="date" name="from"></label>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Hasta la apertura<input class="min-h-11 min-w-0 rounded-xl border border-gintly-border px-3" type="date" name="to"></label>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Caja<select class="min-h-11 w-full min-w-0 rounded-xl border border-gintly-border px-3" name="cash_register_id" data-cash-register-filter><option value="">Todas las cajas</option></select></label>
        <label class="grid min-w-0 gap-2 text-sm font-semibold">Usuario<select class="min-h-11 w-full min-w-0 rounded-xl border border-gintly-border px-3" name="opened_by" data-cash-user-filter><option value="">Todos los usuarios</option></select></label>
        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-cash-filter-submit><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrar</button>
    </form>
    <p class="text-sm text-gintly-text-secondary" data-cash-filter-note role="status">Cargando cajas y usuarios para filtrar…</p>

    <div class="rounded-2xl border border-gintly-border bg-white p-6" data-cash-loading role="status">Cargando sesiones…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900" data-cash-error role="alert" hidden><p data-cash-error-message></p><button class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 text-sm font-semibold" type="button" data-cash-retry>Reintentar</button></div>
    <p class="rounded-2xl border border-gintly-border bg-white p-8 text-center text-sm text-gintly-text-secondary" data-cash-empty hidden>No hay sesiones para el filtro seleccionado.</p>

    <div class="space-y-4" data-cash-content hidden>
        <div data-cash-cards class="grid min-w-0 gap-4"></div>
        <nav class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gintly-border bg-white p-4" aria-label="Paginación de sesiones"><button class="min-h-11 rounded-xl border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-cash-previous>Anterior</button><p class="text-sm text-gintly-text-secondary" role="status" data-cash-page></p><button class="min-h-11 rounded-xl border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-cash-next>Siguiente</button></nav>
    </div>

    <dialog class="m-auto max-h-[94dvh] w-[min(760px,calc(100%-2rem))] overflow-y-auto rounded-3xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60" data-cash-detail-dialog aria-labelledby="cash-detail-title">
        <section class="space-y-5 p-5 sm:p-8" aria-busy="false">
            <header class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-wide text-gintly-brand">Historial inmutable</p><h2 id="cash-detail-title" class="mt-1 text-xl font-bold">Detalle de sesión</h2></div><button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl border border-gintly-border" data-cash-detail-close aria-label="Cerrar detalle">✕</button></header>
            <p data-cash-detail-state role="status">Cargando detalle…</p>
            <div data-cash-detail-content hidden>
                <dl class="grid gap-3 text-sm sm:grid-cols-2" data-cash-detail-fields></dl>
                <section class="mt-5" aria-labelledby="cash-detail-denominations-title"><h3 id="cash-detail-denominations-title" class="font-semibold">Denominaciones contadas</h3><ul class="mt-2 divide-y divide-slate-200 rounded-xl border border-slate-200" data-cash-detail-denominations></ul></section>
                <section class="mt-5" aria-labelledby="cash-detail-counts-title"><h3 id="cash-detail-counts-title" class="font-semibold">Arqueos independientes</h3><p class="mt-1 text-sm text-gintly-text-secondary">Historial inmutable. Un arqueo no cierra la sesión ni genera anomalía.</p><p data-cash-detail-counts-state role="status" class="mt-2 text-sm text-gintly-text-secondary">Cargando arqueos…</p><ol data-cash-detail-counts class="mt-2 divide-y divide-slate-200 rounded-xl border border-slate-200" hidden></ol><button type="button" data-cash-detail-counts-retry hidden class="mt-3 min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold">Reintentar arqueos</button></section>
                <section class="mt-5" aria-labelledby="cash-detail-movements-title"><h3 id="cash-detail-movements-title" class="font-semibold">Movimientos vinculados</h3><ul class="mt-2 divide-y divide-slate-200 rounded-xl border border-slate-200" data-cash-detail-movements></ul><button type="button" class="mt-3 min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold" data-cash-detail-more hidden>Cargar más movimientos</button></section>
            </div>
        </section>
    </dialog>

    <dialog class="m-auto max-h-[94dvh] w-[min(1000px,calc(100%-2rem))] overflow-y-auto rounded-3xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60" data-cash-dialog aria-labelledby="cash-close-title">
        <form class="max-h-[90dvh] overflow-y-auto p-6 sm:p-8" data-cash-close-form>
            <div class="flex items-start justify-between gap-4"><div><p data-cash-dialog-eyebrow class="text-sm font-semibold uppercase tracking-wide text-gintly-brand">Excepción administrativa</p><h2 id="cash-close-title" data-cash-dialog-title class="mt-1 text-xl font-bold">Cierre administrativo</h2><p class="mt-2 text-sm text-gintly-text-secondary" data-cash-close-context></p></div><button class="grid size-11 shrink-0 place-items-center rounded-xl hover:bg-slate-100" type="button" data-cash-dialog-close aria-label="Cerrar diálogo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
            <div data-cash-dialog-help class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">Arqueo ciego: el monto esperado y la diferencia permanecen ocultos hasta que el Backend persista el cierre.</div>
            <div class="mt-6">@include('operations.partials.cash-grid')<p class="mt-2 text-sm text-red-700" data-error-for="counted_denominations" hidden></p><p class="mt-2 text-sm text-red-700" data-error-for="counted_denominations_usd" hidden></p></div>
            <label data-cash-close-notes-block class="mt-5 grid gap-2 text-sm font-semibold">Notas administrativas
                <textarea class="min-h-28 rounded-xl border border-gintly-border p-3" name="closing_notes" maxlength="500" required aria-describedby="closing-notes-error"></textarea>
            </label><p id="closing-notes-error" class="mt-1 text-sm text-red-700" data-error-for="closing_notes" hidden></p>
            <label data-cash-close-confirm-block class="mt-5 flex items-start gap-3 text-sm"><input class="mt-1 size-5" type="checkbox" name="confirmation" required><span>Confirmo que este es un cierre de contingencia, que el conteo es definitivo y que el resultado puede generar una anomalía.</span></label>
            <p class="mt-3 text-sm text-red-700" data-cash-close-error role="alert" hidden></p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button class="min-h-11 rounded-xl border px-5 text-sm font-semibold" type="button" data-cash-dialog-cancel>Cancelar</button><button class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-cash-close-submit>Confirmar cierre administrativo</button></div>
        </form>
    </dialog>
</section>
@endsection
