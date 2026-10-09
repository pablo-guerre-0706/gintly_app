@extends('layouts.billing')
@section('content')
<section data-billing-page data-mode="{{ $billingMode }}" data-can-manage="{{ $canManageBilling ? 'true' : 'false' }}" data-dashboard-url="{{ route('dashboard') }}" data-management-url="{{ route('web.billing.index') }}" aria-labelledby="billing-title" aria-busy="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><p class="text-sm font-semibold text-gintly-brand" data-billing-business>Situación comercial del negocio</p><h1 id="billing-title" tabindex="-1" class="mt-2 text-3xl font-bold text-gintly-sidebar">{{ $billingMode === 'return' ? 'Confirmación de contratación' : 'Tu suscripción en Gintly' }}</h1><p class="mt-3 max-w-2xl text-base leading-7 text-gintly-text-secondary">El servidor confirma el acceso mediante una suscripción vigente o una concesión temporal explícita de evaluación. No solicitamos datos de tarjeta en Gintly.</p></div>
        <button type="button" data-billing-refresh class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold" disabled>Actualizar estado</button>
    </header>
    <p data-billing-loading role="status" class="mt-6 animate-pulse rounded-2xl bg-slate-200 p-6 motion-reduce:animate-none">Consultando tu sesión y situación comercial…</p>
    <div data-billing-notice role="status" tabindex="-1" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-6" hidden></div>
    <section data-billing-state class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-label="Estado de la suscripción" hidden>
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 data-subscription-status class="text-xl font-bold text-gintly-sidebar"></h2><span data-subscription-access class="rounded-full bg-slate-100 px-3 py-2 text-sm font-semibold"></span></div>
        <dl data-subscription-detail class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"></dl>
        <p data-subscription-pending class="mt-5 rounded-xl bg-sky-50 p-4 text-sm leading-6" hidden></p>
        <a data-billing-enter href="{{ route('dashboard') }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-gintly-sidebar px-5 py-3 font-semibold text-white" hidden>Continuar al panel</a>
    </section>
    <p data-billing-readonly class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6" hidden>Solo el propietario real activo puede gestionar la suscripción. Contacta al propietario para contratar o modificar el plan; tu cuenta únicamente consulta esta situación.</p>
    @if($canManageBilling)
    <section data-billing-owner class="mt-8 space-y-6" aria-labelledby="billing-choice-title" hidden>
        <h2 id="billing-choice-title" class="text-xl font-bold text-gintly-sidebar">Plan y periodicidad</h2>
        <form data-billing-form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" novalidate>
            <p data-form-error-summary role="alert" tabindex="-1" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800" hidden></p>
            <p id="billing-selection-help" data-billing-selection-help role="status" class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6" hidden></p>
            <div data-billing-recovery class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5" hidden>
                <p data-checkout-recovery-message class="text-sm leading-6"></p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" data-checkout-retry class="min-h-11 rounded-xl border border-amber-400 bg-white px-4 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-50">Recuperar el mismo intento</button>
                    <button type="button" data-checkout-new class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 py-2 font-semibold" hidden>Iniciar nuevo intento tras vencimiento</button>
                </div>
            </div>
            <fieldset data-billing-fields class="grid min-w-0 gap-5 sm:grid-cols-2">
                <div><label for="billing-plan" class="block text-sm font-semibold">Plan <span class="font-normal text-gintly-text-secondary">· Obligatorio</span></label><select id="billing-plan" name="plan" required aria-describedby="billing-selection-help billing-plan-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-base disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"></select><p id="billing-plan-error" data-error-for="plan" class="mt-2 text-sm text-red-700"></p></div>
                <div><label for="billing-period" class="block text-sm font-semibold">Periodicidad <span class="font-normal text-gintly-text-secondary">· Obligatorio</span></label><select id="billing-period" name="period" required aria-describedby="billing-selection-help billing-period-help billing-period-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-base disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"><option value="monthly">Mensual</option><option value="annual">Anual</option></select><p id="billing-period-help" class="mt-2 text-xs leading-5 text-gintly-text-secondary">Anual: 12 meses cobrados por adelantado, sin descuento.</p><p id="billing-period-error" data-error-for="period" class="mt-2 text-sm text-red-700"></p></div>
            </fieldset>
            <div data-billing-catalog class="mt-6 grid gap-4 lg:grid-cols-3"></div>
            <p data-billing-selection class="mt-6 rounded-xl bg-slate-50 p-4 text-base font-semibold" role="status"></p>
            <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Precios publicados en NIO. Lemon Squeezy mostrará el importe y la moneda cobrables antes de confirmar. Renovación recurrente según la periodicidad elegida; no hay prueba gratuita ni descuento anual.</p>
            <button type="submit" data-billing-submit class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-xl bg-gintly-sidebar px-5 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Continuar al checkout alojado</button>
        </form>
        <button type="button" data-cancel-renewal class="min-h-11 rounded-xl border border-red-300 bg-white px-5 py-3 font-semibold text-red-700" hidden>Cancelar renovación</button>
    </section>
    @endif
    <div data-billing-confirmation class="fixed inset-0 z-[100] overflow-y-auto bg-slate-950/50 p-3 sm:p-6" hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="billing-confirm-title" aria-describedby="billing-confirm-description" tabindex="-1" class="mx-auto my-4 w-full max-w-lg rounded-3xl bg-white p-6 shadow-xl sm:my-16 sm:p-8">
            <h2 id="billing-confirm-title" class="text-xl font-bold text-gintly-sidebar">Confirmar acción de suscripción</h2><p id="billing-confirm-description" data-confirm-description class="mt-4 break-words text-base leading-7"></p>
            <div class="mt-6 flex flex-wrap gap-3"><button type="button" data-confirm-no class="min-h-11 rounded-xl border border-slate-300 px-4 font-semibold">Volver</button><button type="button" data-confirm-yes class="min-h-11 rounded-xl bg-gintly-sidebar px-4 font-semibold text-white">Confirmar</button></div>
        </section>
    </div>
</section>
@endsection
