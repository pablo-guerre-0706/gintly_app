@extends('layouts.panel')

@section('document-title', 'Crear usuario')
@section('page-title', 'Crear usuario')
@section('breadcrumb-root', 'Usuarios')
@section('breadcrumb-current', 'Crear usuario')
@section('page-script', 'organization/users/create')

@section('content')
<section
    class="mx-auto max-w-4xl"
    data-user-create-root
    data-users-endpoint="/users"
    data-branches-endpoint="/branches"
    data-profiles-endpoint="/operative-profiles"
    data-users-url="{{ route('panel.users.index') }}"
    data-access-url-template="{{ url('/organization/users/__USER__/access') }}"
    aria-labelledby="create-user-title"
>
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Personal y organización</p>
        <h1 id="create-user-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Crear usuario</h1>
        <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Registra una cuenta humana dentro del rango que tu rol puede administrar.</p>
    </header>

    <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-create-loading role="status">
        <i class="fa-solid fa-circle-notch fa-spin me-2" aria-hidden="true"></i>
        Cargando opciones autorizadas…
    </div>

    <div class="mt-6 rounded-3xl border border-red-200 bg-red-50 p-6 text-red-900" data-create-fatal role="alert" hidden>
        <h2 class="font-bold">No fue posible preparar el formulario</h2>
        <p class="mt-2 text-sm" data-create-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl bg-red-900 px-4 text-sm font-bold text-white" data-create-retry>Reintentar</button>
    </div>

    <form class="mt-6 space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-user-create-form novalidate hidden>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="user-name" class="mb-2 block text-sm font-bold text-gintly-text-primary">Nombre completo</label>
                <input id="user-name" name="name" type="text" required maxlength="150" autocomplete="name" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="user-name-error">
                <p id="user-name-error" class="mt-2 text-sm text-red-700" data-error-for="name" role="alert" hidden></p>
            </div>

            <div class="sm:col-span-2">
                <label for="user-email" class="mb-2 block text-sm font-bold text-gintly-text-primary">Correo electrónico</label>
                <input id="user-email" name="email" type="email" required maxlength="180" autocomplete="email" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="user-email-error">
                <p id="user-email-error" class="mt-2 text-sm text-red-700" data-error-for="email" role="alert" hidden></p>
            </div>

            <div>
                <label for="user-password" class="mb-2 block text-sm font-bold text-gintly-text-primary">Contraseña temporal</label>
                <input id="user-password" name="password" type="password" required minlength="12" autocomplete="new-password" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="password-help user-password-error">
                <p id="password-help" class="mt-2 text-xs leading-5 text-gintly-text-secondary">Mínimo 12 caracteres, con letras, números y símbolos.</p>
                <p id="user-password-error" class="mt-2 text-sm text-red-700" data-error-for="password" role="alert" hidden></p>
            </div>

            <div>
                <label for="user-password-confirmation" class="mb-2 block text-sm font-bold text-gintly-text-primary">Confirmar contraseña</label>
                <input id="user-password-confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="user-password-confirmation-error">
                <p id="user-password-confirmation-error" class="mt-2 text-sm text-red-700" data-error-for="password_confirmation" role="alert" hidden></p>
            </div>

            <div>
                <label for="user-role" class="mb-2 block text-sm font-bold text-gintly-text-primary">Rol</label>
                <select id="user-role" name="role" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="user-role-error"></select>
                <p id="user-role-error" class="mt-2 text-sm text-red-700" data-error-for="role" role="alert" hidden></p>
            </div>

            <div data-operator-branch hidden>
                <label for="user-branch" class="mb-2 block text-sm font-bold text-gintly-text-primary">Sucursal de ROL-03</label>
                <select id="user-branch" name="branch_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="user-branch-error">
                    <option value="">Selecciona una sucursal</option>
                </select>
                <p id="user-branch-error" class="mt-2 text-sm text-red-700" data-error-for="branch_id" role="alert" hidden></p>
            </div>
        </div>

        <fieldset class="rounded-2xl border border-slate-200 p-5" data-operator-profiles hidden>
            <legend class="px-2 text-sm font-bold text-gintly-text-primary">Perfiles operativos combinables</legend>
            <p class="mb-4 text-sm text-gintly-text-secondary">Selecciona al menos un perfil para ROL-03.</p>
            <div class="grid gap-3 sm:grid-cols-2" data-profile-options></div>
            <p class="mt-3 text-sm text-red-700" data-error-for="profiles" role="alert" hidden></p>
        </fieldset>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" data-create-error role="alert" hidden></div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('panel.users.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Cancelar</a>
            <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-bold text-white transition hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2" data-create-submit>Crear usuario</button>
        </div>
    </form>
</section>
@endsection
