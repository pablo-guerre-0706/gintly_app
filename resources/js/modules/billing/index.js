import { api, initializeCsrf } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { readPreference, readCheckout, saveCheckout, savePreference } from '@/core/billing-storage';
import { clearFormErrors, showFormErrors } from '@/core/form-ui';
import { initLogout } from '@/shell/logout';
import { fetchPlans, fetchSubscription, mutateSubscription } from './data';
import { billingError, priceText, selection, PERIODS } from './contracts';
import { createCheckoutAttempt } from './attempt';
import { SubscriptionPoller } from './poller';
import { confirmation } from './confirmation';
import { billingView } from './view';
export default async function initBilling() {
    initLogout({ loginUrl: document.querySelector('meta[name="login-url"]')?.content });
    const root = document.querySelector('[data-billing-page]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const ui = billingView(root), form = ui.find('billing-form'), refresh = ui.find('billing-refresh');
    let context, plans = [], state = null, attempt, busy = false, reading = false, cooldown = 0, retryTimer = null, mutationUnknown = false;
    const controller = new AbortController(), dialog = confirmation(ui.find('billing-confirmation'));
    const canManage = root.dataset.canManage === 'true';
    const demoActive = () => state?.access_source === 'demo';
    const poller = new SubscriptionPoller({ load: fetchSubscription, visible: () => !document.hidden,
        onResult: value => { state = value; render(); if (value.grants_access) ui.notice(demoActive() ? 'El servidor confirma acceso temporal de evaluación. Puedes continuar al panel.' : 'El servidor confirma acceso comercial vigente. Puedes continuar al panel.'); },
        onError: error => { ui.notice(billingError(error)); if (error.status === 401 || error.status === 403) poller.stop(); },
        onExhausted: () => ui.notice('Confirmación pendiente. Se completaron las 12 consultas de este ciclo; puedes actualizar el estado sin volver a contratar.') });
    const value = () => selection({ plan: form.elements.plan.value, period: form.elements.period.value }, plans);
    function buttons() {
        const locked = demoActive() || busy || !attempt || attempt.state !== 'editing';
        if (form) {
            form.setAttribute('aria-busy', String(busy)); ui.find('billing-fields').disabled = locked;
            ui.find('billing-submit').disabled = demoActive() || busy || !state || reading || mutationUnknown || Date.now() < cooldown || (attempt && attempt.state !== 'editing');
            ui.find('billing-submit').textContent = busy ? 'Procesando…' : state?.grants_access ? 'Solicitar cambio de plan' : 'Continuar al checkout alojado';
            const recovering = attempt && attempt.state !== 'editing';
            ui.find('billing-recovery').hidden = !recovering;
            ui.find('checkout-retry').disabled = busy || Date.now() < (attempt?.retryAt ?? 0) || ['blocked', 'expired', 'checkout'].includes(attempt?.state);
            ui.find('checkout-new').hidden = attempt?.state !== 'expired'; ui.find('checkout-new').disabled = busy;
            ui.find('cancel-renewal').hidden = demoActive() || !state?.grants_access || state.status === 'canceled'; ui.find('cancel-renewal').disabled = demoActive() || busy || reading || mutationUnknown || Date.now() < cooldown;
        }
        refresh.disabled = busy || reading || Date.now() < cooldown;
    }
    function render() {
        if (state) {
            ui.renderState(state, plans, context.business.timezone || 'UTC');
            if (state.grants_access) attempt?.completed();
        }
        ui.find('billing-readonly').hidden = demoActive() || canManage;
        if (form) {
            ui.find('billing-owner').hidden = demoActive();
            if (attempt?.snapshot) { form.elements.plan.value = attempt.snapshot.plan; form.elements.period.value = attempt.snapshot.period; }
            ui.renderCatalog(plans, form.elements.period.value);
            const plan = plans.find(plan => plan.key === form.elements.plan.value);
            ui.find('billing-selection').textContent = plan ? `${plan.name} · ${PERIODS[form.elements.period.value]} · ${priceText(plan, form.elements.period.value)} ${form.elements.period.value === 'annual' ? 'por los 12 meses por adelantado' : 'por mes'}` : 'Selecciona un plan.';
        }
        buttons();
    }
    function errorNotice(error) {
        ui.notice(billingError(error), true);
        if (error.status === 422 && form) {
            showFormErrors(form, error.errors, billingError(error));
            // The writing fieldset unlocks in finally; focus only after that synchronous cleanup.
            queueMicrotask(() => (form.querySelector('[aria-invalid="true"]') ?? form.querySelector('[data-form-error-summary]'))?.focus());
        }
        if (error.status === 429) {
            const seconds = Number(error.retryAfter), date = Date.parse(error.retryAfter);
            cooldown = Date.now() + (Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : Number.isFinite(date) ? Math.max(0, date-Date.now()) : 60000);
            ui.notice(`${billingError(error)} Espera ${Math.ceil((cooldown-Date.now())/1000)} segundos.`, true);
            clearTimeout(retryTimer); retryTimer = setTimeout(buttons, cooldown-Date.now()+10);
        }
    }
    async function read() {
        if (busy || reading || Date.now() < cooldown) return;
        if (!context || !plans.length || (form && !attempt)) return initialize(true);
        reading = true; buttons();
        try { state = await fetchSubscription(controller.signal); mutationUnknown = false; render(); ui.notice('Estado actualizado desde el servidor.'); return state; }
        catch (error) { if (!controller.signal.aborted) errorNotice(error); }
        finally { reading = false; buttons(); }
    }
    async function checkoutSubmit() {
        if (demoActive() || busy || reading || !state || !attempt || mutationUnknown || Date.now() < cooldown || Date.now() < attempt.retryAt) return;
        busy = true; buttons(); if (form) clearFormErrors(form);
        try {
            const result = await attempt.submit(attempt.snapshot ? null : value());
            if (result.checkout) { ui.notice('Checkout preparado. El pago y el acceso todavía no están confirmados.'); window.location.assign(result.checkout.checkout_url); return; }
            if (result.error) { errorNotice(result.error); if (result.alreadyActive) { state = await fetchSubscription(); render(); } }
        } catch (error) { errorNotice(error); }
        finally { busy = false; buttons(); }
    }
    async function management(action, payload, source) {
        if (demoActive() || busy || reading || mutationUnknown || !state?.grants_access || Date.now() < cooldown) return;
        busy = true; buttons();
        const message = action === 'cancel' ? 'Cancelar renovación no elimina tu negocio ni su historial. Se conservará acceso hasta la vigencia que confirme el servidor. ¿Deseas cancelar la próxima renovación?' : 'El proveedor puede determinar un cargo prorrateado. El servidor decide límites y fecha efectiva; las capacidades no cambian por esta selección. ¿Solicitar el cambio indicado?';
        try {
            if (!await dialog.ask(message, source)) return;
            state = await mutateSubscription(action, payload); render();
            ui.notice(action === 'cancel' ? 'Renovación cancelada. Consulta la vigencia pagada informada.' : 'Solicitud registrada. El cambio pendiente y su fecha los confirma el servidor.', true);
            state = await fetchSubscription(); render();
        } catch (error) {
            mutationUnknown = !error.status || error.status >= 500 || error.status === 200;
            errorNotice(error);
            if (mutationUnknown) ui.notice(`${billingError(error)} Actualiza el estado antes de solicitar otra modificación.`, true);
        }
        finally { busy = false; buttons(); }
    }
    refresh.addEventListener('click', async () => { poller.stop(); const current = await read(); if (root.dataset.mode === 'return' && current && !current.grants_access) poller.start(1); });
    form?.addEventListener('change', () => { if (attempt?.state !== 'editing') return; savePreference(value()); render(); });
    form?.addEventListener('submit', event => { event.preventDefault(); if (demoActive() || busy || reading || !state || mutationUnknown || Date.now() < cooldown) return; if (state.grants_access) { let payload; try { payload = value(); } catch (error) { errorNotice(error); return; } void management('change', payload, ui.find('billing-submit')); } else void checkoutSubmit(); });
    ui.find('checkout-retry')?.addEventListener('click', checkoutSubmit);
    ui.find('checkout-new')?.addEventListener('click', () => { if (attempt.newAfterExpiry()) { ui.notice('Intento vencido descartado. Revisa la selección antes de enviar uno nuevo.'); render(); } });
    ui.find('cancel-renewal')?.addEventListener('click', event => { void management('cancel', null, event.currentTarget); });
    document.addEventListener('visibilitychange', () => poller.resume());
    window.addEventListener('pagehide', () => { controller.abort(); poller.stop(); clearTimeout(retryTimer); dialog.destroy(); }, { once: true });
    async function initialize(recovery = false) {
        if (busy || reading || controller.signal.aborted) return;
        reading = true; root.setAttribute('aria-busy', 'true'); ui.find('billing-loading').hidden = false; buttons();
        try {
            context = await getSessionContext({ refresh: recovery && !context });
            if (!Number.isInteger(context.business.id) || context.business.id < 1) throw new TypeError('El contexto no incluye el negocio autenticado. No se puede vincular un intento de contratación.');
            ui.find('billing-business').textContent = context.business.name;
            const results = await Promise.allSettled([fetchPlans(controller.signal), fetchSubscription(controller.signal)]);
            if (results[0].status === 'rejected') throw results[0].reason;
            plans = results[0].value;
            let restore = readCheckout(context.business.id);
            if (form) {
                form.elements.plan.replaceChildren();
                plans.forEach(plan => { const option = document.createElement('option'); option.value = plan.key; option.textContent = plan.name; form.elements.plan.append(option); });
                const preference = readPreference();
                if (preference && plans.some(plan => plan.key === preference.plan)) { form.elements.plan.value = preference.plan; form.elements.period.value = preference.period; }
                if (restore && !plans.some(plan => plan.key === restore.payload.plan)) { saveCheckout(context.business.id, null); restore = null; }
                attempt = createCheckoutAttempt({ plans, post: api.post, csrf: () => initializeCsrf({ dispatchErrors: false }), restore, persist: value => saveCheckout(context.business.id, value) });
            }
            if (results[1].status === 'rejected') throw results[1].reason;
            state = results[1].value; render();
            if (root.dataset.mode === 'access') {
                if (state.grants_access) window.location.replace(root.dataset.dashboardUrl);
                else window.location.replace(root.dataset.managementUrl);
            } else if (root.dataset.mode === 'return' && !state.grants_access) { ui.notice('Confirmación pendiente. Consultamos el estado de forma acotada; regresar del checkout no activa el acceso.'); poller.start(1); }
        } catch (error) { if (!controller.signal.aborted) errorNotice(error); }
        finally { reading = false; root.setAttribute('aria-busy', 'false'); ui.find('billing-loading').hidden = true; buttons(); }
        return state;
    }
    await initialize();
}
