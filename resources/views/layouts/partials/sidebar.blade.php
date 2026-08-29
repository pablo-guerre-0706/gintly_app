@php
    /**
     * Cambia este valor para probar las vistas en tu navegador:
     * 'rol-01' = Propietario (Dueño, acceso total)
     * 'rol-02' = Administrador (Acceso comercial y operativo, no ve KPIs de dueño)
     * 'rol-03' = Operativos
     */
    $currentRole = $currentRole ?? 'rol-01'; 
@endphp

<div class="flex h-screen w-64 flex-col justify-between bg-slate-950 p-6 text-slate-200">
    
    <!-- CONTENEDOR SUPERIOR: LOGO Y MENÚ -->
    <div class="flex flex-col gap-8">
        <!-- Encabezado del Sistema -->
        <div class="px-2">
            <span class="text-lg font-bold tracking-tight text-white">GINTLY</span>
            <!-- Etiqueta dinámica de Rol según el simulador -->
            <p class="text-[10px] font-bold uppercase tracking-wider text-cyan-400">
                @if($currentRole === 'rol-01') Propietario @endif
                @if($currentRole === 'rol-02') Administrador @endif
                @if($currentRole === 'rol-03') Módulo Operativo @endif
            </p>
        </div>

        <!-- SECCIÓN 1: GENERAL & ESTADÍSTICAS (Solo Propietario y Administrador) -->
        @if($currentRole === 'rol-01' || $currentRole === 'rol-02')
            <nav class="flex flex-col gap-1.5" aria-label="Navegación principal">
                <p class="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">General</p>
                
                <a href="{{ route('dashboard') }}" class="flex h-10 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white ring-1 ring-slate-800 transition hover:bg-slate-800">
                    <i class="fa-solid fa-chart-pie mr-2.5 w-4 text-center text-cyan-400"></i> Dashboard
                </a>

                <!-- Datos Maestros compartidos -->
                <a href="{{ route('customers.index') }}" class="flex h-10 items-center rounded-lg px-3 text-xs font-medium text-slate-400 transition hover:bg-slate-900 hover:text-white">
                    <i class="fa-solid fa-users mr-2.5 w-4 text-center"></i> Clientes y Fidelidad
                </a>

                <a href="{{ route('catalog.products') }}" 
                class="flex h-10 items-center rounded-lg px-3 text-xs font-medium transition 
                {{ request()->routeIs('catalog.products') ? 'bg-slate-900 text-white ring-1 ring-slate-800' : 'text-slate-400 hover:bg-slate-900 hover:text-white' }}">
                <i class="fa-solid fa-boxes-stacked mr-2.5 w-4 text-center {{ request()->routeIs('catalog.products') ? 'text-cyan-400' : '' }}"></i> 
                Catálogo de Productos
                </a>
            </nav>
        @endif

        <!-- SECCIÓN 2: HERRAMIENTAS Y LOGÍSTICA (Segmentada por capacidades) -->
        <nav class="flex flex-col gap-1.5" aria-label="Herramientas">
            <p class="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Herramientas</p>

            <!-- Punto de Venta: Disponible para Operativos y Dueño, bloqueado para administradores puros si no facturan -->
            <a href="{{ route('pos.index') }}" class="flex h-10 items-center rounded-lg px-3 text-xs font-medium text-slate-400 transition hover:bg-slate-900 hover:text-white">
                <i class="fa-solid fa-cash-register mr-2.5 w-4 text-center"></i> Puntos de Venta
            </a>

            <!-- Conciliación y Stock: Solo rol-01 y rol-02) supervisan inventarios -->
            @if($currentRole === 'rol-01' || $currentRole === 'rol-02')
                <a href="{{ route('inventory.reconciliation') }}" class="flex h-10 items-center rounded-lg px-3 text-xs font-medium text-slate-400 transition hover:bg-slate-900 hover:text-white">
                    <i class="fa-solid fa-boxes-packing mr-2.5 w-4 text-center"></i> Conciliación y Stock
                </a>
            @endif

            <!-- Cierre de Caja: El operativo rinde cuentas, el dueño audita -->
            <a href="{{ route('finance.cash-closing') }}" class="flex h-10 items-center rounded-lg px-3 text-xs font-medium text-slate-400 transition hover:bg-slate-900 hover:text-white">
                <i class="fa-solid fa-vault mr-2.5 w-4 text-center"></i> Cierre de Caja
            </a>
        </nav>
    </div>

    <!-- CONTENEDOR INFERIOR: CONFIGURACIÓN / SALIR -->
    <div class="border-t border-slate-900 pt-4">
        <a href="{{ route('login') }}" class="flex h-10 items-center rounded-lg px-3 text-xs font-medium text-slate-400 transition hover:bg-red-950/30 hover:text-red-300">
            <i class="fa-solid fa-right-from-bracket mr-2.5 w-4 text-center"></i> Salir de la cuenta
        </a>
    </div>

</div>
