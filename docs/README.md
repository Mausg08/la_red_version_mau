# UniLink — Red Social Universitaria
### Arquitectura de Microservicios · PHP · HTML · CSS · JavaScript

---

## 📁 Estructura del Proyecto

```
unilink/
├── index.php                        ← Landing / Login
├── frontend/
│   ├── css/
│   │   ├── main.css                 ← Sistema de diseño global
│   │   ├── auth.css                 ← Página de login/registro
│   │   ├── dashboard.css            ← Layout y sidebar
│   │   ├── marketplace.css          ← Marketplace
│   │   └── moderation.css           ← Panel de moderación
│   ├── js/
│   │   ├── utils.js                 ← apiFetch, showToast, helpers
│   │   ├── auth.js                  ← Login/registro
│   │   ├── feed.js                  ← Feed principal + creación de posts
│   │   ├── marketplace.js           ← Marketplace + reseñas
│   │   ├── moderation.js            ← Panel de moderación
│   │   ├── notifications.js         ← Notificaciones
│   │   └── websocket.js             ← WebSockets tiempo real
│   └── pages/
│       ├── feed.php                 ← Feed principal
│       ├── marketplace.php          ← Marketplace
│       └── moderation.php           ← Panel moderación
├── backend/
│   ├── api-gateway/
│   │   └── index.php                ← Gateway único · JWT · CORS · Rate limit
│   ├── shared/
│   │   ├── helpers.php              ← Utilidades comunes
│   │   ├── jwt.php                  ← JWT + Response + RateLimiter
│   │   └── auth_check.php           ← Verificación de sesión PHP
│   └── microservices/
│       ├── users/auth_controller.php      ← Login, registro, LDAP
│       ├── feed/feed_controller.php       ← Posts, likes, comentarios
│       ├── marketplace/marketplace_controller.php ← Listings, reseñas
│       ├── academic/                      ← Grupos NRC, calendario
│       └── moderation/                    ← Reportes, pánico, acciones
├── database/
│   └── migrations/001_schema.sql    ← Esquema completo
└── docker/
    └── docker-compose.yml           ← Todos los contenedores
```

---

## 🚀 Instalación Rápida

### Requisitos
- Docker + Docker Compose
- PHP 8.2+ (si corres sin Docker)
- MySQL 8.0 / PostgreSQL 15
- Node.js 20+ (para WebSocket server)

### Con Docker (recomendado)

```bash
# 1. Clonar e ingresar al proyecto
cd unilink

# 2. Copiar variables de entorno
cp .env.example .env
# Editar .env con tus valores

# 3. Levantar todos los microservicios
cd docker
docker-compose up -d

# 4. Ejecutar migraciones
docker exec -i red_social_feed mysql -u unilink -psecret red_social < database/migrations/001_schema.sql

# 5. Abrir en navegador
open http://localhost
```

### Sin Docker (desarrollo local)

```bash
# Iniciar servidor PHP
php -S localhost:8080

# Iniciar WebSocket server
cd websocket-server && npm install && node server.js

# Configurar DB
mysql -u root -p < database/migrations/001_schema.sql
```

---

## 🏗 Arquitectura de Microservicios

```
[Dashboard] → [API Gateway] → [Microservicio]
                ↕ JWT           ↕ DB propia
            Rate Limiting    ↕ Cola mensajes
                CORS        ↕ Redis
```

### Microservicios

| Servicio | Puerto DB | Tecnología | Descripción |
|----------|-----------|------------|-------------|
| **users** | PostgreSQL 5432 | PHP | Auth, roles, directorio |
| **feed** | MySQL 3307 | PHP | Posts, likes, comentarios |
| **marketplace** | MySQL 3308 | PHP | Listings, reseñas |
| **academic** | PostgreSQL 5433 | PHP | Grupos NRC, calendario |
| **moderation** | MongoDB 27017 | PHP | Reportes, logs |
| **websocket** | Redis | Node.js | Tiempo real |

### Regla de Oro
> **Un microservicio no puede leer la BD de otro directamente.**
> Solo se comunican a través de sus APIs.

---

## 👥 Roles y Permisos

| Rol | Permisos |
|-----|----------|
| **student** | Publicar, comprar/vender, reportar |
| **professor** | + Avisos académicos críticos, gestionar grupos NRC |
| **moderator** | + Dashboard moderación, eliminar contenido, gestionar reportes de su facultad |
| **staff** | + Publicar eventos institucionales |
| **admin** | Control total: roles, usuarios, configuración global |

---

## 🔐 Seguridad

- **JWT** (HS256) con expiración de 7 días
- **Rate limiting**: 100 req/min por IP (Redis o file-based)
- **CORS** restringido a dominios universitarios
- **LDAP/Directorio**: verificación automática de matrícula activa
- **Revocación**: al dar de baja a un alumno, se corta acceso
- **XSS**: `htmlspecialchars()` en todo output PHP
- **SQL Injection**: PDO prepared statements
- **Directorio**: teléfono protegido (solo visible si el usuario lo permite)

---

## ⚡ WebSockets (Tiempo Real)

Eventos manejados via Socket.io:

| Evento | Descripción |
|--------|-------------|
| `post_removed` | Publicación moderada → desaparece sin recargar |
| `notification` | Nueva notificación en tiempo real |
| `new_post` | Post nuevo en el feed |
| `reports_update` | Contador de reportes para moderadores |
| `like_update` | Sincronización de likes entre usuarios |
| `system_alert` | Alertas de sistema (mantenimiento, etc.) |

---

## 📊 Base de Datos

### Tablas principales
- `users` — Usuarios con roles y estado
- `faculties` + `careers` — Estructura académica
- `posts` + `post_tags` + `post_media` — Feed
- `post_likes` + `comments` — Interacciones
- `groups_table` + `group_members` — Grupos NRC
- `listings` + `listing_images` + `reviews` — Marketplace
- `reports` + `moderation_log` — Moderación
- `panic_alerts` — Botón de pánico
- `notifications` — Sistema de notificaciones

### Índices clave
- `FULLTEXT` en `posts.content` para búsqueda
- `FULLTEXT` en `listings.title, description`
- Índices en `faculty_id` para segmentación
- Índice compuesto en `(user_id, is_read, created_at)` para notificaciones

---

## 🛠 Variables de Entorno (.env)

```env
APP_ENV=production
JWT_SECRET=tu_clave_secreta_de_32_caracteres_aqui
DB_HOST=localhost
DB_USER=unilink
DB_PASS=tu_password_seguro
DB_ROOT_PASSWORD=root_password
REDIS_PASSWORD=redis_password
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=unilink@tuuni.edu.mx
SMTP_PASS=tu_app_password
MINIO_USER=unilink
MINIO_PASS=minio_password123
```

---

## 📋 API Endpoints Principales

### Auth (`/api-gateway?service=auth`)
- `POST auth/login` — Iniciar sesión
- `POST auth/register` — Crear cuenta
- `POST auth/logout` — Cerrar sesión

### Feed (`?service=feed`)
- `GET  feed/posts` — Obtener feed (paginado 10x)
- `POST feed/posts` — Crear publicación
- `DELETE feed/posts/{id}` — Eliminar publicación
- `POST feed/posts/{id}/like` — Like
- `GET/POST feed/posts/{id}/comments` — Comentarios

### Marketplace (`?service=marketplace`)
- `GET  marketplace/listings` — Listar anuncios
- `POST marketplace/listings` — Nuevo anuncio
- `GET  marketplace/listings/{id}` — Detalle
- `POST marketplace/reviews` — Reseña

### Moderación (`?service=moderation`)
- `GET  moderation/reports` — Ver reportes
- `POST moderation/reports` — Reportar contenido
- `POST moderation/actions` — Ejecutar acción
- `POST moderation/panic` — Activar botón de pánico
- `GET  moderation/stats` — Estadísticas

---

## 🐳 Contenedores Docker

Cada microservicio corre en su propio contenedor. Se pueden encender/apagar independientemente:

```bash
# Apagar solo el marketplace sin afectar los demás
docker stop red_social_marketplace

# Escalar el servidor web
docker-compose up -d --scale web=3

# Ver logs de un servicio
docker logs unilink_web -f
```

---

*Desarrollado para la comunidad universitaria mexicana.*
*UniLink — Tu universidad, conectada.*
