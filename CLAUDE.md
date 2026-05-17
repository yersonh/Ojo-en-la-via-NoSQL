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
- `public/views/usuario/inicio.php` - Citizen home, map view, report creation
- `public/views/usuario/alertas.php` - Reports list with comments and embedded report likes
- `public/views/usuario/perfil.php` - User profile and notification history
- `public/views/admin/panel.php` - Admin dashboard, stats, map, report management

### Database

- **Connection:** `config/conexion.php` returns a MongoDB `\MongoDB\Database` instance.
- All document IDs are MongoDB `ObjectId`; validate with `/^[a-f\d]{24}$/i` before querying.

**Collection schemas:**

`usuario`: `_id`, `nombre_completo`, `telefono`, `email`, `password` (bcrypt), `estado` (bool), `fecha_creacion` (string), `foto_perfil` (string), `rol` ("ciudadano"|"admin")

`Reportes`: `_id`, `usuario_id` (ObjectId), `usuario_creador_id` (legacy alias), `estado` ("pendiente"|"en_revision"|"notificado"|"resuelto"), `fecha_reporte` (UTCDateTime), `fecha_estado` (UTCDateTime), `tipo`/`tipo_incidente`, `descripcion`, `ubicacion`, `latitud`, `longitud`, `direccion_texto`, `imagenes` (array), `likes` (array of `{usuario_id, fecha_like}`), `historial_estados` (array)

`comentarios_reporte`: `_id`, `reporte_id`, `usuario_id`, `comentario` (max 500 chars), `comentario_padre_id` (null = root, ObjectId = reply), `fecha_comentario` (UTCDateTime), `eliminado` (bool), `editado` (bool)

`likes_comentario`: `_id`, `comentario_id`, `usuario_id`, `fecha_like` (UTCDateTime)

`notificaciones`: `_id`, `usuario_destino_id`, `usuario_origen_id`, `tipo` ("comentario"|"respuesta_comentario"|"like_reporte"|"like_comentario"|"estado_reporte"), `titulo`, `mensaje`, `reporte_id`, `comentario_id` (nullable), `leida` (bool), `fecha` (UTCDateTime)

`tipo_incidente`: `_id`, `nombre` (string) - reference collection, no CRUD endpoints

**ID type inconsistency:** older documents may store `usuario_id` as plain string instead of `ObjectId`. Queries on owner fields should use `$or` to match both types.

### API Endpoints

All API files are PHP scripts returning JSON with appropriate HTTP status codes. Use `reportes/controladores/` as the active user report API:

| Action | Path |
|--------|------|
| Edit/Delete report | `public/views/usuario/reportes/controladores/` |
| Comment CRUD + likes | `public/views/usuario/reportes/controladores/` |
| Admin: update status | `public/views/admin/actualizar_estado_reporte.php` |

### Authentication & Roles

PHP sessions. Relevant session keys: `$_SESSION['usuario_id']` (string), `$_SESSION['usuario_rol']` ("ciudadano"|"admin"). Two roles: `ciudadano`, `admin`.

### Notifications System

`notificaciones_helper.php` inserts notification documents when comments, likes, or status changes occur. Admin panel uses Server-Sent Events (SSE) with exponential backoff reconnection logic (`admin-notificaciones.js`).

### File Uploads

Images go to `public/uploads/reportes/`. Validation checks extension (jpg/jpeg/png/webp), MIME type, and size before saving with a randomized filename.

### Frontend

No build step or framework. JavaScript files are loaded directly in PHP views:

- Map: Leaflet.js + OpenStreetMap tiles (`mapa-reportes.js`, `admin-map.js`)
- Camera: MediaDevices API (`foto-camara.js`)
- All API calls use vanilla `fetch()` with JSON

## Key Conventions

- Dates are formatted in `America/Bogota` timezone.
- `htmlspecialchars()` is applied when rendering user-provided content in PHP.
- Error responses return JSON with HTTP 4xx/5xx codes.
- Admin JS utilities are modular classes in `public/views/components/admin/utils/`.
