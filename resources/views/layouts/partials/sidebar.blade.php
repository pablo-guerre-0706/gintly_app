@php
    /**
     * SIMULADOR DE ROLES Y PUESTOS (Cambia los valores para probar localmente)
     * Roles principales: 'rol-01' (Propietario), 'rol-02' (Administrador), 'rol-03' (Usuario Operativo)
     * Puestos para rol-03: 'cajero', 'facturador', 'bodeguero', 'despachador'
     */
    $currentRole = $currentRole ?? 'rol-01'; 
    $puesto = $puesto ?? 'cajero'; 
@endphp

<!-- Contenedor Principal: Ancho fijo de 64 (w-64) o ajustable a tus 337px de Figma -->
<div class="flex h-(880px) w-(337px) flex-col justify-between bg-[#041d26] p-6 text-slate-300 font-sans select-none border-r border-[#082d3b]">
 
    <!-- CONTENEDOR SUPERIOR -->
    <div class="flex flex-col gap-6">
        
        <!-- Header: Logo e Icono de Menú -->
        <div class="flex items-center justify-between px-2 pb-2">
            <!-- Icono de Gintly (Capas apiladas como en la imagen) -->
            <div class="flex items-center gap-3">
                <div class="text-teal-400 text-xl">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <span class="text-sm font-bold tracking-wider text-white">GINTLY</span>
            </div>
            <!-- Icono de Menú Hamburguesa Derecho -->
            <button class="text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-bars-staggered text-sm"></i>
            </button>
        </div>

        <!-- Botón Único de Dashboard (Superior) -->
        @if($currentRole === 'rol-01' || $currentRole === 'rol-02')
            <div class="px-1">
                <a href="{{ route('dashboard') }}" class="flex h-11 items-center rounded-xl bg-linear-to-r from-cyan-950/60 to-cyan-800/40 px-3 text-xs font-semibold text-white border border-cyan-800/30 transition hover:from-cyan-900/80 hover:to-cyan-700/50">
                    <i class="fa-solid fa-border-all mr-3 text-sm text-cyan-400"></i> Dashboard
                </a>
            </div>
        @endif

        <hr class="border-[#082d3b] mx-1">

        <!-- SECCIÓN GENERAL -->
        <nav class="flex flex-col gap-1" aria-label="Navegación general">
            <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">General</p>

            <!-- ACCESOS DE PROPIETARIO Y ADMINISTRADOR COMPLETO -->
            @if($currentRole === 'rol-01' || $currentRole === 'rol-02')
                <!-- Compras -->
                <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                    <span class="flex items-center"><i class="fa-solid fa-cart-shopping mr-3 w-4 text-center group-hover:text-cyan-400"></i> Compras y proveedores</span>
                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>

                <!-- Ventas -->
                <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                    <span class="flex items-center"><i class="fa-solid fa-chart-line mr-3 w-4 text-center group-hover:text-cyan-400"></i> Ventas y operaciones</span>
                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>

                <!-- Inventario y Bodega -->
                <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                    <span class="flex items-center"><i class="fa-solid fa-box-archive mr-3 w-4 text-center group-hover:text-cyan-400"></i> Inventario y bodega</span>
                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>

                <!-- Personal (Solo Propietario) -->
                @if($currentRole === 'rol-01')
                    <a href="#" class="flex h-11 items-center rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                        <i class="fa-solid fa-user-gear mr-3 w-4 text-center group-hover:text-cyan-400"></i> Personal
                    </a>
                @endif
            @endif

            <!-- ACCESOS FILTRADOS PARA ROL OPERATIVO (rol-03) -->
            @if($currentRole === 'rol-03')
                
                <!-- Vistas específicas del Cajero -->
                @if($puesto === 'cajero')
                    <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                        <span class="flex items-center"><i class="fa-solid fa-cash-register mr-3 w-4 text-center"></i> Caja y Ventas</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <a href="{{ route('catalog.products') }}" class="flex h-11 items-center rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white"><i class="fa-solid fa-boxes-stacked mr-3 w-4 text-center"></i> Catálogo</a>
                @endif

                <!-- Vistas específicas del Facturador -->
                @if($puesto === 'facturador')
                    <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                        <span class="flex items-center"><i class="fa-solid fa-file-invoice-dollar mr-3 w-4 text-center"></i> Facturación</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                        <span class="flex items-center"><i class="fa-solid fa-users mr-3 w-4 text-center"></i> Clientes</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                @endif

                <!-- Vistas específicas del Bodeguero -->
                @if($puesto === 'bodeguero')
                    <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                        <span class="flex items-center"><i class="fa-solid fa-boxes-packing mr-3 w-4 text-center"></i> Gestión Stock</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                @endif

                <!-- Vistas específicas del Despachador -->
                @if($puesto === 'despachador')
                    <button class="flex h-11 w-full items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white group">
                        <span class="flex items-center"><i class="fa-solid fa-truck-ramp-box mr-3 w-4 text-center"></i> Logística y Envíos</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                @endif

            @endif
        </nav>

        <!-- SECCIÓN HERRAMIENTAS -->
        <nav class="flex flex-col gap-1" aria-label="Herramientas y soporte">
            <p class="px-3 pb-2 pt-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Herramientas</p>

            <a href="#" class="flex h-11 items-center rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white">
                <i class="fa-solid fa-triangle-exclamation mr-3 w-4 text-center"></i> Centro de Alertas
            </a>

            @if($currentRole === 'rol-01' || $currentRole === 'rol-02')
                <a href="#" class="flex h-11 items-center rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white">
                    <i class="fa-solid fa-gear mr-3 w-4 text-center"></i> Configuración
                </a>
            @endif

            <!-- Switch de Modo Oscuro tal cual la imagen -->
            <div class="flex h-11 items-center justify-between rounded-xl px-3 text-xs font-medium text-slate-400">
                <span class="flex items-center"><i class="fa-solid fa-moon mr-3 w-4 text-center"></i> Modo oscuro</span>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" checked class="peer sr-only">
                    <div class="h-5 w-9 rounded-full bg-slate-800 border border-slate-700 after:absolute after:top-(4px) after:left-(4px) after:h-3 after:w-3 after:rounded-full after:bg-white after:transition-all peer-checked:bg-cyan-500 peer-checked:after:translate-x-4"></div>
                </label>
            </div>

            <a href="#" class="flex h-11 items-center rounded-xl px-3 text-xs font-medium text-slate-400 transition hover:bg-[#072834] hover:text-white">
                <i class="fa-solid fa-circle-question mr-3 w-4 text-center"></i> Centro de ayuda
            </a>
        </nav>
    </div>

    <!-- CONTENEDOR INFERIOR: LOGOUT -->
    <div class="border-t border-[#082d3b] pt-4">
        <a href="{{ route('login') }}" class="flex h-11 items-center rounded-xl px-3 text-xs font-semibold text-slate-300 transition hover:bg-red-950/20 hover:text-red-400 group">
            <i class="fa-solid fa-arrow-right-from-bracket mr-3 w-4 text-center text-slate-400 group-hover:text-red-400"></i> Salir de la cuenta
        </a>
    </div>

</div>
