-- =====================================================================
--  Haras Santa María — cuenta dedicada de paquetería
--  Generado: 2026-08-27
--
--  Agrega la columna `users.paqueteria`, que marca una cuenta como
--  cuenta de portería: entra directo a la oficina de paquetería y no ve
--  gastos comunes, lotes, turnero ni archivos.
--
--  ANTES DE EJECUTAR
--  -----------------
--  1) Backup de la base.
--  2) Esto es un ALTER TABLE sobre `users`: agrega una columna con
--     DEFAULT 0, no toca ninguna fila existente ni ningún otro campo.
--     Todos los usuarios actuales quedan en 0 (sin cambios de comportamiento).
--  3) Verificá que la columna no exista ya:
--     ->  SHOW COLUMNS FROM users LIKE 'paqueteria';
--
--  La tabla `paqueteria_operarios` NO se toca: sigue siendo el permiso
--  aditivo para vecinos/empleados que además operan la oficina.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
  ADD COLUMN `paqueteria` tinyint(1) NOT NULL DEFAULT 0 AFTER `admin`;

-- ---------------------------------------------------------------------
--  Registro en la tabla `migrations`: sin esto, el próximo
--  `php artisan migrate` en producción va a intentar correr esta
--  migración de nuevo y va a explotar porque la columna ya existe.
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_08_27_100000_add_paqueteria_to_users_table', (SELECT IFNULL(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS m));

-- ---------------------------------------------------------------------
--  Alta de la primera cuenta de portería (opcional — descomentar y
--  completar). El password va hasheado con bcrypt; si lo cargás a mano
--  acá, generalo con `php artisan tinker` -> `Hash::make('...')`.
--  Lo normal es crearla desde la PWA: Administración -> Cuentas de Paquetería.
-- ---------------------------------------------------------------------
-- INSERT INTO `users` (`email`, `password`, `nombre`, `admin`, `paqueteria`, `created_at`, `updated_at`)
-- VALUES ('porteria@harassantamaria.com.ar', '$2y$10$...', 'Portería', 0, 1, NOW(), NOW());
