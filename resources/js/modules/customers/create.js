import { api, ApiError } from '@/core/api-client';
import { setButtonLoading } from '@/core/loading';
import { notify } from '@/core/notifications';

function renderValidationErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.elements.namedItem(field);

        if (!(input instanceof HTMLElement)) return;

        input.classList.add('border-red-500');
        const errorElement = input.closest('.form-group')?.querySelector('.error-field');

        if (errorElement) {
            errorElement.textContent = messages[0] ?? '';
            errorElement.classList.remove('hidden');
        }
    });
}

function clearValidationErrors(form) {
    form.querySelectorAll('input, select, textarea').forEach((input) => {
        input.classList.remove('border-red-500');
    });
    form.querySelectorAll('.error-field').forEach((errorElement) => {
        errorElement.classList.add('hidden');
        errorElement.textContent = '';
    });
}

function payload(form) {
    const data = Object.fromEntries(new FormData(form).entries());

    if (!data.document_number?.trim()) {
        data.document_number = null;
    }

    return data;
}

async function submitCustomer(form) {
    if (form.dataset.submitting === 'true') return;

    const endpoint = form.dataset.url;
    const submit = form.querySelector('button[type="submit"]');

    if (!endpoint) {
        notify({ type: 'error', message: 'Endpoint de clientes no configurado.' });
        return;
    }

    form.dataset.submitting = 'true';
    clearValidationErrors(form);
    setButtonLoading(submit, true, { label: 'Guardando...' });

    try {
        await api.post(endpoint, payload(form));

        const destination = form.querySelector('a[href]')?.href;
        if (destination) window.location.assign(destination);
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            renderValidationErrors(form, error.errors);
            notify({ type: 'warning', message: error.message });
            return;
        }

        if (!(error instanceof ApiError)) {
            notify({ type: 'error', message: 'Ocurrió un error inesperado al procesar la solicitud.' });
        }
    } finally {
        setButtonLoading(submit, false);
        form.dataset.submitting = 'false';
    }
}

function init() {
    const form = document.querySelector('#formStoreCustomer');
    if (!form || form.dataset.initialized === 'true') return;

    form.dataset.initialized = 'true';
    form.querySelector('input[name="credit_limit"]')?.addEventListener('blur', (event) => {
        let value = Number.parseFloat(event.currentTarget.value);
        if (!Number.isFinite(value) || value < 0) value = 0;
        event.currentTarget.value = value.toFixed(2);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void submitCustomer(form);
    });
}

init();
