@extends('layouts.panel')

@section('title', 'Cierre de caja')
@section('page-script', 'modules/finance/cash-closing')

@section('content')
@php
    $denominations = [
        ['label'=>'C$ 1000','value'=>'1000.00','type'=>'Billete'],
        ['label'=>'C$ 500','value'=>'500.00','type'=>'Billete'],
        ['label'=>'C$ 200','value'=>'200.00','type'=>'Billete'],
        ['label'=>'C$ 100','value'=>'100.00','type'=>'Billete'],
        ['label'=>'C$ 50','value'=>'50.00','type'=>'Billete'],
        ['label'=>'C$ 20','value'=>'20.00','type'=>'Billete'],
        ['label'=>'C$ 10','value'=>'10.00','type'=>'Billete'],
        ['label'=>'C$ 5','value'=>'5.00','type'=>'Moneda'],
        ['label'=>'C$ 1','value'=>'1.00','type'=>'Moneda'],
    ];

    $summary = [
        ['title'=>'Fondo de apertura','value'=>$session['opening_amount_label'] ?? '—'],
        ['title'=>'Turno','value'=>$session['shift_label'] ?? '—'],
        ['title'=>'Transacciones','value'=>$session['transactions_label'] ?? '—'],
        ['title'=>'Ajustes registrados','value'=>$session['adjustments_label'] ?? '—'],
    ];
@endphp

<main
    id="cashClosingRoot"
    data-close-url="{{ $closeEndpoint ?? '' }}"
    class="mx-auto w-full max-w-[1160px] bg-[#F5F5F4] px-6 py-7"
>
    <header class="mb-7">
        <h1 class="text-[27px] font-bold tracking-[-.035em] text-[#171717]">
            Cierre de caja
        </h1>
        <p class="mt-1 text-[10px] text-[#777]">
            {{ $session['register_label'] ?? 'Caja' }} ·
            {{ $session['user_name'] ?? auth()->user()?->name }} ·
            {{ $session['branch_name'] ?? 'Sucursal' }} ·
            {{ $session['time_label'] ?? '' }}
        </p>
    </header>

    <div class="mb-6 rounded-[8px] border border-[#9EA8F2] bg-[#E8E9FF] px-4 py-3 text-[9px] leading-5 text-[#5A63AA]">
        Arqueo ciego activo. El saldo teórico se mantiene oculto hasta confirmar el conteo físico.
        Ingresa la cantidad de cada denominación disponible en caja.
    </div>

    <section class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4" aria-label="Resumen de caja">
        @foreach ($summary as $item)
            <article class="rounded-[11px] border border-[#DDD] bg-white px-4 py-5 shadow-sm">
                <p class="text-[10px] text-[#7C7C7C]">{{ $item['title'] }}</p>
                <p class="mt-2 text-[15px] font-bold text-[#222]">{{ $item['value'] }}</p>
            </article>
        @endforeach
    </section>

    <form id="cashClosingForm" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
        <section class="overflow-hidden rounded-[12px] border border-[#DDD] bg-white p-5 shadow-sm">
            <div class="grid grid-cols-[1fr_85px_140px_145px] bg-[#F1F1F1] px-3 py-3 text-[9px] font-semibold text-[#555]">
                <span>Denominación</span><span>Tipo</span>
                <span class="text-center">Cantidad</span><span class="text-right">Sub-Total</span>
            </div>

            <div class="divide-y divide-[#ECECEC]">
                @foreach ($denominations as $item)
                    @if ($loop->first || $denominations[$loop->index - 1]['type'] !== $item['type'])
                        <div class="bg-[#F7F7F7] px-3 py-2 text-[9px] font-semibold text-[#555]">
                            {{ $item['type'] }}s
                        </div>
                    @endif

                    <div class="grid grid-cols-[1fr_85px_140px_145px] items-center gap-3 px-3 py-3">
                        <span class="text-[10px] font-semibold text-[#333]">{{ $item['label'] }}</span>
                        <span class="w-fit rounded bg-[#E4E8FF] px-2 py-1 text-[7px] font-medium text-[#6670C7]">
                            {{ $item['type'] }}
                        </span>
                        <input
                            type="text"
                            inputmode="numeric"
                            value="0"
                            data-denomination="{{ $item['value'] }}"
                            class="h-9 rounded-md border border-[#DDD] px-3 text-right text-[10px] outline-none focus:border-[#07839B]"
                        >
                        <output
                            data-line-total
                            class="h-9 rounded-md border border-[#E2E2E2] bg-[#FAFAFA] px-3 py-2 text-right text-[10px] text-[#555]"
                        >C$ 0.00</output>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-between border-t border-[#DDD] bg-[#F7F7F7] px-3 py-4 text-[11px] font-bold">
                <span>Total contado</span>
                <output id="countedTotal">C$ 0.00</output>
            </div>

            <label class="mt-5 block text-[9px] font-semibold text-[#555]">
                Observaciones (opcional)
                <textarea
                    id="closingNotes"
                    rows="3"
                    maxlength="500"
                    placeholder="Nota de cierre de caja..."
                    class="mt-2 w-full resize-none rounded-md border border-[#DDD] p-3 text-[9px] font-normal outline-none focus:border-[#07839B]"
                ></textarea>
            </label>

            <div class="mt-5 flex justify-end gap-3">
                <a
                    href="{{ route('dashboard') }}"
                    class="inline-flex h-10 items-center rounded-md border border-[#DDD] bg-[#F4F4F4] px-5 text-[9px] font-medium text-[#666]"
                >Guardar borrador</a>

                <button
                    type="submit"
                    data-submit
                    class="h-10 rounded-md bg-[#07839B] px-6 text-[9px] font-semibold text-white disabled:opacity-60"
                >Confirmar arqueo</button>
            </div>
        </section>

        <aside class="h-fit rounded-[12px] border border-[#DDD] bg-white p-5 shadow-sm">
            <h2 class="text-[12px] font-bold text-[#292929]">Conciliación</h2>
            <p class="mt-1 text-[8px] leading-4 text-[#888]">
                El saldo del sistema se revelará únicamente después del cierre.
            </p>

            <div id="reconciliationLocked" class="my-8 rounded-lg bg-[#F5F5F5] p-5 text-center">
                <p class="text-[22px]">🔒</p>
                <p class="mt-2 text-[9px] font-semibold text-[#555]">Arqueo ciego activo</p>
            </div>

            <dl id="reconciliationResult" class="hidden space-y-4 border-t border-[#EEE] pt-4 text-[9px]">
                <div class="flex justify-between"><dt>Saldo esperado</dt><dd id="expectedAmount" class="font-semibold">—</dd></div>
                <div class="flex justify-between"><dt>Dinero físico</dt><dd id="physicalAmount" class="font-semibold">—</dd></div>
                <div class="flex justify-between border-t border-[#EEE] pt-4"><dt>Diferencia</dt><dd id="cashDifference" class="font-bold">—</dd></div>
                <div id="closingStatus" class="rounded-md bg-[#F3F3F3] px-3 py-2 text-center font-semibold">—</div>
            </dl>
        </aside>
    </form>
</main>
@endsection
