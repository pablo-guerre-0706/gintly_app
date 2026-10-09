import assert from 'node:assert/strict';

// Executed in the page: inspect visibility, not merely the presence of the shell.
// Never collect values, cookies or request bodies in a startup failure report.
export function inspectRegistrationShell() {
    const visible = node => !!node && node.getClientRects().length > 0
        && getComputedStyle(node).visibility !== 'hidden';
    const root = document.querySelector('[data-registration]');
    const form = root?.querySelector('[data-register-form]');
    const account = root?.querySelector('[data-register-stage="1"]');
    const progress = [...document.querySelectorAll('[data-register-progress]')];
    return {
        shellPresent: !!root,
        initialized: root?.dataset.initialized === 'true',
        steps: progress.length,
        activeStep: progress.find(node => node.getAttribute('aria-current') === 'step')?.dataset.registerProgress || null,
        formVisible: visible(form),
        accountVisible: visible(account),
        visibleInputs: [...(account?.querySelectorAll('input') || [])].filter(visible).length,
    };
}

export function assertRegistrationShell(state) {
    assert(state.shellPresent && state.initialized && state.steps === 4 && state.activeStep === '1'
        && state.formVisible && state.accountVisible && state.visibleInputs === 5,
    'Registration shell loaded without a visible, initialized account form: ' + JSON.stringify(state));
    return state;
}

export async function waitForRegistrationShell(page, timeout = 5000) {
    try {
        await page.waitForFunction(() => {
            const root = document.querySelector('[data-registration]');
            const form = root?.querySelector('[data-register-form]');
            return root?.dataset.initialized === 'true' && form?.getClientRects().length > 0;
        }, null, { timeout });
    } catch {
        // A diagnostic assertion below explains the missing form without leaking data.
    }
    return assertRegistrationShell(await page.evaluate(inspectRegistrationShell));
}
