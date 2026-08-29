<!DOCTYPE html>
<html
    lang="es"
    data-page="@yield('page-script')"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ERP Multitenant') - Sistema</title>

    <!-- Metadatos de Infraestructura para el Cliente HTTP (jQuery/AJAX) -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="api-base-url" content="{{ url('/api/v1') }}">
    <meta name="login-url" content="{{ url('/login') }}">

    <!-- Inyección única y automática de Assets compilados por Vite -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>
<body class="bg-stone-100 font-sans antialiased text-neutral-900">

    <!-- Contenedor maestro de la aplicación -->
        <div class="mx-auto flex min-h-screen max-w-full bg-white shadow-sm overflow-hidden">

        <!-- COMPONENTE SIDEBAR GLOBAL (Ancho estándar de barra lateral) -->
        <aside class="w-64 min-h-screen bg-slate-900 text-white shrink-0">
            @include('layouts.partials.sidebar')
        </aside>

        <!-- PANEL DERECHO CONSOLIDADO (Navbar + Contenido Central + Footer) -->
        <div class="flex flex-1 flex-col bg-stone-100">

            <!-- COMPONENTE NAVBAR GLOBAL (Alto estándar de cabecera) -->
            <header class="h-24 bg-white border-b border-neutral-200 flex items-center px-8 shrink-0">
                @include('layouts.partials.navbar')
            </header>

            <!-- CONTENIDO DINÁMICO DE CADA VISTA (Tus KPIs y pantallas pequeñas) -->
            <main class="flex-1 bg-stone-100">
                <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
                    @yield('content')
                </div>
            </main>

            <!-- 4. COMPONENTE FOOTER GLOBAL (Alto estándar de pie de página) -->
            <footer class="h-20 bg-white border-t border-neutral-200 flex items-center px-8 shrink-0">
                @include('layouts.partials.footer')
            </footer>

        </div>
    </div>
    @stack('scripts')
</body>
</html>
