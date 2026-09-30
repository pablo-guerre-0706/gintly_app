@extends('layouts.panel')

@section('document-title', $reportTitle)
@section('page-title', $reportTitle)
@section('breadcrumb-root', $breadcrumbRoot)
@section('breadcrumb-current', $reportTitle)
@section('page-script', 'reports/summary')

@section('content')
<section
    class="space-y-6"
    data-report-summary
    data-report-type="{{ $reportType }}"
    aria-labelledby="report-summary-title"
>
    <header class="rounded-3xl border border-gintly-border bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">
            {{ $reportType === 'inventario' ? 'Vista consolidada del negocio' : 'Vista directiva' }}
        </p>
        <h1 id="report-summary-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">
            {{ $reportTitle }}
        </h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-gintly-text-secondary sm:text-base">
            {{ $reportDescription }}
        </p>
    </header>

    <form class="rounded-2xl border border-gintly-border bg-white p-5 shadow-sm" data-report-filters>
        <div @class([
            'grid gap-4 md:grid-cols-2 xl:items-end',
            'xl:grid-cols-[1fr_1fr_auto]' => $reportType === 'inventario',
            'xl:grid-cols-[1fr_1fr_1.2fr_auto]' => $reportType !== 'inventario',
        ])>
            <label class="grid gap-2 text-sm font-semibold text-gintly-text-primary">
                Desde
                <input class="min-h-11 rounded-xl border border-gintly-border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" type="date" name="from" required>
            </label>
            <label class="grid gap-2 text-sm font-semibold text-gintly-text-primary">
                Hasta
                <input class="min-h-11 rounded-xl border border-gintly-border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" type="date" name="to" required>
            </label>
            @if ($reportType !== 'inventario')
                <label class="grid gap-2 text-sm font-semibold text-gintly-text-primary">
                    Sucursal
                    <select class="min-h-11 rounded-xl border border-gintly-border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" name="branch_id" data-branch-filter>
                        <option value="">Todas las sucursales</option>
                    </select>
                </label>
            @endif
            <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand disabled:cursor-wait disabled:opacity-60" type="submit" data-report-submit>
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                <span data-submit-label>Consultar</span>
            </button>
        </div>
        <div class="mt-4 flex flex-wrap gap-2" aria-label="Períodos rápidos">
            <button class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold text-gintly-brand hover:bg-gintly-brand/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" type="button" data-report-period="day">Hoy</button>
            <button class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold text-gintly-brand hover:bg-gintly-brand/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" type="button" data-report-period="week">Esta semana</button>
            <button class="min-h-11 rounded-xl border border-gintly-border px-4 text-sm font-semibold text-gintly-brand hover:bg-gintly-brand/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand" type="button" data-report-period="month">Este mes</button>
        </div>
        <p class="mt-3 text-sm text-red-700" data-report-filter-error role="alert" hidden></p>
    </form>

    <div class="rounded-2xl border border-gintly-border bg-white p-6" data-report-loading role="status">
        <div class="flex items-center gap-3 text-sm text-gintly-text-secondary">
            <i class="fa-solid fa-circle-notch fa-spin text-gintly-brand" aria-hidden="true"></i>
            <span>Cargando información consolidada…</span>
        </div>
    </div>

    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900" data-report-error role="alert" hidden>
        <h2 class="font-semibold">No fue posible cargar el reporte</h2>
        <p class="mt-2 text-sm" data-report-error-message></p>
        <button class="mt-4 min-h-11 rounded-xl border border-red-300 bg-white px-4 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300" type="button" data-report-retry>Reintentar</button>
    </div>

    <div class="rounded-2xl border border-gintly-border bg-white p-8 text-center text-sm text-gintly-text-secondary" data-report-empty hidden>
        No hay datos para el período seleccionado.
    </div>

    <div class="space-y-6" data-report-content hidden>
        <section class="rounded-2xl border border-gintly-border bg-white p-5 shadow-sm sm:p-6" aria-labelledby="report-period-title">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="report-period-title" class="font-semibold text-gintly-text-primary">Período consultado</h2>
                    <p class="mt-1 text-sm text-gintly-text-secondary" data-report-period-label></p>
                </div>
                <p class="text-sm text-gintly-text-secondary" data-report-generated></p>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-report-totals aria-label="Totales del reporte"></section>

        <section class="rounded-2xl border border-gintly-border bg-white p-5 shadow-sm sm:p-6" data-report-series-section hidden aria-labelledby="report-series-title">
            <h2 id="report-series-title" class="font-semibold text-gintly-text-primary">Evolución diaria</h2>
            <div class="mt-5" data-report-series></div>
        </section>
    </div>
</section>
@endsection
