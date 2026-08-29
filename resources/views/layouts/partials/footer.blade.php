@php
    /**
     * SEGURIDAD Y AUDITORÍA DE INFRAESTRUCTURA (TENANT ISOLATION)
     * Datos maestros estáticos para el control de cumplimiento legal y técnico del ERP.
     */
    $currentYear = date('Y');
    $appVersion = 'v1.4.2-stable';
@endphp

<div class="flex w-full items-center justify-between bg-white text-neutral-500">
    
    <!-- LADO IZQUIERDO: COPYRIGHT Y MARCA DE AGUA CORPORATIVA -->
    <div class="flex items-center gap-2 text-xs font-medium">
        <span class="text-neutral-400">&copy; {{ $currentYear }}</span>
        <span class="font-semibold text-neutral-700">GINTLY.</span>
        <span class="hidden text-neutral-400 sm:inline">Todos los derechos reservados.</span>
    </div>

    <!-- LADO DERECHO: MÉTRICAS DE INFRAESTRUCTURA Y CUMPLIMIENTO -->
    <div class="flex items-center gap-6 text-[10px] font-medium tracking-wide uppercase">
        
        <!-- Estado del Entorno de Red (Aislamiento Verificado) -->
        <div class="hidden items-center gap-1.5 md:flex" title="Aislamiento de Base de Datos Activo">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            <span class="font-mono text-neutral-400">Tenant Shield Secured</span>
        </div>

        <!-- Divisor sutil entre métricas -->
        <span class="hidden h-3 w-px bg-neutral-200 md:block" aria-hidden="true"></span>

        <!-- Términos de Servicio y Políticas Corporativas -->
        <div class="flex gap-4">
            <a href="#terms" class="text-neutral-400 hover:text-neutral-600 transition hover:underline">Términos</a>
            <a href="#privacy" class="text-neutral-400 hover:text-neutral-600 transition hover:underline">Privacidad</a>
        </div>

        <!-- Divisor sutil para la versión -->
        <span class="h-3 w-px bg-neutral-200" aria-hidden="true"></span>

        <!-- Identificador de Versión Compilada de la Aplicación -->
        <div class="flex items-center gap-1 font-mono text-neutral-400" title="Compilación estable del sistema">
            <i class="fa-solid fa-code-branch text-[9px]"></i>
            <span>{{ $appVersion }}</span>
        </div>

    </div>
</div>
