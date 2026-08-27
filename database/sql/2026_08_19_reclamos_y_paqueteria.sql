-- =====================================================================
--  Haras Santa María — tablas nuevas para producción
--  Generado: 2026-08-19  ·  Origen: schema real de la base local `hsm`
--
--  Contiene los módulos de RECLAMOS/CHATBOT y PAQUETERÍA (7 tablas).
--  Las tablas anteriores (users, gastoscomunes, turnos, canchas,
--  user_fcm_tokens, user_notifications, etc.) NO se tocan.
--
--  ANTES DE EJECUTAR
--  -----------------
--  1) Hacé backup de la base. Este script crea tablas y agrega filas;
--     no borra ni modifica nada existente, pero un backup igual.
--  2) Verificá que `users.id` sea int(11) en producción. Todas las FK de
--     abajo apuntan ahí; si el tipo no coincide, MySQL rechaza la FK.
--     ->  SHOW COLUMNS FROM users LIKE 'id';
--  3) Los CREATE TABLE van sin IF NOT EXISTS a propósito: si alguna ya
--     existe, querés que falle y enterarte, no que pase de largo.
--
--  El orden importa: chat_conversaciones referencia a reclamos, y
--  paquete_eventos/paquete_entregas referencian a paquetes.
-- =====================================================================

SET NAMES utf8mb4;

-- =====================================================================
--  MÓDULO RECLAMOS / CHATBOT
-- =====================================================================

CREATE TABLE `reclamo_ruteos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `categoria` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reclamo_ruteos_categoria_unique` (`categoria`),
  KEY `reclamo_ruteos_user_id_foreign` (`user_id`),
  CONSTRAINT `reclamo_ruteos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `reclamos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nlote` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` enum('mantenimiento','seguridad','alumbrado','agua_cloacas','espacios_verdes','obras','convivencia','administracion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `urgencia` enum('emergencia','alta','normal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `ubicacion_tipo` enum('lote','espacio_comun') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubicacion_detalle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('nuevo','tomado','resuelto','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nuevo',
  `derivado_a` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derivado_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tomado_por` int(11) DEFAULT NULL,
  `tomado_at` timestamp NULL DEFAULT NULL,
  `resuelto_at` timestamp NULL DEFAULT NULL,
  `nota_cierre` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reclamos_tomado_por_foreign` (`tomado_por`),
  KEY `reclamos_estado_categoria_index` (`estado`,`categoria`),
  KEY `reclamos_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `reclamos_tomado_por_foreign` FOREIGN KEY (`tomado_por`) REFERENCES `users` (`id`),
  CONSTRAINT `reclamos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `chat_conversaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `estado` enum('activa','cerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `mensajes` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reclamo_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_conversaciones_reclamo_id_foreign` (`reclamo_id`),
  KEY `chat_conversaciones_user_id_estado_index` (`user_id`,`estado`),
  CONSTRAINT `chat_conversaciones_reclamo_id_foreign` FOREIGN KEY (`reclamo_id`) REFERENCES `reclamos` (`id`),
  CONSTRAINT `chat_conversaciones_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Catálogo de ruteo de reclamos.
--
--  Esto NO son datos de uso: son las 8 categorías que la propia migración
--  crea al correr, igual que las crearía un `artisan migrate` limpio. Sin
--  ellas el módulo de reclamos no tiene a dónde derivar nada.
--
--  Los emails van en NULL (estado nuevo). Mientras estén así, cada reclamo
--  cae al RECLAMOS_EMAIL_FALLBACK del .env. Se completan sin tocar código:
--     UPDATE reclamo_ruteos SET email='jardineria@...' WHERE categoria='espacios_verdes';
-- ---------------------------------------------------------------------
INSERT INTO `reclamo_ruteos` (`categoria`, `nombre`, `email`, `user_id`, `activo`, `created_at`, `updated_at`) VALUES
('mantenimiento',   'Mantenimiento',     NULL, NULL, 1, NOW(), NOW()),
('seguridad',       'Seguridad',         NULL, NULL, 1, NOW(), NOW()),
('alumbrado',       'Alumbrado público', NULL, NULL, 1, NOW(), NOW()),
('agua_cloacas',    'Agua y cloacas',    NULL, NULL, 1, NOW(), NOW()),
('espacios_verdes', 'Espacios verdes',   NULL, NULL, 1, NOW(), NOW()),
('obras',           'Comité de obras',   NULL, NULL, 1, NOW(), NOW()),
('convivencia',     'Convivencia',       NULL, NULL, 1, NOW(), NOW()),
('administracion',  'Administración',    NULL, NULL, 1, NOW(), NOW());


-- =====================================================================
--  MÓDULO PAQUETERÍA
-- =====================================================================

CREATE TABLE `paqueteria_operarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paqueteria_operarios_user_id_unique` (`user_id`),
  CONSTRAINT `paqueteria_operarios_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `paquetes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin_intentos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `pin_bloqueado_at` timestamp NULL DEFAULT NULL,
  `nlote` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email_destino` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` enum('mercadolibre','andreani','oca','correo_argentino','urbano','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'otro',
  `tracking` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('sobre','caja_chica','caja_grande','bulto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'caja_chica',
  `ubicacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('recibido','retirado','devuelto','vencido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recibido',
  `recibido_por` int(11) NOT NULL,
  `recibido_at` timestamp NULL DEFAULT NULL,
  `notificado_at` timestamp NULL DEFAULT NULL,
  `recordatorio_at` timestamp NULL DEFAULT NULL,
  `retirado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paquetes_codigo_unique` (`codigo`),
  KEY `paquetes_recibido_por_foreign` (`recibido_por`),
  KEY `paquetes_estado_nlote_index` (`estado`,`nlote`),
  KEY `paquetes_user_id_estado_index` (`user_id`,`estado`),
  KEY `paquetes_tracking_index` (`tracking`),
  CONSTRAINT `paquetes_recibido_por_foreign` FOREIGN KEY (`recibido_por`) REFERENCES `users` (`id`),
  CONSTRAINT `paquetes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `paquete_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `paquete_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash_anterior` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paquete_eventos_hash_unique` (`hash`),
  KEY `paquete_eventos_user_id_foreign` (`user_id`),
  KEY `paquete_eventos_paquete_id_id_index` (`paquete_id`,`id`),
  KEY `paquete_eventos_tipo_index` (`tipo`),
  CONSTRAINT `paquete_eventos_paquete_id_foreign` FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`),
  CONSTRAINT `paquete_eventos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `paquete_entregas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `paquete_id` bigint(20) unsigned NOT NULL,
  `folio` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metodo` enum('pin','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo_manual` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retirado_por` enum('titular','autorizado','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operario_id` int(11) NOT NULL,
  `entregado_at` timestamp NULL DEFAULT NULL,
  `ack_estado` enum('pendiente','confirmado','desconocido','tacito') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `ack_at` timestamp NULL DEFAULT NULL,
  `ack_user_id` int(11) DEFAULT NULL,
  `ack_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paquete_entregas_paquete_id_unique` (`paquete_id`),
  UNIQUE KEY `paquete_entregas_folio_unique` (`folio`),
  KEY `paquete_entregas_operario_id_foreign` (`operario_id`),
  KEY `paquete_entregas_ack_user_id_foreign` (`ack_user_id`),
  KEY `paquete_entregas_ack_estado_index` (`ack_estado`),
  CONSTRAINT `paquete_entregas_ack_user_id_foreign` FOREIGN KEY (`ack_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `paquete_entregas_operario_id_foreign` FOREIGN KEY (`operario_id`) REFERENCES `users` (`id`),
  CONSTRAINT `paquete_entregas_paquete_id_foreign` FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  REGISTRO EN LA TABLA `migrations`
--
--  Sin esto, el próximo `php artisan migrate` en producción va a intentar
--  crear estas 7 tablas de nuevo y va a explotar porque ya existen.
--  Estas filas le dicen a Laravel que las migraciones ya corrieron.
-- =====================================================================

SET @batch := (SELECT IFNULL(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS m);

INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_08_17_120000_create_reclamo_ruteos_table',       @batch),
('2026_08_17_120100_create_reclamos_table',             @batch),
('2026_08_17_120200_create_chat_conversaciones_table',  @batch),
('2026_08_19_100000_create_paqueteria_operarios_table', @batch),
('2026_08_19_100100_create_paquetes_table',             @batch),
('2026_08_19_100200_create_paquete_eventos_table',      @batch),
('2026_08_19_100300_create_paquete_entregas_table',     @batch);


-- =====================================================================
--  VERIFICACIÓN
-- =====================================================================
-- Deberían dar 7 y 7:
--   SELECT COUNT(*) FROM information_schema.tables
--    WHERE table_schema = DATABASE()
--      AND table_name IN ('reclamo_ruteos','reclamos','chat_conversaciones',
--                         'paqueteria_operarios','paquetes','paquete_eventos',
--                         'paquete_entregas');
--   SELECT COUNT(*) FROM reclamo_ruteos;


-- =====================================================================
--  QUÉ FALTA DESPUÉS DE CORRER ESTE SCRIPT
-- =====================================================================
--
--  1) Subir el .env de producción ya actualizado (.env.prod.txt), que
--     trae PAQUETERIA_HASH_KEY y el resto de las variables nuevas.
--     Esa clave no se cambia más una vez que haya entregas registradas:
--     rotarla invalida la verificación de todo el historial.
--
--  1b) Completar los emails de reclamo_ruteos, que quedan en NULL:
--        UPDATE reclamo_ruteos SET email='...' WHERE categoria='seguridad';
--     Mientras estén vacíos, todo cae al RECLAMOS_EMAIL_FALLBACK del .env.
--
--  2) Cargar los operarios de la oficina (sin esto sólo los admin ven la
--     sección de paquetería):
--        INSERT INTO paqueteria_operarios (user_id, activo, created_at, updated_at)
--        VALUES ((SELECT id FROM users WHERE email='porteria@...'), 1, NOW(), NOW());
--
--  3) Permisos de escritura en storage/app/paqueteria/firmas/
--     (disco privado: las firmas NO van en storage/app/public).
--
--  4) Cron de Laravel corriendo, para el cierre de acuses vencidos y la
--     auditoría diaria de la cadena:
--        * * * * * cd /ruta/api && php artisan schedule:run >> /dev/null 2>&1
--
--  5) Corregir en gastoscomunes los 3 lotes que no resuelven a un usuario:
--        - lotes 100 y 185: email vacío
--        - lote 1: 'flo.romaniello@gmail.com1233' (sufijo pegado)
--     Sus paquetes se registran igual, pero sólo se entregan por método
--     manual porque no hay a quién mandarle el PIN.
--
-- =====================================================================
