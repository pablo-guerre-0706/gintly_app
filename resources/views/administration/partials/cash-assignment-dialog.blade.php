<dialog data-assignment-dialog tabindex="-1" class="m-auto max-h-[94dvh] w-[min(620px,calc(100vw-2rem))] overflow-y-auto rounded-3xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/60" aria-labelledby="assignment-title" aria-describedby="assignment-context">
    <section class="space-y-5 p-5 sm:p-7">
        <header class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold text-gintly-brand">Caja–Cajero</p><h2 id="assignment-title" class="mt-1 text-xl font-bold">Gestionar cajero</h2></div><button data-assignment-close type="button" aria-label="Cerrar gestión de cajero" class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-300">✕</button></header>
        <p id="assignment-context" data-assignment-context class="break-words text-sm text-gintly-text-secondary"></p>
        <p data-assignment-state role="status" tabindex="-1" class="rounded-xl bg-slate-50 p-4 text-sm"></p>
        <button data-assignment-retry type="button" hidden class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Actualizar asignación</button>
        <form data-assignment-form novalidate hidden class="space-y-5">
            <p data-form-error-summary role="alert" tabindex="-1" hidden class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"></p>
            <label class="grid min-w-0 gap-2 text-sm font-semibold">Nuevo cajero · Obligatorio para asignar
                <select name="user_id" data-assignment-cashier class="min-h-11 w-full min-w-0 rounded-xl border border-slate-300 px-3" aria-describedby="assignment-help assignment-user-error"><option value="">Selecciona un cajero</option></select>
            </label>
            <p id="assignment-help" data-assignment-help class="text-sm leading-6 text-gintly-text-secondary"></p>
            <p id="assignment-user-error" data-error-for="user_id" class="text-sm text-red-700"></p>
            <label data-assignment-confirm-block class="flex items-start gap-3 text-sm" hidden><input name="confirmation" type="checkbox" class="mt-1 size-5 shrink-0"><span>Confirmo que se finalizará la asignación vigente. El historial se conserva. El cambio requiere después registrar una nueva asignación.</span></label>
            <div class="flex flex-wrap gap-3"><button data-assignment-submit type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 text-sm font-semibold text-white disabled:opacity-50">Asignar cajero</button><button data-assignment-finish type="button" hidden class="min-h-11 rounded-xl border border-red-300 px-5 text-sm font-semibold text-red-800 disabled:opacity-50">Finalizar asignación</button></div>
        </form>
    </section>
</dialog>
