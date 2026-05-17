# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Ojo en la Via** is a citizen reporting platform for infrastructure issues in Villavicencio, Colombia. Citizens report street problems via an interactive map; admins manage and update report statuses.

**Stack:** PHP 8.2+ · MongoDB (via `mongodb/mongodb` v2.2) · Vanilla JS · Leaflet.js maps · Docker · Railway.app deployment

## Development Commands

```bash
# Install PHP dependencies
composer install

# Run local development server (serves from /public)
php -S 0.0.0.0:8000 -t public

# Build and run with Docker
docker build -t ojo-en-la-via .
docker run -p 8000:80 ojo-en-la-via
```

There are no automated tests or linting tools configured in this project.

## Architecture

### Entry Points & Routing

There is no router. URL paths map directly to PHP files:

- `public/index.php` - Login/registration
- `public/forgot_password.php` / `public/reset_password.php` - Password reset flow (PHPMailer + Brevo SMTP)
- `public/views/usuario/inicio.php` - Citizen home, map view, report creation
- `public/views/usuario/alertas.php` - Reports list with comments and embedded report likes
- `public/views/usuario/perfil.php` - User profile and notification history
- `public/views/admin/panel.php` - Admin dashboard, stats, map, report management

### Database

- **Connection:** `config/conexion.php` — function `conectarMongoDB()` returns a `\MongoDB\Database` instance. The MongoDB URI is hardcoded here (production credentials). No `.env` file is used.
- All document IDs are MongoDB `ObjectId`; validate with `/^[a-f\d]{24}$/i` before querying.

**Collection schemas:**

`usuario`: `_id`, `nombre_completo`, `telefono`, `email`, `password` (bcrypt), `estado` (bool), `fecha_creacion`, `foto_perfil` (string path), `rol` ("ciudadano"|"admin"), `tokens` (array of remember-me token documents with expiry), `reset_password` (nullable password reset token document)

`Reportes`: `_id`, `usuario_id` (ObjectId), `usuario_creador_id` (legacy alias), `estado` ("pendiente"|"en_revision"|"notificado"|"resuelto"), `fecha_reporte` (UTCDateTime), `fecha_estado` (UTCDateTime), `tipo`/`tipo_incidente`, `descripcion`, `ubicacion`, `latitud`, `longitud`, `direccion_texto`, `imagenes` (array of filename strings), `likes` (array of `{usuario_id, fecha_like}`), `comentarios` (array of `{_id, usuario_id, comentario, comentario_padre_id, fecha_comentario, likes, eliminado, editado}`), `historial_estados` (array)

`notificaciones`: `_id`, `usuario_destino_id`, `usuario_origen_id`, `tipo` ("comentario"|"respuesta_comentario"|"like_reporte"|"like_comentario"|"estado_reporte"), `titulo`, `mensaje`, `reporte_id`, `comentario_id` (nullable), `leida` (bool), `fecha` (UTCDateTime)

`tipo_incidente`: `_id`, `nombre` (string) — reference collection, no CRUD endpoints

**ID type inconsistency:** older documents may store `usuario_id` as a plain string instead of `ObjectId`. Queries on owner fields must use `$or` to match both types.

**Field name inconsistency:** report type is stored as either `tipo` or `tipo_incidente` depending on when the document was created.

### Controllers

PHP classes (not endpoints) used by admin views for aggregation queries:

- `controllers/admin_controlador.php` — `AdminControlador`: `obtenerEstadisticas()`, `obtenerUsuarios($limite)`
- `controllers/analyticscontrolador.php` — `AnalyticsControlador`: `obtenerEstadisticasGenerales($dias)`, uses `DateTimeZone('America/Bogota')` for date math

### API Endpoints

All API files are PHP scripts returning JSON. Standard response shape:

```json
{ "ok": true, "data": { ... } }
{ "ok": false, "mensaje": "error description" }
```

| Action | Path |
|--------|------|
| Report CRUD (citizen) | `public/views/usuario/reportes/controladores/` |
| Comment CRUD + likes | `public/views/usuario/reportes/controladores/` |
| Admin: update report status | `public/views/admin/actualizar_estado_reporte.php` |
| Admin: user management, role changes, mass notifications | `public/views/admin/api_admin.php` |

### Authentication & Roles

`config/auth_helper.php` handles session and cookie-based auth.

**Session keys:** `$_SESSION['usuario_id']` (string), `$_SESSION['usuario_nombre']`, `$_SESSION['usuario_email']`, `$_SESSION['foto_perfil']`, `$_SESSION['usuario_rol']` ("ciudadano"|"admin").

**Remember-me:** 64-char hex token stored in `usuario.tokens[]` with expiry; cookie `remember_token` is HttpOnly + SameSite=Lax, 30-day expiry.

**Password reset:** token stored in `usuario.reset_password` document → email link → validated on POST → cleared after successful reset.

### Notifications System

`public/views/usuario/reportes/modelos/notificaciones_modelo.php` — `crearNotificacionUsuario()` inserts notification documents. Self-notifications are suppressed (no notification when acting on your own content).

Admin panel uses Server-Sent Events (SSE) with exponential backoff reconnection in `admin-notificaciones.js`. Includes Visibility API integration (pauses when tab is hidden) and a polling fallback.

### File Uploads

Images saved to `public/uploads/reportes/` with filename `uniqid('reporte_', true) . '.' . ext`. Validation: extension whitelist (jpg/jpeg/png/webp), MIME type, and size limit. Filename strings are stored in `Reportes.imagenes[]`.

### Frontend

No build step or framework. JavaScript files are loaded directly in PHP views:

- **Map:** Leaflet.js + OpenStreetMap tiles, centered on Villavicencio (4.142, −73.6266). Key files: `mapa-reportes.js`, `admin-map.js`, `popup-reporte.js`
- **Camera:** MediaDevices API (`foto-camara.js`)
- **Admin form system:** ES6 modules in `public/views/components/admin/utils/` — `FormManager` orchestrates `ValidationManager`, `UIManager`, `ImageManager`, `CameraManager`. Loaded with `type="module"`.
- All API calls use vanilla `fetch()` with JSON

## Key Conventions

- Dates are stored as `UTCDateTime` and always rendered in `America/Bogota` timezone.
- `htmlspecialchars()` is applied when rendering user-provided content in PHP.
- All endpoints validate ObjectId format (`/^[a-f\d]{24}$/i`) before querying MongoDB.
- Error responses return JSON with HTTP 4xx/5xx codes.
