@extends('layouts.panel')

@section('document-title', 'Centro de anomalías')
@section('page-title', 'Anomalías')
@section('breadcrumb-root', 'Inteligencia y control')
@section('breadcrumb-current', 'Anomalías')
@section('page-script', 'administration/anomalies')

@section('content')
<section class="space-y-6" data-admin-anomalies aria-labelledby="anomalies-title" aria-busy="true">
    <header class="rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Supervisión administrativa</p>
        <h1 id="anomalies-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Centro de anomalías</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary sm:text-base">Consulta evidencia, eventos y justifica anomalías activas cuando el contrato lo autoriza. La resolución final permanece reservada al propietario.</p>
    </header>

    <form class="grid gap-4 rounded-2xl border border-gintly-border bg-white p-5 shadow-sm sm:grid-cols-[1fr_1fr_auto] sm:items-end" data-anomaly-filters>
        <label class="grid gap-2 text-sm font-semibold">Estado
            <select class="min-h-11 rounded-xl border border-gintly-border px-3" name="status">
                <option value="">Todos</option><option value="detectada">Detectada</option><option value="notificada">Notificada</option><option value="en_revision">En revisión</option><option value="justificada">Justificada</option><option value="resuelta">Resuelta</option>
            </select>
        </label>
        <label class="grid gap-2 text-sm font-semibold">Severidad
            <select class="min-h-11 rounded-xl border border-gintly-border px-3" name="severity">
                <option value="">Todas</option><option value="critica">Crítica</option><option value="advertencia">Advertencia</option><option value="informativa">Informativa</option>
            </select>
        </label>
        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-anomaly-filter-submit><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrar</button>
    </form>

    <div class="rounded-2xl border border-gintly-border bg-white p-6" data-anomaly-loading role="status">Cargando anomalías…</div>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900" data-anomaly-error role="alert" hidden><p data-anomaly-error-message></p><button class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 text-sm font-semibold" type="button" data-anomaly-retry>Reintentar</button></div>
    <p class="rounded-2xl border border-gintly-border bg-white p-8 text-center text-sm text-gintly-text-secondary" data-anomaly-empty hidden>No hay anomalías para los filtros seleccionados.</p>

    <div class="overflow-hidden rounded-2xl border border-gintly-border bg-white shadow-sm" data-anomaly-content hidden>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <caption class="sr-only">Anomalías del negocio</caption>
                <thead class="border-b border-gintly-border bg-slate-50"><tr><th class="px-5 py-4">Regla</th><th class="px-5 py-4">Severidad</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Diferencia</th><th class="px-5 py-4">Detectada</th><th class="px-5 py-4"><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody class="divide-y divide-slate-200" data-anomaly-body></tbody>
            </table>
        </div>
        <nav class="flex items-center justify-between gap-4 border-t border-gintly-border px-4 py-3" aria-label="Paginación de anomalías"><button class="min-h-11 rounded-xl border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-anomaly-previous>Anterior</button><p class="text-sm text-gintly-text-secondary" data-anomaly-page></p><button class="min-h-11 rounded-xl border px-4 text-sm font-semibold disabled:opacity-40" type="button" data-anomaly-next>Siguiente</button></nav>
    </div>

    <dialog class="m-auto w-[min(680px,calc(100%-2rem))] rounded-3xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60" data-anomaly-dialog aria-labelledby="anomaly-dialog-title">
        <div class="max-h-[88dvh] overflow-y-auto p-6 sm:p-8">
            <div class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-wide text-gintly-brand">Detalle contractual</p><h2 id="anomaly-dialog-title" class="mt-1 text-xl font-bold" data-anomaly-dialog-title>Anomalía</h2></div><button class="grid size-11 place-items-center rounded-xl hover:bg-slate-100" type="button" data-anomaly-dialog-close aria-label="Cerrar detalle"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
            <div class="mt-6 grid gap-3 sm:grid-cols-2" data-anomaly-detail></div>
            <section class="mt-6" aria-labelledby="anomaly-events-title"><h3 id="anomaly-events-title" class="font-semibold">Bitácora</h3><ul class="mt-3 space-y-3" data-anomaly-events></ul></section>
            <form class="mt-6 rounded-2xl border border-gintly-border bg-slate-50 p-5" data-anomaly-justify hidden>
                <h3 class="font-semibold">Justificar anomalía</h3>
                <p class="mt-1 text-sm text-gintly-text-secondary">La justificación es una transición auditada. No equivale a la resolución final.</p>
                <label class="mt-4 grid gap-2 text-sm font-semibold">Motivo
                    <textarea class="min-h-28 rounded-xl border border-gintly-border bg-white p-3" name="reason" maxlength="500" required aria-describedby="anomaly-reason-error"></textarea>
                </label>
                <p id="anomaly-reason-error" class="mt-1 text-sm text-red-700" data-error-for="reason" hidden></p>
                <label class="mt-4 flex items-start gap-3 text-sm"><input class="mt-1 size-5" type="checkbox" name="confirmation" required><span>Confirmo que el motivo quedará registrado en la bitácora de esta anomalía.</span></label>
                <p class="mt-3 text-sm text-red-700" data-anomaly-justify-error role="alert" hidden></p>
                <button class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-60" type="submit" data-anomaly-justify-submit>Confirmar justificación</button>
            </form>
        </div>
    </dialog>
</section>
@endsection
