@extends('layouts.app')

@section('title', 'Inicio')
@section('page-script', 'dashboard-landing')

@section('content')
<div class="overflow-hidden rounded-2xl bg-[#0d2227] p-6 text-white shadow-xl sm:p-8 lg:p-10 bg-[linear-gradient(rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:60px_60px]">

    <!-- BARRA INTERNA DEL PANEL -->
    <div class="mb-10 flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-white/5 shadow-inner">
                <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            <span class="text-lg font-bold tracking-wide">Gintly ERP</span>
        </div>

        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-300">
            <a href="#" class="text-white hover:text-white transition-colors">Inicio</a>
            <a href="#funciones" class="hover:text-white transition-colors">Funciones</a>
            <a href="#modulos" class="hover:text-white transition-colors">Módulos</a>
            <a href="#planes" class="hover:text-white transition-colors">Planes</a>
        </nav>

        <a href="#soporte" class="rounded-full bg-[#177c93] px-5 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#136173]">
            Soporte Técnico
        </a>
    </div>

    <!-- SECCIÓN HERO INTERNA -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-center">
        <!-- Columna Izquierda -->
        <div class="lg:col-span-6">
            <h1 class="mb-5 text-3xl font-extrabold leading-tight sm:text-4xl lg:text-4xl">
                El sistema de facturación y gestión empresarial diseñado para Nicaragua.
            </h1>
            <p class="mb-8 text-sm leading-relaxed text-[#a0b2b6] sm:text-base">
                Gestiona. Impulsa. Crece. Centraliza la administración de tu negocio con contabilidad automatizada, facturación, cobros, gestión de clientes e inventario en una sola plataforma en la nube.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-[#177c93] px-6 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-[#136173]">
                    Regístrate
                </a>
                <a href="{{ route('login') }}" class="rounded-full bg-[#e5ecee] px-6 py-2.5 text-sm font-semibold text-slate-800 shadow-md transition-all hover:bg-white">
                    Iniciar sesión
                </a>
            </div>
        </div>

        <!-- Columna Derecha -->
        <div class="grid grid-cols-2 gap-4 lg:col-span-6">
            <div class="relative min-h-[120px] overflow-hidden rounded-xl bg-[#aedbe3] p-4 text-slate-900 shadow">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-[#556b70]">Gestiona tu personal</span>
                <h3 class="text-base font-bold leading-snug">Une a todo tu equipo de trabajo</h3>
            </div>

            <div class="relative min-h-[120px] overflow-hidden rounded-xl bg-[#aedbe3] p-4 text-slate-900 shadow">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-[#556b70]">Maneja tu inventario</span>
                <h3 class="text-base font-bold leading-snug">Las mejores herramientas</h3>
            </div>

            <div class="col-span-2 relative flex h-[320px] overflow-hidden rounded-xl bg-[#aedbe3] justify-center items-end shadow">
                <img 
                    src="{{ asset('images/hero-person.png') }}" 
                    alt="Gestión de negocio" 
                    class="h-full object-contain object-bottom"
                />
            </div>
        </div>
    </div>

</div>
@endsection