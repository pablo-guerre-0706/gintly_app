// public/js/auth-validation.js
function initAuthValidation() {
  const state = { nombre: false, apellido: false, correo: false, password: false, confirmPassword: false };

  const updateSubmitButton = () => {
    const btn = document.getElementById('submitBtn');
    if (!btn) return;
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

  // Función genérica reutilizable para validar inputs en cualquier vista
  window.validarCampo = function(key, inputId, msgId, condicion, textoError, textoValido) {
    const input = document.getElementById(inputId);
    const msg = document.getElementById(msgId);
    if (!input || !msg) return;

    input.addEventListener('input', () => {
      if (input.value.trim() === "") {
        input.classList.remove('border-rose-400', 'border-emerald-500', 'ring-2', 'ring-rose-400/20', 'ring-emerald-500/20');
        input.classList.add('border-slate-200');
        msg.textContent = "Es de carácter obligatorio";
        msg.className = "text-[10px] text-slate-500 flex items-center gap-1 transition-all duration-300";
        state[key] = false;
      } else if (condicion(input.value)) {
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
      updateSubmitButton();
    });
  };
}