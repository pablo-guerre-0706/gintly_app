# Registro público canónico — frontend

Esta unidad confirma exclusivamente el alta de negocio y propietario. No incluye
suscripción, planes, checkout, pagos ni activación comercial. Backend conserva la
autoridad sobre operabilidad. Figma no tiene un diseño específico para este flujo.

## Recorrido y reemplazo del asistente

| Paso anterior | Recorrido actual |
| --- | --- |
| 1. Perfil | Cuenta propietaria: nombres, apellidos, correo, contraseña y confirmación |
| 2. Negocio | Nombre del negocio |
| 3. Tipo de negocio | Retirado: no pertenece al contrato de alta |
| 4. Región | Solo zona horaria, editable, dentro de Negocio; se retiran los otros campos |
| 5. Usuarios | Retirado: corresponde a administración autenticada posterior |
| 6. Plan/contratación | Retirado: entrega independiente |
| 7. Resultado legacy | Revisión sin contraseña y Resultado, solo tras HTTP 201 canónico |

Las primeras dos etapas solo editan datos en memoria. Revisión muestra únicamente
propietario, correo, nombre y zona horaria. Crear negocio ejecuta la única escritura
de dominio. Resultado muestra el slug y correo retornados, ofrece copiar el slug e
iniciar sesión manualmente. No afirma envío por correo, pago, contratación ni ERP
habilitado. Nunca ejecuta login automáticamente.

## Contrato efectivo

`POST /api/v1/auth/register`, JSON con exactamente:

```json
{
  "business": { "name": "Nombre del negocio", "timezone": "America/Managua" },
  "owner": {
    "first_name": "Nombres", "last_name": "Apellidos", "email": "propietario@example.test",
    "password": "<introducida por el usuario>", "password_confirmation": "<confirmación exacta>"
  }
}
```

Cabeceras: Accept y Content-Type application/json, Idempotency-Key UUID seguro y
X-XSRF-TOKEN proveniente del handshake real GET /sanctum/csrf-cookie. Todo pasa por
core/api-client.js y las cookies de sesión. No se envían _token, business_id, slug,
roles, perfiles, empleados, planes, suscripciones o pagos.

Éxito y replay: HTTP 201 y exactamente `{"data":{"business_slug":"...","owner_email":"..."}}`.
El cliente comprueba estado HTTP y estructura antes de mostrar éxito.

Fuentes verificadas: RegisterRequest, RegisterController, RegistrationResultResource,
RegistrationService, AppServiceProvider, contratos JSON MOD-01 y FRD RF-01-07.
Password::defaults() instalado exige min(12), letras, números, símbolos y
uncompromised(). El último control es exclusivamente Backend. La contraseña y su
confirmación no se recortan, transforman ni limitan silenciosamente.

El LoginRequest instalado y el frontend existente envían business_slug, email y
password. No existe un control remember en este formulario ni se añade uno por
inferencia a partir de documentación histórica. No se transportan credenciales por
query string. El usuario copia/conserva el slug y escribe su contraseña en login.

## Estado e idempotencia

- editing: se permite navegar/corregir; validación local útil y autoridad Backend.
- submitting: bloqueo síncrono antes de cualquier await; controles disabled y aria-busy.
- uncertain/recoverable: snapshot inmutable y clave originales en memoria; edición
  bloqueada; únicamente reintento explícito del mismo intento. Cancelar/cerrar/recargar
  no implica rollback y no existe recuperación garantizada después de recargar.
- 422: errores por rutas literales, resumen accesible, etapa/campo correspondiente;
  se libera el intento y la siguiente confirmación genera una clave nueva.
- 403: sesión humana existente; enlace al dashboard; no logout automático.
- 409 REGISTRATION_IDEMPOTENCY_CONFLICT: bloqueo definitivo sin rotación de clave;
  secretos liberados. 409 BUSINESS_SLUG_CONFLICT: replay explícito del mismo intento.
- 419: una renovación CSRF y como máximo un replay automático con igual clave/body.
- 429: respeta Retry-After (segundos o fecha); sin bucles de solicitud.
- 500/503/red/timeout/201 ilegible/HTTP inesperado: mensaje sanitizado e incertidumbre;
  nunca se supone éxito ni se crea otra clave silenciosamente.
- success: no segunda creación; se liberan snapshot, contraseña y confirmación y se
  muestran solo el resultado público. No hay persistencia de secretos ni telemetría.

## Rutas y artefactos legacy

- GET /register: auth.register, sin escrituras.
- GET /register/step/1..7: redirección 302 a /register, sin conservar query strings.
- GET /register/step/1..7/store: 410, sin invocar RegisterWizardController.
- Métodos de escritura legacy: no registrados; POST comprobado devuelve 405.
- Único alta: POST /api/v1/auth/register. Login/logout canónicos permanecen separados.

RegisterWizardController, RegisterWizardRequest, RegisterWizard, su migración histórica,
tabla y alias de morphMap permanecen intactos pero sin rutas web/API activas. Las siete
vistas legacy sin consumidores se eliminaron; el entry wizard.js y su import CSS se
reutilizaron para no introducir otro pipeline. No se eliminan tablas ni datos históricos.

## Validación e integración

```sh
node --check resources/js/modules/registration/wizard.js
node --check resources/js/modules/registration/attempt.js
node --check resources/js/modules/registration/contract.js
node --check resources/js/modules/security/auth.js
node --check resources/js/core/api-client.js
node --test tests/frontend/*.test.mjs
npm run build
php artisan view:cache
php artisan route:cache
php artisan route:list --path=register
php tests/frontend/render-registration.php
```

Los tests JS usan Fetch/transportes simulados y lo declaran. La herramienta aislada
storage/app/qa/registration-acceptance.php ejecuta HTTP real, sin desactivar CSRF,
solo en gintly_frontend_qa_rol03, local/testing y host 127.0.0.1. No integrar esa
herramienta, capturas, cachés, dependencias ni public/build. La QA conservó un negocio
identificable, un propietario y una fila idempotente; no se borraron documentos.
La migración progresiva de registration_requests se aplicó únicamente a esa QA.

La aceptación HTTP real comprobó 201 inicial/replay, 419 sin token, 422, 409 de
idempotencia, ausencia de auto-login (/me 401), login 200, /me 200 con ROL-01/negocio,
403 con sesión activa, logout 204 y legacy 410/302/405 sin escrituras.

## Microcierre de navegador — 6 de octubre de 2026

La ausencia del controlador integrado se resolvió usando las herramientas ya
instaladas: Playwright 1.62.1 externo al proyecto y Google Chrome 154.0.8037.93,
con ventana real y perfil QA temporal. No se instalaron paquetes ni navegadores,
no se modificó el pipeline y no se usó un servicio externo. URL de la aceptación:
http://127.0.0.1:8840, exclusivamente contra gintly_frontend_qa_rol03 en MySQL local.
El preflight comprobó APP_ENV local, host 127.0.0.1, nombre efectivo de la base y
ausencia de DB_URL alternativo; no imprimió credenciales. No se aplicaron migraciones
en este microcierre.

### Evidencia real e inducida

La ejecución completa `QA-REGISTER-BROWSER-9737c53a3ad6` aprobó 71 comprobaciones
(salida 0), con 29 capturas. La regresión posterior al ajuste visual del enlace,
`QA-REGISTER-BROWSER-d9a14c0d4832`, aprobó otras 37 comprobaciones (salida 0) y dejó
2 capturas de error definitivas, sin nuevas escrituras. El recorrido real entró desde la landing, navegó
Cuenta → Negocio (zona horaria incluida) → Revisión → Resultado y obtuvo 201.
Durante la carga se bloquearon campos, botón y envíos repetidos; solo hubo un POST
inicial. Se comprobaron en memoria allowlist exacta, UUID por header, JSON, X-XSRF-TOKEN
y contraseña con espacios iniciales/finales intactos, sin registrar sus valores.

El resultado mostró exclusivamente slug/correo del servidor, eliminó los valores
de contraseña y confirmación y enfocó su título. Copiar funcionó en Chrome; la
indisponibilidad inducida de Clipboard API mostró la alternativa de selección y
copia manual del texto público. Recargar no emitió otra creación ni recuperó secretos.
/me devolvió 401 antes del login explícito. El login desde su formulario devolvió
200 con esa contraseña exacta y /me devolvió ROL-01, negocio correcto y capacidades
efectivas. Registrar con sesión humana activa devolvió 403, conservó la sesión y
ofreció el panel. Logout desde el menú de cuenta devolvió 204. El contrato actual
de autenticación conserva solamente slug, email y password; no se añadió remember.

En una segunda alta real del mismo recorrido, la interceptación dejó que el
Backend procesara el POST y devolviera 201, pero descartó su respuesta al navegador.
La UI quedó incierta, bloqueó la edición y recuperó el resultado con un reintento
explícito: mismo UUID, mismo cuerpo y mismo resultado 201. La consulta de BD confirmó
un negocio, un propietario y una fila idempotente por alta, sin duplicación.
Este caso es pérdida de una respuesta real, no un 201 fabricado.

Los estados 422, ambos códigos 409, 419 repetido, 429 con Retry-After=2, 500,
fallo de red y JSON 201 ilegible se provocaron mediante interceptación controlada.
Se identifican como `induced-*` en la evidencia y no acreditan fallos reales del
Backend. La corrección tras 422 preservó valores, enfocó owner.email y generó una
clave nueva; los reintentos inciertos mantuvieron cuerpo/UUID y bloquearon edición.
419 produjo exactamente dos POST y una renovación adicional de CSRF, sin bucle.
429 bloqueó el reintento durante el plazo y no envió solicitudes automáticamente.
Ninguna respuesta fallida o ilegible mostró éxito. La recuperación de 419 seguida
de éxito y otros límites también conserva sus pruebas unitarias de transporte.

Las 7 escrituras legacy GET volvieron a responder 410; los 7 enlaces antiguos, 302;
el POST legacy, 405. La tabla register_wizards conservó 0 filas. No se tocaron las
clases, tablas o migraciones históricas.

### Presentación, foco y corrección indispensable

Se recorrieron 375, 768, 1024, 1280 y 1512 px en cuenta, negocio, revisión y resultado,
con capturas inspeccionadas visualmente, sin desbordamiento horizontal ni controles
superpuestos. Tab, Shift+Tab y Enter comprobaron orden natural y foco visible; los
cambios de etapa enfocaron su encabezado y los errores, el campo pertinente. Se
comprobaron IDs únicos y relaciones ARIA. Esto no constituye una certificación WCAG
completa ni una prueba de todas las tecnologías de asistencia.

El zoom fue **real de Chrome al 200%**, mediante Settings → Appearance → Page zoom;
el valor seleccionado fue 2 y Page.getLayoutMetrics confirmó cssVisualViewport.zoom=2.
No se sustituyó por CSS zoom, devicePixelRatio ni un viewport reducido. Se capturaron
las cuatro etapas ampliadas. La herramienta de captura se ajustó a DIP de Chrome
para evitar que el clip de una página ampliada cortara la imagen en dos; ese recorte
era del arnés QA, no del formulario.

La inspección visual detectó un defecto real: el enlace «Ir a mi panel» se mostraba
en errores anónimos 422, porque la combinación de clases hidden e inline-flex no
garantizaba ocultarlo. Se sustituyó por el atributo HTML hidden y su propiedad DOM.
La prueba de regresión exige que el enlace permanezca invisible en errores anónimos
y aparezca solamente ante el rechazo 403 por sesión autenticada. Se volvieron a
probar esos estados con el bundle corregido, incluyendo mensajes largos en móvil.

No se encontraron errores JavaScript, warnings ni 404 inesperados. Chrome sí
registró avisos de recurso al recibir los 401/403 esperados y los errores inducidos;
no se ocultan ni se confunden con defectos de consola del recorrido exitoso. No se
guardaron HAR, trazas, cuerpos de request, contraseñas ni cookies en la evidencia.
El perfil de cada ejecución se elimina después de cerrar Chrome.

### Comandos y reproducción acotada — verificación de entrega

La aceptación funcional anterior se conserva; este cierre verifica únicamente que
su ejecutable sea reproducible después de integrar el manifiesto. No cambia vistas,
contratos, transporte de la aplicación ni dependencias de producción.

La prueba reutilizable tiene ahora **todas sus fuentes permanentes en tests/frontend**:

- registration-browser.mjs: interacción y evidencia del navegador.
- registration-browser-runtime.mjs: preflight, dependencias y servidor PHP propio.
- registration-browser-db.php: guardas CLI de configuración/conexión y evidencia
  read-only; trasladado desde storage/app/qa. No tiene ruta HTTP.
- registration-browser-guards.test.mjs: regresión de estas guardas.

No depende de storage/app/qa/registration-browser-db.php ni de registration-zoom.mjs.
Tampoco lee un Preferences preexistente: genera su propio browser-profile/Default/Preferences
dentro del directorio exclusivo de la ejecución y lo elimina al cerrar el navegador,
incluso si falla su lanzamiento. No utiliza perfiles personales.

#### Requisitos fuera del bundle

- Proyecto Laravel existente y su Backend canónico integrado; vendor preparado con
  composer install a partir del lockfile existente, PHP 8.3+ y pdo_mysql. DOM también
  es necesario para render-registration.php. No ejecutar composer setup, que incluye
  migraciones, ni instalar otra aplicación.
- Node **22.12+** y npm. Se verificó Node 24.19.0 y PHP 8.3.30.
- Google Chrome instalado, con sesión gráfica para la aceptación visual completa.
  Versión comprobada: **154.0.8037.93**. QA_CHROME es una ruta absoluta configurable;
  no hay fallback al equipo de Codex ni se entrega un binario.
- Playwright **1.62.1**, instalado fuera de la aplicación. Su index.mjs se proporciona
  mediante QA_PLAYWRIGHT_MODULE. La instalación externa contiene también playwright-core.
  No se cambia package.json/package-lock.json de Gintly.
- MySQL local 127.0.0.1, base ya autorizada gintly_frontend_qa_rol03, credenciales locales
  disponibles de forma segura en el entorno/.env existente, APP_KEY válido, esquema
  progresivo del proyecto (incluida registration_requests) y roles/permisos sembrados.
  El ejecutable no crea la base, no migra, no siembra ni limpia datos.
- Puerto **8840 libre**. No lanzar artisan serve aparte ni reutilizar un servidor
  desconocido. public/hot debe estar ausente; regenerar el build de producción.
- Backend conserva Password::uncompromised(): la prueba completa requiere que su
  verificador instalado pueda funcionar; no se desactiva para aprobar QA.

Preparación desde la raíz del proyecto ya existente, PowerShell (no requiere una
ruta C:/Users/... particular):

```powershell
node --version
# Si PHP no está en PATH, establecer antes QA_PHP con su ruta absoluta instalada.
$registrationQaPhp = if ($env:QA_PHP) { $env:QA_PHP } else { (Get-Command php -ErrorAction Stop).Source }
& $registrationQaPhp --version
# Solo si faltan las dependencias del proyecto existente:
composer install
npm ci
npm run build

# O reutilizar un Playwright 1.62.1 externo ya preparado.
# Esta instalación, si hace falta, queda en TEMP, fuera del bundle/repositorio.
$registrationQaTools = Join-Path $env:TEMP 'gintly-registration-qa-tools'
npm install --prefix $registrationQaTools --no-save --package-lock=false playwright@1.62.1

$env:QA_PLAYWRIGHT_MODULE = Join-Path $registrationQaTools 'node_modules/playwright/index.mjs'
$env:QA_CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
$env:QA_PHP = $registrationQaPhp

$env:QA_REGISTRATION_BROWSER = '1'
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8840'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'gintly_frontend_qa_rol03'
$env:DB_URL = ''
$env:DB_SOCKET = ''
$env:SESSION_DRIVER = 'file'
$env:SESSION_CONNECTION = ''
$env:SESSION_DOMAIN = ''
$env:SESSION_SECURE_COOKIE = 'false'
$env:CACHE_STORE = 'file'
$env:SANCTUM_STATEFUL_DOMAINS = '127.0.0.1:8840'
# DB_PORT/DB_USERNAME/DB_PASSWORD proceden de las credenciales locales QA
# ya autorizadas. No copiarlas al informe ni introducirlas en scripts.
$env:QA_HEADLESS = '0'
$env:QA_ERRORS_ONLY = '0'
$env:QA_PREFLIGHT_ONLY = '1'
node tests/frontend/registration-browser.mjs
if ($LASTEXITCODE -ne 0) { throw 'Preflight QA rechazado; no continuar' }
```

El preflight comprueba primero el opt-in, URL exacta, host/driver/nombre allowlisted y
ausencia de DB_URL/socket alternativos; después arranca el helper CLI con el mismo
PHP y entorno que recibirá el servidor. El helper inspecciona la **configuración
resuelta de Laravel**, incluida cualquier caché, rechaza conexiones read/write
alternativas y comprueba **SELECT DATABASE() en PDO**. Valida además sesión/cache
locales, Sanctum, esquema y rol propietario. Solo emite nombres/IDs QA y conteos,
nunca credenciales. Los errores identifican la fase sin imprimir SQL o DSN.

Las comprobaciones de assets/manifest, imports, binarios y puerto también preceden
la creación del perfil y cualquier escritura de aceptación. Si una configuración
cacheada apunta a otra base, el preflight falla: no la borra ni la reemplaza.
El responsable debe preparar una configuración local QA válida y repetir solo el
preflight; no modificar el Backend para eludir la guarda.

Para ejecutar **solamente este microcierre**, conservar las variables anteriores:

```powershell
$env:QA_PREFLIGHT_ONLY = '0'
$env:QA_ERRORS_ONLY = '1'
node tests/frontend/registration-browser.mjs
if ($LASTEXITCODE -ne 0) { throw 'Aceptación acotada incompleta' }

node --check tests/frontend/registration-browser.mjs
node --check tests/frontend/registration-browser-runtime.mjs
node --check tests/frontend/registration-browser-guards.test.mjs
& $registrationQaPhp -l tests/frontend/registration-browser-db.php
node --test tests/frontend/registration.test.mjs tests/frontend/api-client-registration.test.mjs tests/frontend/registration-browser-guards.test.mjs
& $registrationQaPhp tests/frontend/render-registration.php
```

QA_ERRORS_ONLY=1 usa navegador/backend reales para cargar el formulario y verificar
legacy, pero intercepta las respuestas de registro; **no crea negocios**. Se comprueba
esto por lectura de BD al finalizar. No sustituye al recorrido funcional real ya
acreditado; no presenta un 201 inducido como un alta real.

Para repetir la **aceptación completa**, cuando sea necesario y autorizado:

```powershell
$env:QA_PREFLIGHT_ONLY = '0'
$env:QA_ERRORS_ONLY = '0'
node tests/frontend/registration-browser.mjs
if ($LASTEXITCODE -ne 0) { throw 'Aceptación completa incompleta' }
```

Ese modo crea dos fixtures identificables: alta inicial y alta con respuesta real
perdida/replay. Verifica en BD un negocio, propietario y fila idempotente por cada
uno. No necesita recuperar contraseñas antiguas ni reutilizar cuentas personales.

El runner inicia directamente **un proceso PHP -S** en public, con el router
existente de Laravel en vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php.
Mismo binario, raíz y entorno verificados por CLI; exige su propio anuncio de bind
en 127.0.0.1:8840 y aborta si el puerto estaba ocupado o el proceso muere. No consulta
un endpoint de configuración ni confía únicamente en que la URL sea localhost.
No deja un subproceso artisan independiente. El bloque finally cierra Chrome,
detiene ese PHP y elimina el perfil; mantiene capturas/evidence.json como artefactos
excluidos. Un cierre forzado del sistema no garantiza finalmente: antes de repetir,
comprobar y detener únicamente el proceso/perfil QA propio, nunca un perfil personal.

#### Resultado de esta verificación de reproducibilidad

- Preflight real completo: **salida 0**, antes de crear artefactos o iniciar servidor.
- Pruebas de guardas: **14/14**, 0 fallos, 0 omitidas, salida 0. Incluyen ausencia de
  opt-in, producción, BD ordinaria, host remoto, URL distinta, driver distinto,
  DB_URL/socket, rutas de herramienta inválidas, Playwright dentro de la aplicación
  y puerto ocupado. Dos rechazos CLI reales adicionales (debug efectivo activo y
  nombre de base no autorizado) devolvieron **1 esperado**, antes de escribir.
- Pruebas pertinentes de registro + transporte + guardas: **33/33**, 0 fallos,
  0 omitidas, salida 0. No se repitieron suites de otros módulos.
- Sintaxis de los tres módulos QA y lint PHP del helper: salida 0.
- Navegador real acotado: **39 comprobaciones**, salida 0, ejecución
  QA-REGISTER-BROWSER-aed3f0bb5f83. Incluye 37 casos existentes y 2 comprobaciones
  nuevas de evidencia BD: legacy intacto y ningún fixture nuevo.
- Render de registro/login/landing: salida 0; IDs únicos y ARIA válido.
  Manifest generado existente: **73 entradas válidas**.
- Relectura read-only: QA-REGISTER-6defd52506e9 y los 9 fixtures de navegador anteriores
  siguen con 1 propietario y 1 fila idempotente cada uno; legacy conserva 0 filas.
- No se recompiló Vite ni se cambió la aplicación en este cierre: los cambios son
  exclusivamente fuentes de prueba/documentación fuera del bundle. Se conservan
  los resultados anteriores (84 pruebas y build 96 módulos, todos salida 0); no se
  presentan como nuevas ejecuciones.

La prueba es reproducible con las fuentes integrables y los requisitos anteriores.
No queda ningún helper fuente obligatorio bajo storage/app/qa ni dependencia de un
perfil particular de Codex. La aceptación visual completa previa se conserva; aquí
no se reejecutaron los cinco anchos ni el zoom porque el formulario no cambió.

### Fixtures y residuos identificados

Se conserva QA-REGISTER-6defd52506e9, negocio 5, de la aceptación HTTP previa.
El microcierre conservó intencionalmente estas altas QA identificables, incluidas
las generadas al corregir el arnés; cada una tiene 1 propietario y 1 fila idempotente:

| Negocio | Fixture |
| --- | --- |
| 6 | QA-REGISTER-BROWSER-4539c52ebc90 |
| 7 | QA-REGISTER-BROWSER-fb797993c70f |
| 8 | QA-REGISTER-BROWSER-fb797993c70f-lost |
| 9 | QA-REGISTER-BROWSER-7fdce36e84a5 |
| 10 | QA-REGISTER-BROWSER-7fdce36e84a5-lost |
| 11 | QA-REGISTER-BROWSER-5a7a4dc28be8 |
| 12 | QA-REGISTER-BROWSER-5a7a4dc28be8-lost |
| 13 | QA-REGISTER-BROWSER-9737c53a3ad6 |
| 14 | QA-REGISTER-BROWSER-9737c53a3ad6-lost |

No se afirma «cero residuos»: se conservan estas filas, capturas/evidencia de las
ejecuciones y sesiones/cache QA generadas por Laravel. No se eliminaron documentos
ni se hizo limpieza general. Las contraseñas generadas solo vivieron durante las
pruebas; no se conservan para reutilizar estas cuentas. Las capturas definitivas
del recorrido están en storage/app/qa/registration-browser/QA-REGISTER-BROWSER-9737c53a3ad6;
las capturas de errores tras la corrección están en
storage/app/qa/registration-browser/QA-REGISTER-BROWSER-d9a14c0d4832.
Todo storage/app/qa queda fuera de integración.

En la verificación de reproducibilidad no se crearon negocios/propietarios ni
idempotencias nuevas. Se conserva únicamente la evidencia generada bajo
storage/app/qa/registration-browser/QA-REGISTER-BROWSER-aed3f0bb5f83. Se comprobó el
filesystem actual: registration-zoom.mjs, registration-zoom-profile y su antiguo
Default/Preferences están ausentes; tampoco quedan browser-profile de las
ejecuciones conservadas. El archivo Preferences mostrado en el historial de
ediciones fue temporal, no una fuente entregada. La copia temporal
storage/app/qa/registration-browser-db.php se retiró tras trasladar su función a
tests/frontend/registration-browser-db.php. No se limpiaron datos ni otras herramientas.

El servidor temporal quedó detenido al terminar y no se entrega public/hot. No hay
un defecto Backend canónico demostrado ni quedan pendientes de suscripción dentro
de esta aceptación: suscripciones/contratación siguen explícitamente fuera de alcance.

**Veredicto: APTO PARA INTEGRACIÓN FRONTEND DEL REGISTRO.** Se limita al registro,
su aceptación en navegador y la regresión de autenticación comprobada; no incluye
suscripciones, planes, pagos ni contratación.

## Manifiesto mínimo

Fuentes creadas (13):

- resources/js/modules/registration/contract.js
- resources/js/modules/registration/attempt.js
- resources/views/layouts/registration.blade.php
- resources/views/components/registration-field.blade.php
- resources/views/auth/register.blade.php
- tests/frontend/registration.test.mjs
- tests/frontend/api-client-registration.test.mjs
- tests/frontend/render-registration.php
- tests/frontend/registration-browser.mjs (prueba reutilizable opt-in; fuera del bundle)
- tests/frontend/registration-browser-runtime.mjs (nuevo en verificación de entrega; servidor/guardas)
- tests/frontend/registration-browser-db.php (nuevo en tests; helper CLI permanente que debe integrarse)
- tests/frontend/registration-browser-guards.test.mjs (nuevo en verificación de entrega; regresión)
- docs/Frontend_registro_canonico.md

Fuentes modificadas o compartidas (6), reemplazar por su versión final:

- routes/web.php (compartido con panel; preservar otras rutas al integrar)
- resources/js/core/api-client.js (compartido: incorpora Retry-After en ApiError)
- resources/js/modules/security/auth.js (compartido: init idempotente, 419 acotado y HTTP 200)
- resources/views/auth/login.blade.php (compartido: label de contraseña asociado)
- resources/js/modules/registration/wizard.js (reemplazo del legado por coordinador canónico)
- resources/css/register-wizard.css (estilos aislados del registro, elimina reglas legacy)

Siete vistas legacy eliminadas; retirar tras actualizar rutas/entry:

- resources/views/auth/register-step1.blade.php
- resources/views/auth/register-step2.blade.php
- resources/views/auth/register-step3.blade.php
- resources/views/auth/register-step4.blade.php
- resources/views/auth/register-step5.blade.php
- resources/views/auth/register-step6.blade.php
- resources/views/auth/register-step7.blade.php

En el microcierre de aceptación visual se creó registration-browser.mjs y se
actualizaron wizard.js, auth/register.blade.php, registration.test.mjs y este documento.
En la verificación de entrega posterior solo cambian registration-browser.mjs y
este documento, y se añaden los tres helpers/pruebas indicados. **No cambia código
de aplicación**. Integrar la versión final de esos archivos; el resto acumulado no
requiere copiarse de nuevo si Pablo ya lo integró. No copiar la antigua herramienta
de storage/app/qa: el helper definitivo de tests/frontend sí es fuente integrable.

Artefactos/dependencias excluidos: todo storage/app/qa (incluida la herramienta HTTP
histórica registration-acceptance.php), capturas, evidence.json, perfiles y Preferences
generados, public/build, public/hot, vistas compiladas, cachés, bases QA, vendor,
node_modules, instalación externa de Playwright, binarios Chrome/PHP/Node y credenciales.
La retirada de la copia temporal del helper no es una eliminación de aplicación
que Pablo deba replicar; las únicas bajas integrables son las siete vistas legacy.

package.json, package-lock.json, vite.config.js, app.css, landing y Backend no se
modificaron. No hay nuevas dependencias de producción. Regenerar public/build mediante npm run build
después de integración manual. No hacer staging general, commit, push ni copias
automáticas. Esta declaración no incluye suscripciones ni pagos.
