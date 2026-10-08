# Corrección local del propietario demo y configuración TEST

## Alcance

Intervención autorizada exclusivamente en `C:\laragon\www\gintly_app`.
No incorpora MFA, no modifica el frontend de registro ni cambia la compuerta comercial.
No realiza commits, despliegues, migraciones ni escrituras remotas.

## Reparación efectuada

- Conexión efectiva local comprobada: entorno `local`, MySQL en loopback, base `gintly_app`.
- Se verificaron negocio `gintly-demo` (ID 1) y propietario demo (ID 2), activo,
  con ROL-01 del mismo team.
- Se actualizó exclusivamente `businesses.owner_user_id`: NULL → 2.
- Una transacción y un UPDATE condicionado a propietario NULL impidieron sobrescribir
  una asignación existente. No cambiaron la fila del usuario, las demás columnas del
  negocio ni las cantidades de registros. No se ejecutó UserSeeder ni otros seeders.
- UserSeeder ahora completa este vínculo de forma idempotente en futuros seeding
  local/testing, conservando cualquier propietario ya asignado.
- No se creó una suscripción ni se otorgó acceso comercial.

## Configuración local

Se renombraron las seis claves de `.env`, conservando sus valores exactamente:

| Clave anterior | Clave consumida por config/billing.php |
| --- | --- |
| LS_TEST_VARIANT_BASICO_MENSUAL | LS_TEST_VARIANT_BASIC_MONTHLY |
| LS_TEST_VARIANT_BASICO_ANUAL | LS_TEST_VARIANT_BASIC_ANNUAL |
| LS_TEST_VARIANT_COMERCIO_MENSUAL | LS_TEST_VARIANT_COMERCIO_MONTHLY |
| LS_TEST_VARIANT_COMERCIO_ANUAL | LS_TEST_VARIANT_COMERCIO_ANNUAL |
| LS_TEST_VARIANT_CADENA_MENSUAL | LS_TEST_VARIANT_CADENA_MONTHLY |
| LS_TEST_VARIANT_CADENA_ANUAL | LS_TEST_VARIANT_CADENA_ANNUAL |

Se mantuvieron `BILLING_DEPLOYMENT_PURPOSE=demo` y `BILLING_PROVIDER_MODE=test`.
Las claves LIVE y las demás opciones permanecieron intactas. `.env.example` ya
contenía los nombres correctos y no requirió modificación.

Se comprobó la configuración Laravel efectiva y la selección real de BD mediante
PDO, no solo los nombres en `.env`. BillingCatalog resolvió las seis variantes;
esto NO acredita su validez en Lemon Squeezy ni constituye un pago.

No había caché de configuración ni de rutas, por lo que no se limpió ninguna caché.

## Ruta pública retirada

Se eliminó el GET público que ejecutaba migraciones forzadas y su import Artisan.
No había consumidores adicionales en las fuentes revisadas. La prueba HTTP aislada
obtiene 404 y la colección de rutas de la aplicación local confirma que no existe
la ruta. Nunca se invocó la acción de migración anterior.

## Validaciones reproducibles

Desde la raíz, usando las dependencias ya instaladas:

```powershell
php -l database/seeders/UserSeeder.php
php -l routes/web.php
php -l tests/Unit/Mod01/DemoOwnerLinkTest.php
php artisan route:list --path=auth --except-vendor
```

Para pruebas Unit, usar una terminal de pruebas separada; las variables siguientes
solo afectan ese proceso y sus hijos, no `.env`:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
$env:SESSION_DRIVER = 'array'
$env:CACHE_STORE = 'array'
$env:GINTLY_MYSQL_TESTS = '0'
php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Unit
```

La prueba nueva verifica SQLite en memoria ANTES de crear tablas. Ejercita el
vínculo con SQL real aislado y un doble explícito de la consulta de rol; no ejecuta
el seeder completo ni desactiva middleware. Cubre vínculo ausente, repetición,
propietario preexistente, tenant/identidad/estado/rol incompatibles y HTTP 404.
La autorización real del usuario local se verificó por separado con sus roles
persistidos y los FormRequests originales, dentro de una transacción de solo lectura.

```powershell
node --test tests/frontend/billing.test.mjs tests/frontend/api-client-billing.test.mjs tests/frontend/registration.test.mjs tests/frontend/api-client-registration.test.mjs
```

Resultado de esta intervención:

- Sintaxis PHP de tres archivos: código 0, sin errores.
- Prueba nueva: 9 aprobadas, 46 aserciones, código 0.
- Suite Unit completa: 13 aprobadas, 54 aserciones, 0 omitidas, código 0.
- Frontend pertinente: 44 aprobadas, 0 fallos, 0 omitidas, código 0.
- Arranque Laravel y rutas de autenticación: código 0.
- Autorización de CheckoutRequest y CancelSubscriptionRequest: verdaderas.
- Seis variantes TEST disponibles; acceso comercial del demo: falso.

No se ejecutaron las suites MySQL cuya allowlist apunta a otra copia/base.
No se modificaron assets ni dependencias y no fue necesario reconstruir Vite.

## Captura pendiente del fallo de registro en Azure

POST exacto: `https://gintly-app-web.azurewebsites.net/api/v1/auth/register`.
El cliente realiza antes GET `/sanctum/csrf-cookie`. El contrato de éxito es HTTP 201
con `data.business_slug` y `data.owner_email`; no autentica automáticamente.

No se creó ningún negocio en Azure en esta intervención. Antes de cualquier alta
de diagnóstico se debe comunicar y aprobar el destino efectivo, el entorno y un
nombre/correo de prueba identificables. No asumir que una BD pública sea QA.

Para capturar un intento autorizado o uno que el usuario realice por su cuenta:

1. Abrir DevTools → Network, activar Preserve log y filtrar `auth/register`.
2. Registrar hora y zona horaria, método POST, URL final, estado HTTP,
   Content-Type y existencia (no contenido) de Idempotency-Key y cabecera CSRF.
3. Anotar solo el código y mensaje SANITIZADOS y la estructura de respuesta.
   Si hay 201, verificar las dos claves bajo `data`; ocultar el correo.
   Si hay HTML, indicar HTML y título/error genérico, no copiar la página completa.
4. Anotar la excepción de consola sin payload, cookies ni cabeceras sensibles.
   No exportar HAR, trazas, Request Payload ni objetos completos de error.
5. Correlacionar esa hora con Log Stream y el log Laravel del contenedor REAL:
   conservar solo clase de excepción, código SQLSTATE si existe, nombre de
   tabla/columna y primer archivo/línea de la aplicación. No compartir SQL completo,
   parámetros, contraseña, cookies, .env ni secretos del proveedor.
6. Ante resultado incierto, conservar la pantalla y el intento. No recargar,
   modificar datos, rotar la clave ni repetir automáticamente el POST. Una
   recuperación posterior debe reutilizar exactamente clave y snapshot existentes.

La comprobación de si se persistieron negocio, propietario e idempotencia debe
hacerse por consulta de solo lectura autorizada sobre esa misma BD; no por un
segundo registro. No crear endpoints públicos para inspeccionar configuración.

## Pendiente de aplicar/verificar en Azure

- Publicar estos cambios de fuente únicamente cuando el responsable lo autorice.
- Verificar commit/digest de la imagen que App Service ejecuta; publicar `latest`
  en ACR no demuestra por sí solo qué contenedor está sirviendo la aplicación.
- Configurar los nombres exactos de variantes en App Settings, no copiar `.env`.
- TEST exige demo/test y variantes/credenciales TEST verificadas privadamente.
  Producción comercial exige commercial/live y sus seis variantes LIVE: no mezclar.
- Verificar esquema y estado de migraciones por mecanismos privados de despliegue.
  No usar una URL pública para migrar y no ejecutar migraciones remotas en esta tarea.
- Si hubiera caché de configuración/rutas en el destino, renovarla solamente como
  parte del despliegue aprobado. No se efectuó esa operación en Azure.
- Obtener la evidencia del POST y error correlacionado antes de atribuir o corregir
  el fallo del registro. Este documento no declara resuelto el registro de Azure.

## Archivos de esta intervención

- Modificado: `database/seeders/UserSeeder.php`.
- Modificado: `routes/web.php`.
- Creado: `tests/Unit/Mod01/DemoOwnerLinkTest.php`.
- Creado: `docs/Correccion_propietario_demo_y_billing.md`.
- Modificado local, secreto y NO integrable: `.env` (solo nombres de seis variables).

No se eliminaron archivos. `.env`, cachés, logs y dependencias no forman parte de
fuentes publicables. No se tocó `C:\Gintly-Codex\frontend-audit`, Backend-Claude,
MFA, Figma ni archivos de infraestructura/despliegue.
