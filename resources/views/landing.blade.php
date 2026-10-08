<!DOCTYPE html>
<html
    lang="es"
    data-page="landing/index"
    class="scroll-smooth"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Gintly — Sistema de Gestión de Negocios')</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="api-base-url" content="{{ url('/api/v1') }}">
    <meta name="login-url" content="{{ route('login') }}">

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>
<body
    class="min-h-screen overflow-x-clip bg-cover bg-center bg-no-repeat font-sans text-white antialiased selection:bg-[#146F8A] selection:text-white md:bg-fixed"
    style="background-image: url('{{ asset('images/backgroundhero.png') }}');"
>

    <!-- CONTENEDOR PRINCIPAL HERO -->
    <div class="mx-auto w-full max-w-[1440px] px-4 py-4 sm:px-6 sm:py-6 lg:px-8">

        <!-- NAVBAR HEADER -->
        <header class="mb-4 flex items-center justify-between gap-4 pt-2 transition-all duration-500 lg:mb-12">
            
            <a href="{{ route('landing') }}" class="flex shrink-0 items-center transition-transform duration-300 hover:scale-105" aria-label="Gintly, ir al inicio">
                <div class="flex h-14 w-14 items-center justify-center rounded-full border border-white/20 bg-white/4 backdrop-blur-md shadow-[0_8px_32px_0_rgba(0,0,0,0.25)] p-2.5 transition-all duration-300 hover:bg-white/8">
                    <img 
                        src="{{ asset('images/logo.png') }}" 
                        alt="Logo" 
                        class="h-full w-full object-contain"
                        data-image-fallback
                    >
                    <svg class="hidden h-6 w-6 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                </div>
            </a>

            <nav class="hidden h-14 min-w-0 flex-1 items-center justify-between rounded-full border border-white/20 bg-white/[0.04] p-1.5 pl-6 pr-1.5 backdrop-blur-md shadow-[0_8px_32px_0_rgba(0,0,0,0.25)] transition-all duration-300 lg:flex">
                <ul class="flex flex-1 items-center justify-evenly text-sm font-medium text-slate-200 max-w-3xl mx-auto px-4">
                    
                    <li>
                        <a href="#inicio" class="flex items-center gap-2.5 py-2 px-5 text-slate-200 hover:text-white rounded-full transition-all duration-300 ease-in-out hover:bg-[#12627a] hover:shadow-lg hover:-translate-y-0.5">
                            <svg class="h-4 w-4 shrink-0 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            </svg>
                            <span class="text-sm font-normal">Inicio</span>
                        </a>
                    </li>

                    <li>
                        <a href="#funciones" class="flex items-center gap-2.5 py-2 px-5 text-slate-200 hover:text-white rounded-full transition-all duration-300 ease-in-out hover:bg-[#12627a] hover:shadow-lg hover:-translate-y-0.5">
                            <svg class="h-4 w-4 shrink-0 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                                <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                                <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                                <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                                <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                            </svg>
                            <span class="text-sm font-normal">Funciones</span>
                        </a>
                    </li>

                    <li>
                        <a href="#quienes-somos" class="flex items-center gap-2.5 py-2 px-5 text-slate-200 hover:text-white rounded-full transition-all duration-300 ease-in-out hover:bg-[#12627a] hover:shadow-lg hover:-translate-y-0.5">
                            <svg class="h-4 w-4 shrink-0 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span class="text-sm font-normal">Nosotros</span>
                        </a>
                    </li>

                    <li>
                        <a href="#planes" class="flex items-center gap-2.5 py-2 px-5 text-slate-200 hover:text-white rounded-full transition-all duration-300 ease-in-out hover:bg-[#12627a] hover:shadow-lg hover:-translate-y-0.5">
                            <svg class="h-4 w-4 shrink-0 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <line x1="3" y1="6" x2="21" y2="6"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                            <span class="text-sm font-normal">Planes</span>
                        </a>
                    </li>

                </ul>

                <a href="#contacto" class="shrink-0 rounded-full bg-[#12627a] px-6 py-2.5 text-sm font-medium text-white shadow-md transition-all duration-300 ease-in-out hover:bg-[#15728e] hover:shadow-lg hover:-translate-y-0.5">
                    Contáctanos
                </a>
            </nav>

            <div class="flex min-w-0 shrink-0 items-center lg:hidden">
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/25 bg-white/10 px-5 text-sm font-semibold text-white backdrop-blur-md transition hover:bg-white/20">
                    Iniciar sesión
                </a>
            </div>
        </header>

        <!-- SECCIÓN HERO -->
        <main>
            <section id="inicio" class="grid min-w-0 grid-cols-1 items-center gap-10 pt-4 pb-8 sm:pb-10 lg:min-h-[696px] lg:grid-cols-12 lg:py-0 lg:pb-16">

                <!-- Columna Izquierda: Textos y Botones -->
                <div class="min-w-0 flex flex-col justify-center space-y-6 sm:space-y-8 lg:col-span-6">
                    
                    <h1 class="break-words text-3xl font-semibold leading-tight tracking-tight text-white transition-all duration-500 sm:text-5xl lg:text-[56px] lg:leading-[64px]">
                        El sistema de facturación y gestión empresarial diseñado para Nicaragua.
                    </h1>

                    <p class="text-base font-normal leading-relaxed text-slate-200 sm:text-lg lg:text-[18px] lg:leading-[26px] transition-all duration-500">
                        Gestiona. Impulsa. Crece, Centraliza la administración de tu negocio con contabilidad automatizada, facturación, cobros, gestión de clientes e inventario en una sola plataforma en la nube.
                    </p>

                    <!-- Botones -->
                    <div class="flex flex-wrap items-center gap-3 pt-2 sm:gap-6">
                        <a href="{{ route('register.index') }}" 
                        class="inline-flex min-h-12 flex-1 items-center justify-center rounded-full bg-[#146F8A] px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#146F8A]/30 transition-all duration-300 ease-out hover:-translate-y-1 hover:scale-105 hover:bg-[#18809f] hover:shadow-xl hover:shadow-[#146F8A]/50 active:translate-y-0 active:scale-100 sm:flex-none sm:px-8">
                            Regístrate
                        </a>

                        <a href="{{ route('login') }}" 
                        class="inline-flex min-h-12 flex-1 items-center justify-center rounded-full bg-white px-6 py-3.5 text-sm font-semibold text-[#146F8A] shadow-lg shadow-black/20 transition-all duration-300 ease-out hover:-translate-y-1 hover:scale-105 hover:bg-slate-50 hover:text-[#125c73] hover:shadow-xl hover:shadow-white/30 active:translate-y-0 active:scale-100 sm:flex-none sm:px-8">
                            Iniciar sesión
                        </a>
                    </div>

                </div>

                <!-- Columna Derecha: Tarjetas Compuestas -->
                <div class="relative flex min-w-0 flex-col gap-6 lg:col-span-6">

                    <!-- Tarjetas Superiores -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        
                        <!-- Tarjeta 1: Personal -->
                        <div class="relative overflow-hidden rounded-[24px] bg-[#A9D5E2] p-6 text-slate-900 shadow-md transition-all duration-300 ease-in-out hover:-translate-y-1.5 hover:shadow-xl">
                            <div class="absolute -top-12 -right-12 h-40 w-40 rounded-full bg-[#3988A0]/20 blur-sm"></div>
                            <div class="relative z-10 flex items-center justify-between gap-4">
                                <div class="flex flex-col space-y-2">
                                    <span class="text-xs font-normal text-[#333333] opacity-70">Gestiona tu personal</span>
                                    <h3 class="text-xl font-semibold leading-snug text-black">Une a todo tu equipo de trabajo</h3>
                                </div>
                                <img src="{{ asset('images/profesionales.png') }}" alt="Profesionales" class="h-[70px] w-17.5 shrink-0 object-contain transition-transform duration-300 hover:scale-110">
                            </div>
                        </div>

                        <!-- Tarjeta 2: Inventario -->
                        <div class="relative overflow-hidden rounded-[24px] bg-[#A9D5E2] p-6 text-slate-900 shadow-md transition-all duration-300 ease-in-out hover:-translate-y-1.5 hover:shadow-xl">
                            <div class="absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-[#3988A0]/20 blur-sm"></div>
                            <div class="relative z-10 flex items-center justify-between gap-4">
                                <img src="{{ asset('images/cash.png') }}" alt="Inventario" class="h-[70px] w-[70px] shrink-0 object-contain transition-transform duration-300 hover:scale-110">
                                <div class="flex flex-col space-y-2 text-right">
                                    <span class="text-xs font-normal text-[#333333] opacity-70">Maneja tu inventario</span>
                                    <h3 class="text-xl font-semibold leading-snug text-black">Las mejores herramientas</h3>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Tarjeta Inferior Modelo Con Ondas -->
                    <div class="relative flex min-h-[320px] w-full items-end justify-center overflow-hidden rounded-[24px] bg-linear-to-b from-[#A9D5E2] to-white shadow-xl transition-all duration-300 hover:shadow-2xl sm:min-h-[380px] lg:min-h-[420px]">
                        <div class="absolute top-1/2 left-1/2 h-[300px] w-[300px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#146F8A]/20 bg-[#146F8A]/10"></div>
                        <div class="absolute top-1/2 left-1/2 h-[450px] w-[450px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#146F8A]/15 bg-[#146F8A]/10"></div>
                        <div class="absolute top-1/2 left-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#146F8A]/10 bg-transparent"></div>

                        <img 
                            src="{{ asset('images/men_hero.png') }}" 
                            alt="Gestión Hero" 
                            class="relative z-10 max-h-[460px] max-w-full object-contain object-bottom pt-4 transition-transform duration-500 ease-out hover:scale-105"
                        >
                    </div>

                </div>

            </section>
        </main>

    </div>

    <!-- SECCIÓN DE CARACTERÍSTICAS -->
    <section id="funciones" class="w-full bg-white text-slate-900 py-16 transition-colors duration-500">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                
                <div class="flex flex-col space-y-2 transition-transform duration-300 hover:-translate-y-1">
                    <h3 class="text-2xl font-bold leading-snug tracking-tight text-[#146F8A] lg:text-[24px]">
                        Facturación ágil y rápida
                    </h3>
                    <p class="text-sm font-normal leading-relaxed text-slate-500 sm:text-base">
                        Crea y envía facturas en segundos, sin errores ni papeleos.
                    </p>
                </div>

                <div class="flex flex-col space-y-2 transition-transform duration-300 hover:-translate-y-1">
                    <h3 class="text-2xl font-bold leading-snug tracking-tight text-[#146F8A] lg:text-[24px]">
                        Inventario en tiempo real
                    </h3>
                    <p class="text-sm font-normal leading-relaxed text-slate-500 sm:text-base">
                        Controla tu stock y almacén desde cualquier lugar.
                    </p>
                </div>

                <div class="flex flex-col space-y-2 transition-transform duration-300 hover:-translate-y-1">
                    <h3 class="text-2xl font-bold leading-snug tracking-tight text-[#146F8A] lg:text-[24px]">
                        Reportes financieros
                    </h3>
                    <p class="text-sm font-normal leading-relaxed text-slate-500 sm:text-base">
                        Visualiza tu negocio con informes claros y descargables.
                    </p>
                </div>

                <div class="flex flex-col space-y-2 transition-transform duration-300 hover:-translate-y-1">
                    <h3 class="text-2xl font-bold leading-snug tracking-tight text-[#146F8A] lg:text-[24px]">
                        Acceso inmediato
                    </h3>
                    <p class="text-sm font-normal leading-relaxed text-slate-500 sm:text-base">
                        Listo para facturar en minutos.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- SECCIÓN ¿QUIÉNES SOMOS? -->
    <section id="quienes-somos" class="w-full bg-white text-slate-900 pb-20 pt-6 transition-colors duration-500">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <div class="lg:col-span-6">
                    <div class="relative overflow-hidden rounded-[24px] shadow-lg transition-transform duration-500 hover:scale-[1.02]">
                        <img 
                            src="{{ asset('images/about_office.png') }}" 
                            alt="Maneja las finanzas" 
                            class="w-full h-auto object-cover max-h-[460px] transition-transform duration-700 hover:scale-105"
                        >
                    </div>
                </div>

                <div class="lg:col-span-6 flex flex-col items-start space-y-5">
                    
                    <span class="inline-flex items-center rounded-full bg-[#A9D5E2] px-4 py-1.5 text-xs font-medium text-[#11556a] transition-all duration-300 hover:scale-105">
                        Maneja las finanzas
                    </span>

                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-[#146F8A] leading-[1.15] transition-all duration-500">
                        Guiando tu camino financiero para elevar el destino de tu negocio.
                    </h2>

                    <h3 class="text-base font-bold text-slate-900 pt-2">
                        ¿Quiénes somos?
                    </h3>

                    <p class="text-sm sm:text-base text-slate-600 leading-relaxed font-normal">
                        En Gintly somos una plataforma de gestión empresarial que simplifica la administración de los negocios mediante herramientas inteligentes e intuitivas. Centralizamos procesos como la facturación, el control de inventario, la gestión de clientes y otras operaciones clave en un solo lugar, ayudando a nuestros usuarios a optimizar su tiempo, mejorar la toma de decisiones y hacer crecer su negocio con mayor eficiencia y confianza.
                    </p>

                </div>

            </div>
        </div>
    </section>

    <!-- SECCIÓN MISIÓN Y VISIÓN -->
    <section class="w-full bg-white text-slate-900 pb-20 pt-6 transition-colors duration-500">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <div class="lg:col-span-6 flex flex-col items-start space-y-6">
                    
                    <span class="inline-flex items-center rounded-full bg-[#A9D5E2] px-4 py-1.5 text-xs font-medium text-[#11556a] transition-all duration-300 hover:scale-105">
                        Las metas como empresa
                    </span>

                    <div class="flex flex-col space-y-2 transition-transform duration-300 hover:translate-x-1">
                        <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">
                            Nuestra misión
                        </h2>
                        <p class="text-sm sm:text-base text-slate-600 leading-relaxed font-normal">
                            Proporcionar a los propietarios y administradores de PyMEs una plataforma web intuitiva, práctica y accesible que automatice la gestión operativa de sus negocios, permitiéndoles optimizar procesos, mejorar la toma de decisiones y mantener el control de su empresa desde cualquier lugar.
                        </p>
                    </div>

                    <div class="flex flex-col space-y-2 pt-2 transition-transform duration-300 hover:translate-x-1">
                        <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">
                            Nuestra visión
                        </h2>
                        <p class="text-sm sm:text-base text-slate-600 leading-relaxed font-normal">
                            Ser el sistema de gestión y auditoría remota referente en el mercado para propietarios y administradores de negocios, reformando la gestión empresarial en una toma de decisiones inteligente, transparente y automatizada garantizando la rentabilidad de los negocios brindando una protección a nuestros usuarios.
                        </p>
                    </div>

                </div>

                <div class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
                    
                    <div class="relative overflow-hidden rounded-[24px] shadow-lg h-full transition-transform duration-500 hover:scale-[1.02]">
                        <img 
                            src="{{ asset('images/mission_team.png') }}" 
                            alt="Nuestra misión equipo" 
                            class="w-full h-full min-h-[380px] object-cover transition-transform duration-700 hover:scale-105"
                        >
                    </div>

                    <div class="flex flex-col gap-6">
                        <div class="relative overflow-hidden rounded-[24px] shadow-md transition-transform duration-500 hover:scale-[1.02]">
                            <img 
                                src="{{ asset('images/mission_dashboard.png') }}" 
                                alt="Dashboard Financiero" 
                                class="w-full h-[175px] object-cover transition-transform duration-700 hover:scale-105"
                            >
                        </div>
                        <div class="relative overflow-hidden rounded-[24px] shadow-md transition-transform duration-500 hover:scale-[1.02]">
                            <img 
                                src="{{ asset('images/mission_business.png') }}" 
                                alt="Business News" 
                                class="w-full h-[175px] object-cover transition-transform duration-700 hover:scale-105"
                            >
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <div class="max-w-7xl w-full mx-auto px-4">
    <div class="w-full h-[91px] bg-gray-200"></div>
</div>

<!-- SECCIÓN: LOS VALORES QUE NOS DEFINEN (FONDO BLANCO) -->
    <section id="valores" class="w-full bg-white text-slate-900 py-24 transition-colors duration-500 overflow-hidden">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            
            <!-- Cabecera de la sección -->
            <div class="flex flex-col items-start max-w-3xl mb-20">
                <span class="inline-flex items-center rounded-full bg-[#A9D5E2]/60 border border-[#146F8A]/20 px-4 py-1.5 text-xs font-medium text-[#11556a] backdrop-blur-md mb-4 transition-all duration-300 hover:scale-105">
                    Una plataforma digital completa
                </span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-slate-900 mb-6">
                    Los valores que nos definen
                </h2>
                <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                    Nuestros valores son la base de cada decisión, innovación y solución que desarrollamos. Reflejan nuestro compromiso de brindar una experiencia confiable, transparente y orientada al crecimiento de nuestros usuarios.
                </p>
            </div>

            <!-- Bloque 1: Claridad, Empatía, Integridad y Flexibilidad (Izquierda textos / Derecha imágenes) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center mb-28">
                
                <!-- Columna Izquierda: Línea de Tiempo de Valores -->
                <div class="lg:col-span-6 flex flex-col relative">
                    <!-- Línea vertical conectora -->
                    <div class="absolute left-[27px] top-6 bottom-6 w-0.5 bg-linear-to-b from-[#146F8A] via-[#146F8A]/40 to-transparent"></div>

                    <!-- Ítem 1: Claridad -->
                    <div class="relative flex items-start gap-6 pb-12 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Claridad</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Diseñamos interfaces y procesos simples, eliminando la complejidad innecesaria.
                            </p>
                        </div>
                    </div>

                    <!-- Ítem 2: Empatía -->
                    <div class="relative flex items-start gap-6 pb-12 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Empatía</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Entendemos de primera mano los retos y frustraciones de gestionar un negocio a distancia.
                            </p>
                        </div>
                    </div>

                    <!-- Ítem 3: Integridad Impecable -->
                    <div class="relative flex items-start gap-6 pb-12 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Integridad Impecable</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Tratamos la información de tu negocio con absoluto respeto y rigor. Garantizamos datos transparentes, precisos y seguros para que cada decisión que tomes esté respaldada por la verdad operativa de tu empresa.
                            </p>
                        </div>
                    </div>

                    <!-- Ítem 4: Flexibilidad Operativa -->
                    <div class="relative flex items-start gap-6 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Flexibilidad Operativa</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Nos adaptamos al ritmo y dinamismo del trabajo remoto. Diseñamos herramientas maleables que se integran a la forma de trabajar de cada empresa.
                            </p>
                        </div>
                    </div>

                </div>

                <!-- Columna Derecha: Imágenes Bloque 1 -->
                <div class="lg:col-span-6 flex flex-col gap-6">
                    <!-- Imagen Superior Grande -->
                    <div class="relative overflow-hidden rounded-[24px] shadow-lg transition-transform duration-500 hover:scale-[1.02]">
                        <img 
                            src="{{ asset('images/mission_team.png') }}" 
                            alt="Trabajo en equipo" 
                            class="w-full h-[260px] object-cover transition-transform duration-700 hover:scale-105"
                        >
                    </div>
                    <!-- Fila Inferior con Dos Imágenes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="relative overflow-hidden rounded-[24px] shadow-md transition-transform duration-500 hover:scale-[1.02]">
                            <img 
                                src="{{ asset('images/mission_business.png') }}" 
                                alt="Idea Innovadora" 
                                class="w-full h-[200px] object-cover transition-transform duration-700 hover:scale-105"
                            >
                        </div>
                        <div class="relative overflow-hidden rounded-[24px] shadow-md transition-transform duration-500 hover:scale-[1.02]">
                            <img 
                                src="{{ asset('images/mission_dashboard.png') }}" 
                                alt="Métricas y Gráficas" 
                                class="w-full h-[200px] object-cover transition-transform duration-700 hover:scale-105"
                            >
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bloque 2: Autonomía, Ahorro de Tiempo, Confiabilidad y Transparencia (Izquierda imágenes / Derecha textos) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center pt-8">
                
                <!-- Columna Izquierda: Imágenes Bloque 2 -->
                <div class="lg:col-span-6 flex flex-col gap-6 order-2 lg:order-1">
                    <!-- Imagen Superior Grande -->
                    <div class="relative overflow-hidden rounded-[24px] shadow-lg transition-transform duration-500 hover:scale-[1.02]">
                        <img 
                            src="{{ asset('images/about_office.png') }}" 
                            alt="Confianza y Soporte" 
                            class="w-full h-[260px] object-cover transition-transform duration-700 hover:scale-105"
                        >
                    </div>
                    <!-- Fila Inferior con Dos Imágenes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="relative overflow-hidden rounded-[24px] shadow-md transition-transform duration-500 hover:scale-[1.02]">
                            <img 
                                src="{{ asset('images/mission_dashboard.png') }}" 
                                alt="Laptop y Operaciones" 
                                class="w-full h-[200px] object-cover transition-transform duration-700 hover:scale-105"
                            >
                        </div>
                        <div class="relative overflow-hidden rounded-[24px] shadow-md transition-transform duration-500 hover:scale-[1.02]">
                            <img 
                                src="{{ asset('images/mission_team.png') }}" 
                                alt="Reunión de Negocios" 
                                class="w-full h-[200px] object-cover transition-transform duration-700 hover:scale-105"
                            >
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Línea de Tiempo de Valores Parte 2 -->
                <div class="lg:col-span-6 flex flex-col relative order-1 lg:order-2">
                    <!-- Línea vertical conectora -->
                    <div class="absolute left-[27px] top-6 bottom-6 w-0.5 bg-linear-to-b from-[#146F8A] via-[#146F8A]/40 to-transparent"></div>

                    <!-- Ítem 5: Autonomía -->
                    <div class="relative flex items-start gap-6 pb-12 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M18 20V10M12 20V4M6 20v-6"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Autonomía</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Creamos herramientas que permiten operar con libertad desde cualquier lugar.
                            </p>
                        </div>
                    </div>

                    <!-- Ítem 6: Ahorro de Tiempo -->
                    <div class="relative flex items-start gap-6 pb-12 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Ahorro de Tiempo</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Diseñamos cada función pensando en reducir clics, automatizar tareas repetitivas y devolverle horas clave al emprendedor.
                            </p>
                        </div>
                    </div>

                    <!-- Ítem 7: Confiabilidad -->
                    <div class="relative flex items-start gap-6 pb-12 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Confiabilidad</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Garantizamos una infraestructura estable y segura donde la información crítica del negocio siempre está disponible y protegida.
                            </p>
                        </div>
                    </div>

                    <!-- Ítem 8: Transparencia -->
                    <div class="relative flex items-start gap-6 group">
                        <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#146F8A] text-white shadow-lg shadow-[#146F8A]/20 transition-transform duration-300 group-hover:scale-110">
                            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                        <div class="flex flex-col pt-1">
                            <span class="text-xs font-bold text-[#146F8A] uppercase tracking-wider mb-1">Transparencia</span>
                            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                                Ofrecemos visibilidad total de la información para tomar decisiones seguras.
                            </p>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </section>

  <!-- SECCIÓN HERO PRINCIPAL CON FONDO PNG Y CAPA OSCURA -->
<section class="relative flex min-h-[620px] w-full flex-col items-center justify-center overflow-hidden bg-[#146F8A] py-20 sm:min-h-[700px] lg:min-h-[760px]">
    
    <!-- Fondo PNG personalizado -->
    <div class="absolute inset-0 pointer-events-none opacity-40 select-none">
        <img src="{{ asset('images/background2.png') }}" alt="Fondo Hero" class="w-full h-full object-cover">
    </div>

    
    <!-- Contenido Central: Títulos y Textos -->
    <div class="relative z-20 flex flex-col items-center max-w-4xl px-4 text-center gap-4 mt-[-60px]">
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-semibold text-[#FFFDFD] tracking-tight leading-[1.14]">
            Gestiona a distancia. Controla tus finanzas. Protege tu patrimonio.
        </h1>
        <p class="text-sm sm:text-base lg:text-lg text-[#FFFDFD] font-normal leading-relaxed max-w-3xl">
            Mantén tu local completamente administrado, maneja cada capa del negocio distribuye perfectamente cada aspecto o requerimientos funcionales necesarios y óptimos para mantener a tu personal totalmente organizada en su área.
        </p>
    </div>

    <!-- Elementos Decorativos Flotantes (Imágenes 3D ajustadas exactamente como en la referencia) -->
    
    <!-- 1. Esquina inferior izquierda (Monitoreo / Pantalla) -->
    <div class="absolute left-[-60px] bottom-[-40px] w-[380px] sm:w-[440px] h-[380px] sm:h-[440px] z-30 pointer-events-none transform -rotate-[12deg]">
        <img src="{{ asset('images/imagen-izquierda.png') }}" alt="Monitoreo" class="w-full h-full object-contain drop-shadow-2xl">
    </div>

    <!-- 2. Centro (Portapapeles - Ajustado al doble de tamaño) -->
    <div class="absolute left-1/2 transform -translate-x-1/2 bottom-1/2 w-[560px] h-[480px] z-30 pointer-events-none translate-y-100">
        <img src="{{ asset('images/imagen-centro.png') }}" alt="Trabajo social" class="w-full h-full object-contain object-bottom drop-shadow-2xl">
    </div>

    <!-- 3. Esquina inferior derecha (Finanzas / Monedas y Calculadora) -->
    <div class="absolute right-[-40px] bottom-[-50px] w-[380px] sm:w-[440px] h-[380px] sm:h-[440px] z-30 pointer-events-none transform rotate-[8deg]">
        <img src="{{ asset('images/imagen-derecha.png') }}" alt="Compras y proveedores" class="w-full h-full object-contain drop-shadow-2xl">
    </div>
</section>
<!-- SECCIÓN DE GESTIÓN COMPLETA PARA TU EMPRESA CON ANIMACIONES -->
<section class="w-full py-20 bg-white flex flex-col items-center justify-center">
    
    <!-- Encabezado de la Sección -->
    <div class="text-center max-w-3xl px-4 mb-16">
        <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 tracking-tight mb-3">
            Gestión completa para tu empresa
       </h2>
        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">
            <strong class="font-semibold text-gray-900">Toma el control operativo de tu negocio:</strong> Centraliza tu facturación, inventario y finanzas en una sola plataforma web, disponible dondequiera que estés.
        </p>
    </div>

    <!-- Grid de Tarjetas con Hover Animado -->
    <div class="max-w-7xl mx-auto px-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

        <!-- Tarjeta 1: Finanzas -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/cash.png') }}" alt="Finanzas" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Finanzas</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Organiza, gestiona y centraliza la información financiera de forma segura.
                </p>
            </div>
        </div>

        <!-- Tarjeta 2: Inventario -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/catalogoproductos.png') }}" alt="Inventario" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Inventario</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Ajusta inventarios como: paquetes, stock y mercancía en tiempo real.
                </p>
            </div>
        </div>

        <!-- Tarjeta 3: Ventas y Clientes -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/cierrecaja.png') }}" alt="Ventas y Clientes" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Ventas y Clientes</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Mantén una organización fluida de tus ventas diarias y base de clientes.
                </p>
            </div>
        </div>

        <!-- Tarjeta 4: Gestión de personal -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/profesionales.png') }}" alt="Gestión de personal" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Gestión de personal</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Maneja roles y permisos específicos para cada usuario dentro del sistema.
                </p>
            </div>
        </div>

        <!-- Tarjeta 5: Compras y proveedores -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/imagen-derecha.png') }}" alt="Compras y proveedores" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Compras y proveedores</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Gestiona gastos, proveedores, facturas y pedidos de forma automatizada.
                </p>
            </div>
        </div>

        <!-- Tarjeta 6: Reportes -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/mission_dashboard.png') }}" alt="Reportes" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Reportes</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Informes y reportes a nivel interno para una toma de decisiones certera.
                </p>
            </div>
        </div>

        <!-- Tarjeta 7: Monitoreos -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/imagen-izquierda.png') }}" alt="Monitoreos" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Monitoreos</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Registros de actividad, auditorías y control de caja en tiempo real.
                </p>
            </div>
        </div>

        <!-- Tarjeta 8: Perfiles de empresas -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-xl hover:-translate-y-2 hover:border-[#146F8A] transition-all duration-300 ease-out group">
            <div>
                <div class="w-14 h-14 mb-6 transform group-hover:scale-110 transition-transform duration-300">
                    <img src="{{ asset('images/negocio.png') }}" alt="Perfiles de empresas" class="w-full h-full object-contain">
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Perfiles de empresas</h3>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">
                    Maneja múltiples sucursales o empresas desde un mismo sistema central.
                </p>
            </div>
        </div>

    </div>
</section>

<div class="max-w-7xl w-full mx-auto px-4">
    <div class="w-full h-[1px] bg-gray-200"></div>
</div>
<!-- SECCIÓN DE PREGUNTAS FRECUENTES (FAQ) -->
<section class="w-full py-20 bg-white flex flex-col items-center justify-center">
    
    <!-- Encabezado de la sección -->
    <div class="text-center max-w-3xl px-4 mb-16">
        <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 tracking-tight mb-4">
            Preguntas frecuentes de nuestros clientes
        </h2>
        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">
            Sabemos que elegir el software adecuado para tu negocio es una decisión clave. Aquí responderemos a las preguntas más comunes sobre la plataforma, nuestros planes y cómo Gintly te ayuda a tomar el control total de tus operaciones desde el primer día.
        </p>
    </div>

    <!-- Contenedor Principal de Columnas -->
    <div class="max-w-7xl w-full mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 relative items-start">

        <!-- Línea divisoria vertical -->
        <div class="hidden lg:block absolute left-1/2 top-0 bottom-0 w-[1px] bg-gray-200 transform -translate-x-1/2"></div>
        <!-- Columna Izquierda -->
        <div class="flex flex-col gap-6 w-full">

            <!-- FAQ Item 1 -->
            <div class="border-b border-gray-200 pb-6 w-full flex flex-col">
                <button type="button" data-faq-toggle aria-expanded="false" class="w-full flex items-center justify-between text-left py-2 group focus:outline-none">
                    <span class="text-lg font-semibold text-gray-900 group-hover:text-[#146F8A] transition-colors pr-4">
                        ¿Qué es Gintly y cómo funciona?
                    </span>
                    <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-gray-600 transition-transform duration-300 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="faq-content max-h-0 overflow-hidden transition-all duration-300 ease-out w-full">
                    <p class="text-sm text-gray-600 pt-3 pb-1 leading-relaxed">
                        Gintly es un software de gestión empresarial en la nube (SaaS). Funciona mediante una suscripción flexible que te da acceso inmediato a todas las herramientas de control operativo, finanzas e inventario directamente desde cualquier navegador o dispositivo, sin pagar licencias costosas ni actualizaciones.
                    </p>
                </div>
            </div>

            <!-- FAQ Item 2 -->
            <div class="border-b border-gray-200 pb-6 w-full flex flex-col">
                <button type="button" data-faq-toggle aria-expanded="false" class="w-full flex items-center justify-between text-left py-2 group focus:outline-none">
                    <span class="text-lg font-semibold text-gray-900 group-hover:text-[#146F8A] transition-colors pr-4">
                        ¿Tengo que comprar infraestructura especial o realizar configuraciones complejas?
                    </span>
                    <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-gray-600 transition-transform duration-300 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="faq-content max-h-0 overflow-hidden transition-all duration-300 ease-out w-full">
                    <p class="text-sm text-gray-600 pt-3 pb-1 leading-relaxed">
                        Para nada. Al ser una plataforma SaaS, toda la infraestructura y el procesamiento corren por nuestra cuenta. Lo único que tienes que hacer es instalar la app en tu dispositivo, iniciar sesión y comenzar a gestionar tu negocio. Sin servidores locales, sin instalaciones complicadas y con actualizaciones automáticas incluidas.
                    </p>
                </div>
            </div>

            <!-- FAQ Item 3 -->
            <div class="border-b border-gray-200 pb-6 w-full flex flex-col">
                <button type="button" data-faq-toggle aria-expanded="false" class="w-full flex items-center justify-between text-left py-2 group focus:outline-none">
                    <span class="text-lg font-semibold text-gray-900 group-hover:text-[#146F8A] transition-colors pr-4">
                        ¿Es difícil de configurar e implementar en mi equipo?
                    </span>
                    <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-gray-600 transition-transform duration-300 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="faq-content max-h-0 overflow-hidden transition-all duration-300 ease-out w-full">
                    <p class="text-sm text-gray-600 pt-3 pb-1 leading-relaxed">
                        La interfaz está optimizada para que puedas comenzar en cuestión de minutos. No requiere capacitación técnica previa y tu equipo podrá adaptarse rápidamente a la plataforma desde el primer día.
                    </p>
                </div>
            </div>

        </div>

        <!-- Columna Derecha -->
        <div class="flex flex-col gap-6 w-full">

            <!-- FAQ Item 4 -->
            <div class="border-b border-gray-200 pb-6 w-full flex flex-col">
                <button type="button" data-faq-toggle aria-expanded="false" class="w-full flex items-center justify-between text-left py-2 group focus:outline-none">
                    <span class="text-lg font-semibold text-gray-900 group-hover:text-[#146F8A] transition-colors pr-4">
                        ¿Puedo personalizar los módulos según las necesidades de mi negocio?
                    </span>
                    <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-gray-600 transition-transform duration-300 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="faq-content max-h-0 overflow-hidden transition-all duration-300 ease-out w-full">
                    <p class="text-sm text-gray-600 pt-3 pb-1 leading-relaxed">
                        Sí, puedes activar o desactivar herramientas de acuerdo con lo que tu empresa necesite hoy e ir escalando funciones a medida que tu negocio crezca.
                    </p>
                </div>
            </div>

            <!-- FAQ Item 5 -->
            <div class="border-b border-gray-200 pb-6 w-full flex flex-col">
                <button type="button" data-faq-toggle aria-expanded="false" class="w-full flex items-center justify-between text-left py-2 group focus:outline-none">
                    <span class="text-lg font-semibold text-gray-900 group-hover:text-[#146F8A] transition-colors pr-4">
                        ¿Puedo invitar a colaboradores y definir permisos?
                    </span>
                    <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-gray-600 transition-transform duration-300 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="faq-content max-h-0 overflow-hidden transition-all duration-300 ease-out w-full">
                    <p class="text-sm text-gray-600 pt-3 pb-1 leading-relaxed">
                        Sí, puedes agregar a los miembros de tu equipo y asignar roles específicos (administrador, operativo, solo lectura, etc.) para controlar a qué información tiene acceso cada usuario.
                    </p>
                </div>
            </div>

            <!-- FAQ Item 6 -->
            <div class="border-b border-gray-200 pb-6 w-full flex flex-col">
                <button type="button" data-faq-toggle aria-expanded="false" class="w-full flex items-center justify-between text-left py-2 group focus:outline-none">
                    <span class="text-lg font-semibold text-gray-900 group-hover:text-[#146F8A] transition-colors pr-4">
                        ¿Qué tan segura está la información de mi negocio?
                    </span>
                    <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-gray-600 transition-transform duration-300 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div class="faq-content max-h-0 overflow-hidden transition-all duration-300 ease-out w-full">
                    <p class="text-sm text-gray-600 pt-3 pb-1 leading-relaxed">
                        La seguridad es nuestra prioridad. Toda la información en Gintly está encriptada con estándares bancarios de alta seguridad y respaldada automáticamente en servidores seguros para garantizar que tus datos siempre estén protegidos y disponibles.
                    </p>
                </div>
            </div>

        </div>

    </div>
</section>

<div class="max-w-7xl w-full mx-auto px-4">
    <div class="w-full h-[1px] bg-gray-200"></div>
</div>
@include('billing.public-plans', ['billingPlans' => $billingPlans])
<div class="max-w-7xl w-full mx-auto px-4">
    <div class="w-full h-[1px] bg-gray-200"></div>
</div>
<!-- SECCIÓN DE TESTIMONIOS  -->
<section class="w-full py-[80px] bg-white flex flex-col items-center justify-center font-sans overflow-hidden">
    
    <!-- Encabezado de la sección -->
    <div class="max-w-[1320px] w-full px-6 md:px-12 mb-[36px] flex flex-col items-center text-center gap-[12px]">
        <span class="text-[14px] font-semibold text-[#146F8A] tracking-wide uppercase">Testimonios</span>
        <h2 class="text-[32px] md:text-[36px] font-semibold leading-[40px] tracking-[-0.5px] text-black">
            Observa las calificaciones y comentarios de nuestros clientes
        </h2>
        <p class="text-[14px] text-[#777777]">Pasa el cursor para hacer zoom y leer, o usa las flechas laterales</p>
    </div>

    <!-- Contenedor general con controles de flechas -->
    <div class="relative max-w-[1320px] w-full px-6 md:px-12 flex items-center justify-center">
        
        <!-- Botón de Flecha Izquierda -->
        <button type="button" data-testimonial-prev class="absolute left-0 md:left-2 z-20 w-[44px] h-[44px] bg-white border border-[#E5E5E5] rounded-full shadow-md flex items-center justify-center text-black hover:bg-[#146F8A] hover:text-white hover:border-[#146F8A] transition-all duration-300 focus:outline-none cursor-pointer active:scale-95" aria-label="Anterior">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>

        <!-- Contenedor Desplazable con ancho visible fijo para 3 elementos -->
        <div class="w-full max-w-[1140px] overflow-hidden flex select-none py-6 px-2" id="testimonials-container">
            
            <!-- Pista de elementos -->
            <div class="flex gap-[32px] shrink-0 items-stretch transition-transform duration-500 ease-out" id="testimonials-track">
                
                <!-- TESTIMONIO 1 -->
                <div class="testimonial-card w-[356px] shrink-0 flex flex-col items-center text-center p-6 rounded-[24px] transition-all duration-300 hover:scale-[1.03] hover:shadow-[0_10px_30px_rgba(0,0,0,0.08)] bg-white">
                    <div class="w-[72px] h-[72px] rounded-full mb-[16px] shadow-md ring-4 ring-[#146F8A]/10 shrink-0 bg-[#146F8A] text-white flex items-center justify-center text-xl font-semibold" aria-hidden="true">
                        CM
                    </div>
                    <span class="text-[14px] font-medium text-[#146F8A] mb-[4px]">Testimonios</span>
                    <h3 class="text-[18px] font-semibold text-black tracking-tight mb-[12px]">Carlos Mendoza</h3>
                    <p class="text-[14px] font-normal leading-[22px] text-[#555555] italic mb-[16px] grow">
                        "Antes perdíamos horas cruzando datos entre hojas de cálculo y mensajes sueltos. Con Gintly visualizamos las finanzas, el inventario y las tareas del equipo en un solo lugar. La diferencia en nuestra eficiencia fue inmediata"
                    </p>
                    <div class="flex flex-row gap-1 text-[#FFC107] text-[15px]">★★★★★</div>
                </div>

                <!-- TESTIMONIO 2 -->
                <div class="testimonial-card w-[356px] shrink-0 flex flex-col items-center text-center p-6 rounded-[24px] transition-all duration-300 hover:scale-[1.03] hover:shadow-[0_10px_30px_rgba(0,0,0,0.08)] bg-white">
                    <div class="w-[72px] h-[72px] rounded-full mb-[16px] shadow-md ring-4 ring-[#146F8A]/10 shrink-0 bg-[#146F8A] text-white flex items-center justify-center text-xl font-semibold" aria-hidden="true">
                        VR
                    </div>
                    <span class="text-[14px] font-medium text-[#146F8A] mb-[4px]">Testimonios</span>
                    <h3 class="text-[18px] font-semibold text-black tracking-tight mb-[12px]">Valeria Rostrán</h3>
                    <p class="text-[14px] font-normal leading-[22px] text-[#555555] italic mb-[16px] grow">
                        "Probamos varias herramientas complejas que solo ralentizaban el trabajo diario. Gintly es intuitivo desde el día uno: la curva de aprendizaje fue casi nula y la adopción del equipo fue total"
                    </p>
                    <div class="flex flex-row gap-1 text-[#FFC107] text-[15px]">★★★★★</div>
                </div>

                <!-- TESTIMONIO 3 -->
                <div class="testimonial-card w-[356px] shrink-0 flex flex-col items-center text-center p-6 rounded-[24px] transition-all duration-300 hover:scale-[1.03] hover:shadow-[0_10px_30px_rgba(0,0,0,0.08)] bg-white">
                    <div class="w-[72px] h-[72px] rounded-full mb-[16px] shadow-md ring-4 ring-[#146F8A]/10 shrink-0 bg-[#146F8A] text-white flex items-center justify-center text-xl font-semibold" aria-hidden="true">
                        AE
                    </div>
                    <span class="text-[14px] font-medium text-[#146F8A] mb-[4px]">Testimonios</span>
                    <h3 class="text-[18px] font-semibold text-black tracking-tight mb-[12px]">Andrea Espinoza</h3>
                    <p class="text-[14px] font-normal leading-[22px] text-[#555555] italic mb-[16px] grow">
                        "Tener métricas claras en tiempo real nos permitió recortar gastos innecesarios e identificar nuestros productos más rentables. Gintly se pagó solo en el primer mes"
                    </p>
                    <div class="flex flex-row gap-1 text-[#FFC107] text-[15px]">★★★★★</div>
                </div>

                <!-- TESTIMONIO 4 -->
                <div class="testimonial-card w-[356px] shrink-0 flex flex-col items-center text-center p-6 rounded-[24px] transition-all duration-300 hover:scale-[1.03] hover:shadow-[0_10px_30px_rgba(0,0,0,0.08)] bg-white">
                    <div class="w-[72px] h-[72px] rounded-full mb-[16px] shadow-md ring-4 ring-[#146F8A]/10 shrink-0 bg-[#146F8A] text-white flex items-center justify-center text-xl font-semibold" aria-hidden="true">
                        MU
                    </div>
                    <span class="text-[14px] font-medium text-[#146F8A] mb-[4px]">Testimonios</span>
                    <h3 class="text-[18px] font-semibold text-black tracking-tight mb-[12px]">Marcos Urey</h3>
                    <p class="text-[14px] font-normal leading-[22px] text-[#555555] italic mb-[16px] grow">
                        "El soporte y la facilidad de uso superaron todas nuestras expectativas. Recomiendo Gintly a cualquier empresa que busque escalar sin complicaciones."
                    </p>
                    <div class="flex flex-row gap-1 text-[#FFC107] text-[15px]">★★★★★</div>
                </div>

            </div>
        </div>

        <!-- Botón de Flecha Derecha -->
        <button type="button" data-testimonial-next class="absolute right-0 md:right-2 z-20 w-[44px] h-[44px] bg-white border border-[#E5E5E5] rounded-full shadow-md flex items-center justify-center text-black hover:bg-[#146F8A] hover:text-white hover:border-[#146F8A] transition-all duration-300 focus:outline-none cursor-pointer active:scale-95" aria-label="Siguiente">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>

    </div>

<!--Footer-->
    <footer id="contacto" class="w-full min-h-[638px] bg-[#0C4353] text-white py-20 px-6 md:px-24 flex flex-col items-start gap-10 font-inter shrink-0 grow-0">
    
    <div class="w-full max-w-[1320px] mx-auto flex flex-col md:flex-row justify-between items-start gap-12 md:gap-6">
        
        <div class="flex flex-col items-start gap-8 w-full md:w-[458px]">
            
            <div class="flex flex-col items-start gap-6 self-stretch">
                <h2 class="font-semibold text-[24px] leading-[32px] tracking-[-0.5px] text-white self-stretch">
                    Potenciando la gestión de tu negocio
                </h2>
                <p class="font-normal text-[16px] leading-[24px] text-white self-stretch opacity-90">
                    Acompañamos a pequeñas empresas, emprendedores y equipos en la optimización de sus operaciones diarias con herramientas simples, claras y eficientes.
                </p>
            </div>

            <a href="{{ route('register.index') }}" class="inline-flex items-center justify-center px-6 py-2.5 gap-3 bg-[#146F8A] text-white rounded-full text-[12px] font-medium leading-[16px] transition-all duration-300 hover:bg-[#115b71] active:scale-95 shadow-sm">
                <span>Prueba Gintly</span>
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.5 9L7.5 6L4.5 3" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>

        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-8 gap-y-12 w-full md:w-auto md:grow">
    
            <div class="flex flex-col items-start gap-6">
                <h4 class="font-semibold text-[18px] leading-[26px] text-white h-[26px]">Producto</h4>
                <ul class="flex flex-col items-start gap-4 text-[16px] leading-[24px] text-[#C5D8DE]">
                    <li>Módulos</li>
                    <li>Funcionalidades</li>
                    <li>Integraciones</li>
                    <li>Novedades / Updates</li>
                </ul>
            </div>

            <div class="flex flex-col items-start gap-6">
                <h4 class="font-semibold text-[18px] leading-[26px] text-white h-[26px]">Soluciones</h4>
                <ul class="flex flex-col items-start gap-4 text-[16px] leading-[24px] text-[#C5D8DE]">
                    <li>Control Financiero</li>
                    <li>Gestión de Inventario</li>
                    <li>Flujos de Trabajo</li>
                    <li>Métricas y Reportes</li>
                </ul>
            </div>

            <div class="flex flex-col items-start gap-6 col-span-2 sm:col-span-1">
                <h4 class="font-semibold text-[18px] leading-[26px] text-white h-[26px]">Recursos y Soporte</h4>
                <ul class="flex flex-col items-start gap-4 text-[16px] leading-[24px] text-[#C5D8DE]">
                    <li>Centro de Ayuda</li>
                    <li>Guías de Uso</li>
                    <li>Atención a Clientes</li>
                    <li>Preguntas Frecuentes</li>
                </ul>
            </div>

        </div>
    </div>

    <div class="w-full max-w-[1320px] mx-auto border-t border-white/50"></div>

    <div class="w-full max-w-[1320px] mx-auto flex flex-col md:flex-row justify-between items-center gap-4 text-[16px] leading-[24px] text-white/80">

        <p class="font-semibold text-[18px] leading-[26px]">
            Journey Map creador de Gintly, un sitio <a href="https://www.gintly.com" target="_blank" class="hover:underline">https://www.gintly.com</a>
        </p>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 font-normal" aria-label="Información legal pendiente de publicación">
            <span>Políticas de privacidad</span>
            <span>Aviso legal</span>
            <span>Políticas de cookies</span>
        </div>
    </div>
</footer>
</html>
