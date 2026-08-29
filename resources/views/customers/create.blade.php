@extends('layouts.panel')

@section('title', 'Registrar Cliente')

@section('content')
<main class="mx-auto w-full max-w-4xl bg-stone-100 px-6 py-7">
    <header class="mb-7">
        <h1 class="text-[28px] font-bold tracking-[-.035em] text-[#171717]">Nuevo Cliente</h1>
        <p class="mt-1.5 text-[10px] leading-5 text-[#777]">Registre los datos del cliente. Los campos con asterisco (*) son requeridos.</p>
    </header>

    <div class="rounded-2xl bg-white p-6 shadow-sm border border-[#E2E2E2]">
        <form id="formStoreCustomer" data-url="{{ route('customers.store') }}" novalidate class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <!-- Nombre -->
                <div class="form-group">
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Nombre Completo *</label>
                    <input type="text" name="name" required class="h-9 w-full rounded-lg border border-neutral-300 bg-white px-3 text-xs outline-none transition focus:border-cyan-800">
                    <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
                </div>

                <!-- Tipo Documento -->
                <div class="form-group">
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Tipo de Documento *</label>
                    <select name="document_type" required class="h-9 w-full rounded-lg border border-neutral-300 bg-white px-3 text-xs outline-none transition focus:border-cyan-800">
                        <option value="cedula">Cédula</option>
                        <option value="ruc">RUC</option>
                        <option value="pasaporte">Pasaporte</option>
                    </select>
                    <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
                </div>

                <!-- Número Documento -->
                <div class="form-group">
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Número de Documento</label>
                    <input type="text" name="document_number" class="h-9 w-full rounded-lg border border-neutral-300 bg-white px-3 text-xs outline-none transition focus:border-cyan-800">
                    <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
                </div>

                <!-- Teléfono -->
                <div class="form-group">
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Teléfono Celular</label>
                    <input type="text" name="phone_number" class="h-9 w-full rounded-lg border border-neutral-300 bg-white px-3 text-xs outline-none transition focus:border-cyan-800">
                    <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
                </div>

                <!-- Correo -->
                <div class="form-group">
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Correo Electrónico</label>
                    <input type="email" name="email" class="h-9 w-full rounded-lg border border-neutral-300 bg-white px-3 text-xs outline-none transition focus:border-cyan-800">
                    <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
                </div>

                <!-- Fecha de Nacimiento -->
                <div class="form-group">
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Fecha de Nacimiento</label>
                    <input type="date" name="birth_date" class="h-9 w-full rounded-lg border border-neutral-300 bg-white px-3 text-xs outline-none transition focus:border-cyan-800">
                    <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
                </div>

                <!-- Límite de Crédito -->
                <div class="form-group">
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Límite de Crédito (C$)</label>
                    <input type="number" name="credit_limit" min="0" step="0.01" value="0.00" class="h-9 w-full rounded-lg border border-neutral-300 bg-white px-3 text-xs outline-none transition focus:border-cyan-800">
                    <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="form-group">
                <label class="block text-xs font-semibold text-neutral-700 mb-1">Observaciones</label>
                <textarea name="notes" rows="3" class="w-full rounded-lg border border-neutral-300 bg-white p-3 text-xs outline-none transition focus:border-cyan-800"></textarea>
                <p class="error-field text-[9px] text-red-600 mt-1 hidden"></p>
            </div>

            <!-- Botones -->
            <div class="flex justify-end gap-3 pt-4 border-t border-neutral-200">
                <a href="{{ route('customers.view.index') }}" class="flex h-9 items-center px-4 rounded-lg border border-neutral-300 text-xs font-medium text-neutral-600 hover:bg-neutral-50 transition">
                    Cancelar
                </a>
                <button type="submit" class="h-9 px-5 rounded-lg bg-cyan-800 text-xs font-semibold text-white hover:bg-cyan-900 transition">
                    Guardar Cliente
                </button>
            </div>
        </form>
    </div>
</main>
@endsection
@section('scripts')
    @vite(['resources/js/modules/customers/create.js'])
@endsection

