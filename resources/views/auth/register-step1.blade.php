<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Creación de Perfil</title>
  @vite([
    'resources/css/app.css',
    'resources/js/modules/registration/wizard.js',
  ])
</head>
<body data-registration-step="1" class="registration-page bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-6 overflow-y-auto">
  <!-- Contenedor Principal con animación de entrada -->
  <div id="mainContainer" class="flex flex-col lg:flex-row items-center w-full max-w-[1380px] bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white overflow-hidden animate-fade-in transition-all duration-300">

    <!-- Columna Izquierda: Panel Visual y de Marca -->
    <div class="hidden lg:flex flex-col justify-between p-10 xl:p-12 w-[42%] h-full relative overflow-hidden bg-[#0C4353]">
      
      <img src="{{ asset('images/bussy.png') }}" alt="Fondo empresarial" class="absolute inset-0 w-full h-full object-cover object-center scale-105 opacity-90 transition-transform duration-700 hover:scale-100" />

      <div class="absolute inset-0 bg-[#0C4353]/30"></div>
      <div class="absolute inset-0 bg-linear-to-t from-[#0C4353]/95 via-[#0C4353]/40 to-transparent"></div>
      <div class="relative z-10 flex items-center gap-3">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide text-white shadow-sm">
          ✨ Plataforma de Gestión Empresarial
        </span>
      </div>

      <div class="relative z-10 flex flex-col gap-4 my-auto text-white">
        <h1 class="text-3xl xl:text-4xl font-bold leading-[1.2] tracking-tight">
          Gestiona a distancia. Controla tus finanzas. Protege tu patrimonio.
        </h1>
        <p class="text-slate-100 text-sm xl:text-base leading-relaxed opacity-95">
          Desde el control de inventario hasta revisiones automáticas, nuestra plataforma te da gobernanza absoluta sobre tu negocio desde cualquier parte del mundo.
        </p>
      </div>

      <div class="relative z-10 text-xs text-white/80 font-medium">
        © 2026 Gintly. Diseñado para optimizar tu empresa.
      </div>
    </div>

    <!-- Columna Derecha: Formulario Dinámico -->
    <div class="flex flex-col justify-center w-full lg:w-[58%] h-full p-6 md:p-10 bg-white overflow-y-auto">
      
      <div class="w-full max-w-[560px] mx-auto flex flex-col gap-5">
        
        <!-- Header: Volver y Logo -->
        <div class="flex flex-col gap-3 w-full">
          <div class="flex justify-between items-center">
            <a href="{{ route('landing') }}" class="flex items-center justify-center w-10 h-10 bg-slate-100 hover:bg-[#146F8A] hover:text-white text-slate-600 rounded-full transition-all duration-300 shadow-sm active:scale-95">
              <span class="font-bold text-base">←</span>
            </a>
            <div class="flex items-center justify-center h-10 px-2 bg-slate-50/50 rounded-xl">
              <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly Logo" class="h-8 w-auto object-contain" />
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <h2 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">Bienvenido a Gintly app</h2>
            <p class="text-xs md:text-sm text-slate-500 leading-relaxed">
              Crea tu perfil en Gintly y comienza a gestionar tu negocio de forma eficiente con una plataforma diseñada por el equipo de Journey Map.
            </p>
          </div>
        </div>

        <!-- Stepper -->
        <div class="grid grid-cols-4 gap-2 w-full">
          <div class="flex flex-col gap-1.5">
            <div class="h-1.5 w-full bg-[#146F8A] rounded-full shadow-sm"></div>
            <span class="text-[11px] font-bold text-[#146F8A]">01 Perfil</span>
          </div>
          <div class="flex flex-col gap-1.5">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[11px] font-medium text-slate-400">02 Negocio</span>
          </div>
          <div class="flex flex-col gap-1.5">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[11px] font-medium text-slate-400">03 Región</span>
          </div>
          <div class="flex flex-col gap-1.5">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[11px] font-medium text-slate-400">04 Usuarios</span>
          </div>
        </div>

        <!-- Formulario Principal -->
        @php
          $step = 1;
        @endphp

        <form id="profileForm" action="{{ route('register.step.store', ['step' => 1]) }}" method="GET" class="flex flex-col gap-3.5 w-full">
            @if($step === 1)
              {{-- Campos agrupados correctamente --}}
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Nombres</label>
                  <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $formData['nombre'] ?? '') }}" placeholder="Ej: María José" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 hover:shadow-[inset_0_1px_2px_rgba(20,111,138,0.06)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" required />
                  <span id="nombreMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Apellidos</label>
                  <input type="text" id="apellido" name="apellido" value="{{ old('apellido', $formData['apellido'] ?? '') }}" placeholder="Ej: Cruz Valdivia" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 hover:shadow-[inset_0_1px_2px_rgba(20,111,138,0.06)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" required />
                  <span id="apellidoMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Correo electrónico</label>
                  <input type="email" id="correo" name="correo" value="{{ old('correo', $formData['correo'] ?? '') }}" placeholder="mariajosecruz21@gmail.com" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 hover:shadow-[inset_0_1px_2px_rgba(20,111,138,0.06)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" required />
                  <span id="correoMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
                </div>
            
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Número de teléfono</label>
                  <div class="flex gap-2">
                    <input type="text" id="codigoPais" name="codigo_pais" value="{{ old('codigo_pais', $formData['codigo_pais'] ?? '+') }}" placeholder="+" class="px-2 py-2.5 w-14 bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-center text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 hover:shadow-[inset_0_1px_2px_rgba(20,111,138,0.06)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" />
                    <input type="tel" id="telefono" name="telefono" value="{{ old('telefono', $formData['telefono'] ?? '') }}" placeholder="8888 8888" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 hover:shadow-[inset_0_1px_2px_rgba(20,111,138,0.06)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" />
                  </div>
                  <span class="text-[10px] text-slate-400">Opcional para contacto</span>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="Ej: 1234.team1" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 hover:shadow-[inset_0_1px_2px_rgba(20,111,138,0.06)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" required />
                <span id="passwordMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Mínimo 12 caracteres (letras y números)</span>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">confirmar la contraseña</label>
                <input type="password" id="confirmPassword" placeholder="Ej: 1234.team1" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 hover:shadow-[inset_0_1px_2px_rgba(20,111,138,0.06)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" required />
                <span id="confirmMsg" class="text-[10px] text-slate-500 flex items-center gap-1">valida si la contraseña es correcta</span>
              </div>

              <!-- Botón de Continuar con animación suave de salida -->
              <button type="submit" id="submitBtn" disabled class="w-full h-12 mt-2 bg-slate-200 text-slate-400 font-bold text-sm tracking-wide rounded-2xl shadow-sm transition-all duration-300 ease-in-out cursor-not-allowed">
                Continuar
              </button>
            @endif
        </form>
      </div>
    </div>
  </div>

  <!-- Lógica de validación y transición al enviar -->
</body>
</html>
