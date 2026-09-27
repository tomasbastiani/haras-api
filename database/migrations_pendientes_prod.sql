-- =====================================================================
-- Migración pendiente para producción: 2026_08_28_100000_add_sum_quincho_a_canchas
-- Turnero deja fútbol/tenis y pasa a SUM y Quincho. Las canchas viejas
-- (fútbol/tenis) NO se borran, sólo se desactivan para no romper turnos
-- históricos que ya las referencian.
--
-- Revisar antes de correr en producción. Después de ejecutarlo, dejar
-- registrada la migración en la tabla `migrations` para que
-- `php artisan migrate:status` no la muestre como pendiente.
-- =====================================================================

START TRANSACTION;

-- 1) Ampliar el enum de tipo de cancha
ALTER TABLE canchas MODIFY tipo ENUM('futbol','tenis','sum','quincho') NOT NULL;

-- 2) Desactivar las canchas de fútbol y tenis existentes
UPDATE canchas SET activa = 0 WHERE tipo IN ('futbol','tenis');

-- 3) Alta (o actualización si ya existiera) de la cancha SUM
INSERT INTO canchas (nombre, tipo, activa, orden, created_at, updated_at)
SELECT 'SUM', 'sum', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM canchas WHERE nombre = 'SUM');

UPDATE canchas
SET tipo = 'sum', activa = 1, orden = 1, updated_at = NOW()
WHERE nombre = 'SUM';

-- 4) Alta (o actualización si ya existiera) de la cancha Quincho
INSERT INTO canchas (nombre, tipo, activa, orden, created_at, updated_at)
SELECT 'Quincho', 'quincho', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM canchas WHERE nombre = 'Quincho');

UPDATE canchas
SET tipo = 'quincho', activa = 1, orden = 1, updated_at = NOW()
WHERE nombre = 'Quincho';

-- 5) Registrar la migración como aplicada, para que artisan no la re-corra
INSERT INTO migrations (migration, batch)
SELECT '2026_08_28_100000_add_sum_quincho_a_canchas', (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m)
WHERE NOT EXISTS (
  SELECT 1 FROM migrations WHERE migration = '2026_08_28_100000_add_sum_quincho_a_canchas'
);

COMMIT;
