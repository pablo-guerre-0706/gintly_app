# GINTLY · Diccionario de Datos — v1.1

**Stack:** Laravel 13 · PHP 8.3 · MySQL 8 · Spatie Laravel-Permission v8 (modo *teams*)
**Alcance:** 52 tablas de dominio + 6 vistas de agregación · MOD-01 a MOD-12 (más tablas de infraestructura del framework: `migrations`, `sessions`, `cache`, `jobs`, `failed_jobs`, `password_reset_tokens`, `personal_access_tokens`, etc.)

### Historial de revisiones

| **Versión** | **Fecha** | **Descripción** |
| --- | --- | --- |
| **1.0** | 26 de junio de 2026 | Diccionario preliminar derivado del modelado conceptual. |
| **1.1** | 21 de julio de 2026 | **Estructura final sincronizada con las migraciones y modelos de Laravel.** Refleja el esquema efectivamente construido y validado módulo por módulo: incorpora las tablas añadidas durante la implementación (`goods_receipt_items`, `document_sequences`), los campos materializados (`dispatched_quantity`, `returned_quantity`, `recipe_snapshot`, `counted_denominations`), las columnas generadas por el motor (`difference`, `balance`, candados de unicidad parcial) y todas las restricciones CHECK aprobadas. |
| **1.1-cierre** | 26 de septiembre de 2026 | **Cierre documental de la auditoría global (MOD-01→12).** Se incorporan las tablas y columnas añadidas durante las reconciliaciones posteriores: `stock_transfer_items` (MOD-03), `sequences` (folios OC-/TR-), subsistema fiscal normalizado `tax_rules` + `products.tax_class` (retira `products.is_taxable`) + fotografía fiscal en `sale_items` (MOD-07), `receivable_payments.invoice_payment_id` (enlace fiscal 1:1, MOD-08), `credit_note_resolutions` (MOD-10), `register_wizards` (asistente de alta). `businesses.tax_rate` pasa a nullable (regla fiscal como fuente). Verificado contra `information_schema`: 65 tablas base + 6 vistas, 169 FK, 57 CHECK, 42 índices UNIQUE, 19 columnas generadas. |

### Convenciones

| **Notación** | **Significado** |
| --- | --- |
| `PK` / `FK` / `UQ` / `IDX` | Clave primaria / foránea / única / índice |
| `NN` / `NULL` | Obligatorio / admite nulo |
| `GEN` | Columna **generada** por el motor: no escribible por la aplicación |
| `CASC` / `RSTR` / `SETNULL` | Comportamiento de la FK al borrar: cascada / bloquea / anula |
| **bcmath** | Campo decimal operado con aritmética exacta (`bcadd`, `bcsub`, `bcmul`, `bccomp`). **Nunca float.** |
| `SD` | Tabla con borrado lógico (`deleted_at`) |
| `INS` | Tabla de solo inserción (append-only): sin `updated_at`, sin UPDATE ni DELETE |

Toda tabla incluye `business_id` (aislamiento multi-tenant, fuera de asignación masiva) y `id BIGINT UNSIGNED AUTO_INCREMENT PK`. Para no repetirlos, se listan una sola vez por tabla.

---

## MOD-01 — Seguridad, Identidad y Auditoría

### `users` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| id | bigint | PK, unsigned | PK | Identificador. |
| business_id | bigint | NN, unsigned, FK CASC | UQ(business_id,email) | Negocio al que pertenece. FK diferida. |
| branch_id | bigint | NULL, unsigned, FK SETNULL | — | Sucursal asignada. FK diferida. |
| name | string(150) | NN | — | Nombre del usuario. |
| email | string(180) | NN | UQ(business_id,email) | Correo. **Único por negocio, no global.** |
| password | string | NN | — | Hash de contraseña (cast `hashed`). |
| is_active | boolean | NN, def. true | — | Habilitado para autenticarse. |
| last_login_at | timestamp | NULL | — | Último acceso; lo escribe un listener. |
| remember_token | string(100) | NULL | — | Token de sesión persistente. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `businesses` · SD · raíz del tenant
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| id | bigint | PK, unsigned | PK | Identificador del negocio. |
| name | string(150) | NN | — | Razón social o nombre comercial. |
| slug | string(160) | NN | UQ | Identificador legible único global. |
| owner_user_id | bigint | NULL, unsigned, FK SETNULL | — | Propietario. FK diferida (cierra el ciclo). |
| plan | string(50) | NN, def. 'basic' | — | Plan contratado del SaaS. |
| status | enum | NN, def. 'trial' | IDX | `active` / `suspended` / `trial`. |
| tax_rate | decimal(5,4) | NULL, def. 0.1500 | — | Tasa de IVA histórica del negocio. **Nullable desde MOD-07:** la tasa efectiva se resuelve por `tax_rules` (clase fiscal + ámbito); se conserva por compatibilidad y como semilla del backfill. **bcmath.** |
| timezone | string(64) | NN, def. 'America/Managua' | — | Huso IANA; rige cortes de periodo. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `branches` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| name | string(150) | NN | — | Nombre de la sucursal. |
| address | string(255) | NN | — | Dirección física (acreditación obligatoria). |
| manager_user_id | bigint | NULL, FK SETNULL | — | Usuario encargado. |
| opened_at | date | NN | — | Fecha de apertura (acreditación obligatoria). |
| is_active | boolean | NN, def. true | — | Sucursal operativa. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `audit_logs` · INS · **inmutable**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK RSTR | — | Negocio; bloquea el borrado con historial. |
| user_id | bigint | NULL, FK RSTR | — | Autor de la acción. |
| action | string(100) | NN | IDX | Acción ejecutada (create, update, void…). |
| auditable_type | string(120) | NN | IDX(type,id) | Entidad afectada (morph manual). |
| auditable_id | bigint | NULL, unsigned | IDX(type,id) | Id de la entidad afectada. |
| old_values | json | NULL | — | Valores previos al cambio. |
| new_values | json | NULL | — | Valores posteriores al cambio. |
| ip_address | string(45) | NULL | — | Origen de la petición (IPv4/IPv6). |
| created_at | timestamp | NULL | IDX | Momento del hecho. Cast `immutable_datetime`. |

### RBAC (Spatie · modo teams)
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. La columna de equipo es **`business_id`**, lo que aísla los roles por negocio. Regla de dominio: **un usuario mantiene exactamente un rol activo** (`syncRoles`).

### `user_operative_profiles` · perfiles operativos de ROL-03 *(Fase 3)*
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | IDX | Negocio propietario (aislamiento). |
| user_id | bigint | NN, FK CASC | UQ(user_id,profile) | Usuario ROL-03 al que se asigna el perfil. |
| profile | enum | NN | UQ(user_id,profile) | `cajero` / `facturador` / `bodeguero` / `despachador`. |
| assigned_by | bigint | NULL, FK RSTR | — | Quién asignó el perfil (no-repudio). |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**UNIQUE:** `uniq_user_operative_profile` (user_id, profile). **Nota:** ROL-03 es un ÚNICO rol; los perfiles son capacidades COMBINABLES. El mapa perfil→capacidades vive en `config/profiles.php` (fuente única, reutiliza el catálogo de permisos). Un ROL-03 sin filas aquí queda **bloqueado operativamente** (opción B, mínimo privilegio); no se asignan perfiles por compatibilidad. ROL-01/ROL-02 no usan perfiles.

### `register_wizards` · asistente de alta multi-paso (web)
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| user_id | bigint | NULL, FK CASC | — | Usuario en proceso de registro (borrador del asistente). |
| nombres / apellidos / email | string | NULL | — | Paso 1: perfil inicial. |
| nombre_tienda / pais / ciudad / codigo_postal / direccion / email_tienda / telefono_tienda / ruc_identificacion | string | NULL | — | Paso 2: datos de la tienda. |
| numero_sucursales | smallint | NN, def. 1, unsigned | — | Sucursales previstas. |
| tipo_negocio | string(50) | NULL | — | Paso 3: giro del negocio. |
| empleado_nombres / empleado_apellidos / empleado_email / empleado_telefono / empleado_rol | string | NULL | — | Pasos 4-5: datos del empleado. |
| plan_seleccionado | string(50) | NULL | — | Paso 7: plan. |
| frecuencia_pago | enum | NN, def. 'mensual' | — | `mensual` / `anual`. |
| titular_razon_social / email_facturacion | string | NULL | — | Paso 8: facturación. |
| metodo_pago | enum | NULL | — | `tarjeta` / `transferencia`. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**Nota:** borrador del asistente de registro (rutas web `register/*` + `RegisterWizardController`); NO forma parte del núcleo transaccional multi-tenant. El aprovisionamiento atómico definitivo del negocio (propietario, roles, cliente genérico, bodega/caja predeterminada, secuencias, reglas de anomalía) lo ejecuta el flujo de provisioning, no esta tabla.

---

## MOD-02 — Catálogo y Datos Maestros

### `categories` · SD · auto-referencia
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,name) | Negocio propietario. |
| parent_id | bigint | NULL, FK RSTR (self) | — | Categoría padre. Anti-ciclo en dominio. |
| name | string(120) | NN | UQ(business_id,name) | Nombre de la categoría. |
| is_active | boolean | NN, def. true | — | Disponible para clasificar. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `brands` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,name) | Negocio propietario. |
| name | string(120) | NN | UQ(business_id,name) | Nombre de la marca. |
| is_active | boolean | NN, def. true | — | Disponible para etiquetar. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `units_of_measure` · sin SD (protegida por RESTRICT)
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,abbreviation) | Negocio propietario. |
| name | string(50) | NN | — | Nombre (kilogramo, litro, porción…). |
| abbreviation | string(10) | NN | UQ(business_id,abbreviation) | Abreviatura (kg, L, u). |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

### `products` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,sku) | Negocio propietario. |
| category_id | bigint | NN, FK RSTR | — | Categoría de clasificación. |
| brand_id | bigint | NULL, FK SETNULL | — | Marca (opcional). |
| unit_id | bigint | NN, FK RSTR | — | Unidad de medida base. |
| sku | string(60) | NN | UQ(business_id,sku) | Código. Inmutable si hay transacciones. |
| name | string(160) | NN | IDX | Nombre comercial. |
| type | enum | NN | IDX | `simple` / `compound` / `service`. |
| sale_price | decimal(12,2) | NN, def. 0, CHECK ≥ 0 | — | Precio de venta. **bcmath.** |
| cost | decimal(12,2) | NN, def. 0, CHECK ≥ 0 | — | Costo de referencia. **bcmath.** |
| tracks_inventory | boolean | NN, def. true | — | Descuenta stock. CHECK: servicio ⇒ false. |
| tax_class | enum | NN | IDX | `standard` / `reduced` / `zero_rated` / `exempt`. **Fuente fiscal única del producto (MOD-07).** Reemplazó al booleano `is_taxable`, que se retiró como columna y hoy es un accesor derivado (`tax_class <> 'exempt'`). La tasa se resuelve por `tax_rules`. |
| is_active | boolean | NN, def. true | — | Ofertable en el POS. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

**CHECK:** `chk_products_sale_price` · `chk_products_cost` · `chk_service_no_inventory` (`type <> 'service' OR tracks_inventory = 0`).
**Nota fiscal (MOD-07):** `is_taxable` fue eliminada por la migración de backfill fiscal; `tax_class` es la única fuente escribible y `is_taxable` sobrevive solo como accesor de lectura en el modelo.

### `product_recipes` · sin timestamps · auto-referencia doble
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| compound_id | bigint | NN, FK CASC | UQ(compound_id,ingredient_id) | Producto compuesto (el que se arma). |
| ingredient_id | bigint | NN, FK RSTR | UQ(compound_id,ingredient_id) | Insumo que lo compone. |
| quantity | decimal(12,3) | NN, CHECK > 0 | — | Cantidad de insumo por unidad. **bcmath.** |
| unit_id | bigint | NN, FK RSTR | — | Unidad en que se expresa la cantidad. |

**CHECK:** `chk_recipe_quantity` (> 0) · `chk_recipe_no_self` (`compound_id <> ingredient_id`).

---

## MOD-03 — Inventario Lógico y Bodega Física

### `warehouses` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,branch_id,name) | Negocio propietario. |
| branch_id | bigint | NN, FK RSTR | UQ(business_id,branch_id,name) | Sucursal a la que pertenece. |
| name | string(120) | NN | UQ(business_id,branch_id,name) | Nombre de la bodega. |
| is_default | boolean | NN, def. false | — | Bodega que abastece ventas y retiros. |
| is_active | boolean | NN, def. true | — | Bodega operativa. |
| default_lock | bigint | **GEN**, unsigned, virtual | UQ | `branch_id` si `is_default`, si no NULL. **Máx. una default por sucursal.** |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `stock_levels` · saldo mutable · solo `updated_at`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| product_id | bigint | NN, FK RSTR | UQ(product_id,warehouse_id) | Producto. |
| warehouse_id | bigint | NN, FK RSTR | UQ(product_id,warehouse_id) | Bodega. Un saldo por par. |
| quantity | decimal(14,3) | NN, def. 0, CHECK ≥ 0 | — | Existencia física. **bcmath.** Solo lo escribe el servicio de inventario. |
| reserved_quantity | decimal(14,3) | NN, def. 0, CHECK ≥ 0 | — | Cantidad comprometida por facturación. **bcmath.** |
| min_stock | decimal(14,3) | NULL | — | Umbral de reposición. **bcmath.** |
| max_stock | decimal(14,3) | NULL | — | Umbral máximo. **bcmath.** |
| average_cost | decimal(14,4) | NN, def. 0 | — | Costo promedio ponderado. **bcmath.** |
| updated_at | timestamp | NULL | — | Última modificación del saldo. |

**CHECK:** `chk_stock_qty_non_negative` · `chk_stock_reserved_non_negative` · `chk_stock_available_non_negative` (`reserved_quantity <= quantity`, anti-sobreventa de motor).
**Derivado (accesor):** `available = quantity − reserved_quantity`.

### `physical_counts`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| product_id | bigint | NN, FK RSTR | — | Producto contado. |
| warehouse_id | bigint | NN, FK RSTR | — | Bodega donde se contó. |
| user_id | bigint | NN, FK RSTR | — | Responsable del conteo. |
| system_quantity | decimal(14,3) | NN | — | Existencia que declaraba el sistema. **bcmath.** |
| counted_quantity | decimal(14,3) | NN | — | Existencia contada físicamente. **bcmath.** |
| difference | decimal(14,3) | **GEN** stored | — | `counted − system`. No editable. **bcmath.** |
| status | enum | NN | IDX | `abierto` / `justificado` / `ajustado`. |
| notes | string(500) | NULL | — | Observación del conteo. |
| counted_at | timestamp | NN | — | Momento del conteo. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

### `stock_transfers`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,code) | Negocio propietario. |
| from_warehouse_id | bigint | NN, FK RSTR | — | Bodega origen. |
| to_warehouse_id | bigint | NN, FK RSTR | — | Bodega destino. |
| user_id | bigint | NN, FK RSTR | — | Responsable del traspaso. |
| code | string(30) | NN | UQ(business_id,code) | Folio interno del traspaso. |
| status | enum | NN | IDX | `pendiente` / `completado` / `cancelado`. |
| transferred_at | timestamp | NN | — | Momento del traspaso. |
| notes | string(500) | NULL | — | Observación. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_transfer_diff_warehouse` (`from <> to`).

### `stock_transfer_items` · sin timestamps *(añadida en la reconciliación MOD-03)*
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| stock_transfer_id | bigint | NN, FK CASC | UQ(stock_transfer_id,product_id) | Traspaso al que pertenece. La línea vive y muere con su traspaso. |
| product_id | bigint | NN, FK RSTR | UQ(stock_transfer_id,product_id) | Producto trasladado (no se repite en el mismo traspaso). |
| quantity | decimal(14,3) | NN, CHECK > 0 | — | Cantidad a trasladar. **bcmath.** |

**CHECK:** `chk_transfer_item_qty` (> 0). **UNIQUE:** (stock_transfer_id, product_id).
**Nota (opción A):** las líneas se **persisten al crear** (el traspaso nace `pendiente` con ellas); la confirmación las consume bajo lock. El costo no se envía: la entrada en destino se valora al costo promedio de la bodega origen al confirmar.

### `inventory_adjustments`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| warehouse_id | bigint | NN, FK RSTR | — | Bodega ajustada. |
| user_id | bigint | NN, FK RSTR | — | Responsable del ajuste. |
| physical_count_id | bigint | NULL, FK RSTR | — | Conteo que lo originó (si aplica). |
| type | enum | NN | IDX | `merma` / `sobrante` / `correccion`. |
| reason | string(255) | NN | — | Motivo obligatorio del ajuste. |
| adjusted_at | timestamp | NN | — | Momento del ajuste. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

### `inventory_movements` · INS · **kardex inmutable**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| product_id | bigint | NN, FK RSTR | — | Producto movido. |
| warehouse_id | bigint | NN, FK RSTR | — | Bodega afectada. |
| user_id | bigint | NULL, FK RSTR | — | Responsable (NULL = proceso automático). |
| type | enum | NN | IDX | `entrada` / `salida` / `ajuste` / `traspaso`. |
| quantity | decimal(14,3) | NN, CHECK > 0 | — | Cantidad del asiento. **bcmath.** |
| balance_after | decimal(14,3) | NN | — | Saldo resultante (foto del kardex). **bcmath.** |
| unit_cost | decimal(14,4) | NULL | — | Costo unitario del movimiento. **bcmath.** |
| stock_transfer_id | bigint | NULL, FK RSTR | — | Origen: traspaso. |
| inventory_adjustment_id | bigint | NULL, FK RSTR | — | Origen: ajuste. |
| purchase_order_id | bigint | NULL, FK RSTR | IDX | Origen: recepción de compra (FK cableada). |
| dispatch_id | bigint | NULL, FK RSTR | IDX | Origen: retiro (última FK diferida, cableada). |
| reason | string(255) | NULL | — | Descripción del movimiento. |
| created_at | timestamp | NULL | IDX | Momento del asiento. `immutable_datetime`. |

**CHECK:** `chk_movement_quantity` (> 0) · `chk_movement_single_origin` (máximo un origen presente).
**Nota:** las reservas **no** generan asiento. El kardex registra solo movimiento físico.

---

## MOD-04 — Compras, Proveedores y Recepción

### `suppliers` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,tax_id) | Negocio propietario. |
| name | string(160) | NN | IDX | Nombre del proveedor. |
| tax_id | string(30) | NULL | UQ(business_id,tax_id) | RUC/identificación fiscal (NULL múltiples). |
| email | string(180) | NULL | — | Correo de contacto. |
| phone | string(30) | NULL | — | Teléfono de contacto. |
| status | enum | NN, def. 'pendiente' | IDX | `pendiente` / `aprobado` / `suspendido`. |
| approved_by | bigint | NULL, FK RSTR | — | ROL-01 que aprobó. Obligatorio si aprobado. |
| approved_at | timestamp | NULL | — | Momento de la aprobación. |
| is_active | boolean | NN, def. true | — | Proveedor operativo. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `purchase_orders` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,code) | Negocio propietario. |
| branch_id | bigint | NN, FK RSTR | — | Sucursal que ordena. |
| supplier_id | bigint | NN, FK RSTR | — | Proveedor (debe estar aprobado). |
| user_id | bigint | NN, FK RSTR | — | Responsable de la orden. |
| code | string(30) | NN | UQ(business_id,code) | Folio de la orden de compra. |
| status | enum | NN | IDX | `borrador` / `emitida` / `parcial` / `recibida` / `cancelada`. |
| expected_total | decimal(14,2) | NN, def. 0 | — | Total esperado, recalculado del detalle. **bcmath.** |
| ordered_at | date | NN | — | Fecha de la orden. |
| notes | string(500) | NULL | — | Observación. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `purchase_order_items` · sin timestamps
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| purchase_order_id | bigint | NN, FK CASC | UQ(purchase_order_id,product_id) | Orden a la que pertenece. |
| product_id | bigint | NN, FK RSTR | UQ(purchase_order_id,product_id) | Producto ordenado. |
| ordered_quantity | decimal(14,3) | NN, CHECK > 0 | — | Cantidad ordenada. **bcmath.** |
| received_quantity | decimal(14,3) | NN, def. 0, CHECK ≥ 0 | — | Acumulado recibido. **bcmath.** |
| agreed_unit_cost | decimal(14,4) | NN | — | Costo pactado. Pilar del 3-Way Match. **bcmath.** |
| line_total | decimal(14,2) | NN | — | `ordered × agreed`, derivado. **bcmath.** |

**CHECK:** `chk_poi_ordered_qty` (> 0) · `chk_poi_received_non_negative` (≥ 0). *No se limita `received <= ordered`: el exceso debe poder registrarse para que ROL-01 lo resuelva.*

### `goods_receipts`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| purchase_order_id | bigint | NN, FK RSTR | — | Orden que se recibe. |
| warehouse_id | bigint | NN, FK RSTR | — | Bodega receptora. |
| user_id | bigint | NN, FK RSTR | — | Responsable de la recepción. |
| supplier_invoice_number | string(60) | NULL | — | Número de la factura del proveedor. |
| supplier_invoice_total | decimal(14,2) | NULL, CHECK ≥ 0 | — | Total declarado por el proveedor. **bcmath.** |
| match_status | enum | NN | IDX | `ok` / `discrepancia` / `bloqueada`. |
| received_at | timestamp | NN | — | Momento de la recepción. |
| notes | string(500) | NULL | — | Observación. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_gr_invoice_total_non_negative`.

### `goods_receipt_items` · sin timestamps · **evidencia por recepción** *(añadida en v1.1)*
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| goods_receipt_id | bigint | NN, FK CASC | UQ `goods_receipt_order_item_unique` | Recepción a la que pertenece. |
| purchase_order_item_id | bigint | NN, FK RSTR | UQ `goods_receipt_order_item_unique` | Línea de orden que respalda. |
| product_id | bigint | NN, FK RSTR | — | Producto recibido. |
| received_quantity | decimal(14,3) | NN, CHECK > 0 | — | Cantidad recibida en esta entrega. **bcmath.** |
| invoiced_unit_cost | decimal(14,4) | NN | — | Costo facturado por el proveedor. **bcmath.** |
| line_total | decimal(14,2) | NN | — | `recibido × facturado`. **bcmath.** |
| matched | boolean | NN, def. true | — | Resultado del 3-Way Match en esta línea. |

**CHECK:** `chk_gri_received_positive` (> 0).

### `accounts_payable`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| supplier_id | bigint | NN, FK RSTR | — | Proveedor acreedor. |
| purchase_order_id | bigint | NN, FK RSTR | — | Orden que originó la deuda. |
| goods_receipt_id | bigint | NULL, FK RSTR | — | Recepción que la generó. |
| total_amount | decimal(14,2) | NN, CHECK ≥ 0 | — | Monto adeudado. **bcmath.** |
| paid_amount | decimal(14,2) | NN, def. 0, CHECK ≥ 0 | — | Monto abonado (Fase 1: actualización directa). **bcmath.** |
| status | enum | NN, def. 'pendiente' | IDX | `pendiente` / `congelada` / `parcial` / `pagada`. |
| due_date | date | NULL | — | Vencimiento del pago. |
| unblocked_by | bigint | NULL, FK RSTR | — | ROL-01 que descongeló la cuenta. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_ap_total_positive` · `chk_ap_paid_non_negative` · `chk_ap_paid_not_exceed` (`paid <= total`).
**Derivado (accesor):** `balance = total_amount − paid_amount`.

### `sequences` · infraestructura compartida de folios *(añadida en la reconciliación)*
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,type) | Negocio propietario. |
| type | string(50) | NN | UQ(business_id,type) | Tipo de folio (`OC-` órdenes de compra MOD-04, `TR-` traspasos MOD-03). |
| next_value | bigint | NN, def. 1, unsigned | — | Próximo valor a asignar. Contador atómico. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**UNIQUE:** (business_id, type). **Nota:** contador de folio atómico por (negocio, tipo), consumido por `App\Support\SequenceGenerator`; nunca por request. Convive con `document_sequences` (folios fiscales, MOD-07).

---

## MOD-05 — Clientes

### `customers` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,document_number) | Negocio propietario. |
| name | string(160) | NN | IDX | Nombre o razón social. |
| document_type | enum | NN, def. 'cedula' | — | `cedula` / `ruc` / `pasaporte` / `generico`. |
| document_number | string(30) | NULL | UQ(business_id,document_number) | Identificación (NULL múltiples permitidos). |
| email | string(180) | NULL | — | Correo de contacto. |
| phone_number | string(30) | NULL | IDX | Teléfono de contacto. |
| birth_date | date | NULL | — | Fecha de nacimiento (base Fase 2). |
| is_generic | boolean | NN, def. false | IDX | TRUE solo en el "Consumidor Final". Fuera de asignación masiva. |
| is_active | boolean | NN, def. true | — | Cliente operativo. |
| credit_limit | decimal(14,2) | NN, def. 0, CHECK ≥ 0 | — | Cupo de crédito; 0 = no opera a crédito. **bcmath.** |
| notes | string(500) | NULL | — | Observación. |
| generic_lock | bigint | **GEN**, unsigned, virtual | UQ | `business_id` si `is_generic`, si no NULL. **Máx. un genérico por negocio.** |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

**CHECK:** `chk_customer_credit_limit_non_negative`.

### `customer_addresses`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| customer_id | bigint | NN, FK CASC | — | Cliente titular. |
| label | string(50) | NN | — | Etiqueta (casa, obra, bodega…). |
| address_line | string(255) | NN | — | Dirección completa. |
| reference | string(255) | NULL | — | Punto de referencia. |
| is_default | boolean | NN, def. false | — | Dirección preferente. **Sin candado de exclusividad** (decisión de negocio). |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

---

## MOD-06 — Gestión de Caja

### `cash_registers` · SD
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,branch_id,name) | Negocio propietario. |
| branch_id | bigint | NN, FK RSTR | UQ(business_id,branch_id,name) | Sucursal de la caja. |
| name | string(100) | NN | UQ(business_id,branch_id,name) | Nombre de la estación de caja. |
| is_active | boolean | NN, def. true | — | Caja operativa. |
| created_at / updated_at / deleted_at | timestamp | NULL | IDX(deleted_at) | Auditoría y borrado lógico. |

### `cash_sessions`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| cash_register_id | bigint | NN, FK RSTR | — | Caja abierta. |
| opened_by | bigint | NN, FK RSTR | — | Cajero que abrió. |
| closed_by | bigint | NULL, FK RSTR | — | Usuario que cerró. |
| status | enum | NN, def. 'abierta' | IDX | `abierta` / `cerrada` / `descuadrada`. |
| opening_amount | decimal(14,2) | NN, CHECK ≥ 0 | — | Fondo inicial. **bcmath.** |
| expected_amount | decimal(14,2) | NULL | — | Efectivo teórico; **oculto mientras abierta**. **bcmath.** |
| counted_amount | decimal(14,2) | NULL | — | Efectivo declarado en el arqueo. **bcmath.** |
| counted_denominations | json | NULL | — | Desglose de billetes y monedas. Evidencia del arqueo. *(añadido en v1.1)* |
| difference | decimal(14,2) | **GEN** stored | — | `counted − expected`. No editable por el cajero. **bcmath.** |
| opened_at | timestamp | NN | — | Momento de apertura. |
| closed_at | timestamp | NULL | — | Momento de cierre. |
| closing_notes | string(500) | NULL | — | Justificación del cierre. |
| open_register_lock | bigint | **GEN**, unsigned, virtual | UQ | `cash_register_id` si abierta. **Una sesión abierta por caja.** |
| open_user_lock | bigint | **GEN**, unsigned, virtual | UQ | `opened_by` si abierta. **Una sesión abierta por usuario.** *(añadido en v1.1)* |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_cash_session_opening` (≥ 0).

### `cash_movements` · INS · **inmutable**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| cash_session_id | bigint | NN, FK RSTR | IDX `idx_movements_session_payment` | Sesión de caja afectada. |
| user_id | bigint | NN, FK RSTR | — | Responsable del movimiento. |
| type | enum | NN | IDX | `ingreso` / `egreso`. |
| category | enum | NN | IDX | `venta` / `egreso_autorizado` / `retiro` / `ajuste` / `fondo_inicial` / `cobro_credito`. |
| payment_method | enum | NN | IDX `idx_movements_session_payment` | `efectivo` / `transferencia` / `tarjeta`. Solo efectivo cuenta en el arqueo. |
| amount | decimal(14,2) | NN, CHECK > 0 | — | Monto del movimiento. **bcmath.** |
| sale_id | bigint | NULL, FK RSTR | IDX | Venta que lo originó (FK cableada). |
| authorized_by | bigint | NULL, FK RSTR | — | ROL-02 autorizante. Obligatorio en egreso autorizado. |
| description | string(255) | NULL | — | Concepto del movimiento. |
| created_at | timestamp | NULL | IDX | Momento del hecho. `immutable_datetime`. |

**CHECK:** `chk_cash_movement_amount` (> 0) · `chk_cash_movement_egreso_auth` (`category <> 'egreso_autorizado' OR authorized_by IS NOT NULL`).

---

## MOD-07 — Ventas, Facturación e Inmutabilidad

### `sales`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,code) | Negocio propietario. |
| branch_id | bigint | NN, FK RSTR | — | Sucursal emisora. |
| customer_id | bigint | NN, FK RSTR | — | Cliente comprador. |
| user_id | bigint | NN, FK RSTR | — | Cajero responsable. |
| code | string(30) | NN | UQ(business_id,code) | Folio interno de la venta. |
| status | enum | NN, def. 'abierta' | IDX | `abierta` / `confirmada` / `facturada` / `anulada`. |
| table_reference | string(50) | NULL | — | Mesa o referencia operativa. |
| subtotal | decimal(14,2) | NN, def. 0 | — | Suma de líneas, derivada. **bcmath.** |
| notes | string(500) | NULL | — | Observación. |
| opened_at | timestamp | NN | — | Apertura de la venta. |
| confirmed_at | timestamp | NULL | — | Confirmación (habilita facturar). |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

### `sale_items` · sin timestamps · **congela datos maestros**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| sale_id | bigint | NN, FK CASC | — | Venta a la que pertenece. |
| product_id | bigint | NN, FK RSTR | — | Producto vendido. |
| description | string(160) | NN | — | Nombre **congelado** del producto. |
| quantity | decimal(14,3) | NN, CHECK > 0 | — | Cantidad facturada. **bcmath.** |
| unit_price | decimal(14,2) | NN, CHECK ≥ 0 | — | Precio **congelado**. **bcmath.** |
| unit_cost | decimal(14,4) | NN | — | Costo **congelado**. **bcmath.** |
| discount_amount | decimal(14,2) | NN, def. 0, CHECK ≥ 0 | — | Descuento de línea. **bcmath.** |
| line_total | decimal(14,2) | NN, CHECK ≥ 0 | — | `cantidad × precio − descuento`. **bcmath.** |
| recipe_snapshot | json | NULL | — | Composición **congelada** del compuesto. Rige reserva, retiro y reingreso. *(añadido en v1.1)* |
| dispatched_quantity | decimal(14,3) | NN, def. 0 | — | Acumulado entregado. Fuera de asignación masiva. **bcmath.** *(añadido en v1.1)* |
| returned_quantity | decimal(14,3) | NN, def. 0 | — | Acumulado devuelto. Fuera de asignación masiva. **bcmath.** *(añadido en v1.1)* |
| is_taxable | boolean | NN | — | Booleano fiscal **congelado** original de la línea (MOD-07). |
| tax_class | string(20) | NULL | — | Clase fiscal **congelada** al agregar la línea (MOD-07). |
| fiscal_condition | string(20) | NULL | — | Condición fiscal congelada: `gravado` / `tasa_cero` / `exento`. |
| tax_rate | decimal(8,6) | NN, def. 0, CHECK [0,1] | — | Tasa aplicada **congelada** (fracción). **bcmath.** |
| taxable_base | decimal(14,2) | NN, def. 0, CHECK ≥ 0 | — | Base gravable congelada de la línea. **bcmath.** |
| tax_amount | decimal(14,2) | NN, def. 0, CHECK ≥ 0 | — | Impuesto congelado de la línea. **bcmath.** |
| tax_rule_id | bigint | NULL, FK RSTR (tax_rules) | — | Regla fiscal usada (auditoría). Una regla usada no se borra. |

**CHECK:** `chk_sale_item_quantity` (> 0) · `chk_sale_item_price_non_negative` · `chk_sale_item_discount_non_negative` · `chk_sale_item_line_total_non_negative` · `chk_sale_item_dispatch_not_exceed` (`dispatched <= quantity`) · `chk_sale_item_dispatched_non_negative` · `chk_sale_item_return_not_exceed` (`returned <= dispatched`) · `chk_sale_item_returned_non_negative` · `chk_sale_item_tax_rate` ([0,1]) · `chk_sale_item_taxable_base_non_negative` · `chk_sale_item_tax_amount_non_negative`.
**Fotografía fiscal (MOD-07):** al agregar la línea se congela `tax_class/fiscal_condition/tax_rate/taxable_base/tax_amount` + `tax_rule_id`; un cambio posterior de producto/tasa/sucursal no altera una venta confirmada ni una factura emitida.
**Cadena de saldos:** `returned ≤ dispatched ≤ quantity`. **Derivados:** `pending = quantity − dispatched` · `returnable = dispatched − returned`.

### `invoices` · **núcleo fiscal inmutable**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,folio) | Negocio propietario. |
| branch_id | bigint | NN, FK RSTR | — | Sucursal emisora. |
| customer_id | bigint | NN, FK RSTR | — | Cliente facturado. |
| cash_session_id | bigint | NULL, FK RSTR | — | Sesión de caja del cobro. |
| issued_by | bigint | NN, FK RSTR | — | Usuario emisor. |
| folio | string(30) | NN | UQ(business_id,folio) | Folio secuencial. **Generado por el servidor, inmutable.** |
| payment_type | enum | NN, def. 'contado' | IDX | `contado` / `credito`. |
| payment_status | enum | NN | IDX | `pagada` / `parcial` / `pendiente`. Mutable. |
| status | enum | NN, def. 'emitida' | IDX | `emitida` / `anulada`. |
| subtotal | decimal(14,2) | NN | — | Suma de líneas. **bcmath.** |
| tax_amount | decimal(14,2) | NN, def. 0 | — | IVA = base gravable × `tax_rate`. **bcmath.** |
| discount_amount | decimal(14,2) | NN, def. 0 | — | Descuento de factura (post-IVA). **bcmath.** |
| total | decimal(14,2) | NN, CHECK ≥ 0 | — | Total neto a cobrar. **bcmath.** |
| paid_amount | decimal(14,2) | NN, def. 0 | — | Cobrado acumulado. Mutable. **bcmath.** |
| voided_by | bigint | NULL, FK RSTR | — | ROL-01 que anuló. |
| voided_at | timestamp | NULL | — | Momento de la anulación. |
| void_reason | string(255) | NULL | — | Motivo obligatorio de anulación. |
| issued_at | timestamp | NN | — | Fecha de emisión. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_invoice_total` (≥ 0) · `chk_invoice_paid_non_negative` · `chk_invoice_paid_not_exceed` (`paid <= total`) · `chk_invoice_void_coherence` (`status <> 'anulada' OR voided_by IS NOT NULL`).
**Congelados por guarda de modelo:** folio, subtotal, tax_amount, discount_amount, total, issued_at, customer_id, branch_id, payment_type, issued_by.

### `invoice_sale` · puente N:M · sin timestamps
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| invoice_id | bigint | NN, FK CASC | UQ(invoice_id,sale_id) | Factura. |
| sale_id | bigint | NN, FK RSTR | UQ(invoice_id,sale_id) | Venta consolidada o dividida. |

### `invoice_payments` · INS · **inmutable**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| invoice_id | bigint | NN, FK CASC | — | Factura cobrada. |
| cash_session_id | bigint | NULL, FK RSTR | — | Sesión de caja del cobro. |
| user_id | bigint | NN, FK RSTR | — | Responsable del cobro. |
| payment_method | enum | NN | IDX | `efectivo` / `transferencia` / `tarjeta`. Varias filas = pago mixto. |
| amount | decimal(14,2) | NN, CHECK > 0 | — | Monto cobrado. **bcmath.** |
| reference | string(100) | NULL | — | Referencia bancaria o de terminal. |
| paid_at | timestamp | NN | — | Momento del cobro. |
| created_at | timestamp | NULL | — | Registro del hecho. |

**CHECK:** `chk_invoice_payment_amount` (> 0).

### `document_sequences` · contador de folios *(añadida en v1.1)*
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,document_type) | Negocio propietario. |
| document_type | enum | NN | UQ(business_id,document_type) | `invoice` / `credit_note`. |
| prefix | string(10) | NN, def. '' | — | Prefijo del folio (`F-`, `NC-`). |
| next_number | bigint | NN, unsigned, def. 1 | — | Siguiente correlativo. Se asigna con bloqueo de fila. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

### `tax_rules` · subsistema fiscal normalizado *(añadida en MOD-07)*
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | IDX(business_id,tax_class,branch_id) | Negocio propietario. |
| tax_class | enum | NN | IDX | `standard` / `reduced` / `zero_rated` / `exempt`. |
| branch_id | bigint | NULL, FK RSTR | — | NULL = regla general del negocio; con valor = regla específica de sucursal. |
| rate | decimal(8,6) | NN, CHECK [0,1] | — | Tasa como fracción (0.150000 = 15 %). **bcmath.** |
| is_active | boolean | NN, def. true | — | Versionado sin borrado: las reglas se desactivan, no se eliminan. |
| active_scope_lock | string(64) | **GEN**, virtual | UQ | `business:clase:branch` solo si `is_active`; NULL si inactiva. **Máx. una regla activa por (clase, ámbito).** |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_tax_rule_rate` ([0,1]). **UNIQUE:** `uniq_active_tax_rule_scope` (active_scope_lock).
**Nota (MOD-07):** resuelve la tasa por clase fiscal con ámbito general o por sucursal; es la fuente de tasa que reemplazó al `businesses.tax_rate` fijo. `sale_items.tax_rule_id` referencia la regla usada (auditoría).

---

## MOD-08 — Ventas al Crédito y Cuentas por Cobrar

### `accounts_receivables`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| customer_id | bigint | NN, FK RSTR | — | Cliente deudor. |
| invoice_id | bigint | NN, FK RSTR | **UQ** | Factura origen. **Una factura ⇒ una sola CxC.** |
| total_amount | decimal(14,2) | NN, CHECK > 0 | — | Monto total adeudado. **bcmath.** |
| paid_amount | decimal(14,2) | NN, def. 0 | — | Abonado acumulado. **bcmath.** |
| balance | decimal(14,2) | **GEN** stored, CHECK ≥ 0 | — | `total − paid`. No editable. **Anti-sobre-abono de motor.** **bcmath.** |
| status | enum | NN, def. 'pendiente' | IDX | `pendiente` / `parcial` / `pagada` / `vencida`. `vencida` solo la asigna el cron. |
| due_date | date | NULL | IDX | Vencimiento (por defecto emisión + 30 días). |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_ar_total_positive` (> 0) · `chk_ar_balance_non_negative` (≥ 0, sobre la columna generada).

### `receivable_payments` · INS · **inmutable**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| accounts_receivable_id | bigint | NN, FK RSTR | — | Cuenta abonada. |
| invoice_payment_id | bigint | NN, FK RSTR | UQ | **Asiento fiscal 1:1 (MOD-08).** Todo abono (inicial o posterior) respalda su `invoice_payment`. `uniq_rp_invoice_payment` impide dos abonos sobre el mismo asiento. |
| cash_session_id | bigint | NULL, FK RSTR | — | Sesión de caja (obligatoria si efectivo). |
| user_id | bigint | NN, FK RSTR | — | Responsable del cobro. |
| amount | decimal(14,2) | NN, CHECK > 0 | — | Monto del abono. **bcmath.** |
| payment_method | enum | NN | IDX | `efectivo` / `transferencia` / `tarjeta`. |
| reference | string(100) | NULL | — | Referencia del pago. |
| paid_at | timestamp | NN | — | Momento del abono. |
| created_at | timestamp | NULL | — | Registro del hecho. |

**CHECK:** `chk_rp_amount_positive` (> 0). **UNIQUE:** `uniq_rp_invoice_payment` (invoice_payment_id).
**Invariante fiscal (MOD-08):** el pago inicial de una factura a crédito se materializa 1:1 desde su `invoice_payment` (ReceivableService::generarDesdeFactura), de modo que `Σ receivable_payments == accounts_receivable.paid_amount == Σ invoice_payments`.

---

## MOD-09 — Entregas y Retiros

### `dispatches`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,code) | Negocio propietario. |
| branch_id | bigint | NN, FK RSTR | — | Sucursal que entrega. |
| invoice_id | bigint | NN, FK RSTR | — | Factura que respalda el retiro. |
| warehouse_id | bigint | NN, FK RSTR | — | Bodega de origen. |
| user_id | bigint | NN, FK RSTR | — | Responsable de la entrega. |
| code | string(30) | NN | UQ(business_id,code) | Folio del retiro. |
| status | enum | NN, def. 'registrado' | IDX | `registrado` / `revertido`. |
| received_by | string(160) | NULL | — | Persona que recibió la mercancía. |
| dispatched_at | timestamp | NN | — | Momento de la entrega. |
| reverted_by | bigint | NULL, FK RSTR | — | ROL-02 que revirtió. |
| reverted_at | timestamp | NULL | — | Momento de la reversión. |
| revert_reason | string(255) | NULL | — | Motivo obligatorio de reversión. |
| notes | string(500) | NULL | — | Observación. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_dispatch_revert_coherence` (`status <> 'revertido' OR (reverted_by IS NOT NULL AND reverted_at IS NOT NULL)`).

### `dispatch_items` · sin timestamps
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| dispatch_id | bigint | NN, FK CASC | — | Retiro al que pertenece. |
| sale_item_id | bigint | NN, FK RSTR | — | Línea de venta que se entrega. |
| product_id | bigint | NN, FK RSTR | — | Producto entregado. |
| quantity | decimal(14,3) | NN, CHECK > 0 | — | Cantidad entregada. **bcmath.** |

**CHECK:** `chk_dispatch_item_quantity` (> 0).

---

## MOD-10 — Devoluciones, Reingreso y Mermas

### `sales_returns`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,code) | Negocio propietario. |
| branch_id | bigint | NN, FK RSTR | — | Sucursal que recibe la devolución. |
| invoice_id | bigint | NN, FK RSTR | — | Factura original. |
| customer_id | bigint | NN, FK RSTR | — | Cliente que devuelve. |
| user_id | bigint | NN, FK RSTR | — | Responsable de la recepción. |
| code | string(30) | NN | UQ(business_id,code) | Folio de la devolución. |
| status | enum | NN, def. 'registrada' | IDX | `registrada` / `procesada` / `anulada`. |
| total_returned | decimal(14,2) | NN, CHECK ≥ 0 | — | Monto total devuelto. **bcmath.** |
| returned_at | timestamp | NN | — | Momento de la devolución. |
| notes | string(500) | NULL | — | Observación. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_return_total` (≥ 0).

### `sales_return_items` · sin timestamps
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| sales_return_id | bigint | NN, FK CASC | — | Devolución a la que pertenece. |
| product_id | bigint | NN, FK RSTR | — | Producto devuelto. |
| sale_item_id | bigint | NULL, FK RSTR | — | Línea de venta origen (base del devolvible). |
| warehouse_id | bigint | NN, FK RSTR | — | Bodega de reingreso o de registro de merma. |
| quantity | decimal(14,3) | NN, CHECK > 0 | — | Cantidad devuelta. **bcmath.** |
| unit_price | decimal(14,2) | NN, CHECK ≥ 0 | — | Precio **congelado** de la factura. **bcmath.** |
| destination | enum | NN | IDX | `reingreso` (vuelve al stock, a costo) / `merma` (pérdida). |
| reason_code | enum | NN | — | `vencido` / `defecto_fabrica` / `error_despacho` / `insatisfaccion` / `otro`. |
| line_total | decimal(14,2) | NN, CHECK ≥ 0 | — | `cantidad × precio`. **bcmath.** |

**CHECK:** `chk_return_item_quantity` (> 0) · `chk_return_item_price_non_negative` · `chk_return_item_line_total_non_negative`.
**Regla de dominio:** `vencido` y `defecto_fabrica` no admiten `reingreso`.

### `credit_notes`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,folio) | Negocio propietario. |
| invoice_id | bigint | NN, FK RSTR | — | Factura afectada. |
| sales_return_id | bigint | NN, FK RSTR | **UQ** | Devolución origen. **Una devolución ⇒ una sola NC.** |
| customer_id | bigint | NN, FK RSTR | — | Cliente resarcido. |
| cash_session_id | bigint | NULL, FK RSTR | — | Sesión de caja del reembolso. |
| issued_by | bigint | NN, FK RSTR | — | Usuario emisor. |
| folio | string(30) | NN | UQ(business_id,folio) | Folio secuencial de la NC. |
| resolution_type | enum | NN | IDX | `reembolso_efectivo` / `nota_credito_saldo` / `reduccion_cxc` / `mixto`. **Cabecera:** `mixto` cuando el resarcimiento se aplicó por más de una vía (el desglose vive en `credit_note_resolutions`). |
| total_amount | decimal(14,2) | NN, CHECK > 0 | — | Monto resarcido. **bcmath.** |
| tax_amount | decimal(14,2) | NN, def. 0 | — | IVA proporcional revertido. **bcmath.** |
| status | enum | NN, def. 'emitida' | IDX | `emitida` / `anulada`. |
| issued_at | timestamp | NN | — | Fecha de emisión. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_credit_note_total` (> 0).

### `credit_note_resolutions` · INS (solo `created_at`) *(añadida en MOD-10)*
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| credit_note_id | bigint | NN, FK CASC | IDX | Nota de crédito resarcida. |
| cash_session_id | bigint | NULL, FK RSTR | — | Sesión de caja (solo si la vía es reembolso en efectivo). |
| resolution_type | enum | NN | IDX | Vía **concreta** aplicada: `reembolso_efectivo` / `nota_credito_saldo` / `reduccion_cxc` (nunca `mixto`). |
| amount | decimal(14,2) | NN, CHECK > 0 | — | Monto resarcido por esta vía. **bcmath.** |
| created_at | timestamp | NULL | — | Registro del hecho (append-only). |

**CHECK:** `chk_cnr_amount_positive` (> 0). **Invariante:** `Σ(amount) == credit_notes.total_amount`; una NC puede resarcirse por más de una vía (p. ej. reducir la CxC por el saldo pendiente y reembolsar el excedente ya pagado), y la cabecera queda como `mixto`.

---

## MOD-11 — Conciliación, Alertas y Anomalías

### `anomaly_rules` · catálogo parametrizable (6 reglas sembradas por negocio)
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ(business_id,code) | Negocio propietario. |
| code | enum | NN | UQ(business_id,code), IDX | `descuadre_caja` / `faltante_inventario` / `discrepancia_3way` / `cuenta_vencida` / `omision_registro` / `venta_sin_sesion`. |
| name | string(120) | NN | — | Nombre legible de la regla. |
| threshold_value | decimal(14,2) | NULL | — | Umbral bajo el cual no se genera anomalía. **bcmath.** |
| threshold_type | enum | NN | — | `monto` / `porcentaje` / `cantidad` / `tiempo`. |
| default_severity | enum | NN | — | `informativa` / `advertencia` / `critica`. |
| is_active | boolean | NN, def. true | — | Regla habilitada; si es false no se detecta. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

### `reconciliation_runs` · solo `created_at`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| branch_id | bigint | NULL, FK RSTR | — | Sucursal auditada (NULL = todas). |
| triggered_by | bigint | NULL, FK RSTR | — | Solicitante (NULL = proceso programado). |
| run_type | enum | NN | IDX | `programada` / `manual`. |
| scope | enum | NN | IDX | `caja` / `inventario_bodega` / `compras_3way` / `integral`. |
| status | enum | NN | IDX | `en_proceso` / `completada` / `fallida`. |
| anomalies_found | smallint | NN, unsigned, def. 0 | — | Hallazgos generados en la corrida. |
| started_at | timestamp | NN | — | Inicio de la corrida. |
| finished_at | timestamp | NULL | — | Fin de la corrida. |
| created_at | timestamp | NULL | — | Registro del hecho. |

### `anomalies` · máquina de estados
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| anomaly_rule_id | bigint | NN, FK RSTR | — | Regla que la originó. |
| reconciliation_run_id | bigint | NULL, FK RSTR | — | Corrida que la detectó. |
| branch_id | bigint | NULL, FK RSTR | — | Sucursal afectada. |
| severity | enum | NN | IDX | `informativa` / `advertencia` / `critica`. |
| status | enum | NN | IDX | `detectada` / `notificada` / `en_revision` / `justificada` / `resuelta`. |
| expected_value | decimal(14,2) | NULL | — | Valor esperado. **bcmath.** |
| actual_value | decimal(14,2) | NULL | — | Valor real hallado. **bcmath.** |
| difference | decimal(14,2) | NULL | — | Desviación cuantificada. **bcmath.** |
| source_type | string(60) | NULL | IDX(source_type,source_id) | Tipo de origen (puntero débil, sin FK). |
| source_id | bigint | NULL, unsigned | IDX(source_type,source_id) | Id del registro origen. |
| resolved_by | bigint | NULL, FK RSTR | — | Validador. **Nunca el causante (BR-01).** |
| resolved_at | timestamp | NULL | — | Momento de la resolución. |
| detected_at | timestamp | NN | — | Momento de la detección. |
| active_dedupe_key | varchar(160) | **GEN**, virtual | **UQ** `uniq_active_anomaly` | `regla:origen` si el estado es activo. **Idempotencia estructural.** *(añadido en v1.1)* |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_anomaly_resolution_coherence` (`status NOT IN ('justificada','resuelta') OR (resolved_by IS NOT NULL AND resolved_at IS NOT NULL)`).

### `anomaly_events` · INS · **bitácora de transiciones**
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| anomaly_id | bigint | NN, FK CASC | — | Anomalía a la que pertenece. |
| user_id | bigint | NULL, FK RSTR | — | Autor de la transición. |
| from_status | string(20) | NULL | — | Estado anterior. |
| to_status | string(20) | NN | — | Estado nuevo. |
| comment | string(500) | NULL | — | Motivo o justificación. |
| changed_at | timestamp | NN | IDX | Momento de la transición. |
| created_at | timestamp | NULL | — | Registro del hecho. Se genera automáticamente. |

---

## MOD-12 — Reportería, KPIs e Inteligencia de Negocios

### `business_goals`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ `uniq_business_goal` | Negocio propietario. |
| branch_id | bigint | NULL, FK RSTR | — | Sucursal (NULL = meta global). |
| kpi_code | enum | NN | UQ, IDX | `kpi_03` / `kpi_04` / `kpi_05` / `kpi_08` / `margen` / `ticket_promedio` / `rotacion_inventario`. |
| period_type | enum | NN | UQ, IDX | `diario` / `semanal` / `mensual` / `anual`. |
| period_start | date | NN | UQ | Inicio del periodo de la meta. |
| period_end | date | NN | — | Fin del periodo. |
| target_value | decimal(16,2) | NN, CHECK > 0 | — | Meta a alcanzar. **bcmath.** |
| created_by | bigint | NN, FK RSTR | — | ROL-01 que fijó la meta. |
| branch_key | bigint | **GEN**, unsigned, virtual | UQ `uniq_business_goal` | `COALESCE(branch_id, 0)`. Colapsa el NULL para el índice único. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

**CHECK:** `chk_goal_target_positive` (> 0) · `chk_goal_period` (`period_end >= period_start`).

### `kpi_snapshots` · caché recalculable · solo `created_at`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | UQ `uniq_kpi_snapshot` | Negocio propietario. |
| branch_id | bigint | NULL, FK RSTR | — | Sucursal (NULL = consolidado). |
| kpi_code | string(50) | NN | UQ, IDX | Código del indicador; registro canónico en `config/kpis.php`. |
| period_type | enum | NN | UQ, IDX | `diario` / `semanal` / `mensual` / `anual`. |
| period_start | date | NN | UQ, IDX | Inicio del periodo calculado. |
| period_end | date | NN | — | Fin del periodo. |
| value | decimal(16,4) | NN | — | Valor del indicador. **bcmath.** |
| target_value | decimal(16,2) | NULL | — | Meta congelada al calcular. **bcmath.** |
| achievement_pct | decimal(7,2) | NULL | — | `value / target × 100`. **bcmath.** |
| metadata | json | NULL | — | Cifras de apoyo (ticket promedio, conteos, brechas). |
| calculated_at | timestamp | NN | — | Momento del cálculo. |
| branch_key | bigint | **GEN**, unsigned, virtual | UQ `uniq_kpi_snapshot` | `COALESCE(branch_id, 0)`. Colapsa el NULL. |
| created_at | timestamp | NULL | — | Registro del hecho. |

**CHECK:** `chk_snapshot_period` (`period_end >= period_start`).
**Nota:** es **caché**, nunca fuente de verdad. Se recalcula idempotentemente desde las transacciones.

### `report_definitions`
| Campo | Tipo | Atributos | Llave/Índice | Propósito |
| --- | --- | --- | --- | --- |
| business_id | bigint | NN, FK CASC | — | Negocio propietario. |
| user_id | bigint | NN, FK RSTR | — | Autor de la definición. |
| name | string(120) | NN | — | Nombre del reporte guardado. |
| report_type | string(50) | NN | IDX | Tipo (ventas, cartera, inventario, caja…). |
| filters | json | NULL | — | Filtros persistidos del reporte. |
| is_scheduled | boolean | NN, def. false | — | Envío programado. **Motor diferido a Fase 2.** |
| schedule_cron | string(50) | NULL | — | Expresión de calendarización. Diferido a Fase 2. |
| created_at / updated_at | timestamp | NULL | — | Auditoría. |

### Vistas de agregación (solo lectura)

| Vista | Alimenta | Agrupa por | Nota |
| --- | --- | --- | --- |
| `vw_kpi_ventas` | KPI-05 | negocio, sucursal, día | Total vendido, número de facturas, ticket promedio. |
| `vw_kpi_cartera` | KPI-08 | negocio | Cartera emitida, recuperada, pendiente y vencida. |
| `vw_kpi_exactitud_stock` | KPI-02 | negocio, día | Desviación absoluta y % de exactitud de conteos. |
| `vw_kpi_faltantes` | KPI-03 | negocio, día | Faltante no justificado desde anomalías activas. |
| `vw_kpi_uso_sistema` | KPI-04 | negocio, usuario, día | Acciones registradas por usuario. |
| `vw_kpi_disponibilidad` | KPI-07 | negocio, día | Corridas programadas completadas sobre el total. |

> Las vistas son objetos **globales**: no heredan el aislamiento automático del modelo. Toda consulta debe filtrar `business_id` de forma explícita.

### Registro canónico de indicadores — `config/kpis.php`

| Código | Etiqueta | Unidad | Meta | Familia | Fuente |
| --- | --- | --- | --- | --- | --- |
| kpi_01 | Correspondencia ventas-caja-inventario | porcentaje | No | integridad | cálculo directo |
| kpi_02 | Correspondencia bodega-inventario | porcentaje | No | integridad | `vw_kpi_exactitud_stock` |
| kpi_03 | Reducción de faltantes no justificados | monto | Sí | control | `vw_kpi_faltantes` |
| kpi_04 | Uso consistente del sistema | porcentaje | Sí | cumplimiento | `vw_kpi_uso_sistema` |
| kpi_05 | Evolución de ventas | monto | Sí | comercial | `vw_kpi_ventas` |
| kpi_06 | Cumplimiento de metas | porcentaje | No | agregador | snapshots (se calcula al final) |
| kpi_07 | Disponibilidad de reportes confiables | porcentaje | No | sla | `vw_kpi_disponibilidad` |
| kpi_08 | Recuperación de cartera | porcentaje | Sí | financiero | `vw_kpi_cartera` |
| margen | Margen bruto | porcentaje | Sí | comercial | Fase 2 |
| ticket_promedio | Ticket promedio | monto | Sí | comercial | `vw_kpi_ventas` |
| rotacion_inventario | Rotación de inventario | ratio | Sí | operativo | Fase 2 |

---

## Anexo A — Tablas de solo inserción (append-only)

`audit_logs` · `inventory_movements` · `cash_movements` · `invoice_payments` · `receivable_payments` · `goods_receipt_items` · `reconciliation_runs` · `anomaly_events`

Ninguna admite UPDATE ni DELETE por vía alguna: el intento lanza `ImmutableRecordException` (HTTP 403).

## Anexo B — Columnas generadas por el motor

| Tabla | Columna | Expresión | Efecto |
| --- | --- | --- | --- |
| `warehouses` | default_lock | `CASE WHEN is_default THEN branch_id END` | Una bodega predeterminada por sucursal. |
| `physical_counts` | difference | `counted_quantity − system_quantity` | Diferencia no manipulable. |
| `customers` | generic_lock | `CASE WHEN is_generic THEN business_id END` | Un solo "Consumidor Final" por negocio. |
| `cash_sessions` | difference | `counted_amount − expected_amount` | Descuadre no editable por el cajero. |
| `cash_sessions` | open_register_lock | `CASE WHEN status='abierta' THEN cash_register_id END` | Una sesión abierta por caja. |
| `cash_sessions` | open_user_lock | `CASE WHEN status='abierta' THEN opened_by END` | Una sesión abierta por usuario. |
| `accounts_receivables` | balance | `total_amount − paid_amount` | Saldo real; con CHECK ≥ 0 impide el sobre-abono. |
| `anomalies` | active_dedupe_key | `regla:origen` si el estado es activo | Máximo una anomalía activa por origen. |
| `business_goals` | branch_key | `COALESCE(branch_id, 0)` | Permite unicidad con sucursal nula. |
| `kpi_snapshots` | branch_key | `COALESCE(branch_id, 0)` | Permite unicidad con sucursal nula. |

## Anexo C — Comandos programados

| Comando | Frecuencia | Efecto sobre los datos |
| --- | --- | --- |
| `receivables:mark-overdue` | diario 00:30 | Marca `accounts_receivables.status = 'vencida'` y genera anomalías. |
| `reconciliation:run --scope=integral` | diario 01:00 | Inserta `reconciliation_runs` y `anomalies` (idempotente). |
| `kpi:snapshot --period=diario` | diario 02:00 | Recalcula `kpi_snapshots` del día. |
| `kpi:snapshot --period=mensual` | mensual día 1, 02:30 | Recalcula `kpi_snapshots` del mes. |

---

*Fin del documento. GINTLY · Diccionario de Datos v1.1 · 21 de julio de 2026.*
