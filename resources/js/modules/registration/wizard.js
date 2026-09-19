const ACTIVE_BUTTON_CLASSES = [
    'bg-[#146F8A]',
    'text-white',
    'hover:bg-[#10596e]',
    'shadow-lg',
    'shadow-[#146F8A]/25',
    'cursor-pointer',
    'active:scale-[0.99]',
];

const DISABLED_BUTTON_CLASSES = [
    'bg-slate-200',
    'text-slate-400',
    'cursor-not-allowed',
    'shadow-sm',
];

const byId = (id) => document.getElementById(id);

function setButtonEnabled(button, enabled) {
    if (!button) return;

    button.disabled = !enabled;
    button.classList.remove(...(enabled ? DISABLED_BUTTON_CLASSES : ACTIVE_BUTTON_CLASSES));
    button.classList.add(...(enabled ? ACTIVE_BUTTON_CLASSES : DISABLED_BUTTON_CLASSES));
}

function setValidationAppearance(input, message, state, text) {
    if (!input || !message) return;

    input.classList.remove(
        'border-slate-200',
        'border-rose-400',
        'border-rose-500',
        'border-emerald-500',
        'ring-2',
        'ring-rose-400/20',
        'ring-emerald-500/20',
    );

    message.textContent = text;

    if (state === 'valid') {
        input.classList.add('border-emerald-500', 'ring-2', 'ring-emerald-500/20');
        message.className = 'text-[10px] text-emerald-600 font-semibold flex items-center gap-1 transition-all duration-300';
        return;
    }

    if (state === 'invalid') {
        input.classList.add('border-rose-400', 'ring-2', 'ring-rose-400/20');
        message.className = 'text-[10px] text-rose-500 font-semibold flex items-center gap-1 transition-all duration-300';
        return;
    }

    input.classList.add('border-slate-200');
    message.className = 'text-[10px] text-slate-500 flex items-center gap-1 transition-all duration-300';
}

function initStepOne() {
    const form = byId('profileForm');
    if (!form) return;

    const state = {
        nombre: false,
        apellido: false,
        correo: false,
        password: false,
        confirmPassword: false,
    };
    const submit = byId('submitBtn');
    const updateSubmit = () => setButtonEnabled(submit, Object.values(state).every(Boolean));

    const registerValidation = ({ key, inputId, messageId, validate, invalid, valid }) => {
        const input = byId(inputId);
        const message = byId(messageId);
        if (!input || !message) return;

        const run = () => {
            if (!input.value.trim()) {
                state[key] = false;
                setValidationAppearance(input, message, 'neutral', 'Es de carácter obligatorio');
            } else if (validate()) {
                state[key] = true;
                setValidationAppearance(input, message, 'valid', `✓ ${valid}`);
            } else {
                state[key] = false;
                setValidationAppearance(input, message, 'invalid', `✕ ${invalid}`);
            }
            updateSubmit();
        };

        input.addEventListener('input', run);
        run();
    };

    registerValidation({ key: 'nombre', inputId: 'nombre', messageId: 'nombreMsg', validate: () => byId('nombre').value.trim().length >= 2, invalid: 'El nombre es demasiado corto', valid: 'Nombre válido' });
    registerValidation({ key: 'apellido', inputId: 'apellido', messageId: 'apellidoMsg', validate: () => byId('apellido').value.trim().length >= 2, invalid: 'El apellido es demasiado corto', valid: 'Apellido válido' });
    registerValidation({ key: 'correo', inputId: 'correo', messageId: 'correoMsg', validate: () => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(byId('correo').value), invalid: 'Correo no válido', valid: 'Correo electrónico válido' });
    registerValidation({ key: 'password', inputId: 'password', messageId: 'passwordMsg', validate: () => /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{12,}$/.test(byId('password').value), invalid: 'Mínimo 12 caracteres, letras y números', valid: 'Contraseña segura' });
    registerValidation({ key: 'confirmPassword', inputId: 'confirmPassword', messageId: 'confirmMsg', validate: () => byId('confirmPassword').value === byId('password').value && byId('confirmPassword').value !== '', invalid: 'Las contraseñas no coinciden', valid: 'Las contraseñas coinciden' });

    byId('password')?.addEventListener('input', () => byId('confirmPassword')?.dispatchEvent(new Event('input')));
    byId('codigoPais')?.addEventListener('input', (event) => {
        const value = event.currentTarget.value;
        if (!value.startsWith('+')) event.currentTarget.value = `+${value.replace(/\+/g, '')}`;
    });
    byId('telefono')?.addEventListener('input', (event) => {
        event.currentTarget.value = event.currentTarget.value.replace(/\D/g, '');
    });

    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        form.dataset.submitting = 'true';
        byId('mainContainer')?.classList.add('page-transition-out');
        window.setTimeout(() => form.submit(), 320);
    });
}

function initStepTwo() {
    const form = byId('businessForm');
    if (!form) return;

    const state = {
        nombre_tienda: false,
        pais_region: false,
        ciudad: false,
        codigo_postal: false,
        direccion: false,
        correo_tienda: true,
        numero_convencional: true,
        numero_sucursales: false,
        ruc: false,
    };
    const submit = byId('submitBtn');
    const updateSubmit = () => setButtonEnabled(submit, Object.values(state).every(Boolean));

    const registerValidation = ({ key, inputId, messageId, validate, invalid, valid, empty = 'Es de carácter obligatorio', optional = false }) => {
        const input = byId(inputId);
        const message = byId(messageId);
        if (!input || !message) return;

        const run = () => {
            if (!input.value.trim()) {
                state[key] = optional;
                setValidationAppearance(input, message, 'neutral', empty);
            } else if (validate()) {
                state[key] = true;
                setValidationAppearance(input, message, 'valid', `✓ ${valid}`);
            } else {
                state[key] = false;
                setValidationAppearance(input, message, 'invalid', `✕ ${invalid}`);
            }
            updateSubmit();
        };

        input.addEventListener('input', run);
        input.addEventListener('change', run);
        run();
    };

    registerValidation({ key: 'nombre_tienda', inputId: 'nombre_tienda', messageId: 'nombreTiendaMsg', validate: () => byId('nombre_tienda').value.trim().length >= 2, invalid: 'El nombre de la tienda es muy corto', valid: 'Nombre válido' });
    registerValidation({ key: 'pais_region', inputId: 'pais_region', messageId: 'paisRegionMsg', validate: () => byId('pais_region').value.trim().length >= 2, invalid: 'El país o región es muy corto', valid: 'País válido' });
    registerValidation({ key: 'ciudad', inputId: 'ciudad', messageId: 'ciudadMsg', validate: () => byId('ciudad').value.trim().length >= 2, invalid: 'El nombre de la ciudad es muy corto', valid: 'Ciudad válida' });
    registerValidation({ key: 'codigo_postal', inputId: 'codigo_postal', messageId: 'codigoPostalMsg', validate: () => byId('codigo_postal').value.trim().length >= 3, invalid: 'Código postal inválido', valid: 'Código postal válido' });
    registerValidation({ key: 'direccion', inputId: 'direccion', messageId: 'direccionMsg', validate: () => byId('direccion').value.trim().length >= 5, invalid: 'La dirección es muy corta', valid: 'Dirección válida' });
    registerValidation({ key: 'numero_sucursales', inputId: 'numero_sucursales', messageId: 'sucursalesMsg', validate: () => Number.parseInt(byId('numero_sucursales').value, 10) >= 1, invalid: 'Debe ser al menos 1 sucursal', valid: 'Cantidad válida', empty: 'Obligatorio para entender la capacidad de tu negocio.' });
    registerValidation({ key: 'ruc', inputId: 'ruc', messageId: 'rucMsg', validate: () => byId('ruc').value.trim().length >= 5, invalid: 'RUC o identificación fiscal inválida', valid: 'RUC válido' });
    registerValidation({ key: 'correo_tienda', inputId: 'correo_tienda', messageId: 'correoTiendaMsg', validate: () => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(byId('correo_tienda').value), invalid: 'Formato de correo no válido', valid: 'Correo de tienda válido', empty: 'Opcional, pero recomendado como contacto', optional: true });

    const phone = byId('numero_convencional');
    phone?.addEventListener('input', () => { phone.value = phone.value.replace(/\D/g, ''); });
    registerValidation({ key: 'numero_convencional', inputId: 'numero_convencional', messageId: 'numConvencionalMsg', validate: () => phone.value === '' || /^[0-9]{7,15}$/.test(phone.value), invalid: 'Debe tener entre 7 y 15 dígitos', valid: 'Número convencional válido', empty: 'Opcional, pero recomendado como contacto', optional: true });
}

function initStepThree() {
    const form = byId('businessTypeForm');
    if (!form) return;

    const cards = [...document.querySelectorAll('.business-card')];
    const hiddenInput = byId('tipo_negocio');
    const submit = byId('submitBtn');

    const selectCard = (card) => {
        cards.forEach((item) => {
            item.classList.remove('border-[#146F8A]', 'bg-sky-50/40', 'ring-2', 'ring-[#146F8A]/25', 'shadow-md');
            item.classList.add('border-slate-200', 'bg-white');
            item.querySelector('.check-box')?.classList.remove('bg-[#146F8A]', 'border-[#146F8A]');
            item.querySelector('.check-box')?.classList.add('bg-white', 'border-slate-300');
            item.querySelector('.check-icon')?.classList.add('hidden');
        });

        card.classList.remove('border-slate-200', 'bg-white');
        card.classList.add('border-[#146F8A]', 'bg-sky-50/40', 'ring-2', 'ring-[#146F8A]/25', 'shadow-md', 'animate-scale-up');
        window.setTimeout(() => card.classList.remove('animate-scale-up'), 300);
        card.querySelector('.check-box')?.classList.remove('bg-white', 'border-slate-300');
        card.querySelector('.check-box')?.classList.add('bg-[#146F8A]', 'border-[#146F8A]');
        card.querySelector('.check-icon')?.classList.remove('hidden');

        hiddenInput.value = card.dataset.value ?? '';
        setButtonEnabled(submit, Boolean(hiddenInput.value));
    };

    cards.forEach((card) => card.addEventListener('click', () => selectCard(card)));
    const selected = cards.find((card) => card.dataset.value === hiddenInput?.value);
    if (selected) selectCard(selected);
}

function initStepFour() {
    const form = byId('regionalForm');
    if (!form) return;

    const controls = [byId('moneda'), byId('fecha_creacion'), byId('zona_horaria')];
    const update = () => setButtonEnabled(byId('submitBtn'), controls.every((control) => control?.value));
    controls.forEach((control) => {
        control?.addEventListener('input', update);
        control?.addEventListener('change', update);
    });
    update();
}

function initStepFive() {
    const form = byId('employeeForm');
    const modal = byId('employeeModal');
    const deleteModal = byId('deleteModal');
    const list = byId('empleadosListContainer');
    const confirm = byId('confirmBtn');
    if (!form || !modal || !deleteModal || !list || !confirm) return;

    let employees = [];
    const validation = {
        emp_nombre: false,
        emp_apellido: false,
        emp_correo: false,
        emp_telefono: false,
        emp_rol: false,
        emp_password: false,
        emp_password_confirmation: false,
    };

    const checkForm = () => setButtonEnabled(byId('modalSubmitBtn'), Object.values(validation).every(Boolean));
    const setSimpleField = (id, valid) => {
        const input = byId(id);
        const error = byId(`err_${id}`);
        if (!input || !error) return;

        validation[id] = valid;
        input.classList.toggle('border-emerald-500', valid);
        input.classList.toggle('border-rose-500', !valid && input.value.trim() !== '');
        error.classList.toggle('hidden', valid || input.value.trim() === '');
        checkForm();
    };

    const validatePassword = () => {
        const password = byId('emp_password');
        const confirmation = byId('emp_password_confirmation');
        setSimpleField('emp_password', password.value.length >= 12);
        setSimpleField('emp_password_confirmation', confirmation.value.length > 0 && confirmation.value === password.value);
    };

    const updateConfirm = () => {
        const enabled = employees.length > 0;
        confirm.classList.remove(...(enabled ? DISABLED_BUTTON_CLASSES : ACTIVE_BUTTON_CLASSES), 'pointer-events-none');
        confirm.classList.add(...(enabled ? ACTIVE_BUTTON_CLASSES : DISABLED_BUTTON_CLASSES));
        if (!enabled) confirm.classList.add('pointer-events-none');
        confirm.setAttribute('aria-disabled', String(!enabled));
    };

    const closeEmployeeModal = () => modal.classList.add('opacity-0', 'pointer-events-none');
    const closeDeleteModal = () => deleteModal.classList.add('opacity-0', 'pointer-events-none');

    const openEmployeeModal = (index = null) => {
        modal.classList.remove('opacity-0', 'pointer-events-none');
        const editing = index !== null && employees[index];
        byId('modalTitle').textContent = editing ? 'Editar empleado' : 'Registrar nuevo empleado';
        form.reset();
        byId('emp_index').value = editing ? String(index) : '';

        if (editing) {
            const employee = employees[index];
            byId('emp_nombre').value = employee.nombre;
            byId('emp_apellido').value = employee.apellido;
            byId('emp_correo').value = employee.correo;
            byId('emp_ext').value = employee.ext;
            byId('emp_telefono').value = employee.telefono;
            byId('emp_rol').value = employee.rol;
            byId('emp_password').value = employee.password;
            byId('emp_password_confirmation').value = employee.password;
            Object.keys(validation).forEach((key) => { validation[key] = true; });
            form.querySelectorAll('input, select').forEach((control) => control.classList.add('border-emerald-500'));
        } else {
            Object.keys(validation).forEach((key) => { validation[key] = false; });
            form.querySelectorAll('[id^="err_"]').forEach((error) => error.classList.add('hidden'));
            form.querySelectorAll('input, select').forEach((control) => control.classList.remove('border-rose-500', 'border-emerald-500'));
        }
        checkForm();
    };

    const renderEmployees = () => {
        list.replaceChildren();

        employees.forEach((employee, index) => {
            const card = document.createElement('div');
            card.id = `emp-card-${index}`;
            card.className = 'flex items-center justify-between p-4 bg-emerald-50 border border-emerald-200 rounded-2xl shadow-sm animate-fade-in';

            const summary = document.createElement('div');
            summary.className = 'flex items-center gap-3.5';
            const marker = document.createElement('div');
            marker.className = 'w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 font-bold';
            marker.textContent = '✓';
            const text = document.createElement('div');
            text.className = 'flex flex-col';
            const name = document.createElement('span');
            name.className = 'text-xs font-bold text-slate-900';
            name.textContent = `${employee.nombre} ${employee.apellido}`;
            const role = document.createElement('span');
            role.className = 'text-[11px] text-emerald-700';
            role.textContent = `${employee.rol} — ${employee.ext} ${employee.telefono}`;
            text.append(name, role);
            summary.append(marker, text);

            const actions = document.createElement('div');
            actions.className = 'flex items-center gap-2';
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.dataset.editEmployee = String(index);
            edit.className = 'w-8 h-8 rounded-full bg-white hover:bg-emerald-100 text-emerald-700 flex items-center justify-center shadow-sm transition-all';
            edit.title = 'Editar empleado';
            edit.textContent = '✎';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.deleteEmployee = String(index);
            remove.className = 'w-8 h-8 rounded-full bg-white hover:bg-rose-100 text-rose-600 flex items-center justify-center shadow-sm transition-all';
            remove.title = 'Eliminar empleado';
            remove.textContent = '✕';
            actions.append(edit, remove);
            card.append(summary, actions);
            list.appendChild(card);
        });
    };

    document.querySelector('[data-employee-open]')?.addEventListener('click', () => openEmployeeModal());
    document.querySelectorAll('[data-employee-close]').forEach((button) => button.addEventListener('click', closeEmployeeModal));
    document.querySelectorAll('[data-delete-close]').forEach((button) => button.addEventListener('click', closeDeleteModal));
    document.querySelector('[data-delete-confirm]')?.addEventListener('click', () => {
        const index = Number.parseInt(byId('delete_emp_index').value, 10);
        closeDeleteModal();
        const card = byId(`emp-card-${index}`);
        if (!card || !Number.isInteger(index)) return;
        card.classList.add('animate-fade-out');
        window.setTimeout(() => {
            employees.splice(index, 1);
            renderEmployees();
            updateConfirm();
        }, 300);
    });

    list.addEventListener('click', (event) => {
        const edit = event.target.closest('[data-edit-employee]');
        const remove = event.target.closest('[data-delete-employee]');
        if (edit) openEmployeeModal(Number.parseInt(edit.dataset.editEmployee, 10));
        if (remove) {
            byId('delete_emp_index').value = remove.dataset.deleteEmployee;
            deleteModal.classList.remove('opacity-0', 'pointer-events-none');
        }
    });

    byId('emp_nombre')?.addEventListener('input', () => setSimpleField('emp_nombre', /^[a-zA-ZÀ-ÿ\s]{2,}$/.test(byId('emp_nombre').value.trim())));
    byId('emp_apellido')?.addEventListener('input', () => setSimpleField('emp_apellido', /^[a-zA-ZÀ-ÿ\s]{2,}$/.test(byId('emp_apellido').value.trim())));
    byId('emp_correo')?.addEventListener('input', () => setSimpleField('emp_correo', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(byId('emp_correo').value.trim())));
    byId('emp_telefono')?.addEventListener('input', (event) => {
        event.currentTarget.value = event.currentTarget.value.replace(/\D/g, '');
        setSimpleField('emp_telefono', event.currentTarget.value.length >= 7);
    });
    byId('emp_rol')?.addEventListener('change', (event) => setSimpleField('emp_rol', event.currentTarget.value !== ''));
    byId('emp_password')?.addEventListener('input', validatePassword);
    byId('emp_password_confirmation')?.addEventListener('input', validatePassword);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!Object.values(validation).every(Boolean)) return;

        const index = byId('emp_index').value;
        const employee = {
            nombre: byId('emp_nombre').value,
            apellido: byId('emp_apellido').value,
            correo: byId('emp_correo').value,
            ext: byId('emp_ext').value,
            telefono: byId('emp_telefono').value,
            rol: byId('emp_rol').value,
            password: byId('emp_password').value,
        };
        if (index === '') employees.push(employee);
        else employees[Number.parseInt(index, 10)] = employee;

        renderEmployees();
        updateConfirm();
        closeEmployeeModal();
    });

    confirm.addEventListener('click', (event) => {
        if (!employees.length) event.preventDefault();
    });
    modal.addEventListener('click', (event) => { if (event.target === modal) closeEmployeeModal(); });
    deleteModal.addEventListener('click', (event) => { if (event.target === deleteModal) closeDeleteModal(); });
    updateConfirm();
}

const PLANS = Object.freeze({
    'Plan inicial': {
        desc: 'Pulperías pequeñas o en etapa de digitalización',
        monthly: { price: 'C$ 1,160.00', usd: '$32 USD por mes', badge: 'C$ 13,920.00 facturado anualmente' },
        annual: { price: 'C$ 928.00', usd: '$26 USD por mes', badge: 'C$ 11,136.00 facturado anualmente (Ahorras 20%)' },
        features: ['1 Caja / POS activo', '1 Sucursal', 'POS de cobro en vivo', 'Catálogo e Inventario completo', 'Cierre de caja con Arqueo Ciego', 'Devoluciones y Mermas'],
    },
    'Plan Comercio': {
        desc: 'Minisúper, pulperías grandes y comercios consolidados',
        monthly: { price: 'C$ 2,280.00', usd: '$62 USD por mes', badge: 'C$ 27,360.00 facturado anualmente' },
        annual: { price: 'C$ 1,824.00', usd: '$50 USD por mes', badge: 'C$ 21,888.00 facturado anualmente (Ahorras 20%)' },
        features: ['Hasta 3 Cajas simultáneas', '1 Sucursal', 'Todo lo del Plan Inicial', 'Cuentas por Cobrar (Fiados)', 'Verificación 3-Way Match', 'Centro de Alertas y Anomalías', 'Mapa de Proveedores integrado'],
    },
    'Plan Cadena': {
        desc: 'Comerciantes con múltiples puntos de venta o bodega central',
        monthly: { price: 'C$ 4,400.00', usd: '$120 USD por mes', badge: 'C$ 52,800.00 facturado anualmente' },
        annual: { price: 'C$ 3,520.00', usd: '$96 USD por mes', badge: 'C$ 42,240.00 facturado anualmente (Ahorras 20%)' },
        features: ['Cajas ilimitadas', 'Hasta 5 Sucursales conectadas', 'Todo lo del Plan Comercio', 'Reportes y Analítica avanzada', 'Gestión de Personal y Roles', 'Transferencia entre bodegas', 'Asesor dedicado'],
    },
});

function initStepSix() {
    const form = byId('checkoutForm');
    const modal = byId('checkoutModal');
    if (!form || !modal) return;

    let billingCycle = 'monthly';
    let paymentMethod = 'tarjeta';
    const fields = {
        names: byId('checkoutNames'),
        business: byId('checkoutBusinessName'),
        email: byId('checkoutEmail'),
        cardNumber: byId('checkoutCardNumber'),
        cardName: byId('checkoutCardName'),
        cardExpiry: byId('checkoutCardExpiry'),
        cardCvc: byId('checkoutCardCvc'),
        transferReference: byId('checkoutTransferReference'),
    };

    const fieldValid = {
        names: () => fields.names.value.trim().length >= 3,
        business: () => fields.business.value.trim().length >= 2,
        email: () => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email.value),
        cardNumber: () => fields.cardNumber.value.replace(/\s/g, '').length >= 15,
        cardName: () => fields.cardName.value.trim().length >= 3,
        cardExpiry: () => fields.cardExpiry.value.length === 5,
        cardCvc: () => fields.cardCvc.value.length >= 3,
        transferReference: () => fields.transferReference.value.trim().length >= 3,
    };

    const applyFieldState = (field, valid) => {
        if (!field) return;
        const empty = field.value.length === 0;
        field.classList.toggle('border-slate-200', empty);
        field.classList.toggle('border-emerald-500', !empty && valid);
        field.classList.toggle('border-rose-500', !empty && !valid);
    };

    const updateCheckout = () => {
        Object.entries(fields).forEach(([key, field]) => applyFieldState(field, fieldValid[key]()));

        const commonValid = fieldValid.names() && fieldValid.business() && fieldValid.email();
        const paymentValid = paymentMethod === 'tarjeta'
            ? fieldValid.cardNumber() && fieldValid.cardName() && fieldValid.cardExpiry() && fieldValid.cardCvc()
            : fieldValid.transferReference();
        const valid = commonValid && paymentValid;
        const submit = byId('checkoutSubmit');
        submit.disabled = !valid;
        submit.className = `w-full h-10 font-bold text-xs tracking-wide rounded-xl transition-all duration-200 ${valid
            ? 'bg-[#146F8A] hover:bg-[#10596e] text-white shadow-lg shadow-[#146F8A]/20 cursor-pointer active:scale-95 hover:scale-[1.01]'
            : 'bg-slate-300 text-slate-500 cursor-not-allowed opacity-75 shadow-none'}`;

        const messages = {
            checkoutNamesMessage: fields.names.value.length === 0 ? 'ℹ Solo texto alfabético (Mínimo 3 caracteres)' : fieldValid.names() ? '✓ Nombre válido' : '✕ Mínimo 3 caracteres requeridos',
            checkoutBusinessMessage: fields.business.value.length === 0 ? 'ℹ Solo texto alfabético' : fieldValid.business() ? '✓ Razón social válida' : '✕ Ingrese una razón social válida',
            checkoutEmailMessage: fields.email.value.length === 0 ? 'ℹ Formato requerido (ej: usuario@dominio.com)' : fieldValid.email() ? '✓ Correo electrónico válido' : '✕ Formato de correo electrónico incorrecto',
        };
        Object.entries(messages).forEach(([id, text]) => {
            const message = byId(id);
            const input = id === 'checkoutNamesMessage' ? fields.names : id === 'checkoutBusinessMessage' ? fields.business : fields.email;
            const validMessage = input.value.length > 0 && (input === fields.names ? fieldValid.names() : input === fields.business ? fieldValid.business() : fieldValid.email());
            message.textContent = text;
            message.className = `text-[9px] ${input.value.length === 0 ? 'text-slate-400' : validMessage ? 'text-emerald-600 font-semibold' : 'text-rose-500 font-semibold'}`;
        });
    };

    const setPaymentMethod = (method) => {
        paymentMethod = method;
        byId('cardPaymentFields').hidden = method !== 'tarjeta';
        byId('transferPaymentFields').hidden = method !== 'transferencia';
        document.querySelectorAll('[data-payment-method]').forEach((button) => {
            const active = button.dataset.paymentMethod === method;
            button.classList.toggle('bg-[#146F8A]', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('shadow-sm', active);
            button.classList.toggle('text-slate-600', !active);
        });
        updateCheckout();
    };

    const setBillingCycle = (cycle) => {
        billingCycle = cycle;
        document.querySelectorAll('[data-billing-cycle]').forEach((button) => {
            const active = button.dataset.billingCycle === cycle;
            button.classList.toggle('bg-[#146F8A]', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('shadow-sm', active);
            button.classList.toggle('text-slate-600', !active);
        });

        const ids = { 'Plan inicial': 'inicial', 'Plan Comercio': 'comercio', 'Plan Cadena': 'cadena' };
        Object.entries(PLANS).forEach(([name, plan]) => {
            const data = plan[cycle];
            byId(`price-${ids[name]}`).textContent = data.price;
            byId(`subtext-${ids[name]}`).textContent = data.usd;
            byId(`badge-${ids[name]}`).textContent = data.badge;
        });
    };

    const closeModal = () => modal.classList.add('opacity-0', 'pointer-events-none');
    const openModal = (planName) => {
        const plan = PLANS[planName];
        if (!plan) return;

        const price = plan[billingCycle];
        byId('modalPlanTitle').textContent = planName;
        byId('modalPlanDesc').textContent = plan.desc;
        byId('modalPlanPrice').textContent = price.price;
        byId('modalPlanUSD').textContent = price.usd;
        byId('modalPlanBadge').textContent = price.badge;

        const features = byId('modalPlanFeatures');
        features.replaceChildren(...plan.features.map((feature) => {
            const item = document.createElement('li');
            item.className = 'flex items-center gap-2 hover:translate-x-1';
            const marker = document.createElement('span');
            marker.className = 'text-emerald-600 font-bold';
            marker.textContent = '✓';
            item.append(marker, document.createTextNode(` ${feature}`));
            return item;
        }));
        modal.classList.remove('opacity-0', 'pointer-events-none');
    };

    document.querySelectorAll('[data-billing-cycle]').forEach((button) => button.addEventListener('click', () => setBillingCycle(button.dataset.billingCycle)));
    document.querySelectorAll('[data-plan]').forEach((button) => button.addEventListener('click', () => openModal(button.dataset.plan)));
    document.querySelectorAll('[data-checkout-close]').forEach((button) => button.addEventListener('click', closeModal));
    document.querySelectorAll('[data-payment-method]').forEach((button) => button.addEventListener('click', () => setPaymentMethod(button.dataset.paymentMethod)));

    [fields.names, fields.business, fields.cardName].forEach((field) => field.addEventListener('input', () => {
        field.value = field.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
        updateCheckout();
    }));
    fields.email.addEventListener('input', updateCheckout);
    fields.cardNumber.addEventListener('input', () => {
        const digits = fields.cardNumber.value.replace(/\D/g, '').slice(0, 16);
        fields.cardNumber.value = digits.match(/.{1,4}/g)?.join(' ') ?? digits;
        updateCheckout();
    });
    fields.cardExpiry.addEventListener('input', () => {
        const digits = fields.cardExpiry.value.replace(/\D/g, '').slice(0, 4);
        fields.cardExpiry.value = digits.length >= 3 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
        updateCheckout();
    });
    fields.cardCvc.addEventListener('input', () => {
        fields.cardCvc.value = fields.cardCvc.value.replace(/\D/g, '').slice(0, 4);
        updateCheckout();
    });
    fields.transferReference.addEventListener('input', updateCheckout);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (byId('checkoutSubmit').disabled) return;
        const destination = form.dataset.successUrl;
        if (destination) window.location.assign(destination);
    });
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });

    setBillingCycle('monthly');
    setPaymentMethod('tarjeta');
}

const step = document.body?.dataset.registrationStep;

({
    1: initStepOne,
    2: initStepTwo,
    3: initStepThree,
    4: initStepFour,
    5: initStepFive,
    6: initStepSix,
}[step])?.();
