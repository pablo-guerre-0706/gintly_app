// Finite, visibility-aware, non-overlapping status reads; no payment inference or reconciliation.
export class SubscriptionPoller {
    constructor({ load, visible, onResult, onError, onExhausted, schedule = (fn, delay) => setTimeout(fn, delay), cancel = id => clearTimeout(id) }) {
        Object.assign(this, { load, visible, onResult, onError, onExhausted, schedule, cancel });
        this.timer = null; this.controller = null; this.reads = 0; this.stopped = true; this.running = false; this.generation = 0;
    }
    start(initialReads = 0) { this.stop(); this.stopped = false; this.reads = initialReads; if (initialReads) this.timer = this.schedule(() => { void this.tick(); }, 5000); else void this.tick(); }
    stop() { this.generation++; this.stopped = true; this.running = false; this.cancel(this.timer); this.timer = null; this.controller?.abort(); }
    resume() { if (!this.stopped && !this.running && this.visible() && this.timer === null) void this.tick(); }
    async tick() {
        if (this.stopped || this.running) return;
        if (!this.visible()) { this.timer = this.schedule(() => { void this.tick(); }, 5000); return; }
        if (this.reads >= 12) { this.stopped = true; this.onExhausted(); return; }
        this.running = true; this.reads++; const controller = new AbortController(); this.controller = controller; const generation = this.generation; let delay = 5000;
        try {
            const value = await this.load(controller.signal);
            if (controller.signal.aborted || this.stopped) return;
            this.onResult(value);
            if (value.grants_access) { this.stopped = true; return; }
        } catch (error) {
            if (!controller.signal.aborted && !this.stopped) {
                this.onError(error);
                if (error.status === 429) { const seconds = Number(error.retryAfter), date = Date.parse(error.retryAfter); delay = Math.max(5000, Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : Number.isFinite(date) ? date - Date.now() : 60000); }
            }
        }
        finally {
            if (generation !== this.generation) return;
            this.running = false;
            if (!this.stopped) {
                if (this.reads >= 12) { this.stopped = true; this.onExhausted(); }
                else this.timer = this.schedule(() => { void this.tick(); }, delay);
            }
        }
    }
}
