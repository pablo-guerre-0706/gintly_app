@extends('layouts.registration')

@section('content')
<div data-registration>
    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Registra tu negocio</h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">Completa tu cuenta propietaria y los datos básicos del negocio. Solo el último paso envía el registro.</p>
    <ol class="my-6 grid grid-cols-4 gap-2 text-xs sm:text-sm" aria-label="Etapas del registro">
        @foreach(['Cuenta', 'Negocio', 'Revisión', 'Resultado'] as $stageLabel)
            <li data-register-progress="{{ $loop->iteration }}" class="registration-progress"><span class="block">{{ $loop->iteration }}.</span>{{ $stageLabel }}</li>
        @endforeach
    </ol>
    <div id="registration-feedback" data-register-feedback role="alert" tabindex="-1" class="hidden mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <p data-register-message></p>
        <ul data-register-summary class="mt-2 space-y-1"></ul>
        <p data-register-wait class="mt-2"></p>
        <a data-register-dashboard hidden href="{{ route('dashboard') }}" class="registration-link mt-3 inline-flex min-h-11 items-center font-semibold">Ir a mi panel</a>
    </div>
    <p data-register-status role="status" aria-live="polite" class="mb-3 text-sm text-slate-600"></p>
    <noscript><p class="rounded-xl bg-amber-50 p-4 text-amber-900">Este registro requiere JavaScript para enviar los datos de forma segura. Actívalo y recarga; no se ha enviado ningún dato.</p></noscript>
    <form data-register-form method="POST" action="{{ url('/api/v1/auth/register') }}" novalidate hidden>
        <fieldset data-register-stage="1" class="space-y-4">
            <legend data-register-heading tabindex="-1" class="mb-4 text-lg font-semibold">Cuenta propietaria</legend>
            <p class="text-sm leading-relaxed text-slate-600">Esta será la cuenta del propietario. Los demás usuarios se administran después de iniciar sesión.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-registration-field path="owner.first_name" label="Nombres" autocomplete="given-name" maxlength="150" />
                <x-registration-field path="owner.last_name" label="Apellidos" autocomplete="family-name" maxlength="150" />
            </div>
            <x-registration-field path="owner.email" label="Correo electrónico" type="email" autocomplete="username" maxlength="180" />
            <x-registration-field path="owner.password" label="Contraseña" type="password" autocomplete="new-password" help="Al menos 12 caracteres con letras, números y símbolos. Se conservan exactamente los espacios. El servidor comprueba también si la contraseña está comprometida." />
            <x-registration-field path="owner.password_confirmation" label="Confirmación de contraseña" type="password" autocomplete="new-password" />
        </fieldset>
        <fieldset data-register-stage="2" class="space-y-4" hidden>
            <legend data-register-heading tabindex="-1" class="mb-4 text-lg font-semibold">Datos del negocio</legend>
            <x-registration-field path="business.name" label="Nombre del negocio" autocomplete="organization" maxlength="150" />
            <div class="space-y-1.5">
                <label for="business-timezone" class="block text-sm font-semibold">Zona horaria <span class="font-normal text-slate-500">· Obligatorio</span></label>
                <select id="business-timezone" name="business.timezone" required class="registration-input w-full" aria-describedby="business-timezone-help business-timezone-error">
                    @foreach(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC) as $timezone)
                        <option value="{{ $timezone }}" @selected($timezone === config('app.timezone'))>{{ $timezone }}</option>
                    @endforeach
                </select>
                <p id="business-timezone-help" class="text-sm text-slate-500">Selecciona la zona horaria en la que opera tu negocio.</p>
                <p id="business-timezone-error" data-register-error="business.timezone" class="hidden text-sm text-rose-700"></p>
            </div>
        </fieldset>
        <section data-register-stage="3" hidden aria-labelledby="registration-review-title">
            <h3 id="registration-review-title" data-register-heading tabindex="-1" class="text-lg font-semibold">Revisa antes de crear</h3>
            <dl class="my-5 space-y-4 rounded-2xl bg-slate-50 p-5">
                @foreach(['owner.name' => 'Propietario', 'owner.email' => 'Correo', 'business.name' => 'Negocio', 'business.timezone' => 'Zona horaria'] as $key => $reviewLabel)
                    <div><dt class="text-sm text-slate-500">{{ $reviewLabel }}</dt><dd data-register-review="{{ $key }}" class="mt-1 break-words font-medium text-slate-900"></dd></div>
                @endforeach
            </dl>
            <p class="text-sm leading-relaxed text-slate-600">Al confirmar se creará el negocio y tu cuenta propietaria. Esta pantalla no contrata planes ni realiza pagos. La contraseña no se muestra en el resumen.</p>
        </section>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button data-register-back type="button" class="registration-secondary hidden px-5">Atrás</button>
            <button data-register-submit type="submit" class="registration-primary flex-1 px-5">Continuar</button>
        </div>
        <p data-register-recovery class="hidden mt-4 text-sm leading-relaxed text-amber-900">No cambies los datos ni recargues esta página mientras recuperas el resultado. Cerrar o recargar no revierte una operación del servidor y este intento solo se conserva en memoria.</p>
    </form>
    <section data-register-result hidden aria-labelledby="registration-result-title">
        <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-2xl text-emerald-700" aria-hidden="true">✓</div>
        <h3 id="registration-result-title" tabindex="-1" class="text-2xl font-semibold text-slate-900">Tu negocio fue registrado</h3>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">Conserva este identificador público: lo necesitarás junto con tu correo y contraseña para iniciar sesión. No es un ID numérico ni el nombre comercial.</p>
        <dl class="my-5 space-y-4 rounded-2xl border border-slate-200 p-5">
            <div><dt class="text-sm text-slate-500">Identificador del negocio</dt><dd data-register-slug class="mt-1 break-all text-lg font-semibold text-gintly-brand"></dd></div>
            <div><dt class="text-sm text-slate-500">Correo del propietario</dt><dd data-register-email class="mt-1 break-all font-medium"></dd></div>
        </dl>
        <div class="flex flex-wrap gap-3">
            <button data-register-copy type="button" class="registration-secondary px-5">Copiar identificador</button>
            <a href="{{ route('login') }}" class="registration-primary inline-flex min-h-11 flex-1 items-center justify-center px-5">Iniciar sesión</a>
        </div>
        <p class="mt-4 text-sm text-slate-500">El registro no inicia sesión automáticamente. Introduce tu contraseña en el login.</p>
    </section>
</div>
@endsection
