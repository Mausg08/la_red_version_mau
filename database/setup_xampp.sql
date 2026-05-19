-- ============================================================
-- UniLink — setup_xampp.sql
-- Base de datos ÚNICA para XAMPP (más sencillo que multi-DB)
-- Ejecuta este archivo completo en phpMyAdmin
-- ============================================================

-- Crear y seleccionar la base de datos
CREATE DATABASE IF NOT EXISTS red_social
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE red_social;

-- ================================================================
--  FACULTADES Y CARRERAS
-- ================================================================
CREATE TABLE IF NOT EXISTS faculties (
  faculty_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  code         VARCHAR(20)  NOT NULL UNIQUE,
  color        VARCHAR(7)   DEFAULT '#2557A7',
  icon         VARCHAR(10)  DEFAULT '🏛',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO faculties (faculty_id, name, code, color, icon) VALUES
  (1, 'Ingeniería',        'ING', '#2557A7', '⚙️'),
  (2, 'Diseño',            'DIS', '#E85D24', '🎨'),
  (3, 'Negocios',          'NEG', '#0F6E56', '💼'),
  (4, 'Medicina',          'MED', '#C0392B', '🩺'),
  (5, 'Ciencias Sociales', 'SOC', '#8E44AD', '👥'),
  (6, 'Arquitectura',      'ARQ', '#E67E22', '🏗');

CREATE TABLE IF NOT EXISTS careers (
  career_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  faculty_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(150) NOT NULL,
  code        VARCHAR(20)  NOT NULL,
  FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO careers (faculty_id, name, code) VALUES
  (1, 'Ingeniería en Sistemas', 'ISC'),
  (1, 'Ingeniería Industrial',  'IIN'),
  (1, 'Ingeniería Mecatrónica', 'IMT'),
  (2, 'Diseño Gráfico',         'DGR'),
  (2, 'Diseño Industrial',      'DIN'),
  (3, 'Administración',         'LAE'),
  (3, 'Contabilidad',           'CP'),
  (4, 'Medicina General',       'MG');

-- ================================================================
--  USUARIOS
-- ================================================================
CREATE TABLE IF NOT EXISTS users (
  user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(255) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  first_name      VARCHAR(80)  NOT NULL,
  last_name       VARCHAR(80)  NOT NULL,
  student_id      VARCHAR(20)  UNIQUE,
  faculty_id      INT UNSIGNED,
  career_id       INT UNSIGNED,
  semester        TINYINT UNSIGNED DEFAULT 1,
  role            ENUM('student','professor','admin','moderator','staff') DEFAULT 'student',
  status          ENUM('active','inactive','suspended','pending_verification') DEFAULT 'active',
  bio             VARCHAR(300),
  avatar          VARCHAR(512),
  phone           VARCHAR(20),
  show_phone      BOOLEAN DEFAULT FALSE,
  reputation_score INT DEFAULT 0,
  last_login      TIMESTAMP NULL,
  email_verified  BOOLEAN DEFAULT TRUE,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
  FOREIGN KEY (career_id)  REFERENCES careers(career_id)   ON DELETE SET NULL,
  INDEX idx_email      (email),
  INDEX idx_student_id (student_id),
  INDEX idx_faculty    (faculty_id),
  INDEX idx_role_status(role, status)
) ENGINE=InnoDB;

-- ── Usuarios de prueba ──────────────────────────────────────
-- Contraseña para todos: password
INSERT IGNORE INTO users (user_id, email, password_hash, first_name, last_name, student_id, faculty_id, career_id, semester, role, status) VALUES
  (1, 'admin@tec.mx',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin',    'UniLink',  'A00000001', 1, 1, 9,  'admin',     'active'),
  (2, 'profe@tec.mx',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos',   'González', NULL,        1, 1, NULL,'professor', 'active'),
  (3, 'mod@tec.mx',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María',    'López',    'A00111111', 1, 1, 6,  'moderator', 'active'),
  (4, 'ana@tec.mx',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana',      'Martínez', 'A00222222', 1, 1, 4,  'student',   'active'),
  (5, 'luis@tec.mx',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Luis',     'Ramírez',  'A00333333', 2, 4, 3,  'student',   'active'),
  (6, 'sofia@uanl.mx',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sofía',    'Torres',   'A00444444', 3, 6, 2,  'student',   'active'),
  (7, 'eventos@tec.mx',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Eventos',  'Campus',   NULL,        NULL,NULL,NULL,'staff',   'active');

-- ================================================================
--  POSTS / FEED
-- ================================================================
CREATE TABLE IF NOT EXISTS posts (
  post_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  faculty_id     INT UNSIGNED,
  content        TEXT NOT NULL,
  audience       ENUM('public','faculty','career','group') DEFAULT 'public',
  type           ENUM('general','academic','event','poll','lost_found','aviso') DEFAULT 'general',
  event_date     DATETIME NULL,
  event_location VARCHAR(200) NULL,
  status         ENUM('published','flagged','removed','draft') DEFAULT 'published',
  likes_count    INT UNSIGNED DEFAULT 0,
  comments_count INT UNSIGNED DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(user_id)     ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
  INDEX idx_user    (user_id),
  INDEX idx_faculty (faculty_id),
  INDEX idx_status  (status),
  INDEX idx_created (created_at DESC),
  FULLTEXT idx_content_ft (content)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_tags (
  post_id INT UNSIGNED NOT NULL,
  tag     VARCHAR(50)  NOT NULL,
  PRIMARY KEY (post_id, tag),
  FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_media (
  media_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id    INT UNSIGNED NOT NULL,
  url        VARCHAR(512) NOT NULL,
  media_type ENUM('image','video') DEFAULT 'image',
  sort_order TINYINT DEFAULT 0,
  FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_likes (
  post_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, user_id),
  FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
  comment_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id     INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  content     VARCHAR(500) NOT NULL,
  is_flagged  BOOLEAN DEFAULT FALSE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id)  ON DELETE CASCADE,
  INDEX idx_post (post_id, created_at)
) ENGINE=InnoDB;

-- ── Posts de prueba ──────────────────────────────────────────
INSERT IGNORE INTO posts (post_id, user_id, faculty_id, content, type, status, likes_count, comments_count) VALUES
  (1, 2, 1, '📢 Recordatorio: el examen parcial de Cálculo Diferencial será el próximo viernes a las 9am en el Salón A-201. Estudien los temas de límites y derivadas. ¡Suerte a todos!', 'aviso', 'published', 12, 3),
  (2, 4, 1, '¿Alguien tiene el libro de Fundamentos de Programación de Deitel? Lo necesito para el semestre, prefiero prestado o comprar barato 🙏', 'general', 'published', 5, 2),
  (3, 5, 2, '¡El Club de Diseño abre convocatoria para el concurso de logotipos universitarios! 🎨 Premio: $2,000 MXN. Bases en la oficina de Diseño.', 'event', 'published', 24, 7),
  (4, 7, NULL, '🎉 Esta semana: Feria de Talentos Universitarios. Miércoles 15 al viernes 17, plaza central del campus. ¡No faltes!', 'event', 'published', 41, 5),
  (5, 6, 3, 'Busco compañeros para grupo de estudio de Administración Financiera. Nos reunimos martes y jueves 4pm en la biblioteca. DM si te interesa 📚', 'general', 'published', 8, 4);

INSERT IGNORE INTO post_tags (post_id, tag) VALUES
  (1, 'academia'), (1, 'aviso'), (2, 'marketplace'), (3, 'eventos'), (3, 'cultura'), (4, 'eventos'), (5, 'academia');

-- ================================================================
--  GRUPOS
-- ================================================================
CREATE TABLE IF NOT EXISTS groups_table (
  group_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(200) NOT NULL,
  description  TEXT,
  type         ENUM('nrc','faculty','career','club','study','general') DEFAULT 'general',
  nrc_code     VARCHAR(20) UNIQUE,
  faculty_id   INT UNSIGNED,
  career_id    INT UNSIGNED,
  icon         VARCHAR(10) DEFAULT '👥',
  is_private   BOOLEAN DEFAULT FALSE,
  member_count INT UNSIGNED DEFAULT 0,
  created_by   INT UNSIGNED,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_nrc (nrc_code), INDEX idx_faculty (faculty_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_members (
  group_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  role       ENUM('member','admin','moderator') DEFAULT 'member',
  joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id),
  FOREIGN KEY (group_id) REFERENCES groups_table(group_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(user_id)          ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_posts (
  post_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  content    TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (group_id) REFERENCES groups_table(group_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(user_id)         ON DELETE CASCADE,
  INDEX idx_group_created (group_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_post_links (
  group_id INT UNSIGNED NOT NULL,
  post_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (group_id, post_id),
  FOREIGN KEY (group_id) REFERENCES groups_table(group_id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)  REFERENCES posts(post_id)         ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Grupos de prueba ─────────────────────────────────────────
INSERT IGNORE INTO groups_table (group_id, name, description, type, nrc_code, faculty_id, icon, member_count, created_by) VALUES
  (1, 'Cálculo Diferencial NRC-10234',     'Grupo oficial NRC 10234 – Prof. González', 'nrc',   '10234', 1, '📐', 45, 2),
  (2, 'Programación OO NRC-10567',         'Grupo NRC 10567 – Ing. en Sistemas',       'nrc',   '10567', 1, '💻', 38, 2),
  (3, 'Club de Robótica UNI',              'Construimos el futuro juntos 🤖',           'club',  NULL,    1, '🤖', 67, 1),
  (4, 'Diseño de Interfaces NRC-20891',    'Grupo NRC 20891 – Diseño Gráfico',         'nrc',   '20891', 2, '🎨', 29, 2),
  (5, 'Estudio: Examen CENEVAL',           'Preparación para examen de titulación',    'study', NULL,    NULL,'📚', 22, 4),
  (6, 'Ingeniería — General',              'Avisos y noticias de la facultad',          'faculty',NULL,   1, '⚙️', 120, 1);

INSERT IGNORE INTO group_members (group_id, user_id, role) VALUES
  (1, 2, 'admin'), (1, 4, 'member'), (1, 3, 'member'),
  (2, 2, 'admin'), (2, 4, 'member'),
  (3, 1, 'admin'), (3, 4, 'member'), (3, 5, 'member'),
  (6, 1, 'admin'), (6, 4, 'member'), (6, 3, 'member');

-- ================================================================
--  MARKETPLACE
-- ================================================================
CREATE TABLE IF NOT EXISTS listings (
  listing_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT,
  price         DECIMAL(10,2) NOT NULL DEFAULT 0,
  category      ENUM('libros','calculadoras','tutorias','electronica','ropa','otros') DEFAULT 'otros',
  condition_val ENUM('nuevo','como_nuevo','buen_estado','usado') DEFAULT 'buen_estado',
  status        ENUM('active','sold','reserved','removed') DEFAULT 'active',
  thumbnail     VARCHAR(512),
  views         INT UNSIGNED DEFAULT 0,
  faculty_id    INT UNSIGNED,
  is_lost_found BOOLEAN DEFAULT FALSE,
  lost_type     ENUM('lost','found') NULL,
  lost_location VARCHAR(200) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (seller_id)  REFERENCES users(user_id)        ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
  INDEX idx_seller(seller_id), INDEX idx_status(status), INDEX idx_category(category),
  FULLTEXT idx_search (title, description)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS listing_images (
  image_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  url        VARCHAR(512) NOT NULL,
  sort_order TINYINT DEFAULT 0,
  FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviews (
  review_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  seller_id   INT UNSIGNED NOT NULL,
  rating      TINYINT NOT NULL,
  comment     VARCHAR(500),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_review (listing_id, reviewer_id),
  FOREIGN KEY (listing_id)  REFERENCES listings(listing_id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(user_id)       ON DELETE CASCADE,
  FOREIGN KEY (seller_id)   REFERENCES users(user_id)       ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Listings de prueba ───────────────────────────────────────
INSERT IGNORE INTO listings (listing_id, seller_id, title, description, price, category, condition_val, status) VALUES
  (1, 4, 'Cálculo Diferencial – James Stewart 8va ed.', 'Sin subrayados, perfecto estado. Incluye CD de recursos.', 350.00, 'libros', 'como_nuevo', 'active'),
  (2, 4, 'Casio fx-991EX Classwiz', 'Calculadora científica completa. Con manual y estuche original.', 600.00, 'calculadoras', 'buen_estado', 'active'),
  (3, 5, 'Tutoría de Diseño Gráfico (Adobe)', 'Enseño Illustrator y Photoshop. 1h = $150 MXN. Online o presencial.', 150.00, 'tutorias', 'nuevo', 'active'),
  (4, 6, 'Laptop Dell Inspiron 15 i5', 'Usada 1 año, buen estado. 8GB RAM, 256 SSD. Negociable.', 8500.00, 'electronica', 'buen_estado', 'active'),
  (5, 4, 'Calculadora perdida – área de cafetería', 'Encontré una calculadora Casio en la cafetería. Favor de reclamar.', 0, 'otros', 'buen_estado', 'active');

UPDATE listings SET is_lost_found = TRUE, lost_type = 'found', lost_location = 'Cafetería central' WHERE listing_id = 5;

-- ================================================================
--  CALENDARIO
-- ================================================================
CREATE TABLE IF NOT EXISTS academic_calendar (
  event_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  description  TEXT,
  event_date   DATETIME NOT NULL,
  end_date     DATETIME,
  location     VARCHAR(200),
  type         ENUM('clase','examen','taller','conferencia','deportivo','cultural','institutional','otro') DEFAULT 'otro',
  faculty_id   INT UNSIGNED,
  group_id     INT UNSIGNED,
  created_by   INT UNSIGNED NOT NULL,
  is_public    BOOLEAN DEFAULT TRUE,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (faculty_id) REFERENCES faculties(faculty_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_date (event_date), INDEX idx_faculty (faculty_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO academic_calendar (title, description, event_date, end_date, location, type, created_by, is_public) VALUES
  ('Examen Parcial 1 — Cálculo', 'Temas: límites, continuidad y derivadas básicas', DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY), 'Salón A-201', 'examen', 2, TRUE),
  ('Feria de Talentos Universitarios', 'Exhibición de proyectos estudiantiles', DATE_ADD(NOW(), INTERVAL 14 DAY), DATE_ADD(NOW(), INTERVAL 16 DAY), 'Plaza Central', 'cultural', 7, TRUE),
  ('Taller de Innovación Tech', 'Workshop de herramientas para desarrollo de apps', DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), 'Lab. Cómputo 3', 'taller', 2, TRUE),
  ('Partido de Fútbol — Torneo Inter-facultades', 'Ingeniería vs Negocios', DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 10 DAY), 'Cancha deportiva', 'deportivo', 7, TRUE);

-- ================================================================
--  NOTIFICACIONES
-- ================================================================
CREATE TABLE IF NOT EXISTS notifications (
  notif_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  sender_id   INT UNSIGNED,
  type        ENUM('like','comment','follow','mention','group_invite','marketplace','moderation','system','event') NOT NULL,
  message     VARCHAR(300) NOT NULL,
  link        VARCHAR(255),
  is_read     BOOLEAN DEFAULT FALSE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)   REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_user_read (user_id, is_read, created_at DESC)
) ENGINE=InnoDB;

INSERT IGNORE INTO notifications (user_id, sender_id, type, message, is_read) VALUES
  (4, 2, 'system',  '¡Bienvenida a UniLink! Empieza explorando tu feed.', FALSE),
  (4, 5, 'like',    'Luis Ramírez le dio me gusta a tu publicación.', FALSE),
  (4, 2, 'comment', 'Prof. González comentó en tu publicación.', TRUE);

-- ================================================================
--  MODERACIÓN
-- ================================================================
CREATE TABLE IF NOT EXISTS reports (
  report_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id  INT UNSIGNED NOT NULL,
  post_id      INT UNSIGNED,
  comment_id   INT UNSIGNED,
  listing_id   INT UNSIGNED,
  reason       ENUM('spam','harassment','hate_speech','false_info','inappropriate','other') NOT NULL,
  details      TEXT,
  faculty_id   INT UNSIGNED,
  status       ENUM('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
  moderator_id INT UNSIGNED,
  reviewed_at  TIMESTAMP NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reporter_id)  REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)      REFERENCES posts(post_id) ON DELETE SET NULL,
  FOREIGN KEY (moderator_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_log (
  log_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  moderator_id INT UNSIGNED,
  action       ENUM('remove_post','remove_comment','suspend_user','warn_user','dismiss_report','restore_post') NOT NULL,
  target_id    INT UNSIGNED,
  target_type  ENUM('post','comment','user','listing') NOT NULL,
  reason       VARCHAR(300),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (moderator_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS panic_alerts (
  alert_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  latitude   DECIMAL(10,7),
  longitude  DECIMAL(10,7),
  location_text VARCHAR(200),
  status     ENUM('active','acknowledged','resolved') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================================================================
--  ENCUESTAS
-- ================================================================
CREATE TABLE IF NOT EXISTS polls (
  poll_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creator_id  INT UNSIGNED NOT NULL,
  faculty_id  INT UNSIGNED,
  group_id    INT UNSIGNED,
  audience    ENUM('public','faculty','group') DEFAULT 'public',
  title       VARCHAR(300) NOT NULL,
  description TEXT,
  poll_type   ENUM('options','rating','yesno') DEFAULT 'options',
  category    ENUM('general','cafeteria','laboratorio','transporte','biblioteca','academico') DEFAULT 'general',
  status      ENUM('active','closed','draft') DEFAULT 'active',
  closes_at   TIMESTAMP NULL,
  total_votes INT UNSIGNED DEFAULT 0,
  avg_rating  DECIMAL(3,2),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES groups_table(group_id) ON DELETE CASCADE,
  INDEX idx_status(status), INDEX idx_category(category)
) ENGINE=InnoDB;

ALTER TABLE polls
  ADD COLUMN IF NOT EXISTS group_id INT UNSIGNED NULL AFTER faculty_id,
  ADD COLUMN IF NOT EXISTS audience ENUM('public','faculty','group') DEFAULT 'public' AFTER group_id;

CREATE TABLE IF NOT EXISTS poll_options (
  option_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id    INT UNSIGNED NOT NULL,
  text       VARCHAR(200) NOT NULL,
  votes      INT UNSIGNED DEFAULT 0,
  sort_order TINYINT DEFAULT 0,
  FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS poll_votes (
  poll_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  option_id  INT UNSIGNED,
  rating     TINYINT,
  voted_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (poll_id, user_id),
  FOREIGN KEY (poll_id)   REFERENCES polls(poll_id)         ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(user_id)          ON DELETE CASCADE,
  FOREIGN KEY (option_id) REFERENCES poll_options(option_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO polls (poll_id, creator_id, title, poll_type, category, status, total_votes, avg_rating) VALUES
  (1, 7, '¿Cómo calificarías el servicio de la cafetería?',  'rating',  'cafeteria',   'active', 89, 3.40),
  (2, 7, '¿Cómo calificarías los laboratorios de cómputo?',  'rating',  'laboratorio', 'active', 67, 4.10),
  (3, 7, '¿El transporte universitario llega puntual?',       'yesno',   'transporte',  'active', 143, NULL),
  (4, 7, '¿Qué servicio debería mejorar primero?',            'options', 'general',     'active', 201, NULL);

INSERT IGNORE INTO poll_options (poll_id, text, votes, sort_order) VALUES
  (3, 'Sí', 98, 0), (3, 'No', 45, 1),
  (4, 'Cafetería', 72, 0), (4, 'Laboratorios', 55, 1),
  (4, 'Transporte', 43, 2), (4, 'Wi-Fi / Internet', 31, 3);

-- ================================================================
--  FOLLOWS
-- ================================================================
CREATE TABLE IF NOT EXISTS user_follows (
  follower_id  INT UNSIGNED NOT NULL,
  following_id INT UNSIGNED NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  FOREIGN KEY (follower_id)  REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_following (following_id)
) ENGINE=InnoDB;

-- ================================================================
--  TOKENS DE RESET
-- ================================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at    TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================================================================
--  VISTA útil para el feed
-- ================================================================
CREATE OR REPLACE VIEW v_posts_feed AS
SELECT
  p.post_id, p.content, p.audience, p.type,
  p.status, p.likes_count, p.comments_count,
  p.event_date, p.event_location, p.created_at,
  u.user_id   AS author_id,
  CONCAT(u.first_name, ' ', u.last_name) AS author_name,
  u.avatar    AS author_avatar,
  u.role      AS author_role,
  f.name      AS faculty_name,
  f.faculty_id
FROM posts p
JOIN users u        ON p.user_id    = u.user_id
LEFT JOIN faculties f ON p.faculty_id = f.faculty_id
WHERE p.status = 'published';

-- ¡Listo! Base de datos configurada con datos de prueba.
-- Accede a: http://localhost/unilink/
-- Admin: admin@tec.mx / password
