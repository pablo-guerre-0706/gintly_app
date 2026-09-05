<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Iniciar Sesión</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    
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
<body class="bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-6 overflow-hidden">

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
              Ingresa tus credenciales o accede mediante tus redes sociales para entrar a tu panel de Gintly.
            </p>
          </div>
        </div>

        <!-- Alertas de Error -->
        @if ($errors->any())
          <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-600 rounded-xl text-xs font-medium">
            <ul>
              @foreach ($errors->all() as $error)
                <li>• {{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <!-- Botones de Redes Sociales (Google / Facebook) -->
        <div class="grid grid-cols-2 gap-3 w-full">
          <a href="{{ route('auth.google') }}" class="flex items-center justify-center gap-2.5 py-2.5 px-4 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50/80 rounded-xl text-xs font-semibold text-slate-700 shadow-sm transition-all duration-300 active:scale-95">
            <svg class="w-4 h-4" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            Google
          </a>

          <a href="{{ route('auth.facebook') }}" class="flex items-center justify-center gap-2.5 py-2.5 px-4 bg-[#1877F2] hover:bg-[#166fe5] rounded-xl text-xs font-semibold text-white shadow-sm transition-all duration-300 active:scale-95">
            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
            Facebook
          </a>
        </div>

        <!-- Divisor -->
        <div class="flex items-center gap-4 my-1">
          <div class="flex-1 h-px bg-slate-200"></div>
          <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">o con tus datos</span>
          <div class="flex-1 h-px bg-slate-200"></div>
        </div>

        <!-- Formulario de inicio de sesión (Correo o Nombre de Usuario) -->
        <form id="loginForm" action="{{ route('login.store') }}" method="POST" class="flex flex-col gap-4 w-full">
          @csrf

          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Correo electrónico o Nombre de usuario</label>
            <input type="text" id="login" name="login" value="{{ old('login') }}" placeholder="ejemplo@correo.com o tu usuario" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" required />
          </div>

          <div class="flex flex-col gap-1">
            <div class="flex justify-between items-center">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Contraseña</label>
              <!-- Si no usas ruta de recuperar contraseña, puedes cambiar el href="#" -->
              <a href="#" class="text-[11px] font-semibold text-[#146F8A] hover:underline">¿Olvidaste tu contraseña?</a>
            </div>
            <input type="password" id="password" name="password" placeholder="••••••••••••" class="px-3.5 py-2.5 w-full bg-slate-50/70 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder:text-slate-400 hover:bg-white hover:border-[#146F8A]/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all duration-300" required />
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

  <script>
    const form = document.getElementById('loginForm');
    form.addEventListener('submit', (e) => {
      const mainContainer = document.getElementById('mainContainer');
      mainContainer.classList.add('page-transition-out');
    });
  </script>

</body>
</html>