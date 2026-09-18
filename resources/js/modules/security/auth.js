import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { setButtonLoading } from '@/core/loading';

const fields = ['business_slug', 'email', 'password'];

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.content?.trim() || null;
}

function clearErrors(form, feedback) {
    feedback.textContent = '';
    feedback.classList.add('hidden');

    fields.forEach((field) => {
        const input = form.elements.namedItem(field);
        const error = form.querySelector(`[data-field-error="${field}"]`);

        input?.removeAttribute('aria-invalid');

        if (error) {
            error.textContent = '';
            error.classList.add('hidden');
        }
    });
}

function showFieldErrors(form, errors) {
    let firstInvalid = null;
    let displayed = false;

    fields.forEach((field) => {
        const messages = errors?.[field];

        if (!Array.isArray(messages) || messages.length === 0) {
            return;
        }

        const input = form.elements.namedItem(field);
        const error = form.querySelector(`[data-field-error="${field}"]`);

        input?.setAttribute('aria-invalid', 'true');

        if (error) {
            error.textContent = messages[0];
            error.classList.remove('hidden');
            displayed = true;
        }

        firstInvalid ??= input;
    });

    firstInvalid?.focus();

    return displayed;
}

function generalMessage(error) {
    if (!(error instanceof ApiError)) {
        return 'No fue posible completar el inicio de sesión.';
    }

    if (error.status === 0) {
        return 'No fue posible conectar con el servidor. Revise su conexión e intente nuevamente.';
    }

    if (error.status === 401) {
        return error.message || 'El negocio, correo o contraseña no son válidos.';
    }

    if (error.status === 419) {
        return 'La sesión de seguridad expiró. Intente iniciar sesión nuevamente.';
    }

    if (error.status === 429) {
        return error.message || 'Demasiados intentos. Espere un momento antes de volver a intentar.';
    }

    if (error.status >= 500) {
        return 'El servidor no pudo procesar el inicio de sesión. Intente nuevamente más tarde.';
    }

    return error.message || 'No fue posible completar el inicio de sesión.';
}

function showGeneralError(feedback, message) {
    feedback.textContent = message;
    feedback.classList.remove('hidden');
    feedback.focus();
}

function credentials(form) {
    const data = new FormData(form);

    return {
        business_slug: String(data.get('business_slug') ?? '').trim(),
        email: String(data.get('email') ?? '').trim(),
        password: String(data.get('password') ?? ''),
    };
}

export default function initLogin() {
    const form = document.getElementById('loginForm');
    const feedback = document.getElementById('loginFeedback');
    const submitButton = document.getElementById('submitBtn');
    const dashboardUrl = meta('dashboard-url');

    if (!form || !feedback || !submitButton || !dashboardUrl) {
        console.error('[Gintly Login] No se encontró la configuración requerida.');
        return;
    }

    let submitting = false;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (submitting) {
            return;
        }

        submitting = true;
        clearErrors(form, feedback);
        setButtonLoading(submitButton, true, {
            label: 'Validando credenciales...',
        });

        try {
            await initializeCsrf();

            const response = await api.post(
                '/auth/login',
                credentials(form),
                { redirectOn401: false },
            );

            if (!response?.data || typeof response.data !== 'object') {
                throw new ApiError({
                    status: 500,
                    message: 'El servidor devolvió una respuesta de autenticación inválida.',
                    payload: response,
                });
            }

            document
                .getElementById('mainContainer')
                ?.classList.add('page-transition-out');

            window.location.assign(dashboardUrl);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                const displayed = showFieldErrors(form, error.errors);

                if (!displayed) {
                    showGeneralError(feedback, error.message);
                }
            } else {
                showGeneralError(feedback, generalMessage(error));
            }

            submitting = false;
            setButtonLoading(submitButton, false);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogin, { once: true });
} else {
    initLogin();
}
