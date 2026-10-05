@extends('layouts.panel')

@section('document-title', 'Arqueo independiente')
@section('page-title', 'Arqueo independiente')
@section('breadcrumb-root', 'Mi caja')
@section('breadcrumb-current', 'Arqueo')
@section('page-script', 'operations/cash-count')

@section('content')
<section data-cash-count class="space-y-6" aria-labelledby="cash-count-title" aria-busy="true">
    <header class="rounded-3xl bg-gintly-sidebar p-6 text-white sm:p-8"><p class="text-sm font-semibold text-gintly-active">Conteo sin cierre</p><h1 id="cash-count-title" class="mt-2 text-3xl font-bold">Arqueo independiente</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Registra un conteo inmutable por moneda. El esperado y la diferencia se revelan solo después de guardar; la sesión permanece abierta.</p></header>
    <p data-cash-notice tabindex="-1" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm" hidden></p>
    <p data-cash-loading role="status" class="rounded-2xl border border-slate-200 bg-white p-6">Consultando tu sesión…</p>
    <p data-cash-empty hidden class="rounded-2xl border border-slate-200 bg-white p-6 text-sm">No tienes una sesión abierta. <a class="font-semibold text-gintly-brand underline" href="{{ route('panel.operations.cash') }}">Volver a Mi caja</a></p>
    <div data-cash-content hidden class="space-y-6">
        <p data-cash-session class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-950"></p>
        <form data-cash-form class="space-y-6" novalidate>@include('operations.partials.cash-grid')<p data-cash-field-error role="alert" class="text-sm text-red-700" hidden></p><div class="flex flex-wrap gap-3"><button data-cash-submit type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-semibold text-white disabled:opacity-50">Registrar arqueo</button><a href="{{ route('panel.operations.cash') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-semibold">Volver a Mi caja</a></div></form>
        <section aria-labelledby="cash-count-history-title" class="rounded-2xl border border-slate-200 bg-white p-5"><h2 id="cash-count-history-title" class="text-lg font-bold">Arqueos registrados</h2><p data-cash-history-state role="status" class="mt-2 text-sm text-gintly-text-secondary">Cargando historial…</p><ol data-cash-count-history class="mt-4 space-y-3"></ol><button data-cash-history-retry type="button" hidden class="mt-3 min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Reintentar historial</button></section>
    </div>
</section>
@endsection
