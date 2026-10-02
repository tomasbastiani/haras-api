-- =====================================================================
--  Haras Santa María — CVU y alias en el aviso de gastos comunes
--  Generado: 2026-10-02
--
--  Agrega `gastoscomunes_notificaciones.cvu` y `.alias`: los datos de pago
--  de cada lote, que el admin carga en el Excel de /import-gastos
--  (columnas D y E) y que salen en el mail "Nuevos gastos comunes
--  disponibles", debajo del período.
--
--  Equivale a la migración:
--    2026_10_02_100000_add_cvu_alias_to_gastoscomunes_notificaciones
--
--  ANTES DE EJECUTAR
--  -----------------
--  1) Backup de la base.
--  2) Es un ALTER TABLE aditivo: agrega dos columnas NULL y no toca ninguna
--     fila. La lista ya importada queda con CVU/alias vacíos hasta el
--     próximo import, y el mail en ese caso no los muestra.
--  3) Verificá que las columnas no existan ya:
--     ->  SHOW COLUMNS FROM gastoscomunes_notificaciones LIKE 'cvu';
--     ->  SHOW COLUMNS FROM gastoscomunes_notificaciones LIKE 'alias';
--  4) Verificá que exista la columna `nlote` (las nuevas van después):
--     ->  SHOW COLUMNS FROM gastoscomunes_notificaciones LIKE 'nlote';
--
--  El CVU va como texto (varchar 22), nunca como número: 22 dígitos no
--  entran en un BIGINT y como DECIMAL/DOUBLE se perderían los ceros a la
--  izquierda o los últimos dígitos.
--
--  ORDEN DE DESPLIEGUE
--  -------------------
--  Primero este script, DESPUÉS el backend nuevo. El importador nuevo
--  escribe en estas columnas y falla si no existen; el backend viejo, en
--  cambio, funciona igual con las columnas agregadas (las ignora).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `gastoscomunes_notificaciones`
  ADD COLUMN `cvu`   varchar(22) DEFAULT NULL AFTER `nlote`,
  ADD COLUMN `alias` varchar(20) DEFAULT NULL AFTER `cvu`;

-- ---------------------------------------------------------------------
--  Registro en la tabla `migrations`: sin esto, el próximo
--  `php artisan migrate` en producción va a intentar correr esta
--  migración de nuevo y va a explotar porque las columnas ya existen.
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_10_02_100000_add_cvu_alias_to_gastoscomunes_notificaciones', (SELECT IFNULL(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS m));
