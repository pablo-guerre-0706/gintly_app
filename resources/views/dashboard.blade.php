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

<main class="mx-auto w-full max-w-[1180px] bg-[#F5F5F4] px-6 py-7" data-dashboard-root>
    <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-[28px] font-bold tracking-[-.035em] text-[#171717]">Dashboard</h1>
            <p id="dashboardContext" class="mt-1 text-[10px] text-[#777]">Actualizando información...</p>
        </div>

        <div class="flex gap-2.5">
            <button class="h-10 rounded-full border border-[#D0D0D0] bg-white px-5 text-[11px] text-[#696969]">
                <i class="fa-solid fa-download mr-2"></i> Exportar
            </button>
            <button data-dashboard-refresh class="h-10 rounded-full bg-[#087E98] px-5 text-[11px] font-semibold text-white">
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
    <section class="grid grid-cols-1 gap-[18px] sm:grid-cols-2 xl:grid-cols-12 xl:auto-rows-min">
        @foreach ($kpis as $kpi)
            <x-kpi-card
                id="kpi-{{ $kpi['key'] }}"
                class="{{ $kpi['class'] }} min-h-[96px]"
                :title="$kpi['title']"
                value="—"
                subtext="—"
                :icon="$kpi['icon']"
            />
        @endforeach

        <article class="card xl:col-start-1 xl:col-span-7 xl:row-start-3">
            <div class="card-head">
                <div><h2>Estadísticas de ventas semanal</h2><p>VS ventas diarias</p></div>
                <button class="rounded-full bg-[#F4F4F4] px-3 py-2 text-[9px] text-[#777]">Ver más</button>
            </div>
            <div class="h-[300px]"><canvas id="salesWeeklyChart"></canvas></div>
        </article>

        <article class="card xl:col-start-8 xl:col-span-5 xl:row-start-3">
            <div class="card-head"><h2>Estado de caja: Encuadre</h2></div>
            <div class="h-[300px]"><canvas id="cashStatusChart"></canvas></div>
        </article>

        <article class="card xl:col-start-1 xl:col-span-3 xl:row-start-4 xl:row-span-3">
            <div class="card-head"><div><h2>Inv. Lógico vs. Físico</h2><p>Diferencia por sucursal (uds)</p></div></div>
            <div class="h-[270px]"><canvas id="inventoryComparisonChart"></canvas></div>
        </article>

        <article class="card xl:col-start-4 xl:col-span-6 xl:row-start-4 xl:row-span-3">
            <div class="card-head">
                <div><h2>Exposición de cuentas por cobrar</h2><p>Antigüedad de cartera · Últimos 5 meses</p></div>
                <strong id="receivablesTotal" class="text-base">—</strong>
            </div>
            <div class="h-[270px]"><canvas id="receivablesChart"></canvas></div>
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
                <table class="w-full text-left text-[9px]">
                    <tbody id="anomalyAlertsBody"></tbody>
                </table>
            </div>
        </article>
    </section>
</main>
@endsection
