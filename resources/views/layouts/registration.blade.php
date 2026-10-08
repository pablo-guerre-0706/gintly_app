<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="api-base-url" content="{{ url('/api/v1') }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>Registrar negocio · Gintly</title>
    @vite(['resources/css/app.css', 'resources/js/modules/registration/wizard.js'])
</head>
<body class="registration-page min-h-dvh bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 p-3 font-sans text-slate-800 sm:p-6">
    <main class="mx-auto grid w-full max-w-6xl overflow-hidden rounded-[28px] border border-white bg-white shadow-xl shadow-slate-900/5 lg:grid-cols-[38%_minmax(0,1fr)]">
        <aside class="relative hidden overflow-hidden bg-gintly-sidebar p-10 text-white lg:flex lg:flex-col lg:justify-between" aria-label="Gintly">
            <img src="{{ asset('images/bussy.png') }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-35">
            <div class="relative space-y-8">
                <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly" width="140" height="40" class="h-10 w-auto object-contain">
                <div class="space-y-4 pt-12">
                    <h1 class="text-3xl font-semibold leading-tight">El primer paso para organizar tu negocio.</h1>
                    <p class="text-base leading-relaxed text-slate-100">Registra tu negocio y tu cuenta propietaria. Después podrás iniciar sesión con el identificador asignado.</p>
                </div>
            </div>
            <p class="relative pt-16 text-sm text-slate-200">Tus datos de acceso permanecen privados.</p>
        </aside>
        <div class="min-w-0 p-5 sm:p-8 lg:p-10">
            <header class="mb-6 flex items-center justify-between gap-4">
                <a href="{{ route('landing') }}" class="registration-link inline-flex min-h-11 items-center gap-2 text-sm font-medium text-gintly-brand"><span aria-hidden="true">←</span> Volver al inicio</a>
                <a href="{{ route('login') }}" class="registration-link inline-flex min-h-11 items-center text-sm font-semibold text-gintly-brand">Iniciar sesión</a>
            </header>
            @yield('content')
        </div>
    </main>
</body>
</html>
