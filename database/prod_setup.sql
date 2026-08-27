-- ============================================================
--  Haras Santa María — setup de tablas para PRODUCCIÓN
--  Ejecutar en orden. Base de datos: la de prod (equivalente a `hsm` en local).
--  Generado desde el esquema real de la base local.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- 1) migrations
--    Tabla interna de Laravel: registra qué migraciones ya se
--    ejecutaron, para no volver a correrlas. No guarda datos de
--    la app. Si no existe, `php artisan migrate` cree que no se
--    corrió NADA e intenta crear tablas que ya existen (y falla).
-- ============================================================

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marcamos como YA APLICADAS las migraciones cuyas tablas creamos
-- a mano en este script, o que corresponden a tablas que ya existían
-- en prod desde antes (users, password_resets).
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2014_10_12_000000_create_users_table', 1),
  ('2014_10_12_100000_create_password_resets_table', 1),
  ('2026_04_09_132110_create_user_fcm_tokens_table', 1),
  ('2026_04_09_152540_create_user_notifications_table', 1),
  ('2026_06_18_100000_create_canchas_table', 1),
  ('2026_06_18_100100_create_turnos_table', 1),
  ('2019_12_14_000001_create_personal_access_tokens_table', 1);


-- ============================================================
-- 2) personal_access_tokens
--    Tokens de sesión de Laravel Sanctum. Es la tabla que hace
--    funcionar el login con token (Authorization: Bearer ...).
--    SIN esta tabla el login falla en producción.
-- ============================================================

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3) canchas
--    Catálogo de canchas del turnero. Va ANTES de `turnos`
--    porque turnos tiene FK contra esta tabla.
-- ============================================================

CREATE TABLE IF NOT EXISTS `canchas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('futbol','tenis') COLLATE utf8mb4_unicode_ci NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales (equivalente a CanchasSeeder): 4 de fútbol + 16 de tenis.
INSERT INTO `canchas` (`nombre`, `tipo`, `activa`, `orden`, `created_at`, `updated_at`) VALUES
  ('Cancha de Fútbol 1',  'futbol', 1, 1,  NOW(), NOW()),
  ('Cancha de Fútbol 2',  'futbol', 1, 2,  NOW(), NOW()),
  ('Cancha de Fútbol 3',  'futbol', 1, 3,  NOW(), NOW()),
  ('Cancha de Fútbol 4',  'futbol', 1, 4,  NOW(), NOW()),
  ('Cancha de Tenis 1',   'tenis',  1, 1,  NOW(), NOW()),
  ('Cancha de Tenis 2',   'tenis',  1, 2,  NOW(), NOW()),
  ('Cancha de Tenis 3',   'tenis',  1, 3,  NOW(), NOW()),
  ('Cancha de Tenis 4',   'tenis',  1, 4,  NOW(), NOW()),
  ('Cancha de Tenis 5',   'tenis',  1, 5,  NOW(), NOW()),
  ('Cancha de Tenis 6',   'tenis',  1, 6,  NOW(), NOW()),
  ('Cancha de Tenis 7',   'tenis',  1, 7,  NOW(), NOW()),
  ('Cancha de Tenis 8',   'tenis',  1, 8,  NOW(), NOW()),
  ('Cancha de Tenis 9',   'tenis',  1, 9,  NOW(), NOW()),
  ('Cancha de Tenis 10',  'tenis',  1, 10, NOW(), NOW()),
  ('Cancha de Tenis 11',  'tenis',  1, 11, NOW(), NOW()),
  ('Cancha de Tenis 12',  'tenis',  1, 12, NOW(), NOW()),
  ('Cancha de Tenis 13',  'tenis',  1, 13, NOW(), NOW()),
  ('Cancha de Tenis 14',  'tenis',  1, 14, NOW(), NOW()),
  ('Cancha de Tenis 15',  'tenis',  1, 15, NOW(), NOW()),
  ('Cancha de Tenis 16',  'tenis',  1, 16, NOW(), NOW());


-- ============================================================
-- 4) turnos
--    Reservas del turnero. Va AL FINAL: tiene claves foráneas
--    contra `canchas` y contra `users` (que ya debe existir en prod).
-- ============================================================

CREATE TABLE IF NOT EXISTS `turnos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cancha_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `nlote` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('reservado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reservado',
  `cancelado_at` timestamp NULL DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `turnos_cancelado_por_foreign` (`cancelado_por`),
  KEY `turnos_cancha_id_fecha_index` (`cancha_id`,`fecha`),
  KEY `turnos_user_id_fecha_index` (`user_id`,`fecha`),
  CONSTRAINT `turnos_cancha_id_foreign` FOREIGN KEY (`cancha_id`) REFERENCES `canchas` (`id`),
  CONSTRAINT `turnos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `turnos_cancelado_por_foreign` FOREIGN KEY (`cancelado_por`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  Verificación rápida (opcional)
-- ============================================================
-- SELECT COUNT(*) AS canchas FROM canchas;          -- esperado: 20
-- SELECT COUNT(*) AS migraciones FROM migrations;   -- esperado: 7
-- SHOW TABLES LIKE 'personal_access_tokens';
-- SHOW TABLES LIKE 'turnos';
