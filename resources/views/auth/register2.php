<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Tienda - Gintly App</title>
    
    <!-- Fuente Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Carga de Assets unificada con la Arquitectura Base (Vite procesará Lucide y Tailwind) -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>
<body class="wizard-body">

    <div class="wizard-card-container">
        <!-- Panel Izquierdo: Hero Imagen Negocio -->
        <aside class="hero-sidebar">
            <div class="hero-overlay"></div>
            <img src="{{ asset('images/merceria-nadal.jpg') }}" alt="Mercería Nadal" class="hero-image">
            <div class="hero-caption">
                <h1 class="hero-heading">Proporciona la información correcta para la creación de tu tienda</h1>
                <p class="hero-text">Administra uno o varios perfiles de tu estado físico dentro del emprendimiento, manteniendo un control y una gestión integral de todos los elementos de cada tienda física.</p>
            </div>
        </aside>

        <!-- Panel Derecho: Formulario Paso 2 -->
        <main class="wizard-form-area">
            <header class="form-header">
                <a href="{{ route('register.step2') }}" class="back-btn" aria-label="Volver">
                    <i data-lucide="arrow-left"></i>
                </a>
                <div class="brand-logo">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                        <path d="M20 4L4 12L20 20L36 12L20 4Z" stroke="#0e7490" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 20L20 28L36 20" stroke="#0e7490" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 28L20 36L36 28" stroke="#0e7490" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </header>

            <section class="intro-section">
                <h2 class="form-title">Completa el perfil de tu tienda</h2>
                <p class="form-subtitle">Proporciona la información de tu tienda para crear un perfil completo y personalizado. Estos datos nos permitirán organizar mejor tu negocio.</p>
            </section>

            <!-- Stepper Component -->
            <div class="stepper-nav">
                <div class="step-item completed">
                    <div class="step-badge check-badge"><i data-lucide="check"></i></div>
                    <div class="step-info">
                        <strong>Configuración de tu Perfil</strong>
                        <small>Moldea el perfil de tu rol en el negocio</small>
                    </div>
                </div>
                <div class="step-line active-line"></div>
                <div class="step-item active">
                    <div class="step-badge">02</div>
                    <div class="step-info">
                        <strong>Configura el espacio de tu negocio</strong>
                        <small>Haz que la plataforma se adapte a tu negocio</small>
                    </div>
                </div>
                <div class="step-line"></div>
                <div class="step-item disabled">
                    <div class="step-badge">03</div>
                    <div class="step-info">
                        <strong>Preferencias regionales</strong>
                        <small>Define de dónde eres, moneda usada y zona horaria</small>
                    </div>
                </div>
                <div class="step-line"></div>
                <div class="step-item disabled">
                    <div class="step-badge">04</div>
                    <div class="step-info">
                        <strong>Creación de usuarios</strong>
                        <small>Define el rol de tus empleados</small>
                    </div>
                </div>
            </div>

            <!-- Formulario Paso 2 -->
            <form action="{{ route('register.step2.store') }}" method="POST" class="registration-form" novalidate>
                @csrf

                <div class="form-row">
                    <!-- Nombre de la tienda -->
                    <div class="field-group @error('store_name') state-error @elseif(old('store_name')) state-success @enderror">
                        <label for="store_name">Nombre de la tienda</label>
                        <div class="input-wrapper">
                            <input type="text" id="store_name" name="store_name" value="{{ old('store_name') }}" placeholder="Ejemplo: María José">
                            @error('store_name')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('store_name'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('store_name') ? 'alert-circle' : (old('store_name') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('store_name') ?? (old('store_name') ? 'Acción realizada' : 'Es de carácter obligatorio') }}</span>
                        </div>
                    </div>

                    <!-- País o región -->
                    <div class="field-group @error('country') state-error @elseif(old('country')) state-success @enderror">
                        <label for="country">País o región</label>
                        <div class="input-wrapper">
                            <input type="text" id="country" name="country" value="{{ old('country') }}" placeholder="Ejemplo: Nicaragua">
                            @error('country')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('country'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('country') ? 'alert-circle' : (old('country') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('country') ?? (old('country') ? 'Acción realizada' : 'Es de carácter obligatorio') }}</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Ciudad -->
                    <div class="field-group @error('city') state-error @elseif(old('city')) state-success @enderror">
                        <label for="city">Ciudad</label>
                        <div class="input-wrapper">
                            <input type="text" id="city" name="city" value="{{ old('city') }}" placeholder="Ejemplo: Estelí">
                            @error('city')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('city'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('city') ? 'alert-circle' : (old('city') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('city') ?? (old('city') ? 'Acción realizada' : 'Es de carácter obligatorio') }}</span>
                        </div>
                    </div>

                    <!-- Código postal -->
                    <div class="field-group @error('postal_code') state-error @elseif(old('postal_code')) state-success @enderror">
                        <label for="postal_code">Código postal</label>
                        <div class="input-wrapper">
                            <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code') }}" placeholder="Ejemplo: 31000">
                            @error('postal_code')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('postal_code'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('postal_code') ? 'alert-circle' : (old('postal_code') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('postal_code') ?? (old('postal_code') ? 'Acción realizada' : 'Es de carácter obligatorio') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Dirección -->
                <div class="field-group @error('address') state-error @elseif(old('address')) state-success @enderror">
                    <label for="address">Dirección</label>
                    <div class="input-wrapper">
                        <input type="text" id="address" name="address" value="{{ old('address') }}" placeholder="Ejemplo: Barrio Central, Managua, Nicaragua">
                        @error('address')
                            <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                        @elseif(old('address'))
                            <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                        @else
                            <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                        @enderror
                    </div>
                    <div class="field-hint">
                        <i data-lucide="{{ $errors->has('address') ? 'alert-circle' : (old('address') ? 'check' : 'info') }}"></i>
                        <span>{{ $errors->first('address') ?? (old('address') ? 'Acción realizada' : 'Es de carácter obligatorio') }}</span>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Correo electrónico de la tienda -->
                    <div class="field-group @error('store_email') state-error @elseif(old('store_email')) state-success @enderror">
                        <label for="store_email">Correo electrónico de la tienda</label>
                        <div class="input-wrapper">
                            <i data-lucide="mail" class="icon-prefix"></i>
                            <input type="email" id="store_email" name="store_email" value="{{ old('store_email') }}" placeholder="Ejemplo: contacto@ferreteria.com">
                            @error('store_email')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('store_email'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('store_email') ? 'alert-circle' : (old('store_email') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('store_email') ?? (old('store_email') ? 'Proceso completado' : 'Opcional, pero recomendado como contacto') }}</span>
                        </div>
                    </div>

                    <!-- Número convencional -->
                    <div class="field-group @error('conventional_phone') state-error @elseif(old('conventional_phone')) state-success @enderror">
                        <label for="conventional_phone">Número convencional</label>
                        <div class="input-wrapper">
                            <i data-lucide="phone" class="icon-prefix"></i>
                            <input type="tel" id="conventional_phone" name="conventional_phone" value="{{ old('conventional_phone') }}" placeholder="Número de teléfono">
                            @error('conventional_phone')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('conventional_phone'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('conventional_phone') ? 'alert-circle' : (old('conventional_phone') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('conventional_phone') ?? (old('conventional_phone') ? 'Proceso completado' : 'Opcional, pero recomendado como contacto') }}</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Número de sucursales -->
                    <div class="field-group @error('branches_count') state-error @elseif(old('branches_count')) state-success @enderror">
                        <label for="branches_count">Número de sucursales</label>
                        <div class="input-wrapper">
                            <input type="number" id="branches_count" name="branches_count" value="{{ old('branches_count', 0) }}" min="0">
                            @error('branches_count')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('branches_count'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <!-- Spinner indicators match -->
                                <div class="number-spinners">
                                    <i data-lucide="chevron-up"></i>
                                    <i data-lucide="chevron-down"></i>
                                </div>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('branches_count') ? 'alert-circle' : (old('branches_count') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('branches_count') ?? (old('branches_count') ? 'Acción finalizada' : 'Obligatorio para entender la capacidad de tu negocio.') }}</span>
                        </div>
                    </div>

                    <!-- Ruc o identificación Fiscal -->
                    <div class="field-group @error('tax_id') state-error @elseif(old('tax_id')) state-success @enderror">
                        <label for="tax_id">Ruc o identificación Fiscal</label>
                        <div class="input-wrapper">
                            <i data-lucide="file-text" class="icon-prefix"></i>
                            <input type="text" id="tax_id" name="tax_id" value="{{ old('tax_id') }}" placeholder="Ejemplo: J0310000123456">
                            @error('tax_id')
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            @elseif(old('tax_id'))
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            @else
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            @enderror
                        </div>
                        <div class="field-hint">
                            <i data-lucide="{{ $errors->has('tax_id') ? 'alert-circle' : (old('tax_id') ? 'check' : 'info') }}"></i>
                            <span>{{ $errors->first('tax_id') ?? (old('tax_id') ? 'Acción realizada' : 'Es de carácter obligatorio') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Botón Submit -->
                <button type="submit" class="submit-btn {{ $errors->any() ? 'btn-disabled' : (old() ? 'btn-active' : 'btn-disabled') }}">
                    Ingresar
                </button>
            </form>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>