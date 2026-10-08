import { registrationResult, retryDelay } from './contract.js';

export function secureUuid(crypto = globalThis.crypto) {
    if (typeof crypto?.randomUUID === 'function') return crypto.randomUUID();
    if (typeof crypto?.getRandomValues !== 'function') throw new Error('secure_random_unavailable');
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

// Una sola fuente del estado y del intento lógico. Snapshot y secretos solo en memoria.
export function createRegistrationAttempt({ post, csrf, uuid = secureUuid, now = Date.now }) {
    let state = 'editing';
    let attempt = null;
    let retryAt = 0;
    const outcome = (extra = {}) => ({ state, retryAt, ...extra });

    async function submit(payload = null) {
        if (['submitting', 'success', 'forbidden', 'conflict'].includes(state) || now() < retryAt) return outcome({ ignored: true });
        if (state === 'editing') {
            if (!payload) return outcome({ ignored: true });
            const snapshot = Object.freeze({ business: Object.freeze({ ...payload.business }), owner: Object.freeze({ ...payload.owner }) });
            attempt = { key: uuid(), snapshot };
        }
        // Síncrono, antes del primer await: impide doble clic y Enter repetido.
        state = 'submitting';
        try {
            await csrf();
            let response;
            try {
                response = await send();
            } catch (error) {
                if (error.status !== 419) throw error;
                await csrf();
                response = await send(); // máximo un replay automático, misma clave y snapshot
            }
            const result = registrationResult(response);
            attempt = null;
            state = 'success';
            return outcome({ result });
        } catch (error) {
            if (error.status === 422) {
                attempt = null;
                state = 'editing';
                return outcome({ errors: error.errors ?? {}, message: 'Revisa los datos indicados. Todavía no se ha completado este registro.' });
            }
            if (error.status === 403) {
                attempt = null;
                state = 'forbidden';
                return outcome({ message: 'Ya existe una sesión autenticada. Continúa en tu panel; no es necesario registrar otro negocio ni cerrar sesión automáticamente.' });
            }
            if (error.status === 409 && error.code === 'REGISTRATION_IDEMPOTENCY_CONFLICT') {
                attempt = null;
                state = 'conflict';
                return outcome({ message: 'Esta clave de idempotencia corresponde a datos distintos. No se cambiará la clave ni se creará otro negocio automáticamente. Conserva este resultado y solicita revisión.' });
            }
            if (error.status === 429) {
                retryAt = now() + retryDelay(error.retryAfter, now());
                state = 'recoverable';
                return outcome({ message: 'Se alcanzó el límite de registros. Espera el plazo indicado y reintenta el mismo registro.' });
            }
            state = [409, 419, 401].includes(error.status) ? 'recoverable' : 'uncertain';
            const message = error.status === 409 && error.code === 'BUSINESS_SLUG_CONFLICT'
                ? 'El servidor no pudo asignar un identificador único. Reintenta este mismo registro sin cambiar sus datos.'
                : error.status === 419
                    ? 'No se pudo renovar la seguridad de la sesión. Reintenta el mismo registro; no cambies sus datos.'
                    : error.status === 401
                        ? 'El servidor no aceptó esta solicitud pública. Reintenta el mismo registro o solicita revisión.'
                        : 'No pudimos confirmar el resultado. El negocio podría haberse creado. Reintenta con la misma clave y los mismos datos para recuperar el resultado sin duplicarlo.';
            return outcome({ message });
        }
    }

    function send() {
        return post('/auth/register', attempt.snapshot, {
            headers: { 'Idempotency-Key': attempt.key }, expectedStatus: 201,
            redirectOn401: false, dispatchErrors: false,
        });
    }

    return Object.freeze({ submit, get state() { return state; }, get retryAt() { return retryAt; } });
}
