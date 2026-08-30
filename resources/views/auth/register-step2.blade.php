<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Creación del Perfil de Tienda</title>
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

    /* Scroll personalizado y limpio exclusivamente para el contenedor del formulario */
    .custom-scroll::-webkit-scrollbar { width: 4px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
  </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-6 overflow-hidden">

  <!-- Contenedor Principal (Individual y Sin Scroll General) -->
  <div class="flex flex-col lg:flex-row items-center w-full max-w-[1380px] h-[92vh] max-h-[860px] bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white overflow-hidden animate-fade-in">
    
    <!-- Columna Izquierda: Panel Visual Único del Paso de Negocio -->
    <div class="hidden lg:flex flex-col justify-between p-10 xl:p-12 w-[42%] h-full relative overflow-hidden bg-[#0C4353]">
      
      <!-- Imagen de fondo corporativa -->
      <img src="{{ asset('images/merceria.png') }}" alt="Fachada de tienda" class="absolute inset-0 w-full h-full object-cover object-center scale-105 opacity-90 transition-transform duration-700 hover:scale-100" />

      <!-- Degradados de lectura -->
      <div class="absolute inset-0 bg-[#0C4353]/30"></div>
      <div class="absolute inset-0 bg-gradient-to-t from-[#0C4353]/95 via-[#0C4353]/40 to-transparent"></div>

      <!-- Badge Superior -->
      <div class="relative z-10 flex items-center gap-3">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide text-white shadow-sm">
          ✨ Estructura Física y Comercial
        </span>
      </div>

      <!-- Textos descriptivos -->
      <div class="relative z-10 flex flex-col gap-4 my-auto text-white">
        <h1 class="text-3xl xl:text-4xl font-bold leading-[1.2] tracking-tight">
          Proporciona la información correcta para la creación de tu tienda
        </h1>
        <p class="text-slate-100 text-sm xl:text-base leading-relaxed opacity-95">
          Administra uno o varios perfiles de tu estado físico dentro del emprendimiento, manteniendo un control y una gestión integral de todos los elementos de cada tienda física.
        </p>
      </div>

      <!-- Footer -->
      <div class="relative z-10 text-xs text-white/80 font-medium">
        © 2026 Gintly. Diseñado para optimizar tu empresa.
      </div>
    </div>

    <!-- Columna Derecha: Formulario Individual de Negocio con Scroll Interno -->
    <div class="flex flex-col justify-between w-full lg:w-[58%] h-full p-6 md:p-10 bg-white overflow-hidden">
      
      <div class="w-full max-w-[580px] mx-auto flex flex-col gap-3 h-full">
        
        <!-- Header: Regresar al Paso 1 y Logo -->
        <div class="flex flex-col gap-2 w-full shrink-0">
          <div class="flex justify-between items-center">
            <a href="{{ route('register.step', ['step' => 1]) }}" class="flex items-center justify-center w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full transition-all shadow-sm">
              <span class="font-bold text-base">←</span>
            </a>
            <div class="flex items-center justify-center h-8 px-2 bg-slate-50/50 rounded-xl">
              <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly Logo" class="h-7 w-auto object-contain" />
            </div>
          </div>

          <div class="flex flex-col gap-0.5">
            <h2 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">Completa el perfil de tu tienda</h2>
            <p class="text-xs text-slate-500 leading-relaxed">
              Proporciona la información de tu tienda para crear un perfil completo y personalizado. Estos datos nos permitirán organizar mejor tu negocio.
            </p>
          </div>
        </div>

        <!-- Stepper (Paso 1 Completado, Paso 2 Activo) -->
        <div class="grid grid-cols-4 gap-2 w-full shrink-0">
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-emerald-600 rounded-full"></div>
            <span class="text-[10px] font-semibold text-emerald-600">01 Perfil ✓</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-[#146F8A] rounded-full"></div>
            <span class="text-[10px] font-bold text-[#146F8A]">02 Negocio</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[10px] font-medium text-slate-400">03 Región</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[10px] font-medium text-slate-400">04 Usuarios</span>
          </div>
        </div>

        <!-- Formulario configurado con method="GET" y scroll interno -->
        <form id="businessForm" action="{{ route('register.step.store', ['step' => 2]) }}" method="GET" class="flex flex-col gap-3 w-full overflow-y-auto custom-scroll pr-1 py-1">
          
          <!-- Fila 1: Nombre de la tienda y País o región -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Nombre de la tienda</label>
              <input type="text" id="nombre_tienda" name="nombre_tienda" value="{{ old('nombre_tienda', $formData['nombre_tienda'] ?? '') }}" placeholder="Ej: Tienda Nadal Sur" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" required />
              <span id="nombreTiendaMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">País o región</label>
              <input type="text" id="pais_region" name="pais_region" value="{{ old('pais_region', $formData['pais_region'] ?? '') }}" placeholder="Ej: Nicaragua" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" required />
              <span id="paisRegionMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
            </div>
          </div>

          <!-- Fila 2: Ciudad y Código postal -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Ciudad</label>
              <input type="text" id="ciudad" name="ciudad" value="{{ old('ciudad', $formData['ciudad'] ?? '') }}" placeholder="Ej: Managua" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" required />
              <span id="ciudadMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Código postal</label>
              <input type="text" id="codigo_postal" name="codigo_postal" value="{{ old('codigo_postal', $formData['codigo_postal'] ?? '') }}" placeholder="Ej: 11001" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" required />
              <span id="codigoPostalMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
            </div>
          </div>

          <!-- Fila 3: Dirección -->
          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Dirección</label>
            <input type="text" id="direccion" name="direccion" value="{{ old('direccion', $formData['direccion'] ?? '') }}" placeholder="Ej: De los semáforos 2 cuadras abajo" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" required />
            <span id="direccionMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
          </div>

          <!-- Fila 4: Correo electrónico de la tienda y Número convencional -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Correo electrónico de la tienda</label>
              <input type="email" id="correo_tienda" name="correo_tienda" value="{{ old('correo_tienda', $formData['correo_tienda'] ?? '') }}" placeholder="tienda@gintly.com" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" />
              <span id="correoTiendaMsg" class="text-[10px] text-slate-400 flex items-center gap-1">Opcional, pero recomendado como contacto</span>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Número convencional</label>
              <input type="tel" id="numero_convencional" name="numero_convencional" value="{{ old('numero_convencional', $formData['numero_convencional'] ?? '') }}" placeholder="22223344" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" />
              <span id="numConvencionalMsg" class="text-[10px] text-slate-400 flex items-center gap-1">Opcional, pero recomendado como contacto</span>
            </div>
          </div>

          <!-- Fila 5: Número de sucursales y RUC o identificación fiscal -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Número de sucursales</label>
              <input type="number" min="1" id="numero_sucursales" name="numero_sucursales" value="{{ old('numero_sucursales', $formData['numero_sucursales'] ?? '1') }}" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" required />
              <span id="sucursalesMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Obligatorio para entender la capacidad de tu negocio.</span>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Ruc o identificación fiscal</label>
              <input type="text" id="ruc" name="ruc" value="{{ old('ruc', $formData['ruc'] ?? '') }}" placeholder="J0310000000001" class="px-3.5 py-2 w-full bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#146F8A]/20 focus:border-[#146F8A] transition-all shadow-sm" required />
              <span id="rucMsg" class="text-[10px] text-slate-500 flex items-center gap-1">Es de carácter obligatorio</span>
            </div>
          </div>

        </form>

        <!-- Botón de envío fijo al pie con etiqueta "Continuar" -->
        <div class="shrink-0 pt-1">
          <button type="submit" form="businessForm" id="submitBtn" disabled class="w-full h-11 bg-slate-200 text-slate-400 font-bold text-sm tracking-wide rounded-2xl shadow-sm transition-all duration-300 ease-in-out cursor-not-allowed">
            Continuar
          </button>
        </div>

      </div>

    </div>

  </div>

  <!-- Lógica de validación y restricción de caracteres -->
  <script>
    const state = {
      nombre_tienda: false,
      pais_region: false,
      ciudad: false,
      codigo_postal: false,
      direccion: false,
      correo_tienda: true,
      numero_convencional: true,
      numero_sucursales: true,
      ruc: false
    };

    const updateSubmitButton = () => {
      const btn = document.getElementById('submitBtn');
      const allValid = Object.values(state).every(val => val === true);

      if (allValid) {
        btn.disabled = false;
        btn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed', 'shadow-sm');
        btn.classList.add('bg-[#146F8A]', 'text-white', 'hover:bg-[#10596e]', 'shadow-lg', 'shadow-[#146F8A]/25', 'cursor-pointer', 'active:scale-[0.99]');
      } else {
        btn.disabled = true;
        btn.classList.remove('bg-[#146F8A]', 'text-white', 'hover:bg-[#10596e]', 'shadow-lg', 'shadow-[#146F8A]/25', 'cursor-pointer', 'active:scale-[0.99]');
        btn.classList.add('bg-slate-200', 'text-slate-400', 'cursor-not-allowed', 'shadow-sm');
      }
    };

    const ejecutarValidacion = (key, input, msg, condicion, textoError, textoValido, msgObligatorio = "Es de carácter obligatorio", esOpcional = false) => {
      if (input.value.trim() === "") {
        input.classList.remove('border-rose-400', 'border-emerald-500', 'ring-2', 'ring-rose-400/20', 'ring-emerald-500/20');
        input.classList.add('border-slate-200');
        if (esOpcional) {
          msg.textContent = "Opcional, pero recomendado como contacto";
          msg.className = "text-[10px] text-slate-400 flex items-center gap-1 transition-all duration-300";
          state[key] = true;
        } else {
          msg.textContent = msgObligatorio;
          msg.className = "text-[10px] text-slate-500 flex items-center gap-1 transition-all duration-300";
          state[key] = false;
        }
      } else if (condicion()) {
        input.classList.remove('border-slate-200', 'border-rose-400', 'ring-rose-400/20');
        input.classList.add('border-emerald-500', 'ring-2', 'ring-emerald-500/20');
        msg.textContent = "✓ " + textoValido;
        msg.className = "text-[10px] text-emerald-600 font-semibold flex items-center gap-1 transition-all duration-300";
        state[key] = true;
      } else {
        input.classList.remove('border-slate-200', 'border-emerald-500', 'ring-emerald-500/20');
        input.classList.add('border-rose-400', 'ring-2', 'ring-rose-400/20');
        msg.textContent = "✕ " + textoError;
        msg.className = "text-[10px] text-rose-500 font-semibold flex items-center gap-1 transition-all duration-300";
        state[key] = false;
      }
    };

    const registrarValidacion = (key, input, msg, condicion, textoError, textoValido, msgObligatorio, esOpcional = false) => {
      input.addEventListener('input', () => {
        ejecutarValidacion(key, input, msg, condicion, textoError, textoValido, msgObligatorio, esOpcional);
        updateSubmitButton();
      });
      input.addEventListener('change', () => {
        ejecutarValidacion(key, input, msg, condicion, textoError, textoValido, msgObligatorio, esOpcional);
        updateSubmitButton();
      });
      ejecutarValidacion(key, input, msg, condicion, textoError, textoValido, msgObligatorio, esOpcional);
    };

    // Campos Obligatorios
    registrarValidacion('nombre_tienda', document.getElementById('nombre_tienda'), document.getElementById('nombreTiendaMsg'), () => document.getElementById('nombre_tienda').value.trim().length >= 2, "El nombre de la tienda es muy corto", "Nombre válido");
    registrarValidacion('pais_region', document.getElementById('pais_region'), document.getElementById('paisRegionMsg'), () => document.getElementById('pais_region').value.trim().length >= 2, "El país o región es muy corto", "País válido");
    registrarValidacion('ciudad', document.getElementById('ciudad'), document.getElementById('ciudadMsg'), () => document.getElementById('ciudad').value.trim().length >= 2, "El nombre de la ciudad es muy corto", "Ciudad válida");
    registrarValidacion('codigo_postal', document.getElementById('codigo_postal'), document.getElementById('codigoPostalMsg'), () => document.getElementById('codigo_postal').value.trim().length >= 3, "Código postal inválido", "Código postal válido");
    registrarValidacion('direccion', document.getElementById('direccion'), document.getElementById('direccionMsg'), () => document.getElementById('direccion').value.trim().length >= 5, "La dirección es muy corta", "Dirección válida");
    registrarValidacion('numero_sucursales', document.getElementById('numero_sucursales'), document.getElementById('sucursalesMsg'), () => parseInt(document.getElementById('numero_sucursales').value) >= 1, "Debe ser al menos 1 sucursal", "Cantidad válida", "Obligatorio para entender la capacidad de tu negocio.");
    registrarValidacion('ruc', document.getElementById('ruc'), document.getElementById('rucMsg'), () => document.getElementById('ruc').value.trim().length >= 5, "RUC o identificación fiscal inválida", "RUC válido");

    // Correo Electrónico
    registrarValidacion('correo_tienda', document.getElementById('correo_tienda'), document.getElementById('correoTiendaMsg'), () => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(document.getElementById('correo_tienda').value), "Formato de correo no válido", "Correo de tienda válido", "Opcional, pero recomendado como contacto", true);

    // Número Convencional (Bloqueo estricto: solo permite números)
    const inputConvencional = document.getElementById('numero_convencional');
    inputConvencional.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g, '');
    });

    registrarValidacion('numero_convencional', inputConvencional, document.getElementById('numConvencionalMsg'), () => inputConvencional.value === "" || /^[0-9]{7,15}$/.test(inputConvencional.value), "Debe tener entre 7 y 15 dígitos", "Número convencional válido", "Opcional, pero recomendado como contacto", true);

    updateSubmitButton();
  </script>

</body>
</html>