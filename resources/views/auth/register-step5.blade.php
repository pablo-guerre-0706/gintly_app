<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Creación de usuarios</title>
  @vite([
    'resources/css/app.css',
    'resources/js/modules/registration/wizard.js',
  ])
</head>
<body data-registration-step="5" class="registration-page bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-6 overflow-hidden">

  <!-- Contenedor Principal -->
  <div class="flex flex-col lg:flex-row items-center w-full max-w-[1380px] h-[92vh] max-h-[860px] bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white overflow-hidden animate-fade-in relative">

    <!-- Columna Izquierda: Panel Visual Único -->
    <div class="hidden lg:flex flex-col justify-between p-10 xl:p-12 w-[42%] h-full relative overflow-hidden bg-[#0C4353]">
      <img src="{{ asset('images/theoffice.png') }}" alt="Gestión a distancia" class="absolute inset-0 w-full h-full object-cover object-center scale-105 opacity-90 transition-transform duration-700 hover:scale-100" />
      <div class="absolute inset-0 bg-[#0C4353]/30"></div>
      <div class="absolute inset-0 bg-linear-to-t from-[#0C4353]/95 via-[#0C4353]/40 to-transparent"></div>

      <div class="relative z-10 flex items-center gap-3">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide text-white shadow-sm">
          👥 Control de Personal
        </span>
      </div>

      <div class="relative z-10 flex flex-col gap-4 my-auto text-white">
        <h1 class="text-3xl xl:text-4xl font-bold leading-[1.2] tracking-tight">
          Gestiona a distancia. Control tus finanzas. Protege tu patrimonio.
        </h1>
        <p class="text-slate-100 text-sm xl:text-base leading-relaxed opacity-95">
          Desde el control de inventario hasta revisiones automáticas, nuestra plataforma te da gobernanza absoluta sobre tu negocio desde cualquier parte del mundo.
        </p>
      </div>

      <div class="relative z-10 text-xs text-white/80 font-medium">
        © 2026 Gintly. Diseñado para optimizar tu empresa.
      </div>
    </div>

    <!-- Columna Derecha: Vista Principal de Empleados -->
    <div class="flex flex-col justify-between w-full lg:w-[58%] h-full p-6 md:p-8 bg-white overflow-hidden">
      
      <div class="w-full max-w-[620px] mx-auto flex flex-col gap-3 h-full">

        <!-- Header -->
        <div class="flex flex-col gap-2 w-full shrink-0">
          <div class="flex justify-between items-center">
            <a href="{{ route('register.step', ['step' => 4]) }}" class="flex items-center justify-center w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full transition-all duration-200 shadow-sm hover:scale-105 active:scale-95">
              <span class="font-bold text-base">←</span>
            </a>
            <div class="flex items-center justify-center h-8 px-2 bg-slate-50/50 rounded-xl">
              <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly Logo" class="h-7 w-auto object-contain" />
            </div>
          </div>

          <div class="flex flex-col gap-0.5">
            <h2 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">Agrega a tus empleados y registra tu personal</h2>
            <p class="text-xs text-slate-500 leading-relaxed">
              Crea tu perfil en Gintly y comienza a gestionar tu negocio de forma eficiente con una plataforma diseñada para optimizar y fortalecer la administración de tu empresa.
            </p>
          </div>
        </div>

        <!-- Stepper -->
        <div class="grid grid-cols-4 gap-2 w-full shrink-0">
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-emerald-600 rounded-full"></div>
            <span class="text-[10px] font-semibold text-emerald-600">Perfil</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-emerald-600 rounded-full"></div>
            <span class="text-[10px] font-semibold text-emerald-600">Negocio</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-emerald-600 rounded-full"></div>
            <span class="text-[10px] font-semibold text-emerald-600">Regionales</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-[#146F8A] rounded-full shadow-sm shadow-[#146F8A]/30"></div>
            <span class="text-[10px] font-bold text-[#146F8A]">Creación de cuentas</span>
          </div>
        </div>

        <!-- Contenido Central / Lista de Empleados -->
        <div class="flex flex-col gap-3 w-full overflow-y-auto custom-scroll pr-1 py-1 my-auto">
          
          <!-- Botón para abrir Modal -->
          <button type="button" data-employee-open class="flex items-center justify-between p-4 bg-white border border-slate-200 hover:border-[#146F8A] rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 group cursor-pointer">
            <div class="flex items-center gap-3.5">
              <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center group-hover:bg-[#146F8A]/10 transition-colors">
                <img src="{{ asset('images/profesionales.png') }}" alt="Gintly Logo" class="h-7 w-auto object-contain" />
              </div>
              <div class="flex flex-col text-left">
                <span class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Agregar empleado</span>
                <span class="text-[11px] text-slate-500">Crea perfiles para tus empleados en la plataforma de Gintly</span>
              </div>
            </div>
            <span class="w-7 h-7 rounded-full bg-slate-50 group-hover:bg-[#146F8A] group-hover:text-white flex items-center justify-center text-slate-400 font-bold transition-all text-xs">+</span>
          </button>

          <!-- Contenedor dinámico de empleados agregados -->
          <div id="empleadosListContainer" class="flex flex-col gap-2.5"></div>

        </div>

        <!-- Botones de Acción Inferiores -->
        <div class="flex items-center gap-3 shrink-0 pt-2">
          <!-- Omitir: Lleva directo al paso 6 -->
          <a href="{{ route('register.step', ['step' => 6]) }}" class="w-1/3 h-11 flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm tracking-wide rounded-2xl transition-all duration-200">
            Omitir
          </a>
          <!-- Confirmar: Deshabilitado por defecto hasta que exista al menos 1 empleado -->
          <a id="confirmBtn" href="{{ route('register.step', ['step' => 6]) }}" class="w-2/3 h-11 flex items-center justify-center bg-slate-200 text-slate-400 font-bold text-sm tracking-wide rounded-2xl shadow-sm pointer-events-none transition-all duration-300">
            Confirmar
          </a>
        </div>

      </div>

    </div>

  </div>

  <!-- VENTANA POP-UP MODAL (CREAR / EDITAR) -->
  <div id="employeeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 p-4 overflow-y-auto">
    
    <div class="bg-white w-full max-w-[720px] rounded-[28px] shadow-2xl border border-white p-6 md:p-8 transform scale-95 transition-transform duration-300 animate-modal my-auto">

      <!-- Modal Header -->
      <div class="flex justify-between items-center pb-4 border-b border-slate-100">
        <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Registrar nuevo empleado</h3>
        <button type="button" data-employee-close class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors cursor-pointer">
          ✕
        </button>
      </div>

      <!-- Modal Form -->
      <form id="employeeForm" class="flex flex-col gap-3.5 pt-4">
        <input type="hidden" id="emp_index" value="">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
          <!-- Nombres -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-900">Nombres</label>
            <input type="text" id="emp_nombre" required placeholder="Ejemplo: María José" class="w-full h-11 px-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all">
            <span id="err_emp_nombre" class="text-[10px] text-rose-500 hidden">Debe contener al menos 2 letras válidas.</span>
          </div>
          <!-- Apellidos -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-900">Apellidos</label>
            <input type="text" id="emp_apellido" required placeholder="Ejemplo: Cruz Valdivia" class="w-full h-11 px-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all">
            <span id="err_emp_apellido" class="text-[10px] text-rose-500 hidden">Debe contener al menos 2 letras válidas.</span>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
          <!-- Correo -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-900">Correo electrónico</label>
            <input type="email" id="emp_correo" required placeholder="Ejemplo: mariajosecruz21@gmail.com" class="w-full h-11 px-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all">
            <span id="err_emp_correo" class="text-[10px] text-rose-500 hidden">Ingresa un correo electrónico válido.</span>
          </div>
          <!-- Teléfono con extensión -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-900">Número de teléfono</label>
            <div class="flex items-center gap-2">
              <select id="emp_ext" class="w-28 h-11 px-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] cursor-pointer">
                <option value="+505">+505 (NI)</option>
                <option value="+52">+52 (MX)</option>
                <option value="+57">+57 (CO)</option>
                <option value="+1">+1 (US)</option>
                <option value="+34">+34 (ES)</option>
              </select>
              <input type="tel" id="emp_telefono" required placeholder="88888888" maxlength="15" class="w-full h-11 px-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all">
            </div>
            <span id="err_emp_telefono" class="text-[10px] text-rose-500 hidden">Ingresa solo números (mínimo 7 dígitos).</span>
          </div>
        </div>

        <!-- Rol del usuario -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-slate-900">Rol del usuario</label>
          <select id="emp_rol" required class="w-full h-11 px-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all cursor-pointer">
            <option value="" disabled selected>Selecciona un rol</option>
            <option value="Rol de bodega">Responsable de bodega</option>
            <option value="Rol de compras">Responsable de compras</option>
            <option value="Cajero">Cajero</option>
            <option value="Facturador">Facturador</option>
            <option value="Despachador">Despachador</option>
            <option value="Administrador">Administrador</option>
          </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
          <!-- Contraseña -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-900">Contraseña</label>
            <input type="password" id="emp_password" required placeholder="Mínimo 12 caracteres" class="w-full h-11 px-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all">
            <span id="err_emp_password" class="text-[10px] text-rose-500 hidden">Debe tener al menos 12 caracteres.</span>
          </div>
          <!-- Valida contraseña -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-900">Valida la contraseña</label>
            <input type="password" id="emp_password_confirmation" required placeholder="Repite la contraseña" class="w-full h-11 px-4 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:border-[#146F8A] focus:ring-2 focus:ring-[#146F8A]/20 transition-all">
            <span id="err_emp_password_confirmation" class="text-[10px] text-rose-500 hidden">Las contraseñas no coinciden.</span>
          </div>
        </div>

        <!-- Botón Confirmar Empleado -->
        <div class="pt-3">
          <button type="submit" id="modalSubmitBtn" disabled class="w-full h-11 bg-slate-200 text-slate-400 font-bold text-sm tracking-wide rounded-2xl shadow-sm cursor-not-allowed transition-all duration-300">
            Confirmar empleado
          </button>
        </div>

      </form>

    </div>
  </div>

  <!-- VENTANA POP-UP MODAL DE CONFIRMACIÓN DE ELIMINACIÓN -->
  <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 p-4">
    <div class="bg-white w-full max-w-[420px] rounded-[28px] shadow-2xl border border-white p-6 transform scale-95 transition-transform duration-300 animate-modal flex flex-col items-center text-center gap-4">
      <div class="w-14 h-14 rounded-full bg-rose-50 flex items-center justify-center text-rose-500 text-2xl font-bold shadow-sm">
        ⚠️
      </div>
      <div class="flex flex-col gap-1">
        <h3 class="text-base font-bold text-slate-900">¿Estás seguro de eliminar este empleado?</h3>
        <p class="text-xs text-slate-500 leading-relaxed">
          Esta acción eliminará el perfil registrado de la lista de personal. No podrás deshacer este cambio.
        </p>
      </div>
      <input type="hidden" id="delete_emp_index" value="">
      <div class="flex items-center gap-3 w-full pt-2">
        <button type="button" data-delete-close class="w-1/2 h-11 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs tracking-wide rounded-2xl transition-all cursor-pointer">
          Cancelar
        </button>
        <button type="button" data-delete-confirm class="w-1/2 h-11 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs tracking-wide rounded-2xl shadow-lg shadow-rose-600/25 transition-all cursor-pointer">
          Sí, eliminar
        </button>
      </div>
    </div>
  </div>

  <!-- Scripts con Activación Dinámica del Botón Confirmar -->
</body>
</html>
