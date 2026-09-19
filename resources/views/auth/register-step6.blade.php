<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gintly App - Suscripción y Planes</title>
  @vite([
    'resources/css/app.css',
    'resources/js/modules/registration/wizard.js',
  ])
</head>
<body data-registration-step="6" class="registration-page bg-linear-to-br from-slate-50 via-sky-50/30 to-teal-50/20 flex justify-center items-center min-h-screen p-3 md:p-5 overflow-y-auto">

  <!-- Contenedor Principal -->
  <div class="flex flex-col w-full max-w-[1240px] h-auto my-5 bg-white/95 backdrop-blur-xl rounded-[28px] shadow-[0_20px_50px_rgba(12,67,83,0.08)] border border-white p-5 md:p-8 animate-fade-in justify-between">    
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
          <button type="button" id="btnMensual" data-billing-cycle="monthly" class="px-4 py-1.5 text-xs font-bold rounded-xl bg-[#146F8A] text-white shadow-sm cursor-pointer hover:scale-[1.02] active:scale-95">
            Pago mensual
          </button>
          <button type="button" id="btnAnual" data-billing-cycle="annual" class="px-4 py-1.5 text-xs font-bold rounded-xl text-slate-600 hover:text-slate-900 cursor-pointer hover:scale-[1.02] active:scale-95">
            Pago anual (ahorra hasta un 20%)
          </button>
        </div>
      </div>
    </div>

    <!-- Cards de Planes -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 my-auto">
      
      <!-- PLAN INICIAL -->
      <div class="flex flex-col justify-between p-5 bg-white border border-slate-200 hover:border-[#146F8A] rounded-[24px] shadow-sm hover:shadow-[0_15px_30px_rgba(20,111,138,0.12)] hover:-translate-y-1.5 group">
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
          <button type="button" data-plan="Plan inicial" class="w-full h-10 bg-slate-100 group-hover:bg-[#146F8A] text-slate-700 group-hover:text-white font-bold text-xs tracking-wide rounded-xl shadow-sm hover:shadow-md cursor-pointer active:scale-95">
            Seleccionar Plan Inicial
          </button>
        </div>
      </div>

      <!-- PLAN COMERCIO (Más Popular) -->
      <div class="flex flex-col justify-between p-5 bg-linear-to-b from-sky-50/40 via-white to-white border-2 border-[#146F8A]/60 hover:border-[#146F8A] rounded-[24px] shadow-md hover:shadow-[0_20px_40px_rgba(20,111,138,0.18)] hover:-translate-y-2 relative group">
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
          <button type="button" data-plan="Plan Comercio" class="w-full h-10 bg-[#146F8A] hover:bg-[#10596e] text-white font-bold text-xs tracking-wide rounded-xl shadow-md shadow-[#146F8A]/25 hover:shadow-lg cursor-pointer active:scale-95 hover:scale-[1.01]">
            Comenzar Prueba Gratis de 7 Días
          </button>
        </div>
      </div>

      <!-- PLAN CADENA -->
      <div class="flex flex-col justify-between p-5 bg-white border border-slate-200 hover:border-[#146F8A] rounded-[24px] shadow-sm hover:shadow-[0_15px_30px_rgba(20,111,138,0.12)] hover:-translate-y-1.5 group">
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
          <button type="button" data-plan="Plan Cadena" class="w-full h-10 bg-slate-100 group-hover:bg-[#146F8A] text-slate-700 group-hover:text-white font-bold text-xs tracking-wide rounded-xl shadow-sm hover:shadow-md cursor-pointer active:scale-95">
            Seleccionar Plan Cadena
          </button>
        </div>
      </div>

    </div>

    <div class="text-center text-[10px] text-slate-400 font-medium pt-2">
      © 2026 Gintly. Diseñado para optimizar tu empresa.
    </div>

  </div>

  <!-- Ventana modal de pago / checkout -->
  <div id="checkoutModal" 
       class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm opacity-0 pointer-events-none p-4 overflow-y-auto">
    
    <div class="bg-white w-full max-w-[900px] rounded-[28px] shadow-2xl border border-white p-6 md:p-8 transform scale-95 animate-modal my-auto flex flex-col md:flex-row gap-6 max-h-[90vh] overflow-y-auto custom-scroll">
      
      <!-- Columna Izquierda del Modal -->
      <div class="w-full md:w-[38%] flex flex-col justify-between border-b md:border-b-0 md:border-r border-slate-100 pb-5 md:pb-0 md:pr-5">
        <div class="flex flex-col gap-3">
          <div class="flex justify-between items-start">
            <h3 id="modalPlanTitle" class="text-base font-bold text-slate-900">Plan inicial</h3>
            <button type="button" data-checkout-close class="md:hidden w-7 h-7 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-xs hover:bg-slate-200 hover:rotate-90 cursor-pointer">✕</button>
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

      <!-- Columna Derecha del Modal (Formulario de pago) -->
      <div class="w-full md:w-[62%] flex flex-col gap-3">
        
        <div class="flex justify-between items-center">
          <h3 class="text-sm font-bold text-slate-900">Detalles de facturación y pago</h3>
          <button type="button" data-checkout-close class="hidden md:flex w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 items-center justify-center cursor-pointer text-xs hover:rotate-90">
            ✕
          </button>
        </div>

        <form id="checkoutForm" data-success-url="{{ route('register.step', ['step' => 7]) }}" class="flex flex-col gap-3" novalidate>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="flex flex-col gap-0.5">
              <label for="checkoutNames" class="text-[11px] font-bold text-slate-900">Nombres completos</label>
              <input
                id="checkoutNames"
                name="names"
                type="text"
                placeholder="Ejemplo: María José"
                class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
              >
              <span id="checkoutNamesMessage" class="text-[9px] text-slate-400">
                ℹ Solo texto alfabético (Mínimo 3 caracteres)
              </span>
            </div>

            <div class="flex flex-col gap-0.5">
              <label for="checkoutBusinessName" class="text-[11px] font-bold text-slate-900">Razón social</label>
              <input
                id="checkoutBusinessName"
                name="business_name"
                type="text"
                placeholder="Ejemplo: Cruz Valdivia"
                class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
              >
              <span id="checkoutBusinessMessage" class="text-[9px] text-slate-400">
                ℹ Solo texto alfabético
              </span>
            </div>
          </div>

          <div class="flex flex-col gap-0.5">
            <label for="checkoutEmail" class="text-[11px] font-bold text-slate-900">Correo electrónico</label>
            <input
              id="checkoutEmail"
              name="email"
              type="email"
              placeholder="Ejemplo: mariajosecruz21@gmail.com"
              class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm"
            >
            <span id="checkoutEmailMessage" class="text-[9px] text-slate-400">
              ℹ Formato requerido (ej: usuario@dominio.com)
            </span>
          </div>

          <div class="flex flex-col gap-0.5">
            <label class="text-[11px] font-bold text-slate-900">Método de pago</label>
            <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl border border-slate-200 shadow-inner">
              <button type="button" data-payment-method="tarjeta" class="py-2 text-[11px] font-bold rounded-lg cursor-pointer hover:scale-[1.01] active:scale-95 bg-[#146F8A] text-white shadow-sm">
                Tarjeta de Crédito / Débito
              </button>
              <button type="button" data-payment-method="transferencia" class="py-2 text-[11px] font-bold rounded-lg cursor-pointer hover:scale-[1.01] active:scale-95 text-slate-600 hover:text-slate-900">
                Transferencia Bancaria
              </button>
            </div>
          </div>

          <div id="cardPaymentFields" class="flex flex-col gap-3">
            <div class="flex flex-col gap-0.5">
              <label for="checkoutCardNumber" class="text-[11px] font-bold text-slate-900">Número de tarjeta</label>
              <input id="checkoutCardNumber" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" maxlength="19"
                     class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm">
            </div>
            <div class="flex flex-col gap-0.5">
              <label for="checkoutCardName" class="text-[11px] font-bold text-slate-900">Nombre en la tarjeta</label>
              <input id="checkoutCardName" name="card_name" type="text" autocomplete="cc-name" placeholder="Tal y como aparece en la tarjeta"
                     class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="flex flex-col gap-0.5">
                <label for="checkoutCardExpiry" class="text-[11px] font-bold text-slate-900">Vencimiento (MM/AA)</label>
                <input id="checkoutCardExpiry" name="card_expiry" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AA" maxlength="5"
                       class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm">
              </div>
              <div class="flex flex-col gap-0.5">
                <label for="checkoutCardCvc" class="text-[11px] font-bold text-slate-900">CVC / CVV</label>
                <input id="checkoutCardCvc" name="card_cvc" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="••••" maxlength="4"
                       class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm">
              </div>
            </div>
          </div>

          <div id="transferPaymentFields" class="flex flex-col gap-2.5 p-3.5 bg-slate-50/80 hover:bg-slate-50 rounded-xl border border-slate-200 text-[11px]" hidden>
            <span class="font-bold text-slate-900">Cuentas bancarias habilitadas (BAC Credomatic)</span>
            <p class="text-slate-600 leading-relaxed">
              <strong>USD:</strong> 362819201 | <strong>NIO:</strong> 362819219<br>
              A nombre de: <em>Gintly S.A.</em>
            </p>
            <div class="flex flex-col gap-1 pt-1 border-t border-slate-200">
              <label for="checkoutTransferReference" class="font-bold text-slate-900">Número de referencia o comprobante de transferencia *</label>
              <input id="checkoutTransferReference" name="transfer_reference" type="text" placeholder="Ej: TRF-98231049"
                     class="w-full h-9 px-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 font-medium focus:outline-none focus:ring-4 shadow-sm">
              <span class="text-[9px] text-slate-400">Obligatorio para verificar y activar tu suscripción de inmediato.</span>
            </div>
          </div>

          <div class="pt-2">
            <button id="checkoutSubmit" type="submit" disabled
                    class="w-full h-10 font-bold text-xs tracking-wide rounded-xl transition-all duration-200 bg-slate-300 text-slate-500 cursor-not-allowed opacity-75 shadow-none">
              Confirmar Suscripción
            </button>
          </div>

        </form>

      </div>

    </div>
  </div>
