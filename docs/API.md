# API PRAXIS v1

Referencia de la API REST que consume `praxis_client`. Cubre los 87 endpoints de `/api/v1`, agrupados por épica (E01 a E11).

- **URL base local:** `http://localhost:8000/api/v1` (stack de `docker compose`).
- **Formato:** JSON en UTF-8. Envía siempre `Accept: application/json`; si no, los errores de Laravel pueden llegar como HTML o redirección.
- **Fechas:** ISO 8601 en UTC (`2026-10-28T23:32:27+00:00`). Los campos de solo fecha usan `Y-m-d` y las horas `HH:mm`. Plazos y SLA se calculan en hora de Bogotá con días hábiles (lunes a viernes, 8:00-18:00, sin feriados).
- **Identificadores:** las incidencias usan UUID (`id`) y un código legible `codigo_ticket` con formato `TKT-AAAA-######`. El resto de recursos usa enteros.
- **Colección de Postman:** [`docs/praxis.postman_collection.json`](praxis.postman_collection.json).

Los ejemplos de respuesta salen de una ejecución real del flujo completo contra la API con los datos del seeder. Las listas largas se recortaron a uno o dos elementos y los tokens se acortaron.

## Índice

**E01 · Acceso, seguridad y usuarios**

| Método | Ruta | Qué hace |
|---|---|---|
| `POST` | [`/auth/login`](#post-auth-login) | Inicia sesión: devuelve el access token y deja el refresh token en la cookie HttpOnly `praxis_refresh`. |
| `POST` | [`/auth/refresh`](#post-auth-refresh) | Rota el refresh token: emite un access token nuevo y una cookie `praxis_refresh` nueva. |
| `GET` | [`/auth/me`](#get-auth-me) | Perfil del usuario autenticado: roles, rol activo, ruta de inicio, especialidades y permisos del rol activo. |
| `POST` | [`/auth/solicitar-recuperacion`](#post-auth-solicitar-recuperacion) | Envía el correo de restablecimiento de contraseña. |
| `GET` | [`/auth/restablecer-password/{token}`](#get-auth-restablecer-password-token) | Valida el enlace de recuperación antes de mostrar el formulario. |
| `POST` | [`/auth/restablecer-password`](#post-auth-restablecer-password) | Cambia la contraseña con el token del correo e invalida las sesiones abiertas. |
| `GET` | [`/admin/usuarios`](#get-admin-usuarios) | Lista paginada de usuarios con roles y especialidades. |
| `POST` | [`/admin/usuarios`](#post-admin-usuarios) | Crea un usuario con sus roles y especialidades. |
| `GET` | [`/admin/usuarios/{id}`](#get-admin-usuarios-id) | Detalle de un usuario. |
| `PUT` | [`/admin/usuarios/{id}`](#put-admin-usuarios-id) | Actualiza datos básicos y especialidades (los roles se cambian en `/roles`). |
| `PUT` | [`/admin/usuarios/{id}/roles`](#put-admin-usuarios-id-roles) | Reemplaza los roles del usuario. |
| `PATCH` | [`/admin/usuarios/{id}/estado`](#patch-admin-usuarios-id-estado) | Activa o inactiva (borrado lógico) un usuario. |
| `GET` | [`/admin/roles`](#get-admin-roles) | Catálogo de los 7 roles (D03). |
| `GET` | [`/admin/especialidades`](#get-admin-especialidades) | Catálogo de especialidades técnicas. |
| `POST` | [`/auth/logout`](#post-auth-logout) | Cierra la sesión: revoca el access token y el refresh token y borra la cookie. |

**E02 · Shell, navegación, panel y notificaciones**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/navegacion/menu`](#get-navegacion-menu) | Ítems del menú lateral del rol activo según la matriz D09. |
| `GET` | [`/panel/resumen`](#get-panel-resumen) | Contadores del panel de inicio: solicitudes propias por estado, pendientes de acción (E02/E14) y últimas actualizadas. |
| `GET` | [`/catalogo/glosario-estados`](#get-catalogo-glosario-estados) | Glosario de los 18 estados con significado para el usuario, color y si requieren acción del reportante (ayuda). |
| `GET` | [`/notificaciones`](#get-notificaciones) | Notificaciones del usuario, más recientes primero, con el contador `no_leidas` para el badge. |
| `PATCH` | [`/notificaciones/{notificacion}/leer`](#patch-notificaciones-notificacion-leer) | Marca una notificación como leída. |
| `PATCH` | [`/notificaciones/leer-todas`](#patch-notificaciones-leer-todas) | Marca todas las notificaciones del usuario como leídas. |

**E03 · Radicación, evidencias y duplicados**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/catalogo/ubicaciones/arbol-activo`](#get-catalogo-ubicaciones-arbol-activo) | Árbol sede > edificio > piso > espacio con solo las ubicaciones activas (selector en cascada del formulario). |
| `GET` | [`/catalogo/categorias`](#get-catalogo-categorias) | Categorías activas con sus subcategorías e indicadores (`exige_evidencia_fotografica`, `requiere_confirmacion_cierre`, `nivel_riesgo_base`). |
| `GET` | [`/catalogo/prioridades`](#get-catalogo-prioridades) | Prioridades con sus metas SLA en horas hábiles. |
| `POST` | [`/incidencias/evidencias/upload`](#post-incidencias-evidencias-upload) | Sube uno o varios archivos de evidencia. |
| `GET` | [`/incidencias/coincidencias-activas`](#get-incidencias-coincidencias-activas) | Incidencias abiertas (E01-E11, últimos 15 días) en el mismo espacio y categoría, para advertir posibles duplicados antes de radicar. |
| `POST` | [`/incidencias/radicar`](#post-incidencias-radicar) | Radica una incidencia nueva y genera su código `TKT-AAAA-######`. |
| `POST` | [`/incidencias/{incidencia}/suscribir-seguimiento`](#post-incidencias-incidencia-suscribir-seguimiento) | Suscribe al usuario a una incidencia ajena para recibir sus actualizaciones (en lugar de radicar un duplicado). |
| `GET` | [`/evidencias/{evidencia}/descargar`](#get-evidencias-evidencia-descargar) | Descarga el archivo de una evidencia. |

**E04 · Mis solicitudes (reportante)**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/mis-solicitudes`](#get-mis-solicitudes) | Solicitudes del usuario autenticado, más recientes primero. |
| `GET` | [`/mis-solicitudes/{incidencia}`](#get-mis-solicitudes-incidencia) | Ficha de seguimiento: datos, evidencias, historial público, aclaración pendiente, acciones disponibles y ticket principal si fue unificada. |
| `POST` | [`/mis-solicitudes/{incidencia}/aclaraciones`](#post-mis-solicitudes-incidencia-aclaraciones) | El reportante responde la aclaración pedida por el gestor. |
| `POST` | [`/incidencias/{incidencia}/cancelar-solicitante`](#post-incidencias-incidencia-cancelar-solicitante) | El creador cancela su propia solicitud mientras no tenga técnico asignado. |
| `POST` | [`/incidencias/{incidencia}/reabrir`](#post-incidencias-incidencia-reabrir) | El creador reabre una solicitud cerrada dentro de la ventana de garantía; la prioridad pasa a Alta y se alerta a coordinación. |
| `POST` | [`/incidencias/{incidencia}/confirmar`](#post-incidencias-incidencia-confirmar) | El reportante confirma que el problema quedó resuelto y califica el servicio. |
| `POST` | [`/incidencias/{incidencia}/rechazar-conformidad`](#post-incidencias-incidencia-rechazar-conformidad) | El reportante indica que el problema persiste; vuelve a corrección. |

**E05 · Triaje: revisión, duplicados, clasificación y riesgo**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/incidencias/{incidencia}`](#get-incidencias-incidencia) | Ficha operativa completa de una incidencia (para la mesa de ayuda, coordinación, técnicos y verificación). |
| `POST` | [`/incidencias/{incidencia}/iniciar-revision`](#post-incidencias-incidencia-iniciar-revision) | El gestor toma la incidencia para revisarla. |
| `POST` | [`/incidencias/{incidencia}/solicitar-aclaracion`](#post-incidencias-incidencia-solicitar-aclaracion) | Pide información adicional al reportante; fija un plazo de respuesta en días hábiles y lo notifica. |
| `GET` | [`/incidencias/{incidencia}/posibles-duplicados`](#get-incidencias-incidencia-posibles-duplicados) | Candidatos a duplicado ponderados por ubicación, categoría, similitud de descripción y estado abierto. |
| `POST` | [`/incidencias/{incidencia}/vincular-duplicado`](#post-incidencias-incidencia-vincular-duplicado) | Unifica la incidencia como duplicada de otra principal; su reportante pasa a seguir la principal. |
| `POST` | [`/incidencias/{incidencia}/descartar-duplicado`](#post-incidencias-incidencia-descartar-duplicado) | Descarta la sugerencia de duplicado y quita la marca `posible_duplicado`. |
| `POST` | [`/incidencias/{incidencia}/clasificar`](#post-incidencias-incidencia-clasificar) | Decide la procedencia. |
| `PATCH` | [`/incidencias/{incidencia}/prioridad`](#patch-incidencias-incidencia-prioridad) | Recalcula la prioridad de una incidencia ya clasificada y deja el cambio en el historial (RN-020). |
| `POST` | [`/incidencias/{incidencia}/evaluacion-riesgo`](#post-incidencias-incidencia-evaluacion-riesgo) | Registra la evaluación de riesgo. |

**E06 · Asignación y planificación**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/tecnicos/disponibles`](#get-tecnicos-disponibles) | Técnicos activos con su carga actual y el límite WIP; si se filtra por categoría, solo los de la especialidad requerida. |
| `POST` | [`/incidencias/{incidencia}/asignar`](#post-incidencias-incidencia-asignar) | Asigna (o reasigna) el técnico responsable y lo notifica. |
| `POST` | [`/incidencias/{incidencia}/programar`](#post-incidencias-incidencia-programar) | Aprueba el diagnóstico y programa la intervención (cuando no hacen falta recursos). |
| `PATCH` | [`/incidencias/{incidencia}/reprogramar`](#patch-incidencias-incidencia-reprogramar) | Cambia la fecha u hora del plan vigente y guarda la fecha anterior con el motivo. |
| `POST` | [`/incidencias/{incidencia}/devolver-diagnostico`](#post-incidencias-incidencia-devolver-diagnostico) | Devuelve el diagnóstico al técnico por insuficiente; debe volver a inspeccionar. |
| `POST` | [`/incidencias/{incidencia}/cerrar-no-confirmada`](#post-incidencias-incidencia-cerrar-no-confirmada) | Visto bueno del coordinador al descarte del técnico: cierra con resultado `NO_CONFIRMADA_EN_INSPECCION`. |

**E07 · Inspección, diagnóstico y ejecución**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/tecnico/tareas`](#get-tecnico-tareas) | Bandeja del técnico: sus incidencias activas (E06 a E11, E13 y E18), primero las de riesgo. |
| `POST` | [`/incidencias/{incidencia}/iniciar-inspeccion`](#post-incidencias-incidencia-iniciar-inspeccion) | El técnico asignado inicia la inspección en sitio. |
| `POST` | [`/incidencias/{incidencia}/diagnostico`](#post-incidencias-incidencia-diagnostico) | Registra el diagnóstico de un daño confirmado con la intervención propuesta; notifica a coordinación. |
| `POST` | [`/incidencias/{incidencia}/descartar-por-inspeccion`](#post-incidencias-incidencia-descartar-por-inspeccion) | El técnico documenta que el daño no existe; exige evidencia del estado funcional y espera el visto bueno del coordinador. |
| `POST` | [`/incidencias/{incidencia}/iniciar-atencion`](#post-incidencias-incidencia-iniciar-atencion) | El técnico inicia la intervención física. |
| `POST` | [`/incidencias/{incidencia}/registrar-intervencion`](#post-incidencias-incidencia-registrar-intervencion) | Registra lo realizado, el tiempo y los materiales, con evidencia obligatoria, y pasa a verificación. |
| `POST` | [`/incidencias/{incidencia}/suspender`](#post-incidencias-incidencia-suspender) | Suspende temporalmente la atención con causa, motivo y condiciones para reanudar; se comunica al reportante. |
| `POST` | [`/incidencias/{incidencia}/reanudar`](#post-incidencias-incidencia-reanudar) | Reanuda una incidencia suspendida y registra cuánto duró la pausa. |

**E08 · Recursos y autorizaciones**

| Método | Ruta | Qué hace |
|---|---|---|
| `POST` | [`/incidencias/{incidencia}/solicitudes-recursos`](#post-incidencias-incidencia-solicitudes-recursos) | Registra los recursos que faltan para la intervención. |
| `GET` | [`/autorizaciones/pendientes`](#get-autorizaciones-pendientes) | Bandeja de solicitudes de recursos pendientes de dictamen. |
| `POST` | [`/solicitudes-recursos/{id}/dictamen`](#post-solicitudes-recursos-id-dictamen) | Emite el dictamen sobre una solicitud que requiere autorización. |
| `PATCH` | [`/incidencias/{incidencia}/confirmar-recursos`](#patch-incidencias-incidencia-confirmar-recursos) | Confirma que todos los recursos llegaron y programa la intervención. |
| `POST` | [`/solicitudes-recursos/{id}/seguimientos`](#post-solicitudes-recursos-id-seguimientos) | Agrega una nota de seguimiento a una solicitud pendiente (no cambia el estado, RN-030). |
| `POST` | [`/incidencias/{incidencia}/anular`](#post-incidencias-incidencia-anular) | Cancelación administrativa por imposibilidad (p. |

**E09 · Verificación de calidad y cierre**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/verificacion/pendientes`](#get-verificacion-pendientes) | Bandeja de verificación: incidencias en E12, las más antiguas primero. |
| `POST` | [`/incidencias/{incidencia}/verificar`](#post-incidencias-incidencia-verificar) | Dictamen de calidad conforme. |
| `POST` | [`/incidencias/{incidencia}/rechazar-verificacion`](#post-incidencias-incidencia-rechazar-verificacion) | Dictamen no conforme: vuelve a corrección y suma un ciclo de reproceso. |
| `POST` | [`/incidencias/{incidencia}/planificar-correccion`](#post-incidencias-incidencia-planificar-correccion) | Programa la corrección tras un rechazo de verificación o de conformidad. |
| `POST` | [`/incidencias/{incidencia}/cierre-administrativo`](#post-incidencias-incidencia-cierre-administrativo) | Cierra por inactividad del reportante cuando venció el plazo de confirmación. |

**E10 · Consola, trazabilidad, SLA y analítica**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/consola/incidencias`](#get-consola-incidencias) | Consola de incidencias con filtros combinables, tiempo de atención y semáforo SLA por fila. |
| `GET` | [`/incidencias/{incidencia}/historial`](#get-incidencias-incidencia-historial) | Línea de tiempo de auditoría. |
| `GET` | [`/consola/plazos`](#get-consola-plazos) | Incidencias abiertas con su estado SLA (EN_PLAZO, PROXIMA_A_VENCER, VENCIDA) y un resumen con los totales. |
| `GET` | [`/analitica/metricas-basicas`](#get-analitica-metricas-basicas) | Indicadores del periodo: totales por estado y prioridad, MTTR, cumplimiento SLA y fallas recurrentes. |

**E11 · Parametrización (ubicaciones, categorías y parámetros)**

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | [`/admin/ubicaciones`](#get-admin-ubicaciones) | Árbol completo de ubicaciones, incluidas las inactivas. |
| `POST` | [`/admin/ubicaciones/{nivel}`](#post-admin-ubicaciones-nivel) | Crea una sede, edificio, piso o espacio. |
| `PUT` | [`/admin/ubicaciones/{nivel}/{id}`](#put-admin-ubicaciones-nivel-id) | Edita una ubicación (mismos campos que al crear, todos opcionales). |
| `PATCH` | [`/admin/ubicaciones/{nivel}/{id}/estado`](#patch-admin-ubicaciones-nivel-id-estado) | Activa o inactiva una ubicación (deja de salir en el árbol activo). |
| `DELETE` | [`/admin/ubicaciones/{nivel}/{id}`](#delete-admin-ubicaciones-nivel-id) | Elimina una ubicación sin incidencias ni niveles dependientes. |
| `GET` | [`/admin/categorias`](#get-admin-categorias) | Categorías y subcategorías, incluidas las inactivas. |
| `POST` | [`/admin/categorias`](#post-admin-categorias) | Crea una categoría o, con `categoria_padre_id`, una subcategoría. |
| `PUT` | [`/admin/categorias/{id}`](#put-admin-categorias-id) | Edita una categoría (mismos campos, todos opcionales). |
| `GET` | [`/admin/parametros-proceso`](#get-admin-parametros-proceso) | Metas SLA por prioridad y plazo de aclaraciones (lectura para R03). |
| `PUT` | [`/admin/parametros-proceso`](#put-admin-parametros-proceso) | Actualiza metas SLA por prioridad y el plazo de aclaraciones; invalida la caché. |
| `GET` | [`/admin/parametros`](#get-admin-parametros) | Matriz de prioridad, metas SLA y parámetros generales del sistema. |
| `PUT` | [`/admin/parametros`](#put-admin-parametros) | Actualiza la matriz Impacto × Urgencia, las metas SLA o los parámetros; aplica en caliente y queda auditado (valor anterior y nuevo). |
| `GET` | [`/admin/parametros/historial`](#get-admin-parametros-historial) | Eventos de auditoría de configuración (cambios de parámetros, SLA, matriz, categorías, ubicaciones). |

## Conceptos generales

### Login y access token

1. `POST /auth/login` con `email` y `password`.
2. La respuesta trae `access_token` (token Sanctum, dura 15 minutos: `expires_in: 900`), el perfil del usuario y `redirect_to`, la ruta del front a la que conviene ir (la ruta de inicio del rol o el `return_url` que se envió).
3. En cada petición protegida envía `Authorization: Bearer <access_token>`.

```http
POST /api/v1/auth/login
Content-Type: application/json
Accept: application/json

{"email": "coordinador@praxis.test", "password": "Praxis*2026"}
```

Guarda el access token solo en memoria (no en `localStorage`). Tras 5 intentos fallidos en 10 minutos la cuenta se bloquea 15 minutos (`423`).

### Refresh token en cookie

El login también deja la cookie `praxis_refresh`: HttpOnly, válida 7 días y con `path=/api/v1/auth`. El JavaScript del front no puede leerla; el navegador la envía sola a las rutas de autenticación.

- Cuando una petición responde `401`, llama a `POST /auth/refresh` con `credentials: 'include'` y repite la petición con el nuevo `access_token`.
- Cada refresh **rota** la cookie: el refresh token anterior deja de servir. Si alguien reutiliza uno ya rotado, el backend asume robo y revoca todas las sesiones de esa familia (el usuario debe volver a iniciar sesión).
- Si el refresh también responde `401`, manda al usuario al login.
- `POST /auth/logout` revoca ambos tokens y borra la cookie.

```js
// Ejemplo con fetch desde praxis_client
const r = await fetch(`${API}/auth/refresh`, {
  method: 'POST',
  credentials: 'include',            // imprescindible para que viaje la cookie
  headers: { Accept: 'application/json' },
});
```

El backend acepta credenciales CORS solo desde los orígenes de `FRONTEND_URL` (por defecto `http://localhost:3000`; admite varios separados por coma). En local, el `docker-compose.yml` deja la cookie con `Secure=false` y `SameSite=Lax`; en producción va con `Secure` y `SameSite=None` (configurable con `PRAXIS_REFRESH_COOKIE_SECURE` y `PRAXIS_REFRESH_COOKIE_SAMESITE`).

### Rol activo: cabecera `X-Active-Role`

Un usuario puede tener varios roles (por ejemplo, la docente del seeder es R01 y R06). Los permisos se evalúan contra el **rol activo**, no contra la suma de roles:

- Envía `X-Active-Role: R06` (código R01..R07) para elegir con qué rol actúa el usuario.
- Si no envías la cabecera, se usa el primer rol del usuario en orden R01..R07.
- Si envías un rol que el usuario no tiene, responde `403` y queda un evento de seguridad en auditoría.
- `GET /auth/me` y `GET /navegacion/menu` responden según el rol activo; úsalos al cambiar de rol para redibujar el menú.

Roles: R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma. En cada endpoint, "Roles (D09)" lista los roles activos que pueden llamarlo; R07 tiene acceso a todo lo que D09 marca como permitido.

Algunas acciones dependen además de **quién** es el usuario y no solo de su rol: confirmar, cancelar, reabrir o responder aclaraciones solo las puede hacer el creador de la solicitud, e inspeccionar o registrar la intervención solo el técnico asignado. En esos casos la API responde `403` aunque el rol sea correcto.

### Errores (RFC 7807)

Todos los errores llegan con `Content-Type: application/problem+json` y esta forma:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/conflicto-de-estado",
  "title": "Conflicto con el estado actual.",
  "status": 409,
  "detail": "La incidencia está en E03 (En revisión) y no puede pasar a E03 (En revisión).",
  "instance": "/api/v1/incidencias/01a184db-9e3a-7084-8e55-7c7c9ecc9d38/iniciar-revision",
  "regla": "D07",
  "estado_actual": "E03",
  "estado_destino": "E03"
}
```

- `detail` es un mensaje en español apto para mostrarse al usuario. Toda la API responde en español (validaciones, 401, 403, 404, 405, 429). En un `422` de validación, `detail` repite el primer error y cuántos hay además: `"El campo categoría es obligatorio. (y 4 errores más)"`.
- `errors` (solo en `422` de validación) trae los mensajes por campo, como en Laravel: `{"descripcion": ["La descripción debe tener al menos 20 caracteres."]}`.
- `regla` indica la regla de negocio que se incumplió (`RN-0xx`, `D07`, `HU-x.y`). Algunos errores añaden campos propios (`bloqueado_hasta`, `vence`, `alternativa`...).

| Código | `type` (sufijo) | Cuándo |
|---|---|---|
| 401 | `no-autenticado` | Sin token, token vencido o credenciales inválidas. Intenta un refresh. |
| 403 | `acceso-denegado` | El rol activo no tiene permiso (D09), el usuario no es el dueño o técnico asignado, o segregación de funciones. |
| 404 | `recurso-no-encontrado` | No existe o el usuario no puede verlo (las solicitudes ajenas responden 404, no 403). |
| 405 | `metodo-no-permitido` | Operación no soportada (p. ej. borrar usuarios o editar la bitácora). |
| 409 | `conflicto-de-estado` | La acción no aplica al estado actual de la incidencia (matriz D07) o a la situación del recurso. |
| 410 | `enlace-expirado` | Enlace de recuperación de contraseña vencido o usado. |
| 422 | `regla-de-negocio` | Validación de campos (`errors`) o regla de negocio incumplida (`regla`). |
| 423 | `cuenta-bloqueada` | Cuenta bloqueada por intentos fallidos. |
| 429 | `demasiadas-solicitudes` | Se superó el límite de peticiones. |

En cada endpoint, "Errores típicos" lista los casos que el front debe manejar. `401` y `403` aplican a todas las rutas protegidas; `409` aparece en casi todas las acciones sobre incidencias cuando el estado no corresponde (por ejemplo, un doble clic que repite la transición).

### Paginación

Los listados aceptan `page` (desde 1) y `per_page` (1 a 100). Hay dos formatos de respuesta:

- **Colecciones estándar** (`/mis-solicitudes`, `/notificaciones`, `/tecnico/tareas`, `/verificacion/pendientes`, `/admin/parametros/historial`): `data`, `links` (`first`, `last`, `prev`, `next`) y `meta` (`current_page`, `last_page`, `per_page`, `total`, `from`, `to`). Algunas añaden datos propios en `meta` (`sin_resultados`, `filtros`, `no_leidas`).
- **Consolas** (`/consola/incidencias`, `/admin/usuarios`): `data` y un `meta` compacto con `current_page`, `last_page`, `total` (y `per_page`, `sin_resultados` en la consola).

Los filtros de estado aceptan varios códigos separados por coma: `?estado=E02,E14`.

### Doble envío: cabecera `Idempotency-Key`

`POST /incidencias/radicar` acepta `Idempotency-Key` (máx. 100 caracteres). Genera un UUID cuando el usuario abre el formulario y reutilízalo en los reintentos de ese mismo envío:

- La primera vez crea la incidencia y responde `201`.
- Si llega otra vez la misma clave, no crea nada: devuelve la incidencia ya creada con `200`.

Así un doble clic o un reintento por mala conexión no genera dos tickets.

### Evidencias: subir primero, referenciar después

Los archivos no viajan dentro del JSON de radicación. El flujo es:

1. `POST /incidencias/evidencias/upload` como `multipart/form-data` con uno o varios `archivos[]`. Se aceptan JPG, PNG, WEBP o PDF (se valida el contenido real, no la extensión), hasta 10 MB cada uno y 4 por reporte (ambos parametrizables).
2. La respuesta trae un `id` y una `url` de descarga por archivo. En ese momento quedan "pendientes": sin incidencia y visibles solo para quien los subió.
3. Envía esos ids en el campo `evidencias` de `POST /incidencias/radicar`. La API los vincula a la incidencia y les asigna la etapa (REPORTE).

```bash
curl -X POST http://localhost:8000/api/v1/incidencias/evidencias/upload \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F "archivos[]=@grieta.jpg" -F "archivos[]=@techo.jpg"
# → {"data":[{"id":12,...},{"id":13,...}]}
# luego: POST /incidencias/radicar con "evidencias": [12, 13]
```

El mismo mecanismo sirve para aclaraciones, diagnóstico, descarte e intervención: se suben los archivos y se pasan sus ids en `evidencias`. También puedes enviar `incidencia_id` y `etapa` en el upload para adjuntarlos directamente a una incidencia existente.

Las categorías con `exige_evidencia_fotografica: true` (ver `/catalogo/categorias`) obligan a enviar al menos una evidencia al radicar (RN-005). Las URLs de descarga van firmadas y vencen a los 30 minutos; si una vence, vuelve a pedir el detalle de la incidencia.

### Límites de peticiones

- Login y refresh: 20 por minuto por IP.
- Recuperación de contraseña: 10 por minuto por IP.
- Resto de la API: 240 por minuto por usuario.

Al superarlos responde `429`.

## Usuarios de prueba

Los crea `php artisan migrate --seed`. Todos usan la contraseña **`Praxis*2026`**.

| Correo | Nombre | Roles | Especialidades |
|---|---|---|---|
| `reportante@praxis.test` | Laura Reportante | R01 | — |
| `gestor@praxis.test` | Gabriel Gestor | R02 | — |
| `coordinador@praxis.test` | Camila Coordinadora | R03 | — |
| `tecnico@praxis.test` | Tomás Técnico Electricista | R04 | Electricidad, General |
| `plomero@praxis.test` | Pedro Técnico Plomero | R04 | Hidrosanitario, Obra civil |
| `cerrajero@praxis.test` | Ciro Técnico Cerrajero | R04 | Cerrajería, Carpintería |
| `autorizador@praxis.test` | Andrea Autorizadora | R05 | — |
| `verificador@praxis.test` | Valentina Verificadora | R06 | — |
| `admin@praxis.test` | Alberto Administrador | R07 | — |
| `docente.verificador@praxis.test` | Diana Docente Verificadora | R01, R06 (multi-rol) | — |

Para asignar una incidencia de la categoría Eléctrica, usa a Tomás (`tecnico@praxis.test`): `POST /incidencias/{id}/asignar` exige que el técnico tenga la especialidad de la categoría.

## Estados y transiciones

Los 18 estados de D07. La máquina de estados del backend rechaza cualquier transición que no esté en esta tabla (`409`) o que el rol activo no pueda hacer (`403`). R07 puede ejecutar todas las transiciones de los roles operativos. "Creador" significa el usuario que radicó la solicitud, sea cual sea su rol activo.

| Estado | Significado para el usuario | Transiciones permitidas (destino · quién · endpoint) |
|---|---|---|
| **E01** Registrada | Tu reporte fue recibido y espera la revisión inicial de la mesa de ayuda. | → **E03** En revisión · R02 · `POST /incidencias/{id}/iniciar-revision`<br>→ **E05** Pendiente de asignación · R02 · `POST /incidencias/{id}/evaluacion-riesgo` con riesgo (pasa por E03 y E04)<br>→ **E15** Cerrada · R02 · `POST /incidencias/{id}/vincular-duplicado`<br>→ **E17** Cancelada · Creador · `POST /incidencias/{id}/cancelar-solicitante` |
| **E02** Pendiente de información | Necesitamos más información o fotos para entender el daño. Debes responder la aclaración. | → **E03** En revisión · Creador · `POST /mis-solicitudes/{id}/aclaraciones`<br>→ **E17** Cancelada · Creador / Sistema · `POST /incidencias/{id}/cancelar-solicitante` o vencimiento automático del plazo (`praxis:vencer-aclaraciones`) |
| **E03** En revisión | La mesa de ayuda está revisando si el daño corresponde a planta física y si ya fue reportado. | → **E02** Pendiente de información · R02, R03 · `POST /incidencias/{id}/solicitar-aclaracion`<br>→ **E04** En clasificación · R02 · `POST /incidencias/{id}/clasificar` (paso intermedio, continúa a E05)<br>→ **E16** No procedente · R02 · `POST /incidencias/{id}/clasificar` con `procedente=false`<br>→ **E15** Cerrada · R02 · `POST /incidencias/{id}/vincular-duplicado`<br>→ **E17** Cancelada · Creador · `POST /incidencias/{id}/cancelar-solicitante` |
| **E04** En clasificación | Se está definiendo la categoría y la prioridad de atención. | → **E05** Pendiente de asignación · R02 · `POST /incidencias/{id}/clasificar` o `evaluacion-riesgo` con riesgo<br>→ **E16** No procedente · R02 · `POST /incidencias/{id}/clasificar` con `procedente=false`<br>→ **E15** Cerrada · R02 · `POST /incidencias/{id}/vincular-duplicado`<br>→ **E17** Cancelada · Creador · `POST /incidencias/{id}/cancelar-solicitante` |
| **E05** Pendiente de asignación | Tu reporte fue validado y espera la asignación de un técnico. | → **E06** Pendiente de inspección · R03 · `POST /incidencias/{id}/asignar` |
| **E06** Pendiente de inspección | Un técnico fue asignado y realizará la inspección en sitio. | → **E07** En diagnóstico · R04 asignado · `POST /incidencias/{id}/iniciar-inspeccion`<br>→ **E18** Suspendida · R03, R04 · `POST /incidencias/{id}/suspender` |
| **E07** En diagnóstico | El técnico está determinando la causa del daño y lo que se necesita para repararlo. | → **E06** Pendiente de inspección · R03 · `POST /incidencias/{id}/devolver-diagnostico`<br>→ **E08** Pendiente de autorización · R03 · `POST /incidencias/{id}/solicitudes-recursos` con `requiere_autorizacion=true`<br>→ **E09** Pendiente de recursos · R03 · `POST /incidencias/{id}/solicitudes-recursos` con `requiere_autorizacion=false`<br>→ **E10** Programada · R03 · `POST /incidencias/{id}/programar`<br>→ **E15** Cerrada · R03 · `POST /incidencias/{id}/cerrar-no-confirmada` (tras `descartar-por-inspeccion` del técnico)<br>→ **E18** Suspendida · R03, R04 · `POST /incidencias/{id}/suspender` |
| **E08** Pendiente de autorización | La reparación requiere una autorización administrativa o presupuestal. | → **E10** Programada · R05 (R03 dentro del tope) · `POST /solicitudes-recursos/{id}/dictamen` APROBADA con `recursos_disponibles=true`<br>→ **E09** Pendiente de recursos · R05 (R03 dentro del tope) · `POST /solicitudes-recursos/{id}/dictamen` APROBADA sin recursos disponibles<br>→ **E07** En diagnóstico · R05 (R03 dentro del tope) · `POST /solicitudes-recursos/{id}/dictamen` RECHAZADA + `DEVOLVER_DIAGNOSTICO`<br>→ **E18** Suspendida · R05 (R03 dentro del tope) · `POST /solicitudes-recursos/{id}/dictamen` RECHAZADA + `SUSPENDER`<br>→ **E17** Cancelada · R05, R03 · `dictamen` RECHAZADA + `CANCELAR`, o `POST /incidencias/{id}/anular` (R03) |
| **E09** Pendiente de recursos | Se están consiguiendo los materiales o repuestos necesarios. | → **E10** Programada · R03 · `PATCH /incidencias/{id}/confirmar-recursos`<br>→ **E18** Suspendida · R03, R04 · `POST /incidencias/{id}/suspender`<br>→ **E17** Cancelada · R03 · `POST /incidencias/{id}/anular` |
| **E10** Programada | La intervención tiene fecha y cuadrilla programadas. | → **E11** En atención · R04 asignado · `POST /incidencias/{id}/iniciar-atencion`<br>→ **E18** Suspendida · R03, R04 · `POST /incidencias/{id}/suspender` |
| **E11** En atención | El personal técnico está ejecutando la reparación. | → **E12** Pendiente de verificación · R04 asignado · `POST /incidencias/{id}/registrar-intervencion`<br>→ **E18** Suspendida · R03, R04 · `POST /incidencias/{id}/suspender` |
| **E12** Pendiente de verificación | El trabajo terminó y está pendiente de la verificación de calidad. | → **E13** En corrección · R06 · `POST /incidencias/{id}/rechazar-verificacion`<br>→ **E14** Pendiente de confirmación · R06 · `POST /incidencias/{id}/verificar` (la categoría requiere confirmación)<br>→ **E15** Cerrada · R06 · `POST /incidencias/{id}/verificar` (la categoría no requiere confirmación) |
| **E13** En corrección | La verificación encontró defectos y el trabajo será corregido. | → **E10** Programada · R03 · `POST /incidencias/{id}/planificar-correccion` |
| **E14** Pendiente de confirmación | El trabajo fue verificado. Debes confirmar si el problema quedó resuelto. | → **E15** Cerrada · Creador / R03 · `POST /incidencias/{id}/confirmar` (creador) o `POST /incidencias/{id}/cierre-administrativo` (R03, vencido el plazo)<br>→ **E13** En corrección · Creador · `POST /incidencias/{id}/rechazar-conformidad` |
| **E15** Cerrada *(terminal salvo reapertura)* | La incidencia fue atendida y cerrada. | → **E13** En corrección · Creador · `POST /incidencias/{id}/reabrir` (si hubo intervención técnica)<br>→ **E05** Pendiente de asignación · Creador · `POST /incidencias/{id}/reabrir` (si no hubo intervención) |
| **E16** No procedente *(terminal)* | El reporte no corresponde a infraestructura física y fue descartado. | Ninguna. |
| **E17** Cancelada *(terminal)* | La solicitud fue cancelada con causa justificada. | Ninguna. |
| **E18** Suspendida | La atención está detenida temporalmente por una causa documentada. | → **E10** Programada · R03 · `POST /incidencias/{id}/reanudar` con `estado_destino=E10`<br>→ **E11** En atención · R03 · `POST /incidencias/{id}/reanudar` con `estado_destino=E11`<br>→ **E17** Cancelada · R03 · `POST /incidencias/{id}/anular` |

Dos candados adicionales:

- **RN-029:** no se puede pasar a E10 ni E11 mientras haya una solicitud de recursos pendiente de autorización (salvo las de emergencia).
- **RN-037:** no se puede cerrar una incidencia en E11, E12 o E13; primero debe terminar la verificación o la corrección.

La lista de estados con su color y texto de ayuda está en `GET /catalogo/glosario-estados`.

## Endpoints por épica

Cada endpoint indica los roles permitidos, el cambio de estado que provoca, los campos con su validación, un ejemplo de respuesta exitosa y los errores típicos. Las rutas son relativas a `/api/v1`.


---

## E01 · Acceso, seguridad y usuarios

<a id="post-auth-login"></a>

#### `POST /api/v1/auth/login`

Inicia sesión: devuelve el access token y deja el refresh token en la cookie HttpOnly `praxis_refresh`.

- **Roles (D09):** Público (sin token).
- **Estado:** no cambia el estado de la incidencia.
- **Nota:** Límite: 20 peticiones/min por IP.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `email` | string | Obligatorio, correo válido, máx. 255. |
| `password` | string | Obligatorio, máx. 255. |
| `return_url` | string | Opcional. Ruta interna a la que volver tras el login (deep-link). Si no es una ruta interna se ignora y se usa la ruta de inicio del rol. |

**Ejemplo de petición**

```json
{
  "email": "reportante@praxis.test",
  "password": "Praxis*2026"
}
```

**Respuesta 200**

```json
{
  "access_token": "1|tq8f…(token Sanctum)",
  "token_type": "Bearer",
  "expires_in": 900,
  "refresh_expires_in": 604800,
  "redirect_to": "/panel",
  "usuario": {
    "id": 1,
    "nombre": "Laura Reportante",
    "email": "reportante@praxis.test",
    "telefono": null,
    "activo": true,
    "roles": [
      {
        "codigo": "R01",
        "nombre": "Reportante",
        "ruta_inicio": "/panel"
      }
    ],
    "rol_activo": {
      "codigo": "R01",
      "nombre": "Reportante"
    },
    "ruta_inicio": "/panel",
    "especialidades": [],
    "permisos": [
      {
        "modulo": "PANEL",
        "nivel_acceso": "TOTAL"
      }
    ]
  }
}
```

**Errores típicos**

- `401` Credenciales inválidas (mensaje genérico, no revela si el correo existe).
- `403` Usuario inactivo (`regla: HU-1.1`).
- `423` Cuenta bloqueada 15 min tras 5 intentos fallidos en 10 min; trae `bloqueado_hasta`.
- `422` Validación de campos (`errors` trae el detalle por campo).
- `429` Más de 20 intentos por minuto desde la misma IP.

<a id="post-auth-refresh"></a>

#### `POST /api/v1/auth/refresh`

Rota el refresh token: emite un access token nuevo y una cookie `praxis_refresh` nueva.

- **Roles (D09):** Público (sin token).
- **Estado:** no cambia el estado de la incidencia.
- **Nota:** La cookie tiene `path=/api/v1/auth`, así que solo viaja a las rutas de autenticación.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `refresh_token` | string | Opcional. Solo para clientes que no usan la cookie; el navegador envía la cookie automáticamente con `credentials: 'include'`. |

**Respuesta 200**

```json
{
  "access_token": "1|tq8f…(token Sanctum)",
  "token_type": "Bearer",
  "expires_in": 900,
  "refresh_expires_in": 604800,
  "redirect_to": null,
  "usuario": {
    "id": 2,
    "nombre": "Gabriel Gestor",
    "email": "gestor@praxis.test",
    "telefono": null,
    "activo": true,
    "roles": [
      {
        "codigo": "R02",
        "nombre": "Gestor de incidencias",
        "ruta_inicio": "/revision"
      }
    ],
    "rol_activo": {
      "codigo": "R02",
      "nombre": "Gestor de incidencias"
    },
    "ruta_inicio": "/revision",
    "especialidades": [],
    "permisos": [
      {
        "modulo": "PANEL",
        "nivel_acceso": "TOTAL"
      }
    ]
  }
}
```

**Errores típicos**

- `401` No llegó refresh token, es inválido, venció o ya se usó. Reusar un refresh token ya rotado revoca todas las sesiones de esa familia.

<a id="get-auth-me"></a>

#### `GET /api/v1/auth/me`

Perfil del usuario autenticado: roles, rol activo, ruta de inicio, especialidades y permisos del rol activo.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": {
    "id": 1,
    "nombre": "Laura Reportante",
    "email": "reportante@praxis.test",
    "telefono": null,
    "activo": true,
    "roles": [
      {
        "codigo": "R01",
        "nombre": "Reportante",
        "ruta_inicio": "/panel"
      }
    ],
    "rol_activo": {
      "codigo": "R01",
      "nombre": "Reportante"
    },
    "ruta_inicio": "/panel",
    "especialidades": [],
    "permisos": [
      {
        "modulo": "PANEL",
        "nivel_acceso": "TOTAL"
      }
    ]
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` `X-Active-Role` con un rol que el usuario no tiene.

<a id="post-auth-solicitar-recuperacion"></a>

#### `POST /api/v1/auth/solicitar-recuperacion`

Envía el correo de restablecimiento de contraseña. Responde igual exista o no el correo.

- **Roles (D09):** Público (sin token).
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `email` | string | Obligatorio, correo válido. |

**Ejemplo de petición**

```json
{
  "email": "reportante@praxis.test"
}
```

**Respuesta 200**

```json
{
  "mensaje": "Si el correo se encuentra registrado, hemos enviado las instrucciones a su bandeja de entrada"
}
```

**Errores típicos**

- `422` Validación de campos (`errors` trae el detalle por campo).
- `429` Más de 10 peticiones/min por IP.

<a id="get-auth-restablecer-password-token"></a>

#### `GET /api/v1/auth/restablecer-password/{token}`

Valida el enlace de recuperación antes de mostrar el formulario.

- **Roles (D09):** Público (sin token).
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "valido": true
}
```

**Errores típicos**

- `410` Enlace inexistente, usado o vencido (30 min).

Ejemplo de error `410`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/enlace-expirado",
  "title": "Enlace inválido.",
  "status": 410,
  "detail": "El enlace de restablecimiento ha expirado. Por favor solicite uno nuevo"
}
```

<a id="post-auth-restablecer-password"></a>

#### `POST /api/v1/auth/restablecer-password`

Cambia la contraseña con el token del correo e invalida las sesiones abiertas.

- **Roles (D09):** Público (sin token).
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `token` | string | Obligatorio, el token del enlace. |
| `password` | string | Obligatorio, mín. 8, mayúsculas y minúsculas, números y símbolos. |
| `password_confirmation` | string | Obligatorio, igual a `password`. |

**Respuesta 200**

```json
{
  "mensaje": "Contraseña actualizada correctamente. Inicie sesión con su nueva clave.",
  "redirect_to": "/login"
}
```

**Errores típicos**

- `410` Token vencido o ya usado.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-admin-usuarios"></a>

#### `GET /api/v1/admin/usuarios`

Lista paginada de usuarios con roles y especialidades.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `q` | string | Busca en nombre o correo. |
| `rol` | string | R01..R07. |
| `activo` | boolean |  |
| `per_page` | int | 1-100, por defecto 20. |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 6,
      "nombre": "Ciro Técnico Cerrajero",
      "email": "cerrajero@praxis.test",
      "telefono": null,
      "activo": true,
      "bloqueado_hasta": null,
      "ultimo_acceso_en": null,
      "roles": [
        {
          "codigo": "R04",
          "nombre": "Técnico de mantenimiento"
        }
      ],
      "especialidades": [
        {
          "codigo": "CERRAJERIA",
          "nombre": "Cerrajero"
        }
      ],
      "fecha_creacion": "2026-10-28T23:55:29+00:00"
    },
    {
      "id": 5,
      "nombre": "Pedro Técnico Plomero",
      "email": "plomero@praxis.test",
      "telefono": null,
      "activo": true,
      "bloqueado_hasta": null,
      "ultimo_acceso_en": null,
      "roles": [
        {
          "codigo": "R04",
          "nombre": "Técnico de mantenimiento"
        }
      ],
      "especialidades": [
        {
          "codigo": "HIDROSANITARIO",
          "nombre": "Plomero / Hidrosanitario"
        }
      ],
      "fecha_creacion": "2026-10-28T23:55:29+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "total": 3
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="post-admin-usuarios"></a>

#### `POST /api/v1/admin/usuarios`

Crea un usuario con sus roles y especialidades.

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `nombre` | string | Obligatorio, máx. 255. |
| `email` | string | Obligatorio, correo único. |
| `telefono` | string | Opcional, máx. 30. |
| `password` | string | Opcional, mín. 8 con mayúsculas, minúsculas, números y símbolos. |
| `roles` | string[] | Obligatorio, al menos uno: R01..R07. |
| `especialidades` | string[] | Opcional, códigos de `/admin/especialidades`. |
| `motivo` | string | Opcional, máx. 500 (queda en auditoría). |

**Ejemplo de petición**

```json
{
  "nombre": "Nora Técnica",
  "email": "nora@praxis.test",
  "telefono": "3001234567",
  "password": "Clave*Segura1",
  "roles": [
    "R04"
  ],
  "especialidades": [
    "ELECTRICIDAD"
  ]
}
```

**Respuesta 201**

```json
{
  "data": {
    "id": 11,
    "nombre": "Nora Técnica",
    "email": "nora@praxis.test",
    "telefono": "3001234567",
    "activo": true,
    "bloqueado_hasta": null,
    "ultimo_acceso_en": null,
    "roles": [
      {
        "codigo": "R04",
        "nombre": "Técnico de mantenimiento"
      }
    ],
    "especialidades": [
      {
        "codigo": "ELECTRICIDAD",
        "nombre": "Electricista"
      }
    ],
    "fecha_creacion": "2026-10-28T23:55:53+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-admin-usuarios-id"></a>

#### `GET /api/v1/admin/usuarios/{id}`

Detalle de un usuario.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": {
    "id": 11,
    "nombre": "Nora Técnica",
    "email": "nora@praxis.test",
    "telefono": "3001234567",
    "activo": true,
    "bloqueado_hasta": null,
    "ultimo_acceso_en": null,
    "roles": [
      {
        "codigo": "R04",
        "nombre": "Técnico de mantenimiento"
      }
    ],
    "especialidades": [
      {
        "codigo": "ELECTRICIDAD",
        "nombre": "Electricista"
      }
    ],
    "fecha_creacion": "2026-10-28T23:55:53+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.

<a id="put-admin-usuarios-id"></a>

#### `PUT /api/v1/admin/usuarios/{id}`

Actualiza datos básicos y especialidades (los roles se cambian en `/roles`).

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `nombre` | string | Opcional, máx. 255. |
| `email` | string | Opcional, correo único. |
| `telefono` | string | Opcional. |
| `password` | string | Opcional, misma política que al crear. |
| `especialidades` | string[] | Opcional, reemplaza la lista. |
| `motivo` | string | Opcional. |
| `roles` | - | Prohibido aquí: usar `PUT /admin/usuarios/{id}/roles`. |

**Ejemplo de petición**

```json
{
  "telefono": "3109876543",
  "especialidades": [
    "ELECTRICIDAD",
    "GENERAL"
  ]
}
```

**Respuesta 200**

```json
{
  "data": {
    "id": 11,
    "nombre": "Nora Técnica",
    "email": "nora@praxis.test",
    "telefono": "3109876543",
    "activo": true,
    "bloqueado_hasta": null,
    "ultimo_acceso_en": null,
    "roles": [
      {
        "codigo": "R04",
        "nombre": "Técnico de mantenimiento"
      }
    ],
    "especialidades": [
      {
        "codigo": "ELECTRICIDAD",
        "nombre": "Electricista"
      }
    ],
    "fecha_creacion": "2026-10-28T23:55:53+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="put-admin-usuarios-id-roles"></a>

#### `PUT /api/v1/admin/usuarios/{id}/roles`

Reemplaza los roles del usuario.

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `roles` | string[] | Obligatorio, al menos uno: R01..R07. |
| `motivo` | string | Opcional, máx. 500. |

**Ejemplo de petición**

```json
{
  "roles": [
    "R04",
    "R06"
  ],
  "motivo": "Apoyará verificaciones."
}
```

**Respuesta 200**

```json
{
  "data": {
    "id": 11,
    "nombre": "Nora Técnica",
    "email": "nora@praxis.test",
    "telefono": "3109876543",
    "activo": true,
    "bloqueado_hasta": null,
    "ultimo_acceso_en": null,
    "roles": [
      {
        "codigo": "R04",
        "nombre": "Técnico de mantenimiento"
      }
    ],
    "especialidades": [
      {
        "codigo": "ELECTRICIDAD",
        "nombre": "Electricista"
      }
    ],
    "fecha_creacion": "2026-10-28T23:55:53+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` Dejaría al sistema sin ningún administrador R07 activo.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="patch-admin-usuarios-id-estado"></a>

#### `PATCH /api/v1/admin/usuarios/{id}/estado`

Activa o inactiva (borrado lógico) un usuario. Inactivar revoca sus sesiones.

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `activo` | boolean | Obligatorio. |
| `motivo` | string | Opcional, máx. 500. |

**Ejemplo de petición**

```json
{
  "activo": false,
  "motivo": "Fin de contrato."
}
```

**Respuesta 200**

```json
{
  "data": {
    "id": 11,
    "nombre": "Nora Técnica",
    "email": "nora@praxis.test",
    "telefono": "3109876543",
    "activo": false,
    "bloqueado_hasta": null,
    "ultimo_acceso_en": null,
    "roles": [
      {
        "codigo": "R04",
        "nombre": "Técnico de mantenimiento"
      }
    ],
    "especialidades": [
      {
        "codigo": "ELECTRICIDAD",
        "nombre": "Electricista"
      }
    ],
    "fecha_creacion": "2026-10-28T23:55:53+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` Inactivaría al último R07 activo.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-admin-roles"></a>

#### `GET /api/v1/admin/roles`

Catálogo de los 7 roles (D03).

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 1,
      "codigo": "R01",
      "nombre": "Reportante",
      "descripcion": "Comunidad universitaria: registra incidencias, responde aclaraciones y confirma la conformidad del cierre.",
      "ruta_inicio": "/panel"
    },
    {
      "id": 2,
      "codigo": "R02",
      "nombre": "Gestor de incidencias",
      "descripcion": "Mesa de ayuda de infraestructura: revisa reportes, solicita información, identifica duplicados y clasifica.",
      "ruta_inicio": "/revision"
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="get-admin-especialidades"></a>

#### `GET /api/v1/admin/especialidades`

Catálogo de especialidades técnicas.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 6,
      "codigo": "CARPINTERIA",
      "nombre": "Carpintero",
      "activo": true
    },
    {
      "id": 3,
      "codigo": "CERRAJERIA",
      "nombre": "Cerrajero",
      "activo": true
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="post-auth-logout"></a>

#### `POST /api/v1/auth/logout`

Cierra la sesión: revoca el access token y el refresh token y borra la cookie.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Body:** vacío.

**Respuesta 200**

```json
{
  "mensaje": "Sesión cerrada correctamente."
}
```

**Errores típicos**

- `401` Sin token o token vencido.


---

## E02 · Shell, navegación, panel y notificaciones

<a id="get-navegacion-menu"></a>

#### `GET /api/v1/navegacion/menu`

Ítems del menú lateral del rol activo según la matriz D09.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "rol_activo": "R01",
  "data": [
    {
      "codigo": "PANEL",
      "etiqueta": "Inicio",
      "ruta": "/panel",
      "icono": "home",
      "grupo": "General",
      "solo_lectura": false,
      "nivel_acceso": "TOTAL"
    },
    {
      "codigo": "NUEVO_REPORTE",
      "etiqueta": "Nuevo reporte",
      "ruta": "/reportes/nuevo",
      "icono": "plus",
      "grupo": "General",
      "solo_lectura": false,
      "nivel_acceso": "TOTAL"
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.

<a id="get-panel-resumen"></a>

#### `GET /api/v1/panel/resumen`

Contadores del panel de inicio: solicitudes propias por estado, pendientes de acción (E02/E14) y últimas actualizadas.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": {
    "total": 0,
    "por_estado": [
      {
        "codigo": "E01",
        "nombre": "Registrada",
        "total": 0
      }
    ],
    "requieren_accion": {
      "pendiente_informacion": 0,
      "pendiente_confirmacion": 0
    },
    "activas": 0,
    "recientes": []
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.

<a id="get-catalogo-glosario-estados"></a>

#### `GET /api/v1/catalogo/glosario-estados`

Glosario de los 18 estados con significado para el usuario, color y si requieren acción del reportante (ayuda).

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "codigo": "E01",
      "nombre": "Registrada",
      "significado": "Tu reporte fue recibido y espera la revisión inicial de la mesa de ayuda.",
      "color": "azul",
      "categoria": "ABIERTO",
      "requiere_accion_reportante": false,
      "es_terminal": false
    },
    {
      "codigo": "E02",
      "nombre": "Pendiente de información",
      "significado": "Necesitamos más información o fotos para entender el daño. Debes responder la aclaración.",
      "color": "naranja",
      "categoria": "EN_CURSO",
      "requiere_accion_reportante": true,
      "es_terminal": false
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.

<a id="get-notificaciones"></a>

#### `GET /api/v1/notificaciones`

Notificaciones del usuario, más recientes primero, con el contador `no_leidas` para el badge.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `solo_no_leidas` | boolean | Opcional. |
| `per_page` | int | 1-100. |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 54,
      "tipo": "CANCELACION",
      "titulo": "Tu solicitud TKT-2026-000007 fue cancelada",
      "mensaje": "El laboratorio será remodelado y el punto se elimina.",
      "leida": false,
      "incidencia": {
        "id": "01a184f1-057c-7148-87ac-a869b5e6310f",
        "codigo_ticket": "TKT-2026-000007"
      },
      "fecha_envio": "2026-10-28T23:55:51+00:00",
      "fecha_lectura": null
    },
    {
      "id": 44,
      "tipo": "CIERRE_NO_CONFIRMADA",
      "titulo": "Resultado de la inspección de TKT-2026-000005",
      "mensaje": "La inspección técnica no confirmó el daño reportado: Las chispas provenían de una extensión personal ya retirada.",
      "leida": false,
      "incidencia": {
        "id": "01a184f0-fa65-737d-98bb-78a86c49a53a",
        "codigo_ticket": "TKT-2026-000005"
      },
      "fecha_envio": "2026-10-28T23:55:48+00:00",
      "fecha_lectura": null
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/notificaciones?page=1",
    "last": "http://localhost:8000/api/v1/notificaciones?page=4",
    "prev": null,
    "next": "http://localhost:8000/api/v1/notificaciones?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "links": [
      {
        "url": null,
        "label": "&laquo; Anterior",
        "page": null,
        "active": false
      }
    ],
    "path": "http://localhost:8000/api/v1/notificaciones",
    "per_page": 3,
    "to": 3,
    "total": 10,
    "no_leidas": 10
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.

<a id="patch-notificaciones-notificacion-leer"></a>

#### `PATCH /api/v1/notificaciones/{notificacion}/leer`

Marca una notificación como leída.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Body:** vacío.

**Respuesta 200**

```json
{
  "data": {
    "id": 54,
    "tipo": "CANCELACION",
    "titulo": "Tu solicitud TKT-2026-000007 fue cancelada",
    "mensaje": "El laboratorio será remodelado y el punto se elimina.",
    "leida": true,
    "incidencia": {
      "id": "01a184f1-057c-7148-87ac-a869b5e6310f",
      "codigo_ticket": "TKT-2026-000007"
    },
    "fecha_envio": "2026-10-28T23:55:51+00:00",
    "fecha_lectura": "2026-10-28T23:55:53+00:00"
  },
  "meta": {
    "no_leidas": 9
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `404` La notificación no existe o no es del usuario.

<a id="patch-notificaciones-leer-todas"></a>

#### `PATCH /api/v1/notificaciones/leer-todas`

Marca todas las notificaciones del usuario como leídas.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Body:** vacío.

**Respuesta 200**

```json
{
  "marcadas": 9,
  "meta": {
    "no_leidas": 0
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.


---

## E03 · Radicación, evidencias y duplicados

<a id="get-catalogo-ubicaciones-arbol-activo"></a>

#### `GET /api/v1/catalogo/ubicaciones/arbol-activo`

Árbol sede > edificio > piso > espacio con solo las ubicaciones activas (selector en cascada del formulario).

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 1,
      "codigo": "SEDE-PRINCIPAL",
      "nombre": "Campus Principal",
      "edificios": [
        {
          "id": 1,
          "codigo": "BLOQUE-A",
          "nombre": "Bloque A",
          "pisos": [
            {
              "id": 1,
              "codigo": "P1",
              "nombre": "Piso 1",
              "nivel": 1,
              "espacios": [
                {
                  "id": 1,
                  "codigo": "A-101",
                  "nombre": "Aula 101",
                  "tipo": "AULA"
                }
              ]
            }
          ]
        }
      ]
    },
    {
      "id": 2,
      "codigo": "SEDE-NORTE",
      "nombre": "Sede Norte",
      "edificios": [
        {
          "id": 3,
          "codigo": "BLOQUE-C",
          "nombre": "Bloque C",
          "pisos": [
            {
              "id": 6,
              "codigo": "P1",
              "nombre": "Piso 1",
              "nivel": 1,
              "espacios": [
                {
                  "id": 11,
                  "codigo": "C-101",
                  "nombre": "Aula 101",
                  "tipo": "AULA"
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.

<a id="get-catalogo-categorias"></a>

#### `GET /api/v1/catalogo/categorias`

Categorías activas con sus subcategorías e indicadores (`exige_evidencia_fotografica`, `requiere_confirmacion_cierre`, `nivel_riesgo_base`).

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 26,
      "codigo": "CLIMATIZACION",
      "nombre": "Aire Acondicionado y Climatización",
      "descripcion": null,
      "exige_evidencia_fotografica": false,
      "requiere_confirmacion_cierre": true,
      "nivel_riesgo_base": "BAJO",
      "especialidad": {
        "id": 5,
        "codigo": "CLIMATIZACION",
        "nombre": "Técnico de climatización"
      },
      "subcategorias": [
        {
          "id": 27,
          "codigo": "CLIMATIZACION_1",
          "nombre": "Aire acondicionado",
          "exige_evidencia_fotografica": false
        }
      ]
    },
    {
      "id": 22,
      "codigo": "CERRAJERIA",
      "nombre": "Cerrajería",
      "descripcion": null,
      "exige_evidencia_fotografica": false,
      "requiere_confirmacion_cierre": true,
      "nivel_riesgo_base": "BAJO",
      "especialidad": {
        "id": 3,
        "codigo": "CERRAJERIA",
        "nombre": "Cerrajero"
      },
      "subcategorias": [
        {
          "id": 23,
          "codigo": "CERRAJERIA_1",
          "nombre": "Chapas",
          "exige_evidencia_fotografica": false
        }
      ]
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.

<a id="get-catalogo-prioridades"></a>

#### `GET /api/v1/catalogo/prioridades`

Prioridades con sus metas SLA en horas hábiles.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 1,
      "codigo": "CRITICA",
      "nombre": "Crítica",
      "nivel": 4,
      "horas_primera_respuesta": 1,
      "horas_resolucion": 6,
      "color": "rojo"
    },
    {
      "id": 2,
      "codigo": "ALTA",
      "nombre": "Alta",
      "nivel": 3,
      "horas_primera_respuesta": 4,
      "horas_resolucion": 24,
      "color": "naranja"
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.

<a id="post-incidencias-evidencias-upload"></a>

#### `POST /api/v1/incidencias/evidencias/upload`

Sube uno o varios archivos de evidencia. Devuelve sus `id` para referenciarlos después.

- **Roles (D09):** Cualquier usuario autenticado.
- **Estado:** no cambia el estado de la incidencia.
- **Content-Type:** `multipart/form-data`.
- **Nota:** Sin `incidencia_id` el archivo queda pendiente y el `etapa` de la respuesta es REPORTE; la etapa real se asigna cuando otro endpoint lo referencia (radicar, diagnóstico, intervención, etc.).

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `archivos[]` | file[] | Obligatorio, 1 a 4 archivos (parámetro `MAX_EVIDENCIAS_POR_REPORTE`), máx. 10 MB c/u (`TAMANO_MAXIMO_EVIDENCIA_MB`). Solo JPG, PNG, WEBP o PDF, validados por contenido. |
| `incidencia_id` | uuid | Opcional. Si se envía, el archivo queda adjunto de inmediato a esa incidencia. |
| `etapa` | string | Opcional con `incidencia_id`: REPORTE, ACLARACION, INSPECCION, EJECUCION o VERIFICACION. |

**Respuesta 201**

```json
{
  "data": [
    {
      "id": 1,
      "etapa": "REPORTE",
      "nombre_original": "tablero.png",
      "mime_type": "image/png",
      "tamano_bytes": 73,
      "hash_sha256": "21391377d875154ec5cc383409732159c8c82a47b00eeb6d2970a474846dd138",
      "fecha_carga": "2026-10-28T23:55:38+00:00",
      "url": "http://localhost:8000/api/v1/evidencias/1/descargar?expires=1793233538&signature=…"
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` Con `incidencia_id`: el usuario no puede adjuntar en esa etapa (p. ej. INSPECCION solo el técnico asignado o R03/R07).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-incidencias-coincidencias-activas"></a>

#### `GET /api/v1/incidencias/coincidencias-activas`

Incidencias abiertas (E01-E11, últimos 15 días) en el mismo espacio y categoría, para advertir posibles duplicados antes de radicar.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `espacio_fisico_id` | int | Obligatorio, existe. |
| `categoria_id` | int | Opcional, existe. |

**Respuesta 200**

```json
{
  "hay_coincidencias": true,
  "data": [
    {
      "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "codigo_ticket": "TKT-2026-000001",
      "resumen": "Luminaria apagada en Aula 101",
      "categoria": {
        "id": 1,
        "nombre": "Eléctrica"
      },
      "estado": {
        "codigo": "E01",
        "nombre": "Registrada"
      },
      "fecha_registro": "2026-10-28T23:55:38+00:00",
      "es_mia": false,
      "ya_suscrito": false
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-radicar"></a>

#### `POST /api/v1/incidencias/radicar`

Radica una incidencia nueva y genera su código `TKT-AAAA-######`.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** — → E01
- **Cabecera `Idempotency-Key`:** Opcional. Si se repite con el mismo valor, devuelve la incidencia ya creada (200) en lugar de duplicarla.
- **Nota:** Responde 201 al crear y 200 si la `Idempotency-Key` ya se había usado.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `sede_id` | int | Obligatorio, existe. |
| `edificio_id` | int | Obligatorio, existe. |
| `piso_id` | int | Obligatorio, existe. |
| `espacio_fisico_id` | int | Obligatorio, existe y debe pertenecer al piso/edificio/sede enviados y estar activo (RN-003). |
| `categoria_id` | int | Obligatorio, categoría raíz activa. |
| `subcategoria_id` | int | Opcional, subcategoría activa de `categoria_id`. |
| `resumen` | string | Opcional, máx. 150. |
| `descripcion` | string | Obligatorio, 20 a 500 caracteres. |
| `canal_radicacion` | string | Opcional: WEB o MOVIL. |
| `es_riesgo_inmediato` | boolean | Opcional. Si es `true`, la prioridad queda Crítica y se alerta a R02 y R03. |
| `descripcion_riesgo` | string | Obligatorio si `es_riesgo_inmediato` es `true`, máx. 500. |
| `es_problema_diferente` | boolean | Opcional. El usuario confirma que no es el mismo daño de una coincidencia mostrada. |
| `evidencias` | int[] | Opcional, ids devueltos por `/incidencias/evidencias/upload`. Obligatorio al menos uno si la categoría o subcategoría exige foto (RN-005). Máx. 4. |

**Ejemplo de petición**

```json
{
  "sede_id": 1,
  "edificio_id": 1,
  "piso_id": 1,
  "espacio_fisico_id": 1,
  "categoria_id": 1,
  "subcategoria_id": 2,
  "resumen": "Luminaria apagada en Aula 101",
  "descripcion": "La luminaria del fondo del aula no enciende desde ayer en la tarde.",
  "canal_radicacion": "WEB",
  "es_riesgo_inmediato": false,
  "evidencias": [
    1
  ]
}
```

**Respuesta 201**

```json
{
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E01",
      "nombre": "Registrada"
    },
    "posible_duplicado": false,
    "es_riesgo_inmediato": false,
    "fecha_registro": "2026-10-28T23:55:38+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Campos inválidos, ubicación inconsistente o inactiva (RN-003), falta la foto obligatoria (RN-005) o las evidencias no son del usuario.

<a id="post-incidencias-incidencia-suscribir-seguimiento"></a>

#### `POST /api/v1/incidencias/{incidencia}/suscribir-seguimiento`

Suscribe al usuario a una incidencia ajena para recibir sus actualizaciones (en lugar de radicar un duplicado).

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body:** vacío.

**Respuesta 200**

```json
{
  "mensaje": "Ahora recibirá las actualizaciones de TKT-2026-000001.",
  "redirect_to": "/mis-solicitudes"
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` La incidencia ya terminó o el usuario es su creador.

<a id="get-evidencias-evidencia-descargar"></a>

#### `GET /api/v1/evidencias/{evidencia}/descargar`

Descarga el archivo de una evidencia. Se usa la `url` firmada que traen las respuestas (vence en 30 min); no requiere token.

- **Roles (D09):** Público (sin token).
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

`(binario con el Content-Type del archivo)`

**Errores típicos**

- `403` Firma inválida o vencida: pedir de nuevo el detalle para obtener una URL fresca.

Ejemplo de error `403`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/acceso-denegado",
  "title": "Acceso denegado.",
  "status": 403,
  "detail": "El enlace no es válido o ya venció. Solicite uno nuevo.",
  "instance": "/api/v1/evidencias/1/descargar"
}
```


---

## E04 · Mis solicitudes (reportante)

<a id="get-mis-solicitudes"></a>

#### `GET /api/v1/mis-solicitudes`

Solicitudes del usuario autenticado, más recientes primero.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `estado` | string | Opcional. Uno o varios códigos separados por coma: `E02,E14`. |
| `q` | string | Opcional. Busca en código de ticket o resumen. |
| `ambito` | string | Opcional: `propias` (defecto), `seguidas` (suscritas) o `todas`. |
| `per_page` | int | 1-100, por defecto 15. |
| `page` | int |  |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "codigo_ticket": "TKT-2026-000001",
      "resumen": "Luminaria apagada en Aula 101",
      "estado": {
        "codigo": "E02",
        "nombre": "Pendiente de información",
        "color": "naranja",
        "requiere_accion_reportante": true
      },
      "prioridad": null,
      "categoria": {
        "id": 1,
        "codigo": "ELECTRICA",
        "nombre": "Eléctrica"
      },
      "es_riesgo_inmediato": false,
      "posible_duplicado": false,
      "fecha_registro": "2026-10-28T23:55:38+00:00",
      "fecha_actualizacion": "2026-10-28T23:55:39+00:00",
      "fecha_cierre": null
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/mis-solicitudes?page=1",
    "last": "http://localhost:8000/api/v1/mis-solicitudes?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [
      {
        "url": null,
        "label": "&laquo; Anterior",
        "page": null,
        "active": false
      }
    ],
    "path": "http://localhost:8000/api/v1/mis-solicitudes",
    "per_page": 15,
    "to": 1,
    "total": 1,
    "filtros": {
      "estado": [],
      "q": null
    },
    "sin_resultados": false
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-mis-solicitudes-incidencia"></a>

#### `GET /api/v1/mis-solicitudes/{incidencia}`

Ficha de seguimiento: datos, evidencias, historial público, aclaración pendiente, acciones disponibles y ticket principal si fue unificada.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "resumen": "Luminaria apagada en Aula 101",
    "descripcion": "La luminaria del fondo del aula no enciende desde ayer en la tarde.",
    "canal_radicacion": "WEB",
    "estado": {
      "codigo": "E02",
      "nombre": "Pendiente de información",
      "color": "naranja",
      "significado": "Necesitamos más información o fotos para entender el daño. Debes responder la aclaración.",
      "requiere_accion_reportante": true
    },
    "categoria": {
      "id": 1,
      "codigo": "ELECTRICA",
      "nombre": "Eléctrica"
    },
    "subcategoria": {
      "id": 2,
      "codigo": "ELECTRICA_1",
      "nombre": "Luminarias"
    },
    "prioridad": null,
    "ubicacion": {
      "sede": {
        "id": 1,
        "codigo": "SEDE-PRINCIPAL",
        "nombre": "Campus Principal"
      },
      "edificio": {
        "id": 1,
        "codigo": "BLOQUE-A",
        "nombre": "Bloque A"
      },
      "piso": {
        "id": 1,
        "codigo": "P1",
        "nombre": "Piso 1"
      },
      "espacio": {
        "id": 1,
        "codigo": "A-101",
        "nombre": "Aula 101"
      },
      "texto": "Campus Principal > Bloque A > Piso 1 > Aula 101"
    },
    "es_riesgo_inmediato": false,
    "posible_duplicado": false,
    "fecha_registro": "2026-10-28T23:55:38+00:00",
    "fecha_actualizacion": "2026-10-28T23:55:39+00:00",
    "fecha_cierre": null,
    "es_creador": true,
    "es_seguimiento": false,
    "evidencias": [
      {
        "id": 1,
        "etapa": "REPORTE",
        "nombre_original": "tablero.png",
        "mime_type": "image/png",
        "tamano_bytes": 73,
        "hash_sha256": "21391377d875154ec5cc383409732159c8c82a47b00eeb6d2970a474846dd138",
        "usuario_carga": {
          "id": 1,
          "name": "Laura Reportante"
        },
        "fecha_carga": "2026-10-28T23:55:38+00:00",
        "url": "http://localhost:8000/api/v1/evidencias/1/descargar?expires=1793233540&signature=…"
      }
    ],
    "aclaracion_pendiente": {
      "id": 1,
      "pregunta": "¿La luminaria parpadea o está totalmente apagada?",
      "fecha_solicitud": "2026-10-28T23:55:39+00:00",
      "fecha_limite_respuesta": "2026-11-05T23:00:00+00:00"
    },
    "incidencia_principal": null,
    "resultado_cierre": null,
    "historial": [
      {
        "id": "01a184f0-d6d6-7365-a477-f747dbcf94cc",
        "incidencia_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
        "tipo_evento": "CREACION",
        "categoria": "ESTADO",
        "fecha_hora": "2026-10-28T23:55:38+00:00",
        "usuario": null,
        "rol": "R01",
        "estado_anterior": null,
        "estado_nuevo": {
          "codigo": "E01",
          "nombre": "Registrada"
        },
        "motivo": "Incidencia radicada por el reportante.",
        "datos": null,
        "evidencias": [
          {
            "evidencia_id": 1,
            "sha256": "21391377d875154ec5cc383409732159c8c82a47b00eeb6d2970a474846dd138",
            "uri": "evidencias://temporales/1/bb4fca77-c07f-47a8-bce6-a43f84f4b8fb.png",
            "mime_type": "image/png",
            "nombre_original": "tablero.png"
          }
        ]
      }
    ],
    "acciones": {
      "puede_cancelar": true,
      "mensaje_cancelacion": null,
      "puede_responder_aclaracion": true,
      "puede_confirmar": false,
      "puede_reabrir": false,
      "reapertura": {
        "permitido": false,
        "motivo": "Solo se pueden reabrir incidencias cerradas.",
        "fecha_limite": null,
        "reaperturas_restantes": 2
      },
      "puede_crear_reporte_basado": false
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `404` No existe o no es del usuario (ni la sigue).

<a id="post-mis-solicitudes-incidencia-aclaraciones"></a>

#### `POST /api/v1/mis-solicitudes/{incidencia}/aclaraciones`

El reportante responde la aclaración pedida por el gestor.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** E02 → E03

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `respuesta` | string | Obligatorio, máx. 500. |
| `evidencias` | int[] | Opcional, máx. 4 ids subidos antes. |

**Ejemplo de petición**

```json
{
  "respuesta": "Está totalmente apagada, no parpadea."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Aclaración enviada. La solicitud volvió a revisión.",
  "data": {
    "aclaracion_id": 1,
    "estado": "E03"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el creador de la solicitud.
- `404` La incidencia o el recurso no existe.
- `409` La solicitud no está en E02 (RN-008).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-cancelar-solicitante"></a>

#### `POST /api/v1/incidencias/{incidencia}/cancelar-solicitante`

El creador cancela su propia solicitud mientras no tenga técnico asignado.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** E01, E02, E03, E04 → E17

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `motivo` | string | Obligatorio, 15 a 1000. |

**Ejemplo de petición**

```json
{
  "motivo": "Ya la arreglaron los vigilantes."
}
```

**Respuesta 200**

```json
{
  "mensaje": "La solicitud fue cancelada.",
  "data": {
    "estado": "E17"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el creador.
- `404` La incidencia o el recurso no existe.
- `409` Ya tiene técnico asignado o ya terminó (HU-4.4).
- `422` Validación de campos (`errors` trae el detalle por campo).

Ejemplo de error `409`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/conflicto-de-estado",
  "title": "Conflicto con el estado actual.",
  "status": 409,
  "detail": "La solicitud ya se encuentra finalizada.",
  "instance": "/api/v1/incidencias/01a184f0-f8d2-7348-9935-fd976d364518/cancelar-solicitante",
  "regla": "HU-4.4"
}
```

<a id="post-incidencias-incidencia-reabrir"></a>

#### `POST /api/v1/incidencias/{incidencia}/reabrir`

El creador reabre una solicitud cerrada dentro de la ventana de garantía; la prioridad pasa a Alta y se alerta a coordinación.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** E15 → E13 (si hubo intervención) o E05

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `motivo` | string | Obligatorio, 20 a 1000. |

**Ejemplo de petición**

```json
{
  "motivo": "La luminaria volvió a apagarse al día siguiente."
}
```

**Respuesta 200**

```json
{
  "mensaje": "La solicitud fue reabierta y la coordinación fue notificada.",
  "data": {
    "estado": "E13",
    "numero_reaperturas": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el creador.
- `404` La incidencia o el recurso no existe.
- `422` Fuera de la ventana de garantía (`VENTANA_REAPERTURA_DIAS_HABILES`), sin cupo de reaperturas (`MAXIMO_REAPERTURAS`) o fue unificada por duplicado. Trae `alternativa: CREAR_NUEVO_REPORTE`.

<a id="post-incidencias-incidencia-confirmar"></a>

#### `POST /api/v1/incidencias/{incidencia}/confirmar`

El reportante confirma que el problema quedó resuelto y califica el servicio.

- **Roles (D09):** R01 Reportante, R07 Administrador de plataforma
- **Estado:** E14 → E15

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `calificacion` | int | Obligatorio, 1 a 5. |
| `comentario` | string | Opcional, máx. 500. |

**Ejemplo de petición**

```json
{
  "calificacion": 5,
  "comentario": "Quedó funcionando."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Solución confirmada. La incidencia fue cerrada.",
  "data": {
    "estado": "E15"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el creador.
- `404` La incidencia o el recurso no existe.
- `409` No está en E14 (RN-040).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-rechazar-conformidad"></a>

#### `POST /api/v1/incidencias/{incidencia}/rechazar-conformidad`

El reportante indica que el problema persiste; vuelve a corrección.

- **Roles (D09):** R01 Reportante, R07 Administrador de plataforma
- **Estado:** E14 → E13

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `motivo` | string | Obligatorio, 10 a 1000. |

**Ejemplo de petición**

```json
{
  "motivo": "La luminaria vuelve a parpadear."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Se registró el rechazo. La incidencia volvió a corrección.",
  "data": {
    "estado": "E13"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el creador.
- `404` La incidencia o el recurso no existe.
- `409` No está en E14 (RN-040).
- `422` Validación de campos (`errors` trae el detalle por campo).


---

## E05 · Triaje: revisión, duplicados, clasificación y riesgo

<a id="get-incidencias-incidencia"></a>

#### `GET /api/v1/incidencias/{incidencia}`

Ficha operativa completa de una incidencia (para la mesa de ayuda, coordinación, técnicos y verificación).

- **Roles (D09):** R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "resumen": "Luminaria apagada en Aula 101",
    "descripcion": "La luminaria del fondo del aula no enciende desde ayer en la tarde.",
    "canal_radicacion": "WEB",
    "estado": {
      "codigo": "E06",
      "nombre": "Pendiente de inspección",
      "color": "violeta",
      "significado": "Un técnico fue asignado y realizará la inspección en sitio.",
      "requiere_accion_reportante": false
    },
    "categoria": {
      "id": 1,
      "codigo": "ELECTRICA",
      "nombre": "Eléctrica"
    },
    "subcategoria": {
      "id": 2,
      "codigo": "ELECTRICA_1",
      "nombre": "Luminarias"
    },
    "prioridad": {
      "id": 2,
      "codigo": "ALTA",
      "nombre": "Alta",
      "color": "naranja"
    },
    "ubicacion": {
      "sede": {
        "id": 1,
        "codigo": "SEDE-PRINCIPAL",
        "nombre": "Campus Principal"
      },
      "edificio": {
        "id": 1,
        "codigo": "BLOQUE-A",
        "nombre": "Bloque A"
      },
      "piso": {
        "id": 1,
        "codigo": "P1",
        "nombre": "Piso 1"
      },
      "espacio": {
        "id": 1,
        "codigo": "A-101",
        "nombre": "Aula 101"
      },
      "texto": "Campus Principal > Bloque A > Piso 1 > Aula 101"
    },
    "es_riesgo_inmediato": false,
    "posible_duplicado": false,
    "fecha_registro": "2026-10-28T23:55:38+00:00",
    "fecha_actualizacion": "2026-10-28T23:55:41+00:00",
    "fecha_cierre": null,
    "reportante": {
      "id": 1,
      "name": "Laura Reportante",
      "email": "reportante@praxis.test"
    },
    "tecnico_asignado": {
      "id": 4,
      "name": "Tomás Técnico Electricista",
      "email": "tecnico@praxis.test"
    },
    "impacto": "ALTO",
    "urgencia": "MEDIA",
    "protocolo_emergencia_activo": false,
    "descripcion_riesgo": null,
    "numero_reaperturas": 0,
    "numero_reprocesos": 0,
    "resultado_final_cierre": null,
    "incidencia_principal": null,
    "duplicados_vinculados": [
      {
        "id": "01a184f0-d9cd-70df-980d-9cc5c69bfb0e",
        "codigo_ticket": "TKT-2026-000002",
        "resumen": "Lámpara dañada Aula 101"
      }
    ],
    "sla": {
      "fecha_limite_respuesta": "2026-10-29T21:00:00+00:00",
      "fecha_primera_respuesta": "2026-10-28T23:55:39+00:00",
      "sla_respuesta_cumplido": true,
      "resolucion": {
        "estado": "EN_PLAZO",
        "color": "verde",
        "porcentaje_consumido": 0,
        "minutos_restantes": 1440,
        "horas_retraso": null,
        "fecha_limite": "2026-11-03T17:00:00+00:00"
      }
    },
    "aclaraciones": [
      {
        "id": 1,
        "incidencia_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
        "gestor_solicitante_id": 2,
        "pregunta_gestor": "¿La luminaria parpadea o está totalmente apagada?",
        "fecha_solicitud": "2026-10-28T23:55:39.000000Z",
        "fecha_limite_respuesta": "2026-11-05T23:00:00.000000Z",
        "respuesta_reportante": "Está totalmente apagada, no parpadea.",
        "fecha_respuesta": "2026-10-28T23:55:40.000000Z",
        "respondida_por_id": 1,
        "estado": "RESPONDIDA",
        "created_at": "2026-10-28T23:55:39.000000Z",
        "updated_at": "2026-10-28T23:55:40.000000Z",
        "gestor": {
          "id": 2,
          "name": "Gabriel Gestor",
          "email": "gestor@praxis.test",
          "email_verified_at": null,
          "created_at": "2026-10-28T23:55:28.000000Z",
          "updated_at": "2026-10-28T23:55:33.000000Z",
          "telefono": null,
          "activo": true,
          "intentos_fallidos": 0,
          "ultimo_intento_fallido_en": null,
          "bloqueado_hasta": null,
          "ultimo_acceso_en": "2026-10-28T23:55:33.000000Z"
        }
      }
    ],
    "evaluaciones_riesgo": [
      {
        "id": 1,
        "incidencia_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
        "evaluador_id": 2,
        "tiene_riesgo_inmediato": false,
        "nivel_riesgo": "BAJO",
        "protocolo_activado": false,
        "acciones_adoptadas": null,
        "observaciones": "Sin riesgo para los ocupantes.",
        "created_at": "2026-10-28T23:55:40.000000Z",
        "evaluador": {
          "id": 2,
          "name": "Gabriel Gestor",
          "email": "gestor@praxis.test",
          "email_verified_at": null,
          "created_at": "2026-10-28T23:55:28.000000Z",
          "updated_at": "2026-10-28T23:55:33.000000Z",
          "telefono": null,
          "activo": true,
          "intentos_fallidos": 0,
          "ultimo_intento_fallido_en": null,
          "bloqueado_hasta": null,
          "ultimo_acceso_en": "2026-10-28T23:55:33.000000Z"
        }
      }
    ],
    "asignaciones": [
      {
        "id": 1,
        "incidencia_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
        "tecnico_asignado_id": 4,
        "asignado_por_id": 3,
        "tecnico_anterior_id": null,
        "es_vigente": true,
        "excepcion_wip": false,
        "motivo_reasignacion": null,
        "instrucciones": "Revisar balasto y tubo.",
        "fecha_visita_tentativa": "2026-10-29T00:00:00.000000Z",
        "fecha_fin_vigencia": null,
        "created_at": "2026-10-28T23:55:41.000000Z",
        "tecnico": {
          "id": 4,
          "name": "Tomás Técnico Electricista",
          "email": "tecnico@praxis.test",
          "email_verified_at": null,
          "created_at": "2026-10-28T23:55:28.000000Z",
          "updated_at": "2026-10-28T23:55:34.000000Z",
          "telefono": null,
          "activo": true,
          "intentos_fallidos": 0,
          "ultimo_intento_fallido_en": null,
          "bloqueado_hasta": null,
          "ultimo_acceso_en": "2026-10-28T23:55:34.000000Z"
        },
        "asignado_por": {
          "id": 3,
          "name": "Camila Coordinadora",
          "email": "coordinador@praxis.test",
          "email_verified_at": null,
          "created_at": "2026-10-28T23:55:28.000000Z",
          "updated_at": "2026-10-28T23:55:34.000000Z",
          "telefono": null,
          "activo": true,
          "intentos_fallidos": 0,
          "ultimo_intento_fallido_en": null,
          "bloqueado_hasta": null,
          "ultimo_acceso_en": "2026-10-28T23:55:34.000000Z"
        }
      }
    ],
    "diagnosticos": [],
    "solicitudes_recursos": [],
    "planes_intervencion": [],
    "actividades": [],
    "suspensiones": [],
    "verificaciones": [],
    "evidencias": [
      {
        "id": 1,
        "etapa": "REPORTE",
        "nombre_original": "tablero.png",
        "mime_type": "image/png",
        "tamano_bytes": 73,
        "hash_sha256": "21391377d875154ec5cc383409732159c8c82a47b00eeb6d2970a474846dd138",
        "usuario_carga": {
          "id": 1,
          "name": "Laura Reportante"
        },
        "fecha_carga": "2026-10-28T23:55:38+00:00",
        "url": "http://localhost:8000/api/v1/evidencias/1/descargar?expires=1793233541&signature=…"
      }
    ],
    "transiciones_disponibles": [
      "E18"
    ]
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` No existe, o el técnico no está ni estuvo asignado a ella.

<a id="post-incidencias-incidencia-iniciar-revision"></a>

#### `POST /api/v1/incidencias/{incidencia}/iniciar-revision`

El gestor toma la incidencia para revisarla.

- **Roles (D09):** R02 Gestor de incidencias, R07 Administrador de plataforma
- **Estado:** E01 → E03

**Body:** vacío.

**Respuesta 200**

```json
{
  "mensaje": "Revisión iniciada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E03",
      "nombre": "En revisión"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` La incidencia no está en E01 (D07).

Ejemplo de error `409`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/conflicto-de-estado",
  "title": "Conflicto con el estado actual.",
  "status": 409,
  "detail": "La incidencia está en E03 (En revisión) y no puede pasar a E03 (En revisión).",
  "instance": "/api/v1/incidencias/01a184f0-d6d2-73b0-8089-25fd7ba5a503/iniciar-revision",
  "regla": "D07",
  "estado_actual": "E03",
  "estado_destino": "E03"
}
```

<a id="post-incidencias-incidencia-solicitar-aclaracion"></a>

#### `POST /api/v1/incidencias/{incidencia}/solicitar-aclaracion`

Pide información adicional al reportante; fija un plazo de respuesta en días hábiles y lo notifica.

- **Roles (D09):** R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E03 → E02
- **Nota:** Si el reportante no responde en `PLAZO_ACLARACION_DIAS_HABILES`, el proceso diario `praxis:vencer-aclaraciones` la pasa a E17.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `informacion_requerida` | string | Obligatorio, 10 a 1000. |

**Ejemplo de petición**

```json
{
  "informacion_requerida": "¿La luminaria parpadea o está totalmente apagada?"
}
```

**Respuesta 200**

```json
{
  "mensaje": "Aclaración solicitada al reportante.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E02",
      "nombre": "Pendiente de información"
    },
    "aclaracion_id": 1,
    "fecha_limite_respuesta": "2026-11-05T23:00:00+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E03.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-incidencias-incidencia-posibles-duplicados"></a>

#### `GET /api/v1/incidencias/{incidencia}/posibles-duplicados`

Candidatos a duplicado ponderados por ubicación, categoría, similitud de descripción y estado abierto.

- **Roles (D09):** R02 Gestor de incidencias, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": "01a184f0-d9cd-70df-980d-9cc5c69bfb0e",
      "codigo_ticket": "TKT-2026-000002",
      "resumen": "Lámpara dañada Aula 101",
      "descripcion": "La luminaria del fondo del aula no enciende desde ayer en la tarde.",
      "estado": {
        "codigo": "E01",
        "nombre": "Registrada"
      },
      "categoria": {
        "id": 1,
        "nombre": "Eléctrica"
      },
      "espacio": {
        "id": 1,
        "nombre": "Aula 101"
      },
      "fecha_registro": "2026-10-28T23:55:39+00:00",
      "evidencias": 0,
      "criterios": {
        "misma_ubicacion": true,
        "misma_categoria": true,
        "similitud_descripcion": 100
      },
      "puntaje": 100
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.

<a id="post-incidencias-incidencia-vincular-duplicado"></a>

#### `POST /api/v1/incidencias/{incidencia}/vincular-duplicado`

Unifica la incidencia como duplicada de otra principal; su reportante pasa a seguir la principal.

- **Roles (D09):** R02 Gestor de incidencias, R07 Administrador de plataforma
- **Estado:** E01, E03, E04 → E15

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `incidencia_principal_id` | uuid | Obligatorio. No puede ser la misma, ni estar en E16/E17, ni ser a su vez un duplicado. |
| `observaciones` | string | Opcional, máx. 500. |

**Ejemplo de petición**

```json
{
  "incidencia_principal_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
  "observaciones": "Mismo daño reportado en A-101."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Incidencia vinculada como duplicada.",
  "data": {
    "id": "01a184f0-d9cd-70df-980d-9cc5c69bfb0e",
    "codigo_ticket": "TKT-2026-000002",
    "estado": {
      "codigo": "E15",
      "nombre": "Cerrada"
    },
    "incidencia_principal_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` La incidencia no está en E01, E03 o E04 (RN-013).
- `422` Principal inválida.

<a id="post-incidencias-incidencia-descartar-duplicado"></a>

#### `POST /api/v1/incidencias/{incidencia}/descartar-duplicado`

Descarta la sugerencia de duplicado y quita la marca `posible_duplicado`.

- **Roles (D09):** R02 Gestor de incidencias, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `observaciones` | string | Opcional, máx. 500. |

**Ejemplo de petición**

```json
{
  "observaciones": "Es un daño distinto."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Se descartó la sugerencia de duplicado.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E03",
      "nombre": "En revisión"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.

<a id="post-incidencias-incidencia-clasificar"></a>

#### `POST /api/v1/incidencias/{incidencia}/clasificar`

Decide la procedencia. Si procede, calcula la prioridad con la matriz Impacto × Urgencia, fija el plazo SLA de resolución y remite a coordinación.

- **Roles (D09):** R02 Gestor de incidencias, R07 Administrador de plataforma
- **Estado:** E03/E04 → E05 (procedente) · E03/E04 → E16 (no procedente)
- **Nota:** Desde E03 se registran en auditoría las dos transiciones (E03 → E04 y E04 → E05).

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `procedente` | boolean | Obligatorio. |
| `motivo` | string | Obligatorio si `procedente=false`, 20 a 1000. |
| `impacto` | string | Obligatorio si `procedente=true`: ALTO, MEDIO o BAJO. |
| `urgencia` | string | Obligatorio si `procedente=true`: ALTA, MEDIA o BAJA. |
| `justificacion` | string | Opcional, máx. 500. |

**Ejemplo de petición**

```json
{
  "procedente": true,
  "impacto": "MEDIO",
  "urgencia": "MEDIA",
  "justificacion": "Afecta una sola aula."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Incidencia clasificada y remitida a coordinación.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E05",
      "nombre": "Pendiente de asignación"
    },
    "prioridad": {
      "codigo": "BAJA",
      "nombre": "Baja"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E03 ni E04.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="patch-incidencias-incidencia-prioridad"></a>

#### `PATCH /api/v1/incidencias/{incidencia}/prioridad`

Recalcula la prioridad de una incidencia ya clasificada y deja el cambio en el historial (RN-020).

- **Roles (D09):** R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `impacto` | string | Obligatorio: ALTO, MEDIO o BAJO. |
| `urgencia` | string | Obligatorio: ALTA, MEDIA o BAJA. |
| `justificacion` | string | Obligatorio, 10 a 500. |

**Ejemplo de petición**

```json
{
  "impacto": "ALTO",
  "urgencia": "MEDIA",
  "justificacion": "El aula tiene clases nocturnas."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Prioridad actualizada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E05",
      "nombre": "Pendiente de asignación"
    },
    "prioridad": {
      "codigo": "ALTA",
      "nombre": "Alta"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` La incidencia no está clasificada o ya terminó (RN-020).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-evaluacion-riesgo"></a>

#### `POST /api/v1/incidencias/{incidencia}/evaluacion-riesgo`

Registra la evaluación de riesgo. Con riesgo inmediato activa el protocolo de emergencia: prioridad Crítica, salto a E05 y alerta a coordinación.

- **Roles (D09):** R02 Gestor de incidencias, R07 Administrador de plataforma
- **Estado:** Con riesgo: E01/E03/E04 → E05 (vía rápida). Sin riesgo: no cambia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `tiene_riesgo_inmediato` | boolean | Obligatorio. |
| `nivel_riesgo` | string | Obligatorio: BAJO, MEDIO, ALTO o INMEDIATO. |
| `acciones_adoptadas` | string | Obligatorio si hay riesgo, 10 a 2000. |
| `observaciones` | string | Opcional, máx. 1000. |

**Ejemplo de petición**

```json
{
  "tiene_riesgo_inmediato": true,
  "nivel_riesgo": "INMEDIATO",
  "acciones_adoptadas": "Se cortó la energía del bloque B y se acordonó el cuarto técnico."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Protocolo de emergencia activado.",
  "data": {
    "id": "01a184f0-fa65-737d-98bb-78a86c49a53a",
    "codigo_ticket": "TKT-2026-000005",
    "estado": {
      "codigo": "E05",
      "nombre": "Pendiente de asignación"
    },
    "evaluacion_id": 2,
    "protocolo_activado": true,
    "prioridad": {
      "codigo": "CRITICA",
      "nombre": "Crítica"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` La incidencia ya terminó.
- `422` Validación de campos (`errors` trae el detalle por campo).


---

## E06 · Asignación y planificación

<a id="get-tecnicos-disponibles"></a>

#### `GET /api/v1/tecnicos/disponibles`

Técnicos activos con su carga actual y el límite WIP; si se filtra por categoría, solo los de la especialidad requerida.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `categoria_id` | int | Opcional, existe. |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 4,
      "nombre": "Tomás Técnico Electricista",
      "email": "tecnico@praxis.test",
      "especialidades": [
        {
          "id": 1,
          "codigo": "ELECTRICIDAD",
          "nombre": "Electricista"
        }
      ],
      "carga_actual": 0,
      "limite_wip": 5,
      "disponible": true
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-asignar"></a>

#### `POST /api/v1/incidencias/{incidencia}/asignar`

Asigna (o reasigna) el técnico responsable y lo notifica.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E05 → E06 (reasignar no cambia el estado)

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `tecnico_id` | int | Obligatorio. Usuario activo con rol R04 y la especialidad de la categoría. |
| `instrucciones` | string | Opcional, máx. 1000. |
| `fecha_visita_tentativa` | date | Opcional, hoy o posterior. |
| `motivo_reasignacion` | string | Obligatorio al reasignar, 10 a 500 (RN-025). |

**Ejemplo de petición**

```json
{
  "tecnico_id": 4,
  "instrucciones": "Revisar balasto y tubo.",
  "fecha_visita_tentativa": "2026-10-29"
}
```

**Respuesta 200**

```json
{
  "mensaje": "Técnico asignado.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E06",
      "nombre": "Pendiente de inspección"
    },
    "asignacion_id": 1,
    "tecnico_id": 4,
    "excepcion_wip": false
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E05, o no admite reasignación en su estado (RN-025).
- `422` Técnico inválido, sin la especialidad, en su límite WIP (salvo prioridad Crítica) o falta el motivo de reasignación.

<a id="post-incidencias-incidencia-programar"></a>

#### `POST /api/v1/incidencias/{incidencia}/programar`

Aprueba el diagnóstico y programa la intervención (cuando no hacen falta recursos).

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E07 → E10

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `fecha_programada` | date (Y-m-d) | Obligatorio, hoy o posterior. |
| `hora_inicio` | HH:mm | Obligatorio. |
| `hora_fin` | HH:mm | Obligatorio, posterior a `hora_inicio`. |
| `cuadrilla` | string | Opcional, máx. 255. |
| `observaciones` | string | Opcional, máx. 1000. |

**Ejemplo de petición**

```json
{
  "fecha_programada": "2026-10-29",
  "hora_inicio": "07:00",
  "hora_fin": "08:00",
  "cuadrilla": "Electricidad 1"
}
```

**Respuesta 200**

```json
{
  "mensaje": "Intervención programada.",
  "data": {
    "id": "01a184f0-fe79-7351-aafe-077cc2cd59bd",
    "codigo_ticket": "TKT-2026-000006",
    "estado": {
      "codigo": "E10",
      "nombre": "Programada"
    },
    "plan": {
      "plan_id": 5,
      "fecha_programada": "2026-10-29",
      "hora_inicio": "07:00",
      "hora_fin": "08:00",
      "cuadrilla": "Electricidad 1"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E07, no hay diagnóstico registrado (RN-023), el diagnóstico fue de no confirmación (RN-024), ya está programada o hay una autorización pendiente (RN-029).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="patch-incidencias-incidencia-reprogramar"></a>

#### `PATCH /api/v1/incidencias/{incidencia}/reprogramar`

Cambia la fecha u hora del plan vigente y guarda la fecha anterior con el motivo.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E10 → E10 (sin cambio)

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `fecha_programada` | date | Obligatorio, hoy o posterior. |
| `hora_inicio` | HH:mm | Obligatorio. |
| `hora_fin` | HH:mm | Obligatorio, posterior al inicio. |
| `cuadrilla` | string | Opcional. |
| `observaciones` | string | Opcional. |
| `motivo` | string | Obligatorio, 10 a 500. |

**Ejemplo de petición**

```json
{
  "fecha_programada": "2026-10-30",
  "hora_inicio": "14:00",
  "hora_fin": "16:00",
  "motivo": "El aula está ocupada en la mañana."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Intervención reprogramada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E10",
      "nombre": "Programada"
    },
    "plan": {
      "plan_id": 1,
      "fecha_programada": "2026-10-30",
      "hora_inicio": "14:00",
      "hora_fin": "16:00",
      "cuadrilla": "Electricidad 1"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E10 con un plan vigente.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-devolver-diagnostico"></a>

#### `POST /api/v1/incidencias/{incidencia}/devolver-diagnostico`

Devuelve el diagnóstico al técnico por insuficiente; debe volver a inspeccionar.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E07 → E06

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `observaciones` | string | Obligatorio, 10 a 1000. |

**Ejemplo de petición**

```json
{
  "observaciones": "Indique si la caja también está dañada."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Diagnóstico devuelto al técnico.",
  "data": {
    "id": "01a184f0-fe79-7351-aafe-077cc2cd59bd",
    "codigo_ticket": "TKT-2026-000006",
    "estado": {
      "codigo": "E06",
      "nombre": "Pendiente de inspección"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E07 o no hay diagnóstico registrado.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-cerrar-no-confirmada"></a>

#### `POST /api/v1/incidencias/{incidencia}/cerrar-no-confirmada`

Visto bueno del coordinador al descarte del técnico: cierra con resultado `NO_CONFIRMADA_EN_INSPECCION`.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E07 → E15

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `observaciones` | string | Opcional, máx. 1000. |

**Ejemplo de petición**

```json
{
  "observaciones": "Se acepta el descarte del técnico."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Cierre justificado por incidencia no confirmada.",
  "data": {
    "id": "01a184f0-fa65-737d-98bb-78a86c49a53a",
    "codigo_ticket": "TKT-2026-000005",
    "estado": {
      "codigo": "E15",
      "nombre": "Cerrada"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E07 o el técnico no registró un descarte (RN-024).


---

## E07 · Inspección, diagnóstico y ejecución

<a id="get-tecnico-tareas"></a>

#### `GET /api/v1/tecnico/tareas`

Bandeja del técnico: sus incidencias activas (E06 a E11, E13 y E18), primero las de riesgo.

- **Roles (D09):** R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `per_page` | int | Por defecto 20. |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "codigo_ticket": "TKT-2026-000001",
      "resumen": "Luminaria apagada en Aula 101",
      "estado": {
        "codigo": "E06",
        "nombre": "Pendiente de inspección",
        "color": "violeta",
        "requiere_accion_reportante": false
      },
      "prioridad": {
        "id": 2,
        "codigo": "ALTA",
        "nombre": "Alta",
        "color": "naranja"
      },
      "categoria": {
        "id": 1,
        "codigo": "ELECTRICA",
        "nombre": "Eléctrica"
      },
      "ubicacion": {
        "sede": {
          "id": 1,
          "codigo": "SEDE-PRINCIPAL",
          "nombre": "Campus Principal"
        },
        "edificio": {
          "id": 1,
          "codigo": "BLOQUE-A",
          "nombre": "Bloque A"
        },
        "piso": {
          "id": 1,
          "codigo": "P1",
          "nombre": "Piso 1"
        },
        "espacio": {
          "id": 1,
          "codigo": "A-101",
          "nombre": "Aula 101"
        },
        "texto": "Campus Principal > Bloque A > Piso 1 > Aula 101"
      },
      "es_riesgo_inmediato": false,
      "posible_duplicado": false,
      "fecha_registro": "2026-10-28T23:55:38+00:00",
      "fecha_actualizacion": "2026-10-28T23:55:41+00:00",
      "fecha_cierre": null
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/tecnico/tareas?page=1",
    "last": "http://localhost:8000/api/v1/tecnico/tareas?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [
      {
        "url": null,
        "label": "&laquo; Anterior",
        "page": null,
        "active": false
      }
    ],
    "path": "http://localhost:8000/api/v1/tecnico/tareas",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="post-incidencias-incidencia-iniciar-inspeccion"></a>

#### `POST /api/v1/incidencias/{incidencia}/iniciar-inspeccion`

El técnico asignado inicia la inspección en sitio.

- **Roles (D09):** R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** E06 → E07

**Body:** vacío.

**Respuesta 200**

```json
{
  "mensaje": "Inspección iniciada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E07",
      "nombre": "En diagnóstico"
    },
    "diagnostico_id": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el técnico asignado.
- `404` La incidencia o el recurso no existe.
- `409` No está en E06.

<a id="post-incidencias-incidencia-diagnostico"></a>

#### `POST /api/v1/incidencias/{incidencia}/diagnostico`

Registra el diagnóstico de un daño confirmado con la intervención propuesta; notifica a coordinación.

- **Roles (D09):** R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** E07 → E07 (habilita la decisión del coordinador)

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `hallazgos` | string | Obligatorio, 10 a 4000. |
| `descripcion_problema` | string | Obligatorio, 10 a 4000. |
| `alcance` | string | Opcional, máx. 4000. |
| `intervencion_propuesta` | string | Obligatorio, 10 a 4000. |
| `requiere_recursos` | boolean | Opcional. |
| `requiere_autorizacion` | boolean | Opcional. |
| `evidencias` | int[] | Opcional, máx. 10 ids subidos antes (quedan como etapa INSPECCION). |

**Ejemplo de petición**

```json
{
  "hallazgos": "Balasto quemado y tubo fundido.",
  "descripcion_problema": "Falla del balasto electrónico de la luminaria.",
  "intervencion_propuesta": "Reemplazar balasto y tubo LED.",
  "requiere_recursos": true,
  "requiere_autorizacion": true,
  "evidencias": [
    2
  ]
}
```

**Respuesta 200**

```json
{
  "mensaje": "Diagnóstico registrado.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E07",
      "nombre": "En diagnóstico"
    },
    "diagnostico_id": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el técnico asignado.
- `404` La incidencia o el recurso no existe.
- `409` No está en E07 o no hay inspección en curso (RN-022).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-descartar-por-inspeccion"></a>

#### `POST /api/v1/incidencias/{incidencia}/descartar-por-inspeccion`

El técnico documenta que el daño no existe; exige evidencia del estado funcional y espera el visto bueno del coordinador.

- **Roles (D09):** R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** E07 → E07 (queda para `cerrar-no-confirmada`)

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `hallazgos` | string | Obligatorio, 10 a 4000. |
| `justificacion` | string | Obligatorio, 20 a 4000. |
| `evidencias` | int[] | Opcional, máx. 10. Debe existir al menos una evidencia de INSPECCION de esta inspección (subida aquí o adjunta con `incidencia_id`). |

**Ejemplo de petición**

```json
{
  "hallazgos": "Tablero en buen estado, sin rastros de arco eléctrico.",
  "justificacion": "Las chispas provenían de una extensión personal ya retirada."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Descarte registrado. Pendiente del visto bueno del coordinador.",
  "data": {
    "id": "01a184f0-fa65-737d-98bb-78a86c49a53a",
    "codigo_ticket": "TKT-2026-000005",
    "estado": {
      "codigo": "E07",
      "nombre": "En diagnóstico"
    },
    "diagnostico_id": 2
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el técnico asignado.
- `404` La incidencia o el recurso no existe.
- `409` No está en E07 o no hay inspección en curso.
- `422` Falta la evidencia fotográfica (RN-024) o campos inválidos.

<a id="post-incidencias-incidencia-iniciar-atencion"></a>

#### `POST /api/v1/incidencias/{incidencia}/iniciar-atencion`

El técnico inicia la intervención física.

- **Roles (D09):** R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** E10 → E11

**Body:** vacío.

**Respuesta 200**

```json
{
  "mensaje": "Intervención iniciada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E11",
      "nombre": "En atención"
    },
    "actividad_id": 1,
    "fecha_inicio": "2026-10-28T23:55:44+00:00"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el técnico asignado.
- `404` La incidencia o el recurso no existe.
- `409` No está en E10 o hay una autorización pendiente (RN-029).

<a id="post-incidencias-incidencia-registrar-intervencion"></a>

#### `POST /api/v1/incidencias/{incidencia}/registrar-intervencion`

Registra lo realizado, el tiempo y los materiales, con evidencia obligatoria, y pasa a verificación.

- **Roles (D09):** R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** E11 → E12

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `actividades_realizadas` | string | Obligatorio, 10 a 4000. |
| `tiempo_invertido_minutos` | int | Obligatorio, 1 a 100000. |
| `dificultades_observaciones` | string | Opcional, máx. 4000. |
| `materiales` | object[] | Opcional. Cada uno: `nombre` (máx. 150), `cantidad` (> 0), `unidad` (máx. 20), todos obligatorios. |
| `evidencias` | int[] | Máx. 10 ids subidos antes (quedan como EJECUCION). Debe quedar al menos una evidencia de ejecución (RN-033). |

**Ejemplo de petición**

```json
{
  "actividades_realizadas": "Se reemplazó el balasto y los dos tubos.",
  "tiempo_invertido_minutos": 45,
  "materiales": [
    {
      "nombre": "Balasto electrónico 2x32W",
      "cantidad": 1,
      "unidad": "UND"
    }
  ],
  "evidencias": [
    3
  ]
}
```

**Respuesta 200**

```json
{
  "mensaje": "Intervención registrada. Pendiente de verificación.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E12",
      "nombre": "Pendiente de verificación"
    },
    "actividad_id": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` No es el técnico asignado.
- `404` La incidencia o el recurso no existe.
- `409` No está en E11 o no hay intervención iniciada (RN-032).
- `422` Falta la evidencia de ejecución (RN-033) o campos inválidos.

<a id="post-incidencias-incidencia-suspender"></a>

#### `POST /api/v1/incidencias/{incidencia}/suspender`

Suspende temporalmente la atención con causa, motivo y condiciones para reanudar; se comunica al reportante.

- **Roles (D09):** R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R07 Administrador de plataforma
- **Estado:** E06, E07, E09, E10, E11 → E18
- **Nota:** Desde E08 la suspensión la decide el dictamen (`accion_rechazo: SUSPENDER`).

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `causal` | string | Obligatorio, máx. 60 (p. ej. ACCESO_RESTRINGIDO, CLIMA, PROVEEDOR). |
| `motivo_detallado` | string | Obligatorio, 10 a 1000. |
| `condiciones_reanudacion` | string | Obligatorio, 10 a 1000. |

**Ejemplo de petición**

```json
{
  "causal": "ACCESO_RESTRINGIDO",
  "motivo_detallado": "El aula está cerrada por evento institucional.",
  "condiciones_reanudacion": "Reanudar cuando termine el evento."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Atención suspendida.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E18",
      "nombre": "Suspendida"
    },
    "suspension_id": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` El estado actual no admite suspensión (D07).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-reanudar"></a>

#### `POST /api/v1/incidencias/{incidencia}/reanudar`

Reanuda una incidencia suspendida y registra cuánto duró la pausa.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E18 → E10 o E11

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `estado_destino` | string | Obligatorio: E10 o E11. |
| `observacion` | string | Obligatorio, 10 a 1000. |
| `fecha_programada` | date | Opcional (con `hora_inicio`/`hora_fin`): reemplaza el plan vigente si se vuelve a E10. |
| `hora_inicio` | HH:mm | Obligatorio con `fecha_programada`. |
| `hora_fin` | HH:mm | Obligatorio con `fecha_programada`, posterior al inicio. |

**Ejemplo de petición**

```json
{
  "estado_destino": "E10",
  "observacion": "El evento terminó, se retoma el plan.",
  "fecha_programada": "2026-10-30",
  "hora_inicio": "14:00",
  "hora_fin": "16:00"
}
```

**Respuesta 200**

```json
{
  "mensaje": "Atención reanudada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E10",
      "nombre": "Programada"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está suspendida o hay autorización pendiente (RN-029).
- `422` Validación de campos (`errors` trae el detalle por campo).


---

## E08 · Recursos y autorizaciones

<a id="post-incidencias-incidencia-solicitudes-recursos"></a>

#### `POST /api/v1/incidencias/{incidencia}/solicitudes-recursos`

Registra los recursos que faltan para la intervención.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E07 → E08 (requiere autorización) · E07 → E09 (sin autorización) · emergencia: sin cambio

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `decision_requerida` | string | Obligatorio, 10 a 500. |
| `requiere_autorizacion` | boolean | Obligatorio. `true` la envía a R05 (E08); `false` queda esperando insumos (E09). |
| `es_emergencia` | boolean | Opcional. Solo con protocolo de riesgo activo: queda pendiente de regularización y no bloquea la programación (RN-029). |
| `items` | object[] | Obligatorio, al menos uno. Cada uno: `recurso_nombre` (máx. 150), `cantidad` (> 0), `unidad` (máx. 20) obligatorios y `costo_estimado` (≥ 0) opcional. |

**Ejemplo de petición**

```json
{
  "decision_requerida": "Aprobar compra de balasto electrónico.",
  "requiere_autorizacion": true,
  "items": [
    {
      "recurso_nombre": "Balasto electrónico 2x32W",
      "cantidad": 1,
      "unidad": "UND",
      "costo_estimado": 85000
    },
    {
      "recurso_nombre": "Tubo LED T8",
      "cantidad": 2,
      "unidad": "UND",
      "costo_estimado": 18000
    }
  ]
}
```

**Respuesta 201**

```json
{
  "mensaje": "Solicitud de recursos registrada.",
  "data": {
    "id": 1,
    "estado_solicitud": "PENDIENTE_AUTORIZACION",
    "decision_requerida": "Aprobar compra de balasto electrónico.",
    "requiere_autorizacion": true,
    "es_emergencia": false,
    "resultado_dictamen": null,
    "justificacion_dictamen": null,
    "costo_total_estimado": 121000,
    "detalles": [
      {
        "id": 1,
        "solicitud_recurso_id": 1,
        "recurso_nombre": "Balasto electrónico 2x32W",
        "cantidad": 1,
        "unidad": "UND",
        "costo_estimado": 85000,
        "disponible": false,
        "created_at": "2026-10-28T23:55:42.000000Z",
        "updated_at": "2026-10-28T23:55:42.000000Z"
      }
    ],
    "solicitante": null,
    "fecha_solicitud": "2026-10-28T23:55:42+00:00",
    "incidencia": {
      "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "codigo_ticket": "TKT-2026-000001",
      "resumen": "Luminaria apagada en Aula 101",
      "estado": {
        "codigo": "E08",
        "nombre": "Pendiente de autorización"
      }
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E07 o no hay diagnóstico confirmado (RN-026).
- `422` Emergencia sin protocolo activo (RN-029) o campos inválidos.

<a id="get-autorizaciones-pendientes"></a>

#### `GET /api/v1/autorizaciones/pendientes`

Bandeja de solicitudes de recursos pendientes de dictamen.

- **Roles (D09):** R03 Coordinador de mantenimiento, R05 Responsable de autorizaciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 1,
      "estado_solicitud": "PENDIENTE_AUTORIZACION",
      "decision_requerida": "Aprobar compra de balasto electrónico.",
      "requiere_autorizacion": true,
      "es_emergencia": false,
      "resultado_dictamen": null,
      "justificacion_dictamen": null,
      "costo_total_estimado": 121000,
      "detalles": [
        {
          "id": 1,
          "solicitud_recurso_id": 1,
          "recurso_nombre": "Balasto electrónico 2x32W",
          "cantidad": 1,
          "unidad": "UND",
          "costo_estimado": 85000,
          "disponible": false,
          "created_at": "2026-10-28T23:55:42.000000Z",
          "updated_at": "2026-10-28T23:55:42.000000Z"
        }
      ],
      "solicitante": {
        "id": 3,
        "name": "Camila Coordinadora"
      },
      "fecha_solicitud": "2026-10-28T23:55:42+00:00",
      "incidencia": {
        "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
        "codigo_ticket": "TKT-2026-000001",
        "resumen": "Luminaria apagada en Aula 101",
        "estado": {
          "codigo": "E08",
          "nombre": "Pendiente de autorización"
        }
      }
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="post-solicitudes-recursos-id-dictamen"></a>

#### `POST /api/v1/solicitudes-recursos/{id}/dictamen`

Emite el dictamen sobre una solicitud que requiere autorización.

- **Roles (D09):** R03 Coordinador de mantenimiento, R05 Responsable de autorizaciones, R07 Administrador de plataforma
- **Estado:** APROBADA: E08 → E10 (recursos disponibles) o E09 · RECHAZADA: E08 → E07, E18 o E17 según `accion_rechazo`
- **Nota:** Una solicitud de emergencia se dictamina como regularización: no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `resultado` | string | Obligatorio: APROBADA o RECHAZADA. |
| `justificacion` | string | Obligatorio si RECHAZADA, 10 a 1000. |
| `recursos_disponibles` | boolean | Opcional. Con APROBADA y `true` pasa directo a E10. |
| `accion_rechazo` | string | Obligatorio si RECHAZADA: DEVOLVER_DIAGNOSTICO (E07), SUSPENDER (E18) o CANCELAR (E17). |
| `condiciones_reanudacion` | string | Obligatorio si `accion_rechazo=SUSPENDER`, 10 a 1000. |
| `fecha_programada` | date | Opcional, hoy o posterior; crea el plan si se aprueba con recursos disponibles. |
| `hora_inicio` | HH:mm | Obligatorio con `fecha_programada`. |
| `hora_fin` | HH:mm | Obligatorio con `fecha_programada`, posterior al inicio. |

**Ejemplo de petición**

```json
{
  "resultado": "APROBADA",
  "justificacion": "Costo dentro del presupuesto.",
  "recursos_disponibles": false
}
```

**Respuesta 200**

```json
{
  "mensaje": "Dictamen registrado.",
  "data": {
    "id": 1,
    "estado_solicitud": "PENDIENTE_RECURSOS",
    "decision_requerida": "Aprobar compra de balasto electrónico.",
    "requiere_autorizacion": true,
    "es_emergencia": false,
    "resultado_dictamen": "APROBADA",
    "justificacion_dictamen": "Costo dentro del presupuesto.",
    "costo_total_estimado": 121000,
    "detalles": [
      {
        "id": 1,
        "solicitud_recurso_id": 1,
        "recurso_nombre": "Balasto electrónico 2x32W",
        "cantidad": 1,
        "unidad": "UND",
        "costo_estimado": 85000,
        "disponible": false,
        "created_at": "2026-10-28T23:55:42.000000Z",
        "updated_at": "2026-10-28T23:55:42.000000Z"
      }
    ],
    "solicitante": null,
    "fecha_solicitud": "2026-10-28T23:55:42+00:00",
    "incidencia": {
      "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "codigo_ticket": "TKT-2026-000001",
      "resumen": "Luminaria apagada en Aula 101",
      "estado": {
        "codigo": "E09",
        "nombre": "Pendiente de recursos"
      }
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` Rol sin permiso, o R03 con un costo que supera `TOPE_AUTORIDAD_COORDINADOR`.
- `404` La solicitud no existe.
- `409` La solicitud no está pendiente de autorización (RN-028) o la incidencia no está en E08.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="patch-incidencias-incidencia-confirmar-recursos"></a>

#### `PATCH /api/v1/incidencias/{incidencia}/confirmar-recursos`

Confirma que todos los recursos llegaron y programa la intervención.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E09 → E10

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `todos_disponibles` | boolean | Opcional. `true` marca todos los ítems como disponibles. |
| `detalles_disponibles` | int[] | Opcional. Ids de ítems que llegaron (alternativa a `todos_disponibles`). |
| `fecha_programada` | date | Obligatorio, hoy o posterior. |
| `hora_inicio` | HH:mm | Obligatorio. |
| `hora_fin` | HH:mm | Obligatorio, posterior al inicio. |
| `cuadrilla` | string | Opcional. |
| `observaciones` | string | Opcional. |

**Ejemplo de petición**

```json
{
  "todos_disponibles": true,
  "fecha_programada": "2026-10-29",
  "hora_inicio": "09:00",
  "hora_fin": "11:00",
  "cuadrilla": "Electricidad 1"
}
```

**Respuesta 200**

```json
{
  "mensaje": "Recursos disponibles. Intervención programada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "estado": {
      "codigo": "E10",
      "nombre": "Programada"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E09.
- `422` Quedan ítems sin disponibilidad (el mensaje los lista) o campos inválidos.

<a id="post-solicitudes-recursos-id-seguimientos"></a>

#### `POST /api/v1/solicitudes-recursos/{id}/seguimientos`

Agrega una nota de seguimiento a una solicitud pendiente (no cambia el estado, RN-030).

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `comentario` | string | Obligatorio, 5 a 1000. |

**Ejemplo de petición**

```json
{
  "comentario": "Proveedor confirma entrega mañana."
}
```

**Respuesta 201**

```json
{
  "mensaje": "Seguimiento registrado.",
  "data": {
    "solicitud_recurso_id": 1,
    "usuario_id": 3,
    "comentario_seguimiento": "Proveedor confirma entrega mañana.",
    "created_at": "2026-10-28T23:55:43.000000Z",
    "id": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La solicitud no existe.
- `409` La solicitud ya no está pendiente.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-anular"></a>

#### `POST /api/v1/incidencias/{incidencia}/anular`

Cancelación administrativa por imposibilidad (p. ej. recursos que no llegarán); se notifica al reportante.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E08, E09, E18 → E17

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `motivo` | string | Obligatorio, 10 a 1000. |

**Ejemplo de petición**

```json
{
  "motivo": "El laboratorio será remodelado y el punto se elimina."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Incidencia cancelada.",
  "data": {
    "id": "01a184f1-057c-7148-87ac-a869b5e6310f",
    "codigo_ticket": "TKT-2026-000007",
    "estado": {
      "codigo": "E17",
      "nombre": "Cancelada"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` El estado actual no admite anulación (D07).
- `422` Validación de campos (`errors` trae el detalle por campo).


---

## E09 · Verificación de calidad y cierre

<a id="get-verificacion-pendientes"></a>

#### `GET /api/v1/verificacion/pendientes`

Bandeja de verificación: incidencias en E12, las más antiguas primero.

- **Roles (D09):** R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `per_page` | int | Por defecto 20. |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "codigo_ticket": "TKT-2026-000001",
      "resumen": "Luminaria apagada en Aula 101",
      "estado": {
        "codigo": "E12",
        "nombre": "Pendiente de verificación",
        "color": "cian",
        "requiere_accion_reportante": false
      },
      "prioridad": {
        "id": 2,
        "codigo": "ALTA",
        "nombre": "Alta",
        "color": "naranja"
      },
      "categoria": {
        "id": 1,
        "codigo": "ELECTRICA",
        "nombre": "Eléctrica"
      },
      "ubicacion": {
        "sede": {
          "id": 1,
          "codigo": "SEDE-PRINCIPAL",
          "nombre": "Campus Principal"
        },
        "edificio": {
          "id": 1,
          "codigo": "BLOQUE-A",
          "nombre": "Bloque A"
        },
        "piso": {
          "id": 1,
          "codigo": "P1",
          "nombre": "Piso 1"
        },
        "espacio": {
          "id": 1,
          "codigo": "A-101",
          "nombre": "Aula 101"
        },
        "texto": "Campus Principal > Bloque A > Piso 1 > Aula 101"
      },
      "tecnico_asignado": {
        "id": 4,
        "name": "Tomás Técnico Electricista"
      },
      "es_riesgo_inmediato": false,
      "posible_duplicado": false,
      "fecha_registro": "2026-10-28T23:55:38+00:00",
      "fecha_actualizacion": "2026-10-28T23:55:44+00:00",
      "fecha_cierre": null
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/verificacion/pendientes?page=1",
    "last": "http://localhost:8000/api/v1/verificacion/pendientes?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [
      {
        "url": null,
        "label": "&laquo; Anterior",
        "page": null,
        "active": false
      }
    ],
    "path": "http://localhost:8000/api/v1/verificacion/pendientes",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="post-incidencias-incidencia-verificar"></a>

#### `POST /api/v1/incidencias/{incidencia}/verificar`

Dictamen de calidad conforme.

- **Roles (D09):** R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** E12 → E14 (la categoría requiere confirmación) · E12 → E15 (no la requiere)

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `criterios` | object[] | Opcional. Cada uno: `criterio` (máx. 200) y `cumple` (boolean). Si alguno no cumple, hay que usar `rechazar-verificacion`. |
| `observaciones` | string | Opcional, máx. 1000. |

**Ejemplo de petición**

```json
{
  "criterios": [
    {
      "criterio": "Luminaria enciende",
      "cumple": true
    },
    {
      "criterio": "Difusor instalado",
      "cumple": true
    }
  ],
  "observaciones": "Trabajo conforme."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Verificación conforme registrada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "verificacion_id": 2,
    "estado": {
      "codigo": "E14",
      "nombre": "Pendiente de confirmación"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` Rol sin permiso, o el verificador es el mismo técnico ejecutor en un trabajo de riesgo alto o prioridad Crítica (segregación D03).
- `404` La incidencia o el recurso no existe.
- `409` No está en E12 (RN-034).
- `422` Faltan evidencias de ejecución (RN-033) o hay criterios incumplidos.

<a id="post-incidencias-incidencia-rechazar-verificacion"></a>

#### `POST /api/v1/incidencias/{incidencia}/rechazar-verificacion`

Dictamen no conforme: vuelve a corrección y suma un ciclo de reproceso.

- **Roles (D09):** R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** E12 → E13

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `criterios` | object[] | Opcional, igual que en `verificar`. |
| `observaciones` | string | Opcional. |
| `motivo_rechazo` | string | Obligatorio, 10 a 1000. |
| `correccion_requerida` | string | Obligatorio, 10 a 1000. |

**Ejemplo de petición**

```json
{
  "criterios": [
    {
      "criterio": "Luminaria enciende",
      "cumple": true
    },
    {
      "criterio": "Difusor instalado",
      "cumple": false
    }
  ],
  "motivo_rechazo": "Falta reinstalar el difusor.",
  "correccion_requerida": "Instalar el difusor de la luminaria."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Verificación no conforme registrada. La incidencia pasó a corrección.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "verificacion_id": 1,
    "estado": {
      "codigo": "E13",
      "nombre": "En corrección"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` Rol sin permiso o segregación de funciones (D03).
- `404` La incidencia o el recurso no existe.
- `409` No está en E12.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-planificar-correccion"></a>

#### `POST /api/v1/incidencias/{incidencia}/planificar-correccion`

Programa la corrección tras un rechazo de verificación o de conformidad.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E13 → E10

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `fecha_programada` | date | Obligatorio, hoy o posterior. |
| `hora_inicio` | HH:mm | Obligatorio. |
| `hora_fin` | HH:mm | Obligatorio, posterior al inicio. |
| `cuadrilla` | string | Opcional. |
| `observaciones` | string | Opcional. |

**Ejemplo de petición**

```json
{
  "fecha_programada": "2026-10-29",
  "hora_inicio": "08:00",
  "hora_fin": "09:00",
  "observaciones": "Instalar difusor."
}
```

**Respuesta 200**

```json
{
  "mensaje": "Corrección programada.",
  "data": {
    "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E10",
      "nombre": "Programada"
    },
    "plan": {
      "plan_id": 3,
      "fecha_programada": "2026-10-29",
      "hora_inicio": "08:00",
      "hora_fin": "09:00",
      "cuadrilla": null
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E13 (RN-036).
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="post-incidencias-incidencia-cierre-administrativo"></a>

#### `POST /api/v1/incidencias/{incidencia}/cierre-administrativo`

Cierra por inactividad del reportante cuando venció el plazo de confirmación.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** E14 → E15

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `notas` | string | Opcional, máx. 1000. |

**Respuesta 200**

```json
{
  "mensaje": "Cierre administrativo registrado.",
  "data": {
    "id": "01a184db-9e3a-7084-8e55-7c7c9ecc9d38",
    "codigo_ticket": "TKT-2026-000001",
    "estado": {
      "codigo": "E15",
      "nombre": "Cerrada"
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` No está en E14, o está en E11/E12/E13 con trabajo pendiente (RN-037).
- `422` Aún no vence `PLAZO_CONFIRMACION_DIAS_HABILES`; trae `vence`.

Ejemplo de error `422`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/regla-de-negocio",
  "title": "Regla de negocio incumplida.",
  "status": 422,
  "detail": "El plazo de confirmación del reportante (5 días hábiles) aún no ha vencido.",
  "instance": "/api/v1/incidencias/01a184f0-d6d2-73b0-8089-25fd7ba5a503/cierre-administrativo",
  "regla": "HU-9.3",
  "vence": "2026-11-05T23:00:00+00:00"
}
```


---

## E10 · Consola, trazabilidad, SLA y analítica

<a id="get-consola-incidencias"></a>

#### `GET /api/v1/consola/incidencias`

Consola de incidencias con filtros combinables, tiempo de atención y semáforo SLA por fila. R04 solo ve las suyas.

- **Roles (D09):** R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `estado` | string | Códigos separados por coma. |
| `sede_id` | int |  |
| `edificio_id` | int |  |
| `categoria_id` | int |  |
| `prioridad` | string | CRITICA, ALTA, MEDIA o BAJA. |
| `tecnico_id` | int |  |
| `q` | string | Código o resumen. |
| `fecha_desde` | date |  |
| `fecha_hasta` | date | ≥ fecha_desde. |
| `riesgo_inmediato` | boolean |  |
| `per_page` | int | 1-100, por defecto 20. |
| `page` | int |  |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": "01a184f1-057c-7148-87ac-a869b5e6310f",
      "codigo_ticket": "TKT-2026-000007",
      "resumen": "Toma de laboratorio sin energía",
      "estado": {
        "codigo": "E17",
        "nombre": "Cancelada",
        "color": "gris",
        "requiere_accion_reportante": false
      },
      "prioridad": {
        "id": 4,
        "codigo": "BAJA",
        "nombre": "Baja",
        "color": "verde"
      },
      "categoria": {
        "id": 1,
        "codigo": "ELECTRICA",
        "nombre": "Eléctrica"
      },
      "ubicacion": {
        "sede": {
          "id": 1,
          "codigo": "SEDE-PRINCIPAL",
          "nombre": "Campus Principal"
        },
        "edificio": {
          "id": 1,
          "codigo": "BLOQUE-A",
          "nombre": "Bloque A"
        },
        "piso": {
          "id": 3,
          "codigo": "P3",
          "nombre": "Piso 3"
        },
        "espacio": {
          "id": 6,
          "codigo": "LAB-302",
          "nombre": "Laboratorio 302"
        },
        "texto": "Campus Principal > Bloque A > Piso 3 > Laboratorio 302"
      },
      "tecnico_asignado": {
        "id": 4,
        "name": "Tomás Técnico Electricista"
      },
      "es_riesgo_inmediato": false,
      "posible_duplicado": false,
      "fecha_registro": "2026-10-28T23:55:50+00:00",
      "fecha_actualizacion": "2026-10-28T23:55:51+00:00",
      "fecha_cierre": "2026-10-28T23:55:51+00:00",
      "tiempo_atencion": {
        "horas": 0,
        "en_curso": false,
        "texto": "0 horas"
      },
      "sla": {
        "estado": "CUMPLIDA",
        "color": "verde",
        "porcentaje_consumido": null,
        "minutos_restantes": null,
        "horas_retraso": null,
        "fecha_limite": "2026-11-10T15:00:00+00:00"
      }
    },
    {
      "id": "01a184f0-fe79-7351-aafe-077cc2cd59bd",
      "codigo_ticket": "TKT-2026-000006",
      "resumen": "Tomacorriente suelto",
      "estado": {
        "codigo": "E10",
        "nombre": "Programada",
        "color": "cian",
        "requiere_accion_reportante": false
      },
      "prioridad": {
        "id": 4,
        "codigo": "BAJA",
        "nombre": "Baja",
        "color": "verde"
      },
      "categoria": {
        "id": 1,
        "codigo": "ELECTRICA",
        "nombre": "Eléctrica"
      },
      "ubicacion": {
        "sede": {
          "id": 1,
          "codigo": "SEDE-PRINCIPAL",
          "nombre": "Campus Principal"
        },
        "edificio": {
          "id": 1,
          "codigo": "BLOQUE-A",
          "nombre": "Bloque A"
        },
        "piso": {
          "id": 1,
          "codigo": "P1",
          "nombre": "Piso 1"
        },
        "espacio": {
          "id": 2,
          "codigo": "A-102",
          "nombre": "Aula 102"
        },
        "texto": "Campus Principal > Bloque A > Piso 1 > Aula 102"
      },
      "tecnico_asignado": {
        "id": 4,
        "name": "Tomás Técnico Electricista"
      },
      "es_riesgo_inmediato": false,
      "posible_duplicado": false,
      "fecha_registro": "2026-10-28T23:55:48+00:00",
      "fecha_actualizacion": "2026-10-28T23:55:50+00:00",
      "fecha_cierre": null,
      "tiempo_atencion": {
        "horas": 0,
        "en_curso": true,
        "texto": "0 horas"
      },
      "sla": {
        "estado": "EN_PLAZO",
        "color": "verde",
        "porcentaje_consumido": 0,
        "minutos_restantes": 4320,
        "horas_retraso": null,
        "fecha_limite": "2026-11-10T15:00:00+00:00"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 2,
    "total": 7,
    "sin_resultados": false
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-incidencias-incidencia-historial"></a>

#### `GET /api/v1/incidencias/{incidencia}/historial`

Línea de tiempo de auditoría. R01 recibe solo los eventos públicos (`alcance: PUBLICO`); los demás roles, la completa.

- **Roles (D09):** R01 Reportante, R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R04 Técnico de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.
- **Nota:** La bitácora es de solo inserción: no hay PUT, PATCH ni DELETE (405).

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `categoria` | string | Opcional: ESTADO, ACTIVIDAD, FORENSE o COMUNICACION. |

**Respuesta 200**

```json
{
  "alcance": "COMPLETO",
  "data": [
    {
      "id": "01a184f0-d6d6-7365-a477-f747dbcf94cc",
      "incidencia_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "tipo_evento": "CREACION",
      "categoria": "ESTADO",
      "fecha_hora": "2026-10-28T23:55:38+00:00",
      "usuario": {
        "id": 1,
        "name": "Laura Reportante"
      },
      "rol": "R01",
      "estado_anterior": null,
      "estado_nuevo": {
        "codigo": "E01",
        "nombre": "Registrada"
      },
      "motivo": "Incidencia radicada por el reportante.",
      "datos": {
        "codigo_ticket": "TKT-2026-000001",
        "canal": "WEB",
        "posible_duplicado": false,
        "es_problema_diferente": false
      },
      "evidencias": [
        {
          "evidencia_id": 1,
          "sha256": "21391377d875154ec5cc383409732159c8c82a47b00eeb6d2970a474846dd138",
          "uri": "evidencias://temporales/1/bb4fca77-c07f-47a8-bce6-a43f84f4b8fb.png",
          "mime_type": "image/png",
          "nombre_original": "tablero.png"
        }
      ]
    },
    {
      "id": "01a184f0-d91e-7182-aee5-9c89bc1b8869",
      "incidencia_id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "tipo_evento": "SUSCRIPCION_SEGUIMIENTO",
      "categoria": "ACTIVIDAD",
      "fecha_hora": "2026-10-28T23:55:39+00:00",
      "usuario": {
        "id": 10,
        "name": "Diana Docente Verificadora"
      },
      "rol": "R01",
      "estado_anterior": null,
      "estado_nuevo": null,
      "motivo": "Usuario adherido al seguimiento del reporte existente.",
      "datos": null,
      "evidencias": null
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` No existe, o R01 consulta una ajena.

<a id="get-consola-plazos"></a>

#### `GET /api/v1/consola/plazos`

Incidencias abiertas con su estado SLA (EN_PLAZO, PROXIMA_A_VENCER, VENCIDA) y un resumen con los totales.

- **Roles (D09):** R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `estado_sla` | string | Opcional: EN_PLAZO, PROXIMA_A_VENCER o VENCIDA. |

**Respuesta 200**

```json
{
  "resumen": {
    "en_plazo": 2,
    "proximas_a_vencer": 0,
    "vencidas": 0
  },
  "data": [
    {
      "id": "01a184f0-d6d2-73b0-8089-25fd7ba5a503",
      "codigo_ticket": "TKT-2026-000001",
      "resumen": "Luminaria apagada en Aula 101",
      "estado": {
        "codigo": "E13",
        "nombre": "En corrección",
        "color": "rojo",
        "requiere_accion_reportante": false
      },
      "prioridad": {
        "id": 2,
        "codigo": "ALTA",
        "nombre": "Alta",
        "color": "naranja"
      },
      "categoria": {
        "id": 1,
        "codigo": "ELECTRICA",
        "nombre": "Eléctrica"
      },
      "tecnico_asignado": {
        "id": 4,
        "name": "Tomás Técnico Electricista"
      },
      "es_riesgo_inmediato": false,
      "posible_duplicado": false,
      "fecha_registro": "2026-10-28T23:55:38+00:00",
      "fecha_actualizacion": "2026-10-28T23:55:46+00:00",
      "fecha_cierre": null,
      "sla": {
        "estado": "EN_PLAZO",
        "color": "verde",
        "porcentaje_consumido": 0,
        "minutos_restantes": 1440,
        "horas_retraso": null,
        "fecha_limite": "2026-11-03T17:00:00+00:00"
      }
    },
    {
      "id": "01a184f0-fe79-7351-aafe-077cc2cd59bd",
      "codigo_ticket": "TKT-2026-000006",
      "resumen": "Tomacorriente suelto",
      "estado": {
        "codigo": "E10",
        "nombre": "Programada",
        "color": "cian",
        "requiere_accion_reportante": false
      },
      "prioridad": {
        "id": 4,
        "codigo": "BAJA",
        "nombre": "Baja",
        "color": "verde"
      },
      "categoria": {
        "id": 1,
        "codigo": "ELECTRICA",
        "nombre": "Eléctrica"
      },
      "tecnico_asignado": {
        "id": 4,
        "name": "Tomás Técnico Electricista"
      },
      "es_riesgo_inmediato": false,
      "posible_duplicado": false,
      "fecha_registro": "2026-10-28T23:55:48+00:00",
      "fecha_actualizacion": "2026-10-28T23:55:50+00:00",
      "fecha_cierre": null,
      "sla": {
        "estado": "EN_PLAZO",
        "color": "verde",
        "porcentaje_consumido": 0,
        "minutos_restantes": 4320,
        "horas_retraso": null,
        "fecha_limite": "2026-11-10T15:00:00+00:00"
      }
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-analitica-metricas-basicas"></a>

#### `GET /api/v1/analitica/metricas-basicas`

Indicadores del periodo: totales por estado y prioridad, MTTR, cumplimiento SLA y fallas recurrentes.

- **Roles (D09):** R02 Gestor de incidencias, R03 Coordinador de mantenimiento, R05 Responsable de autorizaciones, R06 Verificador de intervenciones, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.
- **Nota:** Sin datos en el periodo responde `sin_datos: true` y métricas en 0 o `null`, nunca un error.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `periodo` | string | semana, mes (defecto), semestre o rango. |
| `desde` | date | Obligatorio si `periodo=rango`. |
| `hasta` | date | Obligatorio si `periodo=rango`, ≥ desde. |

**Respuesta 200**

```json
{
  "data": {
    "periodo": {
      "desde": "2026-10-01T00:00:00+00:00",
      "hasta": "2026-10-28T23:55:52+00:00"
    },
    "sin_datos": false,
    "total_incidencias": 7,
    "por_estado": [
      {
        "codigo": "E01",
        "nombre": "Registrada",
        "total": 0
      }
    ],
    "por_prioridad": {
      "SIN_CLASIFICAR": 3,
      "ALTA": 1,
      "BAJA": 2,
      "CRITICA": 1
    },
    "mttr": {
      "incidencias_cerradas": 2,
      "horas_promedio": 0,
      "texto": "Tiempo promedio: 0 horas"
    },
    "cumplimiento_sla": {
      "evaluadas": 1,
      "cumplidas": 1,
      "porcentaje": 100
    },
    "fallas_recurrentes": {
      "ventana_dias": 30,
      "minimo_reportes": 3,
      "data": []
    }
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).


---

## E11 · Parametrización (ubicaciones, categorías y parámetros)

<a id="get-admin-ubicaciones"></a>

#### `GET /api/v1/admin/ubicaciones`

Árbol completo de ubicaciones, incluidas las inactivas.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 1,
      "codigo": "SEDE-PRINCIPAL",
      "nombre": "Campus Principal",
      "direccion": "Calle 17 # 4-81",
      "activo": true,
      "created_at": "2026-10-28T23:55:27.000000Z",
      "updated_at": "2026-10-28T23:55:27.000000Z",
      "edificios": [
        {
          "id": 1,
          "sede_id": 1,
          "codigo": "BLOQUE-A",
          "nombre": "Bloque A",
          "activo": true,
          "created_at": "2026-10-28T23:55:27.000000Z",
          "updated_at": "2026-10-28T23:55:27.000000Z",
          "pisos": [
            {
              "id": 1,
              "edificio_id": 1,
              "codigo": "P1",
              "nombre": "Piso 1",
              "nivel": 1,
              "activo": true,
              "created_at": "2026-10-28T23:55:27.000000Z",
              "updated_at": "2026-10-28T23:55:27.000000Z",
              "espacios": [
                {
                  "id": 1,
                  "piso_id": 1,
                  "codigo": "A-101",
                  "nombre": "Aula 101",
                  "tipo": "AULA",
                  "activo": true,
                  "created_at": "2026-10-28T23:55:27.000000Z",
                  "updated_at": "2026-10-28T23:55:27.000000Z"
                }
              ]
            }
          ]
        }
      ]
    },
    {
      "id": 2,
      "codigo": "SEDE-NORTE",
      "nombre": "Sede Norte",
      "direccion": "Avenida Carrera 45 # 103-60",
      "activo": true,
      "created_at": "2026-10-28T23:55:27.000000Z",
      "updated_at": "2026-10-28T23:55:27.000000Z",
      "edificios": [
        {
          "id": 3,
          "sede_id": 2,
          "codigo": "BLOQUE-C",
          "nombre": "Bloque C",
          "activo": true,
          "created_at": "2026-10-28T23:55:27.000000Z",
          "updated_at": "2026-10-28T23:55:27.000000Z",
          "pisos": [
            {
              "id": 6,
              "edificio_id": 3,
              "codigo": "P1",
              "nombre": "Piso 1",
              "nivel": 1,
              "activo": true,
              "created_at": "2026-10-28T23:55:27.000000Z",
              "updated_at": "2026-10-28T23:55:27.000000Z",
              "espacios": [
                {
                  "id": 11,
                  "piso_id": 6,
                  "codigo": "C-101",
                  "nombre": "Aula 101",
                  "tipo": "AULA",
                  "activo": true,
                  "created_at": "2026-10-28T23:55:27.000000Z",
                  "updated_at": "2026-10-28T23:55:27.000000Z"
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="post-admin-ubicaciones-nivel"></a>

#### `POST /api/v1/admin/ubicaciones/{nivel}`

Crea una sede, edificio, piso o espacio. `{nivel}`: `sedes`, `edificios`, `pisos` o `espacios`.

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `codigo` | string | Obligatorio, máx. 30, único dentro del padre. |
| `nombre` | string | Obligatorio, máx. 120, único dentro del padre. |
| `sede_id | edificio_id | piso_id` | int | Obligatorio según el nivel: edificios → `sede_id`, pisos → `edificio_id`, espacios → `piso_id`. |
| `direccion` | string | Solo sedes, opcional, máx. 255. |
| `nivel` | int | Solo pisos, opcional, -10 a 200. |
| `tipo` | string | Solo espacios, opcional, máx. 40 (AULA, LABORATORIO, BANO...). |

**Ejemplo de petición**

```json
{
  "sede_id": 3,
  "codigo": "BLOQUE-S",
  "nombre": "Bloque S"
}
```

**Respuesta 201**

```json
{
  "data": {
    "codigo": "BLOQUE-S",
    "nombre": "Bloque S",
    "sede_id": 3,
    "activo": true,
    "updated_at": "2026-10-28T23:55:55.000000Z",
    "created_at": "2026-10-28T23:55:55.000000Z",
    "id": 4
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` "Esa ubicación ya se encuentra parametrizada." o campos inválidos.

Ejemplo de error `422`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/regla-de-negocio",
  "title": "Los datos enviados no son válidos.",
  "status": 422,
  "detail": "Esa ubicación ya se encuentra parametrizada. (y 1 error más)",
  "instance": "/api/v1/admin/ubicaciones/edificios",
  "errors": {
    "codigo": [
      "Esa ubicación ya se encuentra parametrizada."
    ],
    "nombre": [
      "Esa ubicación ya se encuentra parametrizada."
    ]
  }
}
```

<a id="put-admin-ubicaciones-nivel-id"></a>

#### `PUT /api/v1/admin/ubicaciones/{nivel}/{id}`

Edita una ubicación (mismos campos que al crear, todos opcionales).

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `codigo` | string | Opcional, único dentro del padre. |
| `nombre` | string | Opcional, único dentro del padre. |
| `<padre>_id` | int | Opcional, para moverla. |
| `direccion | nivel | tipo` |  | Según el nivel. |

**Ejemplo de petición**

```json
{
  "nombre": "Bloque S - Laboratorios"
}
```

**Respuesta 200**

```json
{
  "data": {
    "id": 4,
    "sede_id": 3,
    "codigo": "BLOQUE-S",
    "nombre": "Bloque S - Laboratorios",
    "activo": true,
    "created_at": "2026-10-28T23:55:55.000000Z",
    "updated_at": "2026-10-28T23:55:55.000000Z"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="patch-admin-ubicaciones-nivel-id-estado"></a>

#### `PATCH /api/v1/admin/ubicaciones/{nivel}/{id}/estado`

Activa o inactiva una ubicación (deja de salir en el árbol activo).

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `activo` | boolean | Obligatorio. |
| `motivo` | string | Opcional, máx. 500. |

**Ejemplo de petición**

```json
{
  "activo": false,
  "motivo": "En remodelación."
}
```

**Respuesta 200**

```json
{
  "data": {
    "id": 4,
    "sede_id": 3,
    "codigo": "BLOQUE-S",
    "nombre": "Bloque S - Laboratorios",
    "activo": false,
    "created_at": "2026-10-28T23:55:55.000000Z",
    "updated_at": "2026-10-28T23:55:55.000000Z"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="delete-admin-ubicaciones-nivel-id"></a>

#### `DELETE /api/v1/admin/ubicaciones/{nivel}/{id}`

Elimina una ubicación sin incidencias ni niveles dependientes. Responde 204.

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 204**

`(204 sin cuerpo)`

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `409` Tiene incidencias históricas (solo puede inactivarse) o niveles dependientes.

Ejemplo de error `409`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/conflicto-de-estado",
  "title": "Conflicto con el estado actual.",
  "status": 409,
  "detail": "La ubicación tiene incidencias históricas asociadas; solo puede inactivarse.",
  "instance": "/api/v1/admin/ubicaciones/espacios/1",
  "regla": "HU-11.1"
}
```

<a id="get-admin-categorias"></a>

#### `GET /api/v1/admin/categorias`

Categorías y subcategorías, incluidas las inactivas.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": [
    {
      "id": 26,
      "categoria_padre_id": null,
      "codigo": "CLIMATIZACION",
      "nombre": "Aire Acondicionado y Climatización",
      "descripcion": null,
      "exige_evidencia_fotografica": false,
      "requiere_confirmacion_cierre": true,
      "nivel_riesgo_base": "BAJO",
      "especialidad_id": 5,
      "activo": true,
      "created_at": "2026-10-28T23:55:27.000000Z",
      "updated_at": "2026-10-28T23:55:27.000000Z",
      "subcategorias": [
        {
          "id": 27,
          "categoria_padre_id": 26,
          "codigo": "CLIMATIZACION_1",
          "nombre": "Aire acondicionado",
          "descripcion": null,
          "exige_evidencia_fotografica": false,
          "requiere_confirmacion_cierre": true,
          "nivel_riesgo_base": "BAJO",
          "especialidad_id": 5,
          "activo": true,
          "created_at": "2026-10-28T23:55:27.000000Z",
          "updated_at": "2026-10-28T23:55:27.000000Z"
        }
      ],
      "especialidad": {
        "id": 5,
        "codigo": "CLIMATIZACION",
        "nombre": "Técnico de climatización",
        "activo": true,
        "created_at": "2026-10-28T23:55:27.000000Z",
        "updated_at": "2026-10-28T23:55:27.000000Z"
      }
    },
    {
      "id": 22,
      "categoria_padre_id": null,
      "codigo": "CERRAJERIA",
      "nombre": "Cerrajería",
      "descripcion": null,
      "exige_evidencia_fotografica": false,
      "requiere_confirmacion_cierre": true,
      "nivel_riesgo_base": "BAJO",
      "especialidad_id": 3,
      "activo": true,
      "created_at": "2026-10-28T23:55:27.000000Z",
      "updated_at": "2026-10-28T23:55:27.000000Z",
      "subcategorias": [
        {
          "id": 23,
          "categoria_padre_id": 22,
          "codigo": "CERRAJERIA_1",
          "nombre": "Chapas",
          "descripcion": null,
          "exige_evidencia_fotografica": false,
          "requiere_confirmacion_cierre": true,
          "nivel_riesgo_base": "BAJO",
          "especialidad_id": 3,
          "activo": true,
          "created_at": "2026-10-28T23:55:27.000000Z",
          "updated_at": "2026-10-28T23:55:27.000000Z"
        }
      ],
      "especialidad": {
        "id": 3,
        "codigo": "CERRAJERIA",
        "nombre": "Cerrajero",
        "activo": true,
        "created_at": "2026-10-28T23:55:27.000000Z",
        "updated_at": "2026-10-28T23:55:27.000000Z"
      }
    }
  ]
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="post-admin-categorias"></a>

#### `POST /api/v1/admin/categorias`

Crea una categoría o, con `categoria_padre_id`, una subcategoría.

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `codigo` | string | Obligatorio, máx. 40, único. |
| `nombre` | string | Obligatorio, máx. 120. |
| `categoria_padre_id` | int | Opcional, debe ser una categoría raíz. |
| `descripcion` | string | Opcional, máx. 500. |
| `exige_evidencia_fotografica` | boolean | Opcional (RN-005). |
| `requiere_confirmacion_cierre` | boolean | Opcional; decide si la verificación termina en E14 o E15. |
| `nivel_riesgo_base` | string | Opcional: BAJO, MEDIO o ALTO. |
| `especialidad_id` | int | Opcional, existe. |
| `activo` | boolean | Opcional. |

**Ejemplo de petición**

```json
{
  "codigo": "JARDINERIA",
  "nombre": "Jardinería",
  "nivel_riesgo_base": "BAJO",
  "requiere_confirmacion_cierre": false
}
```

**Respuesta 201**

```json
{
  "data": {
    "codigo": "JARDINERIA",
    "nombre": "Jardinería",
    "requiere_confirmacion_cierre": false,
    "nivel_riesgo_base": "BAJO",
    "activo": true,
    "updated_at": "2026-10-28T23:55:56.000000Z",
    "created_at": "2026-10-28T23:55:56.000000Z",
    "id": 39
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="put-admin-categorias-id"></a>

#### `PUT /api/v1/admin/categorias/{id}`

Edita una categoría (mismos campos, todos opcionales).

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `(los de crear)` |  | Todos opcionales; `categoria_padre_id` no puede ser ella misma. |

**Ejemplo de petición**

```json
{
  "descripcion": "Poda, riego y zonas verdes.",
  "exige_evidencia_fotografica": true
}
```

**Respuesta 200**

```json
{
  "data": {
    "id": 39,
    "categoria_padre_id": null,
    "codigo": "JARDINERIA",
    "nombre": "Jardinería",
    "descripcion": "Poda, riego y zonas verdes.",
    "exige_evidencia_fotografica": true,
    "requiere_confirmacion_cierre": false,
    "nivel_riesgo_base": "BAJO",
    "especialidad_id": null,
    "activo": true,
    "created_at": "2026-10-28T23:55:56.000000Z",
    "updated_at": "2026-10-28T23:55:56.000000Z"
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `404` La incidencia o el recurso no existe.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-admin-parametros-proceso"></a>

#### `GET /api/v1/admin/parametros-proceso`

Metas SLA por prioridad y plazo de aclaraciones (lectura para R03).

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": {
    "prioridades": [
      {
        "codigo": "CRITICA",
        "nombre": "Crítica",
        "horas_primera_respuesta": 1,
        "horas_resolucion": 6
      }
    ],
    "plazo_aclaracion_dias_habiles": 5
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="put-admin-parametros-proceso"></a>

#### `PUT /api/v1/admin/parametros-proceso`

Actualiza metas SLA por prioridad y el plazo de aclaraciones; invalida la caché.

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `prioridades` | object[] | Opcional. Cada uno: `codigo` (prioridad existente), `horas_primera_respuesta` (1-2000) y `horas_resolucion` (1-5000). |
| `plazo_aclaracion_dias_habiles` | int | Opcional, 1 a 60. |

**Ejemplo de petición**

```json
{
  "prioridades": [
    {
      "codigo": "BAJA",
      "horas_primera_respuesta": 16,
      "horas_resolucion": 80
    }
  ],
  "plazo_aclaracion_dias_habiles": 3
}
```

**Respuesta 200**

```json
{
  "data": {
    "prioridades": [
      {
        "codigo": "CRITICA",
        "nombre": "Crítica",
        "horas_primera_respuesta": 1,
        "horas_resolucion": 6
      }
    ],
    "plazo_aclaracion_dias_habiles": 3
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Validación de campos (`errors` trae el detalle por campo).

<a id="get-admin-parametros"></a>

#### `GET /api/v1/admin/parametros`

Matriz de prioridad, metas SLA y parámetros generales del sistema.

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Respuesta 200**

```json
{
  "data": {
    "matriz_prioridad": [
      {
        "impacto": "ALTO",
        "urgencia": "ALTA",
        "prioridad": "CRITICA"
      }
    ],
    "sla": [
      {
        "codigo": "CRITICA",
        "nombre": "Crítica",
        "horas_primera_respuesta": 1,
        "horas_resolucion": 6
      }
    ],
    "parametros": [
      {
        "clave": "RECURRENCIA_MINIMO_REPORTES",
        "valor": 3,
        "tipo_dato": "entero",
        "grupo": "ANALITICA",
        "descripcion": "Reportes de la misma categoría en un espacio para considerarlo falla recurrente.",
        "actualizado_en": "2026-10-28T23:55:27+00:00"
      }
    ]
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.

<a id="put-admin-parametros"></a>

#### `PUT /api/v1/admin/parametros`

Actualiza la matriz Impacto × Urgencia, las metas SLA o los parámetros; aplica en caliente y queda auditado (valor anterior y nuevo).

- **Roles (D09):** R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Body**

| Campo | Tipo | Reglas |
|---|---|---|
| `matriz` | object[] | Opcional. Cada uno: `impacto` (ALTO/MEDIO/BAJO), `urgencia` (ALTA/MEDIA/BAJA), `prioridad` (CRITICA/ALTA/MEDIA/BAJA). |
| `sla` | object[] | Opcional. Igual que `prioridades` en `parametros-proceso`. |
| `parametros` | object | Opcional. Mapa `CLAVE: entero` (1 a 100000000). Solo claves existentes (ver respuesta del GET). |

**Ejemplo de petición**

```json
{
  "matriz": [
    {
      "impacto": "BAJO",
      "urgencia": "BAJA",
      "prioridad": "BAJA"
    }
  ],
  "parametros": {
    "MAXIMO_REAPERTURAS": 2
  }
}
```

**Respuesta 200**

```json
{
  "data": {
    "matriz_prioridad": [
      {
        "impacto": "ALTO",
        "urgencia": "ALTA",
        "prioridad": "CRITICA"
      }
    ],
    "sla": [
      {
        "codigo": "CRITICA",
        "nombre": "Crítica",
        "horas_primera_respuesta": 1,
        "horas_resolucion": 6
      }
    ],
    "parametros": [
      {
        "clave": "RECURRENCIA_MINIMO_REPORTES",
        "valor": 3,
        "tipo_dato": "entero",
        "grupo": "ANALITICA",
        "descripcion": "Reportes de la misma categoría en un espacio para considerarlo falla recurrente.",
        "actualizado_en": "2026-10-28T23:55:27+00:00"
      }
    ]
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
- `422` Clave desconocida o valor fuera de rango.

Ejemplo de error `422`:

```json
{
  "type": "https://praxis.ecci.edu.co/problemas/regla-de-negocio",
  "title": "Los datos enviados no son válidos.",
  "status": 422,
  "errors": {
    "parametros": [
      "Parámetros desconocidos: NO_EXISTE"
    ]
  }
}
```

<a id="get-admin-parametros-historial"></a>

#### `GET /api/v1/admin/parametros/historial`

Eventos de auditoría de configuración (cambios de parámetros, SLA, matriz, categorías, ubicaciones).

- **Roles (D09):** R03 Coordinador de mantenimiento, R07 Administrador de plataforma
- **Estado:** no cambia el estado de la incidencia.

**Parámetros de consulta**

| Parámetro | Tipo | Reglas |
|---|---|---|
| `per_page` | int | Por defecto 50. |

**Respuesta 200**

```json
{
  "data": [
    {
      "id": "01a184f1-1b31-7246-84a8-6aaae7031c54",
      "incidencia_id": null,
      "tipo_evento": "CATEGORIA_CREADA",
      "categoria": "CONFIGURACION",
      "fecha_hora": "2026-10-28T23:55:56+00:00",
      "usuario": {
        "id": 9,
        "name": "Alberto Administrador"
      },
      "rol": "R07",
      "estado_anterior": null,
      "estado_nuevo": null,
      "motivo": null,
      "datos": {
        "nuevo": {
          "codigo": "JARDINERIA",
          "nombre": "Jardinería",
          "requiere_confirmacion_cierre": false,
          "nivel_riesgo_base": "BAJO",
          "activo": true,
          "updated_at": "2026-10-28T23:55:56.000000Z",
          "created_at": "2026-10-28T23:55:56.000000Z",
          "id": 39
        }
      },
      "evidencias": null
    },
    {
      "id": "01a184f1-1bb8-7354-99b1-3f3019a40f4f",
      "incidencia_id": null,
      "tipo_evento": "CATEGORIA_ACTUALIZADA",
      "categoria": "CONFIGURACION",
      "fecha_hora": "2026-10-28T23:55:56+00:00",
      "usuario": {
        "id": 9,
        "name": "Alberto Administrador"
      },
      "rol": "R07",
      "estado_anterior": null,
      "estado_nuevo": null,
      "motivo": null,
      "datos": {
        "anterior": {
          "id": 39,
          "categoria_padre_id": null,
          "codigo": "JARDINERIA",
          "nombre": "Jardinería",
          "descripcion": null,
          "exige_evidencia_fotografica": false,
          "requiere_confirmacion_cierre": false,
          "nivel_riesgo_base": "BAJO",
          "especialidad_id": null,
          "activo": true,
          "created_at": "2026-10-28T23:55:56.000000Z",
          "updated_at": "2026-10-28T23:55:56.000000Z"
        },
        "nuevo": {
          "id": 39,
          "categoria_padre_id": null,
          "codigo": "JARDINERIA",
          "nombre": "Jardinería",
          "descripcion": "Poda, riego y zonas verdes.",
          "exige_evidencia_fotografica": true,
          "requiere_confirmacion_cierre": false,
          "nivel_riesgo_base": "BAJO",
          "especialidad_id": null,
          "activo": true,
          "created_at": "2026-10-28T23:55:56.000000Z",
          "updated_at": "2026-10-28T23:55:56.000000Z"
        }
      },
      "evidencias": null
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/admin/parametros/historial?page=1",
    "last": "http://localhost:8000/api/v1/admin/parametros/historial?page=7",
    "prev": null,
    "next": "http://localhost:8000/api/v1/admin/parametros/historial?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 7,
    "links": [
      {
        "url": null,
        "label": "&laquo; Anterior",
        "page": null,
        "active": false
      }
    ],
    "path": "http://localhost:8000/api/v1/admin/parametros/historial",
    "per_page": 2,
    "to": 2,
    "total": 13
  }
}
```

**Errores típicos**

- `401` Sin token o token vencido.
- `403` El rol activo no está autorizado para la operación (D09) o `X-Active-Role` no es un rol del usuario.
