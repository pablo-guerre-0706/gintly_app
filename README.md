# Gintly — Sistema de Gestión Integral de Negocios

Descripción: Sistema web **SaaS multi-tenant** para el control operativo, financiero y administrativo de PYMES (ferreterías, distribuidoras, comerciales y afines), orientada a propietarios que gestionan su negocio de forma presencial, delegada o remota. Cada operación queda registrada, es trazable a un responsable y se concilia entre las áreas operativas. Su diseño responde a un Documento de Requisitos de Negocio (BRD) y un Documento de Requerimientos Funcionales (FRD) formales.

---

## Tabla de contenido

- [Arquitectura](#arquitectura)
- [Stack tecnológico](#stack-tecnológico)
- [Módulos del sistema](#módulos-del-sistema)
- [Requisitos previos](#requisitos-previos)
- [Instalación y configuración local](#instalación-y-configuración-local)
- [Acceso al sistema](#acceso-al-sistema)
- [Roles y control de acceso](#roles-y-control-de-acceso)
- [Documentación del proyecto](#documentación-del-proyecto)
- [Autoría](#autoría)

---

## Arquitectura

Gintly es una aplicación web **SaaS multi-tenant** bajo el modelo *shared database, shared schema*: todos los negocios (tenants) operan sobre una misma base de datos, con aislamiento lógico garantizado por una columna `business_id` presente en cada tabla operativa.

Principios arquitectónicos clave:

- **Aislamiento por tenant.** Un *Global Scope* de Eloquent inyecta automáticamente el filtro `business_id` en cada consulta, de modo que ningún negocio accede a datos de otro. El aislamiento es transparente para la capa de aplicación.
- **Autorización por negocio.** El control de roles usa `spatie/laravel-permission` en modo *teams*, donde el `team_id` corresponde al `business_id`. Un mismo usuario puede tener roles distintos en negocios distintos.
- **Integridad transaccional.** Toda operación crítica (venta, retiro, recepción, abono, cierre de caja) se ejecuta dentro de una transacción atómica: se completa por entero o se revierte, sin estados intermedios.
- **Inmutabilidad y trazabilidad.** Los comprobantes fiscales y los libros de movimientos (inventario, caja, auditoría) son de solo inserción: nunca se editan ni se borran físicamente; se anulan o se compensan, preservando la pista de auditoría.
- **Diseño relacional en Tercera Forma Normal (3FN).** El modelo de datos elimina dependencias transitivas y evita redundancia; los valores derivados se calculan o se materializan de forma controlada, nunca se duplican como fuente.

---

## Stack tecnológico

**Backend:** PHP <8.3.30> · Laravel <12.x> 
**Base de datos:** MySQL 8 (InnoDB, diseño relacional en 2FN)
**Autorización:** spatie/laravel-permission (modo *teams*)
**Entorno local:** Laragon (Apache + MySQL) o equivalente
**Control de versiones:** Git + GitHub
**Estándar de código:**: PSR-12

---

## Módulos del sistema

El sistema se organiza en módulos funcionales, ordenados por capas de dependencia de datos (maestros → transaccionales → de control):

01 - Seguridad, Identidad y Auditoría.
02 - Catálogo y Datos Maestros.
03 - Inventario y Bodega.
04 - Compras, Proveedores y Recepción.
05 – Clientes.
06 - Gestión de Caja.
07 - Ventas y Facturación.
08 - Ventas al Crédito y Cuentas por Cobrar.
09 - Entregas y Retiros de mercancias.
10 - Devoluciones, Reingreso y Mermas.
11 - Conciliación, Alertas y Anomalías: Motor antifraude.
12 - Reportería, KPIs e Inteligencia de Negocios:

Cada módulo se encuentra detallado en la documentación del proyecto.

---

## Requisitos previos

- **PHP <8.3.30>** con las extensiones estándar de Laravel.
- **Composer** activo (incluido en Laragon).
- **MySQL 8** con un entorno local que provea Apache y MySQL (Laragon recomendado, o equivalente).
- **Git** para el control de versiones.

---

## Instalación y configuración local

### 1. Clonar el repositorio:

```bash
cd C:\laragon\www\
git clone https://github.com/pablo-guerre-0706/gintly_app.git
```

### 2. Navega a la carpeta del proyecto:

```bash
cd gintly_app
```

### 3. Instala las dependencias PHP:

```bash
composer install
```

### 4. Instala las dependencias JavaScript:

```bash
npm install
```

### 5. Configurar el entorno:

```bash
# Duplicar la plantilla de variables de entorno
cp .env.example .env

# Generar la clave de la aplicación
php artisan key:generate
```

### 6. Configurar la base de datos:

1. Crear una base de datos en MySQL llamada `gintly_app`.
2. Ajustar las credenciales en el archivo `.env`:

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gintly_app
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Ejecutar migraciones y seeders:

```bash
php artisan migrate --seed
```

**Nota de arquitectura:** el modo *teams* de `spatie/laravel-permission` debe estar activo (`'teams' => true` en `config/permission.php`) **antes** de correr las migraciones. El seeder crea un negocio de demostración con su "Consumidor Final" (cliente genérico) y las reglas base de anomalías, de modo que el sistema queda operativo tras la instalación.

### 8. Inicia el servidor de desarrollo:

```bash
# Servidor de Laravel
php artisan serve
```

```bash
# Servidor de Vite (Frontend)
npm run dev
```

---

## Acceso al sistema

Con los servicios de Laragon (Apache y MySQL) activos en Laragon, ingresa desde tu navegador web a:

* **Si usas Artisan Serve:** [http://127.0.0.1:8000](http://127.0.0.1:8000)
* **Si usas el Virtual Host de Laragon:** [http://gintly_app.test](http://gintly_app.test)

---

**Usuario de demostración (generado por el seeder):**
* **Rol:** Propietario
* **Usuario:** `demo@gintly.test`
* **Contraseña:** `password`

---

## Roles y control de acceso

El control de acceso se implementa con `spatie/laravel-permission` (roles y permisos por negocio) reforzado con *Middlewares* y *Policies* de Laravel, bajo el principio de menor privilegio:
**ROL-01** Propietario / Dirección. Acceso total, reportes financieros y gestión absoluta.
**ROL-02** Administrador. Supervisa, valida anomalías y tiene acceso general con permisos dados por el propietario.
**ROL-03** Usuario Operativo. Ejecuta operaciones diarias en formularios funcionales, navegación estándar.
**ROL-SYS** Sistema. Procesos automáticos: descuentos transaccionales, cron de conciliación, cálculo de discrepancias y enrutamiento de alertas.

---

## Documentación del proyecto

**BRD** Requerimientos de negocio, objetivos, alcance y KPIs
**FRD** Requerimientos funcionales por módulo, actores y reglas de negocio
**Diccionario de datos** Entidades, atributos, tipos y relaciones
**Diagrama ER** Modelo entidad-relación y modelo relacional 2FN

---

## Autoría y Créditos

### Ingeniería y Desarrollo
* **Pablo Antonio Guerrero Guillén**
* **Roberto Carlos Romero Blandón**
* **Gianfranco Ubau Torres**

### Diseño UI/UX
* **Juan Carlos Umanzor Cárcamo**

### Marketing y Comunicación
* **María Belén Cruz Rodríguez**
