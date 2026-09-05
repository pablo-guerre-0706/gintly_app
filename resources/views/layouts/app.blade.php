<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Gintly ERP')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cloudflare.com">
    @stack('styles')
</head>
<body class="bg-slate-100 font-sans antialiased text-slate-900">

    <!-- CONTENEDOR MAESTRO FLEX: Une todo de forma horizontal -->
    <div class="flex h-screen w-full overflow-hidden">

        <!-- 1. Tu nueva Sidebar (A la izquierda) -->
        @include('layouts.partials.sidebar')

        <!-- 2. Columna Derecha (Contiene la Navbar arriba y el Dashboard abajo) -->
        <div class="flex-1 h-full overflow-y-auto bg-slate-50 flex flex-col">
            
            <!-- LA NAVBAR (Va aquí, siempre arriba del contenido) -->
            @include('layouts.partials.navbar')

            <!-- EL CONTENIDO (Aquí abajo se pintará tu Dashboard o cualquier otra página) -->
            <div class="p-6 flex-1">
                @yield('content')
            </div>

        </div>

    </div>

    @stack('scripts')
</body>
</html>
