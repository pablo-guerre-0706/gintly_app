@extends('layouts.panel')

@section('document-title', 'Tipo de cambio USD')
@section('page-title', 'Tipo de cambio USD')
@section('breadcrumb-root', 'Caja y finanzas')
@section('breadcrumb-current', 'Tipo de cambio')
@section('page-script', 'administration/exchange-rates')

@section('content')
<section data-exchange-rates class="space-y-6" aria-labelledby="exchange-rates-title" aria-busy="true">
    <header class="rounded-3xl bg-gintly-sidebar p-6 text-white sm:p-8"><p class="text-sm font-semibold text-gintly-active">Administración · historial inmutable</p><h1 id="exchange-rates-title" class="mt-2 text-3xl font-bold">Tipo de cambio USD</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Tasa expresada en NIO por 1 USD. Cada nueva vigencia queda registrada sin editar las anteriores.</p></header>
    <p data-cash-notice tabindex="-1" role="alert" hidden class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm"></p>
    <p data-rate-current role="status" class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950">Consultando tasa vigente…</p>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <form data-rate-form novalidate class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-lg font-bold">Registrar nueva vigencia</h2><label class="grid gap-2 text-sm font-semibold">Tasa NIO por USD · Obligatorio<input name="rate" required inputmode="decimal" autocomplete="off" placeholder="0.000000" class="min-h-11 rounded-xl border border-slate-300 px-3" aria-describedby="rate-error"></label><p id="rate-error" data-error-for="rate" hidden class="text-sm text-red-700"></p><label class="grid gap-2 text-sm font-semibold">Vigente desde · Obligatorio<input name="effective_from" required type="datetime-local" class="min-h-11 rounded-xl border border-slate-300 px-3" aria-describedby="effective-from-error"></label><p id="effective-from-error" data-error-for="effective_from" hidden class="text-sm text-red-700"></p><p data-cash-field-error role="alert" hidden class="text-sm text-red-700"></p><button data-rate-submit type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-semibold text-white disabled:opacity-50">Registrar tasa</button></form>
        <section aria-labelledby="rate-history-title" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><h2 id="rate-history-title" class="text-lg font-bold">Historial de tasas</h2><p data-rate-state role="status" class="mt-2 text-sm text-gintly-text-secondary">Cargando…</p><ol data-rate-list class="mt-4 divide-y divide-slate-200"></ol><button data-rate-retry type="button" hidden class="mt-4 min-h-11 rounded-xl border px-4">Reintentar</button></section>
    </div>
</section>
@endsection
