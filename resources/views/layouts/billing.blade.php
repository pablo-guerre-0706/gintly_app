<!DOCTYPE html>
<html lang="es" data-page="billing/index">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="api-base-url" content="{{ url('/api/v1') }}">
    <meta name="login-url" content="{{ route('login') }}">
    <meta name="billing-url" content="{{ route('web.billing.index') }}">
    <title>@yield('document-title', 'Suscripción del negocio') · Gintly</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-gintly-text-primary antialiased">
    <a href="#billing-main" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:bg-white focus:p-3">Saltar al contenido</a>
    <header class="border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
            <a href="{{ route('landing') }}" class="inline-flex min-h-11 items-center gap-3 rounded-xl focus-visible:ring-2 focus-visible:ring-gintly-brand"><img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly" width="140" height="40" class="h-10 w-auto"><span class="text-sm font-semibold text-gintly-sidebar">Suscripción del negocio</span></a>
            <button type="button" data-logout class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold disabled:opacity-50"><span data-logout-label>Cerrar sesión</span><i data-logout-spinner class="fa-solid fa-circle-notch fa-spin hidden" aria-hidden="true" hidden></i></button>
        </div>
    </header>
    <main id="billing-main" tabindex="-1" class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:py-12">@yield('content')</main>
    <footer class="mx-auto max-w-6xl px-4 py-6 text-sm text-gintly-text-secondary">Gintly — Sistema de Gestión de Negocios. La contratación es independiente del registro de tu negocio.</footer>
</body>
</html>
