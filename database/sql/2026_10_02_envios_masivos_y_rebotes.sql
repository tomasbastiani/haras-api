-- =====================================================================
--  Haras Santa María — envíos masivos encolados + emails rebotados
--  Generado: 2026-10-02
--
--  Equivale a estas 2 migraciones:
--    2026_10_02_110000_add_email_rebotado_at_to_users
--    2026_10_02_110100_create_envios_masivos_tables
--
--  1) `users.email_rebotado_at`: marca de "este email rebota". La pone el
--     comando `mail:sincronizar-rebotes` (scheduler, todos los días a las
--     04:00) leyendo la lista de suprimidas de Postmark. Los envíos masivos
--     saltean esas direcciones.
--  2) `envios_masivos` + `envios_masivos_destinatarios`: el aviso de gastos
--     comunes y el mail personalizado ya no se mandan dentro del request; se
--     encolan acá y los manda `envios:procesar` (scheduler, cada minuto) de
--     a tandas. Evita los cortes por timeout y los envíos duplicados.
--
--  ANTES DE EJECUTAR
--  -----------------
--  1) Backup de la base.
--  2) El ALTER de users es aditivo (columna NULL): no toca ninguna fila.
--  3) Verificá que nada de esto exista ya:
--     ->  SHOW COLUMNS FROM users LIKE 'email_rebotado_at';
--     ->  SHOW TABLES LIKE 'envios_masivos%';
--  4) El orden NO se puede cambiar: destinatarios tiene FK a envios_masivos.
--
--  `users.id` es int(11) SIGNED (tabla legacy), así que `creado_por` es
--  int(11) y no bigint unsigned: si no coinciden, MySQL rechaza la FK con
--  errno 150.
--
--  ORDEN DE DESPLIEGUE
--  -------------------
--  Primero este script, DESPUÉS el backend nuevo, y por último la PWA.
--  El backend nuevo usa estas tablas en "Enviar aviso por mail" y en el
--  mail personalizado.
--
--  DESPUÉS DE DESPLEGAR
--  --------------------
--  No hace falta correr nada a mano: a las 04:00 el scheduler marca los
--  rebotados, y cada minuto procesa los envíos encolados. Ambos dependen
--  del cron de `schedule:run`, el mismo que ya usa mensajeria:notificar.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
  ADD COLUMN `email_rebotado_at` timestamp NULL DEFAULT NULL AFTER `email`;

CREATE TABLE `envios_masivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `periodo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asunto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuerpo` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `total` int(10) unsigned NOT NULL DEFAULT 0,
  `finalizado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `envios_masivos_clave_unique` (`clave`),
  KEY `envios_masivos_creado_por_foreign` (`creado_por`),
  CONSTRAINT `envios_masivos_creado_por_foreign` FOREIGN KEY (`creado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `envios_masivos_destinatarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `envio_masivo_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `detalle` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intentos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `enviado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `envios_masivos_destinatarios_envio_masivo_id_email_unique` (`envio_masivo_id`,`email`),
  KEY `envios_masivos_destinatarios_estado_index` (`estado`),
  CONSTRAINT `envios_masivos_destinatarios_envio_masivo_id_foreign` FOREIGN KEY (`envio_masivo_id`) REFERENCES `envios_masivos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Registro en la tabla `migrations`: sin esto, el próximo
--  `php artisan migrate` en producción va a intentar correr estas
--  migraciones de nuevo y va a fallar porque ya existen.
-- ---------------------------------------------------------------------
SET @batch := (SELECT IFNULL(MAX(batch), 0) + 1 FROM migrations);

INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_10_02_110000_add_email_rebotado_at_to_users', @batch),
('2026_10_02_110100_create_envios_masivos_tables', @batch);
