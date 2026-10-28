@extends('layouts.panel')

@section('title', 'Clientes')
@section('document-title', 'Clientes')
@section('page-title', 'Clientes')
@section('breadcrumb-root', 'Ventas y clientes')
@section('breadcrumb-current', 'Clientes')
@section('page-script', 'customers/index')

@section('content')
<section
    id="customersRoot"
    class="mx-auto w-full max-w-7xl space-y-6"
    data-customers-root
    data-customers-endpoint="/customers"
    aria-labelledby="customers-title"
    aria-busy="true"
>
    <header class="flex flex-col gap-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Ventas y clientes</p>
            <h1 id="customers-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Clientes</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-gintly-text-secondary">Consulta datos de contacto, estado y límite de crédito sin perder el historial comercial del cliente.</p>
        </div>
        <a
            href="{{ route('web.customers.create') }}"
            class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white transition hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2"
            data-create-customer
            hidden
        >Crear cliente</a>
    </header>

    <p data-customers-notice role="status" tabindex="-1" hidden class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"></p>

    <section class="grid min-w-0 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:grid-cols-12" aria-label="Directorio y detalle de clientes">
        <div class="min-w-0 lg:col-span-7 lg:border-r lg:border-slate-200">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <h2 class="text-lg font-bold text-gintly-text-primary">Directorio de clientes</h2>
                <p class="mt-1 text-sm text-gintly-text-secondary" data-customers-summary aria-live="polite">Cargando clientes…</p>
                <form class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(10rem,0.42fr)_auto]" data-customers-filters novalidate>
                    <div>
                        <label for="customerSearch" class="mb-2 block text-sm font-semibold">Buscar cliente</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                            <input id="customerSearch" name="search" type="search" minlength="2" maxlength="160" autocomplete="off" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pl-11 pr-4 text-sm outline-none focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" placeholder="Nombre, identificación, correo o teléfono">
                        </div>
                        <p data-customers-search-error class="mt-2 text-sm text-red-700" role="alert" hidden></p>
                    </div>
                    <div>
                        <label for="customerStatus" class="mb-2 block text-sm font-semibold">Estado</label>
                        <select id="customerStatus" name="is_active" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm outline-none focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20">
                            <option value="">Todos</option>
                            <option value="1">Activos</option>
                            <option value="0">Inactivos</option>
                        </select>
                    </div>
                    <button type="submit" class="min-h-11 self-end rounded-xl border border-gintly-brand px-4 text-sm font-bold text-gintly-brand hover:bg-gintly-brand/5">Buscar</button>
                </form>
            </div>

            <div class="grid min-w-0 gap-4 p-5 sm:p-6" data-customers-list aria-live="polite"></div>
            <div class="border-t border-slate-200 p-5 text-sm text-gintly-text-secondary sm:p-6" data-customers-state role="status">Cargando clientes…</div>
            <nav class="flex items-center justify-between gap-4 border-t border-slate-200 px-5 py-4 sm:px-6" data-customers-pagination aria-label="Paginación de clientes" hidden>
                <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold disabled:opacity-50" data-customers-previous>Anterior</button>
                <span class="text-sm text-gintly-text-secondary" data-customers-page></span>
                <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold disabled:opacity-50" data-customers-next>Siguiente</button>
            </nav>
        </div>

        <aside id="customerDetail" class="min-w-0 border-t border-slate-200 bg-slate-50/60 p-5 sm:p-7 lg:col-span-5 lg:border-t-0" aria-live="polite" data-customer-detail>
            <div class="grid min-h-80 place-items-center text-center" data-customer-empty>
                <div class="max-w-xs">
                    <svg class="mx-auto size-14 text-gintly-brand/70" viewBox="0 0 64 64" fill="currentColor" aria-hidden="true"><circle cx="22" cy="19" r="9"></circle><circle cx="43" cy="19" r="9"></circle><path d="M7 48c0-11 6-18 15-18s15 7 15 18z"></path><path d="M29 48c0-11 6-18 14-18s14 7 14 18z"></path></svg>
                    <h2 class="mt-5 text-lg font-bold">Selecciona un cliente</h2>
                    <p class="mt-2 text-sm leading-6 text-gintly-text-secondary">Usa la acción Ver para consultar su información sin invadir el listado.</p>
                </div>
            </div>
        </aside>
    </section>

    <dialog data-customer-confirm class="m-auto w-[min(440px,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-6 text-gintly-text-primary shadow-2xl backdrop:bg-slate-950/60" aria-labelledby="customer-confirm-title" aria-describedby="customer-confirm-message">
        <h2 id="customer-confirm-title" class="text-lg font-bold">Confirmar cambio de estado</h2>
        <p id="customer-confirm-message" data-customer-confirm-message class="mt-3 text-sm leading-6 text-gintly-text-secondary"></p>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold" data-customer-confirm-cancel>Volver</button>
            <button type="button" class="min-h-11 rounded-xl bg-gintly-brand px-4 text-sm font-bold text-white" data-customer-confirm-accept>Confirmar</button>
        </div>
    </dialog>
</section>
@endsection
