-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-05-2026 a las 22:35:21
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
-- Base de datos: `turnostv`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administradores`
--

CREATE TABLE `administradores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `num_empleado` varchar(20) NOT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `administradores`
--

INSERT INTO `administradores` (`id`, `usuario_id`, `num_empleado`, `departamento`, `telefono`) VALUES
(1, 3, 'ADM-001', 'Administración', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `coordinadores`
--

CREATE TABLE `coordinadores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `num_empleado` varchar(20) NOT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `coordinadores`
--

INSERT INTO `coordinadores` (`id`, `usuario_id`, `num_empleado`, `departamento`, `telefono`) VALUES
(1, 1, 'COORD-01', 'Coordinación', NULL),
(2, 4, 'COORD-002', 'Coordinación', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dias_extra`
--

CREATE TABLE `dias_extra` (
  `id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('festivo','hora_extra','dia_extra') NOT NULL,
  `horas` decimal(4,2) DEFAULT 0.00,
  `dias` decimal(4,2) DEFAULT 0.00,
  `descripcion` varchar(255) DEFAULT '',
  `turno_id` int(11) DEFAULT NULL,
  `origen` enum('manual','automatico') NOT NULL DEFAULT 'manual',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_turno` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `dias_extra`
--

INSERT INTO `dias_extra` (`id`, `empleado_id`, `fecha`, `tipo`, `horas`, `dias`, `descripcion`, `turno_id`, `origen`, `created_at`, `fecha_turno`) VALUES
(34, 6, '2026-05-01', 'festivo', 7.00, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(35, 7, '2026-05-01', 'festivo', 8.00, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(36, 9, '2026-05-01', 'festivo', 8.50, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(37, 12, '2026-05-01', 'festivo', 17.25, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(38, 15, '2026-05-01', 'festivo', 17.25, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(39, 21, '2026-05-01', 'festivo', 17.25, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(40, 25, '2026-05-01', 'festivo', 10.25, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(42, 44, '2026-05-01', 'festivo', 4.75, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(43, 47, '2026-05-01', 'festivo', 2.75, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-08 07:45:43', '2026-05-01'),
(46, 13, '2026-05-01', 'festivo', 0.00, 1.00, 'Festivo 2026-05-01', NULL, 'automatico', '2026-05-12 19:25:46', '2026-05-01'),
(48, 47, '2026-05-14', 'dia_extra', 0.00, 0.00, '', NULL, 'manual', '2026-05-14 15:35:25', NULL),
(49, 48, '2026-05-14', 'dia_extra', 0.00, 0.00, '', NULL, 'manual', '2026-05-14 15:36:12', NULL),
(50, 48, '2026-05-14', 'festivo', 0.00, 1.00, 'festivo porque sí', NULL, 'manual', '2026-05-14 15:36:35', NULL),
(57, 51, '2026-05-15', 'festivo', 0.00, 1.00, 'Festivo 2026-05-15', NULL, 'automatico', '2026-05-14 15:47:25', '2026-05-15'),
(58, 51, '2026-05-05', 'dia_extra', 3.00, 0.00, '', NULL, 'manual', '2026-05-14 15:47:48', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `directores`
--

CREATE TABLE `directores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `num_empleado` varchar(20) NOT NULL,
  `telefono_produccion` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `directores`
--

INSERT INTO `directores` (`id`, `usuario_id`, `num_empleado`, `telefono_produccion`) VALUES
(1, 5, 'DIR-001', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `num_empleado` varchar(20) NOT NULL,
  `puesto` varchar(100) NOT NULL,
  `vacaciones_total` int(11) NOT NULL DEFAULT 22,
  `vacaciones_gastadas` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`id`, `usuario_id`, `num_empleado`, `puesto`, `vacaciones_total`, `vacaciones_gastadas`) VALUES
(1, 6, 'EMP-001', 'JEFE', 22, 5),
(3, 7, 'EMP-007', 'JEFE', 22, 0),
(4, 8, 'EMP-008', 'JEFE', 22, 0),
(5, 9, 'EMP-009', 'JEFE', 22, 0),
(6, 10, 'EMP-010', 'JEFE', 22, 0),
(7, 11, 'EMP-011', 'MEZCLA', 22, 0),
(8, 12, 'EMP-012', 'MEZCLA', 22, 0),
(9, 13, 'EMP-013', 'MEZCLA', 22, 0),
(10, 14, 'EMP-014', 'MEZCLA', 22, 0),
(11, 15, 'EMP-015', 'SONIDO', 22, 0),
(12, 16, 'EMP-016', 'SONIDO', 22, 0),
(13, 17, 'EMP-017', 'SONIDO', 22, 0),
(14, 18, 'EMP-018', 'SONIDO', 22, 0),
(15, 19, 'EMP-019', 'CCU', 22, 0),
(16, 20, 'EMP-020', 'CCU', 22, 0),
(17, 21, 'EMP-021', 'CCU', 22, 0),
(18, 22, 'EMP-022', 'CCU', 22, 0),
(19, 23, 'EMP-023', 'ILUMINA', 22, 0),
(20, 24, 'EMP-024', 'ILUMINA', 22, 0),
(21, 25, 'EMP-025', 'ILUMINA', 22, 0),
(22, 26, 'EMP-026', 'ILUMINA', 22, 0),
(23, 27, 'EMP-027', 'EVS', 22, 0),
(24, 28, 'EMP-028', 'EVS', 22, 0),
(25, 29, 'EMP-029', 'EVS', 22, 0),
(26, 30, 'EMP-030', 'EVS', 22, 0),
(27, 31, 'EMP-031', 'MULTIPLAY', 22, 0),
(28, 32, 'EMP-032', 'MULTIPLAY', 22, 0),
(29, 33, 'EMP-033', 'MULTIPLAY', 22, 0),
(30, 34, 'EMP-034', 'MULTIPLAY', 22, 0),
(31, 35, 'EMP-035', 'ROTULO', 22, 0),
(32, 36, 'EMP-036', 'ROTULO', 22, 0),
(33, 37, 'EMP-037', 'ROTULO', 22, 0),
(34, 38, 'EMP-038', 'ROTULO', 22, 0),
(35, 39, 'EMP-039', 'PROMPT', 22, 0),
(36, 40, 'EMP-040', 'PROMPT', 22, 0),
(37, 41, 'EMP-041', 'PROMPT', 22, 0),
(38, 42, 'EMP-042', 'PROMPT', 22, 0),
(39, 43, 'EMP-043', 'PRIMERA', 22, 0),
(40, 44, 'EMP-044', 'PRIMERA', 22, 0),
(41, 45, 'EMP-045', 'PRIMERA', 22, 0),
(42, 46, 'EMP-046', 'PRIMERA', 22, 0),
(43, 47, 'EMP-047', 'CAMARA', 22, 0),
(44, 48, 'EMP-048', 'CAMARA', 22, 0),
(45, 49, 'EMP-049', 'CAMARA', 22, 0),
(46, 50, 'EMP-050', 'CAMARA', 22, 0),
(47, 51, 'EMP-051', 'AUXILIAR', 22, 0),
(48, 52, 'EMP-052', 'AUXILIAR', 22, 0),
(49, 53, 'EMP-053', 'AUXILIAR', 22, 0),
(50, 54, 'EMP-054', 'AUXILIAR', 22, 0),
(51, 55, 'EMP-2000', 'MEZCLA', 22, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `festivos`
--

CREATE TABLE `festivos` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` varchar(100) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo` enum('festivo','fin_de_semana') NOT NULL DEFAULT 'festivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `festivos`
--

INSERT INTO `festivos` (`id`, `fecha`, `descripcion`, `created_at`, `tipo`) VALUES
(3, '2026-05-09', '', '2026-05-07 08:09:21', 'fin_de_semana'),
(4, '2026-05-10', '', '2026-05-07 08:09:22', 'fin_de_semana'),
(5, '2026-05-16', '', '2026-05-07 08:09:22', 'fin_de_semana'),
(6, '2026-05-17', '', '2026-05-07 08:09:22', 'fin_de_semana'),
(7, '2026-05-23', '', '2026-05-07 08:09:23', 'fin_de_semana'),
(8, '2026-05-24', '', '2026-05-07 08:09:23', 'fin_de_semana'),
(9, '2026-05-30', '', '2026-05-07 08:09:24', 'fin_de_semana'),
(10, '2026-05-31', '', '2026-05-07 08:09:24', 'fin_de_semana'),
(13, '2026-05-01', '', '2026-05-08 07:45:43', 'festivo'),
(14, '2026-05-02', '', '2026-05-11 14:49:51', 'fin_de_semana'),
(15, '2026-05-03', '', '2026-05-11 14:49:51', 'fin_de_semana'),
(20, '2026-05-15', '', '2026-05-14 15:47:25', 'festivo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `peticiones`
--

CREATE TABLE `peticiones` (
  `id` int(11) NOT NULL,
  `director_id` int(11) NOT NULL,
  `programa_id` int(11) DEFAULT NULL,
  `tipo` varchar(100) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_pedida` date DEFAULT NULL,
  `estado` enum('pendiente','en proceso','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `respuesta` text DEFAULT NULL,
  `fecha_peticion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `peticiones`
--

INSERT INTO `peticiones` (`id`, `director_id`, `programa_id`, `tipo`, `descripcion`, `fecha_pedida`, `estado`, `respuesta`, `fecha_peticion`) VALUES
(1, 1, NULL, 'Ampliar horario', 'afr adf sdf sdf sf', '2026-05-27', 'pendiente', NULL, '2026-05-14 18:50:20'),
(2, 1, NULL, 'Refuerzo fin semana', 'ft hydghfgh fh f', '2026-05-31', 'pendiente', NULL, '2026-05-14 18:59:47'),
(3, 1, NULL, 'Ampliar horario', 'sdfg s gd gs g sfsds', '2026-06-01', 'pendiente', NULL, '2026-05-14 19:24:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programas`
--

CREATE TABLE `programas` (
  `id` int(11) NOT NULL,
  `director_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `programas`
--

INSERT INTO `programas` (`id`, `director_id`, `nombre`, `descripcion`, `hora_inicio`, `hora_fin`) VALUES
(1, NULL, 'Informativo Matinal', '', '04:30:00', '09:00:00'),
(2, NULL, 'Informativos', '', '09:00:00', '13:00:00'),
(3, NULL, 'El Programa Mañanero', '', '09:00:00', '14:00:00'),
(4, NULL, 'Informativo Mediodía', '', '13:00:00', '17:00:00'),
(5, NULL, 'Los Deportes Mediodía', '', '14:00:00', '16:00:00'),
(6, NULL, 'El Programa de Tardeo', '', '16:00:00', '21:00:00'),
(7, NULL, 'Informativo Noche', '', '16:00:00', '22:00:00'),
(8, NULL, 'Los Deportes Noche', '', '20:00:00', '23:00:00'),
(9, NULL, 'El Mundo de Noche', '', '22:00:00', '03:00:00'),
(10, NULL, 'Especial Informativo', '', '00:00:00', '00:00:00'),
(11, NULL, 'Programa Divertido', '', '11:00:00', '20:00:00'),
(12, NULL, 'Informativos FD', '', '09:00:00', '00:00:00'),
(13, NULL, 'Guardia', '', '00:00:00', '00:00:00'),
(14, NULL, 'Otro', '', '00:00:00', '00:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programa_empleado`
--

CREATE TABLE `programa_empleado` (
  `programa_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes`
--

CREATE TABLE `solicitudes` (
  `id` int(11) NOT NULL,
  `programa_id` int(11) NOT NULL,
  `director_id` int(11) NOT NULL,
  `control_nombre` varchar(50) NOT NULL,
  `plato` varchar(50) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('pendiente','en_proceso','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `fecha_peticion` datetime NOT NULL DEFAULT current_timestamp(),
  `coordinador_id` int(11) DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `respuesta` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitudes`
--

INSERT INTO `solicitudes` (`id`, `programa_id`, `director_id`, `control_nombre`, `plato`, `hora_inicio`, `hora_fin`, `estado`, `notas`, `fecha_peticion`, `coordinador_id`, `fecha_respuesta`, `respuesta`) VALUES
(1, 1, 1, 'Control 3', 'Plato 3', '05:00:00', '11:00:00', 'en_proceso', 'turnos para el matinal', '2026-05-14 10:44:34', NULL, NULL, NULL),
(2, 8, 1, 'Control 3', 'Plato 3', '17:00:00', '22:00:00', 'aprobada', '', '2026-05-14 10:45:18', NULL, NULL, NULL),
(3, 13, 1, 'Control 3', 'Plato 3', '14:00:00', '22:00:00', 'pendiente', 'f dffffffffffffffffffffffffff daaaaaaaaaaaaaa', '2026-05-14 19:25:01', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_fechas`
--

CREATE TABLE `solicitud_fechas` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitud_fechas`
--

INSERT INTO `solicitud_fechas` (`id`, `solicitud_id`, `fecha`) VALUES
(1, 1, '2026-05-18'),
(2, 1, '2026-05-19'),
(3, 1, '2026-05-20'),
(4, 1, '2026-05-21'),
(5, 1, '2026-05-22'),
(6, 2, '2026-05-18'),
(8, 2, '2026-05-19'),
(9, 2, '2026-05-20'),
(10, 2, '2026-05-21'),
(7, 2, '2026-05-22'),
(11, 3, '2026-05-27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_puestos`
--

CREATE TABLE `solicitud_puestos` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `puesto_solicitado` varchar(100) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `empleado_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','cubierto') NOT NULL DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitud_puestos`
--

INSERT INTO `solicitud_puestos` (`id`, `solicitud_id`, `puesto_solicitado`, `hora_inicio`, `hora_fin`, `empleado_id`, `estado`) VALUES
(1, 1, 'JEFE', '05:00:00', '11:00:00', NULL, 'pendiente'),
(2, 1, 'MEZCLA', '05:00:00', '11:00:00', NULL, 'pendiente'),
(3, 1, 'SONIDO', '05:00:00', '11:00:00', NULL, 'pendiente'),
(4, 1, 'CCU', '05:00:00', '11:00:00', NULL, 'pendiente'),
(5, 1, 'ILUMINA', '05:00:00', '11:00:00', NULL, 'pendiente'),
(6, 1, 'EVS', '05:00:00', '11:00:00', NULL, 'pendiente'),
(7, 1, 'MULTIPLAY', '05:00:00', '11:00:00', NULL, 'pendiente'),
(8, 1, 'ROTULO', '05:00:00', '11:00:00', NULL, 'pendiente'),
(9, 1, 'PROMPT', '05:00:00', '11:00:00', NULL, 'pendiente'),
(10, 1, 'PRIMERA', '05:00:00', '11:00:00', NULL, 'pendiente'),
(11, 1, 'CAMARA', '05:00:00', '11:00:00', NULL, 'pendiente'),
(12, 1, 'AUXILIAR', '05:00:00', '11:00:00', NULL, 'pendiente'),
(13, 2, 'CCU', '17:00:00', '22:00:00', 15, 'cubierto'),
(14, 2, 'ILUMINA', '17:00:00', '22:00:00', 19, 'cubierto'),
(15, 2, 'EVS', '17:00:00', '22:00:00', 23, 'cubierto'),
(16, 3, 'MEZCLA', '14:00:00', '22:00:00', NULL, 'pendiente'),
(17, 3, 'ILUMINA', '14:00:00', '22:00:00', NULL, 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

CREATE TABLE `turnos` (
  `id` int(11) NOT NULL,
  `empleado_id` int(11) DEFAULT NULL,
  `programa_id` int(11) DEFAULT NULL,
  `coordinador_id` int(11) DEFAULT NULL,
  `puesto_solicitado` varchar(100) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('cubierto','sin_cubrir','solicitado','cancelado') NOT NULL DEFAULT 'sin_cubrir',
  `control_nombre` varchar(50) DEFAULT NULL,
  `plato` varchar(50) DEFAULT NULL,
  `bloque_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `turnos`
--

INSERT INTO `turnos` (`id`, `empleado_id`, `programa_id`, `coordinador_id`, `puesto_solicitado`, `fecha`, `hora_inicio`, `hora_fin`, `estado`, `control_nombre`, `plato`, `bloque_id`) VALUES
(1, 15, 8, 2, 'CCU', '2026-05-18', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 1),
(2, 19, 8, 2, 'ILUMINA', '2026-05-18', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 1),
(3, 23, 8, 2, 'EVS', '2026-05-18', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 1),
(4, 15, 8, 2, 'CCU', '2026-05-19', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 2),
(5, 19, 8, 2, 'ILUMINA', '2026-05-19', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 2),
(6, 23, 8, 2, 'EVS', '2026-05-19', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 2),
(7, 15, 8, 2, 'CCU', '2026-05-20', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 3),
(8, 19, 8, 2, 'ILUMINA', '2026-05-20', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 3),
(9, 23, 8, 2, 'EVS', '2026-05-20', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 3),
(10, 15, 8, 2, 'CCU', '2026-05-21', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 4),
(11, 19, 8, 2, 'ILUMINA', '2026-05-21', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 4),
(12, 23, 8, 2, 'EVS', '2026-05-21', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 4),
(13, 15, 8, 2, 'CCU', '2026-05-22', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 5),
(14, 19, 8, 2, 'ILUMINA', '2026-05-22', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 5),
(15, 23, 8, 2, 'EVS', '2026-05-22', '17:00:00', '22:00:00', 'cubierto', 'Control 3', 'Plato 3', 5),
(16, 3, 12, 2, 'JEFE', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(17, 7, 12, 2, 'MEZCLA', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(18, 11, 12, 2, 'SONIDO', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(19, 15, 12, 2, 'CCU', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(20, 19, 12, 2, 'ILUMINA', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(21, 23, 12, 2, 'EVS', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(22, 27, 12, 2, 'MULTIPLAY', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(23, 31, 12, 2, 'ROTULO', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(24, 35, 12, 2, 'PROMPT', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(25, 39, 12, 2, 'PRIMERA', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(26, 43, 12, 2, 'CAMARA', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(27, 47, 12, 2, 'AUXILIAR', '2026-05-16', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 6),
(28, 3, 12, 2, 'JEFE', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(29, 7, 12, 2, 'MEZCLA', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(30, 11, 12, 2, 'SONIDO', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(31, 15, 12, 2, 'CCU', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(32, 19, 12, 2, 'ILUMINA', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(33, 23, 12, 2, 'EVS', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(34, 27, 12, 2, 'MULTIPLAY', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(35, 31, 12, 2, 'ROTULO', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(36, 35, 12, 2, 'PROMPT', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(37, 39, 12, 2, 'PRIMERA', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(38, 43, 12, 2, 'CAMARA', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(39, 47, 12, 2, 'AUXILIAR', '2026-05-17', '09:00:00', '22:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(40, 8, 12, 2, 'MEZCLA', '2026-05-17', '07:30:00', '12:00:00', 'cubierto', 'Control 2', 'Plato 2', 7),
(53, NULL, 14, 2, 'JEFE', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(55, NULL, 14, 2, 'SONIDO', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(56, NULL, 14, 2, 'CCU', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(57, NULL, 14, 2, 'ILUMINA', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(58, NULL, 14, 2, 'EVS', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(59, NULL, 14, 2, 'MULTIPLAY', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(60, NULL, 14, 2, 'ROTULO', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(61, NULL, 14, 2, 'PROMPT', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(62, NULL, 14, 2, 'PRIMERA', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(63, NULL, 14, 2, 'CAMARA', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(64, NULL, 14, 2, 'AUXILIAR', '2026-05-15', '13:15:00', '18:30:00', 'sin_cubrir', 'Control 5', 'Plato 5', 9),
(66, 51, 14, 2, 'MEZCLA', '2026-05-15', '13:30:00', '17:30:00', 'cubierto', 'Control 5', 'Plato 5', 9),
(163, NULL, 7, 2, 'JEFE', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(164, NULL, 7, 2, 'MEZCLA', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(165, NULL, 7, 2, 'SONIDO', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(166, NULL, 7, 2, 'CCU', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(167, NULL, 7, 2, 'ILUMINA', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(168, NULL, 7, 2, 'EVS', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(169, NULL, 7, 2, 'MULTIPLAY', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(170, NULL, 7, 2, 'ROTULO', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(171, NULL, 7, 2, 'PROMPT', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(172, NULL, 7, 2, 'PRIMERA', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(173, NULL, 7, 2, 'CAMARA', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(174, NULL, 7, 2, 'AUXILIAR', '2026-05-11', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 18),
(175, NULL, 7, 2, 'JEFE', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(176, NULL, 7, 2, 'MEZCLA', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(177, NULL, 7, 2, 'SONIDO', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(178, NULL, 7, 2, 'CCU', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(179, NULL, 7, 2, 'ILUMINA', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(180, NULL, 7, 2, 'EVS', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(181, NULL, 7, 2, 'MULTIPLAY', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(182, NULL, 7, 2, 'ROTULO', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(183, NULL, 7, 2, 'PROMPT', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(184, NULL, 7, 2, 'PRIMERA', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(185, NULL, 7, 2, 'CAMARA', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(186, NULL, 7, 2, 'AUXILIAR', '2026-05-12', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 19),
(187, NULL, 7, 2, 'JEFE', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(188, NULL, 7, 2, 'MEZCLA', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(189, NULL, 7, 2, 'SONIDO', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(190, NULL, 7, 2, 'CCU', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(191, NULL, 7, 2, 'ILUMINA', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(192, NULL, 7, 2, 'EVS', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(193, NULL, 7, 2, 'MULTIPLAY', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(194, NULL, 7, 2, 'ROTULO', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(195, NULL, 7, 2, 'PROMPT', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(196, NULL, 7, 2, 'PRIMERA', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(197, NULL, 7, 2, 'CAMARA', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(198, NULL, 7, 2, 'AUXILIAR', '2026-05-13', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 20),
(199, NULL, 7, 2, 'JEFE', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(200, NULL, 7, 2, 'MEZCLA', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(201, NULL, 7, 2, 'SONIDO', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(202, NULL, 7, 2, 'CCU', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(203, NULL, 7, 2, 'ILUMINA', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(204, NULL, 7, 2, 'EVS', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(205, NULL, 7, 2, 'MULTIPLAY', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(206, NULL, 7, 2, 'ROTULO', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(207, NULL, 7, 2, 'PROMPT', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(208, NULL, 7, 2, 'PRIMERA', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(209, NULL, 7, 2, 'CAMARA', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(210, NULL, 7, 2, 'AUXILIAR', '2026-05-14', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 21),
(211, NULL, 7, 2, 'JEFE', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(212, NULL, 7, 2, 'MEZCLA', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(213, NULL, 7, 2, 'SONIDO', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(214, NULL, 7, 2, 'CCU', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(215, NULL, 7, 2, 'ILUMINA', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(216, NULL, 7, 2, 'EVS', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(217, NULL, 7, 2, 'MULTIPLAY', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(218, NULL, 7, 2, 'ROTULO', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(219, NULL, 7, 2, 'PROMPT', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(220, NULL, 7, 2, 'PRIMERA', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(221, NULL, 7, 2, 'CAMARA', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(222, NULL, 7, 2, 'AUXILIAR', '2026-05-15', '14:00:00', '22:00:00', 'sin_cubrir', 'Control 7', 'Plato 7', 22),
(223, 1, 9, 2, 'JEFE', '2026-05-11', '22:00:00', '03:00:00', 'cubierto', 'Control 6', 'Plato 6', 23),
(224, NULL, 9, 2, 'MEZCLA', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(225, NULL, 9, 2, 'SONIDO', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(226, NULL, 9, 2, 'CCU', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(227, NULL, 9, 2, 'ILUMINA', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(228, NULL, 9, 2, 'EVS', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(229, NULL, 9, 2, 'MULTIPLAY', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(230, NULL, 9, 2, 'ROTULO', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(231, NULL, 9, 2, 'PROMPT', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(232, NULL, 9, 2, 'PRIMERA', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(233, NULL, 9, 2, 'CAMARA', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(234, NULL, 9, 2, 'AUXILIAR', '2026-05-11', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 23),
(235, 1, 9, 2, 'JEFE', '2026-05-12', '22:00:00', '03:00:00', 'cubierto', 'Control 6', 'Plato 6', 24),
(236, NULL, 9, 2, 'MEZCLA', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(237, NULL, 9, 2, 'SONIDO', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(238, NULL, 9, 2, 'CCU', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(239, NULL, 9, 2, 'ILUMINA', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(240, NULL, 9, 2, 'EVS', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(241, NULL, 9, 2, 'MULTIPLAY', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(242, NULL, 9, 2, 'ROTULO', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(243, NULL, 9, 2, 'PROMPT', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(244, NULL, 9, 2, 'PRIMERA', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(245, NULL, 9, 2, 'CAMARA', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(246, NULL, 9, 2, 'AUXILIAR', '2026-05-12', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 24),
(247, 1, 9, 2, 'JEFE', '2026-05-13', '22:00:00', '03:00:00', 'cubierto', 'Control 6', 'Plato 6', 25),
(248, NULL, 9, 2, 'MEZCLA', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(249, NULL, 9, 2, 'SONIDO', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(250, NULL, 9, 2, 'CCU', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(251, NULL, 9, 2, 'ILUMINA', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(252, NULL, 9, 2, 'EVS', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(253, NULL, 9, 2, 'MULTIPLAY', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(254, NULL, 9, 2, 'ROTULO', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(255, NULL, 9, 2, 'PROMPT', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(256, NULL, 9, 2, 'PRIMERA', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(257, NULL, 9, 2, 'CAMARA', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(258, NULL, 9, 2, 'AUXILIAR', '2026-05-13', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 25),
(259, 4, 9, 2, 'JEFE', '2026-05-14', '22:00:00', '03:00:00', 'cubierto', 'Control 6', 'Plato 6', 26),
(260, 9, 9, 2, 'MEZCLA', '2026-05-14', '22:00:00', '03:00:00', 'cubierto', 'Control 6', 'Plato 6', 26),
(261, NULL, 9, 2, 'SONIDO', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(262, NULL, 9, 2, 'CCU', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(263, NULL, 9, 2, 'ILUMINA', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(264, NULL, 9, 2, 'EVS', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(265, NULL, 9, 2, 'MULTIPLAY', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(266, NULL, 9, 2, 'ROTULO', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(267, NULL, 9, 2, 'PROMPT', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(268, NULL, 9, 2, 'PRIMERA', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(269, NULL, 9, 2, 'CAMARA', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(270, NULL, 9, 2, 'AUXILIAR', '2026-05-14', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 26),
(271, 1, 9, 2, 'JEFE', '2026-05-15', '22:00:00', '03:00:00', 'cubierto', 'Control 6', 'Plato 6', 27),
(272, NULL, 9, 2, 'MEZCLA', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(273, NULL, 9, 2, 'SONIDO', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(274, NULL, 9, 2, 'CCU', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(275, NULL, 9, 2, 'ILUMINA', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(276, NULL, 9, 2, 'EVS', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(277, NULL, 9, 2, 'MULTIPLAY', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(278, NULL, 9, 2, 'ROTULO', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(279, NULL, 9, 2, 'PROMPT', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(280, NULL, 9, 2, 'PRIMERA', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(281, NULL, 9, 2, 'CAMARA', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27),
(282, NULL, 9, 2, 'AUXILIAR', '2026-05-15', '22:00:00', '03:00:00', 'sin_cubrir', 'Control 6', 'Plato 6', 27);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos_bloque`
--

CREATE TABLE `turnos_bloque` (
  `id` int(11) NOT NULL,
  `control_nombre` varchar(50) NOT NULL,
  `fecha` date NOT NULL,
  `programa_id` int(11) DEFAULT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `coordinador_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `turnos_bloque`
--

INSERT INTO `turnos_bloque` (`id`, `control_nombre`, `fecha`, `programa_id`, `hora_inicio`, `hora_fin`, `coordinador_id`) VALUES
(1, 'Control 3', '2026-05-18', 8, '17:00:00', '22:00:00', 2),
(2, 'Control 3', '2026-05-19', 8, '17:00:00', '22:00:00', 2),
(3, 'Control 3', '2026-05-20', 8, '17:00:00', '22:00:00', 2),
(4, 'Control 3', '2026-05-21', 8, '17:00:00', '22:00:00', 2),
(5, 'Control 3', '2026-05-22', 8, '17:00:00', '22:00:00', 2),
(6, 'Control 2', '2026-05-16', 12, '09:00:00', '22:00:00', 2),
(7, 'Control 2', '2026-05-17', 12, '09:00:00', '22:00:00', 2),
(9, 'Control 5', '2026-05-15', 14, '13:15:00', '18:30:00', 2),
(18, 'Control 7', '2026-05-11', 7, '14:00:00', '22:00:00', 2),
(19, 'Control 7', '2026-05-12', 7, '14:00:00', '22:00:00', 2),
(20, 'Control 7', '2026-05-13', 7, '14:00:00', '22:00:00', 2),
(21, 'Control 7', '2026-05-14', 7, '14:00:00', '22:00:00', 2),
(22, 'Control 7', '2026-05-15', 7, '14:00:00', '22:00:00', 2),
(23, 'Control 6', '2026-05-11', 9, '22:00:00', '03:00:00', 2),
(24, 'Control 6', '2026-05-12', 9, '22:00:00', '03:00:00', 2),
(25, 'Control 6', '2026-05-13', 9, '22:00:00', '03:00:00', 2),
(26, 'Control 6', '2026-05-14', 9, '22:00:00', '03:00:00', 2),
(27, 'Control 6', '2026-05-15', 9, '22:00:00', '03:00:00', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('administrador','coordinador','director','empleado') NOT NULL DEFAULT 'empleado',
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `foto_perfil` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `estado`, `foto_perfil`, `telefono`) VALUES
(1, 'Gonzalo', 'gonzalo@turnostv.com', '$2y$10$59Yh2Ak4eqnPyVG1/kttzODSmpl2wOaLxGOl1Nn3VoXyJd4G7dvLC', 'coordinador', 'activo', NULL, NULL),
(3, 'Administrador', 'admin@turnostv.com', '$2y$10$KYLhRLlqEYIEF.Jm42GdU.SzA1tVG9PoP76YvSxyYAMbUWkEoHlUq', 'administrador', 'activo', NULL, NULL),
(4, 'Gonzalo Martín Segovia', 'coord@turnostv.com', '$2y$10$hx5HK3Zyz5QmUpFTAbz1.u.Q7mc/R1dG3LW4prfz8/qVkCuV.ir7K', 'coordinador', 'activo', 'uploads/1778681918_CabezaPrueba.png', '666666888'),
(5, 'Nombre Director', 'director@turnostv.com', '$2y$10$0hLeL6LwYRtQDHltcXvvU.96CltYE.w5UAzZP/yKUDspBi7Zn5Wq6', 'director', 'activo', NULL, '585258525'),
(6, 'El mejor empleado', 'empleado@turnostv.com', '$2y$10$KAaXJT2u0htr8o7v1Gz7gOvYG/hrQ8jdvwEVkSnt//y8jC6X.R8ue', 'empleado', 'activo', NULL, ''),
(7, 'Jefe Técnico 1', 'jefe1@turnostv.com', '$2y$10$gNsKy/ODvyDbMEnLuhq02OYyfpJbPhg94V96np6UcmjXWh5MYDzgq', 'empleado', 'activo', NULL, NULL),
(8, 'Jefe Técnico 2', 'jefe2@turnostv.com', '$2y$10$4hv6TA1DDDnlWZmRNdxHoeRucQp/FhNi2Xv9ImFKpdq68kN5yfr2m', 'empleado', 'activo', NULL, NULL),
(9, 'Jefe Técnico 3', 'jefe3@turnostv.com', '$2y$10$pLwSyDVd01DRvwoAIOSDHugc5krvLBxWj3I.wSqs9K.G9uI3N5GvC', 'empleado', 'activo', NULL, NULL),
(10, 'Jefe Técnico 4', 'jefe4@turnostv.com', '$2y$10$2WOPufa.CXImKrYCHXB14eKc/ax8rTp5fhQucNQG8vrKzCc2MGSF2', 'empleado', 'activo', NULL, NULL),
(11, 'Mezclador 1', 'mezcla1@turnostv.com', '$2y$10$0KusRGFvVhzdw5QO.Gv4Jee5QsxALo33dEH7dP.KXauv.XYArsr2.', 'empleado', 'activo', NULL, NULL),
(12, 'Mezclador 2', 'mezcla2@turnostv.com', '$2y$10$k0DGSzRKjAH5hqy/aFTvluG6D104je/M7Z4fvz3aZvsKiCaNMmlp.', 'empleado', 'activo', NULL, NULL),
(13, 'Mezclador 3', 'mezcla3@turnostv.com', '$2y$10$Jzu1EmEJVXgxMxzk8TrB6.cxB39/KruD/g/IXiLrAnuyo6fHPisGq', 'empleado', 'activo', NULL, NULL),
(14, 'Mezclador 4', 'mezcla4@turnostv.com', '$2y$10$n6UPn8iDAkmB6FZB85jn7.IF/tW2823vCSQ6Kox8W9rf5fTVECIp2', 'empleado', 'activo', NULL, NULL),
(15, 'Sonido 1', 'sonido1@turnostv.com', '$2y$10$.WVJSMhof9fG3YStO/EpuO9l38O3NpRPzY4giihRwBn7rUUyrUBb6', 'empleado', 'activo', NULL, NULL),
(16, 'Sonido 2', 'sonido2@turnostv.com', '$2y$10$9GjNFmS2V/60NlqTg7yOIul0bLWWbX66DvnmqZ.SyExt4clPsmm3O', 'empleado', 'activo', NULL, NULL),
(17, 'Sonido 3', 'sonido3@turnostv.com', '$2y$10$VRrEVr5GonfEp5Cf9WsRYOP/btOGb5cnaPG.27hi892xSwtMSnmmK', 'empleado', 'activo', NULL, NULL),
(18, 'Sonido 4', 'sonido4@turnostv.com', '$2y$10$qgZEDa8s9UF2mUCtVV9cTOtWeMuF1R4Xyt70oEaKNwmMPMfn962Su', 'empleado', 'activo', NULL, NULL),
(19, 'CCU 1', 'ccu1@turnostv.com', '$2y$10$bkAsNpVQwxllJkO.VNssquUCGiQA0NyFPW3uAfjpy5M.2YG8Et/KK', 'empleado', 'activo', NULL, NULL),
(20, 'CCU 2', 'ccu2@turnostv.com', '$2y$10$5/2QN.KsD4QlxzTlwVgILusRvFktf5GLyVtLL.VcJzlj7zY225e5e', 'empleado', 'activo', NULL, NULL),
(21, 'CCU 3', 'ccu3@turnostv.com', '$2y$10$a5uT3uo/kXt28PSCd3ZEAuB8kOq1DXsECN6j/WitfR2Rg4nQfzP2W', 'empleado', 'activo', NULL, NULL),
(22, 'CCU 4', 'ccu4@turnostv.com', '$2y$10$IofOQt9yRTcjuGdf.CQWI.Fcan8Vzbj2aij4E9edPyVKsfVW1RudK', 'empleado', 'activo', NULL, NULL),
(23, 'Iluminación 1', 'ilumina1@turnostv.com', '$2y$10$LyQbv.QplhJhdUSlqNrRAO1SMI5J5S4hw0A5tw8k3uuyjURoRh5La', 'empleado', 'activo', NULL, NULL),
(24, 'Iluminación 2', 'ilumina2@turnostv.com', '$2y$10$7HRFfI/4PKbVucjq7jg3MOQ685ty9U5TGhxY42QyukoNX0FdUBkei', 'empleado', 'activo', NULL, NULL),
(25, 'Iluminación 3', 'ilumina3@turnostv.com', '$2y$10$9/19k5jxvvNGLIDX/9xtH.NMyKK8Z7gVnd4Oc/uKMD9vKgYrrJXqm', 'empleado', 'activo', NULL, NULL),
(26, 'Iluminación 4', 'ilumina4@turnostv.com', '$2y$10$YgJ7B3IsE49/aOi7YNvB2e9RAqD2DA.tp8TUAiBi8ws/oPjbFEtYO', 'empleado', 'activo', NULL, NULL),
(27, 'EVS 1', 'evs1@turnostv.com', '$2y$10$ThecHWV8rYBYr5pApI8GJuFHT9bSGVO6yxQgT8.sXDIjQVzpE5c9e', 'empleado', 'activo', NULL, NULL),
(28, 'EVS 2', 'evs2@turnostv.com', '$2y$10$ckiyRi1OEXeC/S/QasL32uIv8RZ1xlsWlhgbjRsP02N37m9TVBqTS', 'empleado', 'activo', NULL, NULL),
(29, 'EVS 3', 'evs3@turnostv.com', '$2y$10$3hXJpiqvpCL/RB.QV44Vy..UMGUUJFWOLd/4BxX9ldN6MBUXGgtMm', 'empleado', 'activo', NULL, NULL),
(30, 'EVS 4', 'evs4@turnostv.com', '$2y$10$iuHKDVzJrswo3U/TsZ1Uz.HVS4kWmlq8db1jXs5NjuulmzkYzFQSC', 'empleado', 'activo', NULL, NULL),
(31, 'Multiplay 1', 'multiplay1@turnostv.com', '$2y$10$DrByIqyr6i4a5gMQiZDC0ejU6e5Q4w7AFAlHAL4tp4kQuFFkwWGFm', 'empleado', 'activo', NULL, NULL),
(32, 'Multiplay 2', 'multiplay2@turnostv.com', '$2y$10$wgA5nacxf1mednuaGCQMeeOnGVE.GzFj8lyYiVQjQL06Mr.WTpZwy', 'empleado', 'activo', NULL, NULL),
(33, 'Multiplay 3', 'multiplay3@turnostv.com', '$2y$10$VO1C/eR2.5TbHi6Tfoy6gOy1mx1YpJeU4MDs71wEM35L3SO18IEKy', 'empleado', 'activo', NULL, NULL),
(34, 'Multiplay 4', 'multiplay4@turnostv.com', '$2y$10$am1G02U3pO6y.ygCwuV/kemzeJFrJozI3ewbCH9elX8YJsB6wmdQq', 'empleado', 'activo', NULL, NULL),
(35, 'Rótulo 1', 'rotulo1@turnostv.com', '$2y$10$aDsruyXFhzobfZhjZRWsM.JXU.xLOS9FUvcinee60w51ieA5Jhu3y', 'empleado', 'activo', NULL, NULL),
(36, 'Rótulo 2', 'rotulo2@turnostv.com', '$2y$10$NM.aZtZkslxY4Z/yfsFSdeTgqVJAKKRxt20DsKCem3CwK1MbRkZe2', 'empleado', 'activo', NULL, NULL),
(37, 'Rótulo 3', 'rotulo3@turnostv.com', '$2y$10$eWW7GBNHbMC9zs2aNuJB2.YV9/77KBXvaHRlw19UDsbrgg53ST.bi', 'empleado', 'activo', NULL, NULL),
(38, 'Rótulo 4', 'rotulo4@turnostv.com', '$2y$10$W3UtJYuAZNTpjFGgHxRs0uBOr.HX0Ycal63CMfe0Qwxxfyw04ThV6', 'empleado', 'activo', NULL, NULL),
(39, 'Prompt 1', 'prompt1@turnostv.com', '$2y$10$PYhK6Ccy4opJ6mfbg.i/7eikeTChO1UWOg0KSxbjkFD9f8M8HjChu', 'empleado', 'activo', NULL, NULL),
(40, 'Prompt 2', 'prompt2@turnostv.com', '$2y$10$wfrib6nsZu6SThCzLjyIfupYhxolmDdiCkacNhdA/jtoEhD4SWyoW', 'empleado', 'activo', NULL, NULL),
(41, 'Prompt 3', 'prompt3@turnostv.com', '$2y$10$3ghHHsFNi0gmDcGAY4zHiuLp1JO/VLfIfqpT7cZQjnEO1dCngUVxy', 'empleado', 'activo', NULL, NULL),
(42, 'Prompt 4', 'prompt4@turnostv.com', '$2y$10$ROfgry4VxCvrsyEw0F8YpOga/0.7Td3CQNGSQhlboCwmqjIx4yQUW', 'empleado', 'activo', NULL, NULL),
(43, 'Primera Cámara 1', 'primera1@turnostv.com', '$2y$10$LOTnhEKB9WzJjbE70/KRj.qzo56NCrGnDORkFIz6c1nPGXN3LL/B2', 'empleado', 'activo', NULL, NULL),
(44, 'Primera Cámara 2', 'primera2@turnostv.com', '$2y$10$L1lGQE57yqyYwQGg7sZTyuFXZmyZY6gAsGJOjYnqmxYudavzJ/CT6', 'empleado', 'activo', NULL, NULL),
(45, 'Primera Cámara 3', 'primera3@turnostv.com', '$2y$10$ehueFKWxbQnzgyK0gRaOcuZmaCat6nK7INMuM84pIh3M2O2oau.HK', 'empleado', 'activo', NULL, NULL),
(46, 'Primera Cámara 4', 'primera4@turnostv.com', '$2y$10$r68EduxB2f/tzkZRI3tbMuC6j6Haho0Zm0jwdsg11ibrZ6c4Olv7S', 'empleado', 'activo', NULL, NULL),
(47, 'Cámara 1', 'camara1@turnostv.com', '$2y$10$FRgFFQXx909tjTV91hHMf.xjT7bkjxHZlsB2gzcnyOaHqiUeIx93q', 'empleado', 'activo', NULL, NULL),
(48, 'Cámara 2', 'camara2@turnostv.com', '$2y$10$bPsG5LfW2ZjHIGYVOJHnH.jSpw4F4TIFmvr/EJuE1EVuZM/Zji7j6', 'empleado', 'activo', NULL, NULL),
(49, 'Cámara 3', 'camara3@turnostv.com', '$2y$10$zu92vbwdOyInb6DHi7AHLe./UmqnHhq5sdOVhSXxymqWge0PiU7/6', 'empleado', 'activo', NULL, NULL),
(50, 'Cámara 4', 'camara4@turnostv.com', '$2y$10$bLeqbME5aA0fmMDBG5EFD./tO1RNAfZhNbYKU736QoIvyxmEebx1G', 'empleado', 'activo', NULL, NULL),
(51, 'Auxiliar 1 - prueba', 'auxiliar1@turnostv.com', '$2y$10$ae/7SPpp2nUyhQlR15cXFupt9AacndZslUzAl1/rg51X/87vTNFru', 'empleado', 'activo', NULL, ''),
(52, 'Auxiliar 2', 'auxiliar2@turnostv.com', '$2y$10$j6QCwmz6OmZgx2oDkAyMLeZqiNSc.1tRea.QIy1u0Ucg7xzBf47g.', 'empleado', 'activo', NULL, NULL),
(53, 'Auxiliar 3', 'auxiliar3@turnostv.com', '$2y$10$JcxDuJyFx.xGP9jFxEn.VO/Wbf7PO4q2Aka.XH5wWDSLoQKTbXoZS', 'empleado', 'activo', NULL, NULL),
(54, 'Auxiliar 4', 'auxiliar4@turnostv.com', '$2y$10$vXmXxrrqo/ISv7qf91CELOMnqHSuEIvsYvlbOUW0uRx95MLMdT7Ci', 'empleado', 'activo', NULL, NULL),
(55, 'AEsterPrueba', 'esterprueba1@turnostv.com', '$2y$10$QRmN34WW6npwMVt4vDK9zea5Tj6LEaFDTDmHQb1l97ki2HMCk28BW', 'empleado', 'activo', NULL, '654987321');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vacaciones`
--

CREATE TABLE `vacaciones` (
  `id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `dias` int(11) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `fecha_peticion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `vacaciones`
--

INSERT INTO `vacaciones` (`id`, `empleado_id`, `fecha_inicio`, `fecha_fin`, `dias`, `motivo`, `estado`, `fecha_peticion`) VALUES
(1, 1, '2026-05-15', '2026-05-15', 1, 'festivooooo', 'rechazada', '2026-05-11 17:02:29'),
(2, 1, '2026-05-25', '2026-05-29', 5, '', 'aprobada', '2026-05-11 17:13:40'),
(3, 1, '2026-05-25', '2026-05-29', 5, '', 'en_proceso', '2026-05-11 17:18:02'),
(4, 7, '2026-05-21', '2026-05-21', 1, 'Médico: tengo medico a las 11', 'rechazada', '2026-05-11 21:05:29'),
(5, 7, '2026-05-20', '2026-05-21', 2, 'Asunto propio', 'en_proceso', '2026-05-11 21:05:36'),
(6, 7, '2026-05-13', '2026-05-24', 12, 'me quiero ir de vacaciones', 'pendiente', '2026-05-11 21:05:54'),
(7, 1, '2026-05-25', '2026-05-25', 1, 'Asunto propio', 'en_proceso', '2026-05-12 17:42:20'),
(8, 1, '2026-06-26', '2026-07-05', 10, '', 'pendiente', '2026-05-12 17:42:31'),
(9, 1, '2026-05-23', '2026-05-28', 6, 'Asunto propio: hola, ¿cómo estás? quiero asunto propioooooooooo', 'pendiente', '2026-05-12 17:44:45'),
(10, 1, '2026-05-14', '2026-05-23', 10, 'asda fa fasf asf', 'pendiente', '2026-05-14 20:07:57');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`),
  ADD UNIQUE KEY `num_empleado` (`num_empleado`);

--
-- Indices de la tabla `coordinadores`
--
ALTER TABLE `coordinadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`),
  ADD UNIQUE KEY `num_empleado` (`num_empleado`);

--
-- Indices de la tabla `dias_extra`
--
ALTER TABLE `dias_extra`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_festivo_auto` (`empleado_id`,`fecha_turno`,`tipo`,`origen`);

--
-- Indices de la tabla `directores`
--
ALTER TABLE `directores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`),
  ADD UNIQUE KEY `num_empleado` (`num_empleado`);

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`),
  ADD UNIQUE KEY `num_empleado` (`num_empleado`);

--
-- Indices de la tabla `festivos`
--
ALTER TABLE `festivos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fecha` (`fecha`);

--
-- Indices de la tabla `peticiones`
--
ALTER TABLE `peticiones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_peticion_director` (`director_id`),
  ADD KEY `fk_peticion_programa` (`programa_id`);

--
-- Indices de la tabla `programas`
--
ALTER TABLE `programas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_programa_director` (`director_id`);

--
-- Indices de la tabla `programa_empleado`
--
ALTER TABLE `programa_empleado`
  ADD PRIMARY KEY (`programa_id`,`empleado_id`),
  ADD KEY `fk_pe_empleado` (`empleado_id`);

--
-- Indices de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sol_programa` (`programa_id`),
  ADD KEY `fk_sol_director` (`director_id`),
  ADD KEY `fk_sol_coordinador` (`coordinador_id`);

--
-- Indices de la tabla `solicitud_fechas`
--
ALTER TABLE `solicitud_fechas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sol_fecha` (`solicitud_id`,`fecha`),
  ADD KEY `fk_sf_solicitud` (`solicitud_id`);

--
-- Indices de la tabla `solicitud_puestos`
--
ALTER TABLE `solicitud_puestos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sp_solicitud` (`solicitud_id`),
  ADD KEY `fk_sp_empleado` (`empleado_id`);

--
-- Indices de la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_turno_empleado` (`empleado_id`),
  ADD KEY `fk_turno_programa` (`programa_id`),
  ADD KEY `fk_turno_coordinador` (`coordinador_id`);

--
-- Indices de la tabla `turnos_bloque`
--
ALTER TABLE `turnos_bloque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `programa_id` (`programa_id`),
  ADD KEY `coordinador_id` (`coordinador_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vacaciones`
--
ALTER TABLE `vacaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vacaciones_empleado` (`empleado_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `coordinadores`
--
ALTER TABLE `coordinadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `dias_extra`
--
ALTER TABLE `dias_extra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT de la tabla `directores`
--
ALTER TABLE `directores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `festivos`
--
ALTER TABLE `festivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `peticiones`
--
ALTER TABLE `peticiones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `programas`
--
ALTER TABLE `programas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `solicitud_fechas`
--
ALTER TABLE `solicitud_fechas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `solicitud_puestos`
--
ALTER TABLE `solicitud_puestos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=283;

--
-- AUTO_INCREMENT de la tabla `turnos_bloque`
--
ALTER TABLE `turnos_bloque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de la tabla `vacaciones`
--
ALTER TABLE `vacaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
