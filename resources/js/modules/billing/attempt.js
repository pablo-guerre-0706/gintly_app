import { secureUuid } from '../registration/attempt.js';
import { retryDelay } from '../registration/contract.js';
import { selection, checkout, billingError } from './contracts.js';

export function createCheckoutAttempt({ plans, post, csrf, restore = null, persist = () => {}, uuid = secureUuid, now = Date.now }) {
    let attempt = restore ? Object.freeze({ key: restore.key, snapshot: selection(restore.payload, plans) }) : null;
    let state = attempt ? 'recoverable' : 'editing', retryAt = 0;
    const result = (extra = {}) => ({ state, retryAt, ...extra });
    async function submit(value = null) {
        if (['submitting', 'blocked', 'expired', 'checkout'].includes(state) || now() < retryAt) return result({ ignored: true });
        if (state === 'editing') {
            attempt = Object.freeze({ key: uuid(), snapshot: selection(value, plans) });
            persist(attempt);
        }
        state = 'submitting'; // Before ANY await, including CSRF.
        const send = () => post('/billing/checkout', attempt.snapshot, { expectedStatus: 201, headers: { 'Idempotency-Key': attempt.key }, dispatchErrors: false });
        try {
            await csrf(); let response;
            try { response = await send(); }
            catch (error) { if (error.status !== 419) throw error; await csrf(); response = await send(); }
            const value = checkout(response, attempt.snapshot);
            state = 'checkout'; return result({ checkout: value });
        } catch (error) {
            if (error.status === 422) { attempt = null; persist(null); state = 'editing'; }
            else if (error.code === 'CHECKOUT_KEY_EXPIRED') state = 'expired';
            else if (error.status === 403 || error.code === 'CHECKOUT_IDEMPOTENCY_CONFLICT') state = 'blocked';
            else state = 'recoverable';
            if (error.status === 429) retryAt = now() + retryDelay(error.retryAfter, now());
            return result({ error, message: billingError(error), errors: error.errors ?? {}, alreadyActive: error.code === 'SUBSCRIPTION_ALREADY_ACTIVE' });
        }
    }
    function newAfterExpiry() { if (state !== 'expired') return false; attempt = null; persist(null); state = 'editing'; retryAt = 0; return true; }
    function completed() { attempt = null; persist(null); state = 'editing'; }
    return Object.freeze({ submit, newAfterExpiry, completed, get state() { return state; }, get retryAt() { return retryAt; }, get snapshot() { return attempt?.snapshot ?? null; } });
}
