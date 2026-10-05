# **Contratos JSON** 

### **MOD-01 — Seguridad, Identidad y Auditoría** 

{ 

"module": "MOD-01", 

"version": "2.1", "base_url": "/api/v1", "auth": { "scheme": "Sanctum SPA (cookie de sesión de primera parte; SIN token Bearer)", "transport": "statefulApi() habilita cookies/sesión; el usuario vive en el guard 'web' y Sanctum actúa solo como transporte. No se emiten personal access tokens en Fase 1.", "csrf": "Las peticiones mutadoras requieren el flujo XSRF de Sanctum (GET /sanctum/csrf-cookie antes de POST/PUT/DELETE).", "session_expiry": "La sesión expira por inactividad (SESSION_LIFETIME), conforme a RF-01-02.", "tenant_source": "session.user.business_id (jamás por entrada)" }, "reconciliation_2_1": "Alineado con la implementación: el esquema es cookie/sesión (Sanctum SPA), no Bearer. Corrige el contrato v2.0, que describía tokens; ningún endpoint emite ni consume 'token'.", 

"conventions": { 

"tenant_isolation": "business_id JAMÁS se acepta como entrada; se deriva de la sesión autenticada (RF-01-05).", "pagination": { 

"query": { 

"page": "int|opt|min:1", 

"per_page": "int|opt|min:1|max:100|default:25", 

"sort": "string|opt|allowlist por recurso", 

"direction": "enum[asc,desc]|opt|default:desc" 

"response_envelope": { 

"data": "Resource[]", 

"links": { "first": "url", "last": "url", "prev": "url|null", "next": "url|null" }, 

"meta": { "current_page": "int", "from": "int|null", "last_page": "int", 

"per_page": "int", "to": "int|null", "total": "int" "date_filters": { 

"format": "Y-m-d", 

"semantics": "Interpretados en businesses.timezone. `from` = inicio del día, `to` = fin del día (inclusivo). Convertidos a UTC antes de consultar.", 

"constraint": "to >= from" }, "error_envelope_422": { "message": "string", "errors": { "campo": ["string"] } } }, 

"resources": { "auth": [ { "method": "POST", "path": "/auth/login", "roles": ["public"], "request_class": "LoginRequest", "middleware": ["throttle:login"], "request": { "business_slug": "string(160)|required", "email": "string(180)|required|email", "password": "string|required", "remember": "bool|opt" }, 

"note": "H-05. El correo es único POR NEGOCIO (UQ business_id,email); el negocio se resuelve primero (RF-01-02). Si más adelante se resuelve por subdominio, business_slug se retira sin otro cambio. v2.1: autenticación por cookie de sesión (Sanctum SPA); el login establece la sesión y regenera el id (anti-fijación). NO devuelve token.", 

"response_200": "UserResource (incluye rol activo). La sesión se transporta por cookie; no hay campo 'token'.", 

"errors": [ 

{ "code": "ERR-01", "http": 401, "when": "Credenciales inválidas, cuenta inactiva o sin rol. No revela cuál dato falló." }, 

{ "code": "THROTTLE", "http": 429, "when": "Límite de intentos (RateLimiter, sin contadores en BD)." } 

] 

}, { "method": "POST", "path": "/auth/logout", "roles": ["auth"], "response_204": null }, 

{ "method": "GET", "path": "/me", "roles": ["auth"], "response_200": "MeResource { id, name, email, is_active, role (rol humano garantizado), branch_id, profiles[] (solo ROL-03), capabilities[] (capacidades EFECTIVAS de interfaz: ROL-01/02 = permisos del rol, ROL-03 = unión de capacidades de sus perfiles; NO son autorización por recurso), business{id,name,timezone,status} }. business_id SIEMPRE de la sesión, nunca del frontend. ROL-SYS/inactivos no acceden (EnsureOperableUser → 403 e invalida sesión)." } 

], 

"users": [ { "method": "GET", "path": "/users", "roles": ["ROL-02", "ROL-01"], "request_class": "IndexUserRequest", "query": { "is_active": "bool|opt", "branch_id": "int|opt|same_tenant", "search": "string|opt|min:2|max:150", "from": "date(Y-m-d)|opt", "to": "date(Y-m-d)|opt", "trashed": "enum[with,only]|opt", "page": "int|opt", "per_page": "int|opt", "sort": "enum[name,email,is_active,last_login_at,created_at]|opt", "direction": "enum[asc,desc]|opt" }, "response_200": "Paginated<UserResource>" 

}, { 

"method": "POST", "path": "/users", "roles": ["ROL-02"], 

"request_class": "StoreUserRequest", 

"request": { "name": "string(150)|required", "email": "string(180)|required|unique_per_business", "password": "string|required|confirmed|Password::defaults", "password_confirmation": "string|required", 

"branch_id": "int|nullable|same_tenant", 

"role": "string|required|exists_business_or_global" }, "response_201": "UserResource", "errors": [{ "http": 422, "when": "Email duplicado en el negocio o rol inexistente." }] }, { "method": "GET", "path": "/users/{user}", "roles": ["ROL-02"], "response_200": "UserResource" }, { "method": "PUT", "path": "/users/{user}", "roles": ["ROL-02"], "request_class": "UpdateUserRequest", "request": { "name": "string(150)|opt", "branch_id": "int|nullable|same_tenant", "is_active": "bool|opt" }, "guards": ["No permite autodesactivación."], "response_200": "UserResource" }, { 

"method": "PUT", "path": "/users/{user}/role", "roles": ["ROL-02"], 

"request_class": "UpdateUserRoleRequest", 

"request": { "role": "string|required" }, 

"guards": ["Antiescalada: no permite modificar el rol propio (BR-06)."], 

"note": "syncRoles([role]) — exactamente un rol activo (RF-01-01). Requiere setPermissionsTeamId por middleware.", 

"response_200": "UserResource" 

}, { 

"method": "PUT", "path": "/me/password", "roles": ["auth"], 

"request_class": "UpdateOwnPasswordRequest", 

"request": { 

"current_password": "string|required|current_password", 

"password": "string|required|confirmed|different:current_password|Password::defaults", 

"password_confirmation": "string|required" 

}, 

"note": "H-10 (nuevo). Autoservicio. current_password sustituye la verificación por canal externo, no disponible en Fase 1.", 

"side_effects": ["Registra 'password_reset' en la bitácora inmutable (IMPLEMENTADO v2.1).", "Cierra las DEMÁS sesiones del titular y conserva la actual (IMPLEMENTADO v2.1). Driver de sesión configurado = database: se eliminan las filas de `sessions` del user_id salvo la sesión vigente. No hay tokens Bearer que revocar."], 

"response_204": null 

}, 

{ 

"method": "PUT", "path": "/users/{user}/password", "roles": ["ROL-02"], 

"request_class": "ResetUserPasswordRequest", 

"request": { 

"current_password": "string|required|current_password (del ACTOR)", 

"password": "string|required|confirmed|Password::defaults", 

"password_confirmation": "string|required" 

}, 

"guards": ["No aplica sobre uno mismo; usar /me/password."], 

"note": "H-10 (nuevo). Restablecimiento administrativo con reautenticación del administrador.", 

"side_effects": ["Registra 'password_reset' en la bitácora inmutable (IMPLEMENTADO v2.1).", "Cierra TODAS las sesiones del usuario destino (IMPLEMENTADO v2.1). Driver = database: se eliminan sus filas en `sessions`. No hay tokens Bearer que revocar."], 

"response_204": null 

}, 

{ 

"method": "PUT", "path": "/users/{user}/email", "roles": ["ROL-02"], 

"request_class": "UpdateUserEmailRequest", 

"request": { 

"current_password": "string|required|current_password (del ACTOR)", 

"email": "string(180)|required|unique_per_business|ignore:{user}" 

}, 

"note": "H-10 (nuevo). Operación administrativa, NO autoservicio: el BRD excluye canales externos en Fase 1, luego no hay verificación por enlace y un cambio autoservicio sin verificar es vía de apropiación de cuenta.", 

"side_effects": ["Registra 'update' en la bitácora inmutable con valor anterior/nuevo (IMPLEMENTADO v2.1).", "Cierra TODAS las sesiones del usuario destino (IMPLEMENTADO v2.1): cambió su identificador de acceso. Driver = database: se eliminan sus filas en `sessions`. No hay tokens Bearer que revocar."], 

"response_200": "UserResource" 

}, 

{ 

"method": "DELETE", "path": "/users/{user}", "roles": ["ROL-02"], 

"note": "Desactivación lógica (softDeletes). Conserva historial. No borrado físico (BR-04).", 

"response_204": null 

} 

], 

"branches": [ 

{ 

"method": "GET", "path": "/branches", "roles": ["ROL-02", "ROL-01"], 

"request_class": "IndexBranchRequest", 

"query": { 

"is_active": "bool|opt", "manager_user_id": "int|opt|same_tenant", 

"search": "string|opt|min:2|max:150", 

"from": "date(Y-m-d)|opt", "to": "date(Y-m-d)|opt", 

"trashed": "enum[with,only]|opt", 

"page": "int|opt", "per_page": "int|opt", "sort": "enum[name,opened_at,is_active,created_at]| opt", "direction": "enum[asc,desc]|opt" 

}, 

"response_200": "Paginated<BranchResource>" 

}, 

{ 

"method": "POST", "path": "/branches", "roles": ["ROL-02"], 

"request_class": "StoreBranchRequest", 

"request": { 

"name": "string(150)|required|unique_per_business_active", 

"address": "string(255)|required", 

"manager_user_id": "int|required|same_tenant|is_active", 

"opened_at": "date(Y-m-d)|required|before_or_equal:today", 

"is_active": "bool|opt" 

}, 

"note": "H-06. manager_user_id es REQUIRED en entrada aunque la columna admita NULL (su FK es SETNULL). Acreditación obligatoria por BRD §4.", 

"response_201": "BranchResource" 

}, 

{ "method": "GET", "path": "/branches/{branch}", "roles": ["ROL-02"], "response_200": "BranchResource" }, 

{ 

"method": "PUT", "path": "/branches/{branch}", "roles": ["ROL-02"], 

"request_class": "UpdateBranchRequest", 

"request": { 

"name": "string(150)|opt|unique_per_business_active|ignore:{branch}", 

"address": "string(255)|opt", 

"manager_user_id": "int|opt|same_tenant|is_active", 

"opened_at": "date(Y-m-d)|opt|before_or_equal:today", 

"is_active": "bool|opt" 

}, 

"note": "El contrato v1 no declaraba cuerpo; se deriva de POST con semántica parcial (sometimes| required).", 

"response_200": "BranchResource" 

}, 

{ 

"method": "DELETE", "path": "/branches/{branch}", "roles": ["ROL-02"], 

"response_204": "Soft-delete", 

"errors": [{ "code": "ERR-02B", "http": 409, "when": "Sucursal con bodegas, cajas o usuarios dependientes vigentes. La baja es lógica (soft-delete), por lo que la guarda es de dominio (Branch::hasOperationalDependents), no la FK RESTRICT del motor: excluye dependientes ya dados de baja. IMPLEMENTADO v2.1." }] 

} ], "audit_logs": [ { "method": "GET", "path": "/audit-logs", "roles": ["ROL-01", "ROL-02"], "request_class": "IndexAuditLogRequest", "query": { "user_id": "int|opt|same_tenant", "action": "string|opt|allowlist(config gintly.audit.actions)", "auditable_type": "string|opt|allowlist(config gintly.audit.auditable_types)", "auditable_id": "int|opt|required_with:auditable_type", "ip_address": "string|opt|ip", "from": "datetime(Y-m-d)|opt", "to": "datetime(Y-m-d)|opt", "page": "int|opt", "per_page": "int|opt", "sort": "enum[created_at]|opt", "direction": "enum[asc,desc]|opt" 

}, "response_200": "Paginated<AuditLogResource>", "note": "Recurso de SOLO lectura. Sin POST/PUT/DELETE por diseño (RF-01-03).", "errors": [{ "code": "ERR-01B", "http": 403, "when": "Cualquier UPDATE/DELETE → InmutableAuditException." }] 

} ], "business": [ 

{ "method": "GET", "path": "/business", "roles": ["ROL-01"], "response_200": "BusinessResource" }, { 

"method": "PUT", "path": "/business", "roles": ["ROL-01"], 

"request_class": "UpdateBusinessRequest", 

"request": { "name": "string(150)|opt", "tax_rate": "decimal(5,4)|opt|min:0|max:0.9999|decimal:0,4", "timezone": "string(64)|opt|IANA" }, "removed_from_v1": { "status": "H-07. Atributo del contrato SaaS, no de la operación. Permitir que el cliente se asigne 'active' anularía toda suspensión por impago. Reservado a ROL-SYS.", 

"plan": "Ídem. Nunca fue editable ni figuraba en el contrato v1." }, "response_200": "BusinessResource" } ] }, 

"documentation_gaps": { 

"H-11": "branches y la configuración fiscal/horaria carecen de RF propio. Se propone para el addendum del FRD: RF-01-07 «Gestión de sucursales y acreditación operativa» (ROL-02, Must) y RF-0108 «Configuración fiscal y horaria del negocio» (ROL-01, Must)." 

}, 

"authorization_reconciliation": { "note": "Reconciliación de autorización (Fases 2/3/5/6/7) fusionada en el bloque canónico MOD-01.", 

"enforcement_fase5": "Autorización ADITIVA de ROL-03 por FLUJO (además del rol humano): perfil requerido (operativeCan, config/profiles.php) + sucursal (user.branch_id) + negocio. Las Policies autorizan por nivel de rol; NINGÚN ROL-03 obtiene una operación solo porque su rol Spatie contiene la unión de permisos: debe superar el perfil y el alcance. Gates de perfil: caja abrir/cerrar/movimiento (cajero); ventas crear/confirmar y facturas crear (facturador); CxC abonar (cajero); conteo/traspaso/recepción/devolución (bodeguero); entregas (despachador). Efectivo: perfil cajero + sesión propia abierta + caja de su sucursal (CashService::assertCanOperateSession). Un facturador que cobre efectivo necesita también cajero. Sucursal en la entrada: venta (branch_id), conteo/recepción (bodega), traspaso (origen propio; finalización por bodeguero de la sucursal RECEPTORA), devolución (factura), retiro (MOD-09) → 422/403 si no coincide con user.branch_id. Productos/categorías/clientes/proveedores siguen siendo datos maestros del negocio (no acotados por sucursal; la compuerta es la capacidad). ROL-01/ROL-02 no usan perfiles ni se acotan a sucursal.", 

"branch_isolation_indices": "Microcierre ROL-03 (aislamiento de SUCURSAL en LISTADOS). El detalle/mutación ya los protege la Policy (operatorInBranch); la otra mitad es el scope del índice: cada índice llama `->forOperator($user)` (trait ScopesToOperatorBranch, punto único y comprobable). Para ROL-03 devuelve SOLO filas de su sucursal; para ROL-01/ROL-02, alcance de negocio; para un ROL-03 sin sucursal, NINGUNA fila (cierre en falso). Un branch_id enviado por query NO amplía el alcance. Resolución de sucursal: DIRECTA (ventas, facturas, órdenes de compra, devoluciones, retiros) o INDIRECTA (conteos/recepciones→bodega; traspasos→bodega origen O destino; CxP→orden; notas de crédito→factura). Índices acotados: /sales, /invoices, /physical-counts, /stock-transfers, /goods-receipts, /purchase-orders, /accounts-payable, /sales-returns, /credit-notes, /dispatches; ya acotados previamente: /warehouses, /stock, /cash-registers (activa), /cash-sessions (opened_by=self), /accounts-receivable/collectible. Detalle cross-branch (mismo negocio) → 403; recurso de otro negocio → 404 (BusinessScope). Cross-tenant nunca aparece en índices.", 

"bodeguero_capacidades_usables": "Reconciliación de capacidades del BODEGUERO que estaban declaradas en config/profiles.php pero eran INUTILIZABLES (Policy exigía Admin). Ahora funcionan, acotadas a su sucursal y SIN conceder potestades de ROL-01/ROL-02: proveedores.ver → GET /suppliers y /suppliers/{id} (dato maestro del negocio, sin aislar por sucursal); compras.ver → GET /purchase-orders (índice y detalle de su sucursal); compras.crear → POST /purchase-orders (BORRADOR; branch_id debe ser su sucursal, 422 si no); cuentas_por_pagar.ver → GET /accounts-payable (de su sucursal, por la orden); devoluciones.ver → GET /sales-returns (+/items) de su sucursal; notas_credito.ver → GET /credit-notes de su sucursal. EXCLUSIVO e inalterado: aprobar/suspender proveedor (ROL-01), emitir/cancelar/editar orden (ROL-02), pagar CxP (ROL-02), descongelar CxP (ROL-01), resolver discrepancia 3-Way (ROL-01), reembolso en efectivo de devolución (ROL-01), reversión de retiro (ROL-02).", 

"read_capabilities_rol03": "Microcierre de CAPACIDAD DE LECTURA (viewAny/view). Invariante ROL-03: la lectura exige DOS controles independientes y ADITIVOS — (1) capacidad efectiva del perfil (operativeCan, fuente config/profiles.php) y (2) alcance de sucursal (en el índice vía Model::forOperator; en el detalle vía operatorInBranch). El scope NO reemplaza la capacidad: un ROL-03 de la sucursal correcta pero SIN la capacidad del perfil recibe 403. Ningún permiso Spatie agregado del rol ROL-03 (unión de perfiles) permite saltarse la compuerta fina (operativeCan). ROL-01/ROL-02 conservan su acceso por nivel (operatorGrants devuelve true para no-operativos). Mapa capacidad↔recurso (GET índice y detalle): ventas.ver → /sales; facturas.ver → /invoices; entregas.ver → /dispatches; inventario.traspaso → /stock-transfers; compras.ver → /goods-receipts; bodegas.ver → /warehouses; catalogo.ver → /products y /categories; inventario.ver → /stock; inventario.conteo → /physical-counts; clientes.ver → /customers; cuentas_por_cobrar.ver → /accounts-receivable/collectible (cajero); proveedores.ver → /suppliers; compras.ver → /purchase-orders; cuentas_por_pagar.ver → /accounts-payable; devoluciones.ver → /sales-returns; notas_credito.ver → /credit-notes. Caja (/cash-registers, /cash-sessions, /cash-movements): NO existe una capacidad de LECTURA en config/profiles.php (las capacidades de caja — caja.abrir/cerrar/movimiento.crear — son de MUTACIÓN); la lectura se gobierna por PROPIEDAD (opened_by=self) y sucursal (branch_id + is_active), más /cash-movements restringido a ROL-01/ROL-02; no se inventa una capacidad de lectura. Crear cliente en mostrador sigue abierto a cualquier ROL-03 (no se degrada la mutación).", 

"rol_sys": "ROL-SYS = procesos automáticos; NO humano. No asignable por la API (allowlist de roles humanos), no inicia sesión (AuthService lo rechaza → 401), fuera de la jerarquía humana (RoleName::atLeast lo excluye). El middleware EnsureOperableUser corta CADA petición autenticada (API y web) de cuentas ROL-SYS o inactivas → 403 e invalida la sesión (logout guard web + session invalidate; sin bucles de redirección). Cuentas ROL-SYS existentes: neutralizadas (is_active=false + contraseña aleatoria) y sesiones persistidas invalidadas por migración; no se borran (auditoría).", 

"escalacion": "Regla de RANGO única (UserService::assertGrantable, misma en POST /users y PUT /users/{user}/role): solo roles humanos de nivel ≤ actor. ROL-02 no crea/asigna ROL-01 ni ROL-SYS; ROL-01 no asigna ROL-SYS; nadie modifica su propio rol ni el del propietario. Errores: 422 (ROL-SYS fuera del allowlist en POST), 403 (rango, RoleAssignmentException code ROLE_ASSIGNMENT_FORBIDDEN; y PUT bloqueado por Policy assignRole).", 

"resources": { 

"operative_profiles": [ 
{ "method": "GET", "path": "/operative-profiles", "roles": ["ROL-02", "ROL-01"], "note": "Catálogo de perfiles ROL-03 desde config/profiles.php.", "response_200": "{ data:[ {value, label, capabilities[]} ] }" }, 
{ "method": "GET", "path": "/users/{user}/profiles", "roles": ["ROL-02", "ROL-01"], "response_200": "UserResource (incluye profiles[])" }, 
{ "method": "PUT", "path": "/users/{user}/profiles", "roles": ["ROL-02", "ROL-01"], "request": { "profiles": "array|required|min:1", "profiles.*": "enum[cajero,facturador,bodeguero,despachador]|distinct" }, "note": "Reemplaza el conjunto de perfiles. Solo objetivos ROL-03 del mismo negocio (UserPolicy::manageProfiles); rango del actor aplicado. Para dejar sin perfiles, cambie el rol (que los limpia).", "response_200": "UserResource (incluye profiles[])", "errors": [ { "http": 403, "when": "El objetivo no es ROL-03, o rango insuficiente." }, { "http": 422, "when": "profiles vacío o perfil no reconocido." } ] } 
], 

"users_rol03": [ 
{ "method": "POST", "path": "/users", "roles": ["ROL-02", "ROL-01"], "note": "Crear ROL-03 EXIGE branch_id (same_tenant) y profiles (array min:1). Rol humano ≤ actor (403 si excede; 422 si ROL-SYS).", "request_extra": { "branch_id": "int|required_if:role,ROL-03", "profiles": "array|required_if:role,ROL-03|min:1", "profiles.*": "enum[cajero,facturador,bodeguero,despachador]" } }, 
{ "method": "PUT", "path": "/users/{user}/role", "roles": ["ROL-01", "ROL-02"], "note": "Convertir a ROL-03 EXIGE branch_id + profiles; salir de ROL-03 LIMPIA los perfiles.", "request_extra": { "branch_id": "int|required_if:role,ROL-03", "profiles": "array|required_if:role,ROL-03|min:1" } } 
], 

"cash_session_current": [ 
{ "method": "GET", "path": "/cash-sessions/current", "roles": ["auth (ROL-03 cajero y superiores)"], "note": "Sesión de caja ABIERTA del PROPIO usuario (opened_by=self); nunca la de otro. Registrada antes del binding {cashSession}.", "response_200": "CashSessionResource (incluye cashRegister con branch) | { data: null } si no hay sesión activa (respuesta estable)." } 
], 

"collectible_receivables": [ 
{ "method": "GET", "path": "/accounts-receivable/collectible", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil cajero)"], "query": { "search": "string|opt (cliente, documento o folio de factura)", "per_page": "int|1..100|opt" }, "note": "CxC COBRABLES (estado pendiente/parcial/vencida). ROL-03 acotado a SU sucursal (por la factura). Vista MÍNIMA (no abre el detalle administrativo). Registrada antes del binding {accountReceivable}.", "response_200": "Paginated<CollectibleReceivableResource {id, customer_id, customer_name, invoice_id, invoice_folio, branch_id, total_amount, paid_amount, balance, status, due_date}>", "errors": [ { "http": 403, "when": "ROL-03 sin perfil cajero." } ] } 
], 

"dashboards": [ 
{ "method": "GET", "path": "/dashboard/kpis", "roles": ["ROL-01"], "note": "Ruta CANÓNICA del dashboard de KPIs (MOD-12). No se crea ruta fantasma; el frontend definitivo consume esta." }, 
{ "method": "GET", "path": "/dashboard/admin", "roles": ["ROL-02", "ROL-01"], "note": "Agregado administrativo de pendientes REALES del negocio.", "response_200": "{ data: { anomalias_activas, recepciones_en_discrepancia, cuentas_por_pagar_congeladas, cuentas_por_cobrar_vencidas, sesiones_caja_abiertas, ventas_abiertas } (conteos) }" }, 
{ "method": "GET", "path": "/dashboard/operative", "roles": ["ROL-03 y superiores"], "note": "Secciones CONDICIONADAS por los perfiles del usuario y acotadas a su sucursal. Solo aparece la sección de un perfil si lo tiene.", "response_200": "{ data: { branch_id, profiles[], sections: { cajero?{sesion_caja_abierta, cxc_cobrables}, facturador?{ventas_abiertas}, bodeguero?{conteos_abiertos, recepciones_en_discrepancia}, despachador?{despachos_de_sucursal} } } }" } 
], 

"rol01_evidence": "Lecturas de EVIDENCIA para decisiones ROL-01 (existentes, sin entidad de solicitudes): GET /anomalies y /anomalies/{id}(+/events) antes de resolver; GET /goods-receipts/{id} antes de resolver discrepancia; GET /accounts-payable/{id} antes de desbloquear; GET /invoices/{id} antes de anular; GET /customers/{id}/credit-status antes de autorizar excepción de crédito. No se introduce ninguna bandeja de solicitudes." 

} } 

### **MOD-02 — Catálogo y Datos Maestros** 

{ 

"module": "MOD-02", 

"base_url": "/api/v1", 

"resources": { 

"categories": [ 

{ "method": "GET", "path": "/categories", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil facturador: catalogo.ver)"], 

"query": { "parent_id": "int|opt", "is_active": "bool|opt", "tree": "bool|opt", "search": "string|opt|min:2|max:120" }, 

"response_200": "Paginated<CategoryResource> (lista plana). Con ?tree=true devuelve Collection<CategoryResource> (raíces con descendencia anidada en `children`) SIN paginación: es la vista estructural completa y los filtros de lista no se aplican.", 

"reconciliation_note": "v2.1: ?tree=true devuelve el árbol completo sin sobre de paginación (arreglo de raíces; cada nodo trae `children` recursivo). IMPACTO FRONTEND: distinguir la respuesta paginada (sin tree) de la respuesta en árbol no paginada (con tree=true)." }, 

{ "method": "POST", "path": "/categories", "roles": ["ROL-02"], 

"request": { "name": "string(120)|required|unique_per_business", "parent_id": "int|nullable|same_tenant", "is_active": "bool|opt" }, 

"response_201": "CategoryResource", 

"errors": [ { "code": "ERR-02", "http": 422, "when": "Nombre duplicado o ciclo padre-hijo (CyclicReferenceException)." } ] }, 

- { "method": "PUT", "path": "/categories/{id}", "roles": ["ROL-02"], 

"errors": [ { "code": "ERR-02", "http": 422, "when": "parent_id genera ciclo directo o indirecto." } ], 

"response_200": "CategoryResource" }, 

- { "method": "DELETE", "path": "/categories/{id}", "roles": ["ROL-02"], 

"note": "Soft-delete. El force-delete con dependencias lanza RestrictDeleteException.", 

"errors": [ { "code": "ERR-02B", "http": 409, "when": "Borrado físico con subcategorías/productos." } ], 

"response_204": null } 

], 

"brands": [ 

{ "method": "GET", "path": "/brands", "roles": ["ROL-02"], "response_200": "Paginated<BrandResource>" }, 

- { "method": "POST", "path": "/brands", "roles": ["ROL-02"], 

"request": { "name": "string(120)|required|unique_per_business", "is_active": "bool|opt" }, "response_201": "BrandResource" }, 

{ "method": "PUT", "path": "/brands/{id}", "roles": ["ROL-02"], "response_200": "BrandResource" }, 

{ "method": "DELETE", "path": "/brands/{id}", "roles": ["ROL-02"], "response_204": "Soft-delete (brand_id es nullOnDelete)" } 

], 

"units_of_measure": [ 

- { "method": "GET", "path": "/units", "roles": ["ROL-02"], "response_200": "Paginated<UnitResource>", "reconciliation_note": "v2.1: el listado de unidades SÍ se pagina (sobre estándar {data, links, meta}), igual que el resto de listados. El contrato v1 decía Collection<UnitResource> (arreglo plano); se corrige a Paginated. IMPACTO FRONTEND: consumir unidades desde `data[]` con `meta`/`links`, no como arreglo simple. Acepta ?page y ?per_page." }, 

{ "method": "POST", "path": "/units", "roles": ["ROL-02"], 

"request": { "name": "string(50)|required", "abbreviation": "string(10)|required|unique_per_business" }, "response_201": "UnitResource" }, 

{ "method": "PUT", "path": "/units/{id}", "roles": ["ROL-02"], "response_200": "UnitResource" }, 

- { "method": "DELETE", "path": "/units/{id}", "roles": ["ROL-02"], 

"note": "Sin softDeletes: borrado físico protegido por RESTRICT + guarda de modelo.", 

"errors": [ { "code": "ERR-02B", "http": 409, "when": "Unidad referenciada por productos o recetas." } ], 

"response_204": null } 

], 

"products": [ 

{ "method": "GET", "path": "/products", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil facturador: catalogo.ver)"], 

"query": { "type": "enum[simple,compound,service]|opt", "category_id": "int|opt", "is_active": "bool|opt", "available": "bool|opt", "search": "string|opt" }, 

"response_200": "Paginated<ProductResource>" }, 

{ "method": "POST", "path": "/products", "roles": ["ROL-02"], 

"request": { "sku": "string(60)|required|unique_per_business", "name": "string(160)|required", 

"type": "enum[simple,compound,service]|required", "category_id": "int|required|same_tenant", 

"brand_id": "int|nullable|same_tenant", "unit_id": "int|required|same_tenant", 

"sale_price": "decimal(12,2)|>=0", "cost": "decimal(12,2)|>=0", 

"tracks_inventory": "bool|opt", "is_taxable": "bool|opt", "is_active": "bool|opt" }, 

"note": "type=service ⇒ tracks_inventory forzado a false (backed enum, saving hook).", 

"response_201": "ProductResource", 

"errors": [ { "code": "ERR-02", "http": 422, "when": "SKU duplicado." } ] }, 

{ "method": "PUT", "path": "/products/{id}", "roles": ["ROL-02"], 

"errors": [ { "code": "IMMUTABLE_SKU", "http": 422, "when": "Cambio de SKU con transacciones asociadas (activo desde MOD-03/07).", "reconciliation_note": "v2.1: se implementa como ERROR DE VALIDACIÓN (sobre 422 estándar {message, errors:{sku:[...]}}), NO como excepción con campo `code`. IMPACTO FRONTEND: leer el mensaje en `errors.sku`, no en un `code:'IMMUTABLE_SKU'`. El código IMMUTABLE_SKU queda como etiqueta semántica del caso, no como campo de la respuesta." } ], 

"response_200": "ProductResource" }, 

{ "method": "DELETE", "path": "/products/{id}", "roles": ["ROL-02"], "response_204": "Soft-delete, preserva referencias históricas" } 

], 

"product_recipes": [ 

{ "method": "GET", "path": "/products/{compound_id}/recipe", "roles": ["ROL-02"], "response_200": "Collection<RecipeLineResource>" }, 

{ "method": "POST", "path": "/products/{compound_id}/recipe", "roles": ["ROL-02"], 

"request": { "ingredient_id": "int|required|same_tenant|type!=service", "quantity": "decimal(12,3)|>0", "unit_id": "int| required|same_tenant" }, 

"response_201": "RecipeLineResource", 

"errors": [ { "code": "ERR-02", "http": 422, "when": "Auto-composición o ciclo indirecto (chk_recipe_no_self + DFS)." } ] }, 

{ "method": "PUT", "path": "/products/{compound_id}/recipe/{line_id}", "roles": ["ROL-02"], "response_200": "RecipeLineResource" }, 

{ "method": "DELETE", "path": "/products/{compound_id}/recipe/{line_id}", "roles": ["ROL-02"], "response_204": null } 

] 

} 

} 

### **MOD-03 — Inventario Lógico y Bodega Física** 

{ 

"module": "MOD-03", 

"base_url": "/api/v1", 

"auth": { "scheme": "Cookie/session (Sanctum SPA)", "guard": "web", "tenant_source": "Auth::user()->business_id (BusinessScope global)", "note": "RECONCILIADO (auditoría global): NO es Bearer. Toda la API usa sesión SPA de Sanctum; el tenant se resuelve del usuario autenticado, jamás de un business_id del request." }, 

"conventions": { 

"money_and_qty": "Cantidades y costos viajan como string decimal (precisión bcmath). qty escala 3, costo escala 4.", 

"stock_writes": "Ningún endpoint escribe stock_levels directamente: todo pasa por InventoryService (transaccional + lockForUpdate).", 

"kardex": "inventory_movements es SOLO lectura vía API (append-only, trait Immutable). No hay POST/PUT/DELETE directo.", 

"user_id_from_session": "physical_counts, stock_transfers, inventory_adjustments e inventory_movements derivan user_id de la sesión, nunca del cuerpo.", 

"codes_generated": "stock_transfers.code lo genera StockTransferService de forma atómica (TR-000001 vía tabla `sequences`); nunca se recibe por request.", 

"same_tenant_active": "Los movimientos físicos exigen bodega is_active=true (no solo pertenencia al tenant).", 

"thresholds_only": "/stock nunca crea saldo ni edita quantity/reserved/average_cost por API; el único write es min/max (con CHECK chk_stock_min_le_max). uniq_active_warehouse_name protege el nombre de bodega activo por sucursal (name_lock, compatible con softDeletes)." 

}, 

"resources": { 

"warehouse_assignments": [ 

{ "method": "GET", "path": "/warehouse-assignments", "roles": ["ROL-03", "ROL-02", "ROL-01"], "query": { "warehouse_id": "int|opt", "user_id": "int|opt (solo ROL-01/02)", "active": "bool|opt", "sort": "enum[assigned_at,ended_at,created_at]|opt", "page": "int|opt", "per_page": "int|opt|max:100|default:25" }, "note": "RF-03 asignación Bodega–Bodeguero (M:N, historial temporal). ROL-01/ROL-02 ven todas con filtros; ROL-03 SOLO las suyas (user_id forzado). Activa = ended_at NULL.", "response_200": "Paginated<WarehouseAssignmentResource { id, warehouse_id, user_id, branch_id, assigned_by, assigned_at, ended_at, ended_by, active }>" }, 

{ "method": "POST", "path": "/warehouse-assignments", "roles": ["ROL-01", "ROL-02"], "request": { "warehouse_id": "int|required|same_tenant", "user_id": "int|required|same_tenant" }, "note": "Asigna un bodeguero a una bodega. M:N (un bodeguero varias bodegas activas, una bodega varios bodegueros activos). Invariantes (Service): misma sucursal, usuario ROL-03 con perfil bodeguero, par (bodega,usuario) activo único (candado de motor). business_id/assigned_by/fechas del servidor.", "response_201": "WarehouseAssignmentResource", "errors": [ { "http": 403, "when": "Rol inferior a ROL-02." }, { "http": 422, "field": "user_id", "when": "El usuario no es ROL-03 con perfil bodeguero, o bodega y bodeguero no comparten sucursal." }, { "code": "WAREHOUSE_ALREADY_ASSIGNED", "http": 409, "key": "error", "when": "El par (bodega, bodeguero) ya tiene una asignación activa." } ] }, 

{ "method": "DELETE", "path": "/warehouse-assignments/{id}", "roles": ["ROL-01", "ROL-02"], "note": "Finaliza (ended_at/ended_by). Historial append-only. Idempotente si ya finalizada.", "response_200": "WarehouseAssignmentResource { active:false }", "errors": [ { "http": 404, "when": "La asignación pertenece a otro negocio (BusinessScope)." } ] } 

], 

"_warehouse_operation_enforcement": "RF-03 · Un ROL-03 bodeguero solo OPERA (conteo físico, recepción de compra, traspaso —origen al crear, destino al completar—) bodegas con asignación ACTIVA; la compuerta vive en los Services de inventario (WarehouseAssignmentService::assertOperates), no solo en el FormRequest. Sin asignación → 403. Los ajustes de inventario son potestad administrativa (ROL-02+): el perfil bodeguero no habilita 'ajustes'. ROL-01/ROL-02 no se acotan por asignación.", 

"warehouses": [ 

{ "method": "GET", "path": "/warehouses", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: bodegas.ver — índice y detalle acotados a su sucursal)"], 

"query": { "branch_id": "int|opt", "is_active": "bool|opt", "is_default": "bool|opt" }, 

"response_200": "Paginated<WarehouseResource>" }, 

- { "method": "POST", "path": "/warehouses", "roles": ["ROL-02"], 

"request": { "branch_id": "int|required|same_tenant", "name": "string(120)|required", 

- "is_default": "bool|opt", "is_active": "bool|opt" }, 

"note": "Máx. una bodega is_default=true por sucursal (candado default_lock).", 

"response_201": "WarehouseResource", 

"errors": [ { "http": 422, "when": "Nombre duplicado en (branch, name) o segunda bodega default en la sucursal." } ] }, 

- { "method": "GET", "path": "/warehouses/{id}", "roles": ["ROL-02"], "response_200": "WarehouseResource" }, 

- { "method": "PUT", "path": "/warehouses/{id}", "roles": ["ROL-02"], "response_200": "WarehouseResource" }, 

- { "method": "DELETE", "path": "/warehouses/{id}", "roles": ["ROL-02"], "response_204": "Soft-delete", "errors": [ { "code": "ERR-02B", "http": 409, "when": "La bodega tiene existencias o reservas, participa en traspasos pendientes, o es la predeterminada de su sucursal y no se ha designado otra. La baja es lógica (soft-delete), así que la guarda es de dominio (Warehouse::deletionBlocker). IMPLEMENTADO v2.1. FRONTEND: manejar 409 con code ERR-02B al dar de baja bodegas." } ] } 

], 

- "stock_levels": [ 

- { "method": "GET", "path": "/stock", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: inventario.ver)"], 

"query": { "warehouse_id": "int|opt", "product_id": "int|opt", "below_min": "bool|opt", "search": "string|opt" }, 

- "response_200": "Paginated<StockLevelResource>", 

"resource_fields": { "quantity": "string", "reserved_quantity": "string", "available": "string (quantity - reserved, accesor)", "average_cost": "string", "min_stock": "string|null", "max_stock": "string|null" } }, 

{ "method": "GET", "path": "/stock/{product_id}/{warehouse_id}", "roles": ["ROL-02", "ROL-03"], "response_200": "StockLevelResource" }, 

- { "method": "PUT", "path": "/stock/{product_id}/{warehouse_id}/thresholds", "roles": ["ROL-02"], 

"request": { "min_stock": "decimal|nullable", "max_stock": "decimal|nullable" }, 

"note": "Solo edita umbrales min/max. quantity y reserved NUNCA son editables por API.", 

"response_200": "StockLevelResource" } 

], 

"physical_counts": [ 

- { "method": "GET", "path": "/physical-counts", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: inventario.conteo — acotado a su sucursal)"], 

"query": { "warehouse_id": "int|opt", "status": "enum[abierto,justificado,ajustado]|opt", "product_id": "int|opt" }, "response_200": "Paginated<PhysicalCountResource>" }, 

- { "method": "POST", "path": "/physical-counts", "roles": ["ROL-03"], 

"request": { "warehouse_id": "int|required|same_tenant", "product_id": "int|required|same_tenant", 

"counted_quantity": "decimal(14,3)|required", "notes": "string(500)|nullable" }, 

"note": "system_quantity lo captura el backend desde stock_levels al momento del conteo; difference la calcula el motor (columna generada). status nace 'abierto'.", 

"response_201": "PhysicalCountResource" }, 

- { "method": "GET", "path": "/physical-counts/{id}", "roles": ["ROL-02"], "response_200": "PhysicalCountResource" }, 

- { "method": "POST", "path": "/physical-counts/{id}/apply", "roles": ["ROL-02"], 

"note": "Ejecuta InventoryService::ajustarPorConteo → crea inventory_adjustment + movimiento 'ajuste' y lleva status a 'ajustado'. Atómico.", 

"response_200": "PhysicalCountResource", 

"errors": [ { "http": 409, "when": "El conteo ya fue ajustado (status != abierto)." } ] }, 

- { "method": "POST", "path": "/physical-counts/{id}/justify", "roles": ["ROL-02"], 

"request": { "reason": "string(500)|required|min:3" }, 

"note": "D-8. Transición abierto → justificado SIN ajustar stock; enruta al flujo de anomalías (RF-03-03). Controlador JustifyPhysicalCountController.", 

"response_200": "PhysicalCountResource", 

"errors": [ { "http": 409, "when": "El conteo ya no está abierto." } ] } 

], 

"stock_transfers": [ 

- { "method": "GET", "path": "/stock-transfers", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: inventario.traspaso — su sucursal como origen o destino)"], 

"query": { "from_warehouse_id": "int|opt", "to_warehouse_id": "int|opt", "status": "enum[pendiente,completado,cancelado]| opt" }, 

"response_200": "Paginated<StockTransferResource>" }, 

- { "method": "POST", "path": "/stock-transfers", "roles": ["ROL-03"], 

"request": { "from_warehouse_id": "int|required|same_tenant", "to_warehouse_id": "int|required|same_tenant| different_from:from_warehouse_id", 

"notes": "string(500)|nullable", 

"items": [ { "product_id": "int|required|same_tenant|distinct", "quantity": "decimal(14,3)|>0" } ] }, 

"note": "v2.1 (opción A): las líneas se PERSISTEN al crear (tabla stock_transfer_items) y el traspaso nace 'pendiente'. StockTransferResource expone ahora `items:[{product_id,quantity,product?}]`, visibles incluso en estado pendiente. El costo NO se envía: la entrada en destino se valora al costo promedio de la bodega origen al confirmar.", 

"response_201": "StockTransferResource (incluye `items`)", 

"errors": [ 

- { "http": 422, "when": "from == to (chk_transfer_diff_warehouse + backstop de modelo)." }, 

- { "code": "INSUFFICIENT_STOCK", "http": 409, "when": "Stock insuficiente en la bodega origen." } 

- ] }, 

- { "method": "GET", "path": "/stock-transfers/{id}", "roles": ["ROL-02"], "response_200": "StockTransferResource" }, 

- { "method": "POST", "path": "/stock-transfers/{id}/complete", "roles": ["ROL-03"], "note": "v2.1 (opción A): YA NO acepta `items` en el cuerpo; mueve exclusivamente las líneas persistidas al crear (bajo lock, atómico). FRONTEND: no enviar `items` a /complete; si se envían, se ignoran.", "response_200": "StockTransferResource", "errors": [ { "code": "INSUFFICIENT_STOCK", "http": 409, "when": "Stock insuficiente en origen para alguna línea (rollback total)." }, { "code": "INVALID_STATE", "http": 409, "when": "El traspaso no está pendiente, o es un pendiente antiguo SIN líneas registradas (no se aceptan líneas de reemplazo)." } ] }, 

{ "method": "POST", "path": "/stock-transfers/{id}/cancel", "roles": ["ROL-02"], "response_200": "StockTransferResource" } 

], 

"inventory_adjustments": [ 

- { "method": "GET", "path": "/inventory-adjustments", "roles": ["ROL-02"], 

"query": { "warehouse_id": "int|opt", "type": "enum[merma,sobrante,correccion]|opt", "physical_count_id": "int|opt" }, 

"response_200": "Paginated<InventoryAdjustmentResource>" }, 

- { "method": "POST", "path": "/inventory-adjustments", "roles": ["ROL-02"], 

"request": { "warehouse_id": "int|required|same_tenant", "product_id": "int|required|same_tenant", 

"type": "enum[merma,sobrante,correccion]|required", "quantity": "decimal(14,3)|>0", 

"reason": "string(255)|required", "physical_count_id": "int|nullable|same_tenant" }, 

- "note": "Ajuste directo (sin conteo). Ejecuta InventoryService y escribe movimiento 'ajuste' atómico. reason obligatorio (RF-03-02).", 

"response_201": "InventoryAdjustmentResource", 

"errors": [ { "code": "INSUFFICIENT_STOCK", "http": 409, "when": "Merma que dejaría quantity < 0." } ] }, 

- { "method": "GET", "path": "/inventory-adjustments/{id}", "roles": ["ROL-02"], "response_200": 

- "InventoryAdjustmentResource" } 

], 

"inventory_movements": [ 

- { "method": "GET", "path": "/inventory-movements", "roles": ["ROL-02", "ROL-01"], 

- "query": { "product_id": "int|opt", "warehouse_id": "int|opt", "type": "enum[entrada,salida,ajuste,traspaso]|opt", 

- "from": "datetime|opt", "to": "datetime|opt", "page": "int|opt" }, 

"response_200": "Paginated<InventoryMovementResource>", 

"resource_fields": { "type": "string", "quantity": "string", "balance_after": "string", "unit_cost": "string|null", 

- "origin": "{ purchase_order_id|dispatch_id|stock_transfer_id|inventory_adjustment_id } (máx. 1, 

- chk_movement_single_origin)" }, 

"note": "Kardex SOLO lectura. Cualquier intento de UPDATE/DELETE → ImmutableRecordException (HTTP 403).", 

"errors": [ { "code": "IMMUTABLE_RECORD", "http": 403, "when": "Se intenta modificar/borrar un movimiento del kardex." } ] } ] }, "deferred": { "inventory_movements.purchase_order_id": "FK y relación purchaseOrder() → MOD-04", "inventory_movements.dispatch_id": "FK y relación dispatch() + descuento por retiro → MOD-09", "reservar/liberarReserva": "consumidos por el flujo de facturación → MOD-07" } } 

### **MOD-04 — Compras, Proveedores y Recepción** 

{ 

"module": "MOD-04", 

"base_url": "/api/v1", 

"version": "2.1-canonical", 

"auth": { "scheme": "Cookie/sesión (Sanctum SPA)", "guard": "web", "tenant_source": "session.user.business_id (BusinessScope global)", "note": "NO es Bearer. Toda la API usa sesión SPA de Sanctum; el tenant se resuelve del usuario autenticado, jamás de un business_id del request." }, 

"conventions": { 

"money_and_qty": "Montos string decimal escala 2; costos escala 4; cantidades escala 3 (bcmath).", 

"inventory_entry": "Ingreso a inventario SOLO vía InventoryService::ingresarPorCompra() cuando match_status='ok'.", 

"authority": "Aprobar proveedor, resolver discrepancia y desbloquear CxP = potestad exclusiva ROL-01 (BR-06). Pagar CxP = ROL-02. Registrar recepción = ROL-03.", 

"discrepancy_persists": "Una recepción con discrepancia DEVUELVE 409 pero SÍ crea el goods_receipt + sus items + la CxP congelada (evidencia para ROL-01). El 409 es señal, no rollback (throw DESPUÉS del commit).", 

"codes_by_service": "purchase_orders.code='OC-'+secuencia y stock/otros folios se generan con SequenceGenerator sobre la tabla `sequences` (contador atómico por business_id+type). NUNCA por request. La tabla `sequences` es infraestructura compartida (creada en la reconciliación).", 

"user_id_from_session": "purchase_orders.user_id y goods_receipts.user_id derivan de la sesión (no-repudio). Jamás por request.", 

"supplier_metadata": "approved_by/approved_at son coherencia del SupplierService: aprobar los puebla, suspender los limpia. Jamás por request.", 

"double_supplier_validation": "Proveedor aprobado se valida al CREAR la orden Y al EMITIRLA, bajo lock (puede perder la aprobación en el intervalo).", 

"partial_reception": "received_quantity se acumula por recepción; la orden pasa a 'parcial' o 'recibida' comparando acumulado vs ordenado.", 

"accept_reconstructs_from_evidence": "'aceptar' lee goods_receipt_items (inmutables) y ejecuta el ingreso a inventario diferido.", 

"tax_id_unique": "Candado parcial uniq_active_supplier_tax_id (tax_id_lock): aplica solo con RUC presente y fila activa; múltiples NULL conviven." 

}, 

"resources": { 

"suppliers": [ 

{ "method": "GET", "path": "/suppliers", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: proveedores.ver)"], "query": { "search": "string|opt (nombre o tax_id)", "status": "enum[pendiente,aprobado,suspendido]|opt", "is_active": "bool|opt" }, "note": "v2.1: filtros ya validados por IndexSupplierRequest y ahora aplicados (antes solo se aplicaba search). Aditivos y opcionales; sin impacto para clientes que no los envíen.", "response_200": "Paginated<SupplierResource>" }, 

{ "method": "POST", "path": "/suppliers", "roles": ["ROL-01", "ROL-02"], 

"request": { "name": "string(160)|required", "tax_id": "string(30)|nullable|unique_per_business", "email": "string|nullable", "phone": "string|nullable", "location": "{ address: string(255)|required_with:location, latitude?: numeric[-90,90], longitude?: numeric[-180,180], external_id?: string(120) }|opt — CANDIDATO del mapa: siembra una ubicación 'external' (lat/lng juntas o ninguna)" }, 

"note": "Nace status='pendiente' (ROL-01/ROL-02; un CANDIDATO descubierto externamente también nace pendiente, NUNCA aprobado). approved_by/at no se aceptan aquí. Si se envía `location`, se crea una supplier_location de procedencia 'external' SIN confirmar (no entra al mapa hasta confirmarla).", "response_201": "SupplierResource { ..., locations[] }" }, 

{ "method": "PUT", "path": "/suppliers/{id}", "roles": ["ROL-02"], "response_200": "SupplierResource" }, 

{ "method": "POST", "path": "/suppliers/{id}/approve", "roles": ["ROL-01"], 

"note": "status='aprobado', puebla approved_by (auth)+approved_at. DESPUÉS de aprobar solicita la geocodificación de las ubicaciones pendientes de forma INDEPENDIENTE: si no hay proveedor configurado (driver 'null') o falla, la ubicación queda pendiente y el proveedor SIGUE aprobado (nunca se revierte). Geocodificar NO confirma el marcador.", 

"response_200": "SupplierResource { ..., locations[] }", "errors": [ { "http": 403, "when": "Rol != ROL-01." } ] }, 

{ "method": "POST", "path": "/suppliers/{id}/suspend", "roles": ["ROL-01"], "note": "Limpia metadatos de aprobación. Un proveedor suspendido desaparece del mapa (status != aprobado).", "response_200": "SupplierResource" }, 

{ "method": "DELETE", "path": "/suppliers/{id}", "roles": ["ROL-02"], "response_204": "Soft-delete. También lo saca del mapa." } 

], 

"supplier_locations": [ 

{ "method": "GET", "path": "/suppliers/{supplier}/locations", "roles": ["ROL-02", "ROL-01", "ROL-03 (proveedores.ver)"], "note": "Ubicaciones del proveedor (principal primero). `confirmed` indica si entra al mapa.", "response_200": "Collection<SupplierLocationResource { id, supplier_id, address, latitude, longitude, geocode_source, external_id, quality, is_primary, confirmed, geocoded_at, confirmed_at, confirmed_by }>" }, 

{ "method": "POST", "path": "/suppliers/{supplier}/locations", "roles": ["ROL-01", "ROL-02"], "request": { "address": "string(255)|required", "latitude": "numeric[-90,90]|nullable (junto con longitude)", "longitude": "numeric[-180,180]|nullable", "external_id": "string(120)|nullable", "is_primary": "bool|opt" }, "note": "Crea una ubicación. Con coordenadas al alta ⇒ procedencia 'external', sin confirmar. Una sola principal por proveedor (candado de motor → 422).", "response_201": "SupplierLocationResource", "errors": [ { "http": 422, "field": "latitude", "when": "Coordenada fuera de rango o lat/lng no enviadas juntas." }, { "http": 422, "field": "is_primary", "when": "El proveedor ya tiene una ubicación principal." }, { "http": 403, "when": "Rol inferior a ROL-02." }, { "http": 404, "when": "El proveedor es de otro negocio." } ] }, 

{ "method": "PUT", "path": "/suppliers/{supplier}/locations/{location}", "roles": ["ROL-01", "ROL-02"], "request": { "address": "string(255)|opt", "is_primary": "bool|opt" }, "note": "Cambiar la dirección INVALIDA la confirmación anterior: limpia coordenadas, geocode_source, geocoded_at, confirmed_at/by (sale del mapa hasta regeocodificar/confirmar). No fija coordenadas aquí.", "response_200": "SupplierLocationResource" }, 

{ "method": "POST", "path": "/suppliers/{supplier}/locations/{location}/geocode", "roles": ["ROL-01", "ROL-02"], "note": "Solicita geocodificación vía el adaptador configurado (con caché). Si no hay proveedor o falla, la ubicación queda pendiente (no error de bloqueo). NO confirma el marcador. Devuelve la ubicación con procedencia 'geocoded' si resolvió.", "response_200": "SupplierLocationResource" }, 

{ "method": "POST", "path": "/suppliers/{supplier}/locations/{location}/confirm", "roles": ["ROL-01", "ROL-02"], "request": { "latitude": "numeric[-90,90]|nullable", "longitude": "numeric[-180,180]|nullable" }, "note": "Confirma/corrige el marcador. Con lat/lng: fija coordenadas (procedencia 'manual'); sin ellas: confirma las ya geocodificadas. Exige coordenadas (422 si no hay). Marca confirmed_at/by → entra al mapa.", "response_200": "SupplierLocationResource", "errors": [ { "http": 422, "field": "latitude", "when": "Sin coordenadas disponibles, fuera de rango, o lat/lng no enviadas juntas." } ] } 

], 

"map": [ 

{ "method": "GET", "path": "/map/suppliers", "roles": ["ROL-02", "ROL-01", "ROL-03 (proveedores.ver)"], "note": "Proveedores del MAPA: SOLO aprobados, activos y con al menos una ubicación CONFIRMADA, aislados por el business_id de la sesión. Un proveedor suspendido, inactivo o eliminado no aparece. No expone claves de geocodificación.", "response_200": "Collection<MapSupplierResource { id, name, status, locations[]: { id, address, latitude, longitude, is_primary, quality, confirmed_at } }>" } 

], 

"purchase_orders": [ 

{ "method": "GET", "path": "/purchase-orders", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: compras.ver — índice y detalle acotados a su sucursal)"], 

"query": { "supplier_id": "int|opt", "status": "enum[borrador,emitida,parcial,recibida,cancelada]| opt" }, "response_200": "Paginated<PurchaseOrderResource>" }, 

{ "method": "POST", "path": "/purchase-orders", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: compras.crear — BORRADOR; branch_id debe ser su sucursal, 422 si no)"], 

"request": { "supplier_id": "int|required|same_tenant|approved", "branch_id": "int|required| same_tenant", "ordered_at": "date|required", 

"items": [ { "product_id": "int|required|same_tenant", "ordered_quantity": "decimal(14,3)|>0", "agreed_unit_cost": "decimal(14,4)|>=0" } ] }, 

"note": "Bloquea si proveedor no aprobado. line_total y expected_total los calcula el backend. Nace 'borrador'.", 

"response_201": "PurchaseOrderResource", "errors": [ { "code": "SUPPLIER_NOT_APPROVED", "http": 422 } ] }, 

{ "method": "PUT", "path": "/purchase-orders/{id}", "roles": ["ROL-02"], "note": "Editable solo en 'borrador'. Recalcula expected_total.", "response_200": "PurchaseOrderResource" }, 

{ "method": "POST", "path": "/purchase-orders/{id}/issue", "roles": ["ROL-02"], "note": "borrador→emitida. Revalida proveedor aprobado.", 

"response_200": "PurchaseOrderResource", "errors": [ { "code": "SUPPLIER_NOT_APPROVED", "http": 422 } ] }, 

{ "method": "POST", "path": "/purchase-orders/{id}/cancel", "roles": ["ROL-02"], "response_200": "PurchaseOrderResource" } 

], 

"goods_receipts": [ 

{ "method": "GET", "path": "/goods-receipts", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: compras.ver — acotado a su sucursal por la bodega)"], 

"query": { "purchase_order_id": "int|opt", "match_status": "enum[ok,discrepancia,bloqueada]|opt" }, 

"response_200": "Paginated<GoodsReceiptResource> (incluye items[])" }, 

{ "method": "POST", "path": "/goods-receipts", "roles": ["ROL-03"], 

"request": { "purchase_order_id": "int|required|same_tenant|receivable", "warehouse_id": "int| required|same_tenant", 

"supplier_invoice_number": "string(60)|nullable", "supplier_invoice_total": "decimal(14,2)| nullable", "tolerance": "decimal|opt|default:0.00", 

"lines": [ { "purchase_order_item_id": "int|required", "received_quantity": "decimal(14,3)|>0", "invoiced_unit_cost": "decimal(14,4)|>=0" } ] }, 

"branch_isolation_rol03": "ROL-03 (bodeguero): la ORDEN y la BODEGA deben ser de SU sucursal (purchase_order.branch_id === warehouse.branch_id === user.branch_id). Defensa en DOS capas: StoreGoodsReceiptRequest (422 por campo: purchase_order_id si la orden es de otra sucursal, warehouse_id si la bodega lo es; un ROL-03 sin sucursal falla cerrado) y GoodsReceiptService (AuthorizationException/403 dentro de la transacción, ANTES de persistir goods_receipts/items/inventario/CxP, para que una invocación interna directa no omita el FormRequest). Recurso de otro negocio → 422 (exists multitenant), sin revelar su existencia. ROL-01/ROL-02 no se acotan (alcance de negocio). Al rechazarse NO se persiste ninguna recepción, CxP ni afectación de inventario (rollback). El hook post-commit de discrepancia_3way no se altera.", 

"note": "Ejecuta 3-Way Match (recibido⇄ordenado⇄facturado, costo facturado⇄pactado, total⇄suma). SIEMPRE persiste goods_receipt_items como evidencia. Si 'ok': ingresa a inventario por línea + acumula received_quantity + CxP 'pendiente'. Si discrepancia: NO ingresa, CxP 'congelada'.", 

"response_201": { 

"on_ok": "201. Envelope estándar de Resource: { data: GoodsReceiptResource { match_status:'ok', items:[GoodsReceiptItemResource con matched:true], account_payable:{status:'pendiente'} } }. La clave es `account_payable` (singular, whenLoaded 'accountPayable'), NO `accounts_payable_id`." }, 

"response_409_on_discrepancy": "409, RECURSO CREADO. Cuerpo canónico (PurchaseMatchException::render(), que Laravel prioriza): { message: string, code: 'PURCHASE_MATCH', data: GoodsReceiptResource { id, match_status:'discrepancia', items:[{...,matched:false}], account_payable:{status:'congelada'} } }. `code` es semántico y ESTABLE. NO existe la clave duplicada `goods_receipt` (el antiguo mapeo global que la producía era código muerto y se eliminó). IMPACTO FRONTEND: `code` para el caso, `data` para el recurso (id del recibo, match_status, items[].matched, account_payable.status), `message` para el texto.", 

"errors": [ { "code": "PURCHASE_MATCH", "http": 409, "when": "Discrepancia de cantidad/costo/total. El recibo, sus items y la CxP congelada YA quedaron persistidos (throw tras commit) para resolución de ROL-01. Cuerpo: ver response_409_on_discrepancy." } ] }, 

{ "method": "GET", "path": "/goods-receipts/{id}", "roles": ["ROL-02"], "response_200": "GoodsReceiptResource (incluye items[] con matched por línea)" }, 

{ "method": "GET", "path": "/goods-receipts/{id}/items", "roles": ["ROL-02"], 

"note": "Evidencia inmutable por recepción (goods_receipt_items, sin timestamps). `matched` es columna PERSISTIDA (boolean NN, def. true); su migración faltaba y se añadió en la reconciliación (sin ella toda POST /goods-receipts fallaba).", 

"response_200": "Collection<GoodsReceiptItemResource>", 

"resource_fields": { "purchase_order_item_id": "int", "product_id": "int", "received_quantity": "string", "invoiced_unit_cost": "string", "line_total": "string", "matched": "bool (resultado del 3-Way Match por línea)" } }, 

{ "method": "POST", "path": "/goods-receipts/{id}/resolve", "roles": ["ROL-01"], 

"request": { "resolution": "enum[aceptar,rechazar]|required", "notes": "string(500)|nullable" }, 

"note": "'aceptar': reconstruye desde goods_receipt_items, ingresa a inventario, match_status→'ok', CxP→'pendiente', puebla unblocked_by. 'rechazar': match_status→'bloqueada', CxP permanece 'congelada' (terminal). Potestad exclusiva ROL-01.", 

"response_200": "GoodsReceiptResource", 

"errors": [ 

{ "http": 403, "when": "Rol != ROL-01." }, { "http": 409, "when": "El recibo no está en estado 'discrepancia'." } ] } ], "accounts_payable": [ 

{ "method": "GET", "path": "/accounts-payable", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: cuentas_por_pagar.ver — acotado a su sucursal por la orden)"], 

"query": { "supplier_id": "int|opt", "status": "enum[pendiente,congelada,parcial,pagada]|opt", "overdue": "bool|opt" }, 

"response_200": "Paginated<AccountPayableResource>", 

"resource_fields": { "total_amount": "string", "paid_amount": "string", "balance": "string (accesor)", "status": "string", "goods_receipt_id": "int" } }, 

{ "method": "GET", "path": "/accounts-payable/{id}", "roles": ["ROL-02"], "response_200": "AccountPayableResource" }, 

{ "method": "POST", "path": "/accounts-payable/{id}/pay", "roles": ["ROL-02"], 

"request": { "amount": "decimal(14,2)|>0", "due_date": "date|nullable" }, 

"note": "Sube paid_amount, recalcula status. Fase 1: actualización directa (sin payable_payments). Bloqueado si 'congelada'. chk_ap_paid_not_exceed impide sobre-pago.", 

"response_200": "AccountPayableResource", 

"errors": [ { "http": 409, "when": "CxP congelada." }, { "http": 422, "when": "amount excede balance." } ] }, 

{ "method": "POST", "path": "/accounts-payable/{id}/unblock", "roles": ["ROL-01"], 

"note": "Descongela tras resolución. Puebla unblocked_by. Exclusivo ROL-01.", "response_200": "AccountPayableResource", 

"errors": [ { "http": 403, "when": "Rol != ROL-01." } ] } 

] }, "deferred": { 

"payable_payments_table": "Trazabilidad de abonos a CxP → Fase 2 (Fase 1: paid_amount directo).", "policies": "SupplierPolicy / AccountPayablePolicy (autoridad ROL-01) → capa de servicios.", "accounts_payable.status": "Sin estado 'anulada' en Fase 1 (rechazo = terminal-congelado)." } } 


# **MOD-05 - Clientes** 

{ 

"module": "MOD-05", 

"base_url": "/api/v1", 

"version": "2.1-canonical", 

"auth": { "scheme": "Cookie/sesión (Sanctum SPA), NO Bearer", "tenant_source": "session.user.business_id", "note": "RECONCILIADO: la cabecera histórica decía 'Bearer'/'token.business_id'; la autenticación REAL es cookie/sesión (Sanctum SPA). Todos los módulos quedan alineados a Sanctum SPA en la auditoría global (ya no queda ningún bloque 'Bearer')." }, 

"conventions": { 

"phase_1_scope": "Registro y consulta de clientes + direcciones. Perfilamiento/métricas avanzadas (RF-05-01/02) = Fase 2 (tabla satélite por job).", 

"generic_singleton": "El 'Consumidor Final' (is_generic=true) lo siembra BusinessObserver (no la API), uno por negocio (respaldo del motor: uniq_generic_customer_per_business sobre la columna generada generic_lock). Cuádruple guarda: (1) is_generic fuera de $fillable (is_generic=true en el payload NO crea otro genérico ni altera el existente); (2) document_type='generico' se rechaza en validación (FormRequest Rule::in(DocumentType::publicValues()) → 422 sobre document_type; 'generico' es de sistema, no público); (3) scopeReal() lo excluye de listados salvo include_generic; (4) CustomerService bloquea update/delete con PROTECTED_RESOURCE (403). RECONCILIADO v2.1: el código PROTECTED_RESOURCE lo emite el SERVICE (ProtectedResourceException), no la Policy; antes la Policy devolvía un 403 de autorización SIN `code`. La Policy solo autoriza rango/tenant (aislamiento por negocio 404 + rango ROL-02); NO impone la protección del genérico.", 

"document_type_public": "El enum del motor tiene 4 valores; los FormRequest solo admiten cedula/ruc/pasaporte. 'generico' es exclusivo del sistema (DocumentType::publicValues()).", 

"document_number_lock": "Candado parcial uniq_active_customer_document sobre document_number_lock: único por negocio entre clientes ACTIVOS; el borrado lógico libera el documento; NULL múltiples conviven. TOCTOU 1062 → 422 ValidationException sobre document_number.", 

"include_generic_scope": "GET /customers aplica scopeReal() por defecto; include_generic=true incluye el singleton.", 

"receivables_guard_LIVE": "RECONCILIADO v2.1: hasPendingReceivables() consulta la tabla física `accounts_receivables` (AccountReceivable::$table; scopePending = estados pendiente/parcial/vencida; una CxC 'pagada' NO cuenta como saldo vivo); MOD-08 está implementado. CUSTOMER_HAS_RECEIVABLES (422) se dispara en DOS vías con CxC viva: (a) DELETE /customers/{id} (baja lógica) y (b) PUT/PATCH /customers/{id} con is_active=false (desactivación): la deuda no puede ocultarse retirándole el acceso operativo. CustomerService::assertHasNoLiveReceivables es backstop también de vías no-HTTP. (Antes se documentó 'inerte hasta MOD-08' y solo cubría el borrado.)", 

"addresses_no_default_lock": "Sin default_lock: is_default libre. Binding anidado (scopeBindings) valida que la dirección pertenezca al cliente. DELETE físico (subordinada, cascade).", 

"boolean_query_params": "Los filtros booleanos (is_active, include_generic) usan validación estricta: acepta 1/0 (y true/false nativos), NO las cadenas 'true'/'false'. El frontend debe enviar 1/0.", 

"credit_note": "credit_limit se registra aquí; su aplicación en ventas a crédito es MOD-08." 

}, 

"resources": { 

"customers": [ 

{ "method": "GET", "path": "/customers", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil cajero/facturador: clientes.ver)"], 

"query": { "search": "string|opt|min:2 (nombre, document_number, email o phone_number)", "document_type": "enum[cedula,ruc,pasaporte]|opt", "is_active": "bool|opt (1/0)", "include_generic": "bool|opt|default:false (1/0)", "sort": "enum[name,document_number,is_active,created_at]|opt", "direction": "enum[asc,desc]|opt", "page": "int|opt", "per_page": "int|opt|max:100|default:25" }, 

"note": "Por defecto excluye el genérico (scopeReal); include_generic=1 lo incluye. RECONCILIADO v2.1: search e is_active (validados por IndexCustomerRequest) AHORA se aplican (antes se ignoraban); también orden/paginación.", 

"response_200": "Paginated<CustomerResource>" }, 

{ "method": "POST", "path": "/customers", "roles": ["ROL-02", "ROL-03"], 

"request": { "name": "string(160)|required", "document_type": "enum[cedula,ruc,pasaporte]| required", 

"document_number": "string(30)|nullable|unique_per_business", "email": "string(180)|nullable", 

"phone_number": "string(30)|nullable", "birth_date": "date|nullable", "credit_limit": "decimal(14,2)|>=0|default:0", "notes": "string(500)|nullable" }, 

"note": "is_generic NO se acepta (fuera de fillable). document_number único por negocio entre activos (NULL múltiples ok). RECONCILIADO v2.1: la respuesta 201 refleja is_generic=false (el Service hace refresh tras crear).", 

"response_201": "CustomerResource", 

"errors": [ { "code": "ERR-05", "http": 422, "when": "document_number duplicado en el negocio (ValidationException)." } ] }, 

{ "method": "GET", "path": "/customers/{id}", "roles": ["ROL-02", "ROL-03"], "response_200": "CustomerResource (incluye addresses[])" }, 

{ "method": "PUT", "path": "/customers/{id}", "roles": ["ROL-02"], 

"request": { "name": "string|opt", "document_type": "enum|opt", "document_number": "string| nullable", "email": "string|nullable", "phone_number": "string|nullable", "birth_date": "date|nullable", "credit_limit": "decimal|>=0", "is_active": "bool|opt", "notes": "string|nullable" }, 

"response_200": "CustomerResource", 

"errors": [ { "code": "PROTECTED_RESOURCE", "http": 403, "when": "Intento de editar el Consumidor Final." }, { "code": "CUSTOMER_HAS_RECEIVABLES", "http": 422, "when": "Desactivación (is_active=false) de un cliente con CxC viva en `accounts_receivables` (pendiente/parcial/vencida). RECONCILIADO v2.1: la desactivación se bloquea igual que el borrado; una CxC pagada no cuenta." } ] }, 

{ "method": "DELETE", "path": "/customers/{id}", "roles": ["ROL-02"], 

"note": "Soft-delete. Conserva historial de compras.", 

"response_204": null, 

"errors": [ 

{ "code": "PROTECTED_RESOURCE", "http": 403, "when": "Intento de eliminar el Consumidor Final." }, 

{ "code": "CUSTOMER_HAS_RECEIVABLES", "http": 422, "when": "Cliente con CxC viva. RECONCILIADO v2.1: ACTIVO (MOD-08 implementado); hasPendingReceivables() consulta la tabla física `accounts_receivables` (scopePending = pendiente/parcial/vencida; 'pagada' no cuenta) y el borrado se bloquea con 422." } 

] } 

], 

"customer_addresses": [ 

{ "method": "GET", "path": "/customers/{customer_id}/addresses", "roles": ["ROL-02", "ROL-03"], "query": { "page": "int|opt", "per_page": "int|opt|default:15" }, "order": "is_default desc, id desc", "response_200": "Paginated<CustomerAddressResource> — envelope estándar {data[], links{first,last,prev,next}, meta{current_page,per_page,total,last_page,...}}", "reconciliation_note": "v2.1: se pagina (envelope estándar {data, links, meta}) como el resto de listados; el contrato v1 decía Collection. Default per_page=15 (request->integer('per_page', 15)); meta.per_page refleja el valor recibido. IMPACTO FRONTEND: consumir desde `data[]`. Binding anidado acotado al cliente padre por scopeBindings (dirección de otro cliente del mismo negocio → 404)." }, 

{ "method": "POST", "path": "/customers/{customer_id}/addresses", "roles": ["ROL-02", "ROL-03"], 

"request": { "label": "string(50)|required", "address_line": "string(255)|required", "reference": "string(255)|nullable", "is_default": "bool|opt" }, 

"note": "Si se activa el candado opcional default_lock, marcar una nueva default exige desmarcar la anterior en la misma transacción.", 

"response_201": "CustomerAddressResource" }, 

{ "method": "PUT", "path": "/customers/{customer_id}/addresses/{id}", "roles": ["ROL-02", "ROL-03"], "response_200": "CustomerAddressResource" }, 

{ "method": "DELETE", "path": "/customers/{customer_id}/addresses/{id}", "roles": ["ROL-02"], "response_204": "Borrado físico (subordinada, cascade con cliente)" } 

] 

}, 

"deferred": { 

"customer_metrics": "Perfilamiento dinámico Frecuente/Ocasional + métricas de fidelidad (RF-0501/02) → Fase 2, tabla satélite poblada por job.", 

"accounts_receivable_guard": "YA CABLEADO (MOD-08 implementado): hasPendingReceivables() consulta la tabla física `accounts_receivables` (scopePending). Ya no es deferred; ver conventions.receivables_guard_LIVE.", 

"customer.sales/invoices": "Relaciones a ventas/facturas → MOD-07." 

} } 


# **MOD-06 - Gestion de Caja (Ingresos, Egresos y Arqueo)** 

{ "module": "MOD-06", "base_url": "/api/v1", "version": "2.1-canonical", "auth": { "scheme": "Cookie/sesión (Sanctum SPA)", "guard": "web", "tenant_source": "session.user.business_id (BusinessScope global)", "note": "NO es Bearer. Toda la API usa sesión SPA de Sanctum; el tenant se resuelve del usuario autenticado, jamás del request." }, "conventions": { "money": "Montos string decimal escala 2 (bcmath).", "blind_count": "H-49/D-21. expected_amount y difference NO se exponen mientras status='abierta' (arqueo ciego real, RF-06-04); se ocultan en CashSessionResource y solo se revelan tras el cierre. El esperado se calcula en el servidor tras recibir el conteo (responsabilidad del Resource, no del Service).", "cash_writes": "Todo movimiento/cierre pasa por CashService (transaccional + lockForUpdate). cash_movements es append-only (Immutable): cualquier UPDATE/DELETE → IMMUTABLE_RECORD (403). No existen endpoints de edición/borrado de movimientos.", "expected_is_cash_only": "H-52. expected = opening_amount + Σ(ingresos efectivo) − Σ(egresos efectivo). Solo payment_method='efectivo'; transferencia/tarjeta entran al libro pero NO a la gaveta. fondo_inicial se excluye del sumatorio (ya está en opening_amount).", "dual_currency": "RF-06 doble moneda NIO/USD. Moneda base = NIO (tasa 1, implícita, no almacenada). Cada operación en efectivo conserva: moneda, importe nativo (amount), tasa snapshot congelada al instante (exchange_rate = NIO por 1 unidad de la moneda) y equivalente NIO (base_amount, columna generada STORED = amount×exchange_rate). La TASA la congela el servidor (ExchangeRateService); NUNCA se envía en el request. Una operación en USD sin tasa vigente → 422 EXCHANGE_RATE_MISSING. Invariante de motor: NIO exige exchange_rate=1. Reconciliación POR MONEDA: esperado/contado/diferencia se calculan separadamente para NIO y USD (columnas espejo *_usd + difference_usd generada); una diferencia en NIO O en USD marca la sesión 'descuadrada' y despacha 'descuadre_caja', AUNQUE el consolidado NIO coincida. El consolidado NIO (consolidated_nio en CashSessionResource) es INFORMATIVO: usa session_exchange_rate (tasa de referencia snapshot al cierre) y nunca sustituye ni oculta la reconciliación por moneda. Compatibilidad histórica: toda operación/sesión previa es NIO tasa 1 (base_amount=amount); los payloads sin `currency` siguen comportándose idénticamente.", "discrepancy_persists": "H-50/D-20. Un cierre descuadrado COMMITEA la sesión 'descuadrada' + conteo + desglose + anomalía y lanza 422 DESPUÉS del commit. El 422 transporta la sesión con la diferencia visible. Sin rollback.", "type_category_coherence": "H-51/D-22. type⇄category se valida en StoreCashMovementRequest vía forcedType() del enum CashMovementCategory (venta/cobro_credito/fondo_inicial⇒ingreso; retiro/egreso_autorizado⇒egreso; ajuste libre). Incoherencia → 422. Backstop no-HTTP en CashMovement::creating.", "authorizer_is_admin": "H-53/D-23. category='egreso_autorizado' exige authorized_by; el FormRequest valida que exista y sea del tenant (required_if), y CashService valida que tenga rango ROL-02 (lectura del rol). No-admin → CashAuthorizationException (422). El CHECK chk_cash_movement_egreso_auth solo garantiza que no sea nulo.", "denominations_sum_exact": "H-56. counted_denominations es obligatorio (min:1) y Σ(value×qty) debe igualar EXACTAMENTE counted_amount; si no, 422 (CloseCashSessionRequest::after). Se persiste como evidencia JSON del arqueo.", "dual_open_locks": "H-54. Doble apertura bloqueada por el MOTOR con columnas generadas VIRTUALES + UNIQUE: open_register_lock (una sesión abierta por caja) y open_user_lock (una sesión abierta por usuario). CashService traduce el 1062 a 409 distinguiendo el candado: CASH_REGISTER_BUSY (caja ocupada) o CASH_USER_BUSY (usuario ocupado).", "generated_difference": "cash_sessions.difference es columna generada STORED (counted_amount − expected_amount); el motor la calcula al guardar. El estado (cerrada/descuadrada) se decide con bcmath sobre esa diferencia.", "anomaly_live": "D-24 RECONCILIADO v2.1: el descuadre YA despacha la anomalía 'descuadre_caja' vía AnomalyService::registrarSilencioso (MOD-11 implementado, inyectado en CashService). Se registra tras el commit, es idempotente (uniq_active_anomaly) y nunca rompe el cierre. Ya NO es un hook inerte.", "index_contract": "Los listados (cash-registers/cash-sessions/cash-movements) usan Index*Request: validan filtros (enums, fechas, ids del tenant), acotan `sort` a un allowlist y `per_page` (default 25, máx 100), y devuelven envelope paginado {data, links, meta}.", "role_scope": "Alcance por rol (propiedad). ROL-01/ROL-02: visibilidad y operación administrativa sobre las sesiones del negocio. ROL-03: SOLO sobre las sesiones que él mismo abrió (opened_by=self). Aplica a: listado de sesiones (el filtro opened_by NO amplía el alcance de ROL-03), show y /movements (CashSessionPolicy::view → 403 sobre sesión ajena), registro de movimiento y cierre (403 sobre sesión ajena; el arqueo ciego se conserva para todos los roles).", "branch_scope": "Aislamiento de sucursal en cajas operativas. ROL-03 solo lista y abre cajas ACTIVAS de su propio branch_id; abrir una caja de otra sucursal —o sin sucursal asignada— se rechaza con 403 (AuthorizationException, sin persistir sesión). ROL-01/ROL-02 administran las cajas del negocio conforme a sus Policies.", "error_envelope": "Las excepciones de caja se auto-renderizan (self-render) con la clave JSON `error` (NoActiveCashSession, CashSessionConflict, UnreconciledCashClosing, ImmutableRecord). El identificador es el string documentado como `code` en cada error de abajo. CashAuthorizationException se mapea en bootstrap (solo `message`, 422).", "credit_note": "credit_limit se registra en MOD-05; los cobros/ventas a crédito que generan movimientos de caja se orquestan en MOD-07/08." }, "resources": { 

"exchange_rates": [ 

{ "method": "GET", "path": "/exchange-rates", "roles": ["ROL-01", "ROL-02"], "query": { "currency": "enum[NIO,USD]|opt (filtra por moneda)" }, "note": "RF-06 doble moneda. Historial de vigencias del tipo de cambio (más reciente primero), ordenado por moneda y effective_from desc. Historial INMUTABLE versionado por vigencia (append-only): no hay update ni delete; una corrección se expresa registrando una vigencia nueva. La moneda base (NIO) NO se almacena (tasa 1 implícita). Autoriza ExchangeRatePolicy::viewAny (ROL-02+). Aislamiento por negocio (BusinessScope).", "response_200": "Collection<ExchangeRateResource>", "errors": [ { "http": 403, "key": "message", "when": "Rol inferior a ROL-02 consulta el tipo de cambio." } ] }, 

{ "method": "POST", "path": "/exchange-rates", "roles": ["ROL-01", "ROL-02"], "request": { "currency": "enum[USD]|required (NUNCA la base NIO)", "rate": "decimal(14,6)|>0|required (NIO por 1 unidad de la moneda)", "effective_from": "datetime|required (vigencia desde)" }, "note": "Registra una nueva vigencia (ROL-01/ROL-02). INSERTA una fila; las anteriores se preservan intactas (inmutabilidad histórica). created_by = auth (no-repudio). La moneda base (NIO) se rechaza (su tasa es 1). Autoriza ExchangeRatePolicy::create.", "response_201": "ExchangeRateResource { id, currency, rate, effective_from, created_by, created_by_name, created_at }", "errors": [ { "http": 403, "key": "message", "when": "Rol inferior a ROL-02 intenta registrar una tasa." }, { "http": 422, "field": "currency", "when": "currency = NIO (moneda base: no admite tipo de cambio) o moneda no admitida." }, { "http": 422, "field": "rate", "when": "rate <= 0 o con más de 6 decimales." } ] } 

], 

"cash_registers": [ 

{ "method": "GET", "path": "/cash-registers", "roles": ["ROL-03", "ROL-02", "ROL-01"], 

"query": { "branch_id": "int|opt (solo ROL-01/02)", "is_active": "bool|opt (solo ROL-01/02)", "sort": "enum[name,is_active,created_at]|opt", "page": "int|opt", "per_page": "int|opt|max:100|default:25" }, 

"note": "Alcance por rol (ver conventions.branch_scope): ROL-01/ROL-02 listan todas las cajas del negocio con filtros branch_id/is_active; ROL-03 recibe SOLO las cajas ACTIVAS de su propio branch_id (sin sucursal asignada → colección vacía), ignorando esos filtros.", 

"response_200": "Paginated<CashRegisterResource>" }, 

{ "method": "POST", "path": "/cash-registers", "roles": ["ROL-02"], 

"request": { "branch_id": "int|required|same_tenant", "name": "string(100)|required", "is_active": "bool|opt" }, 

"response_201": "CashRegisterResource", 

"errors": [ { "http": 422, "when": "Nombre duplicado en (business, branch, name)." } ] }, 

{ "method": "PUT", "path": "/cash-registers/{id}", "roles": ["ROL-02"], "response_200": "CashRegisterResource" }, 

{ "method": "DELETE", "path": "/cash-registers/{id}", "roles": ["ROL-02"], "response_204": "Softdelete", "errors": [ { "code": "CASH_REGISTER_OPEN_SESSION", "http": 409, "key": "error", "when": "La caja tiene una sesión abierta vinculada: no puede eliminarse hasta cerrarla." } ], "note_update": "PUT con is_active=false sobre una caja con sesión abierta → 409 CASH_REGISTER_OPEN_SESSION (no se puede desactivar)." } 

], 

"cash_register_assignments": [ 

{ "method": "GET", "path": "/cash-register-assignments", "roles": ["ROL-03", "ROL-02", "ROL-01"], "query": { "cash_register_id": "int|opt", "user_id": "int|opt (solo ROL-01/02)", "active": "bool|opt", "sort": "enum[assigned_at,ended_at,created_at]|opt", "page": "int|opt", "per_page": "int|opt|max:100|default:25" }, "note": "RF-06 asignación Caja–Cajero (historial temporal). ROL-01/ROL-02 ven todas las del negocio con filtros; ROL-03 SOLO las suyas (user_id forzado a sí mismo; el filtro no amplía su alcance). Una asignación activa = ended_at NULL.", "response_200": "Paginated<CashRegisterAssignmentResource { id, cash_register_id, user_id, branch_id, assigned_by, assigned_at, ended_at, ended_by, active }>" }, 

{ "method": "POST", "path": "/cash-register-assignments", "roles": ["ROL-01", "ROL-02"], "request": { "cash_register_id": "int|required|same_tenant", "user_id": "int|required|same_tenant" }, "note": "Asigna un cajero a una caja. Invariantes (Service): misma sucursal, usuario ROL-03 con perfil cajero, caja ≤ 1 cajero activo, cajero ≤ 1 caja activa (candados de motor). ESTRICTO: si la caja o el cajero ya tienen asignación activa → 409 (reasignar = finalizar antes). No se asigna con una sesión abierta vinculada. business_id/assigned_by/fechas del servidor.", "response_201": "CashRegisterAssignmentResource", "errors": [ { "http": 403, "when": "Rol inferior a ROL-02." }, { "http": 422, "field": "user_id", "when": "El usuario no es ROL-03 con perfil cajero, o la caja y el cajero no comparten sucursal." }, { "code": "CASH_REGISTER_ALREADY_ASSIGNED", "http": 409, "key": "error", "when": "La caja ya tiene un cajero activo." }, { "code": "CASHIER_ALREADY_ASSIGNED", "http": 409, "key": "error", "when": "El cajero ya tiene una caja activa." }, { "code": "CASH_ASSIGNMENT_OPEN_SESSION", "http": 409, "key": "error", "when": "Hay una sesión de caja abierta vinculada." } ] }, 

{ "method": "DELETE", "path": "/cash-register-assignments/{id}", "roles": ["ROL-01", "ROL-02"], "note": "Finaliza (cierra la vigencia: ended_at/ended_by). Historial append-only (no se borra). Idempotente si ya estaba finalizada.", "response_200": "CashRegisterAssignmentResource { active:false }", "errors": [ { "http": 404, "when": "La asignación pertenece a otro negocio (BusinessScope)." }, { "code": "CASH_ASSIGNMENT_OPEN_SESSION", "http": 409, "key": "error", "when": "La caja tiene una sesión abierta: no se finaliza hasta cerrarla." } ] } 

], 

"cash_sessions": [ 

{ "method": "GET", "path": "/cash-sessions", "roles": ["ROL-03", "ROL-02", "ROL-01"], 

"query": { "cash_register_id": "int|opt", "opened_by": "int|opt", "branch_id": "int|opt", "status": "enum[abierta,cerrada,descuadrada]|opt", "from": "date|opt", "to": "date|opt", "sort": "enum[opened_at,closed_at,status,created_at]|opt", "direction": "enum[asc,desc]|opt", "page": "int|opt", "per_page": "int|opt|max:100|default:25" }, 

"note": "Alcance por rol (ver conventions.role_scope). ROL-01/ROL-02: todas las sesiones del negocio, con filtro opened_by opcional. ROL-03: EXCLUSIVAMENTE sus propias sesiones (opened_by forzado a sí mismo); el parámetro opened_by NO amplía su alcance. Filtros cash_register_id/from/to/sort/paginación validados por IndexCashSessionRequest. FILTRO branch_id (cash_sessions no tiene branch_id: se resuelve por la caja): ROL-01/ROL-02 filtran por cualquier sucursal del negocio; ROL-03 conserva su alcance (un branch ajeno del mismo negocio solo estrecha a vacío, nunca revela sesiones de otros). Una sucursal de OTRO negocio → 404 (sin filtrar datos).", 

"response_200": "Paginated<CashSessionResource>" }, 

{ "method": "POST", "path": "/cash-sessions", "roles": ["ROL-03", "ROL-02", "ROL-01"], 

"request": { "cash_register_id": "int|required|same_tenant|is_active", "opening_amount": "decimal(14,2)|>=0|required (fondo NIO)", "opening_amount_usd": "decimal(14,2)|>=0|opt|default:0 (fondo USD, doble moneda)" }, 

"note": "RF-06-03. Única ruta de apertura (no existe /cash-sessions/open). opened_by = auth. Nace 'abierta'. Autoriza CashSessionPolicy::create (ROL-03+). Aislamiento de sucursal (ver conventions.branch_scope): ROL-03 solo abre cajas ACTIVAS de su propio branch_id. Candados de motor: una sesión abierta por caja Y una por usuario.", 

"response_201": "CashSessionResource", 

"errors": [ 

{ "http": 403, "key": "message", "when": "ROL-03 intenta abrir una caja de otra sucursal, o no tiene sucursal asignada (AuthorizationException). No se persiste sesión." }, 

{ "code": "CASH_REGISTER_BUSY", "http": 409, "key": "error", "when": "La caja ya tiene una sesión abierta (open_register_lock)." }, 

{ "code": "CASH_USER_BUSY", "http": 409, "key": "error", "when": "El usuario ya tiene una sesión abierta en otra caja (open_user_lock)." } 

] }, 

{ "method": "GET", "path": "/cash-sessions/{id}", "roles": ["ROL-02", "ROL-03"], 

"note": "Propiedad (ver conventions.role_scope): ROL-01/ROL-02 ven cualquier sesión del negocio; ROL-03 solo la propia (sesión ajena → 403). Si status='abierta', el recurso OCULTA expected_amount y difference (arqueo ciego) para TODOS los roles. Cruce de negocio → 404 (BusinessScope).", 

"response_200": "CashSessionResource", 

"errors": [ { "http": 403, "key": "message", "when": "ROL-03 consulta una sesión que no abrió (CashSessionPolicy::view)." } ], 

"resource_fields": { 

"opening_amount": "string (NIO)", "counted_amount": "string|null (NIO)", 

"expected_amount": "string|null (NIO; oculto mientras 'abierta')", 

"difference": "string|null (NIO; oculto mientras 'abierta'; columna generada tras cierre)", 

"counted_denominations": "array|null (NIO)", "status": "string", 

"opening_amount_usd": "string (USD; doble moneda)", "counted_amount_usd": "string|null (USD)", "counted_denominations_usd": "array|null (USD)", 

"expected_amount_usd": "string|null (USD; oculto mientras 'abierta')", "difference_usd": "string|null (USD; oculto mientras 'abierta'; columna generada)", 

"session_exchange_rate": "string|null (tasa de referencia snapshot al cierre, para el consolidado)", 

"consolidated_nio": "{ reference_rate, expected_amount, counted_amount, difference }|null — INFORMATIVO (solo tras cierre y con tasa de referencia); expresa el leg USD en NIO. NUNCA sustituye la reconciliación por moneda ni oculta un descuadre individual." 

} }, 

{ "method": "POST", "path": "/cash-sessions/{id}/close", "roles": ["ROL-03", "ROL-02", "ROL-01"], 

"request": { "counted_amount": "decimal(14,2)|>=0|required (NIO)", 

"counted_denominations": [ { "value": "decimal(14,2)|required", "qty": "int|>=0|required" } ], 

"counted_amount_usd": "decimal(14,2)|>=0|opt|default:0 (USD; doble moneda)", 

"counted_denominations_usd": "[ { value, qty } ]|opt — si se envía, Σ(value×qty) debe igualar counted_amount_usd", 

"closing_notes": "string|max:500 — opcional en cierre ordinario; OBLIGATORIO (min:3, contenido significativo) en cierre administrativo por contingencia" }, 

"dual_currency_close": "RF-06 doble moneda. El esperado/contado/diferencia se reconcilian POR MONEDA (NIO y USD) en importe nativo. La sesión queda 'descuadrada' si difference (NIO) O difference_usd es ≠ 0, aunque la otra moneda cuadre y aunque el consolidado NIO coincida. La anomalía 'descuadre_caja' se despacha por cualquier descuadre de moneda; su contexto lleva difference_nio, difference_usd y las monedas afectadas, y la magnitud para el umbral es el leg de mayor valor absoluto en NIO. session_exchange_rate se congela al cierre si hay dimensión USD (tasa de referencia del consolidado).", 

"close_modes": { "ordinario": "auth.id === cash_session.opened_by: lo cierra quien lo abrió; closing_notes OPCIONAL.", "contingencia_administrativa": "auth.id !== opened_by: solo un ROL-01/ROL-02 del mismo negocio (CashSessionPolicy::close); un ROL-03 ajeno NUNCA (403). closing_notes OBLIGATORIO (string, min:3, max:500). No omite ninguna regla: exige counted_denominations, suma exacta, lockForUpdate, arqueo ciego, cálculo del esperado y persistencia de anomalía si hay descuadre. Validado en CloseCashSessionRequest y con backstop en CashService (422 sobre closing_notes también en vías no-HTTP)." }, 

"note": "Arqueo ciego (RF-06-04/05). `closed_by` lo deriva SIEMPRE el servidor de Auth::id(); jamás se acepta del request. Persiste el conteo, calcula el esperado en efectivo, el motor deriva difference. Sin descuadre → 'cerrada'. Con descuadre → 'descuadrada' + anomalía (MOD-11) tras el commit. Ningún cierre rechazado modifica la sesión (sin conteo/closed_at/closed_by).", 

"response_200": "CashSessionResource { status:'cerrada', expected_amount, difference:'0.00', closed_by }", "errors": [ 

{ "http": 403, "key": "message", "when": "Un ROL-03 distinto del que abrió intenta cerrar (aunque envíe motivo). La sesión no se modifica." }, 

{ "code": "NO_ACTIVE_CASH_SESSION", "http": 409, "key": "error", "when": "La sesión no está abierta." }, 

{ "http": 422, "field": "closing_notes", "when": "Cierre administrativo (auth.id !== opened_by) SIN motivo significativo (ausente o < 3 caracteres). La sesión permanece abierta, sin conteo/closed_at/closed_by." }, 

{ "http": 422, "when": "counted_denominations vacío o su suma value×qty no iguala counted_amount (errores de validación en `errors.counted_denominations`)." }, 

{ "code": "UNRECONCILED_CASH_CLOSING", "http": 422, "key": "error", "response": "{ error:'UNRECONCILED_CASH_CLOSING', message, data: CashSessionResource }", "when": "difference ≠ 0. La sesión YA quedó 'descuadrada' (evidencia persistida, sin rollback). RECONCILIADO v2.1: el string era 'CASH_CLOSING_UNRECONCILED' en el self-render (typo); ahora coincide con el contrato. El Resource revela expected_amount y difference." } 

] }, 

{ "method": "GET", "path": "/cash-sessions/{id}/movements", "roles": ["ROL-02", "ROL-03"], "query": { "payment_method": "enum[efectivo,transferencia,tarjeta]|opt", "page": "int|opt", "per_page": "int|opt|default:25" }, "note": "Propiedad (CashSessionPolicy::view): ROL-03 solo los movimientos de su propia sesión (sesión ajena → 403); ROL-01/ROL-02 cualquiera del negocio.", "response_200": "Paginated<CashMovementResource>", "errors": [ { "http": 403, "key": "message", "when": "ROL-03 consulta los movimientos de una sesión que no abrió." } ] }, 

{ "method": "POST", "path": "/cash-sessions/{id}/counts", "roles": ["ROL-03 (perfil cajero)", "ROL-02", "ROL-01"], 

"request": { "counted_amount": "decimal(14,2)|>=0|required (NIO)", "counted_denominations": [ { "value": "decimal(14,2)|>0|required", "qty": "int|>=0|required" } ], "counted_amount_usd": "decimal(14,2)|>=0|opt|default:0 (USD; doble moneda)", "counted_denominations_usd": "[ { value, qty } ]|opt — si se envía, Σ(value×qty) debe igualar counted_amount_usd" }, 

"note": "RF-06-04 · ARQUEO CIEGO INDEPENDIENTE durante la sesión ABIERTA, SIN cerrarla. Doble moneda: el conteo revela esperado/diferencia POR MONEDA (NIO y USD; columnas espejo *_usd + difference_usd generada). El esperado NO se envía ni se conoce al registrar (arqueo ciego): el servidor lo calcula en ese instante (mismo criterio que el cierre: fondo + Σ ingresos efectivo − Σ egresos efectivo) y la respuesta lo REVELA junto con la diferencia (columna generada counted−expected). Historial APPEND-ONLY: se admiten múltiples arqueos; cada uno congela evidencia inmutable (denominaciones, user_id, counted_at). NO cambia el estado de la sesión y NO genera anomalía (DECISIÓN DE DOMINIO: solo el CIERRE formal marca 'descuadrada' y despacha 'descuadre_caja'; los arqueos intermedios son evidencia, no reconciliación). Autoriza CashSessionPolicy::count: ROL-03 solo SU sesión y con perfil cajero (reutiliza la capacidad caja.movimiento.crear, sin permisos nuevos); ROL-01/ROL-02 cualquier sesión del negocio. business_id/user_id de la sesión; lockForUpdate sobre la sesión; aritmética bcmath.", 

"response_201": "CashCountResource { id, cash_session_id, user_id, counted_amount, expected_amount (REVELADO), difference (REVELADO), counted_denominations, counted_amount_usd, expected_amount_usd (REVELADO), difference_usd (REVELADO), counted_denominations_usd, counted_at }", 

"errors": [ 

{ "http": 403, "key": "message", "when": "ROL-03 arquea una sesión que no abrió, o sin perfil cajero (CashSessionPolicy::count)." }, 

{ "code": "NO_ACTIVE_CASH_SESSION", "http": 409, "key": "error", "when": "La sesión no está abierta: no se registra arqueo." }, 

{ "http": 422, "when": "counted_denominations vacío o su suma value×qty no iguala counted_amount (errores en `errors.counted_denominations`)." }, 

{ "http": 404, "when": "La sesión pertenece a otro negocio (BusinessScope)." } 

] }, 

{ "method": "GET", "path": "/cash-sessions/{id}/counts", "roles": ["ROL-02", "ROL-03 (perfil cajero)"], "note": "Historial de arqueos de la sesión (más reciente primero). Mismo alcance que ver la sesión (CashSessionPolicy::view): ROL-03 solo la propia; ROL-01/ROL-02 cualquiera del negocio. Cada arqueo es evidencia completada: REVELA expected_amount y difference.", "response_200": "Collection<CashCountResource>", "errors": [ { "http": 403, "key": "message", "when": "ROL-03 consulta los arqueos de una sesión que no abrió." } ] } 

], 

"cash_movements": [ 

{ "method": "GET", "path": "/cash-movements", "roles": ["ROL-02", "ROL-01"], 

"query": { "cash_session_id": "int|opt", "type": "enum[ingreso,egreso]|opt", "category": "enum[venta,egreso_autorizado,retiro,ajuste,fondo_inicial,cobro_credito,vuelto]|opt", "payment_method": "enum[efectivo,transferencia,tarjeta]|opt" }, 

"response_200": "Paginated<CashMovementResource { ..., currency, exchange_rate, base_amount }>", 

"note": "Append-only. Cualquier UPDATE/DELETE → ImmutableRecordException (403). Doble moneda: cada movimiento expone currency (NIO|USD), exchange_rate (tasa snapshot) y base_amount (equivalente NIO generado)." }, 

{ "method": "POST", "path": "/cash-movements", "roles": ["ROL-03"], 

"request": { "cash_session_id": "int|required|same_tenant", "type": "enum[ingreso,egreso]| required", 

"category": "enum[venta,egreso_autorizado,retiro,ajuste,fondo_inicial,cobro_credito,vuelto]|required", 

"payment_method": "enum[efectivo,transferencia,tarjeta]|required", "amount": "decimal(14,2)| >0|required (importe NATIVO)", 

"currency": "enum[NIO,USD]|opt|default:NIO (doble moneda; la tasa snapshot la congela el servidor, NO se envía)", 

"authorized_by": "int|nullable|same_tenant", "description": "string(255)|nullable" }, 

"note": "Exige sesión ABIERTA (ERR-06). Propiedad (ver conventions.role_scope): ROL-03 solo registra en SU sesión abierta (sesión ajena → 403, sin persistir); ROL-01/ROL-02 operan sobre cualquier sesión del negocio. type debe ser coherente con category (forcedType). category='egreso_autorizado' exige authorized_by que sea un usuario ACTIVO ROL-02 del mismo negocio. Los movimientos 'venta' normalmente los crea el flujo de facturación (MOD-07), no este endpoint directo.", 

"response_201": "CashMovementResource", 

"errors": [ 

{ "http": 403, "key": "message", "when": "ROL-03 intenta registrar un movimiento en una sesión que no abrió (AuthorizationException). No se persiste (rollback)." }, 

{ "code": "NO_ACTIVE_CASH_SESSION", "http": 409, "key": "error", "when": "La sesión indicada no está abierta (verificada bajo lock, RF-06-02)." }, 

{ "http": 422, "when": "type incoherente con category (forcedType), monto <= 0, o egreso_autorizado sin authorized_by (required_if), o authorized_by de otro negocio/eliminado (tenantExists). Errores de validación estándar." }, 

{ "http": 422, "when": "authorized_by existe y es del tenant pero está INACTIVO o NO tiene rango ROL-02 (CashAuthorizationException; solo `message`). No se persiste (rollback)." }, 

{ "code": "EXCHANGE_RATE_MISSING", "http": 422, "key": "code", "when": "currency=USD sin tipo de cambio vigente para el negocio al instante de la operación (ExchangeRateMissingException). No se persiste (rollback)." }, 

{ "code": "IMMUTABLE_RECORD", "http": 403, "key": "error", "when": "Intento de editar/borrar un movimiento (append-only, trait Immutable). No hay endpoints para ello." } 

] } 

] 

}, 

"deferred": { 

"cash_movements.sale_id": "YA CABLEADO: sale() y CashService::registrarMovimientoVenta crean el movimiento 'venta' (efectivo) desde la facturación (MOD-07). La columna/relación ya existe.", 

"anomaly_dispatch": "YA CABLEADO (MOD-11 implementado): el descuadre despacha 'descuadre_caja' vía AnomalyService::registrarSilencioso tras el commit del cierre. Ver conventions.anomaly_live. Ya no es deferred.", 

"denomination_table": "counted_denominations como JSON en MVP; tabla dedicada cash_count_denominations → Fase 2 si se requiere analítica de billetes/monedas." 

} } 


# **MOD-07 – Ventas, Facturación e Inmutabilidad** 

{ 

"module": "MOD-07", 

"base_url": "/api/v1", 

"version": "2.1-canonical", 

"auth": { "scheme": "Cookie/sesión (Sanctum SPA)", "guard": "web", "tenant_source": "session.user.business_id (BusinessScope global)", "note": "NO es Bearer. Toda la API usa sesión SPA de Sanctum; el tenant se resuelve del usuario autenticado, jamás del request." }, 

"conventions": { 

"money": "Montos string decimal escala 2; costos 4; cantidades 3; TASAS fraccional escala 6 (bcmath, nunca float).", 

"atomic_all_or_nothing": "H-58/D-27. La facturación es TODO-O-NADA real dentro de una única DB::transaction: cualquier fallo (pago incompleto, stock insuficiente, sin sesión de caja, configuración fiscal ausente) revierte TODO —no persiste factura, folio consumido, reserva, pago ni movimiento de caja—.", 

"folio_fiscal": "H-63. Folio secuencial único por negocio, generado server-side vía document_sequences + lockForUpdate DENTRO de la tx. El cliente NUNCA envía folio. FOLIO_CONFLICT (409) es red de seguridad ante el UQ(business_id, folio).", 

"sequences_coexistence": "D-25. Coexisten dos contadores de dominios distintos: document_sequences (folios FISCALES F-/NC-, enum document_type) y sequences (folios INTERNOS no fiscales: V- ventas, TR-/OC- inventario/compras). No se consolidan: locks y UNIQUE independientes.", 

"immutability": "H-66/D-29. Núcleo fiscal de la factura emitida INMUTABLE (folio, subtotal, IVA, total, descuento, cliente, sucursal, líneas) vía guarda de modelo; solo paid_amount/payment_status/status/voided_* son mutables. PUT → 403 IMMUTABLE_INVOICE. No hay borrado físico; se anula (BR-04).", 

"reservation": "H-60/D-26. Facturar COMPROMETE stock (reserved_quantity+) SIN tocar quantity ni el kardex (InventoryService::reservar/liberarReserva). El descuento físico real es el retiro (MOD-09). Los compuestos reservan/liberan sus insumos vía recipe_snapshot congelado (H-59).", 

"payment_integrity": "H-64/H-65. Contado exige Σ pagos = total derivado por el servidor (ERR-07/INCOMPLETE_PAYMENT). Todo pago genera invoice_payment; SOLO el efectivo genera además cash_movement 'venta' vía CashService y exige cash_session_id de una sesión ABIERTA (MOD-06).", 

"fiscal_model": "MOD-07 v2.1. Base fiscal NORMALIZADA, multitenant y extensible. Cada producto tiene una CLASE fiscal explícita products.tax_class ∈ {standard, reduced, zero_rated, exempt} (ya NO un booleano). La TASA proviene de una regla persistida del negocio (tax_rules) con ámbito general (branch_id NULL) o por sucursal. Servicio de dominio único TaxResolver; prioridad determinista: (1) regla activa de la sucursal, (2) regla activa general del negocio, (3) rechazo controlado FISCAL_CONFIG_MISSING (422). Tasa cero y exento no requieren regla (0 por definición). Alcance explícito (delimitación funcional, no deuda): UNA tasa resuelta por línea, configurable por clase y ámbito; NO es motor de impuestos compuestos/cascada. Prohibido el 0.15 hardcodeado.", 

"fiscal_condition": "Se DIFERENCIA exento de tasa cero: exento (exempt) está FUERA del gravamen → base gravable 0; tasa cero (zero_rated) está gravado a 0 % → la base SÍ integra el gravamen. Ambos producen impuesto 0. condition ∈ {gravado, tasa_cero, exento}.", 

"fiscal_snapshot": "Al agregar la línea se CONGELA la fiscalidad además de descripción/precio/costo/receta: tax_class, fiscal_condition, tax_rate, taxable_base (importe de línea tras descuento de línea), tax_amount y tax_rule_id (auditoría). La factura SUMA los impuestos congelados; un cambio posterior de producto, clase, sucursal o tasa no altera una venta confirmada ni una factura emitida. MOD-10 usará estas cifras congeladas sin consultar la configuración vigente.", 

"rounding_policy": "Cálculo por línea: base tras descuento de línea → impuesto = round(base × tasa, 2) mitad-hacia-arriba determinista (App\\Support\\Money, bcmath; se sustituye la TRUNCACIÓN previa de bcmul). Agregación «redondear por línea y sumar». IVA de factura = Σ tax_amount de líneas. Descuento GLOBAL de factura se aplica DESPUÉS del impuesto (H-68): total = subtotal + IVA − descuento (piso 0).", 

"server_computed_only": "El cliente identifica solo productos, cantidades y descuentos permitidos. tax_rate, taxable_base, tax_amount y los totales los RESUELVE el servidor; cualquier valor fiscal enviado en el request se IGNORA.", 

"is_taxable_derived": "COMPATIBILIDAD: products.is_taxable dejó de existir como columna (fuente fiscal única = tax_class). Se reexpone DERIVADO en respuestas (true salvo clase exenta). En sale_items, is_taxable permanece CONGELADO por línea (lo consumen MOD-09/10). Como ALIAS DE ENTRADA DEPRECADO en POST/PUT de productos: si no llega tax_class pero llega is_taxable, se traduce (true→standard, false→exempt); si llegan ambos y se contradicen (is_taxable=false ⇎ tax_class=exempt) → 422; is_taxable NO se persiste.", 

"business_tax_rate_alias": "business.tax_rate NO es una segunda fuente fiscal: es un ALIAS de la regla ESTÁNDAR GENERAL. PUT /business con tax_rate se TRADUCE transaccionalmente a esa regla (versionada), de modo que editarla SÍ afecta la facturación; la columna queda como espejo de compatibilidad y BusinessResource la deriva de la regla vigente. La tasa se siembra al crear el negocio (semilla de la regla estándar).", 

"tax_rule_versioning": "Las tasas son VERSIONADAS. Un cambio de tasa (PUT /tax-rules/{id} rate, o vía business.tax_rate) NO muta la fila: desactiva la anterior y crea una NUEVA activa (misma clase/ámbito) en una transacción; las líneas históricas conservan su versión por sale_items.tax_rule_id. Devuelve 200 (es actualización aunque cree fila). DELETE = baja lógica (is_active=false). Colisiones de ámbito (dos activas) → 1062 traducido a 422, nunca 500 (candado uniq_active_tax_rule_scope sobre columna generada, válido también con branch_id NULL).", 

"void_owner_only": "H-67. Anular es potestad exclusiva de ROL-01, con motivo, auditoría (voided_by/at) y liberación de reservas, conservando folio. Reversión de CxC (MOD-08) y reembolso (MOD-10) se orquestan en esos módulos." 

}, 

"resources": { 

"sales": [ 

{ "method": "GET", "path": "/sales", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil facturador: ventas.ver — índice y detalle acotados a su sucursal)"], 

"query": { "status": "enum[abierta,confirmada,facturada,anulada]|opt", "customer_id": "int|opt", "branch_id": "int|opt", "from": "date|opt", "to": "date|opt" }, 

"response_200": "Paginated<SaleResource>" }, 

{ "method": "POST", "path": "/sales", "roles": ["ROL-03"], 

"request": { "branch_id": "int|required|same_tenant", "customer_id": "int|required|same_tenant", "table_reference": "string(50)|nullable", "notes": "string(500)|nullable" }, 

"note": "Abre la venta (carrito). Nace 'abierta'. code auto-generado.", "response_201": "SaleResource" }, 

{ "method": "GET", "path": "/sales/{id}", "roles": ["ROL-03"], "response_200": "SaleResource (incluye items[])" }, 

{ "method": "POST", "path": "/sales/{id}/items", "roles": ["ROL-03"], 

"request": { "product_id": "int|required|same_tenant", "quantity": "decimal(14,3)|>0", "discount_amount": "decimal(14,2)|>=0|opt" }, 

"note": "Solo en 'abierta'. Congela description/unit_price/unit_cost, recipe_snapshot (compuestos) y la FOTOGRAFÍA FISCAL resuelta por el servidor (ver conventions.fiscal_snapshot). line_total y subtotal derivados. Cualquier tax_rate/tax_amount/taxable_base enviado se IGNORA (server_computed_only).", 

"response_201": "SaleItemResource { ..., is_taxable(derivado), tax_class, fiscal_condition, tax_rate, taxable_base, tax_amount, tax_rule_id }", 

"errors": [ { "http": 409, "when": "La venta no está 'abierta'." }, { "code": "FISCAL_CONFIG_MISSING", "http": 422, "when": "No hay regla fiscal activa (ni de sucursal ni general) para una clase gravada del producto. Rechazo controlado; la línea NO se persiste." } ] }, 

{ "method": "DELETE", "path": "/sales/{id}/items/{item_id}", "roles": ["ROL-03"], "note": "Solo en 'abierta'. Recalcula subtotal.", "response_204": null }, 

{ "method": "POST", "path": "/sales/{id}/confirm", "roles": ["ROL-03"], 

"note": "abierta → confirmada. Exige al menos un ítem. Habilita la facturación.", 

"response_200": "SaleResource", "errors": [ { "http": 422, "when": "Venta vacía o no 'abierta'." } ] } 

], 

"invoices": [ 

{ "method": "GET", "path": "/invoices", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil cajero/facturador/despachador: facturas.ver — acotado a su sucursal)"], 

"query": { "status": "enum[emitida,anulada]|opt", "payment_type": "enum[contado,credito]|opt", "payment_status": "enum[pagada,parcial,pendiente]|opt", "customer_id": "int|opt", "folio": "string| opt", "from": "date|opt", "to": "date|opt" }, 

"response_200": "Paginated<InvoiceResource>" }, 

- { "method": "POST", "path": "/invoices", "roles": ["ROL-03"], 

"request": { 

"sale_ids": "int[]|required|same_tenant|confirmed (mismo cliente y sucursal)", 

"payment_type": "enum[contado,credito]|required", 

"cash_session_id": "int|nullable|same_tenant (obligatorio si hay pago en efectivo)", 

"discount_amount": "decimal(14,2)|>=0|opt|default:0", 

"payments": [ { "method": "enum[efectivo,transferencia,tarjeta]|required", "amount": "decimal(14,2)|>0 (importe NATIVO del leg)", "currency": "enum[NIO,USD]|opt|default:NIO (doble moneda; la tasa snapshot la congela el servidor)", "reference": "string(100)|nullable" } ], 

"change": "{ amount: decimal(14,2)|>0, currency: enum[NIO,USD]|opt|default:NIO }|opt — VUELTO (cambio). Solo contado con ≥1 pago en efectivo. El servidor congela su tasa snapshot y lo asienta como egreso de caja 'vuelto'." 

}, 

"note": "Emite la factura: folio secuencial, reserva de stock, invoice_payments y cash_movement 'venta' (efectivo). El IVA es la SUMA de los impuestos congelados por línea (NO se recalcula con business.tax_rate); subtotal, IVA y total los deriva el servidor. DOBLE MONEDA (RF-06): la factura se denomina en NIO; cada leg conserva moneda/importe nativo/tasa snapshot (congelada al emitir, nunca enviada)/equivalente NIO (base_amount). El total se salda por la SUMA de equivalentes NIO. VUELTO: lo ENTREGADO en efectivo puede exceder el total; el pago NETO = Σ(equivalente NIO entregado) − vuelto (equivalente NIO) debe igualar el total. El vuelto no puede exceder el efectivo recibido (→422) y se asienta como egreso de caja 'vuelto' (misma tasa snapshot, efecto real por moneda). paid_amount = neto aplicado (= total en contado). El efectivo de cualquier moneda genera su cash_movement 'venta' con la MISMA tasa snapshot que su invoice_payment. Crédito exige cliente NO genérico (la CxC se genera atómicamente, MOD-08). Todo dentro de una única transacción.", 

"response_201": "InvoiceResource { folio, subtotal, tax_amount, discount_amount, total, paid_amount, payment_status, sales[], payments[] { ..., currency, exchange_rate, base_amount } }", 

"errors": [ 

{ "code": "INCOMPLETE_PAYMENT", "http": 422, "key": "code", "when": "Contado cuyo pago NETO (Σ equivalente NIO entregado − vuelto) no cubre el 100% del total derivado. NADA se persiste." }, 

{ "code": "EXCHANGE_RATE_MISSING", "http": 422, "key": "code", "when": "Un leg (o el vuelto) en USD sin tipo de cambio vigente al emitir. Revierte toda la facturación." }, 

{ "http": 422, "field": "change", "when": "El vuelto excede el efectivo recibido, o se envía sin contado/sin pago en efectivo. Revierte toda la facturación." }, 

{ "code": "INSUFFICIENT_STOCK", "http": 409, "when": "No hay stock para reservar (simple o insumo de compuesto). Revierte toda la facturación." }, 

{ "code": "NO_ACTIVE_CASH_SESSION", "http": 409, "when": "Pago en efectivo sin sesión de caja activa." }, 

{ "code": "FISCAL_CONFIG_MISSING", "http": 422, "when": "Una línea no tiene fotografía fiscal congelada (venta previa a la función). Defensa; revierte la facturación." }, 

{ "code": "FOLIO_CONFLICT", "http": 409, "when": "Colisión de folio (red de seguridad; el lock la previene)." }, 

{ "http": 422, "when": "Ventas de distinto cliente/sucursal, no confirmadas, o crédito al genérico." } ] }, 

{ "method": "GET", "path": "/invoices/{id}", "roles": ["ROL-02", "ROL-03"], "response_200": "InvoiceResource (incluye sales[], payments[])" }, 

{ "method": "GET", "path": "/invoices/{id}/payments", "roles": ["ROL-02"], "response_200": "Collection<InvoicePaymentResource> (append-only)" }, 

{ "method": "PUT", "path": "/invoices/{id}", "roles": ["ROL-02"], 

"note": "NO permitido sobre el núcleo fiscal (ERR-07B). Cualquier intento → 403.", 

"errors": [ { "code": "IMMUTABLE_INVOICE", "http": 403, "when": "Edición de folio/subtotal/IVA/total de una factura emitida." } ] }, 

{ "method": "POST", "path": "/invoices/{id}/void", "roles": ["ROL-01"], 

"request": { "void_reason": "string(255)|required" }, 

"note": "Anulación (potestad ROL-01). Libera reservas, marca 'anulada' conservando folio y auditoría. Reversión de CxC (MOD-08) y reembolso (MOD-10) se orquestan en esos módulos.", 

"response_200": "InvoiceResource { status:'anulada', voided_by, voided_at, void_reason }", 

"errors": [ 

{ "http": 403, "when": "Rol != ROL-01." }, 

{ "http": 409, "when": "La factura no está 'emitida'." } 

] } ], 

"tax_rules": [ 

{ "method": "GET", "path": "/tax-rules", "roles": ["ROL-01"], "query": { "tax_class": "enum[standard,reduced,zero_rated,exempt]|opt", "branch_id": "int|opt", "is_active": "bool|opt", "sort": "enum[tax_class,rate,is_active,created_at]|opt", "page": "int|opt", "per_page": "int|opt|max:100|default:25" }, "note": "Administración fiscal (potestad exclusiva ROL-01). branch_id NULL = regla general del negocio; con valor = específica de sucursal. rate fraccional decimal(8,6).", "response_200": "Paginated<TaxRuleResource>" }, 

{ "method": "POST", "path": "/tax-rules", "roles": ["ROL-01"], "request": { "tax_class": "enum[standard,reduced,zero_rated,exempt]|required", "branch_id": "int|nullable|same_tenant", "rate": "decimal(8,6)|>=0|<=1|required" }, "response_201": "TaxRuleResource", "errors": [ { "http": 422, "field": "tax_class", "when": "Ya existe una regla ACTIVA para esa clase y ámbito (candado uniq_active_tax_rule_scope). Actualícela o desactívela antes de crear otra." } ] }, 

{ "method": "PUT|PATCH", "path": "/tax-rules/{id}", "roles": ["ROL-01"], "request": { "rate": "decimal(8,6)|>=0|<=1|opt", "is_active": "bool|opt" }, "note": "Solo tasa y estado (clase/ámbito inmutables: identidad de la regla). VERSIONADO (ver conventions.tax_rule_versioning): cambiar rate desactiva la fila anterior y crea una NUEVA activa; devuelve 200 con la versión resultante. is_active se aplica en sitio.", "response_200": "TaxRuleResource (versión activa resultante)", "errors": [ { "http": 422, "field": "tax_class", "when": "Reactivar (is_active=true) choca con otra regla activa del mismo ámbito (1062 → 422, nunca 500)." } ] }, 

{ "method": "DELETE", "path": "/tax-rules/{id}", "roles": ["ROL-01"], "note": "Baja LÓGICA fiscal: desactiva (is_active=false), NUNCA borra (las reglas usadas por documentos históricos preservan trazabilidad; los snapshots por línea ya conservan las cifras).", "response_200": "TaxRuleResource { is_active:false }" } 

] }, 

"deferred": { 

"accounts_receivable": "YA CABLEADO: la CxC de una factura a crédito se genera atómicamente en InvoiceService::facturar (ReceivableService::generarDesdeFactura) y la disponibilidad de cupo se valida ahí mismo (assertCreditAvailable, MOD-08 implementado). El detalle de CxC vive en MOD-08.", 

"void_reversal": "Reversión de CxC (RF-08-07) → MOD-08; reembolso de efectivo → MOD-10.", 

"dispatch": "Descuento físico real (retiro) + liberación parcial de reserva en anulación → MOD-09.", 

"document_sequences.credit_note": "Secuencia de folios de nota de crédito → consumida en MOD-10.", 

"mod10_returns_use_frozen_snapshot": "PENDIENTE MOD-10 (no se toca aquí): ReturnService debe calcular devoluciones y notas de crédito a partir de la FOTOGRAFÍA FISCAL congelada de las líneas (sale_items.tax_rate/taxable_base/tax_amount/fiscal_condition), NUNCA desde la tasa vigente del negocio (business->tax_rate). MOD-07 ya conserva esos snapshots; hoy ReturnService aún lee business->tax_rate (deuda de MOD-10).", 

"mod11_index_anomaly_sortable_columns": "RESUELTO en MOD-11 v2.1: IndexAnomalyRequest (e IndexReconciliationRunRequest) ya implementan sortableColumns() con allowlist explícita. Ya no hay fatal al instanciar.", 

"business_tax_rate_no_default": "business.tax_rate es NULLABLE y sin DEFAULT: un negocio nuevo sin tasa explícita NO recibe 15 %. El BusinessObserver siembra la regla estándar SOLO si hay tasa explícita; sin ella, vender un producto gravado sin regla → 422 FISCAL_CONFIG_MISSING hasta configurar la tasa (PUT /business o POST /tax-rules). BusinessResource.tax_rate = null cuando no hay regla estándar." 

} } 


# **MOD-08 – Ventas a Crédito y Cuentas por Cobrar. RECONCILIADO v2.1 (contrato canónico único).** 

{ 

"module": "MOD-08", 

"base_url": "/api/v1", 

"auth": { "scheme": "Cookie/session (Sanctum SPA)", "guard": "web", "tenant_source": "Auth::user()->business_id (BusinessScope global)", "note": "RECONCILIADO v2.1: NO es Bearer. Toda la API usa sesión SPA de Sanctum; el tenant se resuelve del usuario autenticado, jamás de un business_id del request." }, 

"conventions": { 

"money": "Montos string decimal escala 2 (bcmath). Nunca float.", 

"balance_generated": "balance = total_amount - paid_amount es columna GENERADA STORED (motor). Nunca se envía ni edita. chk_ar_balance_non_negative (>=0) respalda el anti-sobre-abono; chk_ar_total_positive (>=0) permite total=0 tras anulación.", 

"one_ar_per_invoice": "unique(invoice_id) 'uniq_ar_invoice': una factura a crédito → exactamente una CxC. Se genera atómicamente al facturar (ReceivableService::generarDesdeFactura dentro de InvoiceService::facturar).", 

"atomic_payment": "El abono es transaccional de 5 pasos IN-TX: (1) valida/lockea + congela tasa snapshot de la moneda (NIO→1; USD→vigente o EXCHANGE_RATE_MISSING 422) → (2a) asiento fiscal invoice_payment + (2b) abono trazable receivable_payment enlazado 1:1, ambos con moneda/importe nativo/tasa → (3) paid_amount += equivalente NIO (base_amount) → (4) balance recomputado por el motor → (5) sync CxC+factura + cash_movement 'cobro_credito' en la moneda del abono (solo efectivo). Cualquier fallo revierte todo.", 

"lock_order": "Orden de bloqueo CANÓNICO ÚNICO para evitar ABBA: Invoice → AccountReceivable (idéntico en abonar(), revertirPorAnulacion() vía anular(), y reducirPorNotaCredito() de MOD-10). Al facturar a crédito el cliente se bloquea (lockForUpdate) para SERIALIZAR el cupo: dos facturas concurrentes del mismo cliente no pueden ambas leer la misma exposición y ser aprobadas; la 2ª espera el commit de la 1ª y recalcula sobre la CxC ya creada.", 

"ledger_relation": "invoice_payments (fuente FISCAL única) ↔ receivable_payments (fuente TRAZABLE de la cartera), enlazadas 1:1 por receivable_payments.invoice_payment_id. GARANTÍA DE MOTOR (v2.1, migración 000007): FK restrictOnDelete + UNIQUE(invoice_payment_id) 'uniq_rp_invoice_payment' + NOT NULL. El 1:1 no depende solo de Eloquent. TODO invoice_payment de una factura a CRÉDITO tiene su receivable_payment, incluido el pago INICIAL al emitir (materializado por generarDesdeFactura SIN re-incrementar paid_amount ni tocar caja). Invariante monetaria: accounts_receivable.paid_amount == Σ receivable_payments == invoice.paid_amount == Σ invoice_payments (SIN doble conteo). Ambas tablas son append-only (inmutables). Fuentes por consulta: GET /invoices/{id}/payments = invoice_payments (pago inicial + abonos); GET /accounts-receivable/{id}/payments = receivable_payments (inicial + abonos de la CxC). Ningún cobro a crédito queda fuera del historial fiscal ni del de cartera.", 

"credit_authorization": "Exceder el cupo requiere autoría ROL-01 verificada en el SERVIDOR: StoreInvoiceRequest coacciona owner_authorized = boolean(owner_authorized) && user()->hasRole('Owner'). Un booleano del frontend por sí solo NO autoriza; un no-ROL-01 que envíe owner_authorized:true igual recibe CREDIT_LIMIT_EXCEEDED.", 

"vencida_derived": "El estado 'vencida' lo asigna SOLO el cron receivables:mark-overdue (RF-08-05), nunca un usuario ni el abono. Una CxC con abono parcial y due_date vencida se marca 'vencida' (el saldo sigue >0); al saldarla, 'pagada' prima sobre 'vencida'." 

}, 

"resources": { 

"accounts_receivable": [ 

{ "method": "GET", "path": "/accounts-receivable", "roles": ["ROL-02", "ROL-01"], 

"query": { "customer_id": "int|opt (tenant)", "status": "enum[pendiente,parcial,pagada,vencida]|opt", "overdue": "bool|opt", "from": "date|opt (created_at)", "to": "date|opt (created_at)", "per_page": "int|opt", "sort": "NO admitido: sortableColumns=[]; un 'sort' inyectado → 422. Orden fijo por id desc." }, 

"response_200": "Paginated<AccountReceivableResource> (eager customer+invoice, sin N+1)", 

"resource_fields": { "total_amount": "string", "paid_amount": "string", "balance": "string (generado)", "status": "string", "due_date": "date|null", "invoice_id": "int", "customer_id": "int" } }, 

{ "method": "GET", "path": "/accounts-receivable/{id}", "roles": ["ROL-02"], "response_200": "AccountReceivableResource (incluye payments[], invoice)" }, 

{ "method": "GET", "path": "/accounts-receivable/{id}/payments", "roles": ["ROL-02"], 

"note": "Abonos append-only. Inmutables una vez registrados.", 

"response_200": "Collection<ReceivablePaymentResource>" }, 

{ "method": "POST", "path": "/accounts-receivable/{id}/payments", "roles": ["ROL-03", "ROL-02"], 

"request": { "amount": "decimal(14,2)|>0|required (importe NATIVO del abono)", "payment_method": "enum[efectivo,transferencia,tarjeta]|required", 

"currency": "enum[NIO,USD]|opt|default:NIO (doble moneda; la tasa snapshot la congela el servidor, NO se envía)", 

"cash_session_id": "int|nullable|same_tenant (obligatorio si efectivo)", "reference": "string(100)| nullable" }, 

"note": "Abono atómico de 5 pasos (RF-08-03). DOBLE MONEDA (RF-06): la CxC se denomina en NIO; el abono conserva moneda/importe nativo/tasa snapshot (congelada al cobrar)/equivalente NIO (base_amount). AMORTIZA el saldo por su EQUIVALENTE NIO (no puede exceder balance en NIO). Genera SIEMPRE un invoice_payment (fiscal) + un receivable_payment enlazado, ambos con la MISMA moneda y tasa; solo efectivo añade cash_movement 'cobro_credito' en esa moneda (mismo snapshot). cash_session_id se valida con excludeTrashed:false (cash_sessions no es soft-deletable). Sincroniza factura (paid_amount += equivalente NIO + payment_status). Invariante de cartera en NIO: Σ receivable_payments.base_amount == accounts_receivable.paid_amount == Σ invoice_payments.base_amount. Compatibilidad: abono sin `currency` ⇒ NIO tasa 1 (base_amount = amount).", 

"response_201": "ReceivablePaymentResource { id, accounts_receivable_id, amount, currency, exchange_rate, base_amount, payment_method, reference, cash_session_id, invoice_payment_id, paid_at, user, account_receivable: { balance, status } }  // account_receivable se incluye whenLoaded (el service lo carga); NO se emite invoice_payment_status.", 

"errors": [ 

{ "code": "OVERPAYMENT", "http": 422, "when": "equivalente NIO (amount×tasa) > balance, o cuenta ya saldada (validación de servicio + chk_ar_balance_non_negative de motor). Incluye balance y el equivalente NIO." }, 

{ "code": "EXCHANGE_RATE_MISSING", "http": 422, "key": "code", "when": "currency=USD sin tipo de cambio vigente al instante del cobro (ExchangeRateMissingException). Reversión atómica total." }, 

{ "code": "INVOICE_VOIDED", "http": 409, "when": "Abono sobre una factura anulada (se verifica bajo lock antes de cualquier escritura)." }, 

{ "code": "NO_ACTIVE_CASH_SESSION", "http": 409, "when": "Abono en efectivo con la sesión de caja NO abierta (ERR-08B). Reversión atómica total." }, 

{ "http": 422, "when": "amount <= 0 (gt:0)." }, 

{ "http": 422, "when": "payment_method=efectivo sin cash_session_id (required_if de validación)." } 

] } 

], 

"customer_credit": [ 

{ "method": "GET", "path": "/customers/{id}/credit-status", "roles": ["ROL-02", "ROL-01"], 

"note": "RF-08-06: estado de crédito consolidado. Importes de datos reales, sin cálculo manual.", 

"response_200": { 

"credit_limit": "string", 

"exposure": "string (Σ balances pendientes/parciales/vencidas)", 

"available_credit": "string (limit - exposure, piso 0)", 

"open_accounts": "Collection<AccountReceivableResource> (status, balance, due_date)", 

"payment_history": "Collection<ReceivablePaymentResource> (responsable, fecha, medio)" 

} }, 

{ "method": "POST", "path": "/customers/{id}/credit-check", "roles": ["ROL-03", "ROL-02"], 

"request": { "amount": "decimal(14,2)|>0|required" }, 

"note": "RF-08-02: valida si una venta a crédito de 'amount' cabe en el cupo. Operación pura (no escribe). El punto de venta la usa antes de facturar a crédito.", 

"response_200": { "approved": "bool", "exposure": "string", "limit": "string", "available": "string", "requires_owner_authorization": "bool" }, 

"errors": [ 

{ "code": "CREDIT_LIMIT_EXCEEDED", "http": 422, "when": "exposición + amount > límite y sin autorización ROL-01. Respuesta incluye exposure y limit." }, 

{ "http": 422, "when": "credit_limit = 0 (cliente sin línea de crédito)." } 

] } 

] 

}, 

"cross_module_impacts": { 

"invoices_create": "POST /invoices con payment_type='credito' (MOD-07): IN-TX bloquea el cliente (serializa cupo), exige cliente NO genérico y ACTIVO (crédito a cliente inactivo → INVALID_INVOICE_STATE 422), valida el cupo (assertCreditAvailable: sin línea si credit_limit<=0, o exposición+total>límite sin ROL-01) y genera la CxC (generarDesdeFactura). Un pago inicial parcial es válido: baja el balance de la CxC y AHORA (v2.1) materializa su receivable_payment enlazado 1:1 al invoice_payment, sin re-incrementar paid_amount ni generar 'cobro_credito' (el efectivo inicial ya se asentó como 'venta'). owner_authorized:true solo surte efecto con ROL-01 (ver conventions.credit_authorization).", 

"invoices_void": "POST /invoices/{id}/void (ROL-01) revierte la CxC (RF-08-07): total_amount = paid_amount → balance 0, status 'pagada', conservando TODOS los abonos (BR-07, no se borran). Orden de lock Invoice→CxC. El resarcimiento del excedente pagado → MOD-10. Un abono posterior a la anulación → INVOICE_VOIDED (409).", 

"customers_deactivate": "hasPendingReceivables() (scopePending: pendiente/parcial/vencida; 'pagada' no cuenta) operativo: PUT /customers/{id} con is_active=false Y DELETE /customers/{id} con CxC viva → CUSTOMER_HAS_RECEIVABLES (422). El cliente genérico está protegido aparte (PROTECTED_RESOURCE)." 

}, 

"scheduled_jobs": { 

"mark_overdue": { 

"command": "receivables:mark-overdue", 

"frequency": "daily", 

"action": "ReceivableService::marcarVencidas por cada business. Marca 'vencida' (due_date < hoy, balance > 0) y dispara alerta 'cuenta_vencida' (MOD-11).", 

"note": "RF-08-05. El estado 'vencida' es exclusivamente derivado por este job." 

} 

}, 

"deferred": { 

"refund_surplus": "Resarcimiento del excedente ya abonado tras anulación (paid_amount conservado veraz) → MOD-10 (reembolso/saldo a favor). reducirPorNotaCredito() ya cablea el enganche de nota de crédito con el orden de lock canónico.", 

"anomaly_overdue": "El cron ya invoca AnomalyService::registrarSilencioso('cuenta_vencida', $ar) al marcar vencida (hook idempotente, sin duplicar). La máquina de estados/endpoints de anomalías es MOD-11. DEPENDENCIA MOD-11 (NO se corrige aquí): el defecto conocido de IndexAnomalyRequest queda registrado para su reconciliación en MOD-11.", 

"due_date_config": "Plazo de crédito por defecto (30 días, ReceivableService::DEFAULT_CREDIT_TERM_DAYS) → parametrizable por negocio en Fase 2." 

} } 

# **MOD-09 – Entregas y Retiros de Mercancía. RECONCILIADO v2.1 (contrato canónico único).** 

{ "module": "MOD-09", 

"base_url": "/api/v1", 

"auth": { "scheme": "Cookie/session (Sanctum SPA)", "guard": "web", "tenant_source": "Auth::user()->business_id (BusinessScope global)", "note": "RECONCILIADO v2.1: NO es Bearer." }, 

"conventions": { 

"qty": "Cantidades string decimal escala 3 (bcmath). Nunca float.", 

"physical_discount_here": "El descuento FÍSICO real de inventario ocurre SOLO aquí (retiro), vía InventoryService::retirar(): es el ÚNICO origen de una salida de kardex derivada de una venta. Facturar (MOD-07) solo reserva; ni venta ni factura tocan quantity.", 

"pending_balance": "pendiente por línea = sale_item.quantity - sale_item.dispatched_quantity. dispatched_quantity es materializado, FUERA de fillable (solo lo escribe DispatchService), con CHECK chk_sale_item_dispatch_not_exceed (0 <= dispatched <= quantity). El estado de la factura se DERIVA (no se almacena).", 

"dispatchable": "Solo despacha mercancía inventariable: compuestos (explotan a insumos) y simples con tracks_inventory. Servicios y productos no inventariables NO generan retiro y se rechazan en el retiro (422) y se EXCLUYEN del delivery-status. Una factura sin líneas entregables se considera 'completado' (no hay mercancía pendiente).", 

"compound": "Compuestos descuentan/reingresan sus insumos según recipe_snapshot CONGELADO en la línea de venta (nunca la receta viva), en orden determinista por product_id.", 

"warehouse_derivation": "Bodega origen = la predeterminada y ACTIVA de la sucursal de la factura (RF-09-01). NUNCA se acepta warehouse_id del request; sin bodega válida → error de dominio controlado (INVALID_DISPATCH_STATE 409).", 

"server_derived": "El request solo aporta invoice_id, received_by, notes y lines[]{sale_item_id, quantity}. business_id, branch_id, warehouse_id, product_id, code, status, user_id, dispatched_at, reservas, costos y movimientos los deriva el servidor.", 

"received_by_required": "RECONCILIADO v2.1: received_by es OBLIGATORIO (RF-09-01, receptor declarado) — antes nullable. Garantía de motor: dispatches.received_by NOT NULL (migración 000001 harden). IMPACTO FRONTEND: el POST debe enviar received_by.", 

"branch_isolation": "ROL-01/ROL-02 despachan/consultan cualquier sucursal del negocio; ROL-03 con sucursal asignada queda acotado a la suya (retiro de otra sucursal → 403); ROL-03 sin sucursal conserva alcance de negocio.", 

"code_sequence": "code lo genera SequenceGenerator (tabla sequences, contador atómico por (business_id,type='dispatch'), prefijo 'D-'). UNIQUE(business_id, code). Interno (no fiscal): sequences ≠ document_sequences. El contador se revierte con la transacción ante fallo.", 

"lock_order": "ORDEN DE LOCK CANÓNICO ÚNICO (retiro y reversión): (1) Invoice → (2) SaleItems por id asc → (3) saldos de inventario/insumos por product_id asc (pre-bloqueo determinista) → (4) Dispatch y movimientos. Elimina ABBA; dos retiros concurrentes no superan el saldo ni consumen dos veces la reserva.", 

"atomic": "Retiro y reversión son TODO-O-NADA en una única DB::transaction: ante cualquier fallo se revierten dispatch, dispatch_items, dispatched_quantity, quantity, reserved_quantity, inventory_movements y el folio secuencial." 

}, "resources": { 

"dispatches": [ 

{ "method": "GET", "path": "/dispatches", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil despachador: entregas.ver — acotado a su sucursal)"], 

"query": { "invoice_id": "int|opt (tenant)", "warehouse_id": "int|opt (tenant)", "status": "enum[registrado,revertido]|opt", "from": "date|opt (dispatched_at)", "to": "date|opt (dispatched_at)", "per_page": "int|opt", "sort": "allowlist[id,code,dispatched_at,status,created_at]; fuera de la lista → 422", "direction": "asc|desc" }, 

"note": "Eager load user+warehouse+invoice (sin N+1). ROL-03 con sucursal asignada solo ve la suya.", 

"response_200": "Paginated<DispatchResource>" }, 

{ "method": "POST", "path": "/dispatches", "roles": ["ROL-03"], 

"request": { 

"invoice_id": "int|required|same_tenant (emitida, no anulada)", 

"received_by": "string|required|min:2|max:160 (RECEPTOR DECLARADO, RF-09-01)", 

"notes": "string(500)|nullable", 

"lines": [ { "sale_item_id": "int|required|distinct|same_tenant", "quantity": "decimal(0,3)|>0" } ] 

}, 

"note": "Registra retiro total o parcial. Valida (bajo lock) pertenencia de la línea a la factura, despachabilidad y saldo pendiente; descuenta físico real (retirar), consume reserva, sube dispatched_quantity y asienta kardex 'salida' con dispatch_id. lines duplicadas se rechazan por validación (distinct).", 

"response_201": "DispatchResource { code, status:'registrado', received_by, dispatched_at, invoice, warehouse, user, items:[DispatchItemResource] }", 

"errors": [ 

{ "code": "DISPATCH_EXCEEDS_BALANCE", "http": 422, "when": "quantity > pendiente de la línea. Respuesta incluye pending y requested." }, 

{ "code": "DISPATCH_ON_VOIDED_INVOICE", "http": 409, "when": "La factura está anulada (ERR-09B)." }, 

{ "code": "INSUFFICIENT_STOCK", "http": 409, "when": "No hay stock físico para retirar (red de seguridad si se reservó al facturar). Rollback íntegro." }, 

{ "code": "INVALID_DISPATCH_STATE", "http": 422, "when": "La línea no pertenece a la factura, es de servicio o no controla inventario." }, 

{ "code": "INVALID_DISPATCH_STATE", "http": 409, "when": "La sucursal de la factura no tiene bodega predeterminada activa." }, 

{ "http": 403, "when": "ROL-03 intenta despachar una factura de otra sucursal (o sin sucursal asignada)." }, 

{ "http": 422, "when": "received_by ausente, o cantidad <= 0, o sale_item_id repetido." } 

] }, 

{ "method": "GET", "path": "/dispatches/{id}", "roles": ["ROL-03", "ROL-02", "ROL-01"], "response_200": "DispatchResource (incluye invoice, warehouse, user, reverted_by_user, items[])", "note": "ROL-03 acotado a su sucursal." }, 

{ "method": "GET", "path": "/dispatches/{id}/items", "roles": ["ROL-03", "ROL-02", "ROL-01"], "note": "RECONCILIADO v2.1: antes ROL-02+; ahora alineado con view (detalle operativo del retiro). ROL-03 acotado a su sucursal.", "response_200": "Collection<DispatchItemResource> (product + description congelada, cantidades string)" }, 

{ "method": "POST", "path": "/dispatches/{id}/revert", "roles": ["ROL-02", "ROL-01"], 

"request": { "revert_reason": "string|required|min:3|max:255 (motivo significativo)" }, 

"note": "Reversión (RF-09-04, ROL-02+). Bloquea Invoice→Dispatch→SaleItems→Stock; reingresa a la bodega origen, RE-RESERVA (factura viva), baja dispatched_quantity, marca 'revertido' con reverted_by/at/reason y kardex 'entrada' con dispatch_id. NO borra el retiro ni sus líneas.", 

"response_200": "DispatchResource { status:'revertido', reverted_by, reverted_at, revert_reason }", 

"errors": [ 

{ "http": 403, "when": "Rol inferior a ROL-02." }, 

{ "code": "INVALID_DISPATCH_STATE", "http": 409, "when": "El retiro ya está revertido (no está 'registrado')." }, 

{ "code": "INVALID_DISPATCH_STATE", "http": 409, "when": "La factura se anuló DESPUÉS del retiro: NO se re-reserva mercancía de un comprobante inválido; lo entregado se gestiona por devolución (MOD-10)." } 

] } ], "delivery_status": [ 

{ "method": "GET", "path": "/invoices/{id}/delivery-status", "roles": ["ROL-03", "ROL-02", "ROL-01"], 

"note": "RF-09-02: saldo pendiente de entrega consolidado, SOLO lectura y derivado (no acepta cantidades del cliente). pendiente = facturado - retirado acumulado, por línea. El estado consolidado se deriva de las líneas ENTREGABLES: pendiente / parcial / completado; una factura sin líneas entregables (p. ej. solo servicios) es 'completado'.", 

"response_200": { 

"invoice_id": "int", 

"delivery_state": "enum[pendiente,parcial,completado] (derivado)", 

"lines": [ { 

"sale_item_id": "int", "product_id": "int", "description": "string", "is_dispatchable": "bool", 

"invoiced_quantity": "string", "dispatched_quantity": "string", "pending_quantity": "string" 

} ] 

} } 

] 

}, 

"cross_module_impacts": { 

"sale_items": "Columna dispatched_quantity (materializada) + accesor pendingQuantity + CHECK chk_sale_item_dispatch_not_exceed (0 <= dispatched <= quantity). Fuera de fillable (solo DispatchService la escribe).", 

"inventory_movements.dispatch_id": "FK cableada (wire_dispatch_fk_to_inventory_movements), ON DELETE RESTRICT. Un dispatch con kardex no se borra. Relación dispatch() activa.", 

"dispatches_hardening": "RECONCILIADO v2.1 (migración 000001 harden): received_by NOT NULL (receptor declarado) + índice idx_dispatch_dispatched_at para el filtro por fecha. UNIQUE(business_id, code) y chk_dispatch_revert_coherence ya existentes.", 

"invoices_void": "InvoiceService::anular libera SOLO el remanente no despachado (quantity - dispatched_quantity): nunca reingresa lo ya entregado. Sin retiros libera toda la reserva; retiro total no libera nada. Lo entregado se resuelve por devolución (MOD-10)." 

}, 

"deferred": { 

"returns_vs_revert": "MOD-10: una vez la mercancía entregada se devuelve, revertir el retiro se vuelve incompatible (returned_quantity <= dispatched_quantity, chk_sale_item_return_not_exceed). La coordinación revert⇄devolución la define MOD-10; aquí solo se bloquea la reversión sobre factura anulada (409).", 

"anomaly_dispatch": "Inconsistencias facturado⇄retirado → AnomalyService (MOD-11).", 

"logistics": "Rutas, reparto y SLA de entrega → Fase 2 (FUERA de alcance MOD-09)." 

} 

} 

# **MOD-10 – Devoluciones, Reingresos y Mermas. RECONCILIADO v2.1 (contrato canónico único).** 

{ 

"module": "MOD-10", 

"base_url": "/api/v1", 

"auth": { "scheme": "Cookie/session (Sanctum SPA)", "guard": "web", "tenant_source": "Auth::user()->business_id (BusinessScope global)", "note": "RECONCILIADO v2.1: NO es Bearer. business_id SIEMPRE de la sesión, jamás del payload/query." }, 

"conventions": { 

"money": "Montos string decimal escala 2; cantidades escala 3 (bcmath). Nunca float. Redondeo half-up vía App\\Support\\Money.", 

"returnable": "devolvible por línea = sale_item.dispatched_quantity - sale_item.returned_quantity (NUNCA lo facturado). Cadena de saldos: returned_quantity <= dispatched_quantity <= quantity. CHECK chk_sale_item_return_not_exceed (<= entregado) es el anti-sobre-devolución de motor. returned_quantity es materializado, fuera de fillable (solo ReturnService lo escribe).", 

"fiscal_snapshot_exclusive": "REGLA FISCAL CRÍTICA (RF-10-04): la devolución y la NC se calculan EXCLUSIVAMENTE desde los snapshots congelados en MOD-07 de la línea original (unit_price, discount_amount ya incluido en line_total, taxable_base, tax_rate y tax_amount), proporcionalmente a la cantidad devuelta. NUNCA se usan business.tax_rate, tax_rules, precios, descuentos ni configuración fiscal VIGENTES. Devolución total de la línea ⇒ figuras congeladas EXACTAS (sin drift). El descuento a nivel de factura (invoice.discount_amount) se prorratea sobre el bruto devuelto respecto del bruto original (subtotal+IVA), de modo que una devolución total reproduce el total original.", 

"one_cn_per_return": "unique(sales_return_id) 'uniq_credit_note_return': una devolución → exactamente una nota de crédito. Folio fiscal NC- vía document_sequences (tipo credit_note); folio interno DV- de la devolución vía sequences.", 

"refund_branching": "El resarcimiento respeta la condición REAL de la factura: crédito con saldo vivo → reduce PRIMERO la CxC por el saldo PENDIENTE (exacto); el EXCEDENTE —solo lo ya PAGADO— se reembolsa en efectivo (si se aporta caja: sesión ABIERTA + autoridad ROL-01) o queda como saldo a favor. Aportar caja sin excedente pagado ⇒ ERR-10B (no se reembolsa lo no pagado). Contado pagado (o crédito saldado) → efectivo o saldo a favor. Σ(vías aplicadas) == total de la NC, nunca lo excede.", 

"resarcimiento_desglose": "NORMALIZADO (v2.1): cada VÍA aplicada se registra en credit_note_resolutions {resolution_type∈[reduccion_cxc,reembolso_efectivo,nota_credito_saldo], amount, cash_session_id?}, con CHECK amount>0. Σ(amount) == credit_notes.total_amount (atómico). La cabecera credit_notes.resolution_type es la vía única, o 'mixto' cuando se aplicaron dos (p. ej. crédito parcialmente pagado). El saldo a favor del cliente = Σ de las filas 'nota_credito_saldo' vigentes (no del total de NC). Cada vía y su monto quedan trazables.", 

"accumulated_rounding": "Devoluciones parciales acumuladas: el importe de cada parcial = acumulado(devuelto+este) − acumulado(devuelto) sobre las figuras congeladas (redondeo progresivo). La suma NUNCA excede base/descuento/impuesto/total congelados y, al agotar lo devolvible, coincide EXACTAMENTE; el último parcial absorbe el residuo de redondeo.", 

"reentry_vs_merma": "Reingreso: mercancía vendible vuelve al inventario vía InventoryService (compuestos reingresan sus insumos según recipe_snapshot; a costo congelado el simple, a promedio vigente los insumos en Fase 1). Merma: NO reingresa; inventory_adjustment tipo 'merma' (pérdida trazable). 'vencido'/'defecto_fabrica' NUNCA reingresan. Servicios y productos no inventariables no se despachan, por lo que su devolvible es 0 y no generan movimiento físico.", 

"lock_order": "Orden de lock canónico (compatible con MOD-07/08/09): Invoice → SaleItems por id asc → Stock (en el reingreso) → CxC → Caja → NotaCredito. Serializa devoluciones sobre la misma línea (bloqueo de fila) e impide doble devolución/doble resarcimiento.", 

"atomic": "Folio, devolución, líneas, NC, inventario, merma, CxC, caja y acumulados se ejecutan en una única DB::transaction: rollback integral ante cualquier fallo." 

}, 

"resources": { 

"sales_returns": [ 

{ "method": "GET", "path": "/sales-returns", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: devoluciones.ver — índice, detalle e items acotados a su sucursal)"], 

"query": { "invoice_id": "int|opt (tenant)", "customer_id": "int|opt (tenant)", "status": "enum[registrada,procesada,anulada]|opt", "from": "date|opt (returned_at)", "to": "date|opt (returned_at)", "per_page": "int|opt", "sort": "allowlist[id,code,returned_at,status,total_returned,created_at]; fuera de la lista → 422", "direction": "asc|desc" }, 

"note": "Eager load user+invoice+customer+creditNote (sin N+1).", 

"response_200": "Paginated<SalesReturnResource>" }, 

{ "method": "POST", "path": "/sales-returns", "roles": ["ROL-03", "ROL-02", "ROL-01"], 

"request": { 

"invoice_id": "int|required|same_tenant", 

"cash_session_id": "int|nullable|same_tenant (se aporta SOLO cuando el resarcimiento será reembolso en efectivo)", 

"notes": "string(500)|nullable", 

"lines": [ { 

"sale_item_id": "int|required|distinct|same_tenant", "quantity": "decimal(0,3)|>0", 

"reason_code": "enum[vencido,defecto_fabrica,error_despacho,insatisfaccion,otro]|required", 

"destination": "enum[reingreso,merma]|opt (default: sugerido por reason_code; vencido/defecto ⇒ merma)", 

"warehouse_id": "int|nullable|same_tenant (default: bodega default de la sucursal de la factura)" 

} ] 

}, 

"note": "Procesa la devolución completa y ATÓMICA: valida devolvible (<= entregado) y pertenencia de la línea a la factura, aplica destino físico (reingreso / merma), calcula la NC desde snapshots congelados (ver fiscal_snapshot_exclusive), genera UNA nota de crédito y resarce por la vía real de la factura. lines duplicadas se rechazan por validación (distinct).", 

"response_201": "SalesReturnResource { code:'DV-…', status:'procesada', total_returned, items:[SalesReturnItemResource], credit_note:{ folio:'NC-…', resolution_type ('mixto' si hay 2 vías), total_amount, tax_amount, resolutions:[{ resolution_type, amount, cash_session_id }] } }", 

"errors": [ 

{ "code": "RETURN_QUANTITY", "http": 422, "when": "quantity > devolvible (entregado - ya devuelto), o la línea no pertenece a la factura (returnable 0). Respuesta incluye returnable y requested." }, 

{ "code": "INVALID_REFUND_METHOD", "http": 422, "when": "Reembolso en efectivo sobre venta a crédito NO pagada (ERR-10B), o sesión de caja no ABIERTA." }, 

{ "code": "REFUND_AUTHORIZATION_REQUIRED", "http": 403, "when": "El resarcimiento resuelve reembolso en efectivo pero el usuario no es ROL-01." }, 

{ "http": 422, "when": "Motivo vencido/defecto marcado como reingreso, cantidad <= 0, o sale_item_id repetido." } 

] }, 

{ "method": "GET", "path": "/sales-returns/{id}", "roles": ["ROL-02", "ROL-01"], "response_200": "SalesReturnResource (incluye items[], credit_note, invoice, customer)" }, 

{ "method": "GET", "path": "/sales-returns/{id}/items", "roles": ["ROL-02", "ROL-01"], "response_200": "Collection<SalesReturnItemResource> (product, cantidades string)" } 

], 

"credit_notes": [ 

{ "method": "GET", "path": "/credit-notes", "roles": ["ROL-02", "ROL-01", "ROL-03 (perfil bodeguero: notas_credito.ver — acotado a su sucursal por la factura)"], 

"query": { "invoice_id": "int|opt (tenant)", "customer_id": "int|opt (tenant)", "resolution_type": "enum[reembolso_efectivo,nota_credito_saldo,reduccion_cxc]|opt", "status": "enum[emitida,anulada]|opt", "per_page": "int|opt", "sort": "allowlist[id,folio,issued_at,status,total_amount,created_at]; fuera de la lista → 422", "direction": "asc|desc" }, 

"note": "Solo lectura: las NC se emiten dentro de la devolución. Eager load invoice+customer+salesReturn (sin N+1).", 

"response_200": "Paginated<CreditNoteResource>", 

"resource_fields": { "folio": "string", "resolution_type": "string ('mixto' si hay 2 vías)", "total_amount": "string", "tax_amount": "string", "invoice_id": "int", "sales_return_id": "int", "resolutions": "[{ resolution_type, amount:string, cash_session_id:int|null }]" } }, 

{ "method": "GET", "path": "/credit-notes/{id}", "roles": ["ROL-02", "ROL-01"], "response_200": "CreditNoteResource" }, 

{ "method": "GET", "path": "/customers/{id}/credit-balance", "roles": ["ROL-02", "ROL-01"], 

"note": "Saldo a favor del cliente (RF-10-03, Fase 1: suma de credit_notes con resolution_type='nota_credito_saldo' vigentes; sin columna materializada). Solo lectura.", 

"response_200": { "customer_id": "int", "available_credit_balance": "string", "open_credit_notes": "Collection<CreditNoteResource>" } } 

] 

}, 

"cross_module_impacts": { 

"sale_items": "returned_quantity (materializada) + accesor returnableQuantity + CHECK chk_sale_item_return_not_exceed (<= dispatched_quantity) y chk_sale_item_returned_non_negative. Fuera de fillable (solo ReturnService).", 

"credit_note_resolutions": "NUEVA tabla (v2.1, migración 2026_09_25_000002): desglose 1:N del resarcimiento de la NC {business_id, credit_note_id (cascade), cash_session_id? (restrict), resolution_type∈concretas, amount, created_at}, CHECK amount>0. Σ(amount)==credit_notes.total_amount. Morph alias 'credit_note_resolution'. credit_notes.resolution_type ampliado con 'mixto'.", 

"receivable_service (MOD-08)": "reducirPorNotaCredito(ar, cxcPart): reduce el balance de la CxC (crédito) EXACTAMENTE por el saldo pendiente (o menos), con piso en lo abonado, SIN borrar abonos (BR-07); reutiliza syncStatuses. Orden de lock Invoice→CxC.", 

"inventory_service (MOD-03/09)": "ingresarPorDevolucion (reingreso vendible, compuestos por recipe_snapshot) y registrarMermaPorDevolucion (ajuste 'merma', sin reingreso). Componente único de inventario.", 

"cash_service (MOD-06)": "registrarReembolsoDevolucion: egreso 'egreso_autorizado' con authorized_by=ROL-01, exige sesión ABIERTA (chk_cash_movement_egreso_auth).", 

"document_sequences / sequences": "Folio fiscal NC- desde document_sequences (tipo credit_note, sembrado por BusinessObserver); folio interno DV- desde sequences (tipo sales_return)." 

}, 

"deferred": { 

"customer_credit_balance_column": "customers.credit_balance materializado → Fase 2 (Fase 1: la NC de saldo es la fuente de verdad).", 

"refund_exact_cost_on_compound": "Congelar el costo exacto por insumo en el reingreso de compuestos → Fase 2 (Fase 1: costo promedio vigente, decisión del FRD).", 

"credit_balance_application": "Aplicar/consumir el saldo a favor en ventas futuras (débito de la NC de saldo) → Fase 2.", 

"anomaly_reconciliation": "Inconsistencias factura⇄devolución → AnomalyService (MOD-11). NO se modifica MOD-11 en esta etapa." 

} } 

# **MOD-11 – Conciliación, Alertas y Gestión de Anomalías. RECONCILIADO v2.1 (contrato canónico único).** 

{ 

"module": "MOD-11", 

"base_url": "/api/v1", 

"auth": { "scheme": "Cookie/session (Sanctum SPA)", "guard": "web", "tenant_source": "Auth::user()->business_id (BusinessScope global). En el motor PROGRAMADO (sin sesión) el business_id se deriva EXPLÍCITO del origen, jamás de Auth.", "note": "RECONCILIADO v2.1: NO es Bearer. business_id nunca del payload/query/ruta." }, 

"conventions": { 

"idempotent": "El registro de anomalías es idempotente: la columna generada active_dedupe_key (regla:source_type:source_id SOLO mientras está activa) + UNIQUE uniq_active_anomaly impiden dos activas por (regla+origen). La detección duplicada (manual/programada) se deduplica en SILENCIO (captura del 1062, ERR-11B) y se contabiliza como 0 nuevas; nunca error al usuario. Al justificar/resolver, la clave pasa a NULL y libera el candado para una futura aparición.", 

"br01": "BR-01: el causante de una anomalía NO puede justificarla ni resolverla (SELF_RESOLUTION_NOT_ALLOWED, 403). El causante se obtiene del ORIGEN real (cash_sessions.opened_by, goods_receipts.user_id, physical_counts.user_id), nunca de datos del cliente.", 

"state_machine": "Estados ACTIVOS: detectada, notificada, en_revision. TERMINALES: justificada, resuelta. Justificar (ROL-02+) solo desde un estado activo; resolver (ROL-01) desde cualquier estado no resuelto. Toda transición crea un anomaly_event INMUTABLE (append-only) con from/to/usuario/fecha.", 

"weak_pointer": "source_type/source_id es puntero polimórfico DÉBIL (sin FK, fuera del MorphMap): source_type = nombre de tabla (cash_sessions, physical_counts, goods_receipts, accounts_receivables). Su resolución acota SIEMPRE por business_id; nunca resuelve clases arbitrarias ni cruza tenants.", 

"read_only_engine": "El motor de conciliación SOLO LEE los registros operativos (nunca los modifica); genera exclusivamente corridas, anomalías y eventos.", 

"closed_catalog": "Exactamente 6 reglas por negocio (descuadre_caja, faltante_inventario, discrepancia_3way, cuenta_vencida, omision_registro, venta_sin_sesion), sembradas idempotentemente por BusinessObserver (createOrFirst + uniq_anomaly_rule_code). code y name son INMUTABLES; solo ROL-01 parametriza threshold_value/default_severity/is_active. Una desviación bajo umbral NO genera anomalía.", 

"sorting": "Ordenamiento por allowlist (H-04): anomalies [id,detected_at,severity,status,difference,created_at]; reconciliation-runs [id,started_at,finished_at,status,scope,anomalies_found,created_at]. Fuera de la lista → 422.", 

"transaction_boundary": "Justificar/resolver son transaccionales con lockForUpdate sobre la anomalía. Una corrida fallida se marca 'fallida' (nunca queda en 'en_proceso'); un fallo no deja eventos huérfanos ni contadores incorrectos." 

}, 

"resources": { 

"anomaly_rules": [ 

{ "method": "GET", "path": "/anomaly-rules", "roles": ["ROL-01", "ROL-02"], 

"response_200": "Collection<AnomalyRuleResource> (6 reglas sembradas por negocio)" }, 

{ "method": "PUT", "path": "/anomaly-rules/{id}", "roles": ["ROL-01"], 

"request": { "threshold_value": "decimal|nullable", "default_severity": "enum[informativa,advertencia,critica]|opt", "is_active": "bool|opt" }, 

"note": "Parametriza umbral/severidad. El code es inmutable (catálogo cerrado).", 

"response_200": "AnomalyRuleResource" } 

], 

"anomalies": [ 

{ "method": "GET", "path": "/anomalies", "roles": ["ROL-01", "ROL-02"], 

"query": { "status": "enum[detectada,notificada,en_revision,justificada,resuelta]|opt", "severity": "enum[informativa,advertencia,critica]|opt", "rule_code": 

"enum[descuadre_caja,faltante_inventario,discrepancia_3way,cuenta_vencida,omision_registro,venta_ sin_sesion]|opt", "branch_id": "int|opt", "source_type": "string|opt", "from": "date|opt", "to": "date| opt" }, 

"response_200": "Paginated<AnomalyResource>", 

"resource_fields": { "severity": "string", "status": "string", "expected_value": "string|null", "actual_value": "string|null", "difference": "string|null", "source_type": "string|null", "source_id": "int| null", "rule": "AnomalyRuleResource" } }, 

{ "method": "GET", "path": "/anomalies/{id}", "roles": ["ROL-01", "ROL-02"], "response_200": "AnomalyResource (incluye events[], source resuelto)" }, 

{ "method": "GET", "path": "/anomalies/{id}/events", "roles": ["ROL-01", "ROL-02"], 

"note": "Bitácora append-only de la máquina de estados.", 

"response_200": "Collection<AnomalyEventResource>" }, 

{ "method": "POST", "path": "/anomalies/{id}/justify", "roles": ["ROL-02", "ROL-01"], 

"request": { "reason": "string(500)|required" }, 

"note": "RF-11-07: justifica la anomalía (motivo, responsable=Auth, fecha). BR-01: rechaza si el validador es el causante. Genera anomaly_event automático detectada→justificada.", 

"response_200": "AnomalyResource { status:'justificada', resolved_by, resolved_at }", 

"errors": [ 

{ "code": "SELF_RESOLUTION_NOT_ALLOWED", "http": 403, "when": "El validador es el causante de la anomalía (BR-01)." }, 

{ "code": "INVALID_ANOMALY_STATE", "http": 422, "when": "La anomalía no está en un estado activo justificable." } 

] }, 

{ "method": "POST", "path": "/anomalies/{id}/resolve", "roles": ["ROL-01"], 

"request": { "comment": "string(500)|nullable" }, 

"note": "Marca 'resuelta' (autoridad ROL-01). Genera anomaly_event y libera el candado de idempotencia. BR-01 también aplica.", 

"response_200": "AnomalyResource { status:'resuelta', resolved_by, resolved_at }", 

"errors": [ { "code": "INVALID_ANOMALY_STATE", "http": 422, "when": "La anomalía ya está resuelta." } ] } 

], 

"reconciliation_runs": [ 

{ "method": "GET", "path": "/reconciliation-runs", "roles": ["ROL-01", "ROL-02"], 

"query": { "scope": "enum[caja,inventario_bodega,compras_3way,integral]|opt", "run_type": "enum[programada,manual]|opt", "status": "enum[en_proceso,completada,fallida]|opt", "from": "date| opt" }, 

"response_200": "Paginated<ReconciliationRunResource>" }, 

{ "method": "POST", "path": "/reconciliation-runs", "roles": ["ROL-01", "ROL-02"], 

"request": { "scope": "enum[caja,inventario_bodega,compras_3way,integral]|required", "branch_id": "int|nullable|same_tenant" }, 

"note": "Dispara una conciliación MANUAL a demanda (ROL-02+). business_id y triggered_by SIEMPRE de la sesión, nunca del payload. Idempotente (no duplica anomalías ya activas). branch_id acota el ámbito por sucursal (caja→sus cajas; inventario/3way→sus bodegas).", 

"response_201": "ReconciliationRunResource { status, anomalies_found, started_at, finished_at }" }, 

{ "method": "GET", "path": "/reconciliation-runs/{id}", "roles": ["ROL-01", "ROL-02"], "response_200": "ReconciliationRunResource (incluye anomalies[])" } 

] 

}, 

"scheduled_jobs": { 

"reconciliation_run": { "command": "reconciliation:run --scope=integral", "frequency": "daily 01:00", "action": "Auditoría de fondo por negocio (RF-11-04). Recorre todos los tenants (sin auth, explícito)." }, 

"mark_overdue": { "command": "receivables:mark-overdue", "frequency": "daily 00:30", "action": "Marca CxC vencidas → dispara anomalía 'cuenta_vencida' (RF-08-05)." } 

}, 

"detection_mechanisms": { 

"hybrid": "RF-11-03 · detección HÍBRIDA: eventos EN LÍNEA (hooks inmediatos, tras el commit de la operación origen) + CONCILIACIÓN manual/programada. Ambas vías comparten registrarSilencioso y la deduplicación estructural (uniq_active_anomaly): el hook alerta al instante y una corrida posterior NO duplica.", 

"descuadre_caja": "HOOK inmediato en CashService::cerrar: al cerrar con difference<>0 se registra 'descuadre_caja' DESPUÉS del commit (evidencia y sesión 'descuadrada' persisten; el 422 se emite tras alertar; un fallo al alertar no destruye el cierre). ENGINE: reconciliarCaja re-detecta sesiones cerrada/descuadrada con difference<>0. Source: cash_sessions; causante opened_by.", 

"discrepancia_3way": "HOOK inmediato en GoodsReceiptService::recibir: al recibir con match_status='discrepancia' se registra 'discrepancia_3way' DESPUÉS del commit (recepción/evidencia persisten; el 409 se emite tras alertar). ENGINE: reconciliar3Way re-detecta goods_receipts con match_status in ('discrepancia','bloqueada'). Source: goods_receipts; causante user_id.", 

"faltante_inventario": "ENGINE (reconciliarInventario): physical_counts con difference<0 en ventana de 7 días. Source: physical_counts; causante user_id.", 

"cuenta_vencida": "HOOK en tiempo real: ReceivableService::marcarVencidas (comando receivables:mark-overdue) → registrarSilencioso('cuenta_vencida'). Source: accounts_receivables (sin causante humano).", 

"venta_sin_sesion": "ENGINE (reconciliarVentaSinSesion, ámbito 'caja'): AUDITORÍA DEFENSIVA Fase 1 — invoice_payments en efectivo con cash_session_id NULL (dato heredado/inconsistente). MOD-06/07 previenen esto estructuralmente en el flujo real; el detector NO debilita ese bloqueo. Source: invoice_payments.", 

"omision_registro": "Fase 2 (diferido): requiere parametrizar ventanas de alta demanda." 

}, 

"deferred": { 

"omision_registro": "Detección de omisiones de uso (RF-11-05): job que cruza login sin actividad en ventanas de alta demanda → Fase 2 (requiere parametrizar ventanas).", 

"routing_channels": "Enrutamiento por canal (email/push) según severidad (RF-11-06): motor de notificaciones → Fase 2.", 

"reports_consolidation": "Consolidación automática de reportes (RF-11-02) se materializa en MOD-12 (KPIs/reportería)." 

} } 


# **MOD-12 - Reportería, KPIs e Inteligencia de Negocios** 

{ "module": "MOD-12", 

"base_url": "/api/v1", 

"auth": { "scheme": "Sanctum SPA (cookie de sesión + CSRF, guard web)", "tenant_source": "session.user.business_id", "note": "NUNCA Bearer. business_id, created_by y user_id salen SIEMPRE de la sesión, jamás del payload/query/ruta." }, 

"conventions": { 

"snapshots_are_cache": "kpi_snapshots es caché recalculable, NUNCA fuente de verdad (ERR-12B). Se recalcula desde las TABLAS FUENTE (invoices, accounts_receivables, physical_counts, anomalies, audit_logs, reconciliation_runs), no desde otros snapshots.", 

"tenant_isolation": "Toda consulta agregada filtra business_id explícitamente (ERR-12): las vistas SQL y los agregados NO están protegidos por BusinessScope. El binding de rutas de metas/definiciones va acotado por BusinessScope (cross-tenant → 404 sin filtrar).", 

"kpi_registry": "config/kpis.php es el registro canónico (código ↔ etiqueta ↔ unidad ↔ familia ↔ goalable ↔ direction[up|down] ↔ source). Etiqueta/unidad/familia se resuelven ahí, nunca se duplican en controladores/Resources.", 

"timezone": "Los períodos se resuelven en business.timezone como intervalos SEMIABIERTOS [inicio, fin) convertidos a UTC; las tablas fuente (timestamps UTC) se consultan con esos límites. Los totales de Fase 1 son exactos incluso en el borde de medianoche. El corte diario vía CONVERT_TZ DENTRO de las vistas queda diferido a Fase 2 (por eso el recálculo consulta la fuente, no las vistas bucketeadas por DATE()).", 

"idempotency": "Recalcular dos veces el mismo (negocio, sucursal/global, kpi, period_type, period_start) NO duplica: upsert respaldado por uniq_kpi_snapshot (ON DUPLICATE KEY). branch_key = COALESCE(branch_id,0) colapsa el NULL.", 

"atomicity": "KpiService::calcular envuelve todo el juego de KPIs de un período en una transacción: un fallo no deja snapshots a medias (ERR-12B → 500). Transacciones y candados viven en el Service, no en los controladores.", 

"frozen_goals": "target_value y achievement_pct se CONGELAN en el snapshot al calcular; cambiar la meta viva después NO altera snapshots ya calculados hasta un nuevo recálculo.", 

"kpi_06_last": "KPI-06 (agregador de cumplimiento) se calcula AL FINAL, como promedio de los achievement_pct VÁLIDOS de los KPIs con meta (kpi_03, kpi_04, kpi_05, kpi_08, ticket_promedio).", 

"direction": "El logro respeta la dirección: 'up' = valor/target×100; 'down' (solo kpi_03) = target/valor×100 (sin faltante ⇒ 100%). Nunca divide por cero; sin datos ⇒ ceros deterministas, jamás NaN/INF. Todo en BCMath/DECIMAL, decimales como cadenas de escala estable (valor escala 4, meta/logro escala 2).", 

"branch_scope": "Por negocio y período se calcula el snapshot GLOBAL (branch_id NULL) Y uno POR CADA SUCURSAL (RF-12-03), de modo que ninguna meta por sucursal quede sin indicador. Cada ámbito filtra estrictamente sus datos: KPI-02 deriva la sucursal por la bodega, KPI-04 por los usuarios de la sucursal, KPI-05/01/08 por el branch_id de la factura, KPI-03/07 por branch_id. Una meta de sucursal aplica solo a su snapshot; una meta global no contamina los de sucursal. El panel /dashboard/kpis sirve el ámbito GLOBAL; /kpi-snapshots permite filtrar por branch_id.", 

"kpi_sources": { 
"kpi_01": "FRD KPI-01 (índice de salud, sin meta): 'facturado contra la suma de cobros y cuentas por cobrar generadas en el periodo'. Fase 1 mide integridad ventas↔cobro/CxC = (contado cobrado + crédito con CxC) / facturado ×100. La correspondencia física de inventario la cubre KPI-02 y la conciliación 3-way inventario→retiro→cobro→caja el reporte RF-11-02; la pata de inventario NO forma parte de la fuente implementada (diferida a Fase 2). metadata publica measures='facturado_vs_cobros_y_cxc', formula, invoiced/covered/contado/credito e inventory_leg='diferido_fase2'. source: invoices (+accounts_receivables).", 
"kpi_02": "Exactitud de stock: (1 − Σ|difference| / Σ system_quantity) ×100 de los conteos del período. source: physical_counts.", 
"kpi_03": "Faltantes NO justificados (monto): Σ|difference| de anomalías 'faltante_inventario' activas del período. goalable, direction=down. source: anomalies+anomaly_rules.", 
"kpi_04": "Uso del sistema: usuarios distintos con actividad / usuarios habilitados ×100. goalable. source: audit_logs + users(is_active).", 
"kpi_05": "Evolución de ventas (monto) del período + invoice_count + avg_ticket. goalable. source: invoices(status=emitida).", 
"kpi_06": "Cumplimiento de metas (agregador). NO goalable. source: los achievement_pct del propio recálculo.", 
"kpi_07": "Disponibilidad: corridas programadas completadas / totales ×100 del período. source: reconciliation_runs(run_type=programada).", 
"kpi_08": "Recuperación de cartera (%) con SEMÁNTICA HISTÓRICA REPRODUCIBLE (cohorte del período), contabilizando TODOS los eventos de cartera sin doble conteo ni negativos. emitida = Σ invoice.total (obligación ORIGINAL inmutable) de las facturas a crédito de la cohorte VIGENTES al cierre (no anuladas antes del corte; se usa invoices.voided_at, no el estado vivo). pagos_iniciales = abonos al emitir (receivable_payments materializados 1:1 del invoice_payment inicial; paid_at == issued_at). abonos_posteriores = receivable_payments con paid_at > issued_at. recuperada = pagos_iniciales + abonos_posteriores con paid_at < fin del período. reducciones_cxc = resoluciones de nota de crédito 'reduccion_cxc' EMITIDAS antes del cierre (bajan pendiente pero NO son dinero recuperado). pendiente = Σ por CxC de GREATEST(emitida − recuperada − reducciones, 0). vencida = porción pendiente con due_date vencida al cierre (<= fin local). NO usa ar.paid_amount/ar.total_amount/ar.balance vivos, de modo que un abono, NC o anulación POSTERIOR al cierre no reescribe un período histórico. value = recuperada/emitida ×100. metadata: fecha_de_corte, emitida, pagos_iniciales, abonos_posteriores, recuperada, reducciones_cxc, pendiente, vencida. goalable. source: accounts_receivables ⨝ invoices ⨝ receivable_payments ⨝ credit_note_resolutions.", 
"ticket_promedio": "Derivado de ventas del período (total/invoice_count). goalable. source: invoices.", 
"deferred_kpis": "margen y rotacion_inventario: goalable en el registro pero NO calculados en Fase 1 (source: fase2); se admite fijarles meta sin que produzcan snapshot." 
} 

}, 

"resources": { 

"business_goals": [ 

{ "method": "GET", "path": "/business-goals", "roles": ["ROL-01", "ROL-02"], 

"query": { "kpi_code": "enum[kpi_03,kpi_04,kpi_05,kpi_08,margen,ticket_promedio,rotacion_inventario]|opt", "period_type": "enum[diario,semanal,mensual,anual]|opt", "branch_id": "int|opt" }, 

"response_200": "Paginated<BusinessGoalResource>" }, 

{ "method": "POST", "path": "/business-goals", "roles": ["ROL-01"], 

"request": { "kpi_code": "enum[...goalable]|required", "period_type": "enum[diario,semanal,mensual,anual]|required", 

"period_start": "date|required", "period_end": "date|required|>=period_start", 

"target_value": "decimal(16,2)|>0|required", "branch_id": "int|nullable|same_tenant (NULL = meta global)" }, 

"note": "Meta por KPI/período. UNIQUE uniq_business_goal (business_id, branch_key, kpi_code, period_type, period_start): una meta por combinación. branch NULL colapsa a 0 (branch_key). created_by = sesión. Solo códigos goalable del registro.", 

"response_201": "BusinessGoalResource", 

"errors": [ { "http": 422, "when": "Meta duplicada (incl. colisión concurrente 1062 → GOAL_CONFLICT), target<=0, period_end<period_start, kpi_code no goalable, o branch_id de otro tenant (sin filtrar)." } ] }, 

{ "method": "PUT", "path": "/business-goals/{businessGoal}", "roles": ["ROL-01"], 

"request": { "target_value": "decimal(16,2)|>0|opt", "period_end": "date|>=period_start|opt" }, 

"note": "Identidad INMUTABLE (business, sucursal, kpi_code, period_type, period_start): solo se ajustan target_value y/o period_end. No rompe la unicidad histórica ni altera snapshots ya congelados.", 

"response_200": "BusinessGoalResource" }, 

{ "method": "DELETE", "path": "/business-goals/{businessGoal}", "roles": ["ROL-01"], "response_204": null } 

], 

"pagination_sort": { "page": "int|min:1", "per_page": "int|1..100", "sort": "allowlist[id,kpi_code,period_type,period_start,period_end,created_at]", "direction": "asc|desc" }, 

"kpi_snapshots": [ 

{ "method": "GET", "path": "/kpi-snapshots", "roles": ["ROL-01", "ROL-02"], 

"query": { "kpi_code": "string|opt", "period_type": "enum[diario,semanal,mensual,anual]|opt", "period_start": "date|opt", "branch_id": "int|opt" }, 

"response_200": "Paginated<KpiSnapshotResource>", 

"sort": "allowlist[id,kpi_code,period_type,period_start,value,achievement_pct,calculated_at]", 

"resource_fields": { "id": "int", "kpi_code": "string", "label": "string (config/kpis.php)", "family": "string", "unit": "string", "period_type": "string", "period_start": "date", "period_end": "date", "branch_id": "int|null", "value": "string(escala 4)", "target_value": "string|null", "achievement_pct": "string|null", "metadata": "object|null (componentes trazables)", "calculated_at": "datetime ISO8601" } }, 

{ "method": "GET", "path": "/dashboard/kpis", "roles": ["ROL-01"], 

"query": { "period_type": "enum[diario,semanal,mensual,anual]|opt|default:mensual", "period_start": "date|opt (si falta, período vigente en business.timezone)" }, 

"note": "RF-12-02: panel consolidado del negocio. Sirve las instantáneas globales (branch_id NULL) del período; solo códigos conocidos y datos del propio negocio. Sin period_start, KpiService resuelve el período vigente (sin cálculos de período en el controlador).", 

"response_200": "{ data: Collection<KpiSnapshotResource> }" }, 

{ "method": "POST", "path": "/kpi-snapshots/recalculate", "roles": ["ROL-01"], 

"request": { "period_type": "enum[diario,semanal,mensual,anual]|required", "reference_date": "date|nullable (por defecto ahora en business.timezone)" }, 

"note": "Recálculo manual forzado (KpiService::calcular): atómico e idempotente desde las fuentes. Reemplaza la caché del período (útil ante snapshot corrupto, ERR-12B).", 

"response_200": "{ data: Collection<KpiSnapshotResource> }  // las instantáneas resultantes del período recalculado", 

"errors": [ { "http": 500, "code": "KPI_RECALCULATION_FAILED", "when": "Fallo no controlado durante el recálculo (rollback total; sin snapshots parciales)." } ] } 

], 

"reports": [ 

{ "method": "GET", "path": "/reports/{type}", "roles": ["ROL-01"], 

"path_param": { "type": "enum[ventas,cartera,inventario,caja,consolidado] (allowlist; desconocido → 404 sin filtrar)" }, 

"query": { "period_type": "enum[diario,semanal,mensual,anual]|opt", "from": "date(Y-m-d)|opt", "to": "date(Y-m-d)|opt|>=from", "branch_id": "int|opt|same_tenant" }, 

"note": "RF-12-01: reportes SOLO LECTURA, consolidados y trazables. Rangos interpretados en business.timezone con límites UTC; agregación en SQL (sin sumas masivas en PHP). No usa snapshots como fuente operativa. Devuelve período actual + período EQUIVALENTE anterior (mismo span, no solapado) + variación. cartera se acota por período (cohorte de facturas a crédito emitidas). Períodos vacíos → ceros deterministas.", 

"response_200": "ReportResource { type, period{from,to}, totals, comparisons{previous_total,delta,variation_pct}, series[], metadata{timezone,previous_period,utc_bounds,branch_id,generated_at} }" }, 

{ "method": "GET", "path": "/report-definitions", "roles": ["ROL-01", "ROL-02"], "query": { "report_type": "enum[ventas,cartera,inventario,caja,consolidado]|opt", "sort": "allowlist[id,name,report_type,created_at,updated_at]" }, "response_200": "Paginated<ReportDefinitionResource>" }, 

{ "method": "POST", "path": "/report-definitions", "roles": ["ROL-01", "ROL-02"], 

"request": { "name": "string(120)|required", "report_type": "enum[ventas,cartera,inventario,caja,consolidado]|required (allowlist)", "filters": "object|nullable { branch_id?:int(same_tenant), period_type?:enum, from?:date, to?:date } (estructura validada, NUNCA SQL libre)", "is_scheduled": "bool|opt", "schedule_cron": "string(50)|nullable" }, 

"note": "Definición reutilizable. user_id = sesión (no-repudio, fuera de fillable). is_scheduled/schedule_cron se persisten INERTES: el motor de envío programado es Fase 2.", 

"response_201": "ReportDefinitionResource" }, 

{ "method": "PUT", "path": "/report-definitions/{reportDefinition}", "roles": ["ROL-01", "ROL-02"], "request": "idéntico a POST con 'sometimes' (user_id inmutable)", "response_200": "ReportDefinitionResource" }, 

{ "method": "DELETE", "path": "/report-definitions/{reportDefinition}", "roles": ["ROL-01", "ROL-02"], "response_204": null } 

] 

}, "scheduled_jobs": { 

"note": "Exactamente DOS entradas kpi:snapshot, sin duplicados, cada una con withoutOverlapping. El comando recorre todos los tenants NO suspendidos SIN sesión, respeta business.timezone, aísla cada negocio, es idempotente y devuelve código ≠ 0 si algún negocio falla (sin snapshots parciales).", 

"kpi_snapshot_daily": { "command": "kpi:snapshot --period=diario", "frequency": "daily 02:00 (withoutOverlapping)", "action": "Recalcula KPIs diarios por negocio. Orden: KPI-01..05,07,08 y ticket_promedio → luego KPI-06." }, 

"kpi_snapshot_monthly": { "command": "kpi:snapshot --period=mensual", "frequency": "monthly day 1 02:30 (withoutOverlapping)", "action": "Recalcula KPIs mensuales." } 

}, 

"deferred": { 

"timezone_precise_cut": "Corte diario exacto vía CONVERT_TZ DENTRO de las vistas → Fase 2 (los totales de Fase 1 ya son exactos consultando la fuente con límites UTC).", 

"report_scheduling": "Motor de envío programado de reportes (report_definitions.is_scheduled) → Fase 2.", 

"kpi_inventory_gap": "Brecha venta↔inventario del KPI-01 (afinamiento) → Fase 2.", 

"derived_kpis": "margen y rotacion_inventario (cálculo por job) → Fase 2 cuando se requieran como metas activas." 

} 

} 

