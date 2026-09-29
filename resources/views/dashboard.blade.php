@extends('layouts.panel')

@section('document-title', 'Panel')
@section('page-title', 'Panel')
@section('breadcrumb-root', 'Gintly')
@section('breadcrumb-current', 'Panel')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white px-6 py-10 shadow-sm sm:px-8 lg:px-10" aria-labelledby="dashboard-intro-title">
        <div class="max-w-2xl">
            <span class="inline-flex size-12 items-center justify-center rounded-2xl bg-gintly-active text-gintly-sidebar" aria-hidden="true">
                <i class="fa-solid fa-border-all"></i>
            </span>
            <h1 id="dashboard-intro-title" class="mt-5 text-2xl font-semibold tracking-tight text-gintly-text-primary sm:text-3xl">
                Panel de Gintly
            </h1>
            <p class="mt-3 text-sm leading-6 text-gintly-text-secondary sm:text-base">
                El contexto de la cuenta y la navegación autorizada ya están disponibles. El contenido directivo, administrativo u operativo se incorporará en la siguiente etapa según el rol y las capacidades efectivas.
            </p>
        </div>
    </section>
@endsection
