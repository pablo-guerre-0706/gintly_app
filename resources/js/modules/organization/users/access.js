import { api, ApiError } from '@/core/api-client';
import { notify } from '@/core/notifications';
import { getSessionContext } from '@/core/session-context';
import {
    clearFieldErrors,
    fetchActiveBranches,
    grantableRoles,
    mutate,
    responseMessage,
    ROLE_LABELS,
    setButtonBusy,
    showFieldErrors,
} from '../shared';

const state = {
    context: null,
    user: null,
    branches: [],
    catalog: [],
    savingRole: false,
    savingProfiles: false,
};

function endpoint(template, userId) {
    return template.replace('__USER__', String(userId));
}

function option(value, label) {
    const node = document.createElement('option');
    node.value = String(value);
    node.textContent = label;
    return node;
}

function initials(name) {
    return String(name ?? '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'U';
}

function renderProfileInputs(container, selected, prefix) {
    container.replaceChildren();

    state.catalog.forEach((profile, index) => {
        const label = document.createElement('label');
        label.className = 'flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-gintly-brand/40';

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = 'profiles';
        input.value = profile.value;
        input.checked = selected.includes(profile.value);
        input.id = `${prefix}-${index}`;
        input.className = 'mt-1 size-4 rounded border-slate-300 text-gintly-brand focus:ring-gintly-brand';

        const text = document.createElement('span');
        const title = document.createElement('span');
        title.className = 'block text-sm font-bold text-gintly-text-primary';
        title.textContent = profile.label;
        const detail = document.createElement('span');
        detail.className = 'mt-1 block text-xs text-gintly-text-secondary';
        detail.textContent = `${Array.isArray(profile.capabilities) ? profile.capabilities.length : 0} capacidades`;
        text.append(title, detail);
        label.append(input, text);
        container.append(label);
    });
}

function selectedProfiles(form) {
    return Array.from(form.querySelectorAll('input[name="profiles"]:checked')).map((input) => input.value);
}

function branchLabel(id) {
    if (!id) return 'Sin sucursal';
    const branch = state.branches.find((item) => Number(item.id) === Number(id));
    return branch?.name ?? `Sucursal #${id}`;
}

function renderSummary(root) {
    root.querySelector('[data-user-initials]').textContent = initials(state.user.name);
    root.querySelector('[data-user-name]').textContent = state.user.name || 'Usuario';
    root.querySelector('[data-user-email]').textContent = state.user.email || 'Sin correo visible';
    root.querySelector('[data-user-role-label]').textContent = ROLE_LABELS[state.user.role] ?? state.user.role ?? 'Sin rol';
    root.querySelector('[data-user-branch-label]').textContent = branchLabel(state.user.branch_id);
    root.querySelector('[data-user-status]').textContent = state.user.is_active ? 'Activo' : 'Inactivo';
}

function toggleRoleOperatorFields(root) {
    const form = root.querySelector('[data-role-form]');
    const isOperator = form.elements.role.value === 'ROL-03';
    root.querySelector('[data-role-operator-fields]').hidden = !isOperator;
    root.querySelector('[data-role-profile-fields]').hidden = !isOperator;
    form.elements.branch_id.required = isOperator;
    form.elements.branch_id.disabled = !isOperator || state.branches.length === 0 || form.dataset.locked === 'true';
    const empty = root.querySelector('[data-branch-empty]');
    empty.hidden = !isOperator || state.branches.length > 0;
    root.querySelector('[data-branch-create-link]').hidden = empty.hidden || !state.context.capabilities.includes('sucursales.gestionar');
    root.querySelector('[data-role-submit]').disabled = state.savingRole || form.dataset.locked === 'true' || (isOperator && state.branches.length === 0);
}

function syncForms(root) {
    const roleForm = root.querySelector('[data-role-form]');
    const profileForm = root.querySelector('[data-profiles-form]');
    const grantable = grantableRoles(state.context.role);
    const canManageRank = grantable.some((role) => role.value === state.user.role);
    const isSelf = Number(state.context.identity.id) === Number(state.user.id);

    const roleOptions = grantable.map((role) => option(role.value, `${role.label} (${role.value})`));
    if (!canManageRank && state.user.role) {
        const current = option(state.user.role, `${ROLE_LABELS[state.user.role] ?? state.user.role} — rango no administrable`);
        current.disabled = true;
        roleOptions.unshift(current);
    }
    roleForm.elements.role.replaceChildren(...roleOptions);
    roleForm.elements.role.value = state.user.role;

    roleForm.elements.branch_id.replaceChildren(option('', state.branches.length ? 'Selecciona una sucursal' : 'No existen sucursales activas'));
    state.branches.forEach((branch) => roleForm.elements.branch_id.append(option(branch.id, branch.name)));
    roleForm.elements.branch_id.value = state.user.branch_id ? String(state.user.branch_id) : '';

    renderProfileInputs(root.querySelector('[data-role-profile-options]'), state.user.profiles ?? [], 'role-profile');
    renderProfileInputs(root.querySelector('[data-profile-options]'), state.user.profiles ?? [], 'assigned-profile');

    const roleLocked = isSelf || !canManageRank;
    roleForm.dataset.locked = String(roleLocked);
    roleForm.querySelectorAll('select, input, button[type="submit"]').forEach((control) => {
        control.disabled = roleLocked;
    });
    root.querySelector('[data-self-notice]').hidden = !roleLocked;
    root.querySelector('[data-self-notice]').textContent = isSelf
        ? 'No puedes modificar tu propio rol desde esta vía.'
        : 'No puedes gestionar un usuario con autoridad superior a la tuya.';

    profileForm.hidden = state.user.role !== 'ROL-03' || roleLocked;
    toggleRoleOperatorFields(root);
    renderSummary(root);
}

function showFatal(root, message) {
    root.querySelector('[data-access-loading]').hidden = true;
    root.querySelector('[data-access-content]').hidden = true;
    root.querySelector('[data-access-fatal]').hidden = false;
    root.querySelector('[data-access-fatal-message]').textContent = message;
}

async function load(root, refresh = false) {
    root.querySelector('[data-access-loading]').hidden = false;
    root.querySelector('[data-access-content]').hidden = true;
    root.querySelector('[data-access-fatal]').hidden = true;

    try {
        state.context = await getSessionContext({ refresh });
        if (!state.context.capabilities.includes('usuarios.gestionar')) {
            showFatal(root, 'No tienes autorización para administrar accesos de usuarios.');
            return;
        }

        const userId = Number(root.dataset.userId);
        if (!Number.isInteger(userId) || userId < 1) throw new Error('Identificador de usuario no válido.');

        const [userResponse, branches, profilesResponse] = await Promise.all([
            api.get(endpoint(root.dataset.userEndpointTemplate, userId), {}, { dispatchErrors: false }),
            fetchActiveBranches(root.dataset.branchesEndpoint),
            api.get(root.dataset.profilesEndpoint, {}, { dispatchErrors: false }),
        ]);

        if (!userResponse?.data?.id || !userResponse.data.role) throw new Error('El servidor no devolvió el rol del usuario.');

        state.user = userResponse.data;
        state.branches = branches;
        state.catalog = Array.isArray(profilesResponse?.data) ? profilesResponse.data : [];

        syncForms(root);
        root.querySelector('[data-access-loading]').hidden = true;
        root.querySelector('[data-access-content]').hidden = false;
    } catch (error) {
        showFatal(root, responseMessage(error, 'No fue posible cargar el usuario, las sucursales o los perfiles.'));
    }
}

function validateProfiles(form) {
    const values = selectedProfiles(form);
    const error = form.querySelector('[data-error-for="profiles"]');

    if (values.length > 0) return values;

    error.textContent = 'Selecciona al menos un perfil operativo.';
    error.hidden = false;
    form.querySelector('input[name="profiles"]')?.focus();
    return null;
}

async function saveRole(root, form) {
    if (state.savingRole) return;
    clearFieldErrors(form);
    const errorBox = root.querySelector('[data-role-error]');
    errorBox.hidden = true;

    if (form.elements.role.value === 'ROL-03' && state.branches.length === 0) {
        root.querySelector('[data-branch-empty]').hidden = false;
        root.querySelector('[data-branch-create-link]').focus();
        return;
    }
    if (!form.reportValidity()) return;

    const payload = { role: form.elements.role.value };
    if (payload.role === 'ROL-03') {
        const profiles = validateProfiles(form);
        if (!profiles) return;
        payload.branch_id = Number(form.elements.branch_id.value);
        payload.profiles = profiles;
    }

    const button = root.querySelector('[data-role-submit]');
    state.savingRole = true;
    setButtonBusy(button, true, 'Guardando rol…');

    try {
        const response = await mutate('put', endpoint(root.dataset.userRoleEndpointTemplate, state.user.id), payload);
        state.user = response?.data ?? state.user;
        syncForms(root);
        notify({ type: 'success', title: 'Acceso actualizado', message: 'El rol y sus requisitos asociados fueron guardados.' });
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) showFieldErrors(form, error.errors);
        errorBox.textContent = responseMessage(error, 'No fue posible actualizar el rol.');
        errorBox.hidden = false;
    } finally {
        state.savingRole = false;
        setButtonBusy(button, false);
        toggleRoleOperatorFields(root);
    }
}

async function saveProfiles(root, form) {
    if (state.savingProfiles) return;
    clearFieldErrors(form);
    const errorBox = root.querySelector('[data-profiles-error]');
    errorBox.hidden = true;
    const profiles = validateProfiles(form);
    if (!profiles) return;

    const button = root.querySelector('[data-profiles-submit]');
    state.savingProfiles = true;
    setButtonBusy(button, true, 'Guardando perfiles…');

    try {
        const response = await mutate('put', endpoint(root.dataset.userProfilesEndpointTemplate, state.user.id), { profiles });
        state.user = response?.data ?? state.user;
        syncForms(root);
        notify({ type: 'success', title: 'Perfiles actualizados', message: 'Los perfiles operativos fueron reemplazados correctamente.' });
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) showFieldErrors(form, error.errors);
        errorBox.textContent = responseMessage(error, 'No fue posible actualizar los perfiles.');
        errorBox.hidden = false;
    } finally {
        state.savingProfiles = false;
        setButtonBusy(button, false);
    }
}

export default function init() {
    const root = document.querySelector('[data-user-access-root]');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    const roleForm = root.querySelector('[data-role-form]');
    const profilesForm = root.querySelector('[data-profiles-form]');

    roleForm.addEventListener('change', (event) => {
        if (event.target.name === 'role') toggleRoleOperatorFields(root);
    });
    roleForm.addEventListener('submit', (event) => {
        event.preventDefault();
        void saveRole(root, roleForm);
    });
    profilesForm.addEventListener('submit', (event) => {
        event.preventDefault();
        void saveProfiles(root, profilesForm);
    });
    root.querySelector('[data-access-retry]').addEventListener('click', () => void load(root, true));

    void load(root);
}
