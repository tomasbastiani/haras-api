-- =====================================================================
--  Haras Santa María — foto del paquete al ingresar
--  Generado: 2026-08-27
--
--  Agrega `paquetes.foto_path`: la ruta en disco privado de la foto que
--  saca el operario cuando el paquete llega a la oficina.
--
--  ANTES DE EJECUTAR
--  -----------------
--  1) Backup de la base.
--  2) Es un ALTER TABLE aditivo: agrega una columna NULL, no toca ninguna
--     fila ni ninguna otra columna. Los paquetes ya cargados quedan con
--     foto_path NULL y la app los muestra sin foto, sin romperse.
--  3) Verificá que la columna no exista ya:
--     ->  SHOW COLUMNS FROM paquetes LIKE 'foto_path';
--
--  La imagen NO va en la base: se guarda en storage/app/paqueteria/fotos/
--  y se sirve por endpoint autenticado, igual que las firmas de las actas.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `paquetes`
  ADD COLUMN `foto_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `observaciones`;

-- ---------------------------------------------------------------------
--  Registro en la tabla `migrations`: sin esto, el próximo
--  `php artisan migrate` en producción va a intentar correr esta
--  migración de nuevo y va a explotar porque la columna ya existe.
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_08_27_110000_add_foto_to_paquetes_table', (SELECT IFNULL(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS m));
