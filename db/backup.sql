-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-10-2025 a las 05:17:21
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bd_bufete`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `id_area` int(11) NOT NULL,
  `area` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id_area`, `area`) VALUES
(1, 'Casos de Familia'),
(2, 'Diligencias Voluntarias (vía notarial, sin litis)'),
(3, 'Casos Laborales/Juicios Ordinarios');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesores`
--

CREATE TABLE `asesores` (
  `id_asesor` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `telefono` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asesores`
--

INSERT INTO `asesores` (`id_asesor`, `nombre`, `apellido`, `telefono`) VALUES
(1, 'Olga Maribel', 'Tello', '58815052'),
(2, 'Ingrid', 'Lopez', '48000030');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carreras`
--

CREATE TABLE `carreras` (
  `id_carrera` int(11) NOT NULL,
  `carrera` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `carreras`
--

INSERT INTO `carreras` (`id_carrera`, `carrera`) VALUES
(1, 'Licenciatura en Ciencias Jurídicas Sociales'),
(2, 'Licenciatura en Investigación Criminal y Forense'),
(3, 'Técnico Universitarios en Investigación Criminal y Forense');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados`
--

CREATE TABLE `estados` (
  `id_estado` int(11) NOT NULL,
  `estado` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados`
--

INSERT INTO `estados` (`id_estado`, `estado`) VALUES
(1, 'Activo'),
(2, 'Inactivo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_exp`
--

CREATE TABLE `estados_exp` (
  `id_estado_exp` int(11) NOT NULL,
  `estado_exp` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_exp`
--

INSERT INTO `estados_exp` (`id_estado_exp`, `estado_exp`) VALUES
(1, 'Conciliación'),
(2, 'Sentencia de primera'),
(3, 'Sentencia de segunda'),
(4, 'Desestimado'),
(5, 'Descargado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_prest`
--

CREATE TABLE `estados_prest` (
  `id_estado_prest` int(11) NOT NULL,
  `estado_prest` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_prest`
--

INSERT INTO `estados_prest` (`id_estado_prest`, `estado_prest`) VALUES
(1, 'Vigente'),
(2, 'Vencido'),
(3, 'Devuelto');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estantes`
--

CREATE TABLE `estantes` (
  `id_estante` int(11) NOT NULL,
  `estante` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estantes`
--

INSERT INTO `estantes` (`id_estante`, `estante`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `dpi_estudiante` varchar(13) NOT NULL,
  `carnetEstudiantil` varchar(9) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `id_carrera` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expedientes`
--

CREATE TABLE `expedientes` (
  `id_expediente` int(11) NOT NULL,
  `ficha_social` varchar(20) DEFAULT NULL,
  `numero_caso` varchar(2) DEFAULT NULL,
  `anio` year(4) DEFAULT NULL,
  `id_interesado` varchar(13) NOT NULL,
  `id_estudiante` varchar(13) NOT NULL,
  `id_juzgado` int(11) DEFAULT NULL,
  `num_proceso` varchar(50) DEFAULT NULL,
  `folios` int(4) NOT NULL,
  `id_asesor` int(11) DEFAULT NULL,
  `id_tipo_exp` int(11) NOT NULL,
  `id_estado_exp` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_audiencia1` date DEFAULT NULL,
  `fecha_audiencia2` date DEFAULT NULL,
  `fecha_finalizacion` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `id_estante` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inicios_fallidos`
--

CREATE TABLE `inicios_fallidos` (
  `id_log` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `ip` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `interesados`
--

CREATE TABLE `interesados` (
  `dpi_interesado` varchar(13) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `direccion_exacta` varchar(255) DEFAULT NULL,
  `id_lugar` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `juzgados`
--

CREATE TABLE `juzgados` (
  `id_juzgado` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `id_estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `juzgados`
--

INSERT INTO `juzgados` (`id_juzgado`, `nombre`, `id_estado`) VALUES
(1, 'Juzgado Pluripersonal de Primera Instancia de Familia del Departamento de Alta Verapaz\r\n\r\n', 1),
(2, 'Juzgado de Paz Civil, Familia y Trabajo del Municipio de Cobán, departamento de Alta Verapaz\r\n', 1),
(3, 'Juzgado de Paz de San Juan Chamelco, Alta Verapaz', 1),
(4, 'Juzgado de Paz de San Pedro Carchá, Alta Verapaz', 1),
(5, 'Juzgado de Paz de Santa Cruz Verapaz, Alta Verapaz', 1),
(6, 'Juzgado de Primera Instancia De Trabajo y Previsión Social y de lo Económico Coactivo del Departamento de Alta Verapaz', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lugares`
--

CREATE TABLE `lugares` (
  `id_lugar` int(11) NOT NULL,
  `municipio` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `lugares`
--

INSERT INTO `lugares` (`id_lugar`, `municipio`) VALUES
(1, 'Cobán'),
(2, ' San Juan Chamelco'),
(3, 'Santa Cruz'),
(4, 'San Pedro Carcha'),
(5, 'San Cristobal'),
(6, 'Tactic');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `id_prestamo` int(11) NOT NULL,
  `id_expediente` int(11) NOT NULL,
  `dpi_estudiante` varchar(13) NOT NULL,
  `fecha_entrega` date NOT NULL,
  `fecha_estimada_dev` date DEFAULT NULL,
  `fecha_devolucion` date DEFAULT NULL,
  `id_estado_prest` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `rol` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `rol`) VALUES
(1, 'Directora'),
(2, 'Secretaria');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_caso`
--

CREATE TABLE `tipo_caso` (
  `id_tipo_exp` int(11) NOT NULL,
  `caso` varchar(50) NOT NULL,
  `id_area` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id_trans` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tabla` varchar(50) NOT NULL,
  `id_registro` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `usuario` varchar(15) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `usuario`, `contrasena`, `id_rol`, `id_estado`) VALUES
(1, 'Astrid Johana', 'Lemus Peralta', 'Ajlemus', '$2y$10$VI0l9LhTCwnvIWtuMqWJzuKlCwVUSwiCE14oHBst.ULR7R/Nj9wCu', 1, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id_area`);

--
-- Indices de la tabla `asesores`
--
ALTER TABLE `asesores`
  ADD PRIMARY KEY (`id_asesor`);

--
-- Indices de la tabla `carreras`
--
ALTER TABLE `carreras`
  ADD PRIMARY KEY (`id_carrera`);

--
-- Indices de la tabla `estados`
--
ALTER TABLE `estados`
  ADD PRIMARY KEY (`id_estado`);

--
-- Indices de la tabla `estados_exp`
--
ALTER TABLE `estados_exp`
  ADD PRIMARY KEY (`id_estado_exp`);

--
-- Indices de la tabla `estados_prest`
--
ALTER TABLE `estados_prest`
  ADD PRIMARY KEY (`id_estado_prest`);

--
-- Indices de la tabla `estantes`
--
ALTER TABLE `estantes`
  ADD PRIMARY KEY (`id_estante`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`dpi_estudiante`),
  ADD UNIQUE KEY `carnet_Est` (`carnetEstudiantil`),
  ADD KEY `id_carrera` (`id_carrera`),
  ADD KEY `id_estado` (`id_estado`);

--
-- Indices de la tabla `expedientes`
--
ALTER TABLE `expedientes`
  ADD PRIMARY KEY (`id_expediente`),
  ADD KEY `id_interesado` (`id_interesado`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_juzgado` (`id_juzgado`),
  ADD KEY `id_asesor` (`id_asesor`),
  ADD KEY `id_tipo_caso` (`id_tipo_exp`),
  ADD KEY `id_estado_expe` (`id_estado_exp`),
  ADD KEY `id_estante` (`id_estante`);

--
-- Indices de la tabla `inicios_fallidos`
--
ALTER TABLE `inicios_fallidos`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `interesados`
--
ALTER TABLE `interesados`
  ADD PRIMARY KEY (`dpi_interesado`),
  ADD KEY `id_lugar` (`id_lugar`);

--
-- Indices de la tabla `juzgados`
--
ALTER TABLE `juzgados`
  ADD PRIMARY KEY (`id_juzgado`),
  ADD KEY `id_estado` (`id_estado`);

--
-- Indices de la tabla `lugares`
--
ALTER TABLE `lugares`
  ADD PRIMARY KEY (`id_lugar`);

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`id_prestamo`),
  ADD KEY `id_expediente` (`id_expediente`),
  ADD KEY `dpi_estudiante` (`dpi_estudiante`),
  ADD KEY `id_estado_prest` (`id_estado_prest`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `tipo_caso`
--
ALTER TABLE `tipo_caso`
  ADD PRIMARY KEY (`id_tipo_exp`),
  ADD KEY `id_area` (`id_area`);

--
-- Indices de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id_trans`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `id_rol` (`id_rol`),
  ADD KEY `id_estado` (`id_estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas`
--
ALTER TABLE `areas`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `asesores`
--
ALTER TABLE `asesores`
  MODIFY `id_asesor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `carreras`
--
ALTER TABLE `carreras`
  MODIFY `id_carrera` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `estados`
--
ALTER TABLE `estados`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `estados_exp`
--
ALTER TABLE `estados_exp`
  MODIFY `id_estado_exp` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `estados_prest`
--
ALTER TABLE `estados_prest`
  MODIFY `id_estado_prest` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `estantes`
--
ALTER TABLE `estantes`
  MODIFY `id_estante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `expedientes`
--
ALTER TABLE `expedientes`
  MODIFY `id_expediente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inicios_fallidos`
--
ALTER TABLE `inicios_fallidos`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `juzgados`
--
ALTER TABLE `juzgados`
  MODIFY `id_juzgado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `lugares`
--
ALTER TABLE `lugares`
  MODIFY `id_lugar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `id_prestamo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tipo_caso`
--
ALTER TABLE `tipo_caso`
  MODIFY `id_tipo_exp` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id_trans` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`id_carrera`) REFERENCES `carreras` (`id_carrera`),
  ADD CONSTRAINT `estudiantes_ibfk_2` FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`);

--
-- Filtros para la tabla `expedientes`
--
ALTER TABLE `expedientes`
  ADD CONSTRAINT `expedientes_ibfk_1` FOREIGN KEY (`id_interesado`) REFERENCES `interesados` (`dpi_interesado`),
  ADD CONSTRAINT `expedientes_ibfk_2` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`dpi_estudiante`),
  ADD CONSTRAINT `expedientes_ibfk_3` FOREIGN KEY (`id_juzgado`) REFERENCES `juzgados` (`id_juzgado`),
  ADD CONSTRAINT `expedientes_ibfk_4` FOREIGN KEY (`id_asesor`) REFERENCES `asesores` (`id_asesor`),
  ADD CONSTRAINT `expedientes_ibfk_5` FOREIGN KEY (`id_tipo_exp`) REFERENCES `tipo_caso` (`id_tipo_exp`),
  ADD CONSTRAINT `expedientes_ibfk_6` FOREIGN KEY (`id_estado_exp`) REFERENCES `estados_exp` (`id_estado_exp`),
  ADD CONSTRAINT `expedientes_ibfk_7` FOREIGN KEY (`id_estante`) REFERENCES `estantes` (`id_estante`);

--
-- Filtros para la tabla `inicios_fallidos`
--
ALTER TABLE `inicios_fallidos`
  ADD CONSTRAINT `inicios_fallidos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `interesados`
--
ALTER TABLE `interesados`
  ADD CONSTRAINT `interesados_ibfk_1` FOREIGN KEY (`id_lugar`) REFERENCES `lugares` (`id_lugar`);

--
-- Filtros para la tabla `juzgados`
--
ALTER TABLE `juzgados`
  ADD CONSTRAINT `juzgados_ibfk_1` FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`);

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `prestamos_ibfk_1` FOREIGN KEY (`id_expediente`) REFERENCES `expedientes` (`id_expediente`),
  ADD CONSTRAINT `prestamos_ibfk_2` FOREIGN KEY (`dpi_estudiante`) REFERENCES `estudiantes` (`dpi_estudiante`),
  ADD CONSTRAINT `prestamos_ibfk_3` FOREIGN KEY (`id_estado_prest`) REFERENCES `estados_prest` (`id_estado_prest`);

--
-- Filtros para la tabla `tipo_caso`
--
ALTER TABLE `tipo_caso`
  ADD CONSTRAINT `tipo_caso_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`);

--
-- Filtros para la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD CONSTRAINT `transacciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;