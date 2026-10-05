<dialog class="m-auto max-h-[92dvh] w-[calc(100%-2rem)] max-w-3xl overflow-y-auto rounded-2xl border border-gintly-border bg-white p-0 shadow-2xl backdrop:bg-gintly-sidebar/60" data-supplier-dialog aria-labelledby="supplier-dialog-title">
    <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
        <div class="min-w-0"><h2 id="supplier-dialog-title" class="text-xl font-bold"></h2><p class="mt-1 break-words text-sm text-gintly-text-secondary" data-dialog-context></p></div>
        <button type="button" class="min-h-11 min-w-11 rounded-xl border border-slate-300" data-dialog-close aria-label="Cerrar diálogo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="space-y-5 p-5 sm:p-7">
        <p data-dialog-notice class="rounded-xl bg-slate-50 p-4 text-sm" role="status" tabindex="-1" hidden></p>
        <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold" data-dialog-retry hidden>Actualizar ubicaciones</button>
        <form data-candidate-form class="space-y-4" hidden>
            <p class="text-sm text-gintly-text-secondary">El candidato nace pendiente. Registrar, aprobar y confirmar una ubicación son acciones independientes.</p>
            @foreach ([['name','Nombre del proveedor','text',160,true,'organization'],['tax_id','Identificación fiscal','text',30,false,'off'],['email','Correo electrónico','email',180,false,'email'],['phone','Teléfono','tel',30,false,'tel']] as [$field,$label,$type,$max,$required,$autocomplete])
                <div><label for="candidate-{{ $field }}" class="text-sm font-semibold">{{ $label }} · {{ $required ? 'Obligatorio' : 'Opcional' }}</label>
                    <input id="candidate-{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="{{ $max }}" autocomplete="{{ $autocomplete }}" @required($required) aria-describedby="candidate-{{ $field }}-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3">
                    <p id="candidate-{{ $field }}-error" data-error-for="{{ $field }}" class="mt-1 text-sm text-red-700"></p>
                </div>
            @endforeach
            <p data-form-error-summary role="alert" tabindex="-1" class="text-sm text-red-800" hidden></p>
            <button class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white" type="submit">Registrar candidato</button>
        </form>
        <section data-locations-panel hidden aria-label="Ubicaciones del proveedor">
            <div class="mb-5 space-y-3" data-location-list></div>
            <button type="button" class="mb-5 min-h-11 rounded-xl border border-gintly-brand px-4 text-sm font-semibold text-gintly-brand" data-new-location>Añadir dirección</button>
            <form data-location-form class="space-y-4 rounded-xl border border-slate-200 p-4" hidden>
                <h3 class="font-semibold" data-location-form-title></h3>
                <div><label for="location-address" class="text-sm font-semibold">Dirección · Obligatorio</label><textarea id="location-address" name="address" rows="3" maxlength="255" required autocomplete="street-address" aria-describedby="location-address-error" class="mt-2 w-full rounded-xl border border-slate-300 p-3"></textarea><p id="location-address-error" data-error-for="address" class="text-sm text-red-700"></p></div>
                <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="is_primary" aria-describedby="location-primary-help location-primary-error">Ubicación principal · Opcional</label>
                <p id="location-primary-help" class="text-xs text-gintly-text-secondary">Solo puede existir una principal por proveedor. Cambiar la dirección retira el marcador hasta volver a confirmar sus coordenadas.</p><p id="location-primary-error" data-error-for="is_primary" class="text-sm text-red-700"></p>
                <p data-form-error-summary role="alert" tabindex="-1" class="text-sm text-red-800" hidden></p>
                <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white">Guardar dirección</button>
            </form>
            <form data-coordinate-form class="mt-5 space-y-4 rounded-xl border border-slate-200 p-4" hidden>
                <h3 class="font-semibold">Confirmar coordenadas</h3>
                <p class="text-sm text-gintly-text-secondary">Selecciona un punto en el mapa o introduce ambas coordenadas. El punto no se guarda hasta confirmar. También puedes completar este formulario sin geocodificador ni capa cartográfica.</p>
                <div data-point-map class="supplier-map-canvas supplier-map-picker" aria-label="Seleccionar punto de la ubicación"></div>
                <p data-point-map-error class="text-sm text-amber-800" role="status" hidden>La capa cartográfica no está disponible. Puedes introducir las coordenadas manualmente.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['latitude'=>'Latitud (−90 a 90)', 'longitude'=>'Longitud (−180 a 180)'] as $field=>$label)
                        <div><label for="location-{{ $field }}" class="text-sm font-semibold">{{ $label }} · Obligatorio</label><input id="location-{{ $field }}" name="{{ $field }}" type="text" inputmode="decimal" required autocomplete="off" aria-describedby="location-{{ $field }}-error" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3"><p id="location-{{ $field }}-error" data-error-for="{{ $field }}" class="text-sm text-red-700"></p></div>
                    @endforeach
                </div>
                <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="confirmation" required>Confirmo que este punto corresponde a la dirección guardada.</label>
                <p data-form-error-summary role="alert" tabindex="-1" class="text-sm text-red-800" hidden></p>
                <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white">Confirmar ubicación</button>
            </form>
        </section>
        <form data-status-form class="space-y-4" hidden>
            <p data-status-description class="text-sm leading-6 text-gintly-text-secondary"></p>
            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="confirmation" required>Confirmo esta acción sobre el proveedor indicado.</label>
            <p data-form-error-summary role="alert" tabindex="-1" class="text-sm text-red-800" hidden></p>
            <button type="submit" class="min-h-11 rounded-xl bg-gintly-brand px-5 font-semibold text-white" data-status-submit>Confirmar</button>
        </form>
    </div>
</dialog>
