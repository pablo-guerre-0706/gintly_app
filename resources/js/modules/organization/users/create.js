import { api, ApiError } from '@/core/api-client';
import { notify } from '@/core/notifications';
import { getSessionContext } from '@/core/session-context';
import {
    clearFieldErrors,
    fetchActiveBranches,
    grantableRoles,
    mutate,
    responseMessage,
    setButtonBusy,
    showFieldErrors,
} from '../shared';

let submitting = false;

function option(value, label) {
    const item = document.createElement('option');
    item.value = String(value);
    item.textContent = label;
    return item;
}

function renderProfiles(container, profiles) {
    container.replaceChildren();

    profiles.forEach((profile, index) => {
        const label = document.createElement('label');
        label.className = 'flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-gintly-brand/40';

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = 'profiles';
        input.value = profile.value;
        input.className = 'mt-1 size-4 rounded border-slate-300 text-gintly-brand focus:ring-gintly-brand';
        input.id = `create-profile-${index}`;

        const text = document.createElement('span');
        const title = document.createElement('span');
        title.className = 'block text-sm font-bold text-gintly-text-primary';
        title.textContent = profile.label;
        const detail = document.createElement('span');
        detail.className = 'mt-1 block text-xs leading-5 text-gintly-text-secondary';
        detail.textContent = Array.isArray(profile.capabilities)
            ? `${profile.capabilities.length} capacidades operativas`
            : 'Perfil operativo';
        text.append(title, detail);
        label.append(input, text);
        container.append(label);
    });
}

function toggleOperatorFields(root) {
    const form = root.querySelector('[data-user-create-form]');
    const isOperator = form.elements.role.value === 'ROL-03';
    const branch = root.querySelector('[data-operator-branch]');
    const profiles = root.querySelector('[data-operator-profiles]');

    branch.hidden = !isOperator;
    profiles.hidden = !isOperator;
    form.elements.branch_id.required = isOperator;
    form.elements.branch_id.disabled = !isOperator || form.elements.branch_id.options.length < 2;
    root.querySelector('[data-branch-empty]').hidden = !isOperator || !form.elements.branch_id.disabled;
    root.querySelector('[data-branch-create-link]').hidden = !isOperator || !form.elements.branch_id.disabled;
    root.querySelector('[data-create-submit]').disabled = submitting || (isOperator && form.elements.branch_id.disabled);
    Array.from(form.elements.profiles ?? []).forEach((input) => {
        input.disabled = !isOperator;
    });
}

function profileValues(form) {
    return Array.from(form.querySelectorAll('input[name="profiles"]:checked')).map((input) => input.value);
}

function showFatal(root, message) {
    root.querySelector('[data-create-loading]').hidden = true;
    root.querySelector('[data-user-create-form]').hidden = true;
    root.querySelector('[data-create-fatal]').hidden = false;
    root.querySelector('[data-create-fatal-message]').textContent = message;
}

async function prepare(root, refresh = false) {
    root.querySelector('[data-create-loading]').hidden = false;
    root.querySelector('[data-create-fatal]').hidden = true;
    root.querySelector('[data-user-create-form]').hidden = true;

    try {
        const context = await getSessionContext({ refresh });
        if (!context.capabilities.includes('usuarios.gestionar')) {
            showFatal(root, 'No tienes autorización para crear usuarios.');
            return;
        }

        const [branches, profilesResponse] = await Promise.all([
            fetchActiveBranches(root.dataset.branchesEndpoint),
            api.get(root.dataset.profilesEndpoint, {}, { dispatchErrors: false }),
        ]);

        const form = root.querySelector('[data-user-create-form]');
        const roles = grantableRoles(context.role);
        form.elements.role.replaceChildren(option('', 'Selecciona un rol'), ...roles.map((role) => option(role.value, `${role.label} (${role.value})`)));

        form.elements.branch_id.replaceChildren(option('', branches.length ? 'Selecciona una sucursal' : 'No existen sucursales activas'), ...branches.map((branch) => option(branch.id, branch.name)));

        const profiles = Array.isArray(profilesResponse?.data) ? profilesResponse.data : [];
        renderProfiles(root.querySelector('[data-profile-options]'), profiles);

        root.querySelector('[data-create-loading]').hidden = true;
        form.hidden = false;
        form.elements.role.value = roles.some((role) => role.value === 'ROL-03') ? 'ROL-03' : (roles[0]?.value ?? '');
        toggleOperatorFields(root);
        form.elements.name.focus();
    } catch (error) {
        showFatal(root, responseMessage(error, 'No fue posible cargar sucursales y perfiles.'));
    }
}

async function submit(root, form) {
    if (submitting) return;

    clearFieldErrors(form);
    const generalError = root.querySelector('[data-create-error]');
    generalError.hidden = true;

    if (form.elements.role.value === 'ROL-03' && form.elements.branch_id.disabled) {
        root.querySelector('[data-branch-empty]').hidden = false;
        root.querySelector('[data-branch-create-link]').focus();
        return;
    }
    if (!form.reportValidity()) return;

    const profiles = profileValues(form);
    if (form.elements.role.value === 'ROL-03' && profiles.length === 0) {
        const profileError = form.querySelector('[data-error-for="profiles"]');
        profileError.textContent = 'Selecciona al menos un perfil operativo.';
        profileError.hidden = false;
        form.querySelector('input[name="profiles"]')?.focus();
        return;
    }

    const payload = {
        name: form.elements.name.value.trim(),
        email: form.elements.email.value.trim(),
        password: form.elements.password.value,
        password_confirmation: form.elements.password_confirmation.value,
        role: form.elements.role.value,
    };

    if (payload.role === 'ROL-03') {
        payload.branch_id = Number(form.elements.branch_id.value);
        payload.profiles = profiles;
    }

    const button = root.querySelector('[data-create-submit]');
    submitting = true;
    setButtonBusy(button, true, 'Creando usuario…');

    try {
        const response = await mutate('post', root.dataset.usersEndpoint, payload);
        const user = response?.data;

        if (!user?.id) throw new Error('El servidor no devolvió el usuario creado.');

        form.elements.password.value = '';
        form.elements.password_confirmation.value = '';
        notify({ type: 'success', title: 'Usuario creado', message: `${user.name} fue registrado correctamente.` });
        window.location.assign(root.dataset.accessUrlTemplate.replace('__USER__', String(user.id)));
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) showFieldErrors(form, error.errors);
        generalError.textContent = responseMessage(error, 'No fue posible crear el usuario.');
        generalError.hidden = false;
        generalError.focus?.();
    } finally {
        submitting = false;
        setButtonBusy(button, false);
        toggleOperatorFields(root);
    }
}

export default function init() {
    const root = document.querySelector('[data-user-create-root]');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    const form = root.querySelector('[data-user-create-form]');

    form.addEventListener('change', (event) => {
        if (event.target.name === 'role') toggleOperatorFields(root);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void submit(root, form);
    });
    root.querySelector('[data-create-retry]').addEventListener('click', () => void prepare(root, true));

    void prepare(root);
}
