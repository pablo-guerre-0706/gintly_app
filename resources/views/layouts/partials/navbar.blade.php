<div class="flex w-full h-16 items-center justify-between bg-white px-6 border-b border-neutral-100 font-sans select-none">
    
    <!-- LADO IZQUIERDO: RUTA DE NAVEGACIÓN (BREADCRUMBS) -->
    <div class="flex items-center gap-2 text-sm font-medium">
        <span class="text-neutral-400">Gintly</span>
        <span class="text-neutral-300 text-xs"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="font-bold text-neutral-900">Dashboard</span>
    </div>

    <!-- CENTRO: NOTIFICACIÓN Y BARRA DE BÚSQUEDA -->
    <div class="flex items-center gap-4 w-full max-w-lg mx-6">
        <!-- Botón de Notificaciones con fondo circular gris suave -->
        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-neutral-50 text-neutral-500 hover:bg-neutral-100 transition">
            <i class="fa-regular fa-bell text-sm"></i>
        </button>

        <!-- Barra de Búsqueda Estilo Figma -->
        <div class="relative w-full">
            <span class="absolute inset-y-0 left-4 flex items-center text-neutral-400 text-xs">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input 
                type="text" 
                placeholder="Busca lo que necesites" 
                class="h-9 w-full rounded-full bg-neutral-50 pl-10 pr-4 text-xs text-neutral-800 placeholder-neutral-400 border-none focus:bg-neutral-100 focus:outline-none focus:ring-0 transition"
            >
        </div>
    </div>

    <!-- LADO DERECHO: PERFIL DE USUARIO COMPACTO -->
    <button type="button" class="flex items-center gap-3 focus:outline-none group">
        <!-- Foto de Perfil Circular -->
        <div class="h-9 w-9 overflow-hidden rounded-full ring-1 ring-neutral-200 group-hover:ring-neutral-300 transition">
            <img 
                src="https://unsplash.com" 
                alt="Usuario autenticado" 
                class="h-full w-full object-cover"
            >
        </div>
        <!-- Flechas de Selector (Arriba/Abajo) -->
        <div class="flex flex-col text-[8px] text-neutral-400 group-hover:text-neutral-600 transition gap-0.5">
            <i class="fa-solid fa-chevron-up"></i>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
    </button>

</div>
