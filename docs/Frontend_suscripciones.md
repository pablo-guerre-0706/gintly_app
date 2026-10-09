# MOD-SUB — frontend de suscripción y contratación

## Alcance y autoridad

Entrega sobre el registro canónico y el Backend MOD-SUB integrados. No modifica catálogo, pasarela, Services, FormRequests, Resources, API, reglas de acceso o migraciones. Los dos archivos PHP nuevos son adaptadores de presentación web: reutilizan `BillingController::plans()` y `CancelSubscriptionRequest::authorize()`; no toman decisiones de cobro.

La aceptación local utiliza el backend real con sesión/CSRF, su base QA y el doble **existente** `Tests\Support\FakeSubscriptionGateway`, exclusivamente desde el router de pruebas bajo `tests`. No representa un pago real en Lemon Squeezy. TEST real y habilitación LIVE son etapas externas pendientes.

## Recorrido y rutas

Landing → preferencia de plan/periodicidad → registro canónico → login manual → `/billing/access` → contratación o dashboard → checkout HTTPS alojado → `/billing/return` → estado confirmado por backend → panel permitido por rol/perfil/sucursal.

| Web | Presentación / protección |
| --- | --- |
| `/`, `/landing` | Catálogo público renderizado desde el colaborador Backend; no publica la API autenticada. |
| `/register` | Cuenta → Negocio (incluye zona horaria) → Revisión → Resultado. Request canónico intacto, sin campos comerciales ni auto-login. |
| `/login` | Contrato existente; contraseña preservada; salida hacia `web.billing.access`. No añade `remember`. |
| `/billing` | Gestión o consulta. `auth` + `EnsureOperableUser`; fuera de `subscription.active`. Nombre `web.billing.index`. |
| `/billing/access` | `/me` compartido + estado comercial antes del panel. Nombre `web.billing.access`. |
| `/billing/return` | Solo lee estado, no activa ni cancela. Nombre `web.billing.return`. |
| Panel existente | Conserva `auth`, operabilidad y `subscription.active`. Ante restricción HTML presenta 403 con salida clara; JSON conserva el rechazo del Backend. |

| API `/api/v1` | Request | Respuesta |
| --- | --- | --- |
| GET `/billing/plans` | Sin cuerpo | 200 `data[]`: key/name/currency NIO/prices/limits/features. |
| GET `/billing/subscription` | Sin cuerpo | 200 `data`: status/grants_access/plan_key/period/paid_until y campos opcionales del Resource. |
| POST `/billing/checkout` | Exactamente `{plan,period}` + `Idempotency-Key` UUID | 201 `data`: checkout_url/plan_key/period/status/expires_at. |
| POST `/billing/subscription/change` | Exactamente `{plan,period}` | 200 `SubscriptionStatusResource`. |
| POST `/billing/subscription/cancel` | Sin cuerpo adicional | 200 `SubscriptionStatusResource`; no borra recursos. |

Los controles mutantes se renderizan solo si la autorización Backend del propietario real activo permite gestionarlos. ROL-02/ROL-03 consultan estado y reciben la indicación de contactar al propietario. Las Policies/FormRequests Backend continúan siendo autoridad, incluso si se manipula el DOM.

## Catálogo y precios

El SSR deriva el catálogo de `BillingController::plans`, y la pantalla autenticada lo lee de `/billing/plans`. JavaScript no contiene precios de producción. Periodicidades `monthly` y `annual`; anualidad completa por adelantado, sin descuento. “Cajas simultáneas” describe sesiones, no número de cajas registradas. Se retiraron el trial de siete días, equivalencias USD y promociones de las tarjetas anteriores.

El importe publicado es NIO; la moneda y el importe cobrables los muestra Lemon Squeezy antes de confirmar. No usa tasas del ERP, SDK de tarjetas ni checkout incrustado.

## Estado, seguridad y recuperación

- Una promesa `/me` por documento. El `business.id` real se conserva como contexto para vincular intentos; nunca se envía como selector del tenant.
- Solo `grants_access` informa la presentación. No deduce acceso de status, `businesses.plan`, fechas o URLs. Las compuertas HTTP siguen vigentes.
- Estados: none, pending_payment, incomplete, active, past_due, canceled y expired. Se muestran vigencia, renovación y cambio pendiente cuando el Resource los expone.
- UUID criptográfico y snapshot congelado de `{plan,period}` por intento lógico. Bloqueo síncrono antes de CSRF/Fetch. Un resultado incierto no permite editar ese snapshot. Replay explícito con la misma clave. 422 libera el intento para corrección; clave vencida exige iniciar otro explícitamente.
- `sessionStorage` contiene únicamente preferencia y UUID/selección vinculada al negocio. No persiste URL de checkout, proveedor, claves externas, contraseñas, cookies ni tokens. Un intento vinculado a otro negocio se descarta junto con su preferencia; logout limpia ambos. La preferencia pública inicial no es autorización comercial.
- Solo una respuesta 201 completa, coherente con la selección y con URL HTTPS sin credenciales permite navegar. Tener URL no significa pago.
- Checkout: 419 tiene una sola renovación/repetición idéntica. El cliente HTTP central no repite solicitudes. No hay reenvío automático por red/timeout/500/503.
- Cambios/cancelación requieren diálogo explícito; Escape, foco, Tab/Shift+Tab y scroll lock compartidos. No calcula prorrateo ni cambia optimistamente el plan. Un resultado de gestión incierto exige actualizar estado antes de modificar otra vez.
- Retorno: máximo 12 consultas por ciclo, separadas por cinco segundos, sin solapamiento; pausa oculta, aborta al salir. Al agotarse conserva confirmación pendiente y ofrece actualizar. No llama webhook/reconciliación.
- 429 respeta Retry-After; ausencia del header utiliza una espera conservadora. No crea bucles.
- `SUBSCRIPTION_REQUIRED` y `PLAN_FEATURE_UNAVAILABLE` tienen recuperación central. Otros 403 no se confunden con falta de pago. Logout permanece accesible.
- Errores: CHECKOUT_IN_PROGRESS, CHECKOUT_KEY_EXPIRED, CHECKOUT_RESULT_UNKNOWN, CHECKOUT_IDEMPOTENCY_CONFLICT, SUBSCRIPTION_ALREADY_ACTIVE, NO_ACTIVE_SUBSCRIPTION, PLAN_CHANGE_INVALID, PLAN_LIMIT_EXCEEDED, BILLING_UNAVAILABLE, LIMIT_CHECK_UNAVAILABLE; 401/403/404/409/419/422/429/500/503/red/contrato ilegible. Mensajes seguros como texto; 422 asocia campos y enfoca después de desbloquearlos.

## Validación reproducible

Requisitos: proyecto Backend/registro canónico integrado, Composer con dependencias de desarrollo para ejecutar QA, PHP 8.3 con extensiones del proyecto, MySQL local, Node 22.12+ (se utilizó 24.19.0), npm del entorno, Chrome instalado (se utilizó 154.0.8037.93) y Playwright externo 1.62.1. No requiere paquetes de producción nuevos ni binarios/perfiles personales dentro del repositorio.

Helpers permanentes de esta entrega: `tests/frontend/subscription-browser.mjs`, `subscription-browser-db.php`, `subscription-browser-router.php`, `subscription-qa-guard.php` y el runtime compartido `registration-browser-runtime.mjs`. Dependencias previas, presentes en el baseline integrado y sin cambios en esta fase: `tests/frontend/registration-browser-db.php` (preflight de solo lectura) y `tests/Support/FakeSubscriptionGateway.php` (doble Backend existente; necesita autoload de desarrollo). No depende de un helper fuente omitido bajo `storage/app/qa`, perfiles preexistentes ni rutas personales; las rutas externas se proporcionan explícitamente mediante variables QA.

Si no hay Playwright, instalarlo en un directorio temporal de herramientas **fuera de la aplicación**, por ejemplo con `npm install --prefix <directorio-QA-externo> --no-save playwright@1.62.1`, y apuntar al `index.mjs` instalado. Chrome puede ser el binario existente; no es necesario incluirlo ni descargarlo en el proyecto.

Desde la raíz de la copia, PowerShell:

```powershell
$env:QA_REGISTRATION_BROWSER = '1'
$env:QA_SUBSCRIPTION_BROWSER = '1'
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8840'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'gintly_frontend_qa_rol03'
$env:DB_URL = ''
$env:DB_SOCKET = ''
$env:SESSION_DRIVER = 'file'
$env:SESSION_DOMAIN = ''
$env:SESSION_CONNECTION = ''
$env:SESSION_SECURE_COOKIE = 'false'
$env:CACHE_STORE = 'file'
$env:SANCTUM_STATEFUL_DOMAINS = '127.0.0.1:8840'
$env:QA_PHP = (Get-Command php).Source
$env:QA_PLAYWRIGHT_MODULE = '<ruta-absoluta-externa>/node_modules/playwright/index.mjs'
$env:QA_CHROME = '<ruta-absoluta-al-chrome-instalado>/chrome.exe'

# Solo las cuatro migraciones progresivas existentes, con guardas Laravel/PDO previas.
php tests/frontend/subscription-browser-db.php migrate
php tests/frontend/subscription-browser-db.php preflight
node --test tests/frontend/*.test.mjs
npm run build
php artisan view:cache
php artisan route:cache
php artisan route:list --path=billing
php tests/frontend/render-registration.php
php tests/frontend/render-billing.php
node tests/frontend/subscription-browser.mjs
```

En el entorno Codex npm no está en PATH; se ejecutó el equivalente `node C:/laragon/bin/nodejs/node-v22/node_modules/npm/bin/npm-cli.js run build`. No es una ruta requerida del arnés: en el destino se utiliza su propio `npm run build`.

El arnés verifica entorno explícito, Laravel resuelto y `SELECT DATABASE()` de PDO, sesiones/caché, URL exacta, schema, herramientas, manifest, ausencia de `public/hot` y puerto libre. Posee el proceso PHP; nunca se acopla a un servidor desconocido. No deshabilita CSRF ni la compuerta. El router QA usa el doble existente solo tras esa guarda, sin introducir una ruta de activación en la aplicación.

Si existe una configuración cacheada incompatible, la guarda rechaza la ejecución antes de escribir; no la elude ni cambia `.env`. El responsable del entorno debe preparar su configuración QA y volver a ejecutar el preflight. Las mutaciones del navegador también comprueban que la página siga en el origen QA, nunca en el checkout alojado.

`subscription-browser-db.php` tiene acciones CLI preflight/migrate/activate/expire-key/expire-subscription/counts/cleanup. Las acciones de fixtures exigen el token exacto `QA-REGISTER-MODSUB-<12 hex>`. La limpieza se limita al negocio nuevo y sus filas QA; no borra datos anteriores. Ante error se conserva identificación del token y falla de forma segura. El fixture anterior `QA-REGISTER-6defd52506e9` sigue identificado y no pertenece a esta limpieza.

Las capturas, evidencia sanitizada y perfiles propios se generan en `storage/app/qa/subscription-browser/<token>`; el perfil se elimina al finalizar, también ante fallo. No genera HAR, trazas, logs de cuerpo, cookies ni credenciales. El doble tiene destino HTTPS `checkout.test`, interceptado solo por el navegador de QA. La fixture comercial activa es explícitamente local, no un pago del proveedor.

La prueba del transporte `php vendor/bin/phpunit tests/Feature/ModSub/LemonSqueezyGatewayTest.php` no necesita base de datos: usa Http::fake. No se fuerza `SubscriptionHttpTest` ni otras suites MySQL con allowlist `gintly_backend_claude` a esta base; no cuentan como aprobadas/omitidas del presente run.

## Resultados de cierre

**APTO PARA INTEGRACIÓN FRONTEND — ACEPTACIÓN LOCAL.** No comprende pago real TEST ni habilitación LIVE.

Ejecución final: `QA-REGISTER-MODSUB-eddf1bfb05de`, Chrome real `154.0.8037.93`, URL `http://127.0.0.1:8840`, base `gintly_frontend_qa_rol03`. `node tests/frontend/subscription-browser.mjs` terminó con código **0**, **85 comprobaciones**, **21 escenarios inducidos** identificados y **42 capturas**. Evidencia sanitizada: `storage/app/qa/subscription-browser/QA-REGISTER-MODSUB-eddf1bfb05de/evidence.json`; no es fuente integrable.

| Evidencia con Backend real en QA | Resultado |
| --- | --- |
| Landing SSR → registro canónico → login manual | Registro 201; payload sin plan/period/pago; sin auto-login; login 200. Preferencia mensual/anual conservada. |
| Propietario sin suscripción | Estado 200 sin acceso; panel HTML 403 con salida a contratación; API operativa mantiene JSON `SUBSCRIPTION_REQUIRED`. |
| Checkout con doble de proveedor aislado | 201; `{plan,period}` exacto, UUID y CSRF; doble envío bloqueado. No representa pago Lemon Squeezy. |
| Respuesta perdida después de un 201 real | Se descartó la respuesta procesada, no el procesamiento Backend. Replay con misma clave/snapshot; misma URL y una sola fila de checkout. |
| Checkout abierto y misma clave con datos distintos | 409 `CHECKOUT_IN_PROGRESS` y 409 `CHECKOUT_IDEMPOTENCY_CONFLICT`; no se eluden con claves nuevas. |
| Clave vencida | 409 `CHECKOUT_KEY_EXPIRED`; nuevo intento solo mediante acción explícita. |
| Retorno sin pago | Sigue pendiente sin acceso. Ciclo detenido después de 12 respuestas reales; reloj acelerado únicamente en QA, identificado como inducido. Intervalo productivo de cinco segundos verificado por prueba de poller. |
| Suscripción vigente mediante fixture local | `grants_access=true`; dashboards ROL-01, ROL-02 y ROL-03 autorizados y correctos. No se cambia la compuerta de la aplicación. |
| Cambio de plan | 200; descenso pendiente con fecha del Backend, sin actualización optimista. |
| Descenso incompatible con uso | 409 `PLAN_LIMIT_EXCEEDED` real con dos sucursales QA; muestra recurso/límite y no elimina recursos. |
| Cancelar renovación | 200; acceso conservado durante el período pagado comunicado por Backend. |
| ROL-02 y ROL-03 | Vista de solo lectura sin controles propietarios; POST comercial rechazado con 403 real. |
| Pasarela sin clave configurada | 503 `BILLING_UNAVAILABLE` real; mensaje recuperable, sin éxito ficticio ni reintento automático. |
| Logout y otro negocio QA | Logout 204; se limpia selección/intento. Segundo registro 201, sin heredar acceso ni intento del negocio previo. |

Los **21 escenarios inducidos** cubren fallo inicial de `/me`, catálogo y estado (503) con recuperación explícita; aceleración del reloj del ciclo; 422 y foco; códigos de checkout/estado/autorización enumerados; 429 con espera Retry-After; 500; un 419 con una sola renovación y máximo dos envíos idénticos; y 201 ilegible sin éxito. La red interrumpida tras el 201 real se verifica aparte: el Backend sí persistió el intento antes del replay. No se presenta una respuesta interceptada ni la fixture activa como confirmación del proveedor real.

Presentación comprobada en **375, 768, 1024, 1280 y 1512 px**, para catálogo, contratación, retorno pendiente, gestión y diálogo. Capturas inspeccionadas; sin overflow horizontal. Tab/Shift+Tab/Enter, foco visible, Escape, trap y restauración de foco/scroll del diálogo comprobados. **Zoom nativo de Chrome 200 %** verificado mediante su interfaz y `Page.getLayoutMetrics`; no se sustituye por CSS zoom o devicePixelRatio. Consola: **0 errores/warnings JavaScript**. Network: **0 404 inesperados**. `/me`: **una lectura por documento normal**; el caso inducido de error `/me` tiene una segunda lectura expresamente solicitada para recuperar, no una carga normal duplicada.

| Comando o comprobación final | Resultado / código |
| --- | --- |
| `node --check` de fuentes JS y pruebas `.mjs` | 115 archivos, sin errores, 0. |
| `php -l` de adaptadores, rutas y helpers afectados | 9 archivos, sin errores, 0. |
| `node --test tests/frontend/*.test.mjs` | 120 aprobadas, 0 fallos, 0 omitidas/canceladas/todo, 0; baseline previo: 100. |
| Pruebas específicas billing + api-client | 20 aprobadas, 0 fallos/omitidas, 0; incluidas en las 120, no sumar otra vez. |
| `php vendor/bin/phpunit tests/Feature/ModSub/LemonSqueezyGatewayTest.php` | 11 aprobadas, 49 aserciones, sin omisiones, 0; transporte con `Http::fake`, no pago real. |
| `npm run build` (equivalente npm-cli documentado) | Vite 8.2.1, 106 módulos, 0. Aviso no bloqueante de `PLUGIN_TIMINGS`/rendimiento de Tailwind; sin error de bundle. |
| Manifest/imports/assets | 75 entradas válidas, referencias locales existentes. |
| `php artisan view:cache` y `php artisan route:cache` | Ambos 0. |
| `php artisan route:list --path=billing` | 9 rutas: 3 web + 6 API (incluido webhook Backend que el frontend no llama), 0. |
| `php tests/frontend/render-registration.php` | 3 vistas (registro/login/landing), IDs y ARIA válidos, 0. |
| `php tests/frontend/render-billing.php` | 6 variantes; landmarks, IDs/ARIA y ausencia de formulario mutante readonly correctos, 0. |
| `node tests/frontend/subscription-browser.mjs` | 85 comprobaciones; 21 escenarios inducidos diferenciados; 0. |
| Preflight y comprobación posterior de fixtures | Destino Laravel/PDO QA correcto, ningún fixture MOD-SUB restante; 0. |

No se ejecutaron las suites MySQL Backend que exigen su otra base allowlisted; no se forzaron sus protecciones ni se contabilizan como aprobadas. No se repitió íntegramente el arnés antiguo de registro: su regresión se cubre mediante suite frontend, render y recorrido canónico real de esta aceptación. No hay comprobaciones locales pendientes de MOD-SUB.

Se corrigieron los defectos frontend descubiertos durante QA: binding de timers nativos del poller, foco de 422 tras desbloquear controles, recuperación de fuentes iniciales, aviso comercial duplicado y contraste del selector mensual/anual de la landing. No se alteró Backend para hacer pasar estas comprobaciones.

Limpieza final: fixtures de esta etapa eliminados exclusivamente por token (comprobación posterior sin negocios `QA-REGISTER-MODSUB`); servidor 8840 detenido, **0 perfiles temporales** restantes y `public/hot` ausente. Se conserva intencionalmente el fixture anterior `QA-REGISTER-6defd52506e9`, negocio QA **5**, con un propietario y una fila de registro. Capturas/evidencias sanitizadas se conservan intencionalmente bajo `storage/app/qa` y se excluyen de integración. Las cuatro migraciones progresivas aplicadas permanecen en la base QA; no se elimina la base ni datos históricos.

## Manifiesto acumulado MOD-SUB

24 fuentes nuevas, copiar en su ubicación relativa:

- `app/Http/Controllers/Web/SubscriptionPageController.php` — presentación SSR y autorización delegada.
- `app/Http/Middleware/PresentSubscriptionRestriction.php` — salida HTML conservando 403 y compuerta.
- `resources/views/layouts/billing.blade.php` — layout comercial autenticado.
- `resources/views/billing/index.blade.php` — contratación, gestión y retorno.
- `resources/views/billing/restricted.blade.php` — salida desde panel restringido.
- `resources/views/billing/public-plans.blade.php` — catálogo SSR público.
- `resources/js/core/billing-storage.js` — preferencia e intento no sensible por negocio.
- `resources/js/core/commercial-recovery.js` — recuperación comercial central.
- `resources/js/modules/billing/contracts.js` — validación explícita de Resources.
- `resources/js/modules/billing/attempt.js` — idempotencia y estado de checkout.
- `resources/js/modules/billing/data.js` — cliente central/lecturas/mutaciones/419.
- `resources/js/modules/billing/poller.js` — confirmación acotada.
- `resources/js/modules/billing/confirmation.js` — diálogo accesible.
- `resources/js/modules/billing/view.js` — presentación segura de catálogo/estado.
- `resources/js/modules/billing/index.js` — coordinador de pantalla.
- `resources/js/modules/billing/public-plans.js` — periodicidad y preferencia pública.
- `tests/frontend/billing.test.mjs` — contratos, intentos, almacenamiento y polling.
- `tests/frontend/api-client-billing.test.mjs` — regresión de transporte/códigos comerciales.
- `tests/frontend/render-billing.php` — render, landmarks, IDs/ARIA y readonly.
- `tests/frontend/subscription-qa-guard.php` — guardas efectivas compartidas de QA.
- `tests/frontend/subscription-browser-db.php` — schema/evidencia/fixtures/limpieza acotada CLI.
- `tests/frontend/subscription-browser-router.php` — proveedor aislado solo de pruebas.
- `tests/frontend/subscription-browser.mjs` — aceptación real de navegador y errores inducidos.
- `docs/Frontend_suscripciones.md` — integración y reproducción.

14 fuentes modificadas/reemplazan la versión previa; conciliar como archivos compartidos:

- `routes/web.php` — landing SSR, tres rutas comerciales y adaptador HTML.
- `resources/views/landing.blade.php` — sustituye solo la sección de planes.
- `resources/js/modules/landing/index.js` — reutiliza nueva selección canónica, conserva el resto.
- `resources/views/auth/login.blade.php` — meta de destino post-login.
- `resources/js/modules/security/auth.js` — destino post-login sin alterar credenciales.
- `resources/js/core/api-client.js` — evento comercial por code; sin retry de mutaciones.
- `resources/js/core/session-context.js` — conserva business.id real sin otro /me.
- `resources/js/shell/logout.js` — limpia preferencia/intento y controla spinner.
- `resources/views/layouts/panel.blade.php` — URL comercial central.
- `resources/views/layouts/partials/navbar.blade.php` — acceso a suscripción desde cuenta, readonly donde corresponda.
- `resources/js/app.js` — registra módulo y recuperación comercial.
- `tests/frontend/render-registration.php` — landing SSR con colaborador canónico.
- `tests/frontend/registration-browser-runtime.mjs` — router QA opcional, mantiene servidor propio/guardas.
- `tests/frontend/registration-browser.mjs` — expectativa de contratación para propietario recién registrado, sin alterar aceptación del registro.

Eliminados en MOD-SUB: ninguno. Las siete vistas legacy retiradas en la entrega de registro siguen retiradas; no reaparecen ni se vuelve a copiar ese legado. Registro, API y autenticación canónica son dependencias previas ya integradas, no una nueva implementación en esta fase.

Excluir: `storage/app/qa`, capturas, perfiles, `.env`, secrets, cachés, vistas compiladas, `public/hot`, `public/build`, `vendor`, `node_modules`, Playwright instalado, Chrome y base QA. No cambiaron package.json/package-lock.json/Vite. Regenerar el bundle en el destino con `npm run build`; no copiar el build validado de Codex.

Después de la conciliación manual de las fuentes y con la configuración destino preparada por Pablo:

```powershell
# Usa el lockfile ya integrado; no hay cambios de dependencias en MOD-SUB.
npm ci
npm run build
php artisan view:cache
php artisan route:cache
php artisan route:list --path=billing
```

Los comandos de migración QA anteriores no son instrucciones para ejecutar contra datos reales. Las migraciones y configuración comerciales del destino pertenecen al procedimiento Backend ya integrado. No copiar los archivos de entorno de esta aceptación.

## Requisitos externos pendientes

Credenciales Lemon Squeezy TEST, seis variantes oficiales (tres planes × dos periodicidades), tienda, webhook HTTPS firmado y URLs de retorno configuradas por Backend. Después: validar pago, evento y vigencia con el proveedor TEST real. LIVE requiere decisión/configuración externa coherente y su propia aceptación. No solicitar secretos por chat ni sustituir estas verificaciones por una fixture local.

No se hizo integración, staging, commit, push, cambios en Backend-Claude/gintly_app/Figma ni avance hacia MFA/2FA u otros módulos.

## Microcierre de selectores — 2026-10-09

Este ajuste se realizó directamente en `gintly_app` con autorización expresa. No se hicieron staging, commits, push, despliegues, cambios de entorno, de Backend, del proveedor ni integración de MFA. El cambio previo de `README.md` y los archivos de caché ajenos a esta intervención se conservaron.

### Causa reproducida y recuperación

Con un propietario QA nuevo, el catálogo real devuelve tres planes y dos periodicidades; los selectores nativos funcionan con clic y teclado en las seis combinaciones. No hay un elemento superpuesto que los bloquee. El build carga e inicializa el módulo de billing.

Después de un resultado incierto de checkout, el intento conserva UUID y selección. Su estado `recoverable` deshabilita el `fieldset`, también después de recargar. Esa protección es correcta, pero el aviso y las acciones de recuperación estaban debajo del formulario y el catálogo; no había una explicación junto a los campos y su aspecto no evidenciaba el bloqueo.

Ahora hay un mensaje accesible junto a los selectores, estilos de deshabilitado y la recuperación antes de los campos. «Recuperar el mismo intento» conserva UUID y payload. No libera los campos ni crea otra clave. Solo `CHECKOUT_KEY_EXPIRED`, confirmado por el servidor, permite «Iniciar un nuevo intento» explícitamente y restaura el foco en Plan. Un 422 permite corregir y crear el siguiente intento lógico; un conflicto de idempotencia no se evade. Los metadatos públicos del fallo (`status` y `code`) se mantienen solo en memoria; no cambió el formato de almacenamiento por negocio.

`public/hot` local se conservó. La aceptación usó los assets compilados mediante el router QA existente y su guarda; no dependió de un servidor Vite ni borró el indicador local. La prueba de `EnvironmentAwareVite` confirma que producción ignora ese indicador, mientras local conserva el comportamiento de desarrollo. Esta comprobación no afirma que Azure haya sido publicado ni corregido.

### Evidencia final

- Chrome 154.0.8037.93, URL aislada `http://127.0.0.1:8840`, base efectiva Laravel/PDO `gintly_frontend_qa_rol03`.
- Aceptación final: **68 comprobaciones**, código 0. Registro 201, login 200, `/me`, catálogo y estado 200; logout 204. El acceso HTML al dashboard sigue dando 403 y su API `403 SUBSCRIPTION_REQUIRED` para el propietario sin suscripción.
- Selección nativa mediante clic, Home, flechas y Enter de Inicial/Comercio/Cadena × mensual/anual; resúmenes cotejados con precios del catálogo real. Tab y Shift+Tab correctos.
- Fallos de checkout **inducidos mediante interceptación**: dos 503 `BILLING_UNAVAILABLE`, 409 `CHECKOUT_RESULT_UNKNOWN`, 409 `CHECKOUT_KEY_EXPIRED`, 422 y 409 `CHECKOUT_IDEMPOTENCY_CONFLICT`. No son respuestas de Lemon Squeezy. Se acreditó conservación de UUID/snapshot, recarga, recuperación explícita y foco después de liberar por vencimiento.
- 375, 768, 1024, 1280 y 1512 px: sin overflow horizontal; capturas del bloqueo y recuperación. Cero 404 inesperados, excepciones JavaScript o warnings. Se observaron seis mensajes de error de recurso HTTP esperados por los fallos inducidos; no se contabilizan como una consola enteramente vacía.
- Cero checkout o pagos persistidos; el proveedor no fue contactado. El negocio demo y los fixtures anteriores no se alteraron.
- Fixtures exclusivos de este microcierre: `QA-REGISTER-MODSUB-732abdccc80b` (negocio 538, diagnóstico), `QA-REGISTER-MODSUB-fc50bb1adebc` (539) y `QA-REGISTER-MODSUB-140225ca5413` (540, cierre final). La limpieza CLI acotada confirmó la eliminación de cada fixture propio en la QA autorizada. Evidencia retenida intencionalmente en `storage/app/qa/billing-selectors/<token>/`; no integrable. Servidores y contextos de navegador propios detenidos.

### Comandos y resultados

Todos terminaron con código 0:

```powershell
node --check resources/js/modules/billing/index.js
node --check resources/js/modules/billing/attempt.js
node --check resources/js/modules/billing/selection-state.js
node --check tests/frontend/billing.test.mjs
node --check tests/frontend/billing-selectors-browser.mjs
node --test tests/frontend/billing.test.mjs tests/frontend/billing-demo.test.mjs tests/frontend/api-client-billing.test.mjs
node --test tests/frontend/*.test.mjs
node node_modules/vite/bin/vite.js build
php tests/frontend/render-billing.php
php tests/frontend/render-registration.php
php artisan route:list --path=billing
php vendor/phpunit/phpunit/phpunit tests/Unit/Frontend/EnvironmentAwareViteTest.php
git diff --check
```

Resultados: sintaxis de cinco archivos sin errores; suite enfocada 30/30 y completa 148/148, sin fallos ni omitidas. Build Vite 8.2.1: 107 módulos y manifest de 75 entradas válidas; un aviso informativo `PLUGIN_TIMINGS`, no fallo de compilación. Render billing: seis variantes, IDs/ARIA/landmarks válidos. Render registro/login/landing: aprobado. Nueve rutas billing conservadas. Guarda de assets: cinco pruebas, 28 aserciones. Los renders usan un entorno de proceso de producción, sin cambios a `.env`; la prueba unitaria de assets usa SQLite en memoria y no escribe datos del negocio.

Para reproducir exclusivamente la aceptación de selectores, usar PHP 8.3 con las extensiones del proyecto, Node 22.12+ y Chrome/Chromium con Playwright externo instalado; no son dependencias del bundle. Mantener las credenciales QA únicamente en la configuración local ya autorizada, sin pegarlas en la documentación o logs. Desde la raíz del proyecto, tras compilar:

```powershell
$env:QA_REGISTRATION_BROWSER = '1'
$env:QA_SUBSCRIPTION_BROWSER = '1'
$env:QA_CANONICAL_BUILT_ASSETS = '1'
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8840'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'gintly_frontend_qa_rol03'
$env:DB_URL = ''
$env:DB_SOCKET = ''
$env:QA_PHP = '<ruta al ejecutable php.exe>'
$env:QA_CHROME = '<ruta al ejecutable chrome.exe o Chromium>'
$env:QA_PLAYWRIGHT_MODULE = '<ruta al index.mjs de Playwright externo>'
node tests/frontend/billing-selectors-browser.mjs
```

El ejecutable requiere el puerto 8840 libre y las guardas y helpers ya entregados: `registration-browser-runtime.mjs`, `registration-browser-db.php`, `registration-shell.mjs`, `canonical-browser-router.php`, `subscription-browser-db.php` y `subscription-qa-guard.php`. Verifica el destino efectivo antes de crear el único fixture QA propio; no debe ejecutarse contra una base real. No depende de helpers fuente omitidos en `storage/app/qa`. El comando genera capturas y evidencia, pero no llama al proveedor. `--diagnose` es una opción para acreditar el bloqueo original sin exigir los nuevos avisos.

### Manifiesto exclusivo de este parche

Nuevos:

- `resources/js/modules/billing/selection-state.js` — presentación pura del bloqueo y recuperación.
- `tests/frontend/billing-selectors-browser.mjs` — regresión autenticada acotada, con fallos comerciales inducidos.

Modificados, reemplazan sus versiones anteriores:

- `resources/js/modules/billing/attempt.js` — estado público del fallo solo en memoria.
- `resources/js/modules/billing/index.js` — aviso, recuperación y foco; mantiene el bloqueo.
- `resources/views/billing/index.blade.php` — recuperación visible, descripción accesible y aspecto disabled.
- `tests/frontend/billing.test.mjs` — siete pruebas adicionales del bloqueo y recuperación.
- `docs/Frontend_suscripciones.md` — este cierre y comandos de reproducción.

Eliminados: ninguno. No cambiaron paquetes, lockfiles, Vite, rutas, autenticación, demo o compuerta comercial. Excluir herramientas y resultados bajo `storage/app/qa`, capturas, cachés, perfiles, `.env`, dependencias instaladas y `public/build`. El bundle se regenera con `npm run build`; no es una fuente para copiar. Los requisitos externos de Lemon Squeezy y la aceptación del proveedor no forman parte de esta corrección de selectores.
