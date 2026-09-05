<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Suscripción y Planes</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Alpine.js -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

    @keyframes modalScale {
      from { opacity: 0; transform: scale(0.93) translateY(12px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .animate-modal {
      animation: modalScale 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-5px); }
      40%, 80% { transform: translateX(5px); }
    }
    .animate-shake {
      animation: shake 0.4s ease-in-out;
    }

    /* Transiciones globales fluidas */
    * {
      transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform;
      transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
      transition-duration: 250ms;
    }

    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
    .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
  </style>
</head>
<body class="bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-5 overflow-y-auto">

  <!-- Contenedor Principal -->
  <div class="flex flex-col w-full max-w-(1240px) h-auto my-5 bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white p-5 md:p-8 animate-fade-in justify-between">    
    <!-- Header General -->
    <div class="flex flex-col gap-2.5">
      <div class="flex justify-between items-center">
        <a href="{{ route('register.step', ['step' => 5]) }}" class="flex items-center justify-center w-8 h-8 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full shadow-sm hover:scale-105 active:scale-95 cursor-pointer">
          <span class="font-bold text-sm">←</span>
        </a>
        <div class="flex items-center justify-center h-7 px-2 bg-slate-50/50 rounded-xl">
          <img src="{{ asset('images/gintlylogo.png') }}" alt="Gintly Logo" class="h-6 w-auto object-contain" />
        </div>
      </div>

      <div class="flex flex-col gap-0.5 max-w-2xl">
        <h1 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">Ultimo paso: Suscripción y Planes</h1>
        <p class="text-xs text-slate-500 leading-relaxed">
          Elige el plan que mejor se adapte a las necesidades de tu negocio y disfruta de las herramientas que Gintly tiene para ayudarte a gestionar y administrar tu empresa de manera más sencilla y eficiente.
        </p>
      </div>

      <!-- Stepper Superior -->
      <div class="grid grid-cols-4 gap-2 w-full max-w-3xl pt-1">
        <div class="flex flex-col gap-1">
          <div class="h-1.5 w-full bg-emerald-600 rounded-full shadow-sm shadow-emerald-500/20"></div>
          <span class="text-[9px] font-semibold text-emerald-600">Configuración de tu Perfil</span>
        </div>
        <div class="flex flex-col gap-1">
          <div class="h-1.5 w-full bg-emerald-600 rounded-full shadow-sm shadow-emerald-500/20"></div>
          <span class="text-[9px] font-semibold text-emerald-600">Configura el espacio de tú negocio</span>
        </div>
        <div class="flex flex-col gap-1">
          <div class="h-1.5 w-full bg-emerald-600 rounded-full shadow-sm shadow-emerald-500/20"></div>
          <span class="text-[9px] font-semibold text-emerald-600">Preferencias regionales</span>
        </div>
        <div class="flex flex-col gap-1">
          <div class="h-1.5 w-full bg-emerald-600 rounded-full shadow-sm shadow-emerald-500/20"></div>
          <span class="text-[9px] font-semibold text-emerald-600">Creación de usuarios</span>
        </div>
      </div>

      <!-- Toggle Mensual / Anual -->
      <div class="flex justify-center my-2">
        <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200 shadow-sm">
          <button type="button" id="btnMensual" onclick="setBillingCycle('monthly')" class="px-4 py-1.5 text-xs font-bold rounded-xl bg-[#146F8A] text-white shadow-sm cursor-pointer hover:scale-[1.02] active:scale-95">
            Pago mensual
          </button>
          <button type="button" id="btnAnual" onclick="setBillingCycle('annual')" class="px-4 py-1.5 text-xs font-bold rounded-xl text-slate-600 hover:text-slate-900 cursor-pointer hover:scale-[1.02] active:scale-95">
            Pago anual (ahorra hasta un 20%)
          </button>
        </div>
      </div>
    </div>

    <!-- Cards de Planes -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 my-auto">
      
      <!-- PLAN INICIAL -->
      <div class="flex flex-col justify-between p-5 bg-white border border-slate-200 hover:border-[#146F8A] rounded-(24px) shadow-sm hover:shadow-[0_15px_30px_rgba(20,111,138,0.12)] hover:-translate-y-1.5 group">
        <div class="flex flex-col gap-3">
          <div class="flex flex-col gap-0.5">
            <h3 class="text-base font-bold text-slate-900 group-hover:text-[#146F8A]">Plan inicial</h3>
            <p class="text-[11px] text-slate-500">Pulperías pequeñas o en etapa de digitalización</p>
          </div>
          <div class="flex flex-col">
            <div class="flex items-baseline gap-1">
              <span id="price-inicial" class="text-2xl font-extrabold text-slate-900">C$ 1,160.00</span>
              <span class="text-xs text-slate-500 font-medium">/mes</span>
            </div>
            <span id="subtext-inicial" class="text-[11px] text-slate-500">$32 USD por mes</span>
            <div id="badge-inicial" class="mt-1.5 inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-bold w-fit">
              C$ 13,920.00 facturado anualmente
            </div>
          </div>
          <hr class="border-slate-100 my-1">
          <ul class="flex flex-col gap-2 text-[11px] text-slate-600">
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> 1 Caja / POS activo</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> 1 Sucursal</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> POS de cobro en vivo</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Catálogo e Inventario completo</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Cierre de caja con Arqueo Ciego</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Devoluciones y Mermas</li>
          </ul>
        </div>
        <div class="pt-4">
          <button type="button" onclick="openCheckoutModal('Plan inicial')" class="w-full h-10 bg-slate-100 group-hover:bg-[#146F8A] text-slate-700 group-hover:text-white font-bold text-xs tracking-wide rounded-xl shadow-sm hover:shadow-md cursor-pointer active:scale-95">
            Seleccionar Plan Inicial
          </button>
        </div>
      </div>

      <!-- PLAN COMERCIO (Más Popular) -->
      <div class="flex flex-col justify-between p-5 bg-linear-to-b from-sky-50/40 via-white to-white border-2 border-[#146F8A]/60 hover:border-[#146F8A] rounded-(24px) shadow-md hover:shadow-[0_20px_40px_rgba(20,111,138,0.18)] hover:-translate-y-2 relative group">
        <div class="flex flex-col gap-3">
          <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-slate-900 group-hover:text-[#146F8A]">Plan Comercio</h3>
            <span class="px-2.5 py-0.5 bg-[#146F8A]/10 text-[#146F8A] text-[9px] font-bold tracking-wide rounded-full shadow-sm">
              Más Popular
            </span>
          </div>
          <p class="text-[11px] text-slate-500">Minisúper, pulperías grandes y comercios consolidados</p>
          <div class="flex flex-col">
            <div class="flex items-baseline gap-1">
              <span id="price-comercio" class="text-2xl font-extrabold text-slate-900">C$ 2,280.00</span>
              <span class="text-xs text-slate-500 font-medium">/mes</span>
            </div>
            <span id="subtext-comercio" class="text-[11px] text-slate-500">$62 USD por mes</span>
            <div id="badge-comercio" class="mt-1.5 inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-bold w-fit">
              C$ 27,360.00 facturado anualmente
            </div>
          </div>
          <hr class="border-slate-100 my-1">
          <ul class="flex flex-col gap-2 text-[11px] text-slate-600">
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Hasta 3 Cajas simultáneas</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> 1 Sucursal</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Todo lo del Plan Inicial</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Cuentas por Cobrar (Fiados)</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Verificación 3-Way Match</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Centro de Alertas y Anomalías</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Mapa de Proveedores integrado</li>
          </ul>
        </div>
        <div class="pt-4">
          <button type="button" onclick="openCheckoutModal('Plan Comercio')" class="w-full h-10 bg-[#146F8A] hover:bg-[#10596e] text-white font-bold text-xs tracking-wide rounded-xl shadow-md shadow-[#146F8A]/25 hover:shadow-lg cursor-pointer active:scale-95 hover:scale-[1.01]">
            Comenzar Prueba Gratis de 7 Días
          </button>
        </div>
      </div>

      <!-- PLAN CADENA -->
      <div class="flex flex-col justify-between p-5 bg-white border border-slate-200 hover:border-[#146F8A] rounded-(24px) shadow-sm hover:shadow-[0_15px_30px_rgba(20,111,138,0.12)] hover:-translate-y-1.5 group">
        <div class="flex flex-col gap-3">
          <div class="flex flex-col gap-0.5">
            <h3 class="text-base font-bold text-slate-900 group-hover:text-[#146F8A]">Plan Cadena</h3>
            <p class="text-[11px] text-slate-500">Comerciantes con múltiples puntos de venta o bodega central</p>
          </div>
          <div class="flex flex-col">
            <div class="flex items-baseline gap-1">
              <span id="price-cadena" class="text-2xl font-extrabold text-slate-900">C$ 4,400.00</span>
              <span class="text-xs text-slate-500 font-medium">/mes</span>
            </div>
            <span id="subtext-cadena" class="text-[11px] text-slate-500">$120 USD por mes</span>
            <div id="badge-cadena" class="mt-1.5 inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-bold w-fit">
              C$ 52,800.00 facturado anualmente
            </div>
          </div>
          <hr class="border-slate-100 my-1">
          <ul class="flex flex-col gap-2 text-[11px] text-slate-600">
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Cajas ilimitadas</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Hasta 5 Sucursales conectadas</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Todo lo del Plan Comercio</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Reportes y Analítica avanzada</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Gestión de Personal y Roles</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Transferencia entre bodegas</li>
            <li class="flex items-center gap-2 group-hover:translate-x-1"><span class="text-emerald-600 font-bold">✓</span> Asesor dedicado</li>
          </ul>
        </div>
        <div class="pt-4">
          <button type="button" onclick="openCheckoutModal('Plan Cadena')" class="w-full h-10 bg-slate-100 group-hover:bg-[#146F8A] text-slate-700 group-hover:text-white font-bold text-xs tracking-wide rounded-xl shadow-sm hover:shadow-md cursor-pointer active:scale-95">
            Seleccionar Plan Cadena
          </button>
        </div>
      </div>

    </div>

    <div class="text-center text-[10px] text-slate-400 font-medium pt-2">
      © 2026 Gintly. Diseñado para optimizar tu empresa.
    </div>

  </div>

  <!-- VENTANA POP-UP MODAL DE PAGO / CHECKOUT CON ALPINE.JS -->
  <div id="checkoutModal" 
       class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm opacity-0 pointer-events-none p-4 overflow-y-auto"
       x-data="{
         form: {
           nombres: '',
           razon: '',
           correo: '',
           cardNum: '',
           cardName: '',
           cardExp: '',
           cardCvc: '',
           transferRef: ''
         },
         method: 'tarjeta',
         get isFormValid() {
           const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
           const isEmailOk = emailRegex.test(this.form.correo);
           const isNamesOk = this.form.nombres.trim().length >= 3;
           const isRazonOk = this.form.razon.trim().length >= 2;

           if (!isNamesOk || !isRazonOk || !isEmailOk) return false;

           if (this.method === 'tarjeta') {
             const cleanCard = this.form.cardNum.replace(/\s/g, '');
             return cleanCard.length >= 15 &&
                    this.form.cardName.trim().length >= 3 &&
                    this.form.cardExp.length === 5 &&
                    this.form.cardCvc.length >= 3;
           } else {
             return this.form.transferRef.trim().length >= 3;
           }
         }
       }">
    
    <div class="bg-white w-full max-w-(900px) rounded-[28px] shadow-2xl border border-white p-6 md:p-8 transform scale-95 animate-modal my-auto flex flex-col md:flex-row gap-6 max-h-[90vh] overflow-y-auto custom-scroll">
      
      <!-- Columna Izquierda del Modal -->
      <div class="w-full md:w-[38%] flex flex-col justify-between border-b md:border-b-0 md:border-r border-slate-100 pb-5 md:pb-0 md:pr-5">
        <div class="flex flex-col gap-3">
          <div class="flex justify-between items-start">
            <h3 id="modalPlanTitle" class="text-base font-bold text-slate-900">Plan inicial</h3>
            <button type="button" onclick="closeCheckoutModal()" class="md:hidden w-7 h-7 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-xs hover:bg-slate-200 hover:rotate-90 cursor-pointer">✕</button>
          </div>
          <p id="modalPlanDesc" class="text-[11px] text-slate-500">Pulperías pequeñas o en etapa de digitalización</p>
          <div class="flex flex-col">
            <div class="flex items-baseline gap-1">
              <span id="modalPlanPrice" class="text-xl font-extrabold text-slate-900">C$ 1,160.00</span>
              <span class="text-xs text-slate-500 font-medium">/mes</span>
            </div>
            <span id="modalPlanUSD" class="text-[11px] text-slate-500">$32 USD por mes</span>
            <div id="modalPlanBadge" class="mt-1.5 inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-bold w-fit">
              C$ 13,920.00 facturado anualmente
            </div>
          </div>
          <hr class="border-slate-100">
          <ul id="modalPlanFeatures" class="flex flex-col gap-1.5 text-[11px] text-slate-600"></ul>
        </div>
        <div class="pt-4">
          <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 text-center hover:bg-sky-50/50 hover:border-sky-200">
            <span class="text-[10px] text-slate-500 font-medium">Garantía de satisfacción de 7 días. Cancela cuando quieras.</span>
          </div>
        </div>
      </div>

      <!-- Columna Derecha del Modal (Formulario Reactivo) -->
      <div class="w-full md:w-[62%] flex flex-col gap-3">
        
        <div class="flex justify-between items-center">
          <h3 class="text-sm font-bold text-slate-900">Detalles de facturación y pago</h3>
          <button type="button" onclick="closeCheckoutModal()" class="hidden md:flex w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 items-center justify-center cursor-pointer text-xs hover:rotate-90">
            ✕
          </button>
        </div>

        <form id="checkoutForm" @submit.prevent="submitCheckoutForm" class="flex flex-col gap-3" novalidate>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <!-- Nombres -->
            <div class="flex flex-col gap-0.5">
              <label class="text-[11px] font-bold text-slate-900">Nombres completos</label>
              <input type="text" 
                     x-model="form.nombres" 
                     @keypress="return validateTextOnly(event)" 
                     placeholder="Ejemplo: María José" 
                     class="w-full h-10 px-3 bg-white border rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                     :class="{
                       'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.nombres.length === 0,
                       'border-emerald-500 focus:border-emerald-500 focus:ring-emerald-500/10': form.nombres.trim().length >= 3,
                       'border-rose-500 focus:border-rose-500 focus:ring-rose-500/10': form.nombres.length > 0 && form.nombres.trim().length < 3
                     }">
              <span class="text-[9px]" 
                    :class="{
                      'text-slate-400': form.nombres.length === 0,
                      'text-emerald-600 font-semibold': form.nombres.trim().length >= 3,
                      'text-rose-500 font-semibold': form.nombres.length > 0 && form.nombres.trim().length < 3
                    }"
                    x-text="form.nombres.length === 0 ? 'ℹ Solo texto alfabético (Mínimo 3 caracteres)' : (form.nombres.trim().length >= 3 ? '✓ Nombre válido' : '✕ Mínimo 3 caracteres requeridos')">
              </span>
            </div>

            <!-- Razón Social -->
            <div class="flex flex-col gap-0.5">
              <label class="text-[11px] font-bold text-slate-900">Razón social</label>
              <input type="text" 
                     x-model="form.razon" 
                     @keypress="return validateTextOnly(event)" 
                     placeholder="Ejemplo: Cruz Valdivia" 
                     class="w-full h-10 px-3 bg-white border rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                     :class="{
                       'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.razon.length === 0,
                       'border-emerald-500 focus:border-emerald-500 focus:ring-emerald-500/10': form.razon.trim().length >= 2,
                       'border-rose-500 focus:border-rose-500 focus:ring-rose-500/10': form.razon.length > 0 && form.razon.trim().length < 2
                     }">
              <span class="text-[9px]" 
                    :class="{
                      'text-slate-400': form.razon.length === 0,
                      'text-emerald-600 font-semibold': form.razon.trim().length >= 2,
                      'text-rose-500 font-semibold': form.razon.length > 0 && form.razon.trim().length < 2
                    }"
                    x-text="form.razon.length === 0 ? 'ℹ Solo texto alfabético' : (form.razon.trim().length >= 2 ? '✓ Razón social válida' : '✕ Ingrese una razón social válida')">
              </span>
            </div>
          </div>

          <!-- Correo Electrónico -->
          <div class="flex flex-col gap-0.5">
            <label class="text-[11px] font-bold text-slate-900">Correo electrónico</label>
            <input type="email" 
                   x-model="form.correo" 
                   placeholder="Ejemplo: mariajosecruz21@gmail.com" 
                   class="w-full h-10 px-3 bg-white border rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                   :class="{
                     'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.correo.length === 0,
                     'border-emerald-500 focus:border-emerald-500 focus:ring-emerald-500/10': /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.correo),
                     'border-rose-500 focus:border-rose-500 focus:ring-rose-500/10': form.correo.length > 0 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.correo)
                   }">
            <span class="text-[9px]" 
                  :class="{
                    'text-slate-400': form.correo.length === 0,
                    'text-emerald-600 font-semibold': /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.correo),
                    'text-rose-500 font-semibold': form.correo.length > 0 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.correo)
                  }"
                  x-text="form.correo.length === 0 ? 'ℹ Formato requerido (ej: usuario@dominio.com)' : (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.correo) ? '✓ Correo electrónico válido' : '✕ Formato de correo electrónico incorrecto')">
            </span>
          </div>

          <!-- Método de pago -->
          <div class="flex flex-col gap-0.5">
            <label class="text-[11px] font-bold text-slate-900">Método de pago</label>
            <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl border border-slate-200 shadow-inner">
              <button type="button" @click="method = 'tarjeta'; setPaymentMethod('tarjeta')" :class="method === 'tarjeta' ? 'bg-[#146F8A] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="py-2 text-[11px] font-bold rounded-lg cursor-pointer hover:scale-[1.01] active:scale-95">
                Tarjeta de Crédito / Débito
              </button>
              <button type="button" @click="method = 'transferencia'; setPaymentMethod('transferencia')" :class="method === 'transferencia' ? 'bg-[#146F8A] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="py-2 text-[11px] font-bold rounded-lg cursor-pointer hover:scale-[1.01] active:scale-95">
                Transferencia Bancaria
              </button>
            </div>
          </div>

          <!-- Campos de Tarjeta -->
          <div id="cardPaymentFields" class="flex flex-col gap-3" x-show="method === 'tarjeta'">
            <div class="flex flex-col gap-0.5">
              <label class="text-[11px] font-bold text-slate-900">Número de tarjeta</label>
              <input type="text" x-model="form.cardNum" placeholder="0000 0000 0000 0000" maxlength="19" @input="formatCardNumber($event)" @keypress="return validateNumberOnly(event)" 
                     class="w-full h-10 px-3 bg-white border rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                     :class="{
                       'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.cardNum.length === 0,
                       'border-emerald-500 focus:border-emerald-500': form.cardNum.replace(/\s/g, '').length >= 15,
                       'border-rose-500 focus:border-rose-500': form.cardNum.length > 0 && form.cardNum.replace(/\s/g, '').length < 15
                     }">
            </div>
            <div class="flex flex-col gap-0.5">
              <label class="text-[11px] font-bold text-slate-900">Nombre en la tarjeta</label>
              <input type="text" x-model="form.cardName" placeholder="Tal y como aparece en la tarjeta" @keypress="return validateTextOnly(event)" 
                     class="w-full h-10 px-3 bg-white border rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                     :class="{
                       'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.cardName.length === 0,
                       'border-emerald-500 focus:border-emerald-500': form.cardName.trim().length >= 3,
                       'border-rose-500 focus:border-rose-500': form.cardName.length > 0 && form.cardName.trim().length < 3
                     }">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="flex flex-col gap-0.5">
                <label class="text-[11px] font-bold text-slate-900">Vencimiento (MM/AA)</label>
                <input type="text" x-model="form.cardExp" placeholder="MM/AA" maxlength="5" @input="formatExpiryDate($event)" @keypress="return validateNumberOnly(event)" 
                       class="w-full h-10 px-3 bg-white border rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                       :class="{
                         'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.cardExp.length === 0,
                         'border-emerald-500 focus:border-emerald-500': form.cardExp.length === 5,
                         'border-rose-500 focus:border-rose-500': form.cardExp.length > 0 && form.cardExp.length < 5
                       }">
              </div>
              <div class="flex flex-col gap-0.5">
                <label class="text-[11px] font-bold text-slate-900">CVC / CVV</label>
                <input type="password" x-model="form.cardCvc" placeholder="••••" maxlength="4" @keypress="return validateNumberOnly(event)" 
                       class="w-full h-10 px-3 bg-white border rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                       :class="{
                         'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.cardCvc.length === 0,
                         'border-emerald-500 focus:border-emerald-500': form.cardCvc.length >= 3,
                         'border-rose-500 focus:border-rose-500': form.cardCvc.length > 0 && form.cardCvc.length < 3
                       }">
              </div>
            </div>
          </div>

          <!-- Campos de Transferencia -->
          <div id="transferPaymentFields" class="flex-col gap-2.5 p-3.5 bg-slate-50/80 hover:bg-slate-50 rounded-xl border border-slate-200 text-[11px]" x-show="method === 'transferencia'" style="display: none;">
            <span class="font-bold text-slate-900">Cuentas bancarias habilitadas (BAC Credomatic)</span>
            <p class="text-slate-600 leading-relaxed">
              <strong>USD:</strong> 362819201 | <strong>NIO:</strong> 362819219<br>
              A nombre de: <em>Gintly S.A.</em>
            </p>
            <div class="flex flex-col gap-1 pt-1 border-t border-slate-200">
              <label class="font-bold text-slate-900">Número de referencia o comprobante de transferencia *</label>
              <input type="text" x-model="form.transferRef" placeholder="Ej: TRF-98231049" 
                     class="w-full h-9 px-3 bg-white border rounded-lg text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
                     :class="{
                       'border-slate-200 focus:border-[#146F8A] focus:ring-[#146F8A]/10': form.transferRef.length === 0,
                       'border-emerald-500 focus:border-emerald-500': form.transferRef.trim().length >= 3,
                       'border-rose-500 focus:border-rose-500': form.transferRef.length > 0 && form.transferRef.trim().length < 3
                     }">
              <span class="text-[9px] text-slate-400">Obligatorio para verificar y activar tu suscripción de inmediato.</span>
            </div>
          </div>

          <!-- Botón de Confirmación Controlado por Estado (Deshabilitado hasta completar datos válidos) -->
          <div class="pt-2">
            <button type="submit" 
                    :disabled="!isFormValid"
                    :class="isFormValid ? 'bg-[#146F8A] hover:bg-[#10596e] text-white shadow-lg shadow-[#146F8A]/20 cursor-pointer active:scale-95 hover:scale-[1.01]' : 'bg-slate-300 text-slate-500 cursor-not-allowed opacity-75 shadow-none'"
                    class="w-full h-10 font-bold text-xs tracking-wide rounded-xl transition-all duration-200">
              Confirmar Suscripción
            </button>
          </div>

        </form>

      </div>

    </div>
  </div>

  <script>
    let billingCycle = 'monthly';
    let currentPaymentMethod = 'tarjeta';
    const checkoutModal = document.getElementById('checkoutModal');

    const plansData = {
      'Plan inicial': {
        desc: 'Pulperías pequeñas o en etapa de digitalización',
        monthly: { price: 'C$ 1,160.00', usd: '$32 USD por mes', badge: 'C$ 13,920.00 facturado anualmente' },
        annual: { price: 'C$ 928.00', usd: '$26 USD por mes', badge: 'C$ 11,136.00 facturado anualmente (Ahorras 20%)' },
        features: ['1 Caja / POS activo', '1 Sucursal', 'POS de cobro en vivo', 'Catálogo e Inventario completo', 'Cierre de caja con Arqueo Ciego', 'Devoluciones y Mermas']
      },
      'Plan Comercio': {
        desc: 'Minisúper, pulperías grandes y comercios consolidados',
        monthly: { price: 'C$ 2,280.00', usd: '$62 USD por mes', badge: 'C$ 27,360.00 facturado anualmente' },
        annual: { price: 'C$ 1,824.00', usd: '$50 USD por mes', badge: 'C$ 21,888.00 facturado anualmente (Ahorras 20%)' },
        features: ['Hasta 3 Cajas simultáneas', '1 Sucursal', 'Todo lo del Plan Inicial', 'Cuentas por Cobrar (Fiados)', 'Verificación 3-Way Match', 'Centro de Alertas y Anomalías', 'Mapa de Proveedores integrado']
      },
      'Plan Cadena': {
        desc: 'Comerciantes con múltiples puntos de venta o bodega central',
        monthly: { price: 'C$ 4,400.00', usd: '$120 USD por mes', badge: 'C$ 52,800.00 facturado anualmente' },
        annual: { price: 'C$ 3,520.00', usd: '$96 USD por mes', badge: 'C$ 42,240.00 facturado anualmente (Ahorras 20%)' },
        features: ['Cajas ilimitadas', 'Hasta 5 Sucursales conectadas', 'Todo lo del Plan Comercio', 'Reportes y Analítica avanzada', 'Gestión de Personal y Roles', 'Transferencia entre bodegas', 'Asesor dedicado']
      }
    };

    function setBillingCycle(cycle) {
        billingCycle = cycle;
        const btnM = document.getElementById('btnMensual');
        const btnA = document.getElementById('btnAnual');

        if (cycle === 'monthly') {
            btnM.className = "px-4 py-1.5 text-xs font-bold rounded-xl bg-[#146F8A] text-white shadow-sm cursor-pointer hover:scale-[1.02] active:scale-95";
            btnA.className = "px-4 py-1.5 text-xs font-bold rounded-xl text-slate-600 hover:text-slate-900 cursor-pointer hover:scale-[1.02] active:scale-95";
            
            document.getElementById('price-inicial').innerText = plansData['Plan inicial'].monthly.price;
            document.getElementById('subtext-inicial').innerText = plansData['Plan inicial'].monthly.usd;
            document.getElementById('badge-inicial').innerText = plansData['Plan inicial'].monthly.badge;

            document.getElementById('price-comercio').innerText = plansData['Plan Comercio'].monthly.price;
            document.getElementById('subtext-comercio').innerText = plansData['Plan Comercio'].monthly.usd;
            document.getElementById('badge-comercio').innerText = plansData['Plan Comercio'].monthly.badge;

            document.getElementById('price-cadena').innerText = plansData['Plan Cadena'].monthly.price;
            document.getElementById('subtext-cadena').innerText = plansData['Plan Cadena'].monthly.usd;
            document.getElementById('badge-cadena').innerText = plansData['Plan Cadena'].monthly.badge;
        } else {
            btnA.className = "px-4 py-1.5 text-xs font-bold rounded-xl bg-[#146F8A] text-white shadow-sm cursor-pointer hover:scale-[1.02] active:scale-95";
            btnM.className = "px-4 py-1.5 text-xs font-bold rounded-xl text-slate-600 hover:text-slate-900 cursor-pointer hover:scale-[1.02] active:scale-95";
            
            document.getElementById('price-inicial').innerText = plansData['Plan inicial'].annual.price;
            document.getElementById('subtext-inicial').innerText = plansData['Plan inicial'].annual.usd;
            document.getElementById('badge-inicial').innerText = plansData['Plan inicial'].annual.badge;

            document.getElementById('price-comercio').innerText = plansData['Plan Comercio'].annual.price;
            document.getElementById('subtext-comercio').innerText = plansData['Plan Comercio'].annual.usd;
            document.getElementById('badge-comercio').innerText = plansData['Plan Comercio'].annual.badge;

            document.getElementById('price-cadena').innerText = plansData['Plan Cadena'].annual.price;
            document.getElementById('subtext-cadena').innerText = plansData['Plan Cadena'].annual.usd;
            document.getElementById('badge-cadena').innerText = plansData['Plan Cadena'].annual.badge;
        }
    }

    function openCheckoutModal(planName) {
        const data = plansData[planName];
        const currentData = billingCycle === 'monthly' ? data.monthly : data.annual;

        document.getElementById('modalPlanTitle').innerText = planName;
        document.getElementById('modalPlanDesc').innerText = data.desc;
        document.getElementById('modalPlanPrice').innerText = currentData.price;
        document.getElementById('modalPlanUSD').innerText = currentData.usd;
        document.getElementById('modalPlanBadge').innerText = currentData.badge;

        const featuresList = document.getElementById('modalPlanFeatures');
        featuresList.innerHTML = '';
        data.features.forEach(feat => {
            const li = document.createElement('li');
            li.className = "flex items-center gap-2 hover:translate-x-1";
            li.innerHTML = `<span class="text-emerald-600 font-bold">✓</span> ${feat}`;
            featuresList.appendChild(li);
        });

        checkoutModal.classList.remove('opacity-0', 'pointer-events-none');
    }

    function closeCheckoutModal() {
        checkoutModal.classList.add('opacity-0', 'pointer-events-none');
    }

    function setPaymentMethod(method) {
        currentPaymentMethod = method;
    }

    function validateTextOnly(event) {
        const char = String.fromCharCode(event.keyCode || event.which);
        const pattern = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]*$/;
        return pattern.test(char);
    }

    function validateNumberOnly(event) {
        const char = String.fromCharCode(event.keyCode || event.which);
        const pattern = /^[0-9]*$/;
        return pattern.test(char);
    }

    function formatCardNumber(event) {
        let input = event.target;
        let value = input.value.replace(/\D/g, '');
        value = value.substring(0, 16);
        let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
        input.value = formatted;
        input.dispatchEvent(new Event('input')); 
    }

    function formatExpiryDate(event) {
        let input = event.target;
        let value = input.value.replace(/\D/g, '');
        value = value.substring(0, 4);
        if (value.length >= 3) {
            value = value.substring(0, 2) + '/' + value.substring(2);
        }
        input.value = value;
        input.dispatchEvent(new Event('input')); 
    }

    function submitCheckoutForm() {
        // Redirige al paso 7 utilizando la ruta agrupada con parámetros de Laravel
        window.location.href = "{{ route('register.step', ['step' => 7]) }}";
    }

    checkoutModal.addEventListener('click', (e) => {
        if (e.target === checkoutModal) closeCheckoutModal();
    });
  </script>