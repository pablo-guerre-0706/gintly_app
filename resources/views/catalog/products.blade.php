@extends('layouts.panel')

@section('title', 'Catálogo de productos y datos maestros')
@section('page-script', 'modules/catalog/products')

@section('content')
@php
    $filters = $filters ?? [
        ['id'=>'', 'label'=>'Todas'],
        ['id'=>1, 'label'=>'Abarrotes'],
        ['id'=>2, 'label'=>'Lácteos'],
        ['id'=>3, 'label'=>'Bebidas'],
        ['id'=>4, 'label'=>'Uso personal'],
        ['id'=>5, 'label'=>'Panadería'],
    ];

    $products = $products ?? collect(array_fill(0, 6, [
        'id'=>1, 'sku'=>'ARR-001', 'name'=>'Arroz Faisán',
        'category'=>'Abarrotes', 'brand'=>'Faisán', 'unit'=>'LB',
        'sale_price'=>'24.00', 'cost'=>'18.0000', 'type'=>'Simple',
        'tax'=>'IVA 15%', 'is_active'=>true,
    ]));
@endphp

<main
    id="catalogProductsRoot"
    data-products-url="/api/products"
    data-export-url="{{ $exportEndpoint ?? '' }}"
    data-tax-label="{{ $taxLabel ?? 'IVA 15%' }}"
    class="mx-auto w-full max-w-7xl bg-stone-100 px-6 py-7"
>
    <header class="mb-7">
        <h1 class="text-[27px] font-bold tracking-[-.035em] text-[#171717]">
            Catálogo de productos y datos maestros
        </h1>
        <p class="mt-1.5 text-[11px] text-[#707070]">
            Gestión Centralizada de productos, precios y calificaciones
        </p>
    </header>

    <section class="card px-7">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
            <label class="relative w-full xl:max-w-xl">
                <span class="sr-only">Buscar producto</span>
                <svg class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#6F6F6F]"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
                </svg>
                <input
                    id="productSearch"
                    type="search"
                    autocomplete="off"
                    placeholder="Busca el producto"
                    class="h-11 w-full rounded-xl border border-neutral-300 bg-white pl-11 pr-4 text-sm text-neutral-700 outline-none transition focus:border-cyan-800 focus:ring-2 focus:ring-cyan-800/10"
                >
            </label>

            <nav class="flex flex-wrap gap-3" aria-label="Categorías de producto">
                @foreach ($filters as $filter)
                    <button
                        type="button"
                        data-category-filter="{{ $filter['id'] }}"
                        @class([
                            'h-[46px] rounded-[12px] border px-5 text-[12px] font-medium transition',
                            'border-[#087F98] bg-[#087F98] text-white' => $loop->first,
                            'border-[#CFCFCF] bg-[#F5F5F5] text-[#606060] hover:bg-[#ECECEC]' => !$loop->first,
                        ])
                    >
                        {{ $filter['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>
    </section>

    <section class="card mt-6 overflow-hidden p-0">
        <header class="flex flex-col gap-4 px-7 py-7 lg:flex-row lg:items-center lg:justify-between">
            <strong id="productsCount" class="text-[20px] font-bold text-[#2C2C2C]">
                {{ count($products) }} productos
            </strong>

            <div class="flex flex-wrap gap-3">
                <button type="button" data-create-category
                    class="h-11 rounded-xl bg-neutral-100 px-5 text-xs font-medium text-neutral-600"
                    <span class="mr-2 text-[20px] font-light">＋</span> Agregar categoría de producto
                </button>

                <button type="button" data-export
                    class="h-11 rounded-xl border border-cyan-800 bg-white px-5 text-xs font-semibold text-cyan-900"
                    <span class="mr-2">⇩</span> Exportar para Excel
                </button>

                <button type="button" data-create-product
                    class="h-11 rounded-xl bg-cyan-800 px-5 text-xs font-semibold text-white hover:bg-cyan-900"
                    <span class="mr-2 text-[19px] font-light">＋</span> Agregar nuevo producto
                </button>
            </div>
        </header>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left min-w-5xl">
                <thead class="border-y border-[#DADADA] bg-[#F3F3F3]">
                    <tr class="h-16 text-xs font-medium text-neutral-500">
                        @foreach (['SKU','Producto','Categoría','Marca','Unidad','Precio de venta','Costo','Tipo','Impuestos','Estados','Edición'] as $heading)
                            <th class="whitespace-nowrap px-6 font-medium">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody id="productsTableBody" class="divide-y divide-[#E1E1E1]">
                    @foreach ($products as $product)
                        <tr class="h-20 text-xs text-neutral-600" data-product-row="{{ $product['id'] }}">
                            <td class="px-6 text-[14px] font-bold text-[#171717]">{{ $product['sku'] }}</td>
                            <td class="px-6">{{ $product['name'] }}</td>
                            <td class="px-6">{{ $product['category'] }}</td>
                            <td class="px-6">{{ $product['brand'] }}</td>
                            <td class="px-6">{{ $product['unit'] }}</td>
                            <td class="px-6 text-[14px] font-bold text-[#171717]">C$ {{ $product['sale_price'] }}</td>
                            <td class="px-6 text-[14px] font-bold text-[#333]">
                                C$ {{ number_format((string) $product['cost'], 2, '.', ',') }}
                            </td>
                            <td class="px-6"><x-status-badge type="info" :text="$product['type']" /></td>
                            <td class="px-6"><x-status-badge type="warning" :text="$product['tax']" /></td>
                            <td class="px-6"><x-status-badge :type="$product['is_active'] ? 'success' : 'danger'" :text="$product['is_active'] ? 'Activo' : 'Inactivo'" /></td>
                            <td class="px-6">
                                <button type="button" data-edit-product="{{ $product['id'] }}"
                                    class="h-9 rounded-md border border-cyan-800 px-3 text-xs font-medium text-cyan-900"
                                    Editar
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
