let initialized = false;
let lockDepth = 0;
let previousOverflow = '';

const buttonStates = new WeakMap();

function overlay() {
    let element = document.querySelector('#gintly-loading-overlay');

    if (element) {
        return element;
    }

    element = document.createElement('div');
    element.id = 'gintly-loading-overlay';
    element.className =
        'fixed inset-0 z-[9998] hidden items-center justify-center bg-slate-950/30 p-4 backdrop-blur-[1px]';

    element.innerHTML = `
        <div class="flex min-w-52 flex-col items-center rounded-2xl bg-white px-6 py-5 shadow-2xl">
            <svg
                class="h-7 w-7 animate-spin text-slate-900"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
            >
                <circle
                    class="opacity-20"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-90"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                ></path>
            </svg>

            <p
                data-loading-message
                class="mt-3 text-sm font-medium text-slate-700"
            >
                Procesando...
            </p>
        </div>
    `;

    element.setAttribute('role', 'status');
    element.setAttribute('aria-live', 'polite');
    element.setAttribute('aria-hidden', 'true');

    document.body.appendChild(element);

    return element;
}

export function showLoading({
    message = 'Procesando...',
} = {}) {
    const element = overlay();

    lockDepth += 1;

    if (lockDepth === 1) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    }

    const label = element.querySelector('[data-loading-message]');

    if (label) {
        label.textContent = message;
    }

    element.classList.remove('hidden');
    element.classList.add('flex');
    element.setAttribute('aria-hidden', 'false');
}

export function hideLoading({
    force = false,
} = {}) {
    const element = overlay();

    lockDepth = force
        ? 0
        : Math.max(0, lockDepth - 1);

    if (lockDepth > 0) {
        return;
    }

    element.classList.add('hidden');
    element.classList.remove('flex');
    element.setAttribute('aria-hidden', 'true');

    document.body.style.overflow = previousOverflow;
}

export async function withLoading(
    operation,
    options = {},
) {
    showLoading(options);

    try {
        return await (
            typeof operation === 'function'
                ? operation()
                : operation
        );
    } finally {
        hideLoading();
    }
}

function resolveButton(target) {
    if (target instanceof HTMLButtonElement) {
        return target;
    }

    if (typeof target === 'string') {
        return document.querySelector(target);
    }

    return null;
}

export function setButtonLoading(
    target,
    loading,
    {
        label = 'Procesando...',
    } = {},
) {
    const button = resolveButton(target);

    if (!button) {
        return;
    }

    if (loading) {
        if (!buttonStates.has(button)) {
            buttonStates.set(button, {
                html: button.innerHTML,
                disabled: button.disabled,
            });
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        button.innerHTML = `
            <span class="inline-flex items-center justify-center gap-2">
                <svg
                    class="h-4 w-4 animate-spin"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <circle
                        class="opacity-20"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-90"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                    ></path>
                </svg>

                <span></span>
            </span>
        `;

        button.querySelector('span span').textContent = label;

        return;
    }

    const state = buttonStates.get(button);

    if (!state) {
        return;
    }

    button.innerHTML = state.html;
    button.disabled = state.disabled;
    button.removeAttribute('aria-busy');

    buttonStates.delete(button);
}

export function initLoading() {
    if (initialized) {
        return;
    }

    initialized = true;

    document.addEventListener(
        'gintly:loading:start',
        ({ detail }) => showLoading(detail ?? {}),
    );

    document.addEventListener(
        'gintly:loading:stop',
        () => hideLoading(),
    );
}
