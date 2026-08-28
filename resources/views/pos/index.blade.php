@extends('layouts.panel')

@section('title', 'Puntos de venta')
@section('page-script', 'modules/pos/index')

@section('content')
@php
    $categories = ['Todos', 'Café', 'Bebidas', 'Comida', 'Postres'];
@endphp

<main
    id="posRoot"
    data-tax-rate="{{ $taxRate ?? '0.0000' }}"
    data-checkout-url="{{ $checkoutEndpoint ?? '' }}"
    class="min-h-screen bg-[#F5F5F4] px-5 py-7 lg:px-7"
>
    <header class="mb-6">
        <h1 class="text-[27px] font-bold tracking-[-.035em] text-[#181818]">
            Puntos de venta
        </h1>
        <p class="mt-1 text-[11px] text-[#777]">
            Busca productos, registra el efectivo recibido y completa la venta.
        </p>
    </header>

    <section
        class="grid gap-5 rounded-[14px] bg-white p-5 shadow-sm lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,.85fr)]"
        aria-label="Punto de venta"
    >
        {{-- Catálogo --}}
        <div class="min-w-0">
            <label class="relative block">
                <span class="sr-only">Buscar productos</span>
                <input
                    id="posSearch"
                    type="search"
                    placeholder="Buscar un producto"
                    autocomplete="off"
                    class="h-10 w-full rounded-lg border-0 bg-[#F3F3F3] px-10 text-[11px] text-[#333] outline-none ring-1 ring-transparent focus:ring-[#07839B]/30"
                >
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#8A8A8A]">⌕</span>
            </label>

            <nav class="mt-4 flex flex-wrap gap-2" aria-label="Categorías">
                @foreach ($categories as $category)
                    <button
                        type="button"
                        data-filter="{{ Str::slug($category) }}"
                        @class([
                            'rounded-lg border px-4 py-2 text-[10px] font-medium transition',
                            'border-[#07839B] bg-[#07839B] text-white' => $loop->first,
                            'border-[#D9D9D9] bg-white text-[#666] hover:bg-[#F7F7F7]' => !$loop->first,
                        ])
                    >
                        {{ $category }}
                    </button>
                @endforeach
            </nav>

            <div
                id="posProducts"
                class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                aria-live="polite"
            ></div>
        </div>

        {{-- Ticket --}}
        <aside class="flex min-h-128 flex-col border-t border-neutral-200 pt-5 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
            <header class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-semibold text-[#222]">Ticket de venta</p>
                    <p id="posTicketCode" class="mt-1 text-[8px] text-[#8A8A8A]">Nueva venta</p>
                </div>
                <span id="posItemCount" class="text-[10px] font-semibold text-[#555]">0 artículos</span>
            </header>

            <form id="posForm" class="mt-4 flex min-h-0 flex-1 flex-col">
                <input type="hidden" name="branch_id" value="{{ $branchId ?? '' }}">
                <input type="hidden" name="customer_id" value="{{ $defaultCustomerId ?? '' }}">
                <input id="paymentMethod" type="hidden" value="efectivo">

                <div
                    id="posCart"
                    class="min-h-52 flex-1 space-y-2 overflow-y-auto pr-1"
                >
                    <div id="posEmpty" class="grid h-full min-h-52 place-items-center text-center">
                        <div>
                            <div class="text-5xl text-[#888]">🛒</div>
                            <p class="mt-4 text-[11px] font-medium text-[#555]">
                                Selecciona productos del catálogo
                            </p>
                        </div>
                    </div>
                </div>

                <dl class="mt-4 space-y-2 rounded-lg bg-[#F7F7F7] p-3 text-[10px]">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd id="posSubtotal">C$ 0.00</dd></div>
                    <div class="flex justify-between"><dt>IVA</dt><dd id="posTax">C$ 0.00</dd></div>
                    <div class="flex justify-between border-t border-[#DDD] pt-2 font-bold">
                        <dt>Total a cobrar</dt><dd id="posTotal">C$ 0.00</dd>
                    </div>
                </dl>

                <fieldset class="mt-3 grid grid-cols-3 gap-2">
                    @foreach (['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia'] as $key => $label)
                        <button
                            type="button"
                            data-payment="{{ $key }}"
                            @class([
                                'h-10 rounded-lg border text-[9px] font-medium transition',
                                'border-[#72C98D] bg-[#DDF6E5] text-[#258446]' => $loop->first,
                                'border-[#DDD] bg-[#F8F8F8] text-[#555]' => !$loop->first,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </fieldset>

                <button
                    type="submit"
                    data-submit
                    class="mt-3 h-11 rounded-lg bg-[#07839B] text-[11px] font-semibold text-white transition hover:bg-[#066F84] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Finalizar venta e imprimir ticket
                </button>
            </form>
        </aside>
    </section>

    <section class="mt-6 rounded-[14px] bg-white p-5 shadow-sm">
        <header>
            <h2 class="text-[22px] font-bold tracking-[-.03em] text-[#1D1D1D]">
                Historial de compras
            </h2>
            <p class="mt-1 text-[10px] text-[#777]">
                Visualiza el historial reciente de ventas registradas.
            </p>
        </header>

        <div
            id="posHistory"
            class="mt-5 grid gap-3 md:grid-cols-2"
        ></div>
    </section>
</main>
@endsection
