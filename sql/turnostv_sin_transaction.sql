-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 07-05-2026 a las 08:33:28
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12



--
-- Base de datos: `turnostv`
--
SET FOREIGN_KEY_CHECKS = 0;
-- --------------------------------------------------------
DROP TABLE IF EXISTS `administradores`, `coordinadores`, `dias_extra`, `directores`, `empleados`, `festivos`, `peticiones`, `programa_empleado`, `programas`, `solicitud_fechas`, `solicitud_puestos`, `solicitudes`, `turnos`, `turnos_bloque`, `usuarios`, `vacaciones`;
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_fechas`
--

CREATE TABLE `solicitud_fechas` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `coordinadores`
--
ALTER TABLE `coordinadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `dias_extra`
--
ALTER TABLE `dias_extra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `directores`
--
ALTER TABLE `directores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `festivos`
--
ALTER TABLE `festivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `peticiones`
--
ALTER TABLE `peticiones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `programas`
--
ALTER TABLE `programas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_fechas`
--
ALTER TABLE `solicitud_fechas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_puestos`
--
ALTER TABLE `solicitud_puestos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `turnos_bloque`
--
ALTER TABLE `turnos_bloque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `vacaciones`
--
ALTER TABLE `vacaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
