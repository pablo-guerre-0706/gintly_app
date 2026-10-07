@extends('layouts.billing')
@section('document-title', 'Acceso comercial pendiente')
@section('content')
<section class="rounded-3xl border border-amber-200 bg-white p-6 shadow-sm sm:p-10" aria-labelledby="billing-restricted-title" data-billing-restricted>
    <p class="text-sm font-semibold text-gintly-brand">Sesión autenticada · Acceso restringido</p>
    <h1 id="billing-restricted-title" class="mt-3 text-3xl font-bold text-gintly-sidebar">El negocio necesita acceso comercial vigente</h1>
    <p class="mt-4 max-w-2xl text-base leading-7 text-gintly-text-secondary">No se ha concedido acceso a esta pantalla operativa. Registrar un negocio o regresar del checkout no confirma un pago.</p>
    <p class="mt-3 text-sm">{{ $canManageBilling ? 'Consulta la suscripción o contrata un plan desde la gestión comercial.' : 'Contacta al propietario del negocio. Solo él puede contratar, cambiar el plan o cancelar la renovación.' }}</p>
    <a href="{{ route('web.billing.index') }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-gintly-sidebar px-5 py-3 font-semibold text-white">{{ $canManageBilling ? 'Ir a contratación y suscripción' : 'Consultar situación comercial' }}</a>
</section>
@endsection
