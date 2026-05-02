# UniLink — Guía de instalación con XAMPP
## Windows / macOS / Linux

---

## ✅ Requisitos previos

- **XAMPP** 8.2+ (con PHP 8.2, Apache, MySQL)
- **Node.js** 18+ (para el servidor WebSocket)
- **Composer** (opcional, para PHPMailer)
- XAMPP descargable en: https://www.apachefriends.org

---

## 📁 Paso 1 — Copiar el proyecto

Copia la carpeta `unilink/` dentro de `htdocs` de XAMPP:

```
Windows:  C:\xampp\htdocs\unilink\
macOS:    /Applications/XAMPP/htdocs/unilink/
Linux:    /opt/lampp/htdocs/unilink/
```

La estructura debe quedar así:
```
htdocs/
└── unilink/
    ├── index.php
    ├── frontend/
    ├── backend/
    ├── database/
    └── ...
```

---

## 🗄 Paso 2 — Crear la base de datos en MySQL

1. Abre XAMPP Control Panel → **Start** Apache y MySQL
2. Abre **phpMyAdmin**: http://localhost/phpmyadmin
3. Crea un usuario nuevo (recomendado):
   - Usuarios > Agregar usuario
   - Usuario: `unilink`
   - Contraseña: `unilink123`
   - Marca: "Crear base de datos con el mismo nombre"
4. Ejecuta las migraciones **en orden**:
   - phpMyAdmin → Selecciona la BD `red_social` → pestaña **SQL**
   - Pega y ejecuta el contenido de `database/migrations/001_schema.sql`
   - Luego `002_academic_and_extras.sql`
   - Luego `003_polls_and_indexes.sql`

**Alternativa rápida por línea de comandos:**
```bash
# Windows (desde el directorio de XAMPP)
cd C:\xampp\mysql\bin
mysql -u root -p < C:\xampp\htdocs\unilink\database\migrations\001_schema.sql
mysql -u root -p < C:\xampp\htdocs\unilink\database\migrations\002_academic_and_extras.sql
mysql -u root -p < C:\xampp\htdocs\unilink\database\migrations\003_polls_and_indexes.sql

# macOS/Linux
/opt/lampp/bin/mysql -u root -p < /opt/lampp/htdocs/unilink/database/migrations/001_schema.sql
```

---

## ⚙️ Paso 3 — Configurar las variables de entorno

Edita el archivo `backend/shared/helpers.php` y cambia estos valores:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'unilink');       // o 'root' si usas root
define('DB_PASS', 'unilink123');    // tu contraseña de MySQL
define('DB_NAME', 'red_social');
define('JWT_SECRET', 'cambia_esto_por_32_caracteres_secretos');
define('STORAGE_PATH', 'C:/xampp/htdocs/unilink/storage');  // ajusta tu ruta
define('STORAGE_URL', '/unilink/storage');
```

---

## 📂 Paso 4 — Crear carpeta de almacenamiento

Crea estas carpetas dentro del proyecto:
```
unilink/
└── storage/
    ├── posts/
    ├── avatars/
    └── marketplace/
```

Y asegúrate de que Apache tenga permisos de escritura sobre `storage/`.

---

## 🌐 Paso 5 — Configurar Apache (.htaccess)

El proyecto ya incluye un `.htaccess`. Solo verifica que Apache tenga el módulo `mod_rewrite` activo:

1. Abre XAMPP → Apache → Config → `httpd.conf`
2. Busca `#LoadModule rewrite_module` y quita el `#`
3. Busca `<Directory "C:/xampp/htdocs">` y cambia `AllowOverride None` a `AllowOverride All`
4. Guarda y **reinicia Apache**

---

## 🔌 Paso 6 — Servidor WebSocket (opcional pero recomendado)

El WebSocket es para actualizaciones en tiempo real (moderación instantánea, notificaciones live). Sin él, la app funciona igual pero sin tiempo real.

```bash
# Desde la carpeta del proyecto
cd C:\xampp\htdocs\unilink\websocket-server

# Instalar dependencias
npm install

# Iniciar el servidor
node server.js
```

Deja esta ventana abierta mientras usas la app. El servidor corre en `http://localhost:3001`.

---

## 🚀 Paso 7 — Acceder a la aplicación

Con XAMPP corriendo (Apache + MySQL):

**http://localhost/unilink/**

---

## 👤 Crear usuario admin de prueba

Ejecuta este SQL en phpMyAdmin (BD `red_social`):

```sql
INSERT INTO users (email, password_hash, first_name, last_name, student_id, faculty_id, semester, role, status, email_verified)
VALUES (
  'admin@tec.mx',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: password
  'Admin',
  'UniLink',
  'A00000001',
  1,
  1,
  'admin',
  'active',
  1
);
```

Credenciales de acceso:
- Email: `admin@tec.mx`
- Contraseña: `password`

---

## ⚠️ Problemas comunes

| Problema | Solución |
|----------|----------|
| `500 Internal Server Error` | Revisa `php_error.log` en XAMPP, probablemente un `define()` duplicado |
| `Access denied for user` | Verifica usuario/contraseña en `helpers.php` |
| Imágenes no cargan | Crea la carpeta `storage/` y verifica permisos |
| `.htaccess` no funciona | Activa `mod_rewrite` en Apache (ver Paso 5) |
| WebSocket no conecta | Ejecuta `node server.js` por separado, o ignóralo si no necesitas tiempo real |
| `session_start()` error | Verifica que no haya output antes de `<?php session_start()` |
