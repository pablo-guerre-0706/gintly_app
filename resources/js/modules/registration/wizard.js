import { api, initializeCsrf } from '../../core/api-client.js';
import { setButtonLoading } from '../../core/loading.js';
import { FIELDS, registrationPayload, validateRegistration, firstErrorField } from './contract.js';
import { createRegistrationAttempt } from './attempt.js';

export default function initRegistration() {
    const root = document.querySelector('[data-registration]');
    if (!root || root.dataset.initialized === 'true') return;
    const form = root.querySelector('[data-register-form]');
    const submit = root.querySelector('[data-register-submit]');
    const back = root.querySelector('[data-register-back]');
    const feedback = root.querySelector('[data-register-feedback]');
    const status = root.querySelector('[data-register-status]');
    const timezone = form?.elements.namedItem('business.timezone');
    if (!form || !submit || !back || !feedback || !status || !timezone) return;
    root.dataset.initialized = 'true';
    form.hidden = false;
    const machine = createRegistrationAttempt({ post: api.post, csrf: () => initializeCsrf({ dispatchErrors: false }) });
    const timezones = [...timezone.options].map((option) => option.value);
    let step = 1;
    let dirty = false;
    let result = null;
    let waitTimer = null;
    let displayedErrors = {};
    let feedbackMessage = '';

    function values() {
        return registrationPayload(Object.fromEntries(new FormData(form)));
    }

    function showStep(next, focus = true) {
        step = next;
        root.querySelectorAll('[data-register-stage]').forEach((element) => { element.hidden = Number(element.dataset.registerStage) !== step; });
        root.querySelectorAll('[data-register-progress]').forEach((element) => {
            const active = Number(element.dataset.registerProgress) === step;
            element.classList.toggle('registration-progress-active', active);
            if (active) element.setAttribute('aria-current', 'step');
            else element.removeAttribute('aria-current');
        });
        back.classList.toggle('hidden', step === 1 || step === 4);
        submit.textContent = step === 3 ? 'Crear negocio' : 'Continuar';
        if (focus) root.querySelector('[data-register-stage="' + step + '"] [data-register-heading]')?.focus();
    }

    function clearErrors() {
        displayedErrors = {};
        feedbackMessage = '';
        feedback.classList.add('hidden');
        root.querySelector('[data-register-summary]').replaceChildren();
        root.querySelectorAll('[data-register-error]').forEach((element) => { element.textContent = ''; element.classList.add('hidden'); });
        for (const path of Object.keys(FIELDS)) form.elements.namedItem(path)?.removeAttribute('aria-invalid');
    }

    function showErrors(errors, message, focus = true) {
        clearErrors();
        displayedErrors = errors;
        feedbackMessage = message;
        root.querySelector('[data-register-message]').textContent = message;
        feedback.classList.remove('hidden');
        const summary = root.querySelector('[data-register-summary]');
        for (const [path, messages] of Object.entries(errors)) {
            if (!Array.isArray(messages)) continue;
            for (const text of messages) {
                if (typeof text !== 'string') continue;
                const item = document.createElement('li');
                const definition = FIELDS[path];
                if (definition) {
                    const link = document.createElement('button');
                    link.type = 'button';
                    link.className = 'registration-link min-h-11 text-left underline';
                    link.textContent = definition.label + ': ' + text;
                    link.addEventListener('click', () => { showStep(definition.step, false); form.elements.namedItem(path)?.focus(); });
                    item.append(link);
                    const field = form.elements.namedItem(path);
                    field?.setAttribute('aria-invalid', 'true');
                    const hint = root.querySelector('[data-register-error="' + path + '"]');
                    if (hint) { hint.textContent = messages.filter((entry) => typeof entry === 'string').join(' '); hint.classList.remove('hidden'); }
                } else item.textContent = text;
                summary.append(item);
            }
        }
        if (focus) {
            const first = firstErrorField(errors);
            if (first) { showStep(FIELDS[first].step, false); form.elements.namedItem(first)?.focus(); }
            else feedback.focus();
        }
    }

    function refreshEditedField(event) {
        const path = event.target.name;
        if (machine.state !== 'editing' || !FIELDS[path]) return;
        dirty = true;
        const current = validateRegistration(values(), timezones);
        const updated = { ...displayedErrors };
        const affected = new Set([path]);
        // Changing either password recomputes the match. A server-only password
        // rejection is retained until that password itself is edited or resubmitted.
        if (path === 'owner.password') affected.add('owner.password_confirmation');
        if (['owner.first_name', 'owner.last_name'].includes(path)) {
            affected.add('owner.first_name'); affected.add('owner.last_name');
        }
        for (const fieldPath of affected) {
            const confirmation = fieldPath === 'owner.password_confirmation'
                && form.elements.namedItem(fieldPath).value !== '';
            if (current[fieldPath] && (updated[fieldPath] || confirmation)) updated[fieldPath] = current[fieldPath];
            else delete updated[fieldPath];
        }
        if (Object.keys(updated).length) showErrors(updated, feedbackMessage || 'Revisa los campos antes de continuar.', false);
        else clearErrors();
    }

    function review(payload) {
        const visible = { 'owner.name': payload.owner.first_name + ' ' + payload.owner.last_name, 'owner.email': payload.owner.email,
            'business.name': payload.business.name, 'business.timezone': payload.business.timezone };
        root.querySelectorAll('[data-register-review]').forEach((element) => { element.textContent = visible[element.dataset.registerReview] ?? ''; });
    }

    function controls() {
        const locked = machine.state !== 'editing';
        for (const path of Object.keys(FIELDS)) form.elements.namedItem(path).disabled = locked;
        root.querySelectorAll('[data-password-toggle]').forEach((button) => { button.disabled = locked; });
        back.disabled = locked;
        const busy = machine.state === 'submitting';
        form.setAttribute('aria-busy', String(busy));
        setButtonLoading(submit, busy, { label: 'Creando negocio…' });
        submit.disabled = ['success', 'forbidden', 'conflict'].includes(machine.state) || busy || Date.now() < machine.retryAt;
        if (!busy) submit.textContent = locked ? 'Reintentar el mismo registro' : step === 3 ? 'Crear negocio' : 'Continuar';
        root.querySelector('[data-register-recovery]').classList.toggle('hidden', !['uncertain', 'recoverable', 'conflict'].includes(machine.state));
        root.querySelector('[data-register-dashboard]').hidden = machine.state !== 'forbidden';
        clearTimeout(waitTimer);
        const wait = root.querySelector('[data-register-wait]');
        if (Date.now() < machine.retryAt) {
            wait.textContent = 'Puedes reintentar en ' + Math.ceil((machine.retryAt - Date.now()) / 1000) + ' segundos.';
            waitTimer = setTimeout(controls, 1000);
        } else wait.textContent = '';
    }

    function erasePasswords() {
        for (const path of ['owner.password', 'owner.password_confirmation']) {
            const field = form.elements.namedItem(path);
            field.value = '';
            field.type = 'password';
        }
    }

    form.addEventListener('input', refreshEditedField);
    form.addEventListener('change', refreshEditedField);
    back.addEventListener('click', () => { if (machine.state === 'editing') { clearErrors(); showStep(step - 1); } });
    root.querySelectorAll('[data-password-toggle]').forEach((button) => button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.textContent = show ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-label', (show ? 'Ocultar' : 'Mostrar') + ' ' + FIELDS[input.name].label.toLowerCase());
    }));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (machine.state === 'submitting' || submit.disabled || step === 4) return;
        clearErrors();
        let pending;
        if (machine.state === 'editing') {
            // La referencia temporal se descarta antes del await. Solo el intento retiene
            // el snapshot necesario; desaparece tras éxito o validación 422.
            let payload = values();
            const allErrors = validateRegistration(payload, timezones);
            const errors = step < 3 ? Object.fromEntries(Object.entries(allErrors).filter(([path]) => FIELDS[path].step === step)) : allErrors;
            if (Object.keys(errors).length) { showErrors(errors, 'Revisa los campos antes de continuar.'); return; }
            if (step < 3) { review(payload); showStep(step + 1); return; }
            pending = machine.submit(payload);
            payload = null;
        } else pending = machine.submit();
        controls();
        status.textContent = 'Enviando el registro. Espera la confirmación del servidor.';
        let outcome;
        try { outcome = await pending; }
        catch { controls(); status.textContent = ''; showErrors({}, 'No fue posible preparar el registro seguro. Este navegador debe permitir identificadores criptográficamente seguros.'); return; }
        controls();
        if (outcome.ignored) return;
        if (outcome.state === 'success') {
            result = outcome.result;
            erasePasswords();
            dirty = false;
            form.hidden = true;
            root.querySelector('[data-register-result]').hidden = false;
            root.querySelector('[data-register-slug]').textContent = result.business_slug;
            root.querySelector('[data-register-email]').textContent = result.owner_email;
            showStep(4, false);
            status.textContent = 'Registro confirmado. Inicia sesión cuando estés listo.';
            document.getElementById('registration-result-title').focus();
        } else {
            status.textContent = '';
            if (['forbidden', 'conflict'].includes(outcome.state)) { erasePasswords(); dirty = false; }
            showErrors(outcome.errors ?? {}, outcome.message);
        }
    });

    root.querySelector('[data-register-copy]').addEventListener('click', async () => {
        if (!result) return;
        try { await navigator.clipboard.writeText(result.business_slug); status.textContent = 'Identificador copiado.'; }
        catch { status.textContent = 'No se pudo copiar. Selecciona el identificador mostrado y cópialo manualmente.'; }
    });
    window.addEventListener('beforeunload', (event) => {
        if ((dirty || ['submitting', 'uncertain', 'recoverable', 'conflict'].includes(machine.state)) && machine.state !== 'success') {
            event.preventDefault(); event.returnValue = '';
        }
    });
    showStep(1, false);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initRegistration, { once: true });
else initRegistration();
