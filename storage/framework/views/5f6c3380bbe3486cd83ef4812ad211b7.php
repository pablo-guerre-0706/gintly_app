<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Gintly App</title>
    
    <!-- Fuente Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Carga de Assets unificada con la Arquitectura Base -->
    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/js/app.js'
    ]); ?>
</head>
<body class="wizard-body">

    <div class="wizard-card-container">
        <!-- Panel Izquierdo: Imagen Hero con Gradiente -->
        <aside class="hero-sidebar">
            <div class="hero-overlay"></div>
            <img src="<?php echo e(asset('images/business-partners.jpg')); ?>" alt="Gintly Governance" class="hero-image">
            <div class="hero-caption">
                <h1 class="hero-heading">Gestiona a distancia.<br>Controla tus finanzas.<br>Protege tu patrimonio.</h1>
                <p class="hero-text">Desde el control de inventario hasta revisiones automáticas, nuestra plataforma te da gobernanza absoluta sobre tu negocio desde cualquier parte del mundo.</p>
            </div>
        </aside>

        <!-- Panel Derecho: Formulario del Wizard -->
        <main class="wizard-form-area">
            <!-- Header superior con botón atrás y Logo -->
            <header class="form-header">
                <a href="<?php echo e(route('landing')); ?>" class="back-btn" aria-label="Volver">
                    <i data-lucide="arrow-left"></i>
                </a>
                <div class="brand-logo">
                    <!-- SVG Logo Gintly -->
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                        <path d="M20 4L4 12L20 20L36 12L20 4Z" stroke="#0e7490" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 20L20 28L36 20" stroke="#0e7490" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 28L20 36L36 28" stroke="#0e7490" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </header>

            <!-- Título y Descripción -->
            <section class="intro-section">
                <h2 class="form-title">Bienvenido a Gintly app</h2>
                <p class="form-subtitle">Crea tu perfil en Gintly y comienza a gestionar tu negocio de forma eficiente con una plataforma diseñada por el equipo de Journey Map para optimizar y fortalecer la administración de tu empresa.</p>
            </section>

            <!-- Stepper Component -->
            <div class="stepper-nav">
                <div class="step-item active">
                    <div class="step-badge">01</div>
                    <div class="step-info">
                        <strong>Configuración de tu Perfil</strong>
                        <small>Moldea el perfil de tu rol en el negocio</small>
                    </div>
                </div>
                <div class="step-line"></div>
                <div class="step-item disabled">
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

            <!-- Formulario de Registro -->
            <form class="registration-form" novalidate 
                data-msg-success-user="<?php echo app('translator')->get('messages.user_created'); ?>" 
                data-msg-success-account="<?php echo app('translator')->get('messages.account_created'); ?>"
                data-msg-error="<?php echo app('translator')->get('messages.register_error'); ?>">
                <?php echo csrf_field(); ?>

                <div class="form-row">
                    <!-- Nombres -->
                    <div class="field-group <?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('first_name')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="first_name">Nombres</label>
                        <div class="input-wrapper">
                            <input type="text" id="first_name" name="first_name" value="<?php echo e(old('first_name')); ?>" placeholder="Ejemplo: María José">
                            <?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('first_name')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('first_name') ? 'alert-circle' : (old('first_name') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('first_name') ?? (old('first_name') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>

                    <!-- Apellidos -->
                    <div class="field-group <?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('last_name')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="last_name">Apellidos</label>
                        <div class="input-wrapper">
                            <input type="text" id="last_name" name="last_name" value="<?php echo e(old('last_name')); ?>" placeholder="Ejemplo: Cruz Valdivia">
                            <?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('last_name')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('last_name') ? 'alert-circle' : (old('last_name') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('last_name') ?? (old('last_name') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Correo electrónico -->
                    <div class="field-group <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('email')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="email">Correo electrónico</label>
                        <div class="input-wrapper">
                            <i data-lucide="mail" class="icon-prefix"></i>
                            <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" placeholder="Ejemplo: mariajosecruz21@gmail.com">
                            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('email')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('email') ? 'alert-circle' : (old('email') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('email') ?? (old('email') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>

                    <!-- Número de teléfono -->
                    <div class="field-group <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('phone')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="phone">Número de teléfono</label>
                        <div class="input-wrapper phone-wrapper">
                            <select name="phone_prefix" class="prefix-select">
                                <option value="+505">N°</option>
                            </select>
                            <i data-lucide="phone" class="icon-prefix"></i>
                            <input type="tel" id="phone" name="phone" value="<?php echo e(old('phone')); ?>" placeholder="Número de teléfono">
                            <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('phone')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('phone') ? 'alert-circle' : (old('phone') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('phone') ?? (old('phone') ? 'Proceso completado' : 'Opcional, pero recomendado como contacto')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="field-group <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('password')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <i data-lucide="eye" class="icon-prefix"></i>
                        <input type="password" id="password" name="password" placeholder="Ejemplo: 1234.team1">
                        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                        <?php elseif(old('password')): ?>
                            <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                        <?php else: ?>
                            <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="field-hint">
                        <i data-lucide="<?php echo e($errors->has('password') ? 'alert-circle' : (old('password') ? 'check' : 'info')); ?>"></i>
                        <span><?php echo e($errors->first('password') ?? 'Es de carácter obligatorio / es necesario por lo menos 12 caracteres usar letras y números'); ?></span>
                    </div>
                </div>

                <!-- Valida la contraseña -->
                <div class="field-group <?php $__errorArgs = ['password_confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('password_confirmation')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <label for="password_confirmation">Valida la contraseña</label>
                    <div class="input-wrapper">
                        <i data-lucide="eye" class="icon-prefix"></i>
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Ejemplo: 1234.team1">
                        <?php $__errorArgs = ['password_confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                        <?php elseif(old('password_confirmation')): ?>
                            <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                        <?php else: ?>
                            <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="field-hint">
                        <i data-lucide="<?php echo e($errors->has('password_confirmation') ? 'alert-circle' : (old('password_confirmation') ? 'check' : 'info')); ?>"></i>
                        <span><?php echo e($errors->first('password_confirmation') ?? 'Confirma si la contraseña es correcta'); ?></span>
                    </div>
                </div>
                <!-- Botón Submit Dinámico -->
                <button type="submit" class="submit-btn btn-disabled" disabled>
                    Siguiente
                </button>
            </form>
        </main>
    </div>
    <script>
        lucide.createIcons();
    </script>
    <?php echo app('Illuminate\Foundation\Vite')('resources/js/modules/security/auth.js'); ?>
</body>
</html>
<?php /**PATH C:\laragon\www\gintly_app\resources\views/signupprofile.blade.php ENDPATH**/ ?>