@extends('layouts.panel')

@section('title', 'Clientes y Fidelidad')
@section('document-title', 'Clientes')
@section('page-title', 'Clientes y fidelidad')
@section('breadcrumb-root', 'Operación')
@section('breadcrumb-current', 'Clientes')
@section('page-script', 'customers/index')

@section('content')

<section
    id="customersRoot"
    class="mx-auto w-full max-w-6xl bg-stone-100 px-6 py-7"
    aria-busy="true"
>
    <header class="mb-7">
        <h1 class="text-[28px] font-bold tracking-[-.035em] text-[#171717]">
            Clientes y Fidelidad
        </h1>
        <p class="mt-1.5 text-[10px] leading-5 text-[#777]">
            Consulta la información básica y el límite de crédito registrado de los clientes del negocio.
        </p>
    </header>

    <section class="grid min-h-152 overflow-hidden rounded-2xl bg-white shadow-sm xl:grid-cols-12">
        <div class="min-w-0 border-b border-[#E2E2E2] p-6 xl:border-b-0 xl:border-r">
            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_230px]">
                <label class="relative">
                    <span class="sr-only">Buscar cliente</span>
                    <svg class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8B8B8B]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
                    </svg>
                    <input
                        id="customerSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="Busca el cliente/producto"
                        class="h-10 w-full rounded-lg border border-neutral-300 bg-white pl-11 pr-4 text-xs text-neutral-800 outline-none transition focus:border-cyan-800 focus:ring-2 focus:ring-cyan-800/10"
                    >
                </label>

                <a
                    href="{{ route('web.customers.create') }}"
                    class="flex h-10 items-center justify-center rounded-lg bg-cyan-800 px-5 text-xs font-semibold text-white transition hover:bg-cyan-900"
                    data-create-customer
                    hidden
                >
                <span class="mr-2 text-sm font-normal">⊕</span>
                    Registra a nuevo cliente
                </a>
            </div>

            <div id="customersList" class="mt-7 space-y-5" aria-live="polite">
                <p class="py-16 text-center text-sm text-[#777]">Consultando clientes…</p>
            </div>
        </div>

        <aside id="customerDetail" class="grid min-h-112 place-items-center p-8 text-center">
            <div id="customerEmptyState">
                <svg class="mx-auto h-14 w-14 text-[#4F81A5]" viewBox="0 0 64 64" fill="currentColor" aria-hidden="true">
                    <circle cx="22" cy="19" r="9"/><circle cx="43" cy="19" r="9"/>
                    <path d="M7 48c0-11 6-18 15-18s15 7 15 18z"/><path d="M29 48c0-11 6-18 14-18s14 7 14 18z"/>
                </svg>
                <p class="mt-5 text-[12px] font-medium text-[#4C5563]">Selecciona un cliente</p>
                <p class="mt-3 text-[10px] text-[#536071]">para ver su información registrada</p>
            </div>
        </aside>
    </section>
</section>
@endsection
