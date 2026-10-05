@extends('layouts.panel')

@section('document-title', 'Registrar cliente')
@section('page-title', 'Registrar cliente')
@section('breadcrumb-root', 'Clientes')
@section('breadcrumb-current', 'Registrar cliente')
@section('page-script', 'customers/create')

@section('content')
<section class="mx-auto w-full max-w-4xl space-y-6" aria-labelledby="customer-create-title">
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-widest text-gintly-brand">Clientes</p>
        <h1 id="customer-create-title" class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Registrar cliente</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Los datos de identidad y contacto ayudan a reconocer al cliente. Los campos opcionales pueden completarse después.</p>
    </header>

    <form data-customer-create novalidate class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p data-form-error-summary role="alert" tabindex="-1" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800" hidden></p>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="customer-name" class="block text-sm font-semibold">Nombre completo <span class="text-gintly-text-secondary">· Obligatorio</span></label>
                <input id="customer-name" name="name" type="text" required maxlength="160" autocomplete="name" aria-describedby="customer-name-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm" placeholder="Nombre registrado del cliente">
                <p id="customer-name-error" data-error-for="name" class="mt-1 text-sm text-red-700"></p>
            </div>
            <div>
                <label for="customer-document-type" class="block text-sm font-semibold">Tipo de documento <span class="text-gintly-text-secondary">· Obligatorio</span></label>
                <select id="customer-document-type" name="document_type" required aria-describedby="customer-document-type-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm">
                    <option value="cedula">Cédula nicaragüense</option><option value="ruc">RUC</option><option value="pasaporte">Pasaporte</option>
                </select>
                <p id="customer-document-type-error" data-error-for="document_type" class="mt-1 text-sm text-red-700"></p>
            </div>
            <div>
                <label for="customer-document" class="block text-sm font-semibold">Número de documento <span class="text-gintly-text-secondary">· Opcional</span></label>
                <input id="customer-document" name="document_number" type="text" maxlength="30" inputmode="numeric" autocomplete="off" placeholder="001-010100-0001A" aria-describedby="customer-document-help customer-document-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm">
                <p id="customer-document-help" class="mt-1 text-xs text-gintly-text-secondary">La máscara de cédula ayuda a detectar errores de captura; el servidor valida unicidad y longitud.</p>
                <p id="customer-document-error" data-error-for="document_number" class="mt-1 text-sm text-red-700"></p>
            </div>
            <div>
                <label for="customer-phone" class="block text-sm font-semibold">Teléfono internacional <span class="text-gintly-text-secondary">· Opcional</span></label>
                <input id="customer-phone" name="phone_number" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="+50588888888" aria-describedby="customer-phone-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm">
                <p id="customer-phone-error" data-error-for="phone_number" class="mt-1 text-sm text-red-700"></p>
            </div>
            <div>
                <label for="customer-email" class="block text-sm font-semibold">Correo electrónico <span class="text-gintly-text-secondary">· Opcional</span></label>
                <input id="customer-email" name="email" type="email" maxlength="180" autocomplete="email" aria-describedby="customer-email-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm">
                <p id="customer-email-error" data-error-for="email" class="mt-1 text-sm text-red-700"></p>
            </div>
            <div>
                <label for="customer-birth" class="block text-sm font-semibold">Fecha de nacimiento <span class="text-gintly-text-secondary">· Opcional</span></label>
                <input id="customer-birth" name="birth_date" type="date" autocomplete="bday" aria-describedby="customer-birth-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm">
                <p id="customer-birth-error" data-error-for="birth_date" class="mt-1 text-sm text-red-700"></p>
            </div>
            <div>
                <label for="customer-credit" class="block text-sm font-semibold">Límite de crédito <span class="text-gintly-text-secondary">· Opcional</span></label>
                <input id="customer-credit" name="credit_limit" type="text" inputmode="decimal" autocomplete="off" placeholder="0.00" aria-describedby="customer-credit-help customer-credit-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm">
                <p id="customer-credit-help" class="mt-1 text-xs text-gintly-text-secondary">Importe en moneda base del negocio; dejar vacío no envía un límite.</p>
                <p id="customer-credit-error" data-error-for="credit_limit" class="mt-1 text-sm text-red-700"></p>
            </div>
        </div>
        <div>
            <label for="customer-notes" class="block text-sm font-semibold">Observaciones <span class="text-gintly-text-secondary">· Opcional</span></label>
            <textarea id="customer-notes" name="notes" rows="3" maxlength="500" aria-describedby="customer-notes-error" class="mt-2 w-full rounded-xl border border-slate-300 p-4 text-sm"></textarea>
            <p id="customer-notes-error" data-error-for="notes" class="mt-1 text-sm text-red-700"></p>
        </div>
        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('web.customers.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-semibold">Cancelar</a>
            <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white disabled:opacity-60">Guardar cliente</button>
        </div>
    </form>

    <div data-customer-created hidden tabindex="-1" role="status" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-8 text-emerald-900">
        <h2 class="text-xl font-bold">Cliente registrado</h2>
        <p class="mt-2 text-sm">Se creó correctamente <strong data-customer-created-name></strong>.</p>
        <a href="{{ route('web.customers.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-gintly-brand px-5 text-sm font-bold text-white">Volver al directorio</a>
    </div>
</section>
@endsection
