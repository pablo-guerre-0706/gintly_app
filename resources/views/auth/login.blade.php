<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Iniciar Sesión</title>
  <meta name="api-base-url" content="{{ url('/api/v1') }}">
  <meta name="dashboard-url" content="{{ route('dashboard') }}">
  @vite([
    'resources/css/app.css',
    'resources/js/modules/security/auth.js',
  ])
  <style>
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
      animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .page-transition-out {
      opacity: 0;
      transform: translateX(-15px);
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
  </style>
</head>
<body class="bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-6 overflow-hidden font-sans">

  <!-- Contenedor Principal con animación de entrada -->
  <div id="mainContainer" class="flex flex-col lg:flex-row items-center w-full max-w-(1380px) h-[92vh] max-h-(860px) bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white overflow-hidden animate-fade-in transition-all duration-300">
    
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
          Retoma el control de tu empresa en segundos.
        </h1>
        <p class="text-slate-100 text-sm xl:text-base leading-relaxed opacity-95">
          Inicia sesión para acceder a tus finanzas, inventarios y gobernanza absoluta desde cualquier parte del mundo.
        </p>
      </div>

      <div class="relative z-10 text-xs text-white/80 font-medium">
        © 2026 Gintly. Diseñado para optimizar tu empresa.
      </div>
    </div>

    <!-- Columna Derecha: Formulario de Login -->
    <div class="flex flex-col justify-center w-full lg:w-[58%] h-full p-6 md:p-10 bg-white overflow-y-auto">
      
      <div class="w-full max-w-(560px) mx-auto flex flex-col gap-6">
        
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
            <h2 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">¡Qué bueno verte de nuevo!</h2>
            <p class="text-xs md:text-sm text-slate-500 leading-relaxed">
              Ingresa las credenciales de tu negocio para entrar a tu panel de Gintly.
            </p>
          </div>
        </div>

        <div
          id="loginFeedback"
          class="hidden p-3.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium"
          role="alert"
          tabindex="-1"
        ></div>

        <form id="loginForm" class="flex flex-col gap-4 w-full" novalidate>
          <div class="flex flex-col gap-1">
            <label for="business_slug" class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Identificador del negocio</label>
            <input
              type="text"
              id="business_slug"
              name="business_slug"
              placeholder="mi-negocio"
              autocomplete="organization"
              aria-describedby="business_slug-error"
              class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300 aria-invalid:border-rose-400 aria-invalid:ring-rose-200"
              required
            />
            <p id="business_slug-error" data-field-error="business_slug" class="hidden text-xs font-medium text-rose-600" aria-live="polite"></p>
          </div>

          <div class="flex flex-col gap-1">
            <label for="email" class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Correo electrónico</label>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="ejemplo@correo.com"
              autocomplete="username"
              inputmode="email"
              aria-describedby="email-error"
              class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300 aria-invalid:border-rose-400 aria-invalid:ring-rose-200"
              required
            />
            <p id="email-error" data-field-error="email" class="hidden text-xs font-medium text-rose-600" aria-live="polite"></p>
          </div>

          <div class="flex flex-col gap-1">
            <div class="flex justify-between items-center">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Contraseña</label>
              <!-- Si no usas ruta de recuperar contraseña, puedes cambiar el href="#" -->
              <a href="#" class="text-[11px] font-semibold text-[#146F8A] hover:underline">¿Olvidaste tu contraseña?</a>
            </div>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="••••••••••••"
              autocomplete="current-password"
              aria-describedby="password-error"
              class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300 aria-invalid:border-rose-400 aria-invalid:ring-rose-200"
              required
            />
            <p id="password-error" data-field-error="password" class="hidden text-xs font-medium text-rose-600" aria-live="polite"></p>
          </div>

          <!-- Botón de Entrar -->
          <button type="submit" id="submitBtn" class="w-full h-12 mt-2 bg-[#146F8A] hover:bg-[#10596e] text-white font-bold text-sm tracking-wide rounded-2xl shadow-lg shadow-[#146F8A]/25 transition-all duration-300 ease-in-out cursor-pointer active:scale-[0.99]">
            Iniciar Sesión
          </button>
        </form>

        <!-- Pie de página: Enlace a Registro -->
        <div class="text-center text-xs text-slate-500 mt-2">
          ¿Aún no tienes cuenta en Gintly? 
          <a href="{{ route('register.index') }}" class="font-bold text-[#146F8A] hover:underline">Regístrate aquí</a>
        </div>

      </div>

    </div>

  </div>

</body>
</html>
