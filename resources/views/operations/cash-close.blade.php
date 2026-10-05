@extends('layouts.panel')

@section('document-title', 'Cierre de caja')
@section('page-title', 'Cierre de caja')
@section('breadcrumb-root', 'Mi caja')
@section('breadcrumb-current', 'Cierre')
@section('page-script', 'operations/cash-close')

@section('content')
<section data-cash-close class="space-y-6" aria-labelledby="cash-close-title" aria-busy="true">
    <header class="rounded-3xl bg-gintly-sidebar p-6 text-white sm:p-8"><p class="text-sm font-semibold text-gintly-active">Fin de jornada</p><h1 id="cash-close-title" class="mt-2 text-3xl font-bold">Cierre de caja</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Cuenta NIO y USD por separado. El esperado y la diferencia permanecen ocultos hasta que el cierre quede registrado.</p></header>
    <p data-cash-notice tabindex="-1" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm" hidden></p>
    <p data-cash-loading role="status" class="rounded-2xl border border-slate-200 bg-white p-6">Consultando tu sesión…</p>
    <p data-cash-empty hidden class="rounded-2xl border border-slate-200 bg-white p-6 text-sm">No tienes una sesión abierta. <a class="font-semibold text-gintly-brand underline" href="{{ route('panel.operations.cash.history') }}">Consultar historial</a></p>
    <form data-cash-form hidden class="space-y-6" novalidate>
        <p data-cash-session class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-950"></p>
        @include('operations.partials.cash-grid')
        <label class="grid gap-2 text-sm font-semibold">Notas del cierre <span class="font-normal text-gintly-text-secondary">Opcional en cierre propio</span><textarea name="closing_notes" maxlength="500" rows="3" class="w-full rounded-xl border border-slate-300 p-3" aria-describedby="closing-notes-error"></textarea></label>
        <p id="closing-notes-error" data-error-for="closing_notes" hidden class="text-sm text-red-700"></p>
        <p data-cash-field-error role="alert" hidden class="text-sm text-red-700"></p>
        <div class="flex flex-wrap gap-3"><button data-cash-submit type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-semibold text-white disabled:opacity-50">Revisar y cerrar</button><a href="{{ route('panel.operations.cash') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-semibold">Volver a Mi caja</a></div>
    </form>
    <dialog data-cash-confirm-dialog aria-labelledby="cash-confirm-title" class="m-auto w-[min(480px,calc(100%-2rem))] rounded-3xl border-0 p-6 shadow-2xl backdrop:bg-slate-950/60"><h2 id="cash-confirm-title" class="text-xl font-bold">Confirmar cierre irreversible</h2><p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Se registrará la evidencia NIO y USD. La sesión dejará de estar abierta incluso si se detecta un descuadre.</p><p data-cash-confirm-totals class="mt-4 rounded-xl bg-slate-50 p-4 font-semibold"></p><div class="mt-6 flex flex-wrap justify-end gap-3"><button data-cash-confirm-cancel type="button" class="min-h-11 rounded-xl border border-slate-300 px-5 font-semibold">Volver</button><button data-cash-confirm-submit type="button" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white disabled:opacity-50">Cerrar definitivamente</button></div></dialog>
</section>
@endsection
