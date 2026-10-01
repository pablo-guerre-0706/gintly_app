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

    <form class="flex flex-col gap-4 rounded-2xl border border-gintly-border bg-white p-5 shadow-sm sm:flex-row sm:items-end" data-cash-filters>
        <label class="grid min-w-0 flex-1 gap-2 text-sm font-semibold">Estado
            <select class="min-h-11 rounded-xl border border-gintly-border px-3" name="status"><option value="">Todos</option><option value="abierta">Abierta</option><option value="cerrada">Cerrada</option><option value="descuadrada">Descuadrada</option></select>
        </label>
        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-cash-filter-submit><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrar</button>
    </form>

    <div class="rounded-2xl border border-gintly-border bg-white p-6" data-cash-loading role="status">Cargando sesiones…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900" data-cash-error role="alert" hidden><p data-cash-error-message></p><button class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 text-sm font-semibold" type="button" data-cash-retry>Reintentar</button></div>
    <p class="rounded-2xl border border-gintly-border bg-white p-8 text-center text-sm text-gintly-text-secondary" data-cash-empty hidden>No hay sesiones para el filtro seleccionado.</p>

    <div class="overflow-hidden rounded-2xl border border-gintly-border bg-white shadow-sm" data-cash-content hidden>
        <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-sm"><caption class="sr-only">Sesiones de caja del negocio</caption><thead class="border-b bg-slate-50"><tr><th class="px-5 py-4">Caja</th><th class="px-5 py-4">Responsable</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Apertura</th><th class="px-5 py-4">Cierre</th><th class="px-5 py-4"><span class="sr-only">Acciones</span></th></tr></thead><tbody class="divide-y divide-slate-200" data-cash-body></tbody></table></div>
        <nav class="flex items-center justify-between gap-4 border-t px-4 py-3" aria-label="Paginación de sesiones"><button class="min-h-11 rounded-xl border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-cash-previous>Anterior</button><p class="text-sm text-gintly-text-secondary" data-cash-page></p><button class="min-h-11 rounded-xl border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-cash-next>Siguiente</button></nav>
    </div>

    <dialog class="m-auto w-[min(720px,calc(100%-2rem))] rounded-3xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60" data-cash-dialog aria-labelledby="cash-close-title">
        <form class="max-h-[90dvh] overflow-y-auto p-6 sm:p-8" data-cash-close-form>
            <div class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-wide text-gintly-brand">Excepción administrativa</p><h2 id="cash-close-title" class="mt-1 text-xl font-bold">Cierre administrativo</h2><p class="mt-2 text-sm text-gintly-text-secondary" data-cash-close-context></p></div><button class="grid size-11 shrink-0 place-items-center rounded-xl hover:bg-slate-100" type="button" data-cash-dialog-close aria-label="Cancelar cierre"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">Arqueo ciego: el monto esperado y la diferencia permanecen ocultos hasta que el Backend persista el cierre.</div>
            <fieldset class="mt-6"><legend class="font-semibold">Desglose contado</legend><div class="mt-3 space-y-3" data-denomination-list></div><button class="mt-3 min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold" type="button" data-denomination-add>Agregar denominación</button><p class="mt-2 text-sm text-red-700" data-error-for="counted_denominations" hidden></p></fieldset>
            <div class="mt-5 rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-gintly-text-secondary">Efectivo contado</p><output class="mt-1 block text-2xl font-bold" data-counted-amount>C$0.00</output></div>
            <label class="mt-5 grid gap-2 text-sm font-semibold">Notas administrativas
                <textarea class="min-h-28 rounded-xl border border-gintly-border p-3" name="closing_notes" maxlength="500" required aria-describedby="closing-notes-error"></textarea>
            </label><p id="closing-notes-error" class="mt-1 text-sm text-red-700" data-error-for="closing_notes" hidden></p>
            <label class="mt-5 flex items-start gap-3 text-sm"><input class="mt-1 size-5" type="checkbox" name="confirmation" required><span>Confirmo que este es un cierre de contingencia, que el conteo es definitivo y que el resultado puede generar una anomalía.</span></label>
            <p class="mt-3 text-sm text-red-700" data-cash-close-error role="alert" hidden></p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button class="min-h-11 rounded-xl border px-5 text-sm font-semibold" type="button" data-cash-dialog-cancel>Cancelar</button><button class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-cash-close-submit>Confirmar cierre administrativo</button></div>
        </form>
    </dialog>
</section>
@endsection
