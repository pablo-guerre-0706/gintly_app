# GINTLY · Documento de Requerimientos Funcionales — v1.1

**GINTLY**

Sistema de Gestión Integral de Negocios

## DOCUMENTO DE REQUERIMIENTOS FUNCIONALES

| | |
| --- | --- |
| **Versión** | 1.1 |
| **Estado** | Especificación funcional definitiva, sincronizada con la capa de modelos implementada |
| **Ámbito** | Módulos core integrados (MOD-01 a MOD-12). Fase 1 |
| **Documento previo** | Documento de Requisitos de Negocio (BRD) |
| **Documentos base** | Planteamiento del Problema y Propuesta de Sistema · BRD Gintly · Esquema de migraciones · Resumen Consolidado de la Etapa de Modelos |
| **Fecha** | 21 de julio de 2026 |

### Historial de revisiones

| **Versión** | **Fecha** | **Autor(es)** | **Descripción del cambio** |
| --- | --- | --- | --- |
| **1.0** | 26 de junio de 2026 | Equipo de Análisis Gintly | Versión base. Especificación funcional de los doce módulos core (MOD-01 a MOD-12) con 42 requerimientos funcionales, 22 errores transaccionales y 7 reglas de negocio, lista para modelado de datos. |
| **1.1** | 21 de julio de 2026 | Equipo de Análisis y Arquitectura Gintly | **Acoplamiento del FRD a la lógica del código final.** Se sincroniza íntegramente el documento con la capa de modelos, servicios y candados de motor efectivamente implementados y validados módulo por módulo. Cambios principales: (a) se corrige la nomenclatura de entidades y atributos a la del esquema canónico; (b) se sustituye la estrategia de concurrencia declarada en RF-03-05 de *bloqueo optimista* a **bloqueo pesimista** (`lockForUpdate`), conforme a la implementación real; (c) se documentan los candados de motor (CHECK, columnas generadas, índices únicos parciales) que hacen cumplir cada regla a nivel de base de datos; (d) se incorporan las entidades añadidas durante la implementación (`goods_receipt_items`, `document_sequences`) y los atributos materializados (`dispatched_quantity`, `returned_quantity`, `recipe_snapshot`, `counted_denominations`); (e) se precisan las excepciones y códigos HTTP conforme a las clases implementadas; (f) se explicita el patrón de **persistencia de evidencia con señalización de error** en discrepancias de compra y descuadres de caja; (g) se añaden **diez requerimientos nuevos** derivados de comportamientos reales del sistema (RF-01-05 aislamiento entre negocios, RF-01-06 aprovisionamiento automático, RF-03-06 costeo promedio ponderado, RF-05-03 cliente genérico protegido, RF-07-03 composición y liquidación fiscal, RF-07-04 reflejo dual del cobro, RF-07-05 anulación y liberación de compromisos, RF-10-04 reversión fiscal proporcional, RF-11-08 idempotencia de la detección, RF-12-03 cálculo e integridad de indicadores), pasando el documento de 51 a **61 requerimientos funcionales**; (h) se agrega el catálogo consolidado de errores y la matriz de trazabilidad requisito → artefacto de software; (i) se registran las decisiones de alcance Fase 1 / Fase 2 tomadas durante la implementación. Los modelos de entidades por módulo se remiten al diccionario de datos anexo, salvo el de MOD-02 que ya figuraba en la v1.0. |

### Aprobación del documento

| **Rol** | **Responsabilidad** | **Estado** |
| --- | --- | --- |
| Sponsor / Propietario del negocio | Aprueba alcance y reglas de negocio | Aprobado |
| Analista de Negocio | Elabora, valida y mantiene el documento | Aprobado |
| Líder Técnico / Arquitecto de Software | Valida correspondencia entre requerimiento e implementación | Aprobado (v1.1) |

---

## 1. Introducción

### 1.1 Propósito del documento

Define de manera detallada y verificable los requerimientos funcionales del sistema Gintly: describe el comportamiento esperado de la aplicación desde la perspectiva del negocio y consigna, para cada requerimiento, el mecanismo por el cual el sistema lo hace cumplir. Constituye la base contractual y técnica de referencia para analistas, desarrolladores, especialistas de calidad y partes interesadas.

**Naturaleza de la versión 1.1.** Mientras la v1.0 fue una especificación *previa* al modelado, la v1.1 es una especificación *conciliada con la implementación*: cada criterio de aceptación ha sido contrastado contra el modelo de datos, los guardas de modelo, los servicios de dominio y las restricciones de motor efectivamente construidos. Donde la implementación reveló una regla más precisa, más segura o técnicamente más correcta que la enunciada en la v1.0, este documento adopta la regla implementada y la deja explícita. Ello convierte al FRD en documentación definitiva y auditable, no en una intención de diseño.

### 1.2 Alcance funcional

El sistema proporcionará funcionalidades para el control operativo diario y el control administrativo y de supervisión; la gestión diferenciada de inventario lógico y bodega física; la gestión de caja, ventas, compras, entregas y devoluciones; las conciliaciones, validaciones cruzadas y alertas; y la reportería consolidada con métricas de desempeño. El alcance corresponde a la Fase 1 (v1.0 del producto) definida en el BRD.

### 1.3 Convención de identificación de requisitos

Cada requerimiento funcional se identifica con el patrón **RF-MM-NN**, donde MM es el número de módulo y NN el consecutivo dentro del módulo (ej. RF-02-05). Los errores transaccionales usan el prefijo **ERR-MM**, y las reglas de negocio el prefijo **BR-NN**. Esta codificación habilita la trazabilidad hacia casos de uso, historias de usuario, modelo de datos, artefactos de código y casos de prueba, en correspondencia con los requisitos de negocio (Req. 1–10) y KPIs (KPI-01–08) del BRD.

Los requerimientos introducidos en esta versión se marcan con la etiqueta **(Nuevo en v1.1)**; los que cambian de sustancia respecto de la v1.0 se marcan con **(Revisado en v1.1)**.

### 1.4 Definiciones y acrónimos

| **Término** | **Definición** |
| --- | --- |
| **POS** | Punto de venta (Point of Sale): pantalla/terminal donde se registran las ventas. |
| **Arqueo ciego** | Conteo físico de efectivo realizado sin ver el saldo teórico, para evitar sesgo en el conteo. |
| **3-Way Match** | Cruce de tres vías entre orden de compra, mercancía recibida y factura del proveedor. |
| **Bloqueo pesimista** | Estrategia de concurrencia que bloquea físicamente la fila afectada durante la transacción; el segundo proceso espera y relee el dato ya actualizado. Es la estrategia adoptada por el sistema. |
| **Reserva de stock** | Compromiso de existencias derivado de la facturación: la mercancía queda vendida pero permanece físicamente en bodega. No es descuento. |
| **Retiro (dispatch)** | Salida física real de la mercancía. Es el único evento que descuenta existencias. |
| **Congelamiento** | Copia inmutable de un dato maestro (precio, costo, nombre, receta) dentro de la transacción, para que su edición posterior no altere el histórico. |
| **Columna generada** | Columna cuyo valor calcula el motor de base de datos a partir de otras columnas; no es escribible por la aplicación. |
| **Candado de motor** | Restricción declarada en la base de datos (CHECK, UNIQUE, FK) que hace cumplir una regla con independencia del código de aplicación. |
| **Append-only** | Tabla que solo admite inserción: no se puede modificar ni eliminar sus registros. |
| **Idempotencia** | Propiedad por la cual repetir una operación no produce un efecto adicional. |
| **Tenant** | Negocio (`business`) al que pertenece un conjunto aislado de datos en el modelo SaaS multi-negocio. |
| **CxP / CxC** | Cuentas por Pagar / Cuentas por Cobrar. |
| **Borrado lógico** | Marcar un registro como inactivo o eliminado sin borrarlo físicamente, preservando la auditoría. |
| **KPI** | Indicador clave de desempeño. |

### 1.5 Actores del sistema

Los códigos coinciden con los roles del Planteamiento y del BRD; se añade ROL-SYS para los procesos automáticos. Los permisos se rigen por el principio de menor privilegio y se implementan bajo un esquema de roles por negocio (un usuario tiene exactamente un rol activo dentro de su tenant).

| **Código** | **Rol** | **Responsabilidad funcional** |
| --- | --- | --- |
| **ROL-01** | Propietario / Dirección | Autoriza excepciones críticas (anulación de facturas, aprobación de proveedores, resolución de discrepancias de recepción, desbloqueo de CxP, exceso de límite de crédito, reembolso en efectivo, resolución de anomalías), accede a reportes consolidados, KPIs y metas. Máxima autoridad de validación. |
| **ROL-02** | Administrador | Supervisa, justifica anomalías, autoriza egresos de caja, autoriza reversión de retiros y consolida información. Gestiona usuarios, catálogos maestros, proveedores, clientes y bodegas. |
| **ROL-03** | Usuario Operativo | Ejecuta operaciones diarias: apertura/cierre de caja y ventas (cajero); recepción, conteos, traspasos, retiros y devoluciones (bodeguero). |
| **ROL-SYS** | Sistema | Procesos automáticos: reservas y descuentos transaccionales, cálculo de discrepancias, generación de folios, marcado de cuentas vencidas, conciliaciones programadas, detección y deduplicación de anomalías, y cálculo de snapshots de KPI. |

**Tratamiento de ROL-SYS (reconciliación de autorización).** ROL-01/02/03 son roles HUMANOS; **ROL-SYS representa procesos automáticos y NO es asignable a personas ni puede iniciar sesión**. Defensa en profundidad implementada: (a) ROL-SYS queda fuera del catálogo de roles asignables por la API y fuera de la jerarquía humana (`RoleName::atLeast` lo excluye); (b) `AuthService` rechaza el login de cualquier cuenta con ROL-SYS; (c) el middleware `EnsureOperableUser` corta en CADA petición autenticada (API y web) a cuentas ROL-SYS o inactivas —invalidando su sesión— aunque ya tuvieran sesión emitida; (d) ninguna cuenta humana puede crear ni asignar ROL-SYS, y nadie concede un rol de autoridad superior a la suya (regla de rango única en `UserService`, idéntica en `POST /users` y `PUT /users/{user}/role`). Los procesos automáticos ejecutan Services internos sin autenticarse; los registros automáticos conservan `user_id = NULL`.

**Perfiles operativos de ROL-03 (Nuevo — Fase 3).** ROL-03 es un único rol; internamente admite **perfiles COMBINABLES**: `cajero`, `facturador`, `bodeguero`, `despachador`. Un usuario ROL-03 puede tener uno o varios. Todo ROL-03 exige **`branch_id` obligatorio** y **al menos un perfil** al crearse o convertirse. La asignación es persistente, normalizada y auditable (`user_operative_profiles`), compatible con Spatie teams; el mapa perfil→capacidades es la fuente única `config/profiles.php` (reutiliza el catálogo de permisos, sin duplicar). Un ROL-03 **sin perfiles queda bloqueado operativamente** (mínimo privilegio); a los ROL-03 preexistentes NO se les asignan perfiles por compatibilidad. Responsabilidades por perfil:

| Perfil | Responsabilidades |
| --- | --- |
| **Cajero** | Abrir/cerrar su propia sesión de caja, registrar movimientos permitidos, cobrar facturas y CxC (efectivo solo con su sesión propia abierta y caja de su sucursal). |
| **Facturador** | Consultar catálogo y clientes, crear/administrar ventas abiertas, confirmar ventas, emitir/consultar facturas, evaluar crédito. Un pago en efectivo exige además capacidad de cajero con sesión propia activa. |
| **Bodeguero** | Consultar bodegas y existencias de su sucursal; conteos físicos, traspasos y recepción de compra; consultar proveedores, órdenes de compra, CxP, devoluciones y notas de crédito de su sucursal; crear órdenes de compra en BORRADOR; parte física de devoluciones/reingresos/mermas. No aprueba/suspende proveedores, no emite/cancela órdenes, no paga/descongela CxP, no resuelve discrepancias ni autoriza reembolsos (ROL-01/ROL-02). |
| **Despachador** | Consultar facturas y saldos pendientes de entrega de su sucursal, registrar retiros totales/parciales, consultar sus despachos (no revertir). |

Las **Policies por recurso siguen siendo la autoridad final** (autorizan por nivel de rol); el catálogo de permisos + perfiles es el modelo de capacidades que alimenta `GET /me` (capacidades generales de interfaz, no autorización por recurso).

**Enforcement operativo (implementado).** La autorización de ROL-03 es aditiva por flujo: rol operativo + perfil requerido (`operativeCan`) + sucursal (`user.branch_id`) + negocio. Ningún ROL-03 obtiene una operación solo porque su rol Spatie contiene la unión de permisos. El efectivo exige perfil cajero, sesión propia abierta y caja de su sucursal; un facturador que cobre en efectivo necesita también cajero. En traspasos el origen es de su sucursal y la finalización corresponde a un bodeguero de la sucursal receptora. Contratos de apoyo al frontend: `GET /accounts-receivable/collectible` (CxC cobrables, cajero+, acotado por sucursal), `GET /dashboard/admin` (pendientes reales del negocio, ROL-02+), `GET /dashboard/operative` (secciones por perfil y sucursal, ROL-03+) y `GET /dashboard/kpis` (ruta canónica de KPIs de ROL-01, MOD-12). Las lecturas de evidencia para las decisiones de ROL-01 (anomalías, recepciones en discrepancia, CxP congelada, factura a anular, estado de crédito) usan endpoints de consulta existentes; no se introduce una entidad de solicitudes.

**Aislamiento de sucursal en los LISTADOS (microcierre ROL-03).** La Policy protege el detalle y la mutación por recurso, pero un índice no filtra filas; por eso cada listado operativo aplica además el alcance de sucursal del usuario (`Model::forOperator($user)`, trait reutilizable). Para ROL-03 el índice devuelve **solo** los recursos de su sucursal y un `branch_id` enviado por el cliente **no** amplía ese alcance; un ROL-03 sin sucursal no ve nada. La sucursal se resuelve de forma directa (ventas, facturas, órdenes de compra, devoluciones, retiros) o indirecta (conteos/recepciones por su bodega; traspasos por su bodega de origen o destino; CxP por su orden; notas de crédito por su factura). El detalle de otra sucursal del mismo negocio responde 403; el de otro negocio, 404. ROL-01/ROL-02 conservan el alcance de todo el negocio. Las capacidades de consulta del bodeguero declaradas en `config/profiles.php` (proveedores, compras, CxP, devoluciones y notas de crédito) quedan efectivamente utilizables bajo este aislamiento, sin ampliar las potestades exclusivas de ROL-01/ROL-02.

### 1.6 Principios transversales de cumplimiento (Nuevo en v1.1)

Todo requerimiento de este documento se hace cumplir sobre cuatro capas independientes. Esta arquitectura de *defensa en profundidad* es la razón por la que los criterios de aceptación son verificables y no meramente declarativos.

| **Capa** | **Mecanismo** | **Garantía funcional** |
| --- | --- | --- |
| **Motor de datos** | CHECK, UNIQUE, FOREIGN KEY, columnas generadas, índices únicos parciales | La regla se cumple aunque falle la aplicación. No es evadible por ninguna vía. |
| **Modelo de dominio** | Conversión de tipos, guardas de ciclo de vida, comportamientos reutilizables (inmutabilidad, aislamiento por negocio) | La regla acompaña al dato con independencia de quién lo escriba (interfaz, proceso programado, consola). |
| **Servicio de dominio** | Transacciones atómicas, bloqueo pesimista, orquestación entre módulos | Ninguna operación queda a medias; un solo componente es responsable de cada regla crítica. |
| **Borde de aplicación** | Validación de entrada, autorización por rol, contratos de respuesta | La entrada se valida y autoriza antes de alcanzar el dominio. |

**Precisión aritmética.** Todos los importes monetarios, costos y cantidades se representan con tipos decimales de escala fija y se operan con aritmética decimal exacta. El sistema no utiliza aritmética de coma flotante para dinero ni existencias, porque un error de redondeo en un sistema que cuadra la caja al centavo se manifiesta como un descuadre y dispara una alerta de fraude.

**Atomicidad universal.** Todos los errores transaccionales revierten la operación completa. El principio común: **ningún error deja el sistema a medias.**

**Excepción documentada al principio anterior (Nuevo en v1.1).** Existen dos situaciones en las que un hecho de negocio debe quedar registrado *aunque* la operación se señalice al usuario como fallida, porque la evidencia es indispensable para que un rol superior la resuelva: la **recepción con discrepancia de 3-Way Match** (MOD-04) y el **cierre de caja con descuadre** (MOD-06). En ambos casos la transacción se confirma —dejando el documento, sus líneas y el bloqueo correspondiente— y el error se señaliza posteriormente. El código HTTP de error es una **señal al operador**, no una reversión. Esta distinción está implementada de forma explícita y es verificable.

---

## 2. Requerimientos funcionales

Los requerimientos se agrupan en doce módulos funcionales. Cada requerimiento documenta actor responsable, prioridad (MoSCoW: Must / Should / Could), precondición y criterios de aceptación verificables, condición indispensable para que el requerimiento sea comprobable en la fase de pruebas.

Se dejan explícitos los errores transaccionales, con su código, condición de disparo, respuesta del sistema, código HTTP y excepción implementada.

---

### MOD-01 — Seguridad, Identidad y Auditoría

Gobierna el acceso al sistema, el aislamiento entre negocios, la segregación de funciones por rol y la trazabilidad íntegra de toda acción realizada. Es el módulo transversal sobre el que se apoya el resto de la plataforma: su modelo de datos es la raíz del esquema multi-negocio y el ancla de la bitácora de auditoría.


**Nota de arquitectura.** Existe una dependencia circular deliberada entre negocio, usuario y sucursal (un negocio tiene un propietario que es usuario; un usuario pertenece a una sucursal; una sucursal pertenece a un negocio). Se resuelve con claves foráneas diferidas: las columnas se crean planas y se cablean al final de la secuencia de migraciones. El grafo referencial queda completo, sin huecos.

| **RF-01-01** | **Gestión de usuarios y roles** | **ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir crear, modificar y desactivar usuarios, y asignarles un rol con permisos diferenciados por perfil (operativo, administrativo, directivo). |
| **Precondición** | *Sesión iniciada por un usuario con rol ROL-02 o superior.* |
| **Criterios de aceptación** | Un usuario tiene **exactamente un rol activo** a la vez; la asignación reemplaza el rol previo, no lo acumula. La desactivación conserva el usuario y su historial (borrado lógico, nunca físico). Un usuario sin rol asignado no puede autenticarse. El correo electrónico es único **dentro del negocio**, no globalmente: dos negocios distintos pueden registrar el mismo correo. El negocio al que pertenece el usuario nunca se acepta como dato de entrada: se deriva de la sesión autenticada. |

| **RF-01-02** | **Autenticación por credenciales únicas** *(Revisado en v1.1)* | **Todos** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Autenticar a cada usuario mediante credenciales únicas antes de permitir cualquier operación, resolviendo previamente el negocio al que pertenece. |
| **Precondición** | *Usuario dado de alta, activo y con rol asignado.* |
| **Criterios de aceptación** | Las contraseñas se almacenan cifradas mediante función de derivación de un solo sentido, nunca en texto plano; el cifrado se aplica automáticamente al asignar el valor. Dado que el correo es único por negocio, el flujo de autenticación **resuelve primero el negocio** (subdominio o selector) y luego valida las credenciales dentro de él. Un usuario inactivo o sin rol no puede autenticarse aunque las credenciales sean correctas. El bloqueo por intentos fallidos se aplica mediante **limitación de tasa por petición**, sin persistir contadores en la base de datos, con umbral parametrizable. El sistema no revela cuál dato falló. Toda sesión expira por inactividad según parámetro configurable. |

**ERR-01:** Credenciales inválidas, cuenta desactivada o límite de intentos alcanzado. Se dispara cuando un usuario intenta autenticarse con credenciales incorrectas, con la cuenta desactivada, sin rol asignado, o tras superar el número de intentos permitidos. El sistema no revela cuál dato falló, aplica bloqueo temporal por limitación de tasa y registra el intento. **HTTP 401** (credenciales) / **HTTP 429** (límite de intentos). Excepción: `AuthenticationException` / limitador de tasa.

| **RF-01-03** | **Bitácora de auditoría trazable** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Registrar operaciones relevantes con asociación inmutable al usuario autenticado, con fecha y hora. |
| **Precondición** | *Operación ejecutada por un usuario autenticado.* |
| **Criterios de aceptación** | Cada registro incluye: negocio, usuario, acción, entidad afectada (tipo e identificador), valor anterior, valor nuevo, dirección de origen y marca temporal. La bitácora es **estructuralmente de solo inserción**: no posee columna de modificación, su marca temporal de creación es inmutable incluso en memoria, y todo intento de modificación o borrado —proceda de la interfaz, de un proceso programado o de la consola— es rechazado. Un negocio o un usuario con registros de auditoría no puede eliminarse físicamente (restricción referencial). La bitácora es consultable y filtrable por ROL-01 y ROL-02, y no expone ninguna operación de escritura. |

**ERR-01B:** Intento de modificación de bitácora de auditoría. Se dispara si cualquier operación intenta modificar o eliminar un registro de auditoría. El sistema lo rechaza en el propio modelo, con independencia del origen de la llamada, y registra el intento como evento de seguridad. **HTTP 403**. Excepción: `InmutableAuditException`.

| **RF-01-04** | **Registro continuo de operaciones diarias** | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir el registro continuo de operaciones (ventas, consumos, entregas, movimientos de mercancía) de modo que cada operación impacte de inmediato en los módulos correspondientes. |
| **Precondición** | *Turno/caja abierto cuando la operación lo requiera.* |
| **Criterios de aceptación** | Cada operación confirmada actualiza en la misma transacción los módulos dependientes. Ninguna operación queda en estado intermedio indefinido ante un fallo (atomicidad). Las operaciones anidadas entre servicios (por ejemplo, facturación que reserva inventario y registra caja) se comportan como una sola unidad indivisible. |

| **RF-01-05** | **Aislamiento de información entre negocios** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Garantizar que la información de un negocio sea inaccesible desde otro, de forma automática y sin depender de que cada consulta recuerde aplicar el filtro. |
| **Precondición** | *Existe más de un negocio registrado en la plataforma.* |
| **Criterios de aceptación** | Toda entidad del sistema, sin excepción, pertenece a un negocio. El filtro por negocio se aplica **automáticamente a toda consulta** de las entidades de negocio, no como criterio manual. El identificador de negocio **nunca se acepta como dato de entrada** en ninguna operación: se deriva siempre de la sesión autenticada, lo que hace imposible inyectar el identificador de otro negocio. Los procesos programados, que no operan bajo sesión, recorren los negocios de forma explícita y acotada. Ninguna consulta agregada o de reportería puede devolver datos de un negocio distinto al del solicitante. |

| **RF-01-06** | **Aprovisionamiento automático del negocio** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Al darse de alta un negocio, crear automáticamente los registros mínimos indispensables para que pueda operar desde el primer momento. |
| **Precondición** | *Se registra un nuevo negocio en la plataforma.* |
| **Criterios de aceptación** | La creación de un negocio genera de forma automática y en la misma operación (vía `BusinessObserver`): (a) el **cliente genérico "Consumidor Final"**, único y protegido; (b) las **secuencias de numeración** de documentos (factura, nota de crédito y demás del contrato), inicializadas; (c) el **catálogo de reglas de anomalía** con sus umbrales por defecto; (d) la **configuración fiscal estándar** (regla general con la tasa del negocio); y (e) la **matriz de roles del negocio** (ROL-01/02/03 con sus permisos, bajo el team del negocio). El aprovisionamiento es idempotente: si los registros ya existen, no se duplican. El aprovisionamiento **NO** crea sucursales, bodegas ni cajas: esos recursos se administran después y no son parte de esta operación. Un negocio recién creado queda listo para que su propietario inicie sesión y configure dichos recursos. |

| **RF-01-07** | **Alta pública canónica de negocio y propietario** *(añadido en registro público)* | **Visitante público** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir que un visitante no autenticado registre atómicamente un negocio y su propietario mediante `POST /api/v1/auth/register`, sin autenticar al propietario (debe iniciar sesión después). |
| **Precondición** | *El solicitante NO tiene una sesión humana autenticada; aporta un header `Idempotency-Key` (UUID).* |
| **Criterios de aceptación** | El alta crea en UNA sola transacción: Business (con `plan='basic'` y `status='trial'` fijados por backend), el aprovisionamiento canónico del Observer (RF-01-06), el propietario activo (`branch_id` nulo), el vínculo `businesses.owner_user_id` y la asignación EXACTA de ROL-01 bajo el team del negocio; cualquier fallo revierte TODO sin filas parciales. Devuelve **201** con EXCLUSIVAMENTE `{ business_slug, owner_email }` (sin IDs internos, contraseña, hash, roles, permisos, tokens ni detalles de aprovisionamiento) y **no** autentica ni emite tokens. El registro **no** crea empleados, perfiles, suscripciones, cobros ni datos de pago. La validación es estricta (allowlist de claves; rechazo de claves privilegiadas; contraseña conforme a `Password::defaults()`; nombre combinado ≤ 150; zona horaria válida) y preserva la contraseña EXACTA. La idempotencia es persistente: misma clave + mismo payload → mismo 201 sin duplicar; misma clave + payload distinto → 409; el resultado original se conserva ante mutaciones posteriores. El slug deriva solo del nombre; ante colisión real del índice se sufija y, si se agota, responde 409 específico. Un visitante ya autenticado recibe 403. Hay límite de tasa propio del registro. |

---

### MOD-02 — Catálogo y Datos Maestros

Define los datos maestros del negocio: la información de referencia, de baja frecuencia de cambio, que debe existir antes de que cualquier transacción pueda registrarse. Constituye el Tier 1 de la arquitectura de datos: las llaves foráneas de las tablas transaccionales apuntan hacia aquí.

El catálogo unifica bajo un solo atributo `type` tres naturalezas distintas de ítem —producto simple, producto compuesto (platillos de menú, combos) y servicio— evitando modelar tres estructuras separadas. Esta decisión permite que el sistema atienda comercios de distinta índole (ferretería, distribuidora, restaurante, servicios) con un único modelo de producto, sin fragmentar la lógica de ventas ni de inventario.

**Modelo de entidades (arquitectura)** *(Nomenclatura revisada en v1.1 conforme al esquema canónico)*

| **Entidad** | **Naturaleza** | **Atributos clave** | **Relaciones (FK)** |
| --- | --- | --- | --- |
| **categories** | Maestro | id, business_id, name, **parent_id**, is_active | Auto-referencia (jerarquía padre-hijo); 1:N con products |
| **brands** | Maestro | id, business_id, name, is_active | 1:N con products (opcional) |
| **units_of_measure** | Maestro | id, business_id, name, abbreviation | 1:N con products y con product_recipes |
| **products** | Maestro | id, business_id, sku, name, **type** {simple│compound│service}, sale_price, cost, **tracks_inventory**, **tax_class** {standard│reduced│zero_rated│exempt} *(reemplazó a `is_taxable`, hoy accesor derivado; MOD-07)*, is_active | FK a categories, brands, units_of_measure, tax_rules |
| **product_recipes** | Maestro / puente | id, business_id, **compound_id**, **ingredient_id**, quantity, unit_id | Auto-referencia doble a products (compuesto e insumo); FK a units_of_measure |

> **Corrección de nomenclatura (v1.1).** La v1.0 nombraba estas entidades como `category_father_id`, `unit_measures`, `product_recipe`, `compuesto_id`, `insumo_id`, `controla_inventario` y `active`, con valores de tipo `{simple│compuesto│servicio}`. La nomenclatura canónica implementada es la de la tabla anterior. Se documenta la equivalencia para preservar la trazabilidad con la v1.0.

| **RF-02-01** | **Gestión de categorías de producto** | **ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir crear, editar y desactivar las categorías que clasifican los productos, con soporte de jerarquía padre-hijo (auto-referencia) para subcategorías. |
| **Precondición** | *Sesión iniciada por un usuario con rol ROL-02 o superior.* |
| **Criterios de aceptación** | El nombre de categoría es único dentro del negocio. Una categoría puede tener una categoría padre; el sistema **impide los ciclos, directos e indirectos**, recorriendo la cadena de ascendientes antes de persistir y rechazando la operación si el nodo aparece entre sus propios ancestros. La desactivación es lógica: una categoría con subcategorías o productos asociados no se elimina físicamente. |

| **RF-02-02** | **Gestión de marcas** | **ROL-02** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Permitir el registro, edición y desactivación de marcas para etiquetar productos. La marca es un atributo opcional del producto. |
| **Precondición** | *Sesión iniciada por un usuario con rol ROL-02 o superior.* |
| **Criterios de aceptación** | El nombre de la marca es único dentro del negocio. Un producto puede no tener marca (servicios o insumos genéricos). La marca admite borrado lógico; al desvincularse de un producto, el producto conserva su integridad quedando sin marca. |

| **RF-02-03** | **Gestión de unidades de medida** *(Revisado en v1.1)* | **ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Administrar el catálogo de unidades de medida (unidad, kilogramo, gramo, litro, porción, etc.) usadas para expresar existencias, precios por unidad y cantidades en las recetas. |
| **Precondición** | *Sesión iniciada por un usuario con rol ROL-02 o superior.* |
| **Criterios de aceptación** | Cada producto referencia exactamente una unidad de medida base. La abreviatura es única dentro del negocio. **Decisión de alcance:** la unidad de medida no admite desactivación ni borrado lógico; su protección es la restricción referencial estricta. En consecuencia, **no se elimina** una unidad referenciada por productos o por líneas de receta: el intento se rechaza con un mensaje que identifica la dependencia. |

| **RF-02-04** | **Registro del catálogo de productos y servicios** *(Revisado en v1.1)* | **ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Registrar y editar los ítems del catálogo bajo un atributo `type` que unifica tres naturalezas: **simple** (se compra y vende tal cual), **compuesto** (platillo de menú o combo, se arma a partir de insumos) y **servicio** (sin existencia física). Cada ítem lleva código/SKU, nombre, categoría, marca (opcional), unidad de medida, precio de venta, costo de referencia, indicador de control de inventario e indicador de gravabilidad fiscal. |
| **Precondición** | *Existen al menos una categoría (RF-02-01) y una unidad de medida (RF-02-03).* |
| **Criterios de aceptación** | El SKU es único dentro del negocio y **permanece inmutable una vez el producto tenga transacciones asociadas** —entendiendo por tales movimientos de inventario, líneas de venta o líneas de orden de compra—; el intento de modificarlo se rechaza. `type = servicio` **fuerza** el indicador de control de inventario a falso de manera automática al guardar, con independencia de lo que envíe el usuario, y el motor lo verifica. El indicador de gravabilidad determina si el ítem forma parte de la base imponible al facturar. Precio de venta ≥ 0 y costo ≥ 0. Un producto con movimientos o ventas no se elimina físicamente; solo se desactiva (borrado lógico, alineado con BR-04). |

**ERR-02:** SKU duplicado, nombre de maestro duplicado o referencia cíclica. Se dispara al intentar guardar un producto con un SKU ya existente en el negocio, una categoría con nombre duplicado, o una relación donde un elemento se contiene a sí mismo directa o indirectamente (jerarquía de categorías o composición de recetas). El sistema rechaza la operación antes de persistir y detalla el conflicto. **HTTP 422**. Excepción: `ValidationException` (duplicados) / `CyclicReferenceException` (ciclos).

| **RF-02-05** | **Definición de recetas y composición (producto compuesto)** *(Revisado en v1.1)* | **ROL-02** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Para productos de tipo compuesto, definir su receta: la lista de insumos y la cantidad de cada uno, expresada en su unidad de medida. Esta estructura es la base para la reserva y el descuento automático de insumos derivados de la venta. |
| **Precondición** | *Existen productos de tipo simple (insumos) y al menos un producto de tipo compuesto (RF-02-04).* |
| **Criterios de aceptación** | Un producto compuesto se compone de una o más líneas; cada línea registra insumo + cantidad + unidad, con cantidad estrictamente mayor que cero. El sistema impide la **auto-composición directa** —un producto no puede ser insumo de sí mismo— mediante una restricción del motor de datos, y los **ciclos indirectos** —A contiene B, B contiene A— mediante un recorrido del grafo de composición antes de persistir. Una misma pareja compuesto-insumo no puede repetirse. **Editar una receta no altera las ventas históricas ya registradas: la composición vigente se copia y se congela dentro de la línea de venta en el momento de la transacción**, y es esa copia —no la receta actual— la que gobierna la reserva, el descuento por retiro y el reingreso por devolución. |

| **RF-02-06** | **Estado, disponibilidad e integridad del catálogo** | **ROL-02 / ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Controlar el estado (activo/inactivo) y la disponibilidad para venta de cada ítem, y garantizar la integridad referencial de todo el módulo mediante borrado lógico. |
| **Precondición** | *El ítem existe en el catálogo.* |
| **Criterios de aceptación** | Solo los productos activos y disponibles se ofrecen en el punto de venta. Desactivar un ítem preserva su historial y sus referencias en transacciones pasadas. Ningún dato maestro con dependencias se elimina físicamente (alineado con BR-04); el borrado físico, cuando se intenta de forma directa, se bloquea y el sistema ofrece la desactivación lógica como alternativa. |

**ERR-02B:** Eliminación de maestro con dependencias. Se dispara al intentar borrar físicamente una categoría, unidad de medida o producto referenciado por otros registros. El sistema aplica restricción referencial, bloquea el borrado físico y ofrece la desactivación lógica. **HTTP 409**. Excepción: `RestrictDeleteException`.

**Consideraciones técnicas de integridad** *(Ampliadas en v1.1)*

- **Auto-referencia controlada:** tanto la jerarquía de categorías como la composición de recetas se auto-referencian. La composición de recetas cuenta además con una **restricción del motor** que impide la auto-composición directa; los ciclos indirectos se validan en la capa de dominio, dado que no son expresables como restricción declarativa.
- **Borrado lógico universal:** ningún dato maestro con dependencias se elimina físicamente (BR-04). La única excepción documentada es la unidad de medida, protegida por restricción referencial estricta en lugar de borrado lógico.
- **Unicidad e inmutabilidad del SKU:** el código de producto es único y se congela una vez existan transacciones asociadas, para no romper la referencia de las ventas y movimientos ya emitidos. La verificación cubre las tres fuentes transaccionales del sistema.
- **Congelamiento de receta en venta:** la composición vigente se persiste dentro de la línea de venta, de modo que una edición posterior de la receta no altere el histórico ni las operaciones derivadas (retiro, devolución).
- **Validación de pertenencia:** las referencias entre entidades garantizan **existencia**, no **pertenencia al mismo negocio**. La verificación de que categoría, marca, unidad e insumos pertenezcan al negocio activo se realiza en la validación de entrada.

---

### MOD-03 — Inventario Lógico y Bodega Física

Separa el inventario de la bodega, los concilia y controla la concurrencia sobre el stock para evitar sobreventa. Es el módulo que sostiene la regla más crítica del sistema: **no se puede vender ni entregar por debajo de cero.**


**Nota de arquitectura (Nuevo en v1.1).** El kardex `inventory_movements` registra **exclusivamente movimientos físicos** (entrada, salida, ajuste, traspaso). Las reservas derivadas de la facturación **no generan asiento de kardex**: modifican únicamente el saldo comprometido. Esta separación es deliberada y es la que permite que el kardex represente la verdad física del almacén sin contaminarse con compromisos comerciales. Cada movimiento del kardex referencia como máximo un documento de origen, restricción verificada por el motor.

| **RF-03-01** | **Inventario lógico actualizado** | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Mantener un inventario digital actualizado que refleje entradas, salidas y ajustes derivados de operaciones, compras o consumos. |
| **Precondición** | *Existe catálogo de productos.* |
| **Criterios de aceptación** | Toda operación con impacto en stock actualiza el inventario lógico dentro de la misma transacción que la origina. El inventario expone en todo momento tres cifras por producto y bodega: existencia física, cantidad comprometida y **disponible = física − comprometida**. El disponible se calcula y expone de forma derivada, sin almacenarse por separado, de modo que no puede desincronizarse. |

| **RF-03-02** | **Bodega física diferenciada** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Registrar y controlar la existencia real en bodega, diferenciada del inventario lógico: entradas físicas, salidas físicas, traspasos y ajustes por conteo o verificación. |
| **Precondición** | *Existe al menos una bodega definida.* |
| **Criterios de aceptación** | El sistema distingue saldo lógico de saldo físico por artículo y bodega. Los ajustes físicos exigen **motivo obligatorio y responsable**, y quedan clasificados como merma, sobrante o corrección. Cada sucursal admite **como máximo una bodega marcada como predeterminada**, restricción garantizada por el motor de datos; esa bodega es la que abastece por defecto las reservas de venta y los retiros. Un traspaso entre bodegas exige origen y destino distintos, verificado tanto por el motor como por el dominio, y genera dos asientos de kardex —salida en origen y entrada en destino— dentro de una única transacción. |

| **RF-03-03** | **Conciliación inventario ⇄ bodega** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Comparar periódicamente el inventario lógico con la existencia física, identificar diferencias, cuantificarlas y asociarlas a causas, responsables y acciones correctivas. |
| **Precondición** | *Existen movimientos que conciliar.* |
| **Criterios de aceptación** | El conteo físico registra la existencia declarada por el sistema en el momento del conteo y la contada por el operador. **La diferencia es una cifra derivada por el motor de datos, no un valor editable**: se calcula como contada menos sistema y no puede ser manipulada por ningún actor. Cada diferencia se marca como inconsistencia activa y se asocia a causa, responsable y acción correctiva. Al aplicarse el conteo, el saldo del sistema se lleva al valor contado y se genera un ajuste con su asiento de kardex, todo de forma atómica. Los faltantes no justificados se enrutan al módulo de anomalías. |

| **RF-03-04** | **Reserva por facturación y descuento por retiro** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Distinguir el compromiso comercial del movimiento físico: al confirmarse la facturación, comprometer las unidades; al registrarse el retiro, descontarlas efectivamente. |
| **Precondición** | *Facturación confirmada (evento de éxito).* |
| **Criterios de aceptación** | **La facturación dispara la reserva**: la cantidad comprometida sube, la existencia física no varía y **no se genera asiento de kardex**. El producto queda vendido pero sigue en la bodega. **El retiro dispara el descuento real**: la existencia física baja y la cantidad comprometida baja simultáneamente, y **sí se genera un asiento de salida en el kardex** vinculado al documento de retiro. La reserva de un producto compuesto se aplica sobre **sus insumos**, según la composición congelada en la línea de venta, y no sobre el compuesto. Los productos de tipo servicio y los que no controlan inventario no reservan ni descuentan. La reserva se toma de la bodega predeterminada de la sucursal emisora. |

**ERR-03:** Stock insuficiente. Se dispara cuando una reserva excedería el disponible (existencia menos comprometido), o cuando un retiro o traspaso excedería la existencia física. El sistema, dentro de la transacción y con la fila de saldo bloqueada, aborta y devuelve el producto y la bodega implicados. **HTTP 409**. Excepción: `InsufficientStockException`.

**ERR-03B:** Movimiento sin actualización de saldo (fallo de atomicidad). Se dispara si, dentro de la transacción, se altera un saldo pero falla el registro del asiento de kardex, o viceversa. El sistema revierte ambos (todo o nada); nunca deja un kardex sin su saldo ni un saldo sin su kardex. **HTTP 500** (controlado). Excepción: `InventorySyncException`.

| **RF-03-05** | **Control de concurrencia sobre el stock (bloqueo pesimista)** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Ante dos intentos simultáneos de comprometer o retirar el último artículo disponible, serializar el acceso a la fila de saldo de modo que solo uno prospere. |
| **Precondición** | *Stock disponible limitado.* |
| **Criterios de aceptación** | **El sistema aplica bloqueo pesimista sobre la fila de saldo dentro de la transacción.** El primer proceso en obtener el bloqueo confirma; el segundo **espera**, relee el saldo ya actualizado y, si resulta insuficiente, recibe un error controlado y legible que identifica el producto y la bodega. No es posible vender ni entregar por debajo de cero. Adicionalmente, el motor de datos impide de forma incondicional que la existencia o la cantidad comprometida sean negativas, y que lo comprometido supere a lo existente, lo que hace que la sobreventa sea estructuralmente imposible aunque fallara la capa de aplicación. |

> **Cambio sustantivo respecto de la v1.0.** La v1.0 especificaba *bloqueo optimista*. La implementación adoptó **bloqueo pesimista** por ser superior para el caso de contención sobre una única fila caliente: el optimista haría fallar al segundo proceso obligándolo a reintentar, mientras que el pesimista simplemente lo ordena, obteniendo el mismo resultado de negocio con menos reintentos. El resultado observable para el usuario —"stock insuficiente" para el segundo intento— es idéntico al descrito en la v1.0. El término se corrige en el glosario.

| **RF-03-06** | **Costeo de existencias por promedio ponderado** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Mantener el costo unitario de las existencias mediante el método de costo promedio ponderado, recalculado en cada entrada de mercancía. |
| **Precondición** | *Se registra una entrada de mercancía a una bodega.* |
| **Criterios de aceptación** | Toda entrada de mercancía —recepción de compra, reingreso por devolución o reversión de retiro— recalcula el costo promedio de la existencia como el cociente entre el valor total acumulado y la cantidad total resultante. **Existe un único punto en todo el sistema autorizado a realizar este cálculo**, y ningún módulo lo reimplementa ni lo altera. El costo se conserva con mayor precisión decimal que los importes de venta, para no arrastrar error de redondeo en inventarios de alta rotación. La existencia y el costo promedio solo pueden ser escritos por el componente responsable de inventario; ninguna interfaz permite editarlos directamente. |

| **RF-03-07** | **Asignación Bodega–Bodeguero (muchos-a-muchos, historial temporal)** *(añadido durante la implementación)* | **ROL-01 / ROL-02 / ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Habilitar administrativamente qué bodeguero opera qué bodega, con historial temporal append-only. Un bodeguero puede tener varias bodegas activas y una bodega varios bodegueros activos. |
| **Precondición** | *Existe una bodega activa y un usuario ROL-03 con perfil bodeguero en la misma sucursal.* |
| **Criterios de aceptación** | La asignación la administran ROL-01/ROL-02; usuario y bodega deben pertenecer al mismo negocio y a la misma sucursal; el usuario debe ser ROL-03 con perfil `bodeguero`. El par (bodega, bodeguero) activo es único (candado de motor). Reasignar finaliza la vigencia anterior y crea otra: el historial nunca se modifica (salvo el cierre único de vigencia) ni se borra. Un ROL-03 bodeguero **solo opera** (conteo físico, recepción de compra, traspaso —origen al crear, destino al completar— y demás mutaciones de inventario de su competencia) bodegas que tenga **activamente asignadas**; la restricción vive en los Services de inventario, no solo en el FormRequest. Los ajustes de inventario son potestad administrativa (ROL-02+): el perfil bodeguero no los habilita. ROL-01/ROL-02 no se acotan por asignación. Las asignaciones finalizadas permanecen como historial. |

| **RF-03-08** | **Visibilidad consolidada de inventario por bodega asignada** *(añadido en microcierre de visibilidad)* | **ROL-01 / ROL-02 / ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Exponer, por producto y bodega, la existencia registrada, la reservada, la disponible y el último conteo físico con su fecha, el saldo del sistema en ese momento, la diferencia y el estado de conciliación. |
| **Precondición** | *Existe saldo del producto en la bodega; puede o no existir un conteo físico previo.* |
| **Criterios de aceptación** | La consulta `/stock` (índice y detalle) adjunta el último conteo del par como bloque `last_count` SEPARADO de la existencia actual: el último conteo **no** se presenta como existencia física vigente. Las lecturas de ROL-03 respetan sus **asignaciones de bodega**, aun cuando varias bodegas pertenezcan a la misma sucursal (sin asignación activa no ve la bodega); ROL-01/ROL-02 ven todo el negocio. NUNCA se suman cantidades de productos con unidades distintas. La **diferencia** del conteo es un dato propio del conteo (columna generada) y **no desaparece por estar bajo el umbral** de la anomalía `faltante_inventario`: el umbral solo decide si se levanta una anomalía, no la visibilidad de la diferencia. Merma, tiempo sin movimiento e inmovilización solo se exponen si existen datos y reglas verificables (merma = ajuste existente); no se inventan métricas. |

| **RF-03-09** | **Disponibilidad para facturar y aviso de mínimo con recomendación de reposición** *(añadido en microcierre de visibilidad)* | **ROL-01 / ROL-02 / ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Ofrecer al facturador la disponibilidad de venta por producto de la bodega predeterminada de su sucursal, y avisar cuando el disponible alcance o caiga bajo el mínimo configurado, con los datos para iniciar una orden de compra. |
| **Precondición** | *El producto controla inventario; la sucursal tiene una bodega predeterminada (o activa).* |
| **Criterios de aceptación** | `/sales/availability` devuelve existencia/reservado/disponible de la **bodega predeterminada** de la sucursal (misma resolución que la reserva al emitir), sin exponer costos ni conceder acceso general a bodegas; autoriza al facturador (ROL-03 con `ventas.crear`/`facturas.crear`) y a ROL-01/ROL-02, acotado a su negocio y sucursal. Al emitir, la validación transaccional de stock se conserva: si otra operación agotó el disponible o la cantidad solicitada lo supera, se devuelve `INSUFFICIENT_STOCK` (409) **aunque no se haya alcanzado el mínimo**. El aviso de mínimo (`/stock/alerts`) es DERIVADO del estado vivo (disponible ≤ `min_stock`, sin inventar mínimos ni duplicar avisos, actualizándose al variar stock/reserva), visible para ROL-01/ROL-02 y el bodeguero de la bodega asignada, SEPARADO del catálogo de anomalías; incluye producto, bodega, disponible, mínimo y los datos de reposición (producto, unidad, sucursal, bodega, disponible, mínimo, cantidad sugerida). La acción de iniciar la orden solo se ofrece a quien tenga autorización de compras y **reutiliza** el flujo existente de órdenes (nunca se crea ni aprueba automáticamente). |

---

### MOD-04 — Compras, Proveedores y Recepción

Gestiona el ciclo de abastecimiento con validación de proveedores aprobados, cruce de tres vías en recepción y bloqueo de cuentas por pagar ante diferencias no justificadas. Es el módulo con mayor densidad de control anti-fraude del sistema.


> **Entidad añadida durante la implementación.** La v1.0 no contemplaba una tabla de líneas de recepción: la cantidad recibida se acumulaba únicamente sobre la línea de la orden de compra. Al implementar el 3-Way Match se detectó que una recepción con discrepancia queda congelada esperando resolución de ROL-01 **sin evidencia relacional de qué se recibió y a qué costo se disputó**, lo que es incompatible con el ADN auditable del sistema y con la posibilidad de resolver la discrepancia después. Se incorpora `goods_receipt_items` como evidencia por recepción; la cantidad acumulada en la línea de orden pasa a ser la suma de sus líneas de recepción.

| **RF-04-01** | **Gestión de compras y proveedores** *(Revisado en v1.1)* | **ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir el registro de compras, proveedores y recepción de mercancía, con su impacto en inventario y bodega, incluyendo el seguimiento de cuentas por pagar. |
| **Precondición** | *Catálogo de proveedores disponible.* |
| **Criterios de aceptación** | Cada recepción genera su cuenta por pagar asociada. La recepción impacta inventario y bodega de forma trazable, siempre a través del componente único de entrada de mercancía. El total esperado de una orden se **recalcula por el sistema** a partir de sus líneas y no se acepta como dato libre del usuario; el importe de cada línea se deriva de cantidad por costo pactado. Una orden solo es editable mientras esté en borrador. **Decisión de alcance Fase 1:** los pagos a proveedor actualizan directamente el importe pagado de la cuenta, sin tabla de trazabilidad de abonos individuales; el motor impide que el importe pagado supere el total o sea negativo. |

**ERR-04:** Discrepancia en 3-Way Match. Se dispara cuando la cantidad recibida acumulada excede la ordenada, cuando el costo facturado difiere del pactado más allá de la tolerancia, o cuando el total declarado de la factura del proveedor no coincide con la suma calculada. El sistema **detiene el ingreso a inventario**, congela la cuenta por pagar y exige resolución de ROL-01. **Comportamiento implementado:** la recepción, sus líneas de evidencia y la cuenta congelada **sí quedan persistidas** —son la evidencia que ROL-01 necesita para resolver—; el código de error señaliza al operador que no hubo recepción limpia, sin revertir el registro. **HTTP 409**. Excepción: `PurchaseMatchException`.

| **RF-04-02** | **Validación de proveedor aprobado** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Bloquear la creación y emisión de órdenes de compra dirigidas a entidades que no posean estado "Aprobado" en el catálogo maestro gestionado por el ROL-01. |
| **Precondición** | *Proveedor existente en catálogo.* |
| **Criterios de aceptación** | Solo proveedores en estado **aprobado** admiten órdenes de compra. La validación se ejecuta **dos veces**: al crear la orden y de nuevo al emitirla, porque el proveedor puede perder la aprobación en el intervalo. El cambio de estado del proveedor es potestad exclusiva del ROL-01. **La coherencia del estado es estructural:** un proveedor en estado aprobado exige obligatoriamente el registro de quién y cuándo lo aprobó; al perder la aprobación, esos metadatos se limpian automáticamente. Un proveedor nace en estado pendiente y los datos de aprobación nunca se aceptan como entrada del usuario. |

**ERR-04B:** Orden de compra a proveedor no aprobado. Se dispara al intentar crear o emitir una orden hacia un proveedor cuyo estado no es aprobado. El sistema bloquea la operación antes de persistir. **HTTP 422**. Excepción: `SupplierNotApprovedException`.

| **RF-04-03** | **Recepción con cruce de tres vías (3-Way Match)** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Al ingresar mercancía a bodega, exigir el registro de cantidades recibidas y costos facturados por línea, y ejecutar el cruce: Cantidad Recibida ⇄ Cantidad en Orden de Compra ⇄ Cantidad en Factura del Proveedor; y Costo Unitario Facturado ⇄ Costo Unitario Pactado. |
| **Precondición** | *Existe orden de compra en estado emitida o parcial, y factura del proveedor.* |
| **Criterios de aceptación** | El cruce se ejecuta como **evaluación previa sin efecto sobre los datos**: el sistema determina el veredicto antes de escribir nada, lo que lo hace verificable de forma aislada. Se contrastan tres dimensiones: (a) costo facturado contra costo pactado por línea; (b) cantidad recibida acumulada contra cantidad ordenada por línea; y (c) total declarado de la factura contra la suma calculada de las líneas. La tolerancia es parametrizable y su valor por defecto es cero. **Todas las líneas se persisten como evidencia, coincidan o no**, cada una marcada con su resultado individual. Las coincidencias permiten el ingreso a inventario mediante el componente único de entrada; las diferencias lo detienen (RF-04-04). |

| **RF-04-04** | **Bloqueo por diferencias de recepción y su resolución** *(Revisado en v1.1)* | **ROL-SYS / ROL-01** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Ante exceso de costo o diferencia de cantidad sin justificar, detener el ingreso al inventario físico y congelar la cuenta por pagar hasta la resolución del ROL-01. |
| **Precondición** | *3-Way Match con diferencia (RF-04-03).* |
| **Criterios de aceptación** | Diferencia detectada ⇒ **ni una unidad ingresa a inventario** y la cuenta por pagar nace en estado congelado. Una cuenta congelada **no admite pagos**. Solo el ROL-01 puede resolver la discrepancia, con dos vías: **aceptar**, que libera el ingreso a inventario reconstruyéndolo desde las líneas de evidencia persistidas, descongela la cuenta y registra al autorizante; o **rechazar**, que deja la recepción bloqueada y la cuenta congelada de forma terminal, sin borrado físico. El estado de la orden de compra se deriva automáticamente de lo efectivamente recibido: parcial o recibida. Toda resolución queda auditada con responsable y motivo. Adicionalmente, la discrepancia genera una anomalía de tipo *discrepancia de recepción* en el módulo de conciliación. |

> **Nota de diseño (v1.1).** El sistema **no impide a nivel de motor** que la cantidad recibida supere a la ordenada. Es deliberado: prohibirlo estructuralmente eliminaría la capacidad de **registrar** el exceso para que ROL-01 lo resuelva, que es precisamente el control que el negocio requiere. El exceso se detecta y bloquea como discrepancia, no se hace invisible.

| **RF-04-05** | **Proveedores candidatos y ubicaciones para el mapa** *(añadido durante la implementación)* | **ROL-01 / ROL-02** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Registrar proveedores candidatos (incluidos los descubiertos externamente) sin aprobarlos, geolocalizar sus direcciones mediante un adaptador intercambiable y exponer en un mapa solo los proveedores operativos y confirmados. |
| **Precondición** | *Sesión autenticada; la aprobación y la suspensión siguen siendo exclusivas de ROL-01.* |
| **Criterios de aceptación** | (1) ROL-01/ROL-02 crean proveedores que nacen `pendiente`; un resultado externo del mapa **jamás** se convierte en proveedor aprobado automáticamente. (2) Un proveedor tiene una o varias ubicaciones (`supplier_locations`) con dirección, coordenadas, procedencia de geocodificación, identificador externo, calidad, fechas de geocodificación/confirmación, confirmador y marca de principal; las coordenadas se validan por rango y hay una sola ubicación principal por proveedor. (3) La geocodificación usa un adaptador de Backend **intercambiable, configurable y con caché**; tras la aprobación se solicita de forma **independiente** y, si no hay proveedor configurado o falla, la ubicación queda pendiente **sin revertir** la aprobación. No se depende de Nominatim público para producción ni se exponen claves. (4) El marcador puede confirmarse o corregirse manualmente. (5) Al cambiar la dirección se **invalida** la confirmación (y las coordenadas) previas. (6) El mapa expone **solo** proveedores aprobados, activos y con ubicación **confirmada**, aislados por `business_id` de sesión; un proveedor suspendido o eliminado desaparece del mapa. |

---

### MOD-05 — Clientes, Perfilamiento y Fidelidad

Administra la información de clientes y construye dinámicamente su ficha analítica a partir de la actividad transaccional, con métricas parametrizables de frecuencia y fidelidad.

| **Nota de fase** |
| --- |
| En Fase 1 el alcance de clientes se limita al registro, la consulta de compras y la administración del cupo de crédito. El análisis avanzado (RF-05-01 métricas, RF-05-02 perfilamiento) corresponde a la Fase 2; se documenta aquí para la completitud y continuidad del diseño, indicando su fase puntual. |


| **RF-05-01** | **Gestión y métricas de clientes** *(Revisado en v1.1)* | **ROL-02** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Registrar información de clientes y generar métricas de: flujo diario/semanal/mensual, correspondencia venta–caja–inventario, correspondencia bodega–inventario, frecuencia de consumo, evolución de ventas por periodo, cumplimiento de metas y nivel de fidelidad. |
| **Precondición** | *Existe registro de clientes.* |
| **Criterios de aceptación** | **Fase 1 (implementado):** registro de cliente con tipo y número de documento, contacto, fecha de nacimiento, cupo de crédito e historial de compras. El número de documento es único dentro del negocio; se admiten múltiples clientes sin documento. El cupo de crédito no puede ser negativo, restricción garantizada por el motor. Un cliente admite **varias direcciones simultáneas sin que ninguna deba ser exclusiva**, decisión de negocio adoptada para no restringir a clientes con múltiples puntos de entrega (por ejemplo, contratistas). **Fase 2:** métricas avanzadas calculadas a partir de la actividad transaccional real, con periodos de análisis configurables. |

| **RF-05-02** | **Perfilamiento dinámico por identificación (Fase 2)** | **ROL-SYS** | **Could** |
| --- | --- | --- | --- |
| **Descripción** | Al asociar una identificación única (Cédula/RUC) a una factura, actualizar la ficha analítica del cliente: Frecuente = > X compras/visitas en 30 días (X parametrizable por el ROL-01); Ocasional = ≤ X en el mismo periodo. |
| **Precondición** | *Identificación asociada a la factura.* |
| **Criterios de aceptación** | El umbral X es parametrizable por el propietario. La clasificación se recalcula con cada nueva transacción asociada. Requisito planificado para la Fase 2 según el Roadmap del BRD; se materializará como tabla satélite poblada por proceso programado, sin alterar la entidad maestra de cliente. |

| **RF-05-03** | **Cliente genérico protegido ("Consumidor Final")** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Mantener por negocio un cliente genérico único e intocable, destinado a las ventas al contado sin identificación del comprador. |
| **Precondición** | *El negocio existe (creado por RF-01-06).* |
| **Criterios de aceptación** | Existe **exactamente un** cliente genérico por negocio, unicidad garantizada por el motor de datos: aunque dos procesos concurrentes intentaran crearlo, solo uno prospera. El cliente genérico **no puede crearse, editarse ni eliminarse** desde ninguna interfaz: su marca de genérico no se acepta como dato de entrada y solo el sistema puede asignarla. Los listados de clientes lo excluyen por defecto. **No admite ventas a crédito** (RF-08-01). |

**ERR-05:** Documento duplicado o intento de operar sobre el cliente genérico. Se dispara al registrar un número de documento ya existente en el negocio, o al intentar editar o eliminar el "Consumidor Final". El sistema rechaza y protege el registro genérico. **HTTP 422** (duplicado) / **HTTP 403** (genérico). Excepción: `ValidationException` / `ProtectedResourceException`.

**ERR-05B:** Cliente con cuentas por cobrar pendientes. Se dispara al intentar desactivar o eliminar un cliente que tiene saldo vivo en sus cuentas por cobrar —en estado pendiente, parcial o vencida—. El sistema lo impide para no dejar deuda huérfana. **HTTP 422**. Excepción: `CustomerHasReceivablesException`.

---

### MOD-06 — Gestión de Caja (Ingresos, Egresos y Arqueo)

Controla el ciclo de vida del efectivo por estación de trabajo: apertura con fondo, registro de movimientos, arqueo ciego y cierre con detección automática de discrepancias.


| **RF-06-01** | **Ciclo de caja (apertura, movimientos, cierre)** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir la apertura de caja, el registro de ingresos por ventas y cobros, el registro de egresos autorizados y la ejecución del cierre, asociando cada movimiento a su origen, responsable y concepto. |
| **Precondición** | *Usuario con rol operativo asignado a una estación.* |
| **Criterios de aceptación** | Cada movimiento queda ligado a origen, responsable, concepto, medio de pago y marca temporal, y es **inmutable una vez registrado**: no admite modificación ni borrado por ninguna vía. Los egresos autorizados **exigen obligatoriamente el registro del autorizante (ROL-02)**, condición verificada por el motor de datos. **No puede existir más de una apertura activa por caja**, ni **más de una apertura activa por usuario**: ambas restricciones se hacen cumplir mediante índices únicos parciales, de modo que un cajero debe cerrar su sesión antes de abrir otra caja. El tipo de movimiento debe ser coherente con su categoría (una venta o un cobro de crédito solo pueden ser ingreso; un retiro o un egreso autorizado solo pueden ser egreso); la categoría de ajuste admite ambos sentidos. |

| **RF-06-02** | **Bloqueo de POS sin apertura activa** | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Bloquear la pantalla de ventas de la terminal si no existe un registro de apertura activo para el cajero. |
| **Precondición** | *Intento de acceso al POS o de registrar un movimiento.* |
| **Criterios de aceptación** | Sin apertura activa el sistema no permite registrar ventas en efectivo, movimientos de caja ni cobros de crédito en efectivo. El sistema guía al usuario al flujo de apertura. La verificación se realiza con la sesión bloqueada dentro de la transacción, de modo que un movimiento no puede colarse en el instante exacto en que otro proceso cierra la caja. |

**ERR-06:** Operación sin sesión de caja activa. Se dispara cuando se intenta registrar una venta en efectivo, un movimiento de caja o un cobro de crédito en efectivo sin una sesión abierta, o cuando se intenta abrir una segunda sesión en la misma caja o por el mismo usuario. El sistema bloquea la operación y guía a la apertura. **HTTP 409**. Excepción: `NoActiveCashSessionException`.

| **RF-06-03** | **Flujo de apertura con fondo inicial** | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Exigir el ingreso obligatorio del monto de efectivo inicial (fondo de caja). El sistema registra usuario, caja y marca temporal. |
| **Precondición** | *No existe apertura activa para el usuario ni para la caja.* |
| **Criterios de aceptación** | El fondo inicial es obligatorio y numérico ≥ 0. Se persiste usuario, caja y marca temporal de la apertura. La sesión nace en estado abierta. El fondo inicial no se contabiliza dos veces: forma parte del saldo esperado por sí mismo y se excluye del recuento de movimientos. |

| **RF-06-04** | **Arqueo ciego (blind count)** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Al cierre de turno o jornada, ocultar el saldo teórico y solicitar el conteo físico del efectivo mediante el desglose de denominaciones (monedas y billetes). |
| **Precondición** | *Existe una apertura activa a cerrar.* |
| **Criterios de aceptación** | **El saldo teórico permanece oculto mientras la sesión esté abierta:** el sistema no expone el importe esperado ni la diferencia en ninguna consulta hasta que el cierre se ha ejecutado, y el importe esperado se calcula del lado del servidor **después** de recibir el conteo. El desglose por denominación es **obligatorio para poder cerrar** y **se persiste como evidencia del arqueo**. El sistema valida que la suma de valor por cantidad de cada denominación **iguale exactamente** el efectivo declarado; si no coincide, el cierre se rechaza indicando ambas cifras. |

> **Atributo añadido durante la implementación.** La v1.0 exigía el desglose de denominaciones como condición para cerrar, pero el modelo no preveía dónde almacenarlo: solo se guardaba el total contado. Un arqueo con descuadre disputado y sin el detalle del conteo es indefendible en auditoría. Se incorpora el atributo `counted_denominations` en la sesión de caja como evidencia estructurada del conteo.

> **Arqueo ciego INDEPENDIENTE (añadido durante la implementación).** Además del arqueo del cierre, el cajero puede realizar arqueos durante la sesión **abierta sin cerrarla** (`POST /api/v1/cash-sessions/{id}/counts`), con **historial append-only** de múltiples conteos. Cada arqueo congela evidencia inmutable (denominaciones, usuario, fecha) en la tabla `cash_counts`; el saldo esperado y la diferencia permanecen **ocultos antes** de registrar (arqueo ciego) y se **revelan después** en la respuesta y en el historial (`GET …/counts`). **Decisión de dominio:** un arqueo independiente es evidencia y **no** cambia el estado de la sesión ni genera anomalía; únicamente el **cierre formal** (RF-06-05) marca `descuadrada` y dispara la alerta `descuadre_caja`, de modo que la anomalía queda ligada a la reconciliación autoritativa y los conteos intermedios no producen ruido. Alcance de autorización idéntico a operar la caja: ROL-03 solo sobre su propia sesión y con perfil cajero; ROL-01/ROL-02 sobre cualquier sesión del negocio.

| **RF-06-05** | **Cierre y cálculo de discrepancia** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Al guardar el arqueo ciego, calcular automáticamente la diferencia entre el efectivo declarado y el saldo esperado. Cualquier diferencia bloquea el cierre limpio y dispara el módulo de alertas. |
| **Precondición** | *Arqueo ciego completado.* |
| **Criterios de aceptación** | El saldo esperado se calcula como: **fondo inicial + ingresos en efectivo − egresos en efectivo**, considerando **exclusivamente los movimientos cuyo medio de pago es efectivo**. Las transferencias y los pagos con tarjeta se registran pero **no afectan el efectivo esperado**, porque no ingresan a la gaveta física. **La diferencia es una cifra derivada por el motor de datos y no es editable por el cajero ni por ningún otro actor.** Diferencia igual a cero ⇒ sesión cerrada. Diferencia distinta de cero ⇒ **sesión marcada como descuadrada**, cierre limpio bloqueado y anomalía generada hacia MOD-11 para validación administrativa. |

**ERR-06B:** Cierre con discrepancia. Se dispara cuando el arqueo arroja una diferencia distinta de cero. El sistema marca la sesión como descuadrada, bloquea el cierre limpio y dispara la anomalía correspondiente. **Comportamiento implementado:** la sesión descuadrada, su conteo, su desglose y su anomalía **sí quedan persistidos** —son la evidencia que el administrador necesita para validar—; el código de error señaliza al cajero que el cierre no fue limpio, sin revertir el registro. La respuesta incluye el importe de la diferencia. **HTTP 422**. Excepción: `UnreconciledCashClosingException`.

| **RF-06-06** | **Doble moneda NIO/USD (efectivo multimoneda)** *(añadido durante la implementación)* | **ROL-03 / ROL-01 / ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Soportar efectivo en córdobas (NIO, moneda base) y dólares (USD) con contabilidad real por moneda. Toda operación en efectivo conserva su moneda, importe original, tasa de cambio con **snapshot por operación** y equivalente en NIO. |
| **Precondición** | Para operar una moneda extranjera debe existir un tipo de cambio vigente administrado (ROL-01/ROL-02); de lo contrario la operación se rechaza (**EXCHANGE_RATE_MISSING**, HTTP 422). |
| **Criterios de aceptación** | (1) La tasa la congela el servidor al instante de la operación; nunca se envía desde el cliente. NIO usa tasa 1. (2) Los saldos y el arqueo se reconcilian **por separado por moneda** (esperado/contado/diferencia para NIO y para USD en importe nativo). (3) Una diferencia en **NIO o en USD** marca la sesión **descuadrada** y genera anomalía, **aunque el consolidado NIO coincida**; el consolidado NIO es **informativo** y nunca oculta un descuadre individual. (4) Las ventas/cobros en efectivo admiten **pagos mixtos** NIO/USD contra una factura en NIO, saldando el total por la suma de equivalentes NIO. (5) El **vuelto** (cambio) registra moneda entregada y moneda del vuelto con efecto real en la gaveta por moneda (egreso `vuelto`), sin exceder el efectivo recibido. (6) El administrador gestiona el tipo de cambio con **vigencia**, responsable e **inmutabilidad histórica** (una corrección es una vigencia nueva). (7) Migraciones **aditivas**: la evidencia histórica se preserva como NIO tasa 1, sin reinterpretación. |

**ERR-06C:** Operación en moneda extranjera sin tasa vigente. Un movimiento, pago o vuelto en USD sin un tipo de cambio administrado vigente al instante se rechaza de forma controlada, sin inventar ni asumir tasa. **HTTP 422**, `code: EXCHANGE_RATE_MISSING`. Excepción: `ExchangeRateMissingException`.

| **RF-06-07** | **Asignación Caja–Cajero (historial temporal)** *(añadido durante la implementación)* | **ROL-01 / ROL-02 / ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Habilitar administrativamente qué cajero opera qué caja, con historial temporal append-only, como precondición para abrir la caja. |
| **Precondición** | *Existe una caja activa y un usuario ROL-03 con perfil cajero en la misma sucursal.* |
| **Criterios de aceptación** | La asignación la administran ROL-01/ROL-02; caja y cajero deben pertenecer al mismo negocio y a la misma sucursal; el usuario debe ser ROL-03 con perfil `cajero`. Una caja tiene a lo sumo **un cajero activo** y un cajero **una caja activa** (candados de motor). Un ROL-03 **solo puede listar y abrir la caja activa que tenga asignada**; sin asignación vigente, la apertura se rechaza (403) en el servicio, no solo en HTTP. No se puede asignar, reasignar, finalizar la asignación, ni desactivar o eliminar la caja **mientras exista una sesión abierta vinculada** (409). Reasignar finaliza la vigencia anterior y crea otra; el historial nunca se modifica (salvo el cierre único de vigencia) ni se borra, y las sesiones históricas conservan su cajero y caja originales. ROL-01/ROL-02 crean, consultan, cambian y finalizan asignaciones. |

**ERR-06D:** Conflicto de asignación de caja. Asignar una caja ocupada (`CASH_REGISTER_ALREADY_ASSIGNED`), un cajero ya asignado (`CASHIER_ALREADY_ASSIGNED`) o operar sobre una sesión abierta vinculada (`CASH_ASSIGNMENT_OPEN_SESSION`) devuelve **HTTP 409**. Desactivar/eliminar una caja con sesión abierta → **409** `CASH_REGISTER_OPEN_SESSION`. Excepción: `CashAssignmentConflictException`.

**RF-06-08 (ampliación del historial de sesiones):** el historial de sesiones de caja admite el filtro `branch_id`. ROL-01/ROL-02 filtran por cualquier sucursal de su negocio; ROL-03 conserva su alcance (solo sus propias sesiones). Una sucursal de otro negocio responde **404**, sin filtrar datos.

---

### MOD-07 — Ventas, Facturación e Inmutabilidad

Rige la emisión de comprobantes, la integridad contable de las transacciones y las garantías de cobro. Los documentos son inmutables por diseño para preservar la pista de auditoría. Es el módulo de mayor densidad de orquestación: coordina catálogo, inventario, caja y cuentas por cobrar en una sola transacción.


> **Entidad añadida durante la implementación.** La v1.0 exigía un folio secuencial único pero no definía el mecanismo para generarlo. Derivarlo del máximo folio existente es frágil bajo concurrencia: produce huecos, bloqueos y colisiones. Se incorpora `document_sequences` como contador dedicado por negocio y tipo de documento, reutilizable para las notas de crédito de MOD-10. La asignación de folio bloquea únicamente la fila del contador, no la tabla de facturas.

| **RF-07-01** | **Folio único e inmutabilidad transaccional** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Asignar a cada factura un folio alfanumérico secuencial único no modificable. Prohibir el borrado físico de transacciones; las anulaciones requieren aprobación digital (token del ROL-01) y conservan el estado "Anulada" manteniendo la pista de auditoría. |
| **Precondición** | *Transacción confirmada.* |
| **Criterios de aceptación** | El folio es secuencial, único y no reutilizable dentro del negocio. **Se genera exclusivamente del lado del servidor** a partir de un contador dedicado, bloqueado durante la asignación, y **nunca se acepta como dato de entrada**. Dos cajas facturando simultáneamente se serializan; ninguna repite folio. **La inmutabilidad es quirúrgica:** una vez emitida la factura, el núcleo fiscal —folio, subtotal, impuesto, descuento, total, fecha de emisión, cliente, sucursal, condición de pago y emisor— queda congelado y todo intento de modificarlo se rechaza en el propio modelo, con independencia del origen de la llamada; permanecen mutables únicamente el importe pagado, el estado de pago y la transición de anulación. No existe borrado físico de facturas. La anulación exige autorización de ROL-01, motivo obligatorio, y registra autorizante y marca temporal, condición de coherencia verificada por el motor de datos. |

| **RF-07-02** | **Garantía de cobro (pago íntegro)** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Impedir procesar una factura al contado si el conjunto de pagos no cubre el 100% del monto total neto. |
| **Precondición** | *Factura en proceso de cierre.* |
| **Criterios de aceptación** | La suma de pagos debe **igualar** el total neto para confirmar una venta al contado. Se admite pago mixto (efectivo, transferencia, tarjeta) siempre que la suma cubra el 100%. **La validación es previa a toda escritura:** si el pago es insuficiente, la operación se rechaza sin persistir nada —ni factura, ni folio consumido, ni reserva de inventario, ni movimiento de caja—. Las ventas a crédito quedan exceptuadas de esta regla y admiten saldo pendiente (MOD-08). Cada cobro se registra de forma **inmutable** con su medio de pago, referencia, responsable y marca temporal. El motor de datos impide de forma incondicional que el importe pagado sea negativo o supere el total de la factura. |

**ERR-07:** Cobro incompleto o folio en conflicto. Se dispara si la suma de pagos no cubre el total en una venta al contado, o si dos procesos intentaran tomar el mismo folio simultáneamente. En el primer caso el sistema rechaza sin efecto alguno; en el segundo, el bloqueo del contador previene la colisión y la excepción actúa como red de seguridad. **HTTP 422** (cobro) / **HTTP 409** (folio). Excepción: `IncompletePaymentException` / `FolioConflictException`.

**ERR-07B:** Intento de edición de factura emitida. Se dispara si se intenta modificar cualquier campo del núcleo fiscal de una factura en estado emitida. El sistema rechaza y ofrece únicamente la vía de la anulación con autorización de ROL-01. **HTTP 403**. Excepción: `ImmutableInvoiceException`.

| **RF-07-03** | **Composición de la venta, congelamiento y liquidación fiscal** *(Nuevo en v1.1)* | **ROL-03 / ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Registrar la venta como documento operativo previo a la factura, congelando los datos maestros aplicados, y liquidar el impuesto sobre la base gravable al facturar. |
| **Precondición** | *Existen productos activos y una sucursal con bodega predeterminada.* |
| **Criterios de aceptación** | La venta se abre como documento editable, admite líneas y se confirma; solo una venta **confirmada** puede facturarse, y una venta facturada no puede volver a facturarse. Al agregar una línea, el sistema **congela el nombre, el precio de venta, el costo unitario y —si el ítem es compuesto— la composición vigente**, de modo que una edición posterior del catálogo no altere la venta. El importe de línea y el subtotal son **derivados por el sistema**, nunca aceptados del cliente. Una factura puede consolidar **varias ventas** del mismo cliente y sucursal, o dividirse por cuenta. La liquidación fiscal aplica el criterio **impuesto exclusivo**: el impuesto se calcula sobre la base gravable —suma de las líneas cuyos productos están marcados como gravables— multiplicada por la tasa vigente del negocio, y el descuento a nivel de factura se aplica después del impuesto. Los importes de la factura se derivan íntegramente del servidor. **Mecanismo implementado (MOD-07, subsistema fiscal normalizado):** la gravabilidad se determina por la **clase fiscal** del producto (`tax_class` ∈ standard/reduced/zero_rated/exempt), que reemplazó al booleano `is_taxable`; la **tasa se resuelve por `tax_rules`** (clase fiscal + ámbito general del negocio o específico de sucursal) y por cada línea se **congela** la clase, la condición (gravado/tasa cero/exento), la tasa, la base gravable y el impuesto, junto a la regla usada (`sale_items.tax_rule_id`). La factura suma esos importes congelados. `businesses.tax_rate` queda como semilla del backfill y compatibilidad, no como fuente única. |

| **RF-07-04** | **Reflejo dual del cobro en efectivo** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Registrar todo cobro en dos libros complementarios: el registro fiscal del pago de la factura y, cuando corresponde, el movimiento de la gaveta de efectivo. |
| **Precondición** | *Se registra un cobro asociado a una factura.* |
| **Criterios de aceptación** | Todo cobro genera un registro de pago de factura, con independencia del medio. **Únicamente los cobros en efectivo generan además un movimiento de caja** de tipo ingreso y categoría venta, vinculado a la sesión activa y a la venta de origen. Los cobros por transferencia o tarjeta se registran fiscalmente pero **no afectan el efectivo esperado del arqueo**. Un cobro en efectivo exige sesión de caja activa; si no existe, toda la facturación se rechaza. Ambos registros se escriben en la misma transacción que la factura: o existen los dos, o no existe ninguno. Esta correspondencia entre libros es la que concilia el módulo de anomalías. |

| **RF-07-05** | **Anulación de factura y liberación de compromisos** *(Nuevo en v1.1)* | **ROL-01** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir anular una factura emitida, liberando los compromisos de inventario no materializados y revirtiendo los efectos financieros, sin borrar el documento. |
| **Precondición** | *Existe una factura en estado emitida.* |
| **Criterios de aceptación** | Solo una factura **emitida** puede anularse, y solo por ROL-01 con motivo obligatorio. La anulación **libera únicamente el remanente no entregado** de cada línea —cantidad facturada menos cantidad ya retirada—: lo que el cliente ya recibió físicamente no vuelve por anulación, sino que requiere devolución formal (MOD-10). Si la factura era a crédito, su cuenta por cobrar se revierte conservando los abonos ya recibidos (RF-08-07). La factura conserva su folio, su importe y toda su información, cambiando únicamente su estado a anulada y registrando autorizante, fecha y motivo. |

---

### MOD-08 — Ventas al Crédito y Cuentas por Cobrar

Gestiona la financiación de ventas: la emisión de comprobantes a crédito, el saldo adeudado y el registro trazable de sus abonos. Opera como espejo de Compras/CxP del lado del cliente, anclando siempre al comprobante fiscal (factura), no al pedido. Incorpora el control preventivo de límite de crédito y la sincronización atómica entre la cuenta por cobrar, la factura y la caja.


> **Decisión estructural adoptada en la implementación.** El saldo pendiente **no es una columna escribible** sino una **columna derivada por el motor de datos** como total menos pagado, acompañada de una restricción que impide que sea negativo. La consecuencia funcional es que el sobre-abono resulta estructuralmente imposible: aunque fallara la validación de la aplicación, el intento de registrar un pago superior al total haría negativo el saldo derivado y el motor abortaría la transacción.

| **RF-08-01** | **Emisión de la venta a crédito** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir emitir una factura con condición de pago a crédito, generando automáticamente su cuenta por cobrar asociada por el saldo pendiente, en la misma transacción de emisión. |
| **Precondición** | *Existe una factura en proceso con condición de pago a crédito y un cliente identificado (no genérico).* |
| **Criterios de aceptación** | Toda factura a crédito genera **exactamente una** cuenta por cobrar, unicidad garantizada por el motor de datos, con importe total igual al de la factura y saldo derivado como total menos pagado. **No se permite emitir una venta a crédito al cliente genérico "Consumidor Final"**. Si la factura registra un pago inicial parcial, la cuenta nace con ese importe pagado y en estado parcial; si no, nace en pendiente. **La factura y su cuenta se crean atómicamente: si una falla, ninguna persiste.** Una cuenta por cobrar **no puede nacer en estado vencida**, dado que ese estado es derivado del tiempo (RF-08-05). El plazo de crédito por defecto es de 30 días desde la emisión. |

| **RF-08-02** | **Validación del límite de crédito** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Antes de confirmar la venta a crédito, validar que la deuda vigente del cliente más el nuevo monto no supere su límite de crédito autorizado. |
| **Precondición** | *El cliente tiene un límite de crédito definido, mayor que cero para operar a crédito.* |
| **Criterios de aceptación** | El sistema calcula la **exposición** como la suma de los saldos de las cuentas por cobrar del cliente en estado pendiente, parcial o vencida, más el total de la nueva venta. Si esa suma excede el límite, la venta a crédito **se rechaza** y requiere autorización explícita de ROL-01 para proceder. Un cliente con límite igual a cero **no puede operar a crédito**; el sistema bloquea la opción. **La validación es una operación de solo lectura, previa a toda escritura:** si falla, no se persiste nada. La respuesta de rechazo expone la exposición calculada y el límite vigente, de modo que la decisión sea auditable. El resultado de la validación —aprobado, rechazado, autorizado por excepción— queda registrado y es trazable. |

**ERR-08:** Límite de crédito excedido o sobre-abono. Se dispara al emitir una venta a crédito que supera el cupo disponible del cliente, o al registrar un abono mayor que el saldo pendiente. El sistema rechaza —o exige autorización de ROL-01 en el caso del límite— y **nunca deja el saldo negativo**, garantía sostenida tanto por la validación del servicio como por la restricción del motor. **HTTP 422**. Excepción: `CreditLimitExceededException` / `OverpaymentException`.

| **RF-08-03** | **Registro de abonos a cuentas por cobrar** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir registrar pagos parciales o totales (abonos) contra una cuenta por cobrar existente, actualizando el saldo y generando el ingreso correspondiente en caja. |
| **Precondición** | *Existe una cuenta por cobrar con saldo mayor que cero, y una sesión de caja activa cuando el abono ingresa en efectivo.* |
| **Criterios de aceptación** | El monto del abono debe ser mayor que cero y **no puede exceder el saldo** de la cuenta. El registro del abono actualiza atómicamente, en **cinco pasos indivisibles**: (1) inserta el abono; (2) incrementa el importe pagado; (3) el motor recalcula el saldo derivado; (4) sincroniza el estado de la cuenta y el estado de pago de la factura; (5) cuando el abono ingresa en efectivo, genera el movimiento de caja de tipo ingreso con categoría **cobro de crédito**. Si cualquiera de los pasos falla, se revierten todos. Dos abonos concurrentes sobre la misma cuenta se serializan mediante bloqueo de la fila. Cada abono queda asociado a responsable, fecha y medio de pago, y es **inmutable una vez registrado**. |

**ERR-08B:** Abono sin sesión de caja activa. Se dispara al registrar un abono en efectivo sin una sesión de caja abierta que reciba el ingreso. El sistema exige apertura de caja antes de aceptar el cobro. **HTTP 409**. Excepción: `NoActiveCashSessionException` (compartida con MOD-06).

| **RF-08-04** | **Sincronización de estado de cuenta ⇄ factura** | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Mantener coherentes, en la misma transacción, el estado de la cuenta por cobrar y el estado de pago de la factura que la originó. |
| **Precondición** | *Se registró un abono (RF-08-03) o una nota de crédito que afecta la cuenta.* |
| **Criterios de aceptación** | Si el saldo llega a cero, la cuenta pasa a pagada y la factura a estado de pago **pagada**. Si el saldo es mayor que cero pero se ha abonado algo, ambas quedan en estado **parcial**. Si no se ha abonado nada, ambas quedan en **pendiente**. **Nunca puede existir una cuenta pagada con una factura pendiente, ni viceversa**, porque la sincronización ocurre dentro de la misma transacción y sobre la misma operación. La actualización del estado de pago de la factura está expresamente permitida por el guarda de inmutabilidad, que congela únicamente el núcleo fiscal. Toda transición queda registrada con marca temporal. |

| **RF-08-05** | **Marcado de cuentas vencidas** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Identificar automáticamente, mediante un proceso programado, las cuentas por cobrar cuyo plazo de pago ha expirado y mantienen saldo pendiente. |
| **Precondición** | *Existen cuentas por cobrar con fecha de vencimiento definida y saldo mayor que cero.* |
| **Criterios de aceptación** | Un **proceso programado de ejecución diaria** marca como vencida toda cuenta en estado pendiente o parcial cuya fecha de vencimiento sea anterior a la fecha actual y conserve saldo. **El estado vencida no lo asigna ningún usuario manualmente: es derivado del tiempo**, y el sistema rechaza que una cuenta nazca en ese estado. El marcado dispara la alerta correspondiente en el módulo de Conciliación y Anomalías, con deduplicación garantizada. El proceso recorre todos los negocios de forma explícita, dado que se ejecuta sin sesión de usuario. |

| **RF-08-06** | **Consulta de estado de cuenta del cliente** | **ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Proveer una vista consolidada del estado de crédito de un cliente: su límite, su deuda vigente, sus cuentas abiertas y el historial de abonos. |
| **Precondición** | *El cliente tiene al menos una cuenta por cobrar registrada.* |
| **Criterios de aceptación** | La consulta muestra el límite de crédito, la **exposición actual** (suma de saldos de cuentas pendientes, parciales y vencidas) y el **crédito disponible** (límite menos exposición, con piso en cero). Lista las cuentas por cobrar con su estado, saldo y fecha de vencimiento. Permite ver el detalle de abonos aplicados a cada cuenta, con responsable, fecha y medio de pago. **Todos los importes provienen de datos reales registrados, sin cálculos manuales**, y el saldo se lee de la cifra derivada por el motor. |

| **RF-08-07** | **Reversión de crédito por anulación o devolución** *(Revisado en v1.1)* | **ROL-01** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Garantizar que la anulación de una factura a crédito o una devolución asociada ajuste correctamente la cuenta por cobrar, preservando la trazabilidad de los abonos ya recibidos. |
| **Precondición** | *Existe una cuenta por cobrar vinculada a una factura que se anula o sobre la que se emite una nota de crédito.* |
| **Criterios de aceptación** | La anulación **salda la cuenta llevando su importe total al monto ya pagado**, dejando saldo cero y estado trazable. La nota de crédito **reduce el importe total** de la cuenta por el monto correspondiente, sin que el resultado pueda quedar por debajo de lo ya abonado. En ambos casos, **los abonos recibidos no se eliminan: permanecen como evidencia** (alineado con BR-07). El resarcimiento del excedente ya abonado se resuelve por reembolso o saldo a favor, según la política de devoluciones (MOD-10). Una cuenta afectada por anulación total queda con saldo cero y estado trazable, **nunca borrada físicamente**. Toda reversión exige autorización de ROL-01 y queda registrada con motivo. |


---

### MOD-09 — Entregas y Retiros de Mercancías (Fase 1, alcance restringido)

Gestiona el retiro físico de la mercancía facturada, permitiendo entregas totales o parciales y diferidas en el tiempo. Controla el saldo pendiente de entrega por factura y es **el disparador único del descuento de inventario**. No incluye logística de reparto, rutas ni control de SLA, reservados para Fase 2.


> **Atributo materializado añadido en la implementación.** La v1.0 exigía exponer el saldo pendiente de entrega por línea, pero el modelo no preveía dónde acumular lo ya retirado, lo que obligaba a sumar todos los retiros previos en cada validación —costoso, expuesto a condiciones de carrera y sin posibilidad de candado de motor—. Se incorpora `sale_items.dispatched_quantity` como acumulado materializado, con **restricción de motor que impide que supere lo facturado**. Con ello el pendiente es una resta directa y el sobre-retiro es estructuralmente imposible.

| **RF-09-01** | **Registro de retiro (entrega de mercancía)** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir registrar el retiro físico, total o parcial, de los productos de una factura emitida, generando el descuento de inventario correspondiente en el momento del retiro. |
| **Precondición** | *Existe una factura emitida, no anulada, con productos que controlan inventario y saldo pendiente de entrega mayor que cero.* |
| **Criterios de aceptación** | Un retiro registra, por cada línea, la cantidad efectivamente entregada, que **no puede exceder el saldo pendiente de esa línea**. El retiro dispara, de forma atómica, el **movimiento de inventario de salida** —que baja la existencia física y consume simultáneamente la cantidad comprometida— y la actualización del saldo en la bodega de origen, que es la bodega predeterminada de la sucursal. Los productos **compuestos descuentan sus insumos** según la composición congelada en la línea de venta, en plena coherencia con la reserva efectuada al facturar. Una factura puede tener **múltiples retiros en fechas distintas** hasta completar las cantidades facturadas. Cada retiro queda asociado a un responsable, un receptor declarado, una bodega de origen y una marca temporal. Retiros concurrentes sobre la misma línea se serializan mediante bloqueo de la fila. |

| **RF-09-02** | **Control del saldo pendiente de entrega** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Mantener y exponer, por factura y por línea, la cantidad facturada, la cantidad ya retirada y el saldo pendiente de entrega. |
| **Precondición** | *Existe al menos una factura con productos sujetos a entrega.* |
| **Criterios de aceptación** | Por cada línea de factura, el sistema mantiene la cantidad retirada acumulada como dato materializado, y expone el **pendiente = cantidad facturada − cantidad retirada acumulada** como cifra derivada, sin recálculo manual ni agregación en tiempo de consulta. El estado de entrega de la factura se deriva automáticamente: **pendiente** (nada retirado), **parcial** (retirado en parte) o **completado** (todo retirado). No es posible registrar un retiro que haga negativo el saldo pendiente de ninguna línea: el motor de datos lo impide de forma incondicional. El saldo pendiente es consultable en cualquier momento. |

| **RF-09-03** | **Validación de coherencia venta–entrega** | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Garantizar que la suma de lo retirado a lo largo del tiempo nunca supere lo facturado, y que el inventario solo se descuente por retiros efectivos. |
| **Precondición** | *Se registra un retiro (RF-09-01).* |
| **Criterios de aceptación** | La cantidad retirada acumulada es siempre menor o igual a la facturada, garantía sostenida por la validación del servicio y por la restricción del motor. **Ningún producto que controla inventario se descuenta de existencias sin un retiro registrado que lo respalde**: el retiro es el único origen posible de una salida física derivada de una venta. Los productos de tipo servicio **no generan retiro ni descuento de inventario** y el sistema rechaza el intento. Toda inconsistencia detectada entre lo facturado y lo retirado se enruta al módulo de Conciliación y Anomalías. |

**ERR-09:** Retiro mayor al saldo pendiente. Se dispara cuando un retiro excede la cantidad facturada menos la ya retirada en una línea. El sistema aborta la transacción **antes de descontar inventario** y expone en la respuesta el saldo pendiente real y la cantidad solicitada. **HTTP 422**. Excepción: `DispatchExceedsBalanceException`.

**ERR-09B:** Retiro sobre factura anulada. Se dispara al intentar registrar un retiro contra una factura en estado anulada. El sistema bloquea la salida de inventario porque el comprobante ya no es válido; la verificación se realiza con la factura bloqueada dentro de la transacción. **HTTP 409**. Excepción: `DispatchOnVoidedInvoiceException`.

| **RF-09-04** | **Reversión de retiro** *(Revisado en v1.1)* | **ROL-02** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Permitir revertir un retiro registrado por error, devolviendo las cantidades al saldo pendiente y reingresando el stock a la bodega de origen. |
| **Precondición** | *Existe un retiro en estado registrado.* |
| **Criterios de aceptación** | La reversión genera un **movimiento de inventario de entrada** que restituye las cantidades a la bodega de origen y, además, **vuelve a comprometer (reservar) esas cantidades**, dado que la factura sigue vigente y la mercancía continúa vendida; omitir la re-reserva dejaría stock aparentemente libre pese a estar comprometido. El saldo pendiente de entrega se incrementa en las cantidades revertidas. Los productos compuestos reingresan y re-reservan **sus insumos**, según la misma composición congelada. La reversión exige autorización de ROL-02, motivo obligatorio, y queda trazable: el retiro original **no se borra, se marca como revertido** con responsable y marca temporal, coherencia verificada por el motor. **Decisión de alcance Fase 1:** el reingreso de insumos por reversión se valora al costo promedio ponderado vigente; el congelamiento del costo exacto del momento del retiro queda diferido a Fase 2. |

---

### MOD-10 — Devoluciones, Reingreso y Mermas

Formaliza la trazabilidad de retornos con catálogos cerrados de motivos, define el destino de la mercancía devuelta (reingreso al stock o merma con impacto en pérdidas) y resuelve el resarcimiento al cliente por la vía contablemente correcta.


> **Atributo materializado añadido en la implementación.** Por simetría con MOD-09, se incorpora `sale_items.returned_quantity` como acumulado devuelto, con una restricción de motor que impide que supere a **lo entregado** (no a lo facturado). Esta restricción es la traducción literal de ERR-10: no se puede devolver lo que nunca se retiró físicamente. La trilogía de saldos de la línea de venta queda así encadenada: **devuelto ≤ entregado ≤ facturado**.

| **RF-10-01** | **Trazabilidad de retornos** *(Revisado en v1.1)* | **ROL-03** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Exigir de forma obligatoria en devoluciones: factura original, producto, línea de venta de origen, cantidad, destino de la mercancía (apto para reingreso / merma dañada) y código de motivo de catálogo cerrado. |
| **Precondición** | *Existe factura original asociable con mercancía efectivamente entregada.* |
| **Criterios de aceptación** | Todos los campos obligatorios deben completarse para registrar la devolución. El motivo se selecciona de un **catálogo cerrado** —vencido, defecto de fábrica, error de despacho, insatisfacción, otro— y nunca de texto libre. El sistema **sugiere el destino según el motivo** y **rechaza las combinaciones incoherentes**: un producto vencido o con defecto de fábrica no puede reingresar al stock vendible. El precio unitario aplicado es el **congelado en la factura original**, no el vigente en catálogo. **Toda devolución genera exactamente una nota de crédito**, unicidad garantizada por el motor de datos, con folio secuencial propio tomado del contador de notas de crédito del negocio. |

| **RF-10-02** | **Reingreso vs. Merma** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Si el producto es apto para reingreso, sumar las unidades al inventario disponible y recalcular el costo promedio ponderado. Si es merma dañada, registrarlo como pérdida, afectando negativamente el balance operativo de la sucursal. |
| **Precondición** | *Devolución registrada (RF-10-01).* |
| **Criterios de aceptación** | El **reingreso se valora a costo**, nunca a precio de venta —hacerlo inflaría el costo del inventario y falsearía el margen—, utilizando el costo unitario congelado en la línea de venta, y **recalcula el costo promedio ponderado** a través del componente único de entrada de mercancía (RF-03-06). La **merma no reingresa al stock vendible**: se registra como ajuste de inventario de tipo merma, con motivo obligatorio y responsable, e impacta el balance de la sucursal. Los productos compuestos reingresan **sus insumos** según la composición congelada. Los productos de tipo servicio y los que no controlan inventario no generan movimiento físico alguno. |

| **RF-10-03** | **Reembolso y resarcimiento al cliente** *(Revisado en v1.1)* | **ROL-01 / ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Al procesar una devolución con su nota de crédito, resarcir al cliente por el monto devuelto mediante una de tres vías, determinadas por la condición de pago de la factura original: reembolso en efectivo por caja, saldo a favor del cliente, o reducción de su cuenta por cobrar. |
| **Precondición** | *Existe una nota de crédito emitida asociada a una factura, y una sesión de caja activa cuando el resarcimiento sea reembolso en efectivo.* |
| **Criterios de aceptación** | El sistema **determina la vía automáticamente** según la factura original: si fue **al contado y está pagada**, procede reembolso en efectivo o saldo a favor; si fue **a crédito con saldo pendiente**, la nota de crédito **reduce primero el saldo de la cuenta por cobrar** antes de generar cualquier devolución de dinero. **No se reembolsa en efectivo un monto que el cliente aún no ha pagado**: sobre una venta a crédito el resarcimiento se aplica primero a reducir la deuda, y **solo el excedente ya abonado** puede devolverse o quedar como saldo a favor. El reembolso en efectivo genera, de forma atómica, un **movimiento de caja de tipo egreso** vinculado a la nota de crédito y a la sesión del responsable, y requiere autorización de ROL-01. El monto total resarcido, por cualquier vía o combinación de vías, **nunca excede el monto de la nota de crédito**. Todo resarcimiento queda trazable: vía utilizada, monto, responsable autorizante y marca temporal. **Decisión de alcance Fase 1:** el saldo a favor no se materializa como atributo del cliente; la propia nota de crédito de tipo saldo constituye la fuente de verdad y es consultable como crédito disponible. |

| **RF-10-04** | **Reversión fiscal proporcional** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Revertir en la nota de crédito la porción de impuesto correspondiente a la mercancía devuelta. |
| **Precondición** | *Devolución procesada con líneas gravables.* |
| **Criterios de aceptación** | El impuesto de la nota de crédito se calcula **proporcionalmente sobre las líneas devueltas cuyos productos están marcados como gravables**, aplicando la tasa vigente del negocio sobre los importes **congelados de la factura original**. El impuesto no se recalcula con precios actuales: se deriva de las cifras de la venta original, preservando la correspondencia fiscal entre el comprobante y su nota de crédito. |

**ERR-10:** Devolución mayor a lo entregado. Se dispara al intentar devolver una cantidad superior a la efectivamente retirada de esa factura —**no a la facturada**— menos lo ya devuelto. El sistema rechaza y expone en la respuesta el máximo devolvible por línea y la cantidad solicitada. **HTTP 422**. Excepción: `ReturnQuantityException`.

**ERR-10B:** Reembolso en efectivo sobre venta a crédito no pagada. Se dispara al intentar devolver dinero de una factura a crédito con saldo pendiente, o al intentar un reembolso en efectivo sin sesión de caja activa. El sistema redirige el resarcimiento a reducir el saldo de la cuenta por cobrar, no a egreso de caja. **HTTP 422**. Excepción: `InvalidRefundMethodException`.

---

### MOD-11 — Conciliación, Alertas y Gestión de Anomalías

Es el motor de control interno: consolida información operativa y administrativa, ejecuta auditorías de fondo, detecta omisiones y enruta alertas con validación obligatoria del administrador. No genera flujo transaccional propio: **observa** el resto del sistema en modo de solo lectura y produce hallazgos.


**Nota de arquitectura.** El origen de una anomalía se referencia mediante un **puntero débil** (tipo e identificador) y no mediante clave foránea, porque un hallazgo puede apuntar a entidades de módulos distintos —sesión de caja, conteo físico, recepción de mercancía, cuenta por cobrar—. El sistema resuelve el origen bajo demanda, lo que hace el mecanismo extensible a nuevos tipos sin alterar el esquema.

| **RF-11-01** | **Sincronización operativa ⇄ administrativa** | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Consolidar automáticamente la información registrada por usuarios operativos y administrativos, garantizando coherencia verificable entre la operación diaria y los reportes administrativos. |
| **Precondición** | *Existen registros operativos y administrativos.* |
| **Criterios de aceptación** | La consolidación es automática y **no edita los registros base**: el motor de conciliación opera en modo de solo lectura sobre las transacciones y únicamente produce hallazgos. Toda incoherencia detectada se marca para revisión con su valor esperado, su valor real y la diferencia cuantificada. |

| **RF-11-02** | **Consolidación automática de reportes** | **ROL-SYS** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Generar reportes consolidados automáticos que eviten la revisión manual de registros individuales, presentando totales consolidados, indicadores de correspondencia/inconsistencia y diferencias detectadas. |
| **Precondición** | *Datos consolidados disponibles.* |
| **Criterios de aceptación** | El reporte muestra totales, correspondencias y diferencias, y no requiere intervención manual para generarse. **La conciliación inventario–facturación–caja se realiza tomando la factura como eje**, recorriendo la cadena movimiento de inventario → retiro → cobro de factura → movimiento de caja. La materialización de estos reportes se expone a través de MOD-12. |

| **RF-11-03** | **Alertas de anomalías** *(Revisado en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Emitir alertas ante descuadres de caja, diferencias inventario–bodega, discrepancias de recepción, cuentas vencidas, omisiones de registro y uso indebido. |
| **Precondición** | *Evento anómalo detectado.* |
| **Criterios de aceptación** | El sistema mantiene un **catálogo cerrado de seis reglas** por negocio —descuadre de caja, faltante de inventario, discrepancia de recepción, cuenta vencida, omisión de registro y venta sin sesión—, cada una con **umbral y severidad parametrizables por ROL-01**. Una desviación **por debajo del umbral no genera anomalía**. Cada anomalía registra valor esperado, valor real, diferencia, origen, severidad y momento de detección, y queda rastreable hasta su resolución. Las alertas se disparan tanto desde los eventos en línea (cierre de caja con descuadre, discrepancia de recepción, marcado de cuenta vencida) como desde las auditorías programadas. |

| **RF-11-04** | **Auditoría de fondo programada** *(Revisado en v1.1)* | **ROL-SYS** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Ejecutar procesos programados a intervalos parametrizables que analicen la consistencia de los datos, cruzando ventas, mermas, órdenes de compra y bitácoras del sistema. |
| **Precondición** | *Servicio de tareas programado activo.* |
| **Criterios de aceptación** | El proceso corre de forma autónoma según su calendarización **diaria**, recorriendo todos los negocios de forma explícita al operar sin sesión de usuario. Admite cuatro ámbitos: **caja**, **inventario–bodega**, **compras** e **integral**. Cada corrida se registra con su tipo, ámbito, estado, número de hallazgos y marcas de inicio y fin; una corrida fallida queda marcada como tal sin dejar estado ambiguo. Genera hallazgos consultables **sin bloquear la operación en curso**. La conciliación también puede dispararse **manualmente a demanda** por ROL-01 o ROL-02, registrando al solicitante. |

| **RF-11-05** | **Detección de omisiones de uso (Fase 2)** *(Revisado en v1.1)* | **ROL-SYS** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Si un usuario con rol operativo o administrativo inicia sesión pero no registra actividad en cajas o bodega durante ventanas de alta demanda parametrizadas, clasificar el evento como "Sospecha de Omisión / Evasión de Sistema". |
| **Precondición** | *Ventanas de alta demanda parametrizadas.* |
| **Criterios de aceptación** | La ausencia de actividad esperada genera un hallazgo clasificado que se enruta como alerta (RF-11-06). **Decisión de alcance:** la regla existe en el catálogo desde Fase 1 y es parametrizable, pero su **detección automática se difiere a Fase 2**, dado que requiere la parametrización previa de las ventanas de alta demanda por negocio y sucursal. |

| **RF-11-06** | **Enrutamiento y severidad de alertas (Fase 2 el canal)** *(Revisado en v1.1)* | **ROL-SYS** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Al gatillarse una anomalía, categorizarla según código de alerta, evento detonante, severidad, acción del sistema y canal de envío. |
| **Precondición** | *Anomalía detectada.* |
| **Criterios de aceptación** | Cada alerta lleva **código, severidad y origen definidos**; la severidad se hereda de la regla y determina la urgencia y el destinatario, correspondiendo a ROL-01 las de severidad crítica. Los tres niveles son informativa, advertencia y crítica. **Decisión de alcance:** la clasificación y la severidad están implementadas desde Fase 1; el **motor de envío por canal** (correo, notificación) se difiere a Fase 2. |

| **RF-11-07** | **Justificación y validación de anomalías** *(Revisado en v1.1)* | **ROL-02** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Permitir que una anomalía sea justificada únicamente mediante validación expresa del administrador, dejando registro de la justificación, responsable y fecha. |
| **Precondición** | *Anomalía activa pendiente.* |
| **Criterios de aceptación** | **Ningún usuario puede autojustificar una anomalía propia** (BR-01): el sistema **identifica al causante a partir del origen del hallazgo** —el cajero de la sesión descuadrada, el operador del conteo, el responsable de la recepción— y rechaza la operación si coincide con el validador. La validación exige responsable, motivo y fecha, condición de coherencia verificada por el motor: una anomalía en estado justificada o resuelta **debe** registrar quién y cuándo. **Toda transición de estado genera automáticamente un registro inmutable en la bitácora de la anomalía**, sin depender de que el operador o el programador lo recuerden, lo que hace que el ciclo completo —detección, notificación, revisión, justificación o resolución— sea siempre rastreable. |

| **RF-11-08** | **Idempotencia de la detección de anomalías** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Garantizar que un mismo problema detectado por vías distintas o en momentos distintos no produzca alertas duplicadas mientras siga activo. |
| **Precondición** | *Coexisten la detección en línea, la conciliación programada y la conciliación manual.* |
| **Criterios de aceptación** | El sistema admite **como máximo una anomalía activa** por combinación de regla y origen, restricción garantizada por el motor de datos mediante índice único parcial sobre los estados activos. Si el proceso programado y una conciliación manual detectan simultáneamente el mismo problema, **solo se registra una**; el intento duplicado se descarta **sin error visible para el usuario** y se contabiliza como deduplicación. Cuando la anomalía alcanza un estado terminal —justificada o resuelta—, **el candado se libera**, de modo que una reaparición futura del mismo problema sí genera un hallazgo nuevo y distinguible. |

**ERR-11:** Autojustificación de anomalía. Se dispara cuando el usuario que originó una anomalía intenta cerrarla o justificarla él mismo, violando BR-01. El sistema rechaza y exige validación de un rol superior. **HTTP 403**. Excepción: `SelfResolutionNotAllowedException`.

**ERR-11B:** Anomalía duplicada en conciliación híbrida. Se dispara cuando el proceso programado y una conciliación manual generarían la misma anomalía sobre el mismo origen. El sistema aplica la regla de idempotencia: si ya existe una anomalía activa para ese origen y esa regla, no crea otra. **Sin error visible al usuario**; se registra la deduplicación. Sin código HTTP (proceso interno). Excepción: `DuplicateAnomalyException` (capturada y silenciada).

---

### MOD-12 — Reportería, KPIs e Inteligencia de Negocios

Provee la capa analítica para dirección: reportes consolidados y comparables e indicadores clave para la toma de decisiones operativas, administrativas y estratégicas. No genera transacciones: **lee** el sistema y lo destila en indicadores.


| **RF-12-01** | **Reportes administrativos y directivos** *(Revisado en v1.1)* | **ROL-01** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Generar reportes diarios, semanales y mensuales para administradores y propietarios, con información consolidada, comparativa y trazable. |
| **Precondición** | *Datos consolidados disponibles.* |
| **Criterios de aceptación** | Los reportes son consolidados, comparables entre periodos y **trazables al dato origen**. Se generan por rango diario, semanal y mensual. Los cortes de periodo se calculan en el **huso horario del negocio**, no en el del servidor. Las definiciones de reporte son guardables y reutilizables por usuario. **Decisión de alcance Fase 1:** el motor de **envío programado** de reportes se difiere a Fase 2; los atributos de calendarización existen en el modelo pero no accionan envíos. |

| **RF-12-02** | **Panel de indicadores clave (KPI)** *(Revisado en v1.1)* | **ROL-01** | **Should** |
| --- | --- | --- | --- |
| **Descripción** | Mostrar indicadores gráficos que incluyan, como mínimo: nivel de sincronización inventario–bodega; ventas comparativas por semana y mes; metas definidas y avances acumulados por periodo; e indicadores de cumplimiento operativo y administrativo. |
| **Precondición** | *Existen datos suficientes para el cálculo.* |
| **Criterios de aceptación** | El panel presenta, como mínimo, los cuatro indicadores listados, alineados con KPI-01 a KPI-08 del BRD. Cada indicador expone su **valor, su meta (cuando existe) y su porcentaje de logro**. Las etiquetas, unidades y familias de cada indicador provienen de un **registro canónico único**, de modo que no existan códigos de indicador sin definición. Los indicadores se actualizan con la información consolidada vigente. |

| **RF-12-03** | **Cálculo, vigencia e integridad de los indicadores** *(Nuevo en v1.1)* | **ROL-SYS** | **Must** |
| --- | --- | --- | --- |
| **Descripción** | Calcular y mantener los indicadores mediante instantáneas periódicas recalculables, garantizando el aislamiento entre negocios y la trazabilidad al dato origen. |
| **Precondición** | *Existen transacciones registradas y, opcionalmente, metas definidas.* |
| **Criterios de aceptación** | **Las instantáneas de indicador son caché recalculable y nunca la fuente de verdad**: ante cualquier sospecha de inconsistencia, se descartan y se recalculan desde las transacciones origen; el sistema no sirve un dato dudoso. El recálculo es **idempotente**: ejecutarlo dos veces sobre el mismo periodo produce el mismo resultado, sin duplicar registros. Un **proceso programado diario y otro mensual** recalculan los indicadores por negocio, recorriendo los negocios de forma explícita al operar sin sesión. El cálculo respeta un **orden de dependencias**: el indicador agregador de cumplimiento de metas (KPI-06) se calcula al final, a partir de los porcentajes de logro de los demás. **Toda consulta de agregación filtra explícitamente por negocio**, incluidas las consultas sobre las vistas, que son objetos globales no sujetos al aislamiento automático del modelo de dominio. Las metas admiten alcance global o por sucursal, con unicidad garantizada por indicador y periodo. |

**ERR-12:** Fuga de tenant en consulta agregada. Se dispara si una consulta de reporte omitiera el filtro por negocio e intentara cruzar datos entre negocios. El aislamiento automático lo previene en las entidades de dominio; en las **vistas de agregación**, que son objetos globales no cubiertos por ese aislamiento, el filtro por negocio se aplica de forma explícita e invariable como primer criterio de cada consulta. El sistema nunca devuelve datos de otro negocio. **HTTP 500** si escapara al control (fallo de programación), pero el diseño lo bloquea antes. Excepción: controlada por el aislamiento de negocio.

**ERR-12B:** Instantánea de KPI corrupta o inconsistente. Se dispara si un indicador no cuadra con las transacciones origen. El sistema **descarta la instantánea y la recalcula desde las tablas fuente**; nunca sirve el dato dudoso. Existe una operación explícita de recálculo forzado a disposición de ROL-01. **HTTP 500** si ocurre en consulta directa; normalmente se resuelve en el proceso programado. Excepción: `KpiRecalculationException`.

---

## 3. Catálogo consolidado de errores transaccionales (Nuevo en v1.1)

Todos los errores revierten la transacción, con las dos excepciones de persistencia de evidencia explícitamente señaladas.

| **Código** | **Condición de disparo** | **HTTP** | **Excepción implementada** | **Persiste evidencia** |
| --- | --- | --- | --- | --- |
| **ERR-01** | Credenciales inválidas, cuenta inactiva o límite de intentos | 401 / 429 | `AuthenticationException` / limitador de tasa | No |
| **ERR-01B** | Modificación o borrado de bitácora de auditoría | 403 | `InmutableAuditException` | No |
| **ERR-02** | SKU o nombre duplicado; referencia cíclica | 422 | `ValidationException` / `CyclicReferenceException` | No |
| **ERR-02B** | Borrado físico de maestro con dependencias | 409 | `RestrictDeleteException` | No |
| **ERR-03** | Stock insuficiente para reservar, retirar o traspasar | 409 | `InsufficientStockException` | No |
| **ERR-03B** | Movimiento sin actualización de saldo (atomicidad) | 500 | `InventorySyncException` | No |
| **ERR-04** | Discrepancia en 3-Way Match | 409 | `PurchaseMatchException` | **Sí** |
| **ERR-04B** | Orden de compra a proveedor no aprobado | 422 | `SupplierNotApprovedException` | No |
| **ERR-05** | Documento duplicado / operación sobre cliente genérico | 422 / 403 | `ValidationException` / `ProtectedResourceException` | No |
| **ERR-05B** | Cliente con cuentas por cobrar pendientes | 422 | `CustomerHasReceivablesException` | No |
| **ERR-06** | Operación sin sesión de caja activa; segunda apertura | 409 | `NoActiveCashSessionException` | No |
| **ERR-06B** | Cierre de caja con discrepancia | 422 | `UnreconciledCashClosingException` | **Sí** |
| **ERR-07** | Cobro incompleto al contado / conflicto de folio | 422 / 409 | `IncompletePaymentException` / `FolioConflictException` | No |
| **ERR-07B** | Edición del núcleo fiscal de factura emitida | 403 | `ImmutableInvoiceException` | No |
| **ERR-08** | Límite de crédito excedido / sobre-abono | 422 | `CreditLimitExceededException` / `OverpaymentException` | No |
| **ERR-08B** | Abono en efectivo sin sesión de caja activa | 409 | `NoActiveCashSessionException` | No |
| **ERR-09** | Retiro mayor al saldo pendiente de entrega | 422 | `DispatchExceedsBalanceException` | No |
| **ERR-09B** | Retiro sobre factura anulada | 409 | `DispatchOnVoidedInvoiceException` | No |
| **ERR-10** | Devolución mayor a lo efectivamente entregado | 422 | `ReturnQuantityException` | No |
| **ERR-10B** | Reembolso en efectivo sobre crédito no pagado | 422 | `InvalidRefundMethodException` | No |
| **ERR-11** | Autojustificación de anomalía (viola BR-01) | 403 | `SelfResolutionNotAllowedException` | No |
| **ERR-11B** | Anomalía duplicada (idempotencia) | — | `DuplicateAnomalyException` (silenciada) | No aplica |
| **ERR-12** | Fuga de negocio en consulta agregada | 500 | Controlada por aislamiento de negocio | No |
| **ERR-12B** | Instantánea de KPI inconsistente | 500 | `KpiRecalculationException` | No aplica |
| **Transversal** | Modificación o borrado de registro de solo inserción | 403 | `ImmutableRecordException` | No |

**Registros de solo inserción del sistema.** Bitácora de auditoría, kardex de inventario, movimientos de caja, cobros de factura, abonos a cuentas por cobrar, líneas de evidencia de recepción, corridas de conciliación y bitácora de anomalías. Ninguno admite modificación ni borrado por vía alguna.


---

## 4. Reglas de negocio

Las reglas de negocio son restricciones transversales que condicionan a varios requisitos. Se extraen explícitamente para evitar dispersarlas dentro de los requisitos y facilitar su verificación independiente. Corresponden a las Business Rules del BRD (sección 7). **En la v1.1 se añade, para cada regla, el mecanismo por el cual el sistema la hace cumplir**, lo que la convierte en verificable y no meramente declarativa.

| **Código** | **Regla** | **Enunciado** | **Mecanismo de cumplimiento implementado** |
| --- | --- | --- | --- |
| **BR-01** | Segregación de justificación | Ningún usuario puede justificar por sí mismo una anomalía en la que esté involucrado; la validación recae en un rol superior (ROL-02). | El sistema identifica al causante a partir del origen del hallazgo (sesión de caja, conteo, recepción) y rechaza la operación con HTTP 403 si coincide con el validador. Toda transición queda registrada en bitácora inmutable. |
| **BR-02** | Inconsistencia activa | Toda diferencia no validada (caja, inventario–bodega, recepción) se considera una inconsistencia activa y bloquea los cierres asociados hasta su resolución. | Estados de bloqueo persistidos: sesión **descuadrada**, cuenta por pagar **congelada** (no admite pagos), recepción en **discrepancia**, conteo **abierto**. La evidencia se conserva precisamente para permitir la resolución. |
| **BR-03** | Fuente única de verdad | La información consolidada por el sistema es la referencia oficial del negocio; prevalece sobre registros parciales o manuales. | El conteo físico prevalece sobre el saldo del sistema al aplicarse. Las instantáneas de KPI son caché recalculable y nunca fuente de verdad. Las cifras derivadas (disponible, diferencia, saldo, pendiente) se calculan y no se capturan. |
| **BR-04** | No borrado físico | Ninguna transacción se elimina físicamente; solo se anula conservando estado y pista de auditoría. | Borrado lógico en todos los maestros con dependencias; estados terminales trazables en documentos (anulada, revertido, bloqueada); registros de solo inserción en las ocho bitácoras del sistema; restricciones referenciales que impiden el borrado con historial. |
| **BR-05** | Cobro íntegro | No se confirma ninguna venta al contado cuyo pago no cubra el 100% del total neto. | Validación previa a toda escritura: si el pago no iguala el total, no se persiste factura, folio, reserva ni movimiento de caja. Restricción de motor que impide que el importe pagado supere el total. |
| **BR-06** | Autorización de excepciones | Las excepciones críticas (anulaciones, desbloqueo de CxP, aprobación de proveedores) son potestad exclusiva del ROL-01. | Operaciones reservadas a ROL-01: anular factura, aprobar/suspender proveedor, resolver discrepancia de recepción, desbloquear CxP, autorizar exceso de límite de crédito, autorizar reembolso en efectivo, resolver anomalía. Cada una registra al autorizante, con coherencia verificada por el motor. |
| **BR-07** | Facturas a crédito / CxC | Toda factura a crédito genera una cuenta por cobrar. Si se anula una factura a crédito con abonos, la cuenta no puede desaparecer: la anulación revierte, pero los abonos recibidos exigen nota de crédito o devolución formal. | Unicidad garantizada por el motor: una factura a crédito ⇒ exactamente una cuenta, creada atómicamente. La reversión **salda la cuenta sin eliminar los abonos**, que permanecen como evidencia inmutable; el excedente ya abonado se resarce por MOD-10. |

---

## 5. Indicadores clave de desempeño (KPIs)

Los indicadores corresponden a los definidos en el BRD (sección 10). En la v1.1 se precisa su **fuente de datos efectiva** y su **mecanismo de cálculo implementado**.

| **Código** | **Indicador** | **Qué mide** | **Fuente de datos implementada** | **Naturaleza de la meta** |
| --- | --- | --- | --- | --- |
| **KPI-01** | Correspondencia entre ventas, caja e inventario | Integridad cruzada | Cálculo directo: facturado contra la suma de cobros y cuentas por cobrar generadas en el periodo | No es meta; índice de salud |
| **KPI-02** | Índice de correspondencia entre bodega e inventario | Integridad física | Vista de exactitud de stock sobre los conteos físicos | Sí; meta a la baja en desviación |
| **KPI-03** | Reducción de faltantes no justificados | Control anti-fraude | Vista de faltantes sobre anomalías activas de tipo faltante de inventario | Sí; meta a la baja |
| **KPI-04** | Uso consistente del sistema por el personal clave | Cumplimiento de personal | Vista de uso sobre la bitácora de auditoría, medida como usuarios activos sobre usuarios habilitados | Sí; meta porcentual |
| **KPI-05** | Evolución de ventas por periodo | Comercial | Vista de ventas sobre facturas emitidas; incluye ticket promedio y número de facturas | Sí; meta de montos |
| **KPI-06** | Cumplimiento de metas operativas y comerciales | Meta agregadora | Promedio de los porcentajes de logro de los demás indicadores del periodo; **se calcula al final** | Es el agregador; no tiene meta propia |
| **KPI-07** | Disponibilidad de reportes diarios, semanales y mensuales confiables | SLA operativo | Vista de disponibilidad sobre corridas de conciliación programadas completadas | No; métrica de servicio |
| **KPI-08** | Índice de recuperación de cartera / saldo por cobrar | Financiero | Vista de cartera sobre cuentas por cobrar: emitida, recuperada, pendiente y vencida | Sí; meta porcentual |

**Indicadores derivados admitidos como meta** (registro canónico ampliado en v1.1): margen bruto, ticket promedio y rotación de inventario. Su cálculo automático se difiere a Fase 2; el registro y la definición existen desde Fase 1 para que puedan fijarse metas sobre ellos.

---

## 6. Trazabilidad requisito → artefacto de software (Nuevo en v1.1)

Esta matriz es el puente entre la especificación funcional y la implementación, y permite auditar que cada requerimiento tiene un componente responsable de cumplirlo.

| **Módulo** | **Requerimientos** | **Componente de dominio responsable** | **Procesos programados** |
| --- | --- | --- | --- |
| **MOD-01** | RF-01-01 … RF-01-07 | Aislamiento por negocio (comportamiento transversal); aprovisionamiento automático del negocio; **alta pública canónica atómica e idempotente (RegistrationService)** | — |
| **MOD-02** | RF-02-01 … RF-02-06 | Guardas de modelo: anti-ciclo de categorías, anti-ciclo de recetas, inmutabilidad de SKU, forzado de servicio sin inventario | — |
| **MOD-03** | RF-03-01 … RF-03-09 | **Servicio de Inventario**: único autorizado a escribir existencias; reservar, liberar, retirar, ingresar, traspasar, ajustar por conteo; **asignación Bodega–Bodeguero (M:N, operar solo bodega asignada)**; **visibilidad consolidada por bodega asignada (último conteo/diferencia/conciliación), disponibilidad para facturar y aviso de mínimo con reposición (derivados, sin nuevo esquema)** | — |
| **MOD-04** | RF-04-01 … RF-04-05 | **Servicio de Compras**: 3-Way Match, congelamiento de CxP, resolución de discrepancia; consume el Servicio de Inventario; **proveedores candidatos + ubicaciones del mapa + geocodificación desacoplada (adaptador intercambiable con caché)** | — |
| **MOD-05** | RF-05-01 … RF-05-03 | Guardas de modelo: protección del cliente genérico, bloqueo por cartera viva | — |
| **MOD-06** | RF-06-01 … RF-06-08 | **Servicio de Caja**: apertura con doble candado, arqueo ciego, esperado solo efectivo, cierre con descuadre, **doble moneda NIO/USD (snapshot por operación, reconciliación por moneda, tipo de cambio versionado)**, **asignación Caja–Cajero (abrir solo caja asignada) e historial de sesiones filtrable por sucursal** | — |
| **MOD-07** | RF-07-01 … RF-07-05 | **Servicio de Ventas** (composición y congelamiento) y **Servicio de Facturación** (folio, impuesto, reserva, cobro dual, anulación) | — |
| **MOD-08** | RF-08-01 … RF-08-07 | **Servicio de Cuentas por Cobrar**: límite de crédito, abono atómico, sincronización, reversión, reducción por nota de crédito | Marcado de cuentas vencidas (diario) |
| **MOD-09** | RF-09-01 … RF-09-04 | **Servicio de Entregas**: descuento físico real, control de saldo pendiente, reversión con reingreso y re-reserva | — |
| **MOD-10** | RF-10-01 … RF-10-04 | **Servicio de Devoluciones**: destino físico, nota de crédito, resarcimiento ramificado, reversión fiscal | — |
| **MOD-11** | RF-11-01 … RF-11-08 | **Servicio de Anomalías** (registro idempotente, justificación con BR-01) y **Servicio de Conciliación** (auditorías de fondo) | Conciliación integral (diario) |
| **MOD-12** | RF-12-01 … RF-12-03 | **Servicio de Indicadores**: recálculo idempotente con aislamiento de negocio y orden de dependencias | Instantáneas de KPI (diario y mensual) |

**Procesos programados del sistema (Fase 1):** marcado de cuentas vencidas (diario), conciliación integral (diario), instantáneas de KPI diarias y mensuales. Todos recorren los negocios de forma explícita, dado que operan sin sesión de usuario.

---

## 7. Decisiones de alcance registradas (Nuevo en v1.1)

Elementos evaluados durante la implementación y deliberadamente diferidos o descartados, con su justificación. Se documentan para que ninguna omisión se lea como olvido.

| **Elemento** | **Decisión** | **Justificación** |
| --- | --- | --- |
| Trazabilidad de abonos a proveedor | **Diferido a Fase 2** | Fase 1 actualiza el importe pagado de la CxP directamente; el motor impide sobre-pago. La tabla de abonos individuales se incorporará como espejo de la de cuentas por cobrar. |
| Estado "anulada" en cuentas por pagar | **Diferido a Fase 2** | El rechazo de una recepción deja la CxP en estado congelado terminal, que es trazable y no exigible. |
| Perfilamiento dinámico de clientes | **Diferido a Fase 2** | Se materializará como entidad satélite poblada por proceso programado, sin alterar el maestro de clientes. |
| Dirección predeterminada exclusiva por cliente | **Descartado** | Decisión de negocio: clientes con múltiples puntos de entrega (contratistas, distribuidores) no deben quedar atados a una sola dirección. |
| Saldo a favor materializado en el cliente | **Diferido a Fase 2** | Fase 1 usa la nota de crédito de tipo saldo como fuente de verdad, evitando un dato adicional que mantener sincronizado. |
| Congelamiento del costo exacto en reversión de retiro | **Diferido a Fase 2** | Fase 1 valora el reingreso al costo promedio ponderado vigente, que es el comportamiento estándar y suficiente para el volumen previsto. |
| Detección automática de omisión de registro | **Diferido a Fase 2** | Requiere la parametrización previa de ventanas de alta demanda por negocio y sucursal. La regla y su umbral existen desde Fase 1. |
| Enrutamiento de alertas por canal | **Diferido a Fase 2** | La clasificación y la severidad están implementadas; el motor de envío (correo, notificación) se incorporará con la capa de notificaciones. |
| Envío programado de reportes | **Diferido a Fase 2** | Los atributos de calendarización existen en el modelo, sin accionar envíos en Fase 1. |
| Corte diario exacto por huso horario en vistas de agregación | **Diferido a Fase 2** | Fase 1 acota los periodos en el huso del negocio, lo que resuelve correctamente los totales por periodo. El corte diario exacto requiere configuración adicional del motor de datos. |
| Numeración secuencial **unificada** para ventas y órdenes de compra | **Diferido a Fase 2** | Fase 1 asigna folios con **contadores secuenciales atómicos por (negocio, tipo)** en la tabla `sequences` vía `SequenceGenerator`: `OC-` para órdenes de compra (MOD-04) y `TR-` para traspasos (MOD-03), protegidos por unicidad. Los documentos fiscales (factura, nota de crédito) usan su propia secuencia estricta en `document_sequences`. Lo diferido a Fase 2 es **unificar** esas numeraciones en un esquema único transversal; hoy cada tipo tiene su propio correlativo. |
| Optimización anti-bloqueo cruzado en traspasos masivos | **Diferido a producción a escala** | El flujo actual de traspaso punto a punto es seguro; el ordenamiento determinista de bloqueos se incorporará si el volumen lo requiere. |

---

## 8. Requerimientos no funcionales y condiciones de entorno (referencia externa)

Los atributos no funcionales y las condiciones de entorno se separan del cuerpo exigible de requerimientos funcionales. Se clasifican en dos naturalezas: (a) dependencias externas responsabilidad del cliente o de la infraestructura de despliegue, y (b) atributos de calidad que orientan el diseño, pero no constituyen funciones evaluables del núcleo. Se conservan como referencia, no como exigencia contractual del set de requerimientos funcionales.

| **Atributo / Condición** | **Descripción y naturaleza** |
| --- | --- |
| **Compatibilidad de cliente** | El sistema, por ser aplicación web, se ejecuta en navegadores modernos sobre Windows, Linux, macOS, Android e iOS. La disponibilidad de un dispositivo compatible es responsabilidad del cliente. |
| **Conectividad** | Requiere acceso a Internet con ancho de banda estable. La calidad y continuidad del enlace es una dependencia externa, no una función del sistema. |
| **Sin instalación adicional** | Se utiliza sin instalar software adicional más allá del navegador. |
| **Competencia del usuario** | El usuario final debe poseer conocimientos mínimos de uso de sistemas web; ante dudas, debe apoyarse en el manual de usuario. |
| **Hardware mínimo sugerido** | Equipo de escritorio o portátil de gama de entrada o superior, con procesador de 2+ núcleos y 2 GB de RAM o más. Es una recomendación de entorno, no un requisito del software. |
| **Rendimiento y respuesta** | Tiempos de respuesta ágiles bajo condiciones de red adecuadas y escalabilidad ante el crecimiento de usuarios. Se documentan como metas de calidad, sujetas a la infraestructura de despliegue. |
| **Seguridad de datos** | El desarrollo aplica patrones y buenas prácticas que refuerzan la seguridad y la protección de datos frente a accesos no autorizados: aislamiento estructural entre negocios, cifrado irreversible de credenciales, principio de menor privilegio y bitácora inmutable. |
| **Precisión aritmética** | Todos los importes y existencias se representan y operan con aritmética decimal exacta. Es un atributo de calidad con impacto funcional directo sobre el arqueo de caja y la conciliación. |
| **Diseño responsive** | La interfaz se adapta a computadoras, tabletas y teléfonos. Se trata como atributo de calidad de la interfaz. |
| **Capacitación y manuales** | La entrega incluye manuales de usuario estructurados. Es un entregable del proyecto, no una función en tiempo de ejecución del sistema. |

---

## 9. Supuestos funcionales

- El sistema se utiliza mediante aplicación web (modelo B2B SaaS multi-negocio).
- La información registrada se considera válida salvo detección de anomalías por el propio sistema.
- Cada usuario opera bajo un único rol activo y credenciales personales e intransferibles.
- Cada sucursal dispone de una bodega marcada como predeterminada; sin ella, las reservas de venta y los retiros no pueden ejecutarse.
- Cada negocio define su tasa impositiva y su huso horario, que gobiernan la liquidación fiscal y los cortes de periodo respectivamente.
- Los procesos programados se ejecutan sobre un servicio de tareas activo y disponible.

---

## 10. Fuera de alcance

Los siguientes elementos quedan explícitamente excluidos del alcance funcional de esta versión (Fase 1). Esta lista es coherente con las exclusiones del BRD (sección 3.2):

- Integraciones bancarias o contabilidad fiscal/financiera completa.
- Algoritmos propietarios específicos de cálculo o de seguridad (se definen en el diseño técnico).
- Incidencias de red del cliente al momento de ingresar al sistema.
- Sistema de videovigilancia o hardware de cámaras.
- Registros patrimoniales del negocio y del propietario.
- Migración de datos o registros operativos/administrativos previos al primer acceso al sistema.
- Errores del usuario al capturar sus propios datos.
- Logística de reparto, rutas y control de SLA de entrega (MOD-09 en Fase 1 cubre únicamente el retiro y su saldo pendiente).
- Motor de notificaciones por canal externo y envío programado de reportes.

---

## 11. Relación con otros documentos y siguientes pasos

Este documento sirve como insumo para el diseño funcional detallado, los casos de uso, las historias de usuario y el diseño técnico y de pruebas. Se anexa un diccionario de datos extendido.

Este Documento de Requerimientos Funcionales consolida el análisis de requerimientos, el diseño arquitectónico y las especificaciones técnicas de software de alta fidelidad. El contenido técnico aquí expuesto garantiza la suficiencia informática para la derivación del modelo entidad-relación normalizado (2FN/3FN) y la arquitectura del motor de base de datos. Asimismo, provee los contratos de servicios, reglas de negocio y flujos transaccionales necesarios para el desarrollo del backend.

**Estado de la versión 1.1.** A diferencia de la v1.0, que precedía al modelado, esta versión ha sido **conciliada con la capa de modelos, servicios y restricciones de motor efectivamente implementadas y validadas módulo por módulo (MOD-01 a MOD-12)**. Cada criterio de aceptación de este documento es verificable contra un artefacto de software existente. En consecuencia, la v1.1 es la referencia definitiva para:

1. **Diseño de casos de prueba**, que pueden derivarse directamente de los criterios de aceptación y del catálogo consolidado de errores (sección 3).
2. **Construcción de la capa de aplicación** (validaciones de entrada, políticas de autorización y controladores), cuyo alcance queda delimitado por los mecanismos ya cubiertos en dominio y motor.
3. **Auditoría funcional ante terceros**, dado que cada regla de negocio (sección 4) declara explícitamente su mecanismo de cumplimiento.

**Siguientes pasos inmediatos:** construcción de la capa de aplicación —validación de pertenencia entre entidades del mismo negocio, políticas de autorización de ROL-01 y ROL-02, resolución de negocio en el flujo de autenticación y limitación de tasa en el acceso—, seguida del diseño de interfaz y la batería de pruebas automatizadas sobre los criterios aquí especificados.

---

*Fin del documento. GINTLY · Documento de Requerimientos Funcionales v1.1 · 21 de julio de 2026.*
