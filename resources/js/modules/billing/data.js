import { api, initializeCsrf } from '@/core/api-client';
import { catalog, subscription } from './contracts';
let statusRequest = null;
// One recovery owner. API client itself NEVER replays a billing request.
export async function billingRequest(send, signal = null) {
    try { return await send(); }
    catch (error) { if (error.status !== 419 || signal?.aborted) throw error; await initializeCsrf({ dispatchErrors: false }); return send(); }
}
export const fetchPlans = async signal => catalog(await billingRequest(() => api.get('/billing/plans', {}, { expectedStatus: 200, dispatchErrors: false, signal }), signal));
export function fetchSubscription(signal = null) {
    if (statusRequest) return statusRequest;
    statusRequest = billingRequest(() => api.get('/billing/subscription', {}, { expectedStatus: 200, dispatchErrors: false, signal }), signal).then(subscription).finally(() => { statusRequest = null; });
    return statusRequest;
}
export const mutateSubscription = async (action, value = null) => subscription(await billingRequest(() => api.post(`/billing/subscription/${action}`, value, { expectedStatus: 200, dispatchErrors: false })));
