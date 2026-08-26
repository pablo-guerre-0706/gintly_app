const THEMES = Object.freeze({
    success: {
        accent: 'bg-emerald-500',
        badge: 'bg-emerald-100 text-emerald-700',
        label: 'Éxito',
    },
    warning: {
        accent: 'bg-amber-500',
        badge: 'bg-amber-100 text-amber-700',
        label: 'Atención',
    },
    error: {
        accent: 'bg-red-500',
        badge: 'bg-red-100 text-red-700',
        label: 'Error',
    },
    info: {
        accent: 'bg-blue-500',
        badge: 'bg-blue-100 text-blue-700',
        label: 'Información',
    },
});

let initialized = false;

function theme(type) {
    return THEMES[type] ?? THEMES.info;
}

function toastContainer() {
    let container = document.querySelector('#gintly-toast-container');

    if (container) {
        return container;
    }

    container = document.createElement('div');
    container.id = 'gintly-toast-container';
    container.className =
        'pointer-events-none fixed right-4 top-4 z-[9999] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3 sm:right-6 sm:top-6';

    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'false');

    document.body.appendChild(container);

    return container;
}

function removeToast(element) {
    element.classList.add(
        'translate-x-4',
        'opacity-0',
    );

    window.setTimeout(() => {
        element.remove();
    }, 200);
}

export function notify({
    type = 'info',
    title = null,
    message,
    duration = 5000,
    persistent = false,
} = {}) {
    if (!message) {
        return null;
    }

    const colors = theme(type);
    const toast = document.createElement('article');

    toast.className =
        'pointer-events-auto relative flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg transition-all duration-200';

    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const accent = document.createElement('div');
    accent.className = `w-1 shrink-0 ${colors.accent}`;

    const content = document.createElement('div');
    content.className = 'min-w-0 flex-1 px-4 py-3';

    const header = document.createElement('div');
    header.className = 'mb-1 flex items-center gap-2';

    const badge = document.createElement('span');
    badge.className =
        `rounded-full px-2 py-0.5 text-[11px] font-semibold ${colors.badge}`;
    badge.textContent = title ?? colors.label;

    const text = document.createElement('p');
    text.className =
        'text-sm leading-5 text-slate-600';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className =
        'm-2 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300';

    close.setAttribute('aria-label', 'Cerrar notificación');
    close.textContent = '×';

    header.appendChild(badge);
    content.append(header, text);
    toast.append(accent, content, close);

    close.addEventListener('click', () => removeToast(toast));

    toastContainer().appendChild(toast);

    if (!persistent && duration > 0) {
        window.setTimeout(() => {
            if (toast.isConnected) {
                removeToast(toast);
            }
        }, duration);
    }

    return toast;
}

export function alertModal({
    type = 'warning',
    title = 'Atención',
    message,
    confirmText = 'Entendido',
} = {}) {
    return new Promise((resolve) => {
        const colors = theme(type);

        const overlay = document.createElement('div');
        overlay.className =
            'fixed inset-0 z-[10000] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]';

        const dialog = document.createElement('div');
        dialog.className =
            'w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl';

        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');

        const badge = document.createElement('span');
        badge.className =
            `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${colors.badge}`;
        badge.textContent = colors.label;

        const heading = document.createElement('h2');
        heading.className =
            'mt-4 text-lg font-semibold text-slate-900';
        heading.textContent = title;

        const body = document.createElement('p');
        body.className =
            'mt-2 text-sm leading-6 text-slate-600';
        body.textContent = message ?? '';

        const actions = document.createElement('div');
        actions.className =
            'mt-6 flex justify-end';

        const confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className =
            'rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400';
        confirm.textContent = confirmText;

        actions.appendChild(confirm);
        dialog.append(badge, heading, body, actions);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        const close = () => {
            document.removeEventListener('keydown', onKeydown);
            overlay.remove();
            resolve(true);
        };

        const onKeydown = (event) => {
            if (event.key === 'Escape' || event.key === 'Enter') {
                close();
            }
        };

        confirm.addEventListener('click', close);
        document.addEventListener('keydown', onKeydown);

        confirm.focus();
    });
}

function handleGlobalHttpError(error) {
    if (!error) {
        return;
    }

    /*
     * 409 y 422 pertenecen al módulo:
     * - 409 puede contener evidencia persistida.
     * - 422 debe pintar errores junto a los campos.
     *
     * 401 lo procesa api-client.js mediante redirección.
     */
    if ([401, 409, 422].includes(error.status)) {
        return;
    }

    if (error.status === 403) {
        notify({
            type: 'error',
            title: 'Acceso denegado',
            message: error.message,
        });
        return;
    }

    if (error.status === 419) {
        notify({
            type: 'warning',
            title: 'Sesión expirada',
            message: error.message,
        });
        return;
    }

    if (error.status === 429) {
        notify({
            type: 'warning',
            title: 'Demasiadas solicitudes',
            message: error.message,
        });
        return;
    }

    if (error.status === 0 || error.status >= 500) {
        notify({
            type: 'error',
            title: 'Error del sistema',
            message: error.message,
            persistent: true,
        });
    }
}

export function initNotifications() {
    if (initialized) {
        return;
    }

    initialized = true;

    document.addEventListener(
        'gintly:http-error',
        ({ detail }) => handleGlobalHttpError(detail),
    );

    document.addEventListener(
        'gintly:notify',
        ({ detail }) => notify(detail),
    );
}
