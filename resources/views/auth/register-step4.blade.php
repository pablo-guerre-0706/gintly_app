<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Configura tus datos regionales</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
      animation: fadeIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Scroll personalizado y limpio exclusivamente para el contenedor del formulario */
    .custom-scroll::-webkit-scrollbar { width: 4px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
  </style>
</head>
<body class="bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-6 overflow-hidden">

  <!-- Contenedor Principal (Individual y Sin Scroll General) -->
  <div class="flex flex-col lg:flex-row items-center w-full max-w-(1380px) h-[92vh] max-h-(860px) bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white overflow-hidden animate-fade-in">
    
    <!-- Columna Izquierda: Panel Visual Único del Paso Regional -->
    <div class="hidden lg:flex flex-col justify-between p-10 xl:p-12 w-[42%] h-full relative overflow-hidden bg-[#0C4353]">
      
      <!-- Imagen de fondo corporativa -->
      <img src="{{ asset('images/paso4.png') }}" alt="Tu negocio en orden" class="absolute inset-0 w-full h-full object-cover object-center scale-105 opacity-90 transition-transform duration-700 hover:scale-100" />

      <!-- Degradados de lectura -->
      <div class="absolute inset-0 bg-[#0C4353]/30"></div>
      <div class="absolute inset-0 bg-linear-to-t from-[#0C4353]/95 via-[#0C4353]/40 to-transparent"></div>
      <!-- Badge Superior -->
      <div class="relative z-10 flex items-center gap-3">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide text-white shadow-sm">
          ✨ Estructura Financiera y Temporal
        </span>
      </div>

      <!-- Textos descriptivos -->
      <div class="relative z-10 flex flex-col gap-4 my-auto text-white">
        <h1 class="text-3xl xl:text-4xl font-bold leading-[1.2] tracking-tight">
          Tu negocio en orden, estés donde estés.
        </h1>
        <p class="text-slate-100 text-sm xl:text-base leading-relaxed opacity-95">
          Control total de ventas, caja e inventario en una sola plataforma. Monitorea la salud financiera de tu empresa con total precisión.
        </p>
      </div>

      <!-- Footer -->
      <div class="relative z-10 text-xs text-white/80 font-medium">
        © 2026 Gintly. Diseñado para optimizar tu empresa.
      </div>
    </div>

    <!-- Columna Derecha: Formulario de Preferencias Regionales con Scroll Interno -->
    <div class="flex flex-col justify-between w-full lg:w-[58%] h-full p-6 md:p-8 bg-white overflow-hidden">
      
      <div class="w-full max-w-(620px) mx-auto flex flex-col gap-3 h-full">
        
        <!-- Header: Regresar al paso 3 y Logo -->
        <div class="flex flex-col gap-2 w-full shrink-0">
          <div class="flex justify-between items-center">
            <a href="{{ route('register.step', ['step' => 3]) }}" class="flex items-center justify-center w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full transition-all duration-200 shadow-sm hover:scale-105 active:scale-95">
              <span class="font-bold text-base">←</span>
            </a>
            <div class="flex items-center justify-center h-8 px-2 bg-slate-50/50 rounded-xl">
              <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly Logo" class="h-7 w-auto object-contain" />
            </div>
          </div>

          <div class="flex flex-col gap-0.5">
            <h2 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">Configura tus datos regionales</h2>
            <p class="text-xs text-slate-500 leading-relaxed">
              Establece la zona horaria, moneda y fecha de tu empresa para mantener tus reportes financieros e inventarios siempre al día.
            </p>
          </div>
        </div>

        <!-- Stepper -->
        <div class="grid grid-cols-4 gap-2 w-full shrink-0">
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-emerald-600 rounded-full transition-all duration-500"></div>
            <span class="text-[10px] font-semibold text-emerald-600">Configuración de tu Perfil</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-emerald-600 rounded-full transition-all duration-500"></div>
            <span class="text-[10px] font-semibold text-emerald-600">Configura el espacio de tú negocio</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-[#146F8A] rounded-full transition-all duration-500 shadow-sm shadow-[#146F8A]/30"></div>
            <span class="text-[10px] font-bold text-[#146F8A]">Preferencias regionales</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[10px] font-medium text-slate-400">Creación de usuarios</span>
          </div>
        </div>

        <!-- Formulario con Scroll Interno -->
        <form id="regionalForm" action="{{ route('register.step.store', ['step' => 4]) }}" method="GET" class="flex flex-col gap-4 w-full overflow-y-auto custom-scroll pr-1 py-1 my-auto">
          
          <!-- Campo 1: Zona Horaria (Ahora de primera) -->
          <div class="flex flex-col gap-1.5 w-full">
            <label for="zona_horaria" class="text-xs font-bold text-slate-900">Zona Horaria</label>
            <div class="relative flex items-center">
              <span class="absolute left-3.5 text-slate-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
              </span>

              <select id="zona_horaria" name="zona_horaria" required class="w-full h-11 pl-10 pr-10 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all appearance-none cursor-pointer">
                <option value="" disabled {{ empty($formData['zona_horaria']) ? 'selected' : '' }}>Selecciona una zona horaria</option>
                <option value="America/Managua" {{ (isset($formData['zona_horaria']) && $formData['zona_horaria'] == 'America/Managua') || (!isset($formData['zona_horaria']) && config('app.timezone') == 'America/Managua') ? 'selected' : '' }}>(UTC-06:00) Managua, Centroamérica</option>
                <option value="America/Mexico_City" {{ (isset($formData['zona_horaria']) && $formData['zona_horaria'] == 'America/Mexico_City') ? 'selected' : '' }}>(UTC-06:00) Ciudad de México</option>
                <option value="America/Bogota" {{ (isset($formData['zona_horaria']) && $formData['zona_horaria'] == 'America/Bogota') ? 'selected' : '' }}>(UTC-05:00) Bogotá, Lima, Quito</option>
                <option value="America/New_York" {{ (isset($formData['zona_horaria']) && $formData['zona_horaria'] == 'America/New_York') ? 'selected' : '' }}>(UTC-05:00) Este de EE. UU. / Canadá</option>
                <option value="Europe/Madrid" {{ (isset($formData['zona_horaria']) && $formData['zona_horaria'] == 'Europe/Madrid') ? 'selected' : '' }}>(UTC+01:00) Madrid, España</option>
              </select>
              <span class="absolute right-3.5 text-slate-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
              </span>
            </div>
            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 px-1">
              <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
              <span>Se usará para registrar la hora exacta de tus transacciones y movimientos de caja</span>
            </div>
          </div>

          <!-- Campo 2: Moneda -->
          <div class="flex flex-col gap-1.5 w-full">
            <label for="moneda" class="text-xs font-bold text-slate-900">Moneda</label>
            <div class="relative flex items-center">
              <span class="absolute left-3.5 text-slate-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M14.8 9A2 2 0 0 0 13 8h-2a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4h-2a2 2 0 0 1-1.8-1"></path><path d="M12 6v2m0 8v2"></path></svg>
              </span>

              <select id="moneda" name="moneda" required class="w-full h-11 pl-10 pr-10 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all appearance-none cursor-pointer">
                <option value="" disabled {{ empty($formData['moneda']) ? 'selected' : '' }}>Tipo de moneda</option>
                <option value="USD" {{ (isset($formData['moneda']) && $formData['moneda'] == 'USD') ? 'selected' : '' }}>USD - Dólar estadounidense ($)</option>
                <option value="NIO" {{ (isset($formData['moneda']) && $formData['moneda'] == 'NIO') ? 'selected' : '' }}>NIO - Córdoba nicaragüense (C$)</option>
                <option value="EUR" {{ (isset($formData['moneda']) && $formData['moneda'] == 'EUR') ? 'selected' : '' }}>EUR - Euro (€)</option>
              </select>
              <span class="absolute right-3.5 text-slate-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
              </span>
            </div>
            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 px-1">
              <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
              <span>Indica la moneda principal de tu empresa</span>
            </div>
          </div>

          <!-- Campo 3: Fecha de creación -->
          <div class="flex flex-col gap-1.5 w-full">
            <label for="fecha_creacion" class="text-xs font-bold text-slate-900">Fecha de creación</label>
            <div class="relative flex items-center">
              <span class="absolute left-3.5 text-slate-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
              </span>

              <input 
                type="date" 
                id="fecha_creacion" 
                name="fecha_creacion" 
                value="{{ old('fecha_creacion', $formData['fecha_creacion'] ?? date('Y-m-d')) }}"
                max="{{ date('Y-m-d') }}"
                required 
                class="w-full h-11 pl-10 pr-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all cursor-pointer" 
              />
            </div>
            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 px-1">
              <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
              <span>Indica la fecha de inicio o creación del espacio (no se permiten fechas futuras)</span>
            </div>
          </div>

        </form>

        <!-- Botón de envío fijo al pie -->
        <div class="shrink-0 pt-2">
          <button type="submit" form="regionalForm" id="submitBtn" class="w-full h-11 bg-[#146F8A] text-white hover:bg-[#10596e] font-bold text-sm tracking-wide rounded-2xl shadow-lg shadow-[#146F8A]/25 transition-all duration-300 ease-in-out cursor-pointer active:scale-[0.99]">
            continuar
          </button>
        </div>

      </div>

    </div>

  </div>

  <!-- Lógica de validación dinámica -->
  <script>
    const monedaSelect = document.getElementById('moneda');
    const fechaInput = document.getElementById('fecha_creacion');
    const zonaHorariaSelect = document.getElementById('zona_horaria');
    const submitBtn = document.getElementById('submitBtn');

    function checkFormValidity() {
      if (monedaSelect.value !== "" && fechaInput.value !== "" && zonaHorariaSelect.value !== "") {
        submitBtn.disabled = false;
        submitBtn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed', 'shadow-sm');
        submitBtn.classList.add('bg-[#146F8A]', 'text-white', 'hover:bg-[#10596e]', 'shadow-lg', 'shadow-[#146F8A]/25', 'cursor-pointer', 'active:scale-[0.99]');
      } else {
        submitBtn.disabled = true;
        submitBtn.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed', 'shadow-sm');
        submitBtn.classList.remove('bg-[#146F8A]', 'text-white', 'hover:bg-[#10596e]', 'shadow-lg', 'shadow-[#146F8A]/25', 'cursor-pointer', 'active:scale-[0.99]');
      }
    }

    monedaSelect.addEventListener('change', checkFormValidity);
    fechaInput.addEventListener('input', checkFormValidity);
    zonaHorariaSelect.addEventListener('change', checkFormValidity);

    // Validación inicial al cargar la página
    checkFormValidity();
  </script>

</body>
</html>