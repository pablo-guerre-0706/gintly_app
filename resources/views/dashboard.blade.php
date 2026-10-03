@extends('layouts.panel')

@section('document-title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('breadcrumb-root', 'Gintly')
@section('breadcrumb-current', 'Dashboard')
@section('page-script', 'dashboard/index')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white p-6" data-dashboard-router-gate aria-live="polite">
    <div class="flex items-center gap-3 text-sm text-gintly-text-secondary">
        <i class="fa-solid fa-circle-notch fa-spin text-gintly-brand" aria-hidden="true"></i>
        <span>Validando acceso al dashboard…</span>
    </div>
</div>

<div data-owner-dashboard hidden>
    <div class="space-y-8" data-dashboard-content hidden>
        <section class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between" aria-labelledby="owner-dashboard-title">
            <div class="min-w-0">
                <h1 id="owner-dashboard-title" class="text-[32px]/10 font-semibold tracking-[-0.5px] text-gintly-text-primary">
                    Dashboard
                </h1>
                <p class="mt-3 text-sm/5 text-gintly-text-secondary" data-dashboard-context>
                    Vista consolidada del negocio
                </p>
            </div>

            <button
                type="button"
                class="inline-flex min-h-[54px] items-center justify-center gap-2 self-start rounded-2xl bg-gintly-brand px-6 text-sm font-semibold text-white transition hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand disabled:cursor-wait disabled:opacity-60 lg:self-center"
                data-dashboard-refresh
            >
                <i class="fa-solid fa-rotate" data-refresh-icon aria-hidden="true"></i>
                <span data-refresh-label>Actualizar</span>
            </button>
        </section>

        <x-dashboard.async-section
            id="kpis"
            title="Indicadores del negocio"
            description="Valores consolidados del período vigente."
        >
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-kpi-grid></div>
        </x-dashboard.async-section>

        <aside class="overflow-hidden rounded-3xl border border-gintly-border bg-white shadow-sm" aria-labelledby="nearby-suppliers-title">
            <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-start">
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-gintly-brand/10 text-2xl text-gintly-brand" aria-hidden="true">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 id="nearby-suppliers-title" class="text-xl font-bold text-gintly-text-primary">Explorar proveedores cercanos</h2>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900">Externo</span>
                        </div>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-gintly-text-secondary">
                            Descubre posibles proveedores de productos o servicios sin mezclarlos con el directorio interno ni aprobarlos automáticamente.
                        </p>
                    </div>
                </div>
                <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2" href="{{ route('panel.suppliers.explore') }}">
                    Ver disponibilidad
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </aside>

        <div class="grid gap-8 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
            <x-dashboard.async-section
                id="sales"
                title="Evolución de ventas"
                description="Facturación emitida durante el período consultado."
            >
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gintly-text-secondary">Venta total</p>
                        <p class="mt-1 text-2xl font-semibold text-gintly-text-primary" data-sales-total></p>
                    </div>
                    <p class="text-sm text-gintly-text-secondary" data-sales-period></p>
                </div>
                <div class="mt-6" data-sales-chart></div>
                <p class="sr-only" data-sales-summary></p>
            </x-dashboard.async-section>

            <x-dashboard.async-section
                id="cash"
                title="Estado de cajas"
                description="Sesiones abiertas actualmente en el negocio."
            >
                <ul class="divide-y divide-slate-200" data-cash-session-list></ul>
            </x-dashboard.async-section>
        </div>

        <div class="grid gap-8 xl:grid-cols-2">
            <x-dashboard.async-section
                id="inventory"
                title="Inventario consolidado"
                description="Vista consolidada del negocio: exactitud, desviaciones y faltantes del período."
            >
                <div data-inventory-report></div>
            </x-dashboard.async-section>

            <x-dashboard.async-section
                id="receivables"
                title="Exposición de cuentas por cobrar"
                description="Cartera de facturas a crédito emitidas durante el período."
            >
                <div data-receivables-report></div>
            </x-dashboard.async-section>
        </div>

        <x-dashboard.async-section
            id="alerts"
            title="Centro de alertas de anomalías"
            description="Anomalías activas priorizadas por severidad y fecha de detección."
        >
            <div class="mb-5 flex flex-wrap gap-2 text-xs font-semibold" data-alert-summary></div>
            <ul class="space-y-3" data-dashboard-alert-list></ul>
        </x-dashboard.async-section>
    </div>
</div>

@include('dashboard.admin')
@include('dashboard.operator')
@endsection
