<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Configuración de Tienda - Gintly App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="p-6">
    <header class="flex items-center gap-4">
        <!-- Logo SVG blanco integrado -->
        <a href="{{ route('landing') }}" class="flex items-center justify-center w-12 h-12 rounded-full bg-white/5 border border-white/15 backdrop-blur-md hover:bg-white/10 transition-colors p-2.5">
            <svg class="w-full h-full" viewBox="0 0 43 47" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M43 32.6592C35.8361 36.8728 28.6639 41.078 21.5 45.2916C14.3361 41.078 7.16392 36.8728 0 32.6592C1.43278 31.8132 2.86557 30.9755 4.29835 30.1294L21.4918 40.2319C27.2311 36.8644 32.9623 33.4969 38.7016 30.1294L43 32.6592Z" fill="white"/>
                <path d="M43 23.9499C39.6733 25.9017 36.3548 27.8536 33.0282 29.8054C29.1827 32.0671 25.3372 34.3205 21.4918 36.5823C14.3278 32.3687 7.16392 28.1635 0 23.9499C1.43278 23.1039 2.86557 22.2662 4.29835 21.4201C10.0295 24.7876 15.7689 28.1551 21.5 31.5226C27.2311 28.1551 32.9705 24.7876 38.7016 21.4201L43 23.9499Z" fill="white"/>
                <path d="M43 14.3346C35.8278 18.5481 28.6639 22.7533 21.4918 26.9669C14.3278 22.7533 7.16392 18.5398 0 14.3346C7.16392 10.121 14.3278 5.91579 21.4918 1.70221C21.4918 3.76293 21.4918 5.83202 21.4918 7.89273L9.5766 14.8874C13.5456 17.2162 17.5146 19.5534 21.4918 21.8821C23.3857 20.768 25.2796 19.6539 27.1817 18.5398C29.5862 17.1324 31.9906 15.7251 34.3951 14.3262L30.0967 11.7964C27.2311 13.4801 24.3656 15.1639 21.5 16.8476V11.7964C24.3656 10.1126 27.2311 8.42885 30.0967 6.7451C34.3951 9.27492 38.6934 11.7964 43 14.3262V14.3346Z" fill="white"/>
            </svg>
        </a>

        <!-- Barra flotante central -->
        <nav class="nav-glass flex items-center">
            <a href="{{ route('landing') }}" class="nav-item rounded-l-full">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
                <span>Inicio</span>
            </a>

            <a href="{{ route('landing') }}#funciones" class="nav-item">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <rect width="7" height="7" x="3" y="3" rx="1"/>
                    <rect width="7" height="7" x="14" y="3" rx="1"/>
                    <rect width="7" height="7" x="14" y="14" rx="1"/>
                    <rect width="7" height="7" x="3" y="14" rx="1"/>
                </svg>
                <span>Funciones</span>
            </a>

            <a href="{{ route('landing') }}#modulos" class="nav-item">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path d="m21 16-4 4-4-4"/>
                    <path d="M17 20V4"/>
                    <path d="m3 8 4-4 4 4"/>
                    <path d="M7 4v16"/>
                </svg>
                <span>Módulos</span>
            </a>

            <a href="{{ route('landing') }}#planes" class="nav-item border-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>
                    <path d="M3 6h18"/>
                </svg>
                <span>Planes</span>
            </a>

            <!-- Botón de Registro asignado al paso 1 -->
            <a href="{{ route('register.step1') }}" class="ml-2 px-5 py-2 bg-cyan-700 hover:bg-cyan-800 text-white text-sm font-medium rounded-full transition-colors">
                Regístrate
            </a>
        </nav>
    </header>
</body>
</html>