-- =============================================================================
--  Mensajería interna (chat privado del personal) — script de despliegue
-- =============================================================================
--
--  Equivale a correr estas 6 migraciones:
--
--    2026_09_27_100000_create_mensajeria_miembros_table
--    2026_09_27_100100_create_mensajeria_canales_table
--    2026_09_27_100200_create_mensajeria_mensajes_table
--    2026_09_27_100300_create_mensajeria_canal_miembros_table
--    2026_09_27_100400_create_mensajeria_adjuntos_table
--    2026_09_27_100500_add_notificado_at_to_mensajeria_mensajes
--
--  Está pensado para pegar en phpMyAdmin (o en el cliente SQL del hosting) sobre
--  la base de producción. Funciona igual en MySQL 5.7+/8.0 y en MariaDB 10.x.
--
--  ── Cómo leerlo ─────────────────────────────────────────────────────────────
--
--  * Las 6 migraciones se resuelven con 5 CREATE TABLE, no 6 sentencias: la
--    sexta migración agrega la columna `notificado_at` a una tabla que en
--    producción todavía no existe, así que acá ya viene creada con la columna
--    puesta. Se evita un ALTER innecesario, y `ADD COLUMN IF NOT EXISTS` no es
--    portable (existe en MariaDB pero no en MySQL).
--
--  * El orden NO se puede cambiar: cada tabla tiene claves foráneas a las
--    anteriores.
--
--  * `users.id` es int(11) SIGNED en esta base (tabla legacy), así que todas las
--    FK hacia users usan `int(11)` y no el `bigint unsigned` que pondría Laravel
--    por defecto. Si el tipo no coincide, MySQL rechaza la FK con el error 1005
--    / errno 150.
--
--  * utf8mb4 en todas las columnas de texto, explícito: hace falta para que los
--    emojis de los mensajes no rompan el INSERT.
--
--  * El bloque final marca las 6 migraciones como ejecutadas en la tabla
--    `migrations`. NO lo saltees: sin eso, el próximo `php artisan migrate` va a
--    intentar crear estas tablas de nuevo y va a fallar.
--
--  ── Después de correr esto ──────────────────────────────────────────────────
--
--    1. Subir el código (api/ y el build de pwa/).
--    2. Agregar las variables MENSAJERIA_* al .env (todas tienen default, así
--       que el módulo arranca sin ninguna; ver api/.env.example).
--    3. Crear la carpeta de adjuntos y darle permiso de escritura:
--         storage/app/mensajeria/adjuntos
--    4. Verificar que el scheduler de Laravel esté corriendo (el cron que ya
--       existe para paquetería): de ahí sale `mensajeria:notificar`, que manda
--       las push cada minuto.
--    5. Habilitar al primer empleado desde el panel:
--         Administración → Acceso a Mensajería
--       Un admin ya entra sin estar habilitado, pero sólo a supervisar grupos.
--
-- =============================================================================

SET NAMES utf8mb4;


-- -----------------------------------------------------------------------------
-- 1) mensajeria_miembros — quién tiene acceso al módulo + ficha del empleado
-- -----------------------------------------------------------------------------
-- Permiso aditivo sobre una cuenta existente (igual que paqueteria_operarios):
-- al empleado que además es propietario no le cambia nada del resto de la app.
--
-- La clave es user_id y NO el email: en esta base los emails mutan, y además
-- users.email no tiene índice único (hay cientos de emails con varias filas).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajeria_miembros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `puesto` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` enum('miembro','moderador') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'miembro',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultima_actividad` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mensajeria_miembros_user_id_unique` (`user_id`),
  KEY `mensajeria_miembros_activo_user_id_index` (`activo`,`user_id`),
  CONSTRAINT `mensajeria_miembros_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 2) mensajeria_canales — conversaciones: directos (1 a 1) y grupos
-- -----------------------------------------------------------------------------
-- Las dos clases van en la misma tabla porque todo lo que viene después
-- (mensajes, cursor de lectura, no leídos, adjuntos, push) es idéntico.
--
-- clave_directo es el truco que hace imposible el directo duplicado: guarda
-- "menorUserId-mayorUserId" con índice único, así el par (A,B) y el (B,A)
-- producen la misma clave y no pueden coexistir dos hilos con la mitad de los
-- mensajes en cada uno. Va NULL en los grupos (MySQL permite varios NULL en un
-- UNIQUE, así que no colisionan entre sí).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajeria_canales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('directo','grupo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_directo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `archivado` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_mensaje_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mensajeria_canales_clave_directo_unique` (`clave_directo`),
  KEY `mensajeria_canales_creado_por_foreign` (`creado_por`),
  KEY `mensajeria_canales_tipo_archivado_index` (`tipo`,`archivado`),
  KEY `mensajeria_canales_ultimo_mensaje_at_index` (`ultimo_mensaje_at`),
  CONSTRAINT `mensajeria_canales_creado_por_foreign`
    FOREIGN KEY (`creado_por`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 3) mensajeria_mensajes — los mensajes
-- -----------------------------------------------------------------------------
-- OJO: esto NO es una bitácora sellada como paquete_eventos. Acá los mensajes se
-- pueden editar y borrar (es una conversación de trabajo). El borrado deja lápida
-- en `eliminado_at` y vacía `cuerpo`, para poder mostrar "mensaje eliminado" sin
-- que el texto siga en la base.
--
-- `notificado_at` ya viene incluida: es la migración ...100500, y evita que el
-- comando de push vuelva a notificar cada minuto lo mismo.
--
-- El índice (canal_id, id) es el que sostiene todo: el hilo paginado hacia atrás
-- y el barrido del endpoint /sync.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajeria_mensajes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `canal_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `tipo` enum('texto','archivo','sistema') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'texto',
  `cuerpo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `responde_a_id` bigint(20) unsigned DEFAULT NULL,
  `editado_at` timestamp NULL DEFAULT NULL,
  `eliminado_at` timestamp NULL DEFAULT NULL,
  `notificado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mensajeria_mensajes_user_id_foreign` (`user_id`),
  KEY `mensajeria_mensajes_canal_id_id_index` (`canal_id`,`id`),
  KEY `mensajeria_mensajes_notificado_at_id_index` (`notificado_at`,`id`),
  CONSTRAINT `mensajeria_mensajes_canal_id_foreign`
    FOREIGN KEY (`canal_id`) REFERENCES `mensajeria_canales` (`id`),
  CONSTRAINT `mensajeria_mensajes_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 4) mensajeria_canal_miembros — quién participa de cada canal, y hasta dónde leyó
-- -----------------------------------------------------------------------------
-- Tener fila acá es lo ÚNICO que da acceso a los mensajes de un canal. La sola
-- excepción es el admin de la app sobre los GRUPOS, y es de sólo lectura; a los
-- chats directos no llega ni siendo admin.
--
-- ultimo_leido_mensaje_id es un CURSOR, no un contador: los no leídos se cuentan
-- con `id > cursor`. Un contador que se incrementa y decrementa se desincroniza
-- siempre (dos pestañas abiertas, un request que falla) y deja un badge rojo que
-- no se va nunca.
--
-- A propósito SIN clave foránea: es una marca de posición, no una referencia. Con
-- FK, borrar el mensaje justo apuntado fallaría o dejaría el cursor en NULL, o
-- sea todo sin leer de nuevo.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajeria_canal_miembros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `canal_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `rol` enum('owner','miembro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'miembro',
  `ultimo_leido_mensaje_id` bigint(20) unsigned DEFAULT NULL,
  `silenciado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mensajeria_canal_miembros_canal_id_user_id_unique` (`canal_id`,`user_id`),
  KEY `mensajeria_canal_miembros_user_id_index` (`user_id`),
  CONSTRAINT `mensajeria_canal_miembros_canal_id_foreign`
    FOREIGN KEY (`canal_id`) REFERENCES `mensajeria_canales` (`id`),
  CONSTRAINT `mensajeria_canal_miembros_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 5) mensajeria_adjuntos — archivos e imágenes de un mensaje
-- -----------------------------------------------------------------------------
-- Los archivos NO van al disco `public` (a diferencia de los de ArchivoController,
-- que quedan servidos por URL directa bajo /storage). Van a storage/app/mensajeria,
-- que no es accesible por HTTP, y se sirven por un endpoint que chequea el canal.
-- En un chat privado, una URL adivinable que devuelve la foto de una conversación
-- ajena vaciaría de sentido todo el control de acceso.
--
-- `path` es la ruta en disco y NO viaja nunca al cliente. `ancho`/`alto` sólo se
-- cargan en imágenes, y sirven para que el hilo reserve el lugar antes de que la
-- foto cargue (si no, cada imagen que entra te mueve el texto que estabas leyendo).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajeria_adjuntos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mensaje_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamano` int(10) unsigned NOT NULL,
  `ancho` int(10) unsigned DEFAULT NULL,
  `alto` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mensajeria_adjuntos_mensaje_id_index` (`mensaje_id`),
  CONSTRAINT `mensajeria_adjuntos_mensaje_id_foreign`
    FOREIGN KEY (`mensaje_id`) REFERENCES `mensajeria_mensajes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 6) Registrar las migraciones como ejecutadas
-- -----------------------------------------------------------------------------
-- IMPRESCINDIBLE. Sin esto, el próximo `php artisan migrate` intenta crear las
-- tablas otra vez y corta el deploy con "table already exists".
--
-- El lote se calcula solo (el siguiente al último que haya en producción), así
-- que un eventual `migrate:rollback` deshace las 6 juntas, que es lo correcto:
-- son un solo cambio.
--
-- El WHERE NOT EXISTS lo hace repetible: si ya estaban registradas, no duplica.
-- -----------------------------------------------------------------------------
SET @lote = (SELECT IFNULL(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.nombre, @lote
FROM (
  SELECT '2026_09_27_100000_create_mensajeria_miembros_table'          AS nombre
  UNION ALL SELECT '2026_09_27_100100_create_mensajeria_canales_table'
  UNION ALL SELECT '2026_09_27_100200_create_mensajeria_mensajes_table'
  UNION ALL SELECT '2026_09_27_100300_create_mensajeria_canal_miembros_table'
  UNION ALL SELECT '2026_09_27_100400_create_mensajeria_adjuntos_table'
  UNION ALL SELECT '2026_09_27_100500_add_notificado_at_to_mensajeria_mensajes'
) AS m
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` mi WHERE mi.`migration` = m.nombre
);


-- -----------------------------------------------------------------------------
-- 7) Verificación
-- -----------------------------------------------------------------------------
-- Tiene que devolver 5 tablas y 6 migraciones. Si da menos, algo falló arriba.
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS tablas_creadas
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'mensajeria_miembros',
    'mensajeria_canales',
    'mensajeria_mensajes',
    'mensajeria_canal_miembros',
    'mensajeria_adjuntos'
  );

SELECT COUNT(*) AS migraciones_registradas
FROM `migrations`
WHERE `migration` LIKE '2026_09_27_1005%'
   OR `migration` LIKE '2026_09_27_1000%'
   OR `migration` LIKE '2026_09_27_1001%'
   OR `migration` LIKE '2026_09_27_1002%'
   OR `migration` LIKE '2026_09_27_1003%'
   OR `migration` LIKE '2026_09_27_1004%';
