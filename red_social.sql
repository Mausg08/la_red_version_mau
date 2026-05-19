-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 28-04-2026 a las 19:54:07
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `red_social`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `academic_calendar`
--

CREATE TABLE `academic_calendar` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `type` enum('clase','examen','taller','conferencia','deportivo','cultural','institutional','otro') DEFAULT 'otro',
  `faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `group_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `academic_calendar`
--

INSERT INTO `academic_calendar` (`event_id`, `title`, `description`, `event_date`, `end_date`, `location`, `type`, `faculty_id`, `group_id`, `created_by`, `is_public`, `created_at`) VALUES
(1, 'Examen Parcial 1 — Cálculo', 'Temas: límites, continuidad y derivadas básicas', '2026-03-26 12:53:19', '2026-03-26 12:53:19', 'Salón A-201', 'examen', NULL, NULL, 2, 1, '2026-03-19 18:53:19'),
(2, 'Feria de Talentos Universitarios', 'Exhibición de proyectos estudiantiles', '2026-04-02 12:53:19', '2026-04-04 12:53:19', 'Plaza Central', 'cultural', NULL, NULL, 7, 1, '2026-03-19 18:53:19'),
(3, 'Taller de Innovación Tech', 'Workshop de herramientas para desarrollo de apps', '2026-03-24 12:53:19', '2026-03-24 12:53:19', 'Lab. Cómputo 3', 'taller', NULL, NULL, 2, 1, '2026-03-19 18:53:19'),
(4, 'Partido de Fútbol — Torneo Inter-facultades', 'Ingeniería vs Negocios', '2026-03-29 12:53:19', '2026-03-29 12:53:19', 'Cancha deportiva', 'deportivo', NULL, NULL, 7, 1, '2026-03-19 18:53:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `careers`
--

CREATE TABLE `careers` (
  `career_id` int(10) UNSIGNED NOT NULL,
  `faculty_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `careers`
--

INSERT INTO `careers` (`career_id`, `faculty_id`, `name`, `code`) VALUES
(1, 1, 'Ingeniería en Sistemas', 'ISC'),
(2, 1, 'Ingeniería Industrial', 'IIN'),
(3, 1, 'Ingeniería Mecatrónica', 'IMT'),
(4, 2, 'Diseño Gráfico', 'DGR'),
(5, 2, 'Diseño Industrial', 'DIN'),
(6, 3, 'Administración', 'LAE'),
(7, 3, 'Contabilidad', 'CP'),
(8, 4, 'Medicina General', 'MG');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `content` varchar(500) NOT NULL,
  `is_flagged` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `faculties`
--

CREATE TABLE `faculties` (
  `faculty_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(20) NOT NULL,
  `color` varchar(7) DEFAULT '#2557A7',
  `icon` varchar(10) DEFAULT '?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `faculties`
--

INSERT INTO `faculties` (`faculty_id`, `name`, `code`, `color`, `icon`, `created_at`) VALUES
(1, 'Ingeniería', 'ING', '#2557A7', '⚙️', '2026-03-19 18:53:18'),
(2, 'Diseño', 'DIS', '#E85D24', '🎨', '2026-03-19 18:53:18'),
(3, 'Negocios', 'NEG', '#0F6E56', '💼', '2026-03-19 18:53:18'),
(4, 'Medicina', 'MED', '#C0392B', '🩺', '2026-03-19 18:53:18'),
(5, 'Ciencias Sociales', 'SOC', '#8E44AD', '👥', '2026-03-19 18:53:18'),
(6, 'Arquitectura', 'ARQ', '#E67E22', '🏗', '2026-03-19 18:53:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `groups_table`
--

CREATE TABLE `groups_table` (
  `group_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('nrc','faculty','career','club','study','general') DEFAULT 'general',
  `nrc_code` varchar(20) DEFAULT NULL,
  `faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `career_id` int(10) UNSIGNED DEFAULT NULL,
  `icon` varchar(10) DEFAULT '?',
  `is_private` tinyint(1) DEFAULT 0,
  `member_count` int(10) UNSIGNED DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `groups_table`
--

INSERT INTO `groups_table` (`group_id`, `name`, `description`, `type`, `nrc_code`, `faculty_id`, `career_id`, `icon`, `is_private`, `member_count`, `created_by`, `created_at`) VALUES
(1, 'Cálculo Diferencial NRC-10234', 'Grupo oficial NRC 10234 – Prof. González', 'nrc', '10234', 1, NULL, '📐', 0, 45, 2, '2026-03-19 18:53:19'),
(2, 'Programación OO NRC-10567', 'Grupo NRC 10567 – Ing. en Sistemas', 'nrc', '10567', 1, NULL, '💻', 0, 38, 2, '2026-03-19 18:53:19'),
(3, 'Club de Robótica UNI', 'Construimos el futuro juntos 🤖', 'club', NULL, 1, NULL, '🤖', 0, 67, 1, '2026-03-19 18:53:19'),
(4, 'Diseño de Interfaces NRC-20891', 'Grupo NRC 20891 – Diseño Gráfico', 'nrc', '20891', 2, NULL, '🎨', 0, 29, 2, '2026-03-19 18:53:19'),
(5, 'Estudio: Examen CENEVAL', 'Preparación para examen de titulación', 'study', NULL, NULL, NULL, '📚', 0, 22, 4, '2026-03-19 18:53:19'),
(6, 'Ingeniería — General', 'Avisos y noticias de la facultad', 'faculty', NULL, 1, NULL, '⚙️', 0, 120, 1, '2026-03-19 18:53:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `group_members`
--

CREATE TABLE `group_members` (
  `group_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` enum('member','admin','moderator') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `group_members`
--

INSERT INTO `group_members` (`group_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 2, 'admin', '2026-03-19 18:53:19'),
(1, 3, 'member', '2026-03-19 18:53:19'),
(1, 4, 'member', '2026-03-19 18:53:19'),
(2, 2, 'admin', '2026-03-19 18:53:19'),
(2, 4, 'member', '2026-03-19 18:53:19'),
(3, 1, 'admin', '2026-03-19 18:53:19'),
(3, 4, 'member', '2026-03-19 18:53:19'),
(3, 5, 'member', '2026-03-19 18:53:19'),
(6, 1, 'admin', '2026-03-19 18:53:19'),
(6, 3, 'member', '2026-03-19 18:53:19'),
(6, 4, 'member', '2026-03-19 18:53:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `group_posts`
--

CREATE TABLE `group_posts` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `group_post_links`
--

CREATE TABLE `group_post_links` (
  `group_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `listings`
--

CREATE TABLE `listings` (
  `listing_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` enum('libros','calculadoras','tutorias','electronica','ropa','otros') DEFAULT 'otros',
  `condition_val` enum('nuevo','como_nuevo','buen_estado','usado') DEFAULT 'buen_estado',
  `status` enum('active','sold','reserved','removed') DEFAULT 'active',
  `thumbnail` varchar(512) DEFAULT NULL,
  `views` int(10) UNSIGNED DEFAULT 0,
  `faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `is_lost_found` tinyint(1) DEFAULT 0,
  `lost_type` enum('lost','found') DEFAULT NULL,
  `lost_location` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `listings`
--

INSERT INTO `listings` (`listing_id`, `seller_id`, `title`, `description`, `price`, `category`, `condition_val`, `status`, `thumbnail`, `views`, `faculty_id`, `is_lost_found`, `lost_type`, `lost_location`, `created_at`, `updated_at`) VALUES
(1, 4, 'Cálculo Diferencial – James Stewart 8va ed.', 'Sin subrayados, perfecto estado. Incluye CD de recursos.', 350.00, 'libros', 'como_nuevo', 'active', NULL, 0, NULL, 0, NULL, NULL, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(2, 4, 'Casio fx-991EX Classwiz', 'Calculadora científica completa. Con manual y estuche original.', 600.00, 'calculadoras', 'buen_estado', 'active', NULL, 0, NULL, 0, NULL, NULL, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(3, 5, 'Tutoría de Diseño Gráfico (Adobe)', 'Enseño Illustrator y Photoshop. 1h = $150 MXN. Online o presencial.', 150.00, 'tutorias', 'nuevo', 'active', NULL, 0, NULL, 0, NULL, NULL, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(4, 6, 'Laptop Dell Inspiron 15 i5', 'Usada 1 año, buen estado. 8GB RAM, 256 SSD. Negociable.', 8500.00, 'electronica', 'buen_estado', 'active', NULL, 0, NULL, 0, NULL, NULL, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(5, 4, 'Calculadora perdida – área de cafetería', 'Encontré una calculadora Casio en la cafetería. Favor de reclamar.', 0.00, 'otros', 'buen_estado', 'active', NULL, 0, NULL, 1, 'found', 'Cafetería central', '2026-03-19 18:53:19', '2026-03-19 18:53:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `listing_images`
--

CREATE TABLE `listing_images` (
  `image_id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(512) NOT NULL,
  `sort_order` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `moderation_log`
--

CREATE TABLE `moderation_log` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `moderator_id` int(10) UNSIGNED DEFAULT NULL,
  `action` enum('remove_post','remove_comment','suspend_user','warn_user','dismiss_report','restore_post') NOT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `target_type` enum('post','comment','user','listing') NOT NULL,
  `reason` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('like','comment','follow','mention','group_invite','marketplace','moderation','system','event') NOT NULL,
  `message` varchar(300) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `sender_id`, `type`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 4, 2, 'system', '¡Bienvenida a UniLink! Empieza explorando tu feed.', NULL, 0, '2026-03-19 18:53:19'),
(2, 4, 5, 'like', 'Luis Ramírez le dio me gusta a tu publicación.', NULL, 0, '2026-03-19 18:53:19'),
(3, 4, 2, 'comment', 'Prof. González comentó en tu publicación.', NULL, 1, '2026-03-19 18:53:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `panic_alerts`
--

CREATE TABLE `panic_alerts` (
  `alert_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `location_text` varchar(200) DEFAULT NULL,
  `status` enum('active','acknowledged','resolved') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `polls`
--

CREATE TABLE `polls` (
  `poll_id` int(10) UNSIGNED NOT NULL,
  `creator_id` int(10) UNSIGNED NOT NULL,
  `faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `group_id` int(10) UNSIGNED DEFAULT NULL,
  `audience` enum('public','faculty','group') DEFAULT 'public',
  `title` varchar(300) NOT NULL,
  `description` text DEFAULT NULL,
  `poll_type` enum('options','rating','yesno') DEFAULT 'options',
  `category` enum('general','cafeteria','laboratorio','transporte','biblioteca','academico') DEFAULT 'general',
  `status` enum('active','closed','draft') DEFAULT 'active',
  `closes_at` timestamp NULL DEFAULT NULL,
  `total_votes` int(10) UNSIGNED DEFAULT 0,
  `avg_rating` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `polls`
--

INSERT INTO `polls` (`poll_id`, `creator_id`, `faculty_id`, `title`, `description`, `poll_type`, `category`, `status`, `closes_at`, `total_votes`, `avg_rating`, `created_at`) VALUES
(1, 7, NULL, '¿Cómo calificarías el servicio de la cafetería?', NULL, 'rating', 'cafeteria', 'active', NULL, 89, 3.40, '2026-03-19 18:53:19'),
(2, 7, NULL, '¿Cómo calificarías los laboratorios de cómputo?', NULL, 'rating', 'laboratorio', 'active', NULL, 67, 4.10, '2026-03-19 18:53:19'),
(3, 7, NULL, '¿El transporte universitario llega puntual?', NULL, 'yesno', 'transporte', 'active', NULL, 143, NULL, '2026-03-19 18:53:19'),
(4, 7, NULL, '¿Qué servicio debería mejorar primero?', NULL, 'options', 'general', 'active', NULL, 201, NULL, '2026-03-19 18:53:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `poll_options`
--

CREATE TABLE `poll_options` (
  `option_id` int(10) UNSIGNED NOT NULL,
  `poll_id` int(10) UNSIGNED NOT NULL,
  `text` varchar(200) NOT NULL,
  `votes` int(10) UNSIGNED DEFAULT 0,
  `sort_order` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `poll_options`
--

INSERT INTO `poll_options` (`option_id`, `poll_id`, `text`, `votes`, `sort_order`) VALUES
(1, 3, 'Sí', 98, 0),
(2, 3, 'No', 45, 1),
(3, 4, 'Cafetería', 72, 0),
(4, 4, 'Laboratorios', 55, 1),
(5, 4, 'Transporte', 43, 2),
(6, 4, 'Wi-Fi / Internet', 31, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `poll_votes`
--

CREATE TABLE `poll_votes` (
  `poll_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `option_id` int(10) UNSIGNED DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `posts`
--

CREATE TABLE `posts` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `content` text NOT NULL,
  `audience` enum('public','faculty','career','group') DEFAULT 'public',
  `type` enum('general','academic','event','poll','lost_found','aviso') DEFAULT 'general',
  `event_date` datetime DEFAULT NULL,
  `event_location` varchar(200) DEFAULT NULL,
  `status` enum('published','flagged','removed','draft') DEFAULT 'published',
  `likes_count` int(10) UNSIGNED DEFAULT 0,
  `comments_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `posts`
--

INSERT INTO `posts` (`post_id`, `user_id`, `faculty_id`, `content`, `audience`, `type`, `event_date`, `event_location`, `status`, `likes_count`, `comments_count`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '📢 Recordatorio: el examen parcial de Cálculo Diferencial será el próximo viernes a las 9am en el Salón A-201. Estudien los temas de límites y derivadas. ¡Suerte a todos!', 'public', 'aviso', NULL, NULL, 'published', 12, 3, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(2, 4, 1, '¿Alguien tiene el libro de Fundamentos de Programación de Deitel? Lo necesito para el semestre, prefiero prestado o comprar barato 🙏', 'public', 'general', NULL, NULL, 'published', 5, 2, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(3, 5, 2, '¡El Club de Diseño abre convocatoria para el concurso de logotipos universitarios! 🎨 Premio: $2,000 MXN. Bases en la oficina de Diseño.', 'public', 'event', NULL, NULL, 'published', 24, 7, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(4, 7, NULL, '🎉 Esta semana: Feria de Talentos Universitarios. Miércoles 15 al viernes 17, plaza central del campus. ¡No faltes!', 'public', 'event', NULL, NULL, 'published', 41, 5, '2026-03-19 18:53:19', '2026-03-19 18:53:19'),
(5, 6, 3, 'Busco compañeros para grupo de estudio de Administración Financiera. Nos reunimos martes y jueves 4pm en la biblioteca. DM si te interesa 📚', 'public', 'general', NULL, NULL, 'published', 8, 4, '2026-03-19 18:53:19', '2026-03-19 18:53:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `post_likes`
--

CREATE TABLE `post_likes` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `post_media`
--

CREATE TABLE `post_media` (
  `media_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(512) NOT NULL,
  `media_type` enum('image','video') DEFAULT 'image',
  `sort_order` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `post_tags`
--

CREATE TABLE `post_tags` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `tag` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `post_tags`
--

INSERT INTO `post_tags` (`post_id`, `tag`) VALUES
(1, 'academia'),
(1, 'aviso'),
(2, 'marketplace'),
(3, 'cultura'),
(3, 'eventos'),
(4, 'eventos'),
(5, 'academia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reports`
--

CREATE TABLE `reports` (
  `report_id` int(10) UNSIGNED NOT NULL,
  `reporter_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED DEFAULT NULL,
  `comment_id` int(10) UNSIGNED DEFAULT NULL,
  `listing_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` enum('spam','harassment','hate_speech','false_info','inappropriate','other') NOT NULL,
  `details` text DEFAULT NULL,
  `faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
  `moderator_id` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `reviewer_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `faculty_id` int(10) UNSIGNED DEFAULT NULL,
  `career_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` tinyint(3) UNSIGNED DEFAULT 1,
  `role` enum('student','professor','admin','moderator','staff') DEFAULT 'student',
  `status` enum('active','inactive','suspended','pending_verification') DEFAULT 'active',
  `bio` varchar(300) DEFAULT NULL,
  `avatar` varchar(512) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `show_phone` tinyint(1) DEFAULT 0,
  `reputation_score` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `first_name`, `last_name`, `student_id`, `faculty_id`, `career_id`, `semester`, `role`, `status`, `bio`, `avatar`, `phone`, `show_phone`, `reputation_score`, `last_login`, `email_verified`, `created_at`, `updated_at`) VALUES
(1, 'admin@correo.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Admin', 'UniLink', 'A00000001', 1, 1, 9, 'admin', 'active', NULL, NULL, NULL, 0, 0, NULL, 1, '2026-03-19 18:53:18', '2026-04-12 05:19:46'),
(2, 'profe@correo.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Carlos', 'González', NULL, 1, 1, NULL, 'professor', 'active', NULL, NULL, NULL, 0, 0, NULL, 1, '2026-03-19 18:53:18', '2026-04-12 05:19:46'),
(3, 'mod@correo.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'María', 'López', 'A00111111', 1, 1, 6, 'moderator', 'active', NULL, NULL, NULL, 0, 0, NULL, 1, '2026-03-19 18:53:18', '2026-04-12 05:19:46'),
(4, 'ana@alumno.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Ana', 'Martínez', 'A00222222', 1, 1, 4, 'student', 'active', NULL, NULL, NULL, 0, 0, '2026-04-14 21:43:45', 1, '2026-03-19 18:53:18', '2026-04-14 21:43:45'),
(5, 'luis@alumno.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Luis', 'Ramírez', 'A00333333', 2, 4, 3, 'student', 'active', NULL, NULL, NULL, 0, 0, NULL, 1, '2026-03-19 18:53:18', '2026-04-12 05:19:46'),
(6, 'sofia@alumno.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Sofía', 'Torres', 'A00444444', 3, 6, 2, 'student', 'active', NULL, NULL, NULL, 0, 0, NULL, 1, '2026-03-19 18:53:18', '2026-04-12 05:19:46'),
(7, 'eventos@correo.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Eventos', 'Campus', NULL, NULL, NULL, NULL, 'staff', 'active', NULL, NULL, NULL, 0, 0, NULL, 1, '2026-03-19 18:53:18', '2026-04-12 05:19:46'),
(10, 'juan.gutierrezhe@alumno.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Juan', 'Gutierrez', '222', 1, NULL, 7, 'student', 'active', NULL, NULL, NULL, 0, 0, '2026-04-12 05:20:46', 1, '2026-03-24 06:15:56', '2026-04-12 05:20:46'),
(11, 'juangutierrez1403147@alumno.buap.mx', '$2y$10$uuNM37uikvIi/pKEZICh6exHrrpN8HW1tCN4DkpXDiMf6Y1ocfpeG', 'Mona', 'Gut', '123', 2, NULL, 4, 'student', 'active', NULL, NULL, NULL, 0, 0, '2026-04-10 22:24:45', 1, '2026-04-10 22:24:34', '2026-04-12 05:19:46'),
(16, 'pepe@alumno.buap.mx', '$2y$12$vXfpoZDiC0xv.RmF3DHeceQ0PDOvDGqHK7jdGLTb5dTkIbUhed2c2', 'pepe', 'gonzales', '222697', 1, NULL, 7, 'student', 'active', NULL, NULL, NULL, 0, 0, '2026-04-14 19:27:06', 1, '2026-04-14 19:26:35', '2026-04-14 19:27:06'),
(18, 'mau@correo.buap.mx', '$2y$12$94xaJkF0vfMEz5XQvR0XkuIuw4ul1ZpIVmTaUuTTujXR1pNlJTs3y', 'mau', 'gon', '123456789', 2, NULL, NULL, 'professor', 'active', NULL, NULL, NULL, 0, 0, '2026-04-14 21:17:51', 1, '2026-04-14 21:17:39', '2026-04-14 21:17:51'),
(19, 'Karla@alumno.buap.mx', '$2y$12$CPs/e876lLmD1MME.bDVvOfaRqJ8E7rEGbfvCAQLeZkrw00cdSZXO', 'karla', 'perez', '2596', 4, NULL, 5, 'student', 'active', NULL, NULL, NULL, 0, 0, '2026-04-27 21:15:47', 1, '2026-04-27 19:27:35', '2026-04-27 21:15:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_follows`
--

CREATE TABLE `user_follows` (
  `follower_id` int(10) UNSIGNED NOT NULL,
  `following_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_posts_feed`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_posts_feed` (
`post_id` int(10) unsigned
,`content` text
,`audience` enum('public','faculty','career','group')
,`type` enum('general','academic','event','poll','lost_found','aviso')
,`status` enum('published','flagged','removed','draft')
,`likes_count` int(10) unsigned
,`comments_count` int(10) unsigned
,`event_date` datetime
,`event_location` varchar(200)
,`created_at` timestamp
,`author_id` int(10) unsigned
,`author_name` varchar(161)
,`author_avatar` varchar(512)
,`author_role` enum('student','professor','admin','moderator','staff')
,`faculty_name` varchar(120)
,`faculty_id` int(10) unsigned
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_posts_feed`
--
DROP TABLE IF EXISTS `v_posts_feed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_posts_feed`  AS SELECT `p`.`post_id` AS `post_id`, `p`.`content` AS `content`, `p`.`audience` AS `audience`, `p`.`type` AS `type`, `p`.`status` AS `status`, `p`.`likes_count` AS `likes_count`, `p`.`comments_count` AS `comments_count`, `p`.`event_date` AS `event_date`, `p`.`event_location` AS `event_location`, `p`.`created_at` AS `created_at`, `u`.`user_id` AS `author_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `author_name`, `u`.`avatar` AS `author_avatar`, `u`.`role` AS `author_role`, `f`.`name` AS `faculty_name`, `f`.`faculty_id` AS `faculty_id` FROM ((`posts` `p` join `users` `u` on(`p`.`user_id` = `u`.`user_id`)) left join `faculties` `f` on(`p`.`faculty_id` = `f`.`faculty_id`)) WHERE `p`.`status` = 'published' ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `academic_calendar`
--
ALTER TABLE `academic_calendar`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`event_date`),
  ADD KEY `idx_faculty` (`faculty_id`);

--
-- Indices de la tabla `careers`
--
ALTER TABLE `careers`
  ADD PRIMARY KEY (`career_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indices de la tabla `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_post` (`post_id`,`created_at`);

--
-- Indices de la tabla `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indices de la tabla `groups_table`
--
ALTER TABLE `groups_table`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `nrc_code` (`nrc_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_nrc` (`nrc_code`),
  ADD KEY `idx_faculty` (`faculty_id`);

--
-- Indices de la tabla `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `group_posts`
--
ALTER TABLE `group_posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `idx_group_created` (`group_id`,`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `group_post_links`
--
ALTER TABLE `group_post_links`
  ADD PRIMARY KEY (`group_id`,`post_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indices de la tabla `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);
ALTER TABLE `listings` ADD FULLTEXT KEY `idx_search` (`title`,`description`);

--
-- Indices de la tabla `listing_images`
--
ALTER TABLE `listing_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indices de la tabla `moderation_log`
--
ALTER TABLE `moderation_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `moderator_id` (`moderator_id`);

--
-- Indices de la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`,`created_at`);

--
-- Indices de la tabla `panic_alerts`
--
ALTER TABLE `panic_alerts`
  ADD PRIMARY KEY (`alert_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `polls`
--
ALTER TABLE `polls`
  ADD PRIMARY KEY (`poll_id`),
  ADD KEY `creator_id` (`creator_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);

--
-- Indices de la tabla `poll_options`
--
ALTER TABLE `poll_options`
  ADD PRIMARY KEY (`option_id`),
  ADD KEY `poll_id` (`poll_id`);

--
-- Indices de la tabla `poll_votes`
--
ALTER TABLE `poll_votes`
  ADD PRIMARY KEY (`poll_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `option_id` (`option_id`);

--
-- Indices de la tabla `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_faculty` (`faculty_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);
ALTER TABLE `posts` ADD FULLTEXT KEY `idx_content_ft` (`content`);

--
-- Indices de la tabla `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `post_media`
--
ALTER TABLE `post_media`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indices de la tabla `post_tags`
--
ALTER TABLE `post_tags`
  ADD PRIMARY KEY (`post_id`,`tag`);

--
-- Indices de la tabla `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `moderator_id` (`moderator_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indices de la tabla `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_review` (`listing_id`,`reviewer_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `career_id` (`career_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_faculty` (`faculty_id`),
  ADD KEY `idx_role_status` (`role`,`status`);

--
-- Indices de la tabla `user_follows`
--
ALTER TABLE `user_follows`
  ADD PRIMARY KEY (`follower_id`,`following_id`),
  ADD KEY `idx_following` (`following_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `academic_calendar`
--
ALTER TABLE `academic_calendar`
  MODIFY `event_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `careers`
--
ALTER TABLE `careers`
  MODIFY `career_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `faculties`
--
ALTER TABLE `faculties`
  MODIFY `faculty_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `groups_table`
--
ALTER TABLE `groups_table`
  MODIFY `group_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `group_posts`
--
ALTER TABLE `group_posts`
  MODIFY `post_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `listings`
--
ALTER TABLE `listings`
  MODIFY `listing_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `listing_images`
--
ALTER TABLE `listing_images`
  MODIFY `image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `moderation_log`
--
ALTER TABLE `moderation_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `panic_alerts`
--
ALTER TABLE `panic_alerts`
  MODIFY `alert_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `polls`
--
ALTER TABLE `polls`
  MODIFY `poll_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `poll_options`
--
ALTER TABLE `poll_options`
  MODIFY `option_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `post_media`
--
ALTER TABLE `post_media`
  MODIFY `media_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `academic_calendar`
--
ALTER TABLE `academic_calendar`
  ADD CONSTRAINT `academic_calendar_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `academic_calendar_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `careers`
--
ALTER TABLE `careers`
  ADD CONSTRAINT `careers_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `groups_table`
--
ALTER TABLE `groups_table`
  ADD CONSTRAINT `groups_table_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `groups_table_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `group_posts`
--
ALTER TABLE `group_posts`
  ADD CONSTRAINT `group_posts_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `group_post_links`
--
ALTER TABLE `group_post_links`
  ADD CONSTRAINT `group_post_links_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_post_links_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `listings`
--
ALTER TABLE `listings`
  ADD CONSTRAINT `listings_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `listings_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `listing_images`
--
ALTER TABLE `listing_images`
  ADD CONSTRAINT `listing_images_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`listing_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `moderation_log`
--
ALTER TABLE `moderation_log`
  ADD CONSTRAINT `moderation_log_ibfk_1` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `panic_alerts`
--
ALTER TABLE `panic_alerts`
  ADD CONSTRAINT `panic_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `polls`
--
ALTER TABLE `polls`
  ADD CONSTRAINT `polls_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `polls_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`group_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `poll_options`
--
ALTER TABLE `poll_options`
  ADD CONSTRAINT `poll_options_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`poll_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `poll_votes`
--
ALTER TABLE `poll_votes`
  ADD CONSTRAINT `poll_votes_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`poll_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poll_votes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poll_votes_ibfk_3` FOREIGN KEY (`option_id`) REFERENCES `poll_options` (`option_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `post_media`
--
ALTER TABLE `post_media`
  ADD CONSTRAINT `post_media_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `post_tags`
--
ALTER TABLE `post_tags`
  ADD CONSTRAINT `post_tags_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`listing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`career_id`) REFERENCES `careers` (`career_id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `user_follows`
--
ALTER TABLE `user_follows`
  ADD CONSTRAINT `user_follows_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_follows_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
