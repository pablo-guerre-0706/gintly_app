@extends('layouts.panel')

@section('title', 'Dashboard')
@section('page-script', 'modules/dashboard/index')

@section('content')
@php
$kpis = [
    ['key'=>'sales_today','title'=>'Ventas del día','icon'=>'<i class="fa-solid fa-chart-line"></i>','class'=>'xl:col-start-1 xl:col-span-4 xl:row-start-1'],
    ['key'=>'average_ticket','title'=>'Ticket promedio','icon'=>'<i class="fa-solid fa-receipt"></i>','class'=>'xl:col-start-5 xl:col-span-4 xl:row-start-1'],
    ['key'=>'overdue_receivables','title'=>'CxC Vencida +60 días','icon'=>'<i class="fa-solid fa-money-bill-wave"></i>','class'=>'xl:col-start-9 xl:col-span-4 xl:row-start-1'],
    ['key'=>'inventory_variance','title'=>'Descuadre Inv.','icon'=>'<i class="fa-solid fa-boxes-stacked"></i>','class'=>'xl:col-start-1 xl:col-span-6 xl:row-start-2'],
    ['key'=>'active_alerts','title'=>'Alertas activas','icon'=>'<i class="fa-solid fa-triangle-exclamation"></i>','class'=>'xl:col-start-7 xl:col-span-6 xl:row-start-2'],
    ['key'=>'gross_margin','title'=>'Margen bruto','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-4'],
    ['key'=>'returns','title'=>'Devoluciones','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-5'],
    ['key'=>'pending_purchases','title'=>'Compras p. de aprobación','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-6'],
];
@endphp

<main class="mx-auto w-full max-w-6xl bg-stone-100 px-6 py-7" data-dashboard-root>
    <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-neutral-900">Dashboard</h1>
            <p id="dashboardContext" class="mt-1 text-xs text-neutral-500">Actualizando información...</p>
        </div>

        <div class="flex gap-2.5">
            <button class="h-10 rounded-full border border-neutral-300 bg-white px-5 text-xs text-neutral-600">
                <i class="fa-solid fa-download mr-2"></i> Exportar
            </button>
            <button data-dashboard-refresh class="h-10 rounded-full bg-cyan-800 px-5 text-xs font-semibold text-white">
                <i class="fa-solid fa-rotate mr-2"></i> Actualizar
            </button>
        </div>
    </header>
    <a href="{{ route('pos.index') }}">
        Punto de venta
    </a>
    <a href="{{ route('finance.cash-closing') }}">
    Cierre de caja
    </a>
    <a href="{{ route('customers.index') }}">
    Clientes y Fidelidad
    </a>
    <a href="{{ route('inventory.reconciliation') }}">
    Conciliación y stock
    </a>
    <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-12">
        @foreach ($kpis as $kpi)
            <x-kpi-card
                id="kpi-{{ $kpi['key'] }}"
                class="{{ $kpi['class'] }} min-h-24"
                :title="$kpi['title']"
                value="—"
                subtext="—"
                :icon="$kpi['icon']"
            />
        @endforeach

        <article class="card xl:col-start-1 xl:col-span-7 xl:row-start-3">
            <div class="card-head">
                <div><h2>Estadísticas de ventas semanal</h2><p>VS ventas diarias</p></div>
                <button class="rounded-full bg-neutral-100 px-3 py-2 text-2xs text-neutral-500">Ver más</button>
            </div>
            <div class="h-80"><canvas id="salesWeeklyChart"></canvas></div>
        </article>

        <article class="card xl:col-start-8 xl:col-span-5 xl:row-start-3">
            <div class="card-head"><h2>Estado de caja: Encuadre</h2></div>
            <div class="h-80"><canvas id="cashStatusChart"></canvas></div>
        </article>

        <article class="card xl:col-start-1 xl:col-span-3 xl:row-start-4 xl:row-span-3">
            <div class="card-head"><div><h2>Inv. Lógico vs. Físico</h2><p>Diferencia por sucursal (uds)</p></div></div>
            <div class="h-72"><canvas id="inventoryComparisonChart"></canvas></div>
        </article>

        <article class="card xl:col-start-4 xl:col-span-6 xl:row-start-4 xl:row-span-3">
            <div class="card-head">
                <div><h2>Exposición de cuentas por cobrar</h2><p>Antigüedad de cartera · Últimos 5 meses</p></div>
                <strong id="receivablesTotal" class="text-base">—</strong>
            </div>
            <div class="h-72"><canvas id="receivablesChart"></canvas></div>
        </article>

        <article class="card xl:col-start-1 xl:col-span-12 xl:row-start-7">
            <div class="card-head">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-red-600"></i>
                    <h2>Centro de Alertas de Anomalías</h2>
                    <x-status-badge type="danger" text="Activas" />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-2xs">
                    <tbody id="anomalyAlertsBody"></tbody>
                </table>
            </div>
        </article>
    </section>
</main>
@endsection
