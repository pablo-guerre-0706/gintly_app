@php
    /**
     * SIMULADOR DE CONTEXTO TENANT (RBAC & SOC)
     * Hereda el rol definido en la estructura superior para mantener consistencia.
     */
    $currentRole = $currentRole ?? 'rol-01'; 
    $tenantName = $tenantName ?? 'Corporación Matriz S.A.';
    $businessId = $businessId ?? 'TENANT-001';
@endphp

<div class="flex w-full items-center justify-between bg-white text-neutral-800">
    
    <!-- LADO IZQUIERDO: CONTEXTO DEL TENANT (AISLAMIENTO VISUAL) -->
    <div class="flex items-center gap-4">
        <!-- Indicador visual del Business ID del Tenant Actual -->
        <div class="flex flex-col">
            <span class="text-sm font-bold tracking-tight text-neutral-900">{{ $tenantName }}</span>
            <div class="flex items-center gap-1.5 mt-0.5">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[10px] font-mono font-semibold tracking-wider text-neutral-400 uppercase">ID: {{ $businessId }}</span>
            </div>
        </div>
    </div>

    <!-- LADO DERECHO: ACCIONES GLOBALES, ALERTAS Y PERFIL DE USUARIO -->
    <div class="flex items-center gap-6">
        
        <!-- NOTIFICACIONES Y ALERTAS ACTIVAS (Visibilidad según Rol) -->
        <div class="relative">
            <button type="button" id="navbarNotificationsBtn" class="relative p-2 text-neutral-400 hover:text-neutral-600 transition" aria-label="Ver alertas">
                <i class="fa-solid fa-bell text-lg"></i>
                <!-- Punto de notificación dinámico (Simulado si hay alertas activas en el Dashboard) -->
                <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-amber-500 ring-2 ring-white"></span>
            </button>
        </div>

        <!-- Opciones de Soporte Técnico Directo -->
        <a href="#support" class="p-2 text-neutral-400 hover:text-neutral-600 transition" title="Soporte del Sistema">
            <i class="fa-solid fa-circle-question text-lg"></i>
        </a>

        <!-- DIVISOR VISUAL INTEGRADO -->
        <span class="h-6 w-px bg-neutral-200" aria-hidden="true"></span>

        <!-- PERFIL DEL USUARIO AUTENTICADO (RBAC DINÁMICO) -->
        <div class="flex items-center gap-3">
            <div class="flex flex-col text-right">
                <span class="text-xs font-semibold text-neutral-900">Roberto Romero</span>
                <!-- Badge dinámico de nivel de acceso basado en tus Policies/Roles del Backend -->
                <span class="text-[9px] font-bold uppercase tracking-wider text-cyan-600 mt-0.5">
                    @if($currentRole === 'rol-01') Propietario @endif
                    @if($currentRole === 'rol-02') Administrador @endif
                    @if($currentRole === 'rol-03') Operativo @endif
                </span>
            </div>
            
            <!-- Avatar / Iniciales del Usuario -->
            <button type="button" id="navbarUserMenuBtn" class="flex h-9 w-9 items-center justify-between rounded-full bg-neutral-100 ring-1 ring-neutral-200 transition hover:ring-neutral-300 focus:outline-none" aria-label="Menú de usuario">
                <span class="m-auto text-xs font-bold text-neutral-600">RG</span>
            </button>
        </div>

    </div>
</div>
