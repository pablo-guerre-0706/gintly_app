@extends('layouts.panel')

@section('title', 'Dashboard')

@push('scripts')
    @vite(['resources/js/modules/dashboard/index.js'])
@endpush

@section('content')
@php
$kpis = [
    // Fila 1: 3 tarjetas superiores simétricas
    ['key'=>'sales_today','title'=>'Ventas del día','icon'=>'<i class="fa-solid fa-chart-line text-[#146F8A]"></i>','class'=>'xl:col-start-1 xl:col-span-4 xl:row-start-1'],
    ['key'=>'average_ticket','title'=>'Ticket promedio','icon'=>'<i class="fa-solid fa-receipt text-[#146F8A]"></i>','class'=>'xl:col-start-5 xl:col-span-4 xl:row-start-1'],
    ['key'=>'overdue_receivables','title'=>'CxC Vencida +60 días','icon'=>'<i class="fa-solid fa-money-bill-wave text-rose-500"></i>','class'=>'xl:col-start-9 xl:col-span-4 xl:row-start-1'],
    
    // Fila 2: 2 tarjetas de advertencia/control más anchas
    ['key'=>'inventory_variance','title'=>'Descuadre inv.','icon'=>'<i class="fa-solid fa-wallet text-amber-500"></i>','class'=>'xl:col-start-1 xl:col-span-6 xl:row-start-2'],
    ['key'=>'active_alerts','title'=>'Alertas activas','icon'=>'<i class="fa-solid fa-triangle-exclamation text-rose-500"></i>','class'=>'xl:col-start-7 xl:col-span-6 xl:row-start-2'],
    
    // Bloque lateral derecho: Tarjetas secundarias compactas sin icono (Fila 4, 5 y 6)
    ['key'=>'gross_margin','title'=>'Margen bruto','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-4'],
    ['key'=>'returns','title'=>'Devoluciones','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-5'],
    ['key'=>'pending_purchases','title'=>'Compras p. de aprobación','icon'=>null,'class'=>'xl:col-start-10 xl:col-span-3 xl:row-start-6'],
];
@endphp

<main class="mx-auto w-full max-w-6xl bg-white px-6 py-7 tracking-tight" data-dashboard-root>
   
    <!-- Encabezado Principal -->
    <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Dashboard</h1>
            <p id="dashboardContext" class="mt-0.5 text-xs text-slate-400">Actualizando información...</p>
        </div>

        <div class="flex gap-2.5">
            <button class="h-9 inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 transition-all hover:bg-slate-50 active:scale-95">
                <i class="fa-solid fa-download mr-2 text-slate-400"></i> Exportar
            </button>
            <button data-dashboard-refresh class="h-9 inline-flex items-center rounded-xl bg-[#146F8A] hover:bg-[#10596e] px-4 text-xs font-bold text-white transition-all shadow-md shadow-[#146F8A]/10 hover:scale-105 active:scale-95">
                <i class="fa-solid fa-rotate mr-2"></i> Actualizar
            </button>
        </div>
    </header>

    <!-- Barra de Accesos Rápidos -->
    <nav class="mb-8 rounded-2xl border border-slate-100 bg-slate-50/50 p-4" aria-label="Accesos rápidos">
        <h2 class="mb-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Accesos Rápidos al Sistema</h2>
        <div class="flex flex-wrap gap-2.5">
            <a href="{{ route('pos.index') }}" class="inline-flex h-9 items-center rounded-xl bg-white px-4 text-xs font-semibold text-slate-700 border border-slate-100 shadow-sm transition-all hover:bg-slate-50 hover:scale-105">
                <i class="fa-solid fa-cash-register mr-2 text-[#146F8A]"></i> Punto de venta
            </a>
            <a href="{{ route('finance.cash-closing') }}" class="inline-flex h-9 items-center rounded-xl bg-white px-4 text-xs font-semibold text-slate-700 border border-slate-100 shadow-sm transition-all hover:bg-slate-50 hover:scale-105">
                <i class="fa-solid fa-vault mr-2 text-[#146F8A]"></i> Cierre de caja
            </a>
            <a href="{{ route('customers.index') }}" class="inline-flex h-9 items-center rounded-xl bg-white px-4 text-xs font-semibold text-slate-700 border border-slate-100 shadow-sm transition-all hover:bg-slate-50 hover:scale-105">
                <i class="fa-solid fa-users mr-2 text-[#146F8A]"></i> Clientes y Fidelidad
            </a>
            <a href="{{ route('inventory.reconciliation') }}" class="inline-flex h-9 items-center rounded-xl bg-white px-4 text-xs font-semibold text-slate-700 border border-slate-100 shadow-sm transition-all hover:bg-slate-50 hover:scale-105">
                <i class="fa-solid fa-boxes-packing mr-2 text-[#146F8A]"></i> Conciliación y stock
            </a>
        </div>
    </nav>

    <!-- Grilla de Tarjetas y Reportes Gráficos -->
    <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-12">
        @foreach ($kpis as $kpi)
            <x-kpi-card
                id="kpi-{{ $kpi['key'] }}"
                class="{{ $kpi['class'] }} min-h-24 rounded-2xl border border-slate-100 shadow-sm text-slate-900 bg-white"
                :title="$kpi['title']"
                value="—"
                subtext="—"
                :icon="$kpi['icon']"
            />
        @endforeach

        <!-- Gráfica Semanal (Grande Izquierda) -->
        <article class="card xl:col-start-1 xl:col-span-7 xl:row-start-3 rounded-2xl border border-slate-100 shadow-sm bg-white p-5">
            <div class="card-head flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Estadísticas de ventas semanal</h2>
                    <p class="text-xs text-slate-400">VS ventas diarias</p>
                </div>
                <button class="rounded-xl bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 transition">Ver más</button>
            </div>
            <div class="h-80"><canvas id="salesWeeklyChart"></canvas></div>
        </article>

        <!-- Estado de Caja Encuadre (Derecha de Gráfica Semanal) -->
        <article class="card xl:col-start-8 xl:col-span-5 xl:row-start-3 rounded-2xl border border-slate-100 shadow-sm bg-white p-5">
            <div class="card-head mb-4"><h2 class="text-sm font-bold text-slate-800">Estado de caja: Encuadre</h2></div>
            <div class="h-80"><canvas id="cashStatusChart"></canvas></div>
        </article>

        <!-- Inventario Lógico vs Físico (Fila Inferior Izquierda) -->
        <article class="card xl:col-start-1 xl:col-span-3 xl:row-start-4 xl:row-span-3 rounded-2xl border border-slate-100 shadow-sm bg-white p-5">
            <div class="card-head mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Inv. Lógico vs. Físico</h2>
                    <p class="text-xs text-slate-400">Diferencia por sucursal (uds)</p>
                </div>
            </div>
            <div class="h-72"><canvas id="inventoryComparisonChart"></canvas></div>
        </article>

        <!-- Exposición de Cuentas por Cobrar (Centro Inferior) -->
        <article class="card xl:col-start-4 xl:col-span-6 xl:row-start-4 xl:row-span-3 rounded-2xl border border-slate-100 shadow-sm bg-white p-5">
            <div class="card-head flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Exposición de cuentas por cobrar</h2>
                    <p class="text-xs text-slate-400">Antigüedad de cartera · Últimos 5 meses</p>
                </div>
                <strong id="receivablesTotal" class="text-base font-bold text-slate-900">—</strong>
            </div>
            <div class="h-72"><canvas id="receivablesChart"></canvas></div>
        </article>

        <!-- Centro de Alertas de Anomalías (Ancho Completo Inferior) -->
        <article class="card xl:col-start-1 xl:col-span-12 xl:row-start-7 rounded-2xl border border-slate-100 shadow-sm bg-white p-5">
            <div class="card-head mb-4">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                    <h2 class="text-sm font-bold text-slate-800">Centro de Alertas de Anomalías</h2>
                    <x-status-badge type="danger" text="Activas" />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <tbody id="anomalyAlertsBody"></tbody>
                </table>
            </div>
        </article>
    </section>
</main>

{{-- Inyección directa para asegurar que Vite rinda el script en el navegador local --}}
@stack('scripts')

@endsection
