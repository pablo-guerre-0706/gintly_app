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
    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/js/app.js'
    ]); ?>
</head>
<body class="wizard-body">

    <div class="wizard-card-container">
        <!-- Panel Izquierdo: Hero Imagen Negocio -->
        <aside class="hero-sidebar">
            <div class="hero-overlay"></div>
            <img src="<?php echo e(asset('images/merceria-nadal.jpg')); ?>" alt="Mercería Nadal" class="hero-image">
            <div class="hero-caption">
                <h1 class="hero-heading">Proporciona la información correcta para la creación de tu tienda</h1>
                <p class="hero-text">Administra uno o varios perfiles de tu estado físico dentro del emprendimiento, manteniendo un control y una gestión integral de todos los elementos de cada tienda física.</p>
            </div>
        </aside>

    <!-- Panel Derecho: Formulario Paso 2 -->
        <main class="wizard-form-area">
            <header class="form-header">
                <!-- Enlace único y corregido que regresa al Paso 1 -->
                <a href="<?php echo e(route('register.step1')); ?>" class="back-btn" aria-label="Volver a datos personales">
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
            <form class="registration-form" novalidate 
                data-msg-success-user="<?php echo app('translator')->get('messages.user_created'); ?>" 
                data-msg-success-account="<?php echo app('translator')->get('messages.account_created'); ?>"
                data-msg-error="<?php echo app('translator')->get('messages.register_error'); ?>">
                <?php echo csrf_field(); ?>

                <div class="form-row">
                    <!-- Nombre de la tienda -->
                    <div class="field-group <?php $__errorArgs = ['store_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('store_name')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="store_name">Nombre de la tienda</label>
                        <div class="input-wrapper">
                            <input type="text" id="store_name" name="store_name" value="<?php echo e(old('store_name')); ?>" placeholder="Ejemplo: María José">
                            <?php $__errorArgs = ['store_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('store_name')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('store_name') ? 'alert-circle' : (old('store_name') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('store_name') ?? (old('store_name') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>

                    <!-- País o región -->
                    <div class="field-group <?php $__errorArgs = ['country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('country')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="country">País o región</label>
                        <div class="input-wrapper">
                            <input type="text" id="country" name="country" value="<?php echo e(old('country')); ?>" placeholder="Ejemplo: Nicaragua">
                            <?php $__errorArgs = ['country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('country')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('country') ? 'alert-circle' : (old('country') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('country') ?? (old('country') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Ciudad -->
                    <div class="field-group <?php $__errorArgs = ['city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('city')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="city">Ciudad</label>
                        <div class="input-wrapper">
                            <input type="text" id="city" name="city" value="<?php echo e(old('city')); ?>" placeholder="Ejemplo: Estelí">
                            <?php $__errorArgs = ['city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('city')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('city') ? 'alert-circle' : (old('city') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('city') ?? (old('city') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>

                    <!-- Código postal -->
                    <div class="field-group <?php $__errorArgs = ['postal_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('postal_code')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="postal_code">Código postal</label>
                        <div class="input-wrapper">
                            <input type="text" id="postal_code" name="postal_code" value="<?php echo e(old('postal_code')); ?>" placeholder="Ejemplo: 31000">
                            <?php $__errorArgs = ['postal_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('postal_code')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('postal_code') ? 'alert-circle' : (old('postal_code') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('postal_code') ?? (old('postal_code') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Dirección -->
                <div class="field-group <?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('address')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <label for="address">Dirección</label>
                    <div class="input-wrapper">
                        <input type="text" id="address" name="address" value="<?php echo e(old('address')); ?>" placeholder="Ejemplo: Barrio Central, Managua, Nicaragua">
                        <?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                        <?php elseif(old('address')): ?>
                            <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                        <?php else: ?>
                            <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="field-hint">
                        <i data-lucide="<?php echo e($errors->has('address') ? 'alert-circle' : (old('address') ? 'check' : 'info')); ?>"></i>
                        <span><?php echo e($errors->first('address') ?? (old('address') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Correo electrónico de la tienda -->
                    <div class="field-group <?php $__errorArgs = ['store_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('store_email')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="store_email">Correo electrónico de la tienda</label>
                        <div class="input-wrapper">
                            <i data-lucide="mail" class="icon-prefix"></i>
                            <input type="email" id="store_email" name="store_email" value="<?php echo e(old('store_email')); ?>" placeholder="Ejemplo: contacto@ferreteria.com">
                            <?php $__errorArgs = ['store_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('store_email')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('store_email') ? 'alert-circle' : (old('store_email') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('store_email') ?? (old('store_email') ? 'Proceso completado' : 'Opcional, pero recomendado como contacto')); ?></span>
                        </div>
                    </div>

                    <!-- Número convencional -->
                    <div class="field-group <?php $__errorArgs = ['conventional_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('conventional_phone')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="conventional_phone">Número convencional</label>
                        <div class="input-wrapper">
                            <i data-lucide="phone" class="icon-prefix"></i>
                            <input type="tel" id="conventional_phone" name="conventional_phone" value="<?php echo e(old('conventional_phone')); ?>" placeholder="Número de teléfono">
                            <?php $__errorArgs = ['conventional_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('conventional_phone')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('conventional_phone') ? 'alert-circle' : (old('conventional_phone') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('conventional_phone') ?? (old('conventional_phone') ? 'Proceso completado' : 'Opcional, pero recomendado como contacto')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Número de sucursales -->
                    <div class="field-group <?php $__errorArgs = ['branches_count'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('branches_count')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="branches_count">Número de sucursales</label>
                        <div class="input-wrapper">
                            <input type="number" id="branches_count" name="branches_count" value="<?php echo e(old('branches_count', 0)); ?>" min="0">
                            <?php $__errorArgs = ['branches_count'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('branches_count')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <!-- Spinner indicators match -->
                                <div class="number-spinners">
                                    <i data-lucide="chevron-up"></i>
                                    <i data-lucide="chevron-down"></i>
                                </div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('branches_count') ? 'alert-circle' : (old('branches_count') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('branches_count') ?? (old('branches_count') ? 'Acción finalizada' : 'Obligatorio para entender la capacidad de tu negocio.')); ?></span>
                        </div>
                    </div>

                    <!-- Ruc o identificación Fiscal -->
                    <div class="field-group <?php $__errorArgs = ['tax_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> state-error <?php elseif(old('tax_id')): ?> state-success <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label for="tax_id">Ruc o identificación Fiscal</label>
                        <div class="input-wrapper">
                            <i data-lucide="file-text" class="icon-prefix"></i>
                            <input type="text" id="tax_id" name="tax_id" value="<?php echo e(old('tax_id')); ?>" placeholder="Ejemplo: J0310000123456">
                            <?php $__errorArgs = ['tax_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <i data-lucide="alert-circle" class="icon-status icon-error"></i>
                            <?php elseif(old('tax_id')): ?>
                                <i data-lucide="check-circle-2" class="icon-status icon-success"></i>
                            <?php else: ?>
                                <i data-lucide="x-circle" class="icon-status icon-clear"></i>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="field-hint">
                            <i data-lucide="<?php echo e($errors->has('tax_id') ? 'alert-circle' : (old('tax_id') ? 'check' : 'info')); ?>"></i>
                            <span><?php echo e($errors->first('tax_id') ?? (old('tax_id') ? 'Acción realizada' : 'Es de carácter obligatorio')); ?></span>
                        </div>
                    </div>
                </div>
                <!-- Botón Submit -->
                <button type="submit" class="submit-btn btn-disabled" disabled>
                    Crear Cuenta
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
<?php /**PATH C:\laragon\www\gintly_app\resources\views/signupbusinessprofile.blade.php ENDPATH**/ ?>