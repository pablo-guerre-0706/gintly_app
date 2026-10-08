@props(['path', 'label', 'type' => 'text', 'autocomplete' => 'off', 'help' => null, 'maxlength' => null])
@php($fieldId = str_replace('.', '-', $path))
<div class="space-y-1.5">
    <label for="{{ $fieldId }}" class="block text-sm font-semibold text-slate-800">{{ $label }} <span class="font-normal text-slate-500">· Obligatorio</span></label>
    <div class="flex min-w-0 gap-2">
        <input id="{{ $fieldId }}" name="{{ $path }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}"
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @if($type === 'email') inputmode="email" autocapitalize="none" spellcheck="false" @endif
            aria-describedby="{{ $fieldId }}-error{{ $help ? ' '.$fieldId.'-help' : '' }}"
            class="registration-input min-w-0 flex-1" required>
        @if($type === 'password')
            <button type="button" data-password-toggle="{{ $fieldId }}" aria-controls="{{ $fieldId }}" aria-pressed="false" aria-label="Mostrar {{ mb_strtolower($label) }}" class="registration-secondary shrink-0 px-3 text-sm">Mostrar</button>
        @endif
    </div>
    @if($help)<p id="{{ $fieldId }}-help" class="text-sm leading-relaxed text-slate-500">{{ $help }}</p>@endif
    <p id="{{ $fieldId }}-error" data-register-error="{{ $path }}" class="hidden text-sm text-rose-700"></p>
</div>
