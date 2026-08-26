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
<body class="bg-slate-100 font-sans text-slate-800 antialiased">

    <!-- Contenedor Principal de la Interfaz (Estructura Base Flexbox) -->
    <div class="flex h-screen overflow-hidden">

        <!-- 1. COMPONENTE SIDEBAR GLOBAL -->
        <!-- Aquí invocarás tu componente dinámico protegido por @can y @role -->
        @include('layouts.partials.sidebar')

        <!-- Contenedor del contenido derecho (Navbar + Vista) -->
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">

            <!-- 2. COMPONENTE NAVBAR GLOBAL -->
            @include('layouts.partials.navbar')

            <!-- 3. CONTENIDO DINÁMICO DE CADA VISTA -->
            <main class="flex-1">
                <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
                    @yield('content')
                </div>
            </main>

            <!-- 4. COMPONENTE FOOTER GLOBAL -->
            @include('layouts.partials.footer')

        </div>
    </div>

</body>
</html>
