@extends('layouts.panel')

@section('title', 'Conciliación y stocks')
@section('page-script', 'modules/inventory/reconciliation')

@section('content')
@php
    $kpis = [
        ['title'=>'Valor Total del Inventario','value'=>'C$ 19,718.00','subtext'=>'al costo de adquisición'],
        ['title'=>'Stock Crítico','value'=>'2','subtext'=>'Por debajo del mínimo'],
        ['title'=>'Stock bajo','value'=>'0','subtext'=>'Cerca del mínimo'],
    ];

    $rows = $rows ?? collect(array_fill(0, 4, [
        'count_id'=>1, 'sku'=>'ARR-001', 'name'=>'Arroz Faisán', 'category'=>'Abarrotes',
        'system'=>'320.000', 'counted'=>'318.000', 'unit'=>'LB', 'cost'=>'18.0000',
        'min'=>'50.000', 'level'=>'normal',
    ]));
@endphp

<main
    id="inventoryReconciliationRoot"
    data-export-url="{{ $exportEndpoint ?? '' }}"
    class="mx-auto w-full max-w-7xl bg-stone-100 px-6 py-7"
>
    <nav class="mb-7 text-[10px] text-[#8A8A8A]" aria-label="Breadcrumb">
        <span>Gintly</span>
        <span class="mx-2">›</span>
        <strong class="font-semibold text-[#333]">Inventario y bodega</strong>
    </nav>

    <header class="mb-7">
        <h1 class="text-[28px] font-bold tracking-[-.035em] text-[#171717]">
            Conciliación y stocks
        </h1>
        <p class="mt-1.5 text-[10px] text-[#777]">
            Conciliación entre stock del sistema y conteo físico de bodega
        </p>
    </header>

    <section class="grid grid-cols-1 gap-5 md:grid-cols-3">
        @foreach ($kpis as $kpi)
            <x-kpi-card
                class="min-h-28"
                :title="$kpi['title']"
                :value="$kpi['value']"
                :subtext="$kpi['subtext']"
            />
        @endforeach
    </section>

    <section class="mt-6 flex min-h-24 items-center gap-4 rounded-xl border border-neutral-300 bg-white px-6 shadow-sm">
        <div class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-red-100 text-2xl font-bold text-red-700">
            ↕
        </div>
        <div>
            <p class="text-[11px] text-[#777]">Descuadres detectados</p>
            <p id="mismatchCount" class="mt-1 text-[23px] font-bold leading-none text-[#181818]">6</p>
            <p class="mt-1 text-[8px] font-medium text-[#C90009]">Requieren revisión</p>
        </div>
    </section>

    <section class="mt-6 rounded-[13px] border border-[#D7D7D7] bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <label class="relative w-full lg:max-w-2xl">
                <span class="sr-only">Buscar producto</span>
                <svg class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#777]"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
                </svg>
                <input
                    id="inventorySearch"
                    type="search"
                    placeholder="Busca el producto"
                    autocomplete="off"
                    class="h-10 w-full rounded-lg border border-neutral-300 pl-11 pr-4 text-xs outline-none focus:border-cyan-800 focus:ring-2 focus:ring-cyan-800/10"
                >
            </label>

            <nav class="flex flex-wrap gap-3" aria-label="Nivel de stock">
                @foreach (['all'=>'Todas','critical'=>'Crítico','low'=>'Bajo','normal'=>'Normal'] as $key => $label)
                    <button
                        type="button"
                        data-stock-filter="{{ $key }}"
                        @class([
                            'h-[36px] rounded-[9px] border px-6 text-[9px] font-medium transition',
                            'border-[#087F98] bg-[#087F98] text-white' => $loop->first,
                            'border-[#D7D7D7] bg-[#F5F5F5] text-[#666]' => !$loop->first,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </nav>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-[13px] border border-[#D5D5D5] bg-white shadow-sm">
        <header class="flex justify-end px-5 py-4">
            <button
                type="button"
                data-export
                class="h-9 rounded-lg bg-cyan-800 px-5 text-2xs font-semibold text-white"
            >
                ⇩&nbsp;&nbsp; Exportar para Excel
            </button>
        </header>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left min-w-5xl">
                <thead class="border-y border-[#DDD] bg-[#F2F2F2]">
                    <tr class="h-14 text-2xs font-medium text-neutral-500">
                        @foreach (['SKU','Producto','Categoría','Stock teórico','Conteo físico','Diferencia','Valor','Nivel de stock','Ajuste'] as $heading)
                            <th class="whitespace-nowrap px-5 font-medium">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody id="reconciliationTableBody" class="divide-y divide-[#E5E5E5]">
                    @foreach ($rows as $row)
                        <tr
                            data-reconciliation-row
                            data-name="{{ Str::lower($row['name'].' '.$row['sku']) }}"
                            data-level="{{ $row['level'] }}"
                            data-system="{{ $row['system'] }}"
                            data-counted="{{ $row['counted'] }}"
                            data-cost="{{ $row['cost'] }}"
                            class="h-20 text-2xs text-neutral-600"
                        >
                            <td class="px-5 text-[11px] font-bold text-[#222]">{{ $row['sku'] }}</td>
                            <td class="px-5">{{ $row['name'] }}</td>
                            <td class="px-5">{{ $row['category'] }}</td>
                            <td class="px-5 font-semibold">{{ $row['system'] }} {{ $row['unit'] }}</td>
                            <td class="px-5">{{ $row['counted'] }} {{ strtolower($row['unit']) }}</td>
                            <td class="px-5"><span data-difference class="rounded-[5px] bg-red-100 px-2 py-1 font-bold text-red-700">-2.000</span></td>
                            <td data-cost-display class="px-5 text-[11px] font-bold text-[#333]">C$ 18.00</td>
                            <td class="px-5">
                                <p class="font-semibold text-[#444]">Estado normal</p>
                                <div class="mt-2 h-1 w-24 overflow-hidden rounded-full bg-neutral-200">
                                    <div class="h-full w-3/4 rounded-full bg-emerald-500"></div>
                                </div>
                                <p class="mt-1 text-[7px] text-[#888]">Min: {{ $row['min'] }}</p>
                            </td>
                            <td class="px-5">
                                <button
                                    type="button"
                                    data-apply-count="{{ $row['count_id'] }}"
                                    class="h-8 rounded-md border border-cyan-800 px-3 text-2xs font-medium text-cyan-900"
                                >Ajustar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
