import { api } from '@/core/api-client';
import { showFormErrors, submitFormOnce, trackUnsaved } from '@/core/form-ui';
import { money } from '@/core/money';

const CEDULA = /^\d{3}-\d{6}-\d{4}[A-Z]$/;
const DATE_PART = /^\d{3}-(\d{2})(\d{2})(\d{2})-\d{4}[A-Z]$/;

function validCedula(value) {
    if (!CEDULA.test(value)) return false;
    const [, day, month, year] = DATE_PART.exec(value) ?? [];
    if (!day) return false;
    return [1900, 2000].some((century) => {
        const date = new Date(Date.UTC(century + Number(year), Number(month) - 1, Number(day)));
        return date.getUTCDate() === Number(day) && date.getUTCMonth() === Number(month) - 1;
    });
}

function formatCedula(input) {
    const original = input.value;
    const caret = input.selectionStart ?? original.length;
    const before = original.slice(0, caret).replace(/[^a-z\d]/gi, '').length;
    const raw = original.toUpperCase().replace(/[^A-Z\d]/g, '');
    const numeric = raw.slice(0, 13).replace(/\D/g, '');
    const letter = raw.slice(13).replace(/[^A-Z]/g, '').slice(0, 1);
    const value = [numeric.slice(0, 3), numeric.slice(3, 9), numeric.slice(9, 13)]
        .filter(Boolean).join('-') + letter;
    input.value = value;
    let position = 0;
    let count = 0;
    while (position < value.length && count < before) {
        if (/[A-Z\d]/.test(value[position])) count += 1;
        position += 1;
    }
    input.setSelectionRange(position, position);
}

function customerPayload(form) {
    const data = Object.fromEntries(new FormData(form).entries());
    data.name = data.name.trim();
    data.document_number = data.document_number.trim() || null;
    data.email = data.email.trim() || null;
    data.phone_number = data.phone_number.trim() || null;
    data.birth_date = data.birth_date || null;
    data.notes = data.notes.trim() || null;
    if (!data.credit_limit.trim()) delete data.credit_limit;
    else data.credit_limit = money(data.credit_limit.trim());
    return data;
}

function validate(form) {
    const errors = {};
    const document = form.elements.document_number.value.trim();
    if (!form.elements.name.value.trim()) errors.name = ['El nombre es obligatorio.'];
    if (document && form.elements.document_type.value === 'cedula' && !validCedula(document)) {
        errors.document_number = ['La cédula debe seguir ###-######-####A y contener una fecha central válida.'];
    }
    const credit = form.elements.credit_limit.value.trim();
    if (credit && !/^\d+(?:\.\d{1,2})?$/.test(credit)) errors.credit_limit = ['Usa un importe no negativo con máximo dos decimales.'];
    if (form.elements.phone_number.value.trim() === '+505') errors.phone_number = ['Completa el número o deja el campo vacío.'];
    if (!form.checkValidity()) {
        for (const field of form.elements) {
            if (field instanceof HTMLElement && field.name && !field.validity.valid) errors[field.name] = [field.validationMessage];
        }
    }
    return errors;
}

export default function init() {
    const form = document.querySelector('[data-customer-create]');
    if (!form || form.dataset.initialized === 'true') return;
    form.dataset.initialized = 'true';
    const clearDirty = trackUnsaved(form);
    const documentField = form.elements.document_number;
    const type = form.elements.document_type;
    type.addEventListener('change', () => {
        documentField.placeholder = type.value === 'cedula' ? '001-010100-0001A' : 'Número de documento';
        documentField.inputMode = type.value === 'cedula' ? 'numeric' : 'text';
        document.getElementById('customer-document-help').textContent = type.value === 'cedula'
            ? 'La máscara de cédula ayuda a detectar errores de captura; el servidor valida unicidad y longitud.'
            : 'Ingresa el identificador como aparece en el documento; el servidor valida unicidad y longitud.';
        if (type.value === 'cedula' && documentField.value) formatCedula(documentField);
    });
    documentField.addEventListener('input', () => { if (type.value === 'cedula') formatCedula(documentField); });
    form.elements.phone_number.addEventListener('focus', (event) => {
        if (!event.target.value) event.target.value = '+505';
    });
    form.elements.phone_number.addEventListener('blur', (event) => {
        if (event.target.value.trim() === '+505') event.target.value = '';
    });
    form.elements.credit_limit.addEventListener('blur', (event) => {
        const value = event.target.value.trim();
        if (value && /^\d+(?:\.\d{1,2})?$/.test(value)) event.target.value = money(value);
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const errors = validate(form);
        if (Object.keys(errors).length) { showFormErrors(form, errors); return; }
        const button = form.querySelector('[type="submit"]');
        await submitFormOnce(form, button, () => api.post('/customers', customerPayload(form), { dispatchErrors: false, expectedStatus: 201 }), {
            onSuccess: (response) => {
                if (!Number.isInteger(response?.data?.id)) throw new TypeError('El servidor no confirmó la creación del cliente.');
                clearDirty();
                const result = document.querySelector('[data-customer-created]');
                result.querySelector('[data-customer-created-name]').textContent = response.data.name;
                form.hidden = true;
                result.hidden = false;
                result.focus();
            },
        });
    });
}
