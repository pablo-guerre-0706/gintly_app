@extends('layouts.panel')

@section('document-title', 'Acceso de usuario')
@section('page-title', 'Rol y perfiles')
@section('breadcrumb-root', 'Usuarios')
@section('breadcrumb-current', 'Acceso')
@section('page-script', 'organization/users/access')

@section('content')
<section
    class="mx-auto max-w-5xl space-y-6"
    data-user-access-root
    data-user-id="{{ request()->route('user') }}"
    data-user-endpoint-template="/users/__USER__/profiles"
    data-user-role-endpoint-template="/users/__USER__/role"
    data-user-profiles-endpoint-template="/users/__USER__/profiles"
    data-branches-endpoint="/branches"
    data-profiles-endpoint="/operative-profiles"
    aria-labelledby="user-access-title"
>
    <header class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gintly-brand">Personal y organización</p>
            <h1 id="user-access-title" class="mt-2 text-2xl font-bold tracking-tight text-gintly-text-primary sm:text-3xl">Rol y perfiles del usuario</h1>
            <p class="mt-3 text-sm leading-6 text-gintly-text-secondary">Administra el rol humano y, cuando corresponda, los perfiles combinables de ROL-03.</p>
        </div>
        <a href="{{ route('panel.users.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver a usuarios
        </a>
    </header>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-access-loading role="status">
        <i class="fa-solid fa-circle-notch fa-spin me-2" aria-hidden="true"></i>
        Cargando acceso del usuario…
    </div>

    <div class="rounded-3xl border border-red-200 bg-red-50 p-6 text-red-900" data-access-fatal role="alert" hidden>
        <h2 class="font-bold">No fue posible cargar el acceso</h2>
        <p class="mt-2 text-sm" data-access-fatal-message></p>
        <button type="button" class="mt-4 min-h-11 rounded-xl bg-red-900 px-4 text-sm font-bold text-white" data-access-retry>Reintentar</button>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]" data-access-content hidden>
        <aside class="self-start rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <span class="grid size-14 place-items-center rounded-2xl bg-gintly-brand/10 text-xl font-bold text-gintly-brand" data-user-initials aria-hidden="true"></span>
            <h2 class="mt-5 text-xl font-bold text-gintly-text-primary" data-user-name></h2>
            <p class="mt-1 break-all text-sm text-gintly-text-secondary" data-user-email></p>
            <dl class="mt-6 space-y-4 border-t border-slate-200 pt-5 text-sm">
                <div><dt class="text-gintly-text-secondary">Rol actual</dt><dd class="mt-1 font-bold text-gintly-text-primary" data-user-role-label></dd></div>
                <div><dt class="text-gintly-text-secondary">Sucursal</dt><dd class="mt-1 font-bold text-gintly-text-primary" data-user-branch-label></dd></div>
                <div><dt class="text-gintly-text-secondary">Estado</dt><dd class="mt-1 font-bold text-gintly-text-primary" data-user-status></dd></div>
            </dl>
        </aside>

        <div class="space-y-6">
            <form class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-role-form novalidate>
                <div>
                    <h2 class="text-lg font-bold text-gintly-text-primary">Asignación de rol</h2>
                    <p class="mt-1 text-sm leading-6 text-gintly-text-secondary">Solo se muestran roles humanos que tu cuenta puede conceder. El Backend valida nuevamente el rango.</p>
                </div>

                <div>
                    <label for="access-role" class="mb-2 block text-sm font-bold text-gintly-text-primary">Rol</label>
                    <select id="access-role" name="role" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="access-role-error"></select>
                    <p id="access-role-error" class="mt-2 text-sm text-red-700" data-error-for="role" role="alert" hidden></p>
                </div>

                <div data-role-operator-fields hidden>
                    <label for="access-branch" class="mb-2 block text-sm font-bold text-gintly-text-primary">Sucursal de ROL-03</label>
                    <select id="access-branch" name="branch_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none transition focus:border-gintly-brand focus:ring-2 focus:ring-gintly-brand/20" aria-describedby="access-branch-error">
                        <option value="">Selecciona una sucursal</option>
                    </select>
                    <p id="access-branch-error" class="mt-2 text-sm text-red-700" data-error-for="branch_id" role="alert" hidden></p>
                </div>

                <fieldset class="rounded-2xl border border-slate-200 p-5" data-role-profile-fields hidden>
                    <legend class="px-2 text-sm font-bold text-gintly-text-primary">Perfiles requeridos al asignar ROL-03</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2" data-role-profile-options></div>
                    <p class="mt-3 text-sm text-red-700" data-error-for="profiles" role="alert" hidden></p>
                </fieldset>

                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" data-role-error role="alert" hidden></div>
                <p class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-self-notice hidden>No puedes modificar tu propio rol desde esta vía.</p>

                <div class="flex justify-end border-t border-slate-200 pt-5">
                    <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-bold text-white transition hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2" data-role-submit>Guardar rol</button>
                </div>
            </form>

            <form class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-profiles-form novalidate hidden>
                <div>
                    <h2 class="text-lg font-bold text-gintly-text-primary">Perfiles operativos</h2>
                    <p class="mt-1 text-sm leading-6 text-gintly-text-secondary">Reemplaza el conjunto completo de perfiles de este usuario ROL-03.</p>
                </div>
                <fieldset>
                    <legend class="sr-only">Perfiles operativos asignados</legend>
                    <div class="grid gap-3 sm:grid-cols-2" data-profile-options></div>
                    <p class="mt-3 text-sm text-red-700" data-error-for="profiles" role="alert" hidden></p>
                </fieldset>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" data-profiles-error role="alert" hidden></div>
                <div class="flex justify-end border-t border-slate-200 pt-5">
                    <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-6 text-sm font-bold text-white transition hover:bg-gintly-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand focus-visible:ring-offset-2" data-profiles-submit>Guardar perfiles</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
