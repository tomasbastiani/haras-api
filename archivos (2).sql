-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-11-2025 a las 13:18:24
-- Versión del servidor: 10.4.22-MariaDB
-- Versión de PHP: 8.0.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `hsm`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos`
--

CREATE TABLE `archivos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nlote` int(11) NOT NULL,
  `ncarta` int(11) NOT NULL,
  `user` varchar(150) NOT NULL,
  `comments` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `archivos`
--

INSERT INTO `archivos` (`id`, `nlote`, `ncarta`, `user`, `comments`, `file_path`, `file_name`, `mime_type`, `created_at`, `updated_at`) VALUES
(14, 408, 189, '1974consultoria@gmail.com', 'comprobante de pago', '/storage/archivos/0MGOtqyGVd8hX0bLs84x7oPdYm0JkvxjIRkg80IT.jpg', 'WhatsApp Image 2025-11-11 at 15.40.58.jpeg', 'image/jpeg', '2025-11-15 20:20:49', '2025-11-15 20:20:49'),
(15, 1, 190, 'flo.romaniello@gmail.comLote1New', 'imagen', '/storage/archivos/0dh8LgCRkPZYkOWcOfDgFxaRG6NYoVCA29DQ1cR4.jpg', 'file.jpg', 'image/jpeg', '2025-11-15 21:31:07', '2025-11-15 21:31:07'),
(16, 9, 199, 'fabiola@ciudad.com.arNuevo1', 'testing', '/storage/archivos/mUZm3f7wrAutliGmq2LuksYeN7C9ICDTuqVmWxoo.jpg', 'file (1).jpg', 'image/jpeg', '2025-11-17 18:24:49', '2025-11-17 18:24:49');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `archivos`
--
ALTER TABLE `archivos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `archivos`
--
ALTER TABLE `archivos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
