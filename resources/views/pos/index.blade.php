@extends('layouts.panel')

@section('title', 'Puntos de venta')
@section('document-title', 'Punto de venta')
@section('page-title', 'Punto de venta')
@section('breadcrumb-root', 'Operación')
@section('breadcrumb-current', 'Punto de venta')
@section('page-script', 'pos/index')

@section('content')
<section
    id="posRoot"
    class="min-h-screen bg-[#F5F5F4] px-5 py-7 lg:px-7"
    aria-busy="true"
>
    <header class="mb-6">
        <h1 class="text-[27px] font-bold tracking-[-.035em] text-[#181818]">
            Puntos de venta
        </h1>
        <p class="mt-1 text-[11px] text-[#777]">
            Busca productos y emite la factura con un medio de pago autorizado.
        </p>
    </header>

    <section
        class="grid gap-5 rounded-[14px] bg-white p-5 shadow-sm lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,.85fr)]"
        aria-label="Punto de venta"
    >
        {{-- Catálogo --}}
        <div class="min-w-0">
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm" aria-live="polite">
                <p data-pos-availability-state>Consultando disponibilidad de la bodega utilizada al emitir…</p>
                <button type="button" data-pos-availability-refresh class="mt-2 min-h-11 rounded-lg border border-slate-300 bg-white px-3 font-semibold disabled:opacity-50">Actualizar disponibilidad</button>
            </div>
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
                <label class="mb-3 block text-[10px] font-semibold text-[#444]">
                    Cliente
                    <select
                        name="customer_id"
                        required
                        class="mt-1 min-h-11 w-full rounded-lg border border-[#DDD] bg-white px-3 text-[10px] text-[#333] outline-none focus:border-[#07839B] focus:ring-2 focus:ring-[#07839B]/20"
                    >
                        <option value="">Cargando clientes…</option>
                    </select>
                </label>
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
                    <div class="flex justify-between font-bold">
                        <dt>Subtotal estimado</dt><dd id="posSubtotal">C$ 0.00</dd>
                    </div>
                    <p class="text-[8px] leading-4 text-[#777]">El Backend calculará impuestos y total definitivo al confirmar la venta.</p>
                </dl>
                <p class="mt-3 text-sm text-amber-900" data-pos-stock-warning role="status" hidden></p>

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
                    Confirmar venta y emitir factura
                </button>
                <section class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-pos-invoice-recovery aria-label="Recuperación de emisión" hidden>
                    <p>El ticket registrado se conserva sin editar sus líneas. Actualiza la disponibilidad; cuando exista saldo suficiente, puedes reintentar únicamente la emisión de esa misma venta.</p>
                    <button type="button" data-pos-invoice-retry class="mt-3 min-h-11 w-full rounded-lg border border-amber-300 bg-white px-3 font-semibold disabled:opacity-50">Reintentar solo emisión</button>
                </section>
            </form>
        </aside>
    </section>
    <p class="mt-4 text-sm text-[#555]" data-pos-live role="status" aria-live="polite"></p>
</section>
@endsection
