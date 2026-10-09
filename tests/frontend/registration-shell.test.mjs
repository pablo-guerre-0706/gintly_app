import test from 'node:test';
import assert from 'node:assert/strict';
import { assertRegistrationShell } from './registration-shell.mjs';

const ready = { shellPresent: true, initialized: true, steps: 4, activeStep: '1', formVisible: true, accountVisible: true, visibleInputs: 5 };

test('Registration acceptance requires the initialized, visible first step', () => {
    assert.equal(assertRegistrationShell(ready), ready);
});

for (const [key, value] of Object.entries({ initialized: false, formVisible: false, accountVisible: false, visibleInputs: 0, activeStep: null, steps: 7 })) {
    test('A rendered shell cannot pass acceptance when ' + key + ' is ' + value, () => {
        assert.throws(() => assertRegistrationShell({ ...ready, [key]: value }), /shell loaded without a visible/);
    });
}
