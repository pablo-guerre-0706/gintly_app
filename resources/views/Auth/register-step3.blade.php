<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Configura el tipo de tu negocio</title>
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

    @keyframes scaleUp {
      0% { transform: scale(0.95); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }
    .animate-scale-up {
      animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Scroll personalizado y limpio exclusivamente para el contenedor del formulario */
    .custom-scroll::-webkit-scrollbar { width: 4px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
  </style>
</head>
<body class="bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-6 overflow-hidden">

  <!-- Contenedor Principal (Individual y Sin Scroll General) -->
  <div class="flex flex-col lg:flex-row items-center w-full max-w-(1380px) h-[92vh] max-h-(860px) bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white overflow-hidden animate-fade-in">
    
    <!-- Columna Izquierda: Panel Visual Único del Paso de Negocio -->
    <div class="hidden lg:flex flex-col justify-between p-10 xl:p-12 w-[42%] h-full relative overflow-hidden bg-[#0C4353]">
      
      <!-- Imagen de fondo corporativa -->
      <img src="{{ asset('images/negocio.png') }}" alt="Fachada de tienda" class="absolute inset-0 w-full h-full object-cover object-center scale-105 opacity-90 transition-transform duration-700 hover:scale-100" />

      <!-- Degradados de lectura -->
      <div class="absolute inset-0 bg-[#0C4353]/30"></div>
      <div class="absolute inset-0 bg-linear-to-t from-[#0C4353]/95 via-[#0C4353]/40 to-transparent"></div>

      <!-- Badge Superior -->
      <div class="relative z-10 flex items-center gap-3">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide text-white shadow-sm">
          ✨ Estructura Física y Comercial
        </span>
      </div>

      <!-- Textos descriptivos -->
      <div class="relative z-10 flex flex-col gap-4 my-auto text-white">
        <h1 class="text-3xl xl:text-4xl font-bold leading-[1.2] tracking-tight">
          Gestiona tu negocio con confianza
        </h1>
        <p class="text-slate-100 text-sm xl:text-base leading-relaxed opacity-95">
          Mantén el control de tu negocio desde cualquier lugar, supervisando sus operaciones, recursos y finanzas de manera sencilla. Gintly te brinda las herramientas necesarias para administrar tu emprendimiento con mayor seguridad y tomar mejores decisiones.
        </p>
      </div>

      <!-- Footer -->
      <div class="relative z-10 text-xs text-white/80 font-medium">
        © 2026 Gintly. Diseñado para optimizar tu empresa.
      </div>
    </div>

    <!-- Columna Derecha: Selección de Tipo de Negocio con Scroll Interno -->
    <div class="flex flex-col justify-between w-full lg:w-[58%] h-full p-6 md:p-8 bg-white overflow-hidden">
      
      <div class="w-full max-w-(620px) mx-auto flex flex-col gap-3 h-full">
        
        <!-- Header: Regresar y Logo -->
        <div class="flex flex-col gap-2 w-full shrink-0">
          <div class="flex justify-between items-center">
            <a href="{{ route('register.step', ['step' => 2]) }}" class="flex items-center justify-center w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full transition-all duration-200 shadow-sm hover:scale-105 active:scale-95">
              <span class="font-bold text-base">←</span>
            </a>
            <div class="flex items-center justify-center h-8 px-2 bg-slate-50/50 rounded-xl">
              <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly Logo" class="h-7 w-auto object-contain" />
            </div>
          </div>

          <div class="flex flex-col gap-0.5">
            <h2 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">Configura el tipo de tu negocio</h2>
            <p class="text-xs text-slate-500 leading-relaxed">
              Selecciona el tipo de negocio que deseas administrar en Gintly. Esta información nos permitirá personalizar tu experiencia y adaptar las herramientas de gestión.
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
            <div class="h-1.5 w-full bg-[#146F8A] rounded-full transition-all duration-500 shadow-sm shadow-[#146F8A]/30"></div>
            <span class="text-[10px] font-bold text-[#146F8A]">Configura el espacio de tú negocio</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[10px] font-medium text-slate-400">Preferencias regionales</span>
          </div>
          <div class="flex flex-col gap-1">
            <div class="h-1.5 w-full bg-slate-100 rounded-full"></div>
            <span class="text-[10px] font-medium text-slate-400">Creación de usuarios</span>
          </div>
        </div>

        <!-- Formulario adaptado a GET sin directiva CSRF -->
        <form id="businessTypeForm" action="{{ route('register.step.store', ['step' => 4]) }}" method="GET" class="flex flex-col gap-3 w-full overflow-y-auto custom-scroll pr-1 py-1">
          <input type="hidden" id="tipo_negocio" name="tipo_negocio" value="{{ old('tipo_negocio', $formData['tipo_negocio'] ?? '') }}" required />

          <!-- Opciones de Negocio (Grid de Tarjetas) -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            
            <!-- Opción 1: Ferretería -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="ferreteria">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/ferreteria.png') }}" alt="Ferretería" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Ferretería</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Administra herramientas, materiales y productos para construcción.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

            <!-- Opción 2: Restaurante -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="restaurante">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/restaurante.png') }}" alt="Restaurante" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Restaurante</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Gestiona menús, pedidos, mesas y operaciones del restaurante.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

            <!-- Opción 3: Supermercado -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="supermercado">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/supermercado.png') }}" alt="Supermercado" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Supermercado</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Controla inventario, ventas y operaciones diarias del negocio.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

            <!-- Opción 4: Ropa y accesorios -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="ropa_accesorios">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/ropaaccesorios.png') }}" alt="Ropa y accesorios" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Ropa y accesorios</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Administra prendas, accesorios e inventario de moda.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

            <!-- Opción 5: Farmacia -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="farmacia">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/farmacia.png') }}" alt="Farmacia" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Farmacia</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Gestiona medicamentos, productos de salud e inventario.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

            <!-- Opción 6: Tienda de electrónicos -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="electronicos">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/electronic.png') }}" alt="Tienda de electrónicos" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Tienda de electrónicos</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Gestiona dispositivos electrónicos, accesorios e inventario.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

            <!-- Opción 7: Cafetería -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="cafeteria">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/cafeteria.png') }}" alt="Cafetería" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Cafetería</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Administra bebidas, alimentos y atención al cliente.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

            <!-- Opción 8: Distribuidora -->
            <div class="business-card relative flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 group" data-value="distribuidora">
              <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
                <img src="{{ asset('images/distribuidora.png') }}" alt="Distribuidora" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 pr-6">
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Distribuidora</h3>
                <p class="text-[11px] text-slate-500 leading-relaxed">Controla inventario, almacenes y distribución de productos.</p>
              </div>
              <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
                <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
            </div>

          </div>

          <!-- Opción 9: Otro (Ancho completo) -->
          <div class="business-card relative flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-[#146F8A]/60 hover:shadow-md cursor-pointer transition-all duration-300 w-full group" data-value="otro">
            <div class="w-12 h-12 shrink-0 bg-slate-50 group-hover:bg-sky-50/50 rounded-xl flex items-center justify-center p-2 border border-slate-100 transition-colors">
              <img src="{{ asset('images/otro.png') }}" alt="Otro" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110" />
            </div>
            <div class="flex flex-col gap-0.5 flex-1 pr-6">
              <h3 class="text-xs font-bold text-slate-900 group-hover:text-[#146F8A] transition-colors">Otro</h3>
              <p class="text-[11px] text-slate-500 leading-relaxed">Selecciona esta opción si tu negocio no aparece en la lista.</p>
            </div>
            <div class="absolute top-3.5 right-3.5 w-4 h-4 rounded border border-slate-300 flex items-center justify-center check-box bg-white transition-all duration-300">
              <svg class="w-3 h-3 text-white hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
          </div>

        </form>

        <!-- Botón de envío fijo al pie -->
        <div class="shrink-0 pt-1">
          <button type="submit" form="businessTypeForm" id="submitBtn" disabled class="w-full h-11 bg-slate-200 text-slate-400 font-bold text-sm tracking-wide rounded-2xl shadow-sm transition-all duration-300 ease-in-out cursor-not-allowed">
            Continuar
          </button>
        </div>

      </div>

    </div>

  </div>

  <!-- Lógica de selección y animaciones interactivas -->
  <script>
    const cards = document.querySelectorAll('.business-card');
    const hiddenInput = document.getElementById('tipo_negocio');
    const submitBtn = document.getElementById('submitBtn');

    cards.forEach(card => {
      card.addEventListener('click', () => {
        // Remover estado seleccionado de todas con transición suave
        cards.forEach(c => {
          c.classList.remove('border-[#146F8A]', 'bg-sky-50/40', 'ring-2', 'ring-[#146F8A]/25', 'shadow-md');
          c.classList.add('border-slate-200', 'bg-white');
          const box = c.querySelector('.check-box');
          const icon = c.querySelector('.check-icon');
          box.classList.remove('bg-[#146F8A]', 'border-[#146F8A]');
          box.classList.add('bg-white', 'border-slate-300');
          icon.classList.add('hidden');
        });

        // Activar la tarjeta clickeada con animación de selección
        card.classList.remove('border-slate-200', 'bg-white');
        card.classList.add('border-[#146F8A]', 'bg-sky-50/40', 'ring-2', 'ring-[#146F8A]/25', 'shadow-md', 'animate-scale-up');
        
        setTimeout(() => card.classList.remove('animate-scale-up'), 300);

        const activeBox = card.querySelector('.check-box');
        const activeIcon = card.querySelector('.check-icon');
        activeBox.classList.remove('bg-white', 'border-slate-300');
        activeBox.classList.add('bg-[#146F8A]', 'border-[#146F8A]');
        activeIcon.classList.remove('hidden');

        // Asignar valor y habilitar botón de forma dinámica
        hiddenInput.value = card.getAttribute('data-value');
        submitBtn.disabled = false;
        submitBtn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed', 'shadow-sm');
        submitBtn.classList.add('bg-[#146F8A]', 'text-white', 'hover:bg-[#10596e]', 'shadow-lg', 'shadow-[#146F8A]/25', 'cursor-pointer', 'active:scale-[0.99]');
      });
    });

    // Restaurar selección previa si existe (old() de Laravel)
    const initialValue = hiddenInput.value;
    if (initialValue) {
      const targetCard = document.querySelector(`.business-card[data-value="${initialValue}"]`);
      if (targetCard) {
        targetCard.click();
      }
    }
  </script>

</body>
</html>