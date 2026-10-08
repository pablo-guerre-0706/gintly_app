// Only non-sensitive preference + tenant-bound UUID/snapshot. Never provider URLs or authentication.
const PREFERENCE = 'gintly.billing.preference';
const ATTEMPT = 'gintly.billing.checkout';
const storage = () => { try { return window.sessionStorage; } catch { return null; } };
export const validUuid = value => typeof value === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value);
function read(key, target = storage()) { try { return JSON.parse(target?.getItem(key) ?? 'null'); } catch { return null; } }
function save(key, value, target = storage()) { try { if (value === null) target?.removeItem(key); else target?.setItem(key, JSON.stringify(value)); } catch { /* Memory remains authoritative for this page. */ } }
export function readPreference(target) { const value = read(PREFERENCE, target); return value && typeof value.plan === 'string' && ['monthly', 'annual'].includes(value.period) ? { plan: value.plan, period: value.period } : null; }
export function savePreference(value, target) { save(PREFERENCE, { plan: value.plan, period: value.period }, target); }
export function readCheckout(businessId, target) {
    const value = read(ATTEMPT, target);
    if (!value) return null;
    if (value.businessId !== businessId) { clearBillingStorage(target); return null; }
    if (!validUuid(value.key) || !value.payload || Object.keys(value.payload).sort().join(',') !== 'period,plan') { save(ATTEMPT, null, target); return null; }
    return { key: value.key, payload: { plan: value.payload.plan, period: value.payload.period } };
}
export function saveCheckout(businessId, attempt, target) { save(ATTEMPT, attempt === null ? null : { businessId, key: attempt.key, payload: { plan: attempt.snapshot.plan, period: attempt.snapshot.period } }, target); }
export function clearBillingStorage(target) { save(PREFERENCE, null, target); save(ATTEMPT, null, target); }
