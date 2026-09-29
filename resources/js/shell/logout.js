import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { notify } from '@/core/notifications';

let pendingLogout = null;
const buttonLabels = new WeakMap();

function setButtonsLoading(buttons, loading) {
    buttons.forEach((button) => {
        button.disabled = loading;
        button.setAttribute('aria-busy', String(loading));

        const label = button.querySelector('[data-logout-label]');
        const spinner = button.querySelector('[data-logout-spinner]');

        if (label) {
            if (!buttonLabels.has(button)) {
                buttonLabels.set(button, label.textContent);
            }

            label.textContent = loading
                ? 'Cerrando sesión…'
                : buttonLabels.get(button);
        }

        spinner?.classList.toggle('hidden', !loading);
    });
}

function errorMessage(error) {
    if (!(error instanceof ApiError)) {
        return 'No fue posible cerrar la sesión. Intente nuevamente.';
    }

    if (error.status === 0) {
        return 'No se pudo conectar con el servidor para cerrar la sesión.';
    }

    if (error.status === 419) {
        return 'No fue posible renovar la protección CSRF para cerrar la sesión.';
    }

    if (error.status === 429) {
        return 'Hay demasiadas solicitudes. Espere un momento e intente nuevamente.';
    }

    if (error.status >= 500) {
        return 'El servidor no pudo cerrar la sesión. Intente nuevamente más tarde.';
    }

    return error.message || 'No fue posible cerrar la sesión.';
}

async function submitLogout() {
    try {
        await api.post('/auth/logout', {}, {
            redirectOn401: false,
            dispatchErrors: false,
        });
    } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
            return;
        }

        if (error instanceof ApiError && error.status === 419) {
            await initializeCsrf({ dispatchErrors: false });
            await api.post('/auth/logout', {}, {
                redirectOn401: false,
                dispatchErrors: false,
            });
            return;
        }

        throw error;
    }
}

export function initLogout({ loginUrl, beforeLogout = null } = {}) {
    const buttons = Array.from(document.querySelectorAll('[data-logout]'));

    if (!loginUrl || buttons.length === 0) {
        return;
    }

    const logout = async () => {
        if (pendingLogout) {
            return pendingLogout;
        }

        beforeLogout?.();
        setButtonsLoading(buttons, true);

        pendingLogout = submitLogout()
            .then(() => window.location.assign(loginUrl))
            .catch((error) => {
                notify({
                    type: 'error',
                    title: 'No se cerró la sesión',
                    message: errorMessage(error),
                    persistent: true,
                });

                setButtonsLoading(buttons, false);
                pendingLogout = null;
            });

        return pendingLogout;
    };

    buttons.forEach((button) => button.addEventListener('click', logout));
}
