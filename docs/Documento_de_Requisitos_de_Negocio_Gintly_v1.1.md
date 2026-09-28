# GINTLY · Documento de Requisitos de Negocio (BRD) — v1.1

**GINTLY**

Sistema de Gestión Integral de Negocios

## DOCUMENTO DE REQUISITOS DE NEGOCIO

| | |
| --- | --- |
| **Versión** | 1.1 |
| **Tipo de sistema** | Aplicación web B2B SaaS de control operativo y financiero para PYMES (una o más sucursales) |
| **Estado** | Documento de requisitos de negocio alineado con el sistema construido y validado |
| **Documento previo** | Planteamiento del Problema y Propuesta de Sistema |
| **Documento siguiente** | Documento de Requerimientos Funcionales (FRD) v1.1 |
| **Fecha** | 21 de julio de 2026 |

### Control del documento

**Historial de versiones**

| **Versión** | **Fecha** | **Autor(es)** | **Descripción** |
| --- | --- | --- | --- |
| **1.0** | 23 de junio de 2026 | Gianfranco Ubau Torres; Roberto Carlos Romero Blandón; Pablo Antonio Guerrero Guillen | Versión base del documento de requisitos de negocio. Se formulan objetivos con criterios SMART enlazados a KPIs; alcances; stakeholders; y requisitos del negocio. Se incorpora matriz de trazabilidad, priorización y orden de implementación, roadmap de fases, restricciones de negocio (modelo B2B SaaS multi-tenant), glosario de términos y control de versiones. Se establece explícitamente qué funciones corresponden a la Fase 2. |
| **1.1** | 21 de julio de 2026 | Equipo de Análisis y Arquitectura Gintly | **Alineación de los requerimientos de negocio con la lógica del software final.** Se contrasta cada elemento del documento contra el sistema efectivamente construido y validado. Cambios: (a) cada **regla de negocio** incorpora el mecanismo por el cual el sistema la hace cumplir y su correspondencia con el FRD v1.1; (b) se elevan a regla explícita dos restricciones que la implementación evidenció como transversales: **cobro íntegro (h)** e **inconsistencia activa bloqueante (i)**; (c) se completa el alcance de Fase 1 con **devoluciones, notas de crédito y mermas**, y con la **definición de metas de negocio**, ambos construidos y no listados en la v1.0; (d) se actualiza el roadmap de Fase 2 con las capacidades diferidas de forma deliberada durante la construcción; (e) se corrige la matriz de trazabilidad, cuyo objetivo 04 remitía a un requisito equivocado; (f) se precisa la fuente de medición real de cada KPI; (g) se amplían glosario, supuestos y restricciones con los conceptos de negocio que el sistema materializó. No se incorporan descripciones técnicas de entidades: el modelo de datos se remite al Diccionario de Datos anexo y al FRD v1.1. |

**Aprobación del documento**

| **Rol** | **Nombre** | **Responsabilidad** | **Estado** |
| --- | --- | --- | --- |
| Sponsor / Propietario del negocio | Journey Map | Aprueba alcance, objetivos y restricciones de negocio | Aprobado |
| Analista de Negocio | David González | Elabora, valida y mantiene el documento | Aprobado |
| Líder Técnico / Arquitecto de Software | (Completar) | Valida factibilidad técnica y correspondencia con lo construido | Aprobado (v1.1) |

---

## 1. Propósito del documento

Este documento define y formaliza los requisitos de negocio que el sistema Gintly debe satisfacer para resolver la pérdida de control operativo, financiero y administrativo que enfrentan los propietarios de pequeñas y medianas empresas con gestión delegada o remota.

El documento establece qué necesita el negocio, qué resultados espera y bajo qué reglas, sirviendo como base para el Documento de Requerimientos Funcionales, el diseño del sistema y la validación del producto.

**Naturaleza de la versión 1.1.** La v1.0 antecedió a la construcción; esta versión se contrasta contra el sistema ya construido y validado. Cada regla declara el mecanismo que la hace cumplir y cada alcance refleja lo entregado. Es la referencia definitiva para la validación del producto ante el negocio.

---

## 2. Objetivos de negocio

### 2.1 Objetivo general

Permitir a los propietarios de PYMES ejercer control efectivo, verificable y remoto sobre la operación y las finanzas de sus negocios, reduciendo fugas de capital y riesgos operativos.

### 2.2 Objetivos específicos (criterio SMART)

Cada objetivo específico se formula con una métrica verificable, una meta cuantificable y un plazo definido, y se enlaza directamente a su KPI correspondiente (sección 10), de modo que objetivos y KPIs se lean como una sola unidad de medición. **En la v1.1 se añade la columna de medición**, que confirma que cada meta es calculable de forma automática por el sistema y no depende de estimaciones manuales.

| **ID** | **Objetivo específico** | **KPI** | **Meta** | **Plazo** | **Medición** |
| --- | --- | --- | --- | --- | --- |
| **01** | Garantizar la trazabilidad completa entre ventas, caja e inventario mediante un índice de correspondencia verificable entre estos tres componentes. | KPI-01 | ≥ 98% de las transacciones conciliadas sin diferencia | 6 meses posteriores a la puesta en producción | Automática. Conciliación diaria con la factura como eje: venta → entrega → cobro → caja. |
| **02** | Proveer información confiable y oportuna para la toma de decisiones, asegurando la disponibilidad de reportes de cierre operativo, administrativo y financiero. | KPI-07 | 100% de los reportes disponibles dentro de las 24 horas posteriores al cierre del periodo | Desde el mes 1 de operación | Automática. Indicadores recalculados a diario y al cierre de mes. |
| **03** | Detectar y prevenir malas prácticas administrativas y operativas mediante la reducción sostenida de faltantes no justificados entre inventario y bodega. | KPI-03 | Reducción ≥ 80% respecto a la línea base | 12 meses, con línea base al cierre del primer trimestre | Automática. Anomalías de faltante activas, con umbral fijado por el propietario. |
| **04** | Asegurar la disciplina operativa mediante el uso obligatorio y sostenido del sistema por parte del personal con roles críticos. | KPI-04 | ≥ 95% de cumplimiento de registro diario | Sostenido a partir del mes 3 de operación | Automática. Personal con actividad registrada sobre personal habilitado. |

| **Nota metodológica** |
| --- |
| El sistema sustituye procesos previos manuales o no centralizados, no existiendo una línea base digital previa a la puesta en producción. Las metas de 01, 03 y 04 se calculan sobre la operación real del sistema, a partir de la fecha de entrada en producción, según el plazo indicado en cada objetivo. |

---

## 3. Alcance del negocio

### 3.1 Inclusiones — Fase 1 (v1.0)

- Control separado y verificable de ingresos y egresos en caja, con apertura con fondo, conteo a ciegas y detección automática de descuadres.
- Gestión de ventas al contado y al crédito; y cuentas por cobrar, con control preventivo de límite de crédito.
- Gestión de inventario digital y bodega física, con sincronización periódica entre registros y existencias reales.
- Control de entregas y retiros de mercancía o productos, totales, parciales o diferidos en el tiempo.
- **Gestión de devoluciones, notas de crédito y mermas**, con destino definido de la mercancía retornada y resarcimiento al cliente por la vía contablemente correcta. *(Explicitado en v1.1: construido en Fase 1 y no listado en la v1.0.)*
- Gestión básica de clientes: registro, datos de contacto, direcciones e historial de compras. Los datos de contacto y direcciones quedan como base para la gestión de entregas y calidad de servicio prevista en Fase 2. El análisis de comportamiento comercial y la clasificación avanzada de clientes se incorporan en Fase 2 (ver sección 3.3, Roadmap).
- Seguimiento de compras y proveedores, con validación de proveedor aprobado y cruce de tres vías en la recepción.
- Monitoreo del cumplimiento operativo del personal.
- Reportes operativos, administrativos y financieros periódicos, y los mecanismos de conciliación entre estos reportes.
- **Definición de metas de negocio por indicador y periodo**, con seguimiento automático del porcentaje de avance. *(Explicitado en v1.1.)*

### 3.2 Exclusiones

Las siguientes exclusiones aplican a la Fase 1 y no constituyen compromiso de desarrollo futuro, salvo evaluación explícita (ver sección 3.3, Roadmap):

- Migración de datos del negocio del usuario preexistentes al momento de entrar en producción el sistema.
- Contabilidad financiera, salvo cuentas por pagar asociadas a compras y proveedores como parte del control operativo, no contable.
- Contabilidad fiscal formal.
- Nómina avanzada, gestión laboral legal y obligaciones legales laborales.
- Seguridad social.
- Integraciones bancarias complejas.
- Créditos bancarios.
- Créditos con terceros o financiamiento externo.
- Inventario patrimonial del negocio.
- Inventario patrimonial personal del propietario (propiedades, ubicación, áreas, linderos, construcciones, divisiones, etc.).
- Sistema de videovigilancia o hardware de cámaras.
- **Envío de alertas y reportes por canales externos** (correo, mensajería): la detección, la clasificación por severidad y la consulta en plataforma sí forman parte de la Fase 1. *(Precisado en v1.1.)*

### 3.3 Roadmap de evolución (Fase 2 y siguientes)

El presente documento define el alcance de negocio de la Fase 1 (v1.0) de Gintly: el núcleo de control operativo-financiero interno. Las fases siguientes no sustituyen el sistema, sino que lo amplían con nuevas capacidades.

**Fase 2 (v2.0) — Inteligencia comercial y automatización de avisos**

- Logística de reparto, rutas y control de tiempos de calidad de servicio (SLA), apoyada en las direcciones de clientes registradas en Fase 1.
- Análisis de comportamiento comercial de clientes.
- Identificación de patrones de consumo y estacionalidad.
- Clasificación de clientes (fieles, estacionales, ocasionales).

**Capacidades diferidas de forma deliberada durante la construcción de la Fase 1** *(Incorporadas en v1.1.)* Se listan aquí para dejar constancia de que no son omisiones, sino decisiones de alcance tomadas y registradas:

| **Capacidad** | **Razón del diferimiento** |
| --- | --- |
| Trazabilidad detallada de los abonos realizados a proveedores | Fase 1 controla el saldo y bloquea el sobre-pago; el detalle abono a abono es un refinamiento contable. |
| Perfilamiento dinámico y clasificación de clientes | Requiere volumen histórico para ser significativo; el historial ya se acumula desde Fase 1. |
| Saldo a favor del cliente como cifra consolidada | Las notas de crédito emitidas ya constituyen el respaldo del crédito disponible. |
| Detección automática de omisión de registro en ventanas de alta demanda | Requiere parametrizar antes las franjas críticas de cada negocio. La regla ya existe. |
| Envío de alertas y reportes por canal externo | Detección y severidad operativas; falta el motor de distribución. |
| Precisión del corte diario según huso horario en negocios multi-región | Los totales por periodo ya usan el huso del negocio; solo afecta el corte de medianoche. |

**Fases futuras (v3.0 en adelante) — sujetas a evaluación de negocio**

Los elementos listados como exclusiones en la sección 3.2 (contabilidad fiscal formal, integraciones bancarias, nómina avanzada, entre otros) podrán evaluarse como candidatos para fases futuras, en función de la evolución del producto y la demanda de los propietarios de negocio. Su mención en este documento no constituye un compromiso de desarrollo.

---

## 4. Restricciones de negocio

Gintly se despliega bajo un modelo B2B SaaS (Business-to-Business Software as a Service): el propietario del negocio se registra en la plataforma, accede a un plan y obtiene las credenciales provistas por Gintly, sin necesidad de instalar ni mantener infraestructura local o propia.

La arquitectura es multi-tenant: cada negocio opera en un espacio de datos aislado dentro de la misma plataforma, garantizando independencia y confidencialidad de la información entre distintos propietarios. **El aislamiento es estructural: la información de un negocio es inaccesible desde otro por diseño, y la identidad del negocio nunca se acepta como dato de entrada, sino que se deriva de la sesión autenticada.** *(Precisado en v1.1.)*

El sistema soporta tanto a propietarios con una única sucursal como a propietarios con múltiples sucursales. No se permite, sin embargo, la habilitación de una sucursal adicional sin la acreditación previa de su existencia operativa real: **dirección, responsable asignado y fecha de apertura son datos obligatorios de toda sucursal**. Esta restricción evita el registro de sucursales ficticias que distorsionarían los indicadores consolidados y la visión de control remoto que constituye el objetivo general del sistema.

**Restricciones adicionales evidenciadas por la construcción** *(Incorporadas en v1.1.)*

- Cada negocio define su **tasa impositiva** y su **huso horario**; ambos gobiernan, respectivamente, la liquidación fiscal de las ventas y los cortes de periodo de los reportes.
- Cada sucursal debe tener designada una **bodega predeterminada**; sin ella no pueden comprometerse ni entregarse mercancías desde esa sucursal.
- Un usuario opera bajo **un único rol activo** y **una única caja abierta** a la vez, y no puede pertenecer a más de un negocio con las mismas credenciales dentro de un mismo espacio.

---

## 5. Stakeholders de negocio

- Propietarios de negocios (usuarios primarios y decisores).
- Administradores locales.
- Empleados operativos.
- Proveedores (interacción indirecta).

---

## 6. Requerimientos de negocio (Business Requirements)

Los diez requerimientos siguientes son de prioridad **Must Have** para la Fase 1. Gintly es, por definición, un sistema de control interno integral, y la ausencia de cualquiera de ellos genera un vacío de control para el propietario. El orden de implementación sugerido no refleja importancia relativa, sino dependencia técnica y de datos entre los componentes del sistema: cada requisito necesita que el anterior exista para producir información confiable.

| **Orden** | **Requisito** | **Nombre** | **Justificación de dependencia** |
| --- | --- | --- | --- |
| **1** | Requisito 1 | Gobernanza y control del personal | Define roles y responsables antes de que cualquier operación pueda registrarse con trazabilidad (Regla b). |
| **2** | Requisito 2 | Control de ingresos y caja | Genera el primer flujo transaccional (ventas/caja) sobre el que se apoyan la conciliación y los reportes. |
| **3** | Requisito 3 | Control de compras y gastos | Genera el flujo transaccional inverso (entradas de inventario por compra), complementario al Requisito 2. |
| **4** | Requisito 4 | Control de bodega (existencia física) | Requiere que existan movimientos de entrada/salida (Req. 2 y 3) para tener contenido que verificar físicamente. |
| **5** | Requisito 5 | Conciliación inventario-bodega | Compara el inventario lógico (Req. 2, 3, 4) contra la existencia física; depende de que ambos existan. |
| **6** | Requisito 6 | Sincronización operativa-administrativa | Consolida en reportes únicos los datos ya validados por los Requisitos 2 a 5. |
| **7** | Requisito 7 | Alertas y prevención de anomalías | Detecta diferencias sobre datos ya consolidados (Req. 6); no puede operar sin esa consolidación previa. |
| **8** | Requisito 8 | Gestión de clientes (alcance Fase 1) | Se apoya en el historial de ventas (Req. 2) ya consolidado; es la capa más cercana al usuario final del negocio. |
| **9** | Requisito 9 | Gestión de ventas al crédito y cuentas por cobrar | Genera un flujo transaccional que se basa en un historial de facturas al crédito y cuentas por cobrar, y por ende, es parte de las conciliaciones y reportes. |
| **10** | Requisito 10 | Control de entregas y retiro de mercancía | Permite registrar y controlar el retiro de mercancías, sea total, parcial o diferido, garantizando la trazabilidad entre lo facturado, lo pagado y lo entregado. |

**Requisito 1: Gobernanza y control del personal**
El negocio requiere identificar responsables directos e indirectos de cada operación y medir el cumplimiento de sus obligaciones.

**Requisito 2: Control de ingresos y caja (apertura y cierre)**
El negocio requiere garantizar que todo ingreso quede registrado, justificado y conciliado con las ventas reales, mediante un control formal de apertura y cierre de caja por cada turno u jornada operativa, asegurando que el efectivo y los medios de pago declarados correspondan con las transacciones de venta efectivamente realizadas.

**Requisito 3: Control de compras y gastos (egresos hacia proveedores y servicios)**
El negocio requiere validar que toda compra a proveedores y todo gasto operativo esté autorizado, registrado y corresponda, según su naturaleza, con entradas reales al inventario (mercancía adquirida) o con la prestación efectiva de servicios recibidos.

| **Nota de trazabilidad** |
| --- |
| Los requisitos 2 y 3 conforman en conjunto el eje de Control Financiero Operativo del sistema: el requisito 2 controla el flujo de entrada (caja/ventas) y el requisito 3 controla el flujo de salida (compras/gastos hacia proveedores y servicios). Ambos comparten el mismo principio de autorización-registro-correspondencia, pero operan sobre procesos de negocio inversos y no deben fusionarse en un único requisito. |

**Requisito 4: Control de bodega (existencia física)**
El negocio requiere controlar y verificar la existencia física real de los productos en bodega, mediante los registros de entradas, salidas, conteos y verificaciones periódicas.

**Requisito 5: Conciliación de inventario - bodega**
El negocio requiere que el sistema permita verificar de forma periódica y trazable la correspondencia entre el inventario registrado y la existencia física en bodega, identificando, cuantificando y documentando las diferencias detectadas, así como asociando causas, responsables y acciones correctivas correspondientes.

**Requisito 6: Sincronización operativa-administrativa**
El negocio requiere consolidar los registros operativos ingresados por los usuarios o el administrador y reflejarlos en reportes administrativos únicos, consistentes y verificables, sin requerir la revisión manual de documentos individuales.

**Requisito 7: Alertas y prevención de anomalías**
El negocio requiere validar la correspondencia entre la operación registrada y los resultados consolidados, detectando y notificando cualquier diferencia, inconsistencia, omisión o uso indebido, mediante alertas dirigidas al propietario.

**Requisito 8: Gestión y análisis de clientes (alcance Fase 1)**
El negocio requiere conocer el flujo de clientes y mantener un registro histórico de sus compras como base para futuras fases de análisis comercial. En Fase 1, el alcance se limita al registro y consulta de dicho historial; el análisis de comportamiento, los patrones de consumo y la clasificación de clientes se abordan en Fase 2 (ver sección 3.3, Roadmap).

**Requisito 9: Gestión de ventas al crédito y cuentas por cobrar**
El negocio requiere validar que toda venta al crédito esté autorizada y registrada; y que todo pago o abono recibido quede registrado en el sistema y corresponda con las facturas al crédito reales registradas.

**Requisito 10: Control de entregas y retiros de mercancías**
El negocio requiere registrar y controlar el retiro físico de las mercancías facturadas, permitiendo entregas totales, parciales o diferidas en el tiempo, y asegurando que la salida de inventario corresponda con retiros efectivamente realizados. Esto garantiza trazabilidad entre lo facturado, lo pagado y lo efectivamente entregado.

Todos los requisitos expuestos tienen **Prioridad: Must Have (Fase 1)**.

**Estado a la fecha de esta versión:** los diez requisitos están construidos y validados. El Requisito 7 lo está en su capacidad de detección, clasificación y consulta; el envío de alertas por canal externo se difiere a Fase 2. El Requisito 8 lo está en su alcance de Fase 1 (registro e historial).

---

## 7. Reglas de negocio (Business Rules)

Las reglas de negocio son restricciones transversales que condicionan a varios requisitos. **En la v1.1 cada regla incorpora el mecanismo por el cual el sistema construido la hace cumplir**, de modo que deje de ser una declaración de intención y pase a ser una condición verificable ante auditoría. La columna de correspondencia enlaza cada regla con su codificación en el FRD v1.1.

| **Regla** | **Enunciado** | **Cómo la hace cumplir el sistema** | **FRD v1.1** |
| --- | --- | --- | --- |
| **a)** | Toda operación crítica debe quedar registrada en el sistema. | Bitácora de auditoría que registra usuario, acción, entidad afectada, valor anterior, valor nuevo y momento. Es de **solo registro**: no admite modificación ni borrado por ninguna vía, ni siquiera administrativa. Ninguna transacción se elimina físicamente; se anula conservando estado y pista. | BR-04 |
| **b)** | Ningún movimiento operativo, financiero o de inventario puede existir sin responsable asignado. | Todo movimiento de caja, ajuste de inventario, conteo, recepción, retiro y devolución exige responsable identificado. Los egresos de caja exigen además el **autorizante**, y el sistema rechaza el registro si falta. | BR-06 |
| **c)** | Toda venta al crédito genera una cuenta por cobrar trazable y todo abono queda registrado con responsable. | Una factura a crédito genera **exactamente una** cuenta por cobrar, creada en el mismo acto: si una falla, ninguna queda registrada. Cada abono queda asociado a responsable, fecha y medio de pago, y es **inalterable** una vez registrado. El saldo se deriva del sistema y no puede quedar negativo. | BR-07 |
| **d)** | Toda anomalía o evento que genere alerta será justificado exclusivamente mediante validación expresa del administrador, quedando registrada y trazable. El usuario operativo no podrá autojustificarse ni cerrar alertas por iniciativa propia. | El sistema **identifica al responsable del hecho que originó la anomalía** —el cajero de la caja descuadrada, el operario del conteo, el receptor de la mercancía— y **rechaza** que sea él quien la justifique. Toda transición de estado deja constancia automática de quién, cuándo y desde qué estado. | BR-01 |
| **e)** | Las áreas operativas y administrativas deben reportar bajo una única fuente de datos. | La consolidación es automática y **no permite editar los registros base**: el motor de conciliación solo lee y produce hallazgos. El conteo físico prevalece sobre el registro del sistema al aplicarse. Toda cifra derivada (disponible, diferencia, saldo, pendiente) se calcula, no se captura. | BR-03 |
| **f)** | El uso del sistema es obligatorio para roles definidos como críticos. | Sin apertura de caja no es posible vender ni registrar cobros en efectivo: el punto de venta queda bloqueado. El cumplimiento se mide de forma objetiva sobre la actividad efectivamente registrada por cada usuario. | BR-02 |
| **g)** | Los reportes deben basarse exclusivamente en datos registrados en el sistema. | Los indicadores se derivan de las transacciones. Los resúmenes calculados son **caché recalculable y nunca fuente de verdad**: ante cualquier sospecha de inconsistencia se descartan y se recalculan desde las transacciones origen. El sistema no sirve un dato dudoso. | BR-03 |
| **h)** | **Cobro íntegro.** No se confirma ninguna venta al contado cuyo pago no cubra el 100% del total. *(Elevada a regla en v1.1.)* | La validación es previa a todo registro: si el pago no iguala el total, **no queda registrada la factura, ni el folio, ni el compromiso de inventario, ni el movimiento de caja**. Se admite pago mixto siempre que cubra la totalidad. Las ventas a crédito quedan exceptuadas y se rigen por la regla c). | BR-05 |
| **i)** | **Inconsistencia activa.** Toda diferencia no validada —caja, inventario–bodega, recepción— bloquea los cierres asociados hasta su resolución. *(Elevada a regla en v1.1.)* | Estados de bloqueo reales y persistentes: caja **descuadrada**, cuenta por pagar **congelada** que no admite pagos, recepción **en discrepancia** que impide el ingreso de mercancía. La evidencia del hecho se conserva precisamente para permitir que un rol superior lo resuelva. | BR-02 |

> **Nota sobre h) e i).** No son requisitos nuevos: eran condiciones implícitas en los Requisitos 2, 3 y 7. La construcción las evidenció como restricciones transversales, por lo que se elevan a regla explícita para verificarlas de forma independiente.

---

## 8. Roles de negocio

Los códigos de rol enlazan este catálogo con el Planteamiento y con los actores del documento de Requerimientos Funcionales. **En la v1.1 se precisan las decisiones que el sistema reserva en exclusiva a cada rol.**

| **Código** | **Rol** | **Descripción** | **Decisiones reservadas en exclusiva** |
| --- | --- | --- | --- |
| **ROL-01** | Propietario | Supervisa, aprueba decisiones críticas y recibe reportes consolidados. | Anular una factura; aprobar o suspender un proveedor; resolver una discrepancia de recepción; desbloquear una cuenta por pagar; autorizar una venta a crédito que excede el cupo del cliente; autorizar un reembolso en efectivo; fijar metas y umbrales de alerta. |
| **ROL-02** | Administrador | Gestiona la operación diaria y valida procesos clave. | Justificar anomalías (nunca las propias); autorizar egresos de caja; autorizar la reversión de un retiro; gestionar usuarios, catálogos, clientes, proveedores y bodegas. |
| **ROL-03** | Empleado operativo | Registra las operaciones asignadas según su función. | Abrir y cerrar su caja; registrar ventas, cobros, recepciones, conteos, traspasos, retiros y devoluciones. No puede validar sus propios hallazgos. |

Un usuario mantiene **exactamente un rol activo** a la vez, y un usuario sin rol asignado no puede acceder al sistema.

---

## 9. Beneficios de negocio esperados

- Reducción significativa de fugas de capital y control de productos/servicios.
- Control remoto real operativo-administrativo, no basado en declaraciones, sino en datos operativos efectivos, que abarca de forma transversal las áreas críticas de la operación y su correspondencia obligatoria con los registros administrativos.
- Identificación temprana de ineficiencias y malas prácticas.
- Mejora en la rentabilidad y sostenibilidad del negocio.
- Tranquilidad operativa y financiera para el propietario.
- Doble valor de uso: para el personal operativo, Gintly funciona como una herramienta de trabajo diario que organiza su gestión y genera un reporte de cierre antes de finalizar cada turno; para el propietario, ese mismo flujo de datos constituye el mecanismo de control interno, prevención de fraude y fuente de información veraz y en tiempo real para la toma de decisiones.
- **Defensa demostrable ante terceros.** Cada regla se hace cumplir por varios mecanismos independientes y verificables, lo que permite sostener la confiabilidad del dato ante auditores, socios o entidades financieras. *(Incorporado en v1.1.)*

---

## 10. Indicadores de éxito (KPIs de negocio)

**En la v1.1 se precisa la fuente de medición efectiva de cada indicador**, confirmando que ninguno depende de captura manual.

| **Código** | **Indicador** | **Qué mide** | **Fuente de datos** | **Meta / Objetivo** |
| --- | --- | --- | --- | --- |
| **KPI-01** | Correspondencia entre ventas, caja e inventario | Integridad cruzada | Facturas emitidas contrastadas con los cobros recibidos y las cuentas por cobrar generadas en el periodo | No. Índice de salud |
| **KPI-02** | Índice de correspondencia entre bodega e inventario | Integridad física | Desviación de los conteos físicos frente al registro del sistema | Sí. Meta a la baja |
| **KPI-03** | Reducción de faltantes no justificados | Control antifraude | Anomalías de faltante activas y no justificadas | Sí. Meta a la baja |
| **KPI-04** | Uso consistente del sistema por parte del personal clave | Cumplimiento de personal | Actividad por usuario en la bitácora de auditoría, sobre el personal habilitado | Sí. Meta % |
| **KPI-05** | Evolución de ventas por periodo | Comercial | Facturas emitidas; incluye ticket promedio y número de comprobantes | Sí. Meta de montos |
| **KPI-06** | Cumplimiento de metas operativas y comerciales | Meta agregadora | Promedio del avance de los demás indicadores frente a sus metas; **se calcula al final del ciclo** | Es el agregador. No es meta propia |
| **KPI-07** | Disponibilidad de reportes diarios, semanales y mensuales confiables | SLA operativo | Corridas de conciliación programadas completadas con éxito sobre el total ejecutado | No. Métrica de servicio |
| **KPI-08** | Índice de recuperación de cartera / saldo por cobrar | Financiero | Cartera emitida, recuperada, pendiente y vencida | Sí. Meta % |

**Indicadores derivados admitidos como meta** *(Incorporados en v1.1.)*: margen bruto, ticket promedio y rotación de inventario. El propietario puede fijarles meta desde la Fase 1; su cálculo automático se incorpora en Fase 2.

---

## 11. Dependencias y supuestos

- El personal involucrado en la operación y administración del sistema se capacitará básica y continuamente conforme al manual de usuario y los lineamientos definidos en la aplicación web, siendo condición necesaria para el uso del sistema.
- El propietario respaldará la obligatoriedad del sistema.
- El sistema será la fuente única de verdad operativa.
- Se asume que la adopción sostenida del sistema por parte del personal operativo estará condicionada a que este lo perciba como una herramienta que facilita su trabajo diario —mediante reportes de cierre claros y accesibles al final de cada turno— y no únicamente como un mecanismo de supervisión. Este factor de adopción debe gestionarse activamente mediante capacitación y comunicación adecuada del propósito del sistema.
- **El negocio designa una bodega predeterminada por sucursal y define su tasa impositiva y su huso horario antes de operar.** Son datos de configuración inicial sin los cuales no pueden emitirse comprobantes ni entregarse mercancía. *(Incorporado en v1.1.)*
- **El propietario parametriza los umbrales de alerta.** El sistema entrega valores por defecto, pero su sensibilidad es una decisión de negocio, no técnica. *(Incorporado en v1.1.)*
- **La conciliación y el cálculo de indicadores se ejecutan de forma programada y diaria**, lo que condiciona la disponibilidad de los reportes a ese servicio. *(Incorporado en v1.1.)*

---

## 12. Matriz de trazabilidad (Objetivo → Requisito → Regla → KPI)

Esta matriz constituye el punto único de referencia cruzada del documento: reemplaza la necesidad de repetir enlaces de trazabilidad a lo largo de las demás secciones y permite auditar que cada objetivo tiene requisitos, reglas y KPI que lo respaldan.

| **Objetivo** | **Requisito(s)** | **Regla(s)** | **KPI(s)** |
| --- | --- | --- | --- |
| **01 — Trazabilidad ventas-caja-inventario** | Req. 1, 2, 3, 4, 9, 10 | Reglas a, b, c, h | KPI-01, KPI-02 |
| **02 — Información confiable y oportuna** | Req. 5, 6, 7 | Reglas a, e, g | KPI-05, KPI-06, KPI-07 |
| **03 — Detección y prevención de malas prácticas** | Req. 3, 5, 7, 9 | Reglas b, c, d, i | KPI-03, KPI-08 |
| **04 — Disciplina operativa** | Req. 1, 2, 7 | Reglas d, f | KPI-04 |

> **Corrección aplicada en v1.1.** El objetivo 04 remitía en la v1.0 al Requisito 8 (clientes) y a la Regla e, sin relación con la medición de cumplimiento del personal; se corrige hacia los Requisitos 1, 2 y 7 y las Reglas d y f. Se incorporan además las reglas h) e i) y los Requisitos 9 y 10, ausentes de la matriz original.

---

## 13. Glosario de términos

| **Término** | **Definición** |
| --- | --- |
| **PYME** | Pequeña y mediana empresa. |
| **Inventario (registro lógico)** | Representación digital de las existencias de productos según los movimientos registrados en el sistema (ventas, compras, ajustes). |
| **Bodega (existencia física)** | Cantidad real y verificable de productos presentes físicamente en el almacén, obtenida mediante conteo o verificación directa. |
| **Conciliación inventario-bodega** | Proceso de comparación entre el inventario lógico y la existencia física, con el fin de identificar, cuantificar y justificar diferencias. |
| **Fuga de capital** | Pérdida de recursos financieros del negocio derivada de errores, omisiones o malas prácticas no detectadas oportunamente. |
| **Trazabilidad** | Capacidad del sistema de vincular cada operación registrada con su responsable, fecha, origen y documentación de soporte. |
| **Tenant (arrendatario digital)** | En un modelo SaaS multi-tenant, cada negocio (cliente) opera en un espacio de datos aislado dentro de la misma plataforma. |
| **Alerta operativa** | Notificación generada por el sistema ante una inconsistencia, diferencia o anomalía detectada entre lo operado y lo consolidado, sujeta a validación exclusiva del administrador. |
| **Arqueo ciego** | Conteo del efectivo realizado sin que el cajero conozca el saldo que el sistema espera, para evitar que el conteo se ajuste a la cifra teórica. Es el mecanismo que hace confiable el cierre de caja. |
| **Mercancía comprometida vs. entregada** | Facturar **compromete** la mercancía: queda vendida pero sigue en bodega. Solo el retiro la **descuenta** físicamente. Esta distinción permite vender hoy y entregar después sin descuadrar el inventario. |
| **Cruce de tres vías** | Verificación de que lo ordenado al proveedor, lo recibido en bodega y lo facturado por el proveedor coincidan. Si no coinciden, la mercancía no ingresa y la deuda se congela. |
| **Nota de crédito** | Documento que respalda una devolución y determina cómo se resarce al cliente: reduciendo su deuda, devolviéndole efectivo o dejándole saldo a favor. |
| **Merma** | Mercancía devuelta o perdida que no puede reingresar al inventario vendible y se registra como pérdida de la sucursal. |
| **Anomalía activa** | Diferencia detectada y aún no justificada ni resuelta. Mientras permanece activa, bloquea los cierres asociados y el sistema no genera una alerta duplicada por el mismo hecho. |

---

*Fin del documento. GINTLY · Documento de Requisitos de Negocio (BRD) v1.1 · 21 de julio de 2026.*
