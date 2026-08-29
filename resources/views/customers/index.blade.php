@extends('layouts.panel')

@section('title', 'Clientes y Fidelidad')
@section('page-script', 'modules/customers/index')

@section('content')

<main
    id="customersRoot"
    data-customers-url="{{ $customersEndpoint ?? route('customers.index') }}"
    class="mx-auto w-full max-w-6xl bg-stone-100 px-6 py-7"
>
    <header class="mb-7">
        <h1 class="text-[28px] font-bold tracking-[-.035em] text-[#171717]">
            Clientes y Fidelidad
        </h1>
        <p class="mt-1.5 text-[10px] leading-5 text-[#777]">
            Revisa el saldo de los clientes que compran al fiado, verifica sus límites de crédito disponibles y procesa los abonos a sus deudas pendientes.
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
                    href="{{ route('customers.view.create') }}"
                    class="flex h-10 items-center justify-center rounded-lg bg-cyan-800 px-5 text-xs font-semibold text-white transition hover:bg-cyan-900"
                >
                <span class="mr-2 text-sm font-normal">⊕</span>
                    Registra a nuevo cliente
                </a>
            </div>

            <nav class="mt-6 flex flex-wrap gap-3" aria-label="Filtros de clientes">
                @foreach ([['all','Todas (6)'], ['frequent','Clientes frecuentes (4)'], ['occasional','Ocasional (2)']] as [$key,$label])
                    <button
                        type="button"
                        data-profile-filter="{{ $key }}"
                        @class([
                            'h-[36px] rounded-[10px] border px-4 text-[10px] font-medium transition',
                            'border-[#087F98] bg-[#087F98] text-white' => $loop->first,
                            'border-[#D7D7D7] bg-[#F7F7F7] text-[#696969]' => !$loop->first,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </nav>

            <div id="customersList" class="mt-7 space-y-5" aria-live="polite">
                @foreach ($customers as $customer)
                    <article
                        tabindex="0"
                        data-customer-card
                        data-type="{{ $customer['profile_type'] }}"
                        data-customer="{{ json_encode($customer, JSON_UNESCAPED_UNICODE) }}"
                        class="grid cursor-pointer gap-4 rounded-[14px] border border-[#D6D6D6] bg-white px-5 py-5 transition hover:border-[#9ABFC8] hover:shadow-sm sm:grid-cols-[1.35fr_1fr_110px]"
                    >
                        <div class="min-w-0">
                            <h2 class="truncate text-[12px] font-bold text-[#202020]">{{ $customer['name'] }}</h2>
                            <x-status-badge class="mt-2" type="info" :text="$customer['profile_label']" />
                            <p class="mt-3 text-[8px] text-[#696969]">Cédula: {{ $customer['document_number'] }}</p>
                            <p class="mt-2 truncate text-[8px] text-[#777]">Dirección: {{ $customer['address'] }}</p>
                        </div>

                        <dl class="space-y-2 pt-1 text-[8px] text-[#777]">
                            <div><dt class="inline font-semibold">Número celular:</dt> <dd class="inline">{{ $customer['phone_number'] }}</dd></div>
                            <div><dt class="inline font-semibold">Límite de crédito:</dt> <dd class="inline">C$ {{ $customer['credit_limit'] }}</dd></div>
                        </dl>

                        <div class="text-right">
                            <p class="text-[19px] font-bold tracking-[-.02em] text-[#202020]">C$ {{ $customer['balance'] }}</p>
                            <p class="mt-2 text-[10px] text-[#777]">{{ $customer['purchase_count'] }} compras</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <aside id="customerDetail" class="grid min-h-112 place-items-center p-8 text-center">
            <div id="customerEmptyState">
                <svg class="mx-auto h-14 w-14 text-[#4F81A5]" viewBox="0 0 64 64" fill="currentColor" aria-hidden="true">
                    <circle cx="22" cy="19" r="9"/><circle cx="43" cy="19" r="9"/>
                    <path d="M7 48c0-11 6-18 15-18s15 7 15 18z"/><path d="M29 48c0-11 6-18 14-18s14 7 14 18z"/>
                </svg>
                <p class="mt-5 text-[12px] font-medium text-[#4C5563]">Selecciona un cliente</p>
                <p class="mt-3 text-[10px] text-[#536071]">para ver su perfil e historial de compras</p>
            </div>
        </aside>
    </section>
</main>
@endsection
