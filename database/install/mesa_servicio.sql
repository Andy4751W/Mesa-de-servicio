-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-08-2026 a las 18:00:05
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
-- Base de datos: `mesa_servicio`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogos`
--

CREATE TABLE `catalogos` (
  `id_catalogo` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `imagen` varchar(500) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 9999,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogos`
--

INSERT INTO `catalogos` (`id_catalogo`, `id_pais_operacion`, `nombre`, `descripcion`, `imagen`, `estado`, `orden`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'Adquisiciones', 'Solicitudes de compras y adquisiciones.', 'private/catalogos/catalogo_8c784825f9bebc25.webp', 'activo', 6, '2026-07-31 09:00:22', '2026-08-05 17:02:26'),
(2, 1, 'Contabilidad', 'Servicios contables, tributarios y financieros.', 'uploads/catalogo_970357f00a43dc74.jpg', 'activo', 3, '2026-07-31 09:00:22', '2026-08-06 16:34:52'),
(3, 1, 'Jurídica', 'Consultas y solicitudes jurídicas.', 'uploads/catalogo_67a694e3abb98f60.webp', 'activo', 1, '2026-07-31 09:00:22', '2026-08-10 19:59:47'),
(4, 1, 'Seguridad Integral', 'Servicios de seguridad y gestión de riesgos.', 'uploads/catalogo_d4a0a7c721a2ac5e.png', 'activo', 2, '2026-07-31 09:00:22', '2026-08-10 19:59:47'),
(5, 1, 'Talento Humano', 'Servicios relacionados con los colaboradores.', 'uploads/catalogo_f0d10ff0f4568d10.png', 'activo', 5, '2026-07-31 09:00:22', '2026-08-06 16:31:57'),
(6, 1, 'TICs', 'Soporte tecnológico y sistemas de información.', 'uploads/catalogo_9a16b4f1330fbb56.webp', 'activo', 4, '2026-07-31 09:00:22', '2026-08-06 16:31:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones_servicio`
--

CREATE TABLE `configuraciones_servicio` (
  `id_opcion` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `tipo` enum('pais','departamento','ciudad','prioridad','urgencia','nivel','impacto','estado') NOT NULL,
  `id_padre` int(11) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` char(7) NOT NULL DEFAULT '#0f6fec',
  `estado_registro` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuraciones_servicio`
--

INSERT INTO `configuraciones_servicio` (`id_opcion`, `id_pais_operacion`, `tipo`, `id_padre`, `nombre`, `descripcion`, `color`, `estado_registro`, `orden`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'pais', NULL, 'Colombia', 'País de operación.', '#7c3aed', 'activo', 1, '2026-07-31 09:00:22', '2026-08-05 21:45:35'),
(2, 1, 'ciudad', 9, 'Bogotá', 'Ciudad principal de operación.', '#8b5cf6', 'activo', 1, '2026-07-31 09:00:22', '2026-08-06 15:34:34'),
(9, 1, 'departamento', 1, 'cundinamarca', 'n', '#0f6fec', 'activo', 1, '2026-07-31 09:00:22', '2026-08-06 15:34:34'),
(10, 1, 'prioridad', NULL, 'Baja', 'Prioridad de impacto reducido.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(11, 1, 'prioridad', NULL, 'Media', 'Prioridad de atención regular.', '#eab308', 'activo', 2, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(12, 1, 'prioridad', NULL, 'Alta', 'Prioridad que requiere pronta atención.', '#f97316', 'activo', 3, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(13, 1, 'prioridad', NULL, 'Crítica', 'Prioridad con afectación crítica.', '#dc2626', 'activo', 4, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(14, 1, 'urgencia', NULL, 'Baja', 'Puede atenderse dentro del tiempo normal.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(15, 1, 'urgencia', NULL, 'Moderada', 'Requiere atención regular.', '#eab308', 'activo', 2, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(16, 1, 'urgencia', NULL, 'Alta', 'Requiere atención prioritaria.', '#f97316', 'activo', 3, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(17, 1, 'urgencia', NULL, 'Urgente', 'Requiere atención inmediata.', '#dc2626', 'activo', 4, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(18, 1, 'nivel', NULL, 'Nivel 1', 'Atención básica o primer nivel.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(19, 1, 'nivel', NULL, 'Nivel 2', 'Atención especializada o segundo nivel.', '#3b82f6', 'activo', 2, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(20, 1, 'nivel', NULL, 'Nivel 3', 'Atención avanzada o tercer nivel.', '#8b5cf6', 'activo', 3, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(21, 1, 'impacto', NULL, 'Usuario', 'Afecta a un usuario individual.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(22, 1, 'impacto', NULL, 'Área', 'Afecta a un área o equipo de trabajo.', '#3b82f6', 'activo', 2, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(23, 1, 'impacto', NULL, 'Empresa', 'Afecta a una empresa o filial.', '#f97316', 'activo', 3, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(24, 1, 'impacto', NULL, 'Negocio', 'Afecta la continuidad del negocio.', '#dc2626', 'activo', 4, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(25, 1, 'estado', NULL, 'Abierto', 'Solicitud registrada y pendiente.', '#16a34a', 'activo', 1, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(26, 1, 'estado', NULL, 'En proceso', 'Solicitud actualmente en gestión.', '#2563eb', 'activo', 2, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(27, 1, 'estado', NULL, 'En espera', 'Pendiente de información o validación.', '#ca8a04', 'activo', 3, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(28, 1, 'estado', NULL, 'Resuelta', 'Solicitud solucionada.', '#64748b', 'activo', 4, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(29, 1, 'estado', NULL, 'Cerrada', 'Solicitud finalizada y cerrada.', '#475569', 'activo', 5, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(30, 1, 'estado', NULL, 'Cancelada', 'Solicitud cancelada.', '#dc2626', 'activo', 6, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(31, 1, 'pais', NULL, 'Alemania', '', '#ff0033', 'activo', 3, '2026-07-31 09:12:18', '2026-08-05 13:50:04'),
(32, 1, 'ciudad', NULL, 'Berlín', '', '#ff0000', 'activo', 3, '2026-07-31 09:12:52', '2026-08-06 15:34:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `feriados`
--

CREATE TABLE `feriados` (
  `id_feriado` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `tipo` enum('dia_completo','rango_horario') NOT NULL DEFAULT 'dia_completo',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `id_creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `feriados`
--

INSERT INTO `feriados` (`id_feriado`, `id_pais_operacion`, `nombre`, `tipo`, `fecha_inicio`, `fecha_fin`, `descripcion`, `estado`, `id_creado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'festivo', 'dia_completo', '2026-08-06 00:00:00', '2026-08-07 00:00:00', '', 'activo', 1, '2026-08-05 12:58:10', '2026-08-05 17:58:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_acciones`
--

CREATE TABLE `historial_acciones` (
  `id_historial` bigint(20) UNSIGNED NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(160) NOT NULL,
  `detalle` text DEFAULT NULL,
  `fecha_accion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes`
--

CREATE TABLE `mensajes` (
  `id_mensaje` bigint(20) UNSIGNED NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_emisor` int(11) DEFAULT NULL,
  `mensaje` text NOT NULL,
  `fecha_envio` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_ticket` int(11) DEFAULT NULL,
  `id_ticket_etapa` bigint(20) UNSIGNED DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `mensaje` varchar(1000) NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `creada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `leida_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paises_operacion`
--

CREATE TABLE `paises_operacion` (
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `codigo` char(2) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `zona_horaria` varchar(80) NOT NULL,
  `color_primario` char(7) NOT NULL,
  `color_secundario` char(7) NOT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paises_operacion`
--

INSERT INTO `paises_operacion` (`id_pais_operacion`, `codigo`, `nombre`, `zona_horaria`, `color_primario`, `color_secundario`, `estado`, `orden`, `creado_en`) VALUES
(1, 'CO', 'Colombia', 'America/Bogota', '#0f6fec', '#facc15', 'activo', 1, '2026-08-05 08:50:04'),
(2, 'PE', 'Perú', 'America/Lima', '#c81e3a', '#ffffff', 'activo', 2, '2026-08-05 08:50:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procesos`
--

CREATE TABLE `procesos` (
  `id_proceso` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` varchar(1000) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `procesos`
--

INSERT INTO `procesos` (`id_proceso`, `id_pais_operacion`, `nombre`, `descripcion`, `estado`, `creado_por`, `actualizado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'Ingreso', 'Ingreso', 'activo', 1, 1, '2026-08-03 09:39:57', '2026-08-05 13:50:04'),
(2, 1, 'asignacion de computador', 'diligenciado', 'activo', 1, 1, '2026-08-03 11:52:26', '2026-08-05 13:50:04'),
(3, 1, 'Jurídica · Revisión de contratos', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-05 13:50:04'),
(4, 1, 'Contabilidad · Declaración de impuestos', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-05 13:50:04'),
(5, 1, 'Adquisiciones · Solicitud de compra', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-05 13:50:04'),
(6, 1, 'Seguridad Integral · Reporte de incidente de seguridad', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-05 13:50:04'),
(7, 1, 'TICs · asignacion de computador', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-05 13:50:04'),
(8, 1, 'Talento Humano · Ingreso', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:37:50', '2026-08-05 13:50:04'),
(9, 1, 'TICs · Inconvenientes tecnologicos', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-05 14:29:07', '2026-08-05 19:29:07'),
(10, 1, 'TICs · Recepción de equipos y entrega de otro', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-05 14:31:20', '2026-08-05 19:31:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proceso_etapas`
--

CREATE TABLE `proceso_etapas` (
  `id_proceso_etapa` int(11) NOT NULL,
  `id_proceso` int(11) NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `id_gestor` int(11) DEFAULT NULL,
  `id_sla` int(11) DEFAULT NULL,
  `orden` int(10) UNSIGNED NOT NULL,
  `nombre_etapa` varchar(160) DEFAULT NULL,
  `instrucciones` varchar(1000) DEFAULT NULL,
  `requiere_comentario_cierre` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proceso_etapas`
--

INSERT INTO `proceso_etapas` (`id_proceso_etapa`, `id_proceso`, `id_servicio`, `id_gestor`, `id_sla`, `orden`, `nombre_etapa`, `instrucciones`, `requiere_comentario_cierre`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 7, 2, 4, 2, 'Asignacion de computador', NULL, 0, 'activo', '2026-08-03 09:44:10', '2026-08-03 21:51:44'),
(2, 1, 5, 2, 4, 1, 'Ingreso', NULL, 0, 'activo', '2026-08-03 09:51:45', '2026-08-03 21:51:44'),
(3, 2, 6, 2, 2, 1, 'diligenciado', NULL, 0, 'activo', '2026-08-03 11:53:12', '2026-08-03 21:51:44'),
(4, 2, 7, 2, 4, 2, 'Asignacion de computador', NULL, 0, 'activo', '2026-08-03 11:53:39', '2026-08-03 21:51:44'),
(5, 3, 3, 2, 3, 1, NULL, NULL, 0, 'activo', '2026-08-03 12:30:34', '2026-08-03 21:51:44'),
(6, 4, 2, 2, 3, 1, NULL, NULL, 0, 'activo', '2026-08-03 12:30:34', '2026-08-03 21:51:44'),
(7, 5, 1, 2, 2, 1, NULL, NULL, 0, 'activo', '2026-08-03 12:30:34', '2026-08-03 21:51:44'),
(8, 6, 4, 2, 2, 1, NULL, NULL, 0, 'activo', '2026-08-03 12:30:34', '2026-08-03 21:51:44'),
(9, 7, 7, 2, 4, 1, NULL, NULL, 0, 'activo', '2026-08-03 12:30:34', '2026-08-03 21:51:44'),
(10, 3, 7, 2, 4, 2, 'Asignacion de computador', 'asignar computador', 0, 'activo', '2026-08-03 12:32:51', '2026-08-03 21:51:44'),
(11, 6, 5, 2, 4, 2, 'estado del colaborador', NULL, 0, 'activo', '2026-08-03 12:33:50', '2026-08-03 21:51:44'),
(12, 8, 8, 2, 3, 1, NULL, NULL, 0, 'activo', '2026-08-03 12:37:50', '2026-08-03 21:51:44'),
(13, 8, 7, 2, 4, 4, 'Asignacion de computador', NULL, 0, 'activo', '2026-08-03 12:38:13', '2026-08-03 21:51:44'),
(14, 8, 5, 2, 4, 3, 'Certificacion bancaria', NULL, 0, 'activo', '2026-08-03 12:38:49', '2026-08-03 21:51:44'),
(15, 8, 3, 2, 3, 2, 'contratación', NULL, 0, 'activo', '2026-08-03 12:39:13', '2026-08-03 21:51:44'),
(16, 9, 9, NULL, NULL, 1, NULL, NULL, 0, 'activo', '2026-08-05 14:29:07', '2026-08-05 19:29:07'),
(17, 9, 6, NULL, NULL, 2, 'Validacion de equipo', NULL, 0, 'activo', '2026-08-05 14:29:50', '2026-08-05 19:29:50'),
(18, 10, 10, NULL, NULL, 1, NULL, NULL, 0, 'activo', '2026-08-05 14:31:20', '2026-08-05 19:31:20'),
(19, 9, 7, NULL, NULL, 3, 'Recepcion de computador', NULL, 0, 'activo', '2026-08-05 14:31:42', '2026-08-05 19:31:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proceso_etapa_checklist`
--

CREATE TABLE `proceso_etapa_checklist` (
  `id_checklist` int(11) NOT NULL,
  `id_proceso_etapa` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` varchar(1000) DEFAULT NULL,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 1,
  `requiere_evidencia` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proceso_etapa_checklist`
--

INSERT INTO `proceso_etapa_checklist` (`id_checklist`, `id_proceso_etapa`, `nombre`, `descripcion`, `obligatorio`, `requiere_evidencia`, `orden`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'Documentos diligenciados', NULL, 1, 0, 1, 'activo', '2026-08-03 09:50:13', '2026-08-03 14:50:13'),
(2, 2, 'Documentos diligenciados', NULL, 1, 0, 1, 'activo', '2026-08-03 09:52:03', '2026-08-03 14:52:03'),
(3, 16, 'Documentos diligenciados', NULL, 1, 0, 1, 'activo', '2026-08-05 15:33:04', '2026-08-05 20:33:04'),
(4, 16, '¿se generaron las validaciones correspondientes?', NULL, 1, 0, 2, 'activo', '2026-08-05 15:34:13', '2026-08-05 20:34:13'),
(5, 5, '¿Se valido identidad?', NULL, 1, 0, 1, 'activo', '2026-08-06 12:07:35', '2026-08-06 17:07:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`, `descripcion`, `estado`, `creado_en`) VALUES
(1, 'Administrador', 'Administración completa de la Mesa de Servicio.', 'activo', '2026-07-31 09:00:22'),
(2, 'Gestor', 'Atención y gestión de los tickets asignados.', 'activo', '2026-07-31 09:00:22'),
(3, 'Solicitante', 'Creación y seguimiento de solicitudes.', 'activo', '2026-07-31 09:00:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguridad_intentos_login`
--

CREATE TABLE `seguridad_intentos_login` (
  `clave` char(64) NOT NULL,
  `intentos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_intento` datetime NOT NULL DEFAULT current_timestamp(),
  `bloqueado_hasta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `seguridad_intentos_login`
--

INSERT INTO `seguridad_intentos_login` (`clave`, `intentos`, `ultimo_intento`, `bloqueado_hasta`) VALUES
('1747eb9fb4376f968ead335be851c7a2f65b97c75415743509312ee4ecf1c5e1', 2, '2026-08-10 14:56:53', NULL),
('b94edceade70d83214a30d9839bc23ed9752673e972d90e843ebccc1ccf6392d', 1, '2026-08-10 14:57:03', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recuperaciones_password`
--

CREATE TABLE `recuperaciones_password` (
  `id_recuperacion` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `codigo_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `solicitado_ip_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id_servicio` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `id_catalogo` int(11) NOT NULL,
  `id_sla` int(11) NOT NULL,
  `id_gestor` int(11) DEFAULT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_solicitud` enum('requerimiento','incidente') NOT NULL DEFAULT 'requerimiento',
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `id_pais` int(11) DEFAULT NULL,
  `id_ciudad` int(11) DEFAULT NULL,
  `id_departamento` int(11) DEFAULT NULL,
  `id_prioridad` int(11) DEFAULT NULL,
  `id_urgencia` int(11) DEFAULT NULL,
  `id_nivel` int(11) DEFAULT NULL,
  `id_impacto` int(11) DEFAULT NULL,
  `id_estado` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id_servicio`, `id_pais_operacion`, `id_catalogo`, `id_sla`, `id_gestor`, `nombre`, `descripcion`, `tipo_solicitud`, `estado`, `id_pais`, `id_ciudad`, `id_departamento`, `id_prioridad`, `id_urgencia`, `id_nivel`, `id_impacto`, `id_estado`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 1, 2, 4, 'Solicitud de compra', 'Registro de requerimientos de compra.', 'requerimiento', 'activo', 1, 2, 9, 11, 15, 18, 21, 25, '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(2, 1, 2, 5, 4, 'Declaración de impuestos', 'Gestión tributaria y declaración de impuestos.', 'requerimiento', 'activo', 1, 2, 9, 11, 15, 18, 22, 25, '2026-07-31 09:00:22', '2026-08-06 15:34:34'),
(3, 1, 3, 3, 2, 'Revisión de contratos', 'Revisión y análisis de documentos contractuales.', 'requerimiento', 'activo', 1, 2, 9, 13, 15, 18, 22, 25, '2026-07-31 09:00:22', '2026-08-10 20:40:09'),
(4, 1, 4, 2, 2, 'Reporte de incidente de seguridad', 'Atención de novedades de seguridad integral.', 'incidente', 'activo', 1, 2, 9, 12, 16, 18, 23, 25, '2026-07-31 09:00:22', '2026-08-10 13:53:23'),
(5, 1, 5, 4, 2, 'Novedades de nómina', 'Gestión de novedades relacionadas con nómina.', 'requerimiento', 'activo', 1, 2, 9, 11, 15, 18, 21, 25, '2026-07-31 09:00:22', '2026-08-06 15:34:34'),
(6, 1, 6, 2, 2, 'Soporte técnico general', 'Atención de incidentes y requerimientos de TICs.', 'incidente', 'activo', 1, 2, 9, 12, 16, 18, 22, 25, '2026-07-31 09:00:22', '2026-08-10 13:53:23'),
(7, 1, 6, 4, 2, 'asignacion de computador', 'na', 'requerimiento', 'activo', 1, 2, 9, 11, 14, 18, 21, 25, '2026-08-03 09:41:53', '2026-08-05 13:50:04'),
(8, 1, 5, 3, 2, 'Ingreso', 'Ingreso del colaborador a la empresa', 'requerimiento', 'activo', 1, 2, 9, 11, 15, 18, 23, 25, '2026-08-03 12:37:47', '2026-08-05 13:50:04'),
(9, 1, 6, 4, 4, 'Inconvenientes tecnologicos', 'Cuando el equipo no prende, no inicia o no puede usarlo', 'requerimiento', 'activo', 1, 2, 9, 10, 14, 18, 21, 25, '2026-08-05 14:29:05', '2026-08-05 19:29:24'),
(10, 1, 6, 1, NULL, 'Recepción de equipos y entrega de otro', 'Cuando el equipo falla y no hay solucion se valida la causa y se le entrega uno nuevo', 'requerimiento', 'activo', 1, 2, 9, 10, 14, 18, 21, 25, '2026-08-05 14:31:18', '2026-08-05 19:31:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sla`
--

CREATE TABLE `sla` (
  `id_sla` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `tiempo_respuesta` int(10) UNSIGNED NOT NULL,
  `unidad` enum('minutos','horas','dias') NOT NULL DEFAULT 'dias',
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sla`
--

INSERT INTO `sla` (`id_sla`, `id_pais_operacion`, `nombre`, `tiempo_respuesta`, `unidad`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'SLA inicial - 1 día', 1, 'dias', 'activo', '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(2, 1, 'SLA prioritario - 4 horas', 4, 'horas', 'activo', '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(3, 1, 'SLA estándar - 3 días', 3, 'dias', 'activo', '2026-07-31 09:00:22', '2026-08-05 13:50:04'),
(4, 1, '4 días', 4, 'dias', 'activo', '2026-07-31 12:21:27', '2026-08-05 13:50:04'),
(5, 1, '30 dias tics', 30, 'dias', 'activo', '2026-08-03 12:02:54', '2026-08-05 13:50:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_actividades`
--

CREATE TABLE `solicitud_actividades` (
  `id_actividad` int(11) NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_programada` datetime DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
  `id_responsable` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `completado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_adjuntos`
--

CREATE TABLE `solicitud_adjuntos` (
  `id_adjunto` bigint(20) UNSIGNED NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_ticket_etapa` bigint(20) UNSIGNED DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_guardado` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(120) NOT NULL,
  `tamano` int(10) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `solicitud_adjuntos`
--
DELIMITER $$
CREATE TRIGGER `trg_actividad_adjunto_insert` AFTER INSERT ON `solicitud_adjuntos` FOR EACH ROW BEGIN
    UPDATE tickets SET actualizado_en = NOW() WHERE id_ticket = NEW.id_ticket;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_calificaciones`
--

CREATE TABLE `solicitud_calificaciones` (
  `id_calificacion` int(11) NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_ticket_etapa` bigint(20) UNSIGNED DEFAULT NULL,
  `id_solicitante` int(11) DEFAULT NULL,
  `id_gestor` int(11) DEFAULT NULL,
  `calificacion` tinyint(3) UNSIGNED NOT NULL,
  `calificacion_area` tinyint(3) UNSIGNED DEFAULT NULL,
  `calificacion_tiempo` tinyint(3) UNSIGNED DEFAULT NULL,
  `tipo_calificacion` enum('encuesta_servicio','evaluacion_derivacion','evaluacion_caso','historica') NOT NULL DEFAULT 'historica',
  `comentario` varchar(1000) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `solicitud_calificaciones`
--
DELIMITER $$
CREATE TRIGGER `trg_actividad_calificacion_insert` AFTER INSERT ON `solicitud_calificaciones` FOR EACH ROW BEGIN
    UPDATE tickets SET actualizado_en = NOW() WHERE id_ticket = NEW.id_ticket;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_comunicaciones`
--

CREATE TABLE `solicitud_comunicaciones` (
  `id_comunicacion` bigint(20) UNSIGNED NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_ticket_etapa` bigint(20) UNSIGNED DEFAULT NULL,
  `id_emisor` int(11) DEFAULT NULL,
  `tipo` enum('publica','interna') NOT NULL DEFAULT 'publica',
  `mensaje` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `solicitud_comunicaciones`
--
DELIMITER $$
CREATE TRIGGER `trg_actividad_comunicacion_insert` AFTER INSERT ON `solicitud_comunicaciones` FOR EACH ROW BEGIN
    UPDATE tickets SET actualizado_en = NOW() WHERE id_ticket = NEW.id_ticket;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_historial`
--

CREATE TABLE `solicitud_historial` (
  `id_historial` bigint(20) UNSIGNED NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_ticket_etapa` bigint(20) UNSIGNED DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `detalle` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `solicitud_historial`
--
DELIMITER $$
CREATE TRIGGER `trg_actividad_historial_insert` AFTER INSERT ON `solicitud_historial` FOR EACH ROW BEGIN
    UPDATE tickets SET actualizado_en = NOW() WHERE id_ticket = NEW.id_ticket;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_resoluciones`
--

CREATE TABLE `solicitud_resoluciones` (
  `id_ticket` int(11) NOT NULL,
  `resolucion` text NOT NULL,
  `causa_raiz` text DEFAULT NULL,
  `id_resuelto_por` int(11) DEFAULT NULL,
  `resuelto_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_vinculos`
--

CREATE TABLE `solicitud_vinculos` (
  `id_vinculo` int(11) NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_ticket_vinculado` int(11) NOT NULL,
  `tipo_vinculo` varchar(50) NOT NULL DEFAULT 'relacionada',
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `soluciones_servicio`
--

CREATE TABLE `soluciones_servicio` (
  `id_solucion` int(10) UNSIGNED NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `soluciones_servicio`
--

INSERT INTO `soluciones_servicio` (`id_solucion`, `id_servicio`, `nombre`, `descripcion`, `estado`, `orden`, `creado_por`, `actualizado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 6, 'Reinicio computador', 'se reinicio el computador', 'activo', 1, 1, 1, '2026-08-04 10:24:02', '2026-08-04 15:24:02'),
(2, 3, 'Contrato firmado', 'Contrato firmado con exito', 'activo', 1, 1, 1, '2026-08-04 11:35:52', '2026-08-04 16:35:52'),
(3, 2, 'Impuestos liquidados', 'Se liquidaron los impuestos sin novedad', 'activo', 1, 1, 1, '2026-08-04 11:36:32', '2026-08-04 16:36:32'),
(4, 1, 'Compra de computador', 'La compra fue satisfactoria', 'activo', 1, 1, 1, '2026-08-04 11:39:48', '2026-08-04 16:39:48'),
(5, 7, 'Asignado', 'Computador asignado', 'activo', 1, 1, 1, '2026-08-04 11:41:56', '2026-08-04 16:41:56'),
(6, 9, 'no hubo solucion remota', 'no hubo solucion remota tocó ver el computador en fisico', 'activo', 1, 1, 1, '2026-08-05 15:23:35', '2026-08-05 20:23:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id_ticket` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text NOT NULL,
  `tipo_solicitud` enum('requerimiento','incidente') NOT NULL DEFAULT 'requerimiento',
  `estado` varchar(30) NOT NULL DEFAULT 'abierto',
  `urgencia` varchar(20) NOT NULL DEFAULT 'moderada',
  `prioridad` varchar(20) NOT NULL DEFAULT 'media',
  `id_usuario` int(11) DEFAULT NULL,
  `id_tecnico` int(11) DEFAULT NULL,
  `id_servicio` int(11) DEFAULT NULL,
  `id_proceso` int(11) DEFAULT NULL,
  `estado_flujo` varchar(30) NOT NULL DEFAULT 'sin_flujo',
  `id_etapa_actual` bigint(20) UNSIGNED DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_finalizacion` datetime DEFAULT NULL,
  `esperando_solicitante_desde` datetime DEFAULT NULL,
  `cierre_tipo` varchar(20) DEFAULT NULL,
  `motivo_cierre` varchar(255) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_etapas`
--

CREATE TABLE `ticket_etapas` (
  `id_ticket_etapa` bigint(20) UNSIGNED NOT NULL,
  `id_ticket_etapa_padre` bigint(20) UNSIGNED DEFAULT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_proceso_etapa` int(11) DEFAULT NULL,
  `nivel` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `orden` int(10) UNSIGNED NOT NULL,
  `id_catalogo` int(11) DEFAULT NULL,
  `catalogo_nombre` varchar(120) NOT NULL,
  `id_servicio` int(11) DEFAULT NULL,
  `servicio_nombre` varchar(160) NOT NULL,
  `id_gestor` int(11) DEFAULT NULL,
  `gestor_nombre` varchar(160) NOT NULL,
  `id_sla` int(11) DEFAULT NULL,
  `sla_nombre` varchar(120) NOT NULL,
  `sla_tiempo` int(10) UNSIGNED NOT NULL,
  `sla_unidad` enum('minutos','horas','dias') NOT NULL,
  `sla_minutos_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sla_minutos_consumidos` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('bloqueada','pendiente','en_proceso','en_espera_solicitante','pausada','listo_cierre','completada','cancelada') NOT NULL DEFAULT 'bloqueada',
  `fecha_activacion` datetime DEFAULT NULL,
  `fecha_vencimiento` datetime DEFAULT NULL,
  `fecha_ultima_reanudacion` datetime DEFAULT NULL,
  `fecha_pausa` datetime DEFAULT NULL,
  `fecha_marcado_listo` datetime DEFAULT NULL,
  `minutos_hasta_listo` int(10) UNSIGNED DEFAULT NULL,
  `resultado_sla_listo` enum('dentro_sla','fuera_sla') DEFAULT NULL,
  `marcado_listo_por` int(11) DEFAULT NULL,
  `fecha_ultima_reapertura` datetime DEFAULT NULL,
  `cantidad_reaperturas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `cantidad_pausas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `fecha_finalizacion` datetime DEFAULT NULL,
  `minutos_atencion` int(10) UNSIGNED DEFAULT NULL,
  `resultado_sla` enum('sin_iniciar','dentro_sla','fuera_sla') NOT NULL DEFAULT 'sin_iniciar',
  `id_solucion` int(10) UNSIGNED DEFAULT NULL,
  `solucion_nombre` varchar(180) DEFAULT NULL,
  `comentario_cierre` varchar(2000) DEFAULT NULL,
  `solicita_cierre_definitivo` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_derivacion` varchar(2000) DEFAULT NULL,
  `completado_por` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `ticket_etapas`
--
DELIMITER $$
CREATE TRIGGER `trg_actividad_etapa_insert` AFTER INSERT ON `ticket_etapas` FOR EACH ROW BEGIN
    UPDATE tickets SET actualizado_en = NOW() WHERE id_ticket = NEW.id_ticket;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_actividad_etapa_update` AFTER UPDATE ON `ticket_etapas` FOR EACH ROW BEGIN
    UPDATE tickets SET actualizado_en = NOW() WHERE id_ticket = NEW.id_ticket;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_etapa_checklist`
--

CREATE TABLE `ticket_etapa_checklist` (
  `id_ticket_checklist` bigint(20) UNSIGNED NOT NULL,
  `id_ticket_etapa` bigint(20) UNSIGNED NOT NULL,
  `id_checklist_plantilla` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` varchar(1000) DEFAULT NULL,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 1,
  `requiere_evidencia` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `observacion` varchar(1000) DEFAULT NULL,
  `evidencia_ruta` varchar(500) DEFAULT NULL,
  `completado_por` int(11) DEFAULT NULL,
  `completado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `ticket_etapa_checklist`
--
DELIMITER $$
CREATE TRIGGER `trg_actividad_checklist_insert` AFTER INSERT ON `ticket_etapa_checklist` FOR EACH ROW BEGIN
    UPDATE tickets AS t
    INNER JOIN ticket_etapas AS te ON te.id_ticket = t.id_ticket
    SET t.actualizado_en = NOW()
    WHERE te.id_ticket_etapa = NEW.id_ticket_etapa;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_actividad_checklist_update` AFTER UPDATE ON `ticket_etapa_checklist` FOR EACH ROW BEGIN
    UPDATE tickets AS t
    INNER JOIN ticket_etapas AS te ON te.id_ticket = t.id_ticket
    SET t.actualizado_en = NOW()
    WHERE te.id_ticket_etapa = NEW.id_ticket_etapa;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `cedula` varchar(30) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `proceso` varchar(120) NOT NULL,
  `cu1` varchar(120) NOT NULL,
  `cu3` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `descripcion_cu1` varchar(500) NOT NULL,
  `ciudad` varchar(120) NOT NULL,
  `empresa` varchar(160) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_pais_operacion` smallint(5) UNSIGNED DEFAULT NULL,
  `id_pais` int(11) DEFAULT NULL,
  `id_departamento` int(11) DEFAULT NULL,
  `id_ciudad` int(11) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `cedula`, `nombre`, `proceso`, `cu1`, `cu3`, `email`, `descripcion_cu1`, `ciudad`, `empresa`, `password`, `id_rol`, `id_pais_operacion`, `id_pais`, `id_departamento`, `id_ciudad`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 'ADMIN001', 'Administrador General', 'Administración', 'N/A', 'N/A', 'admin@gmail.com', 'Cuenta administradora inicial del sistema.', 'Bogotá', 'Conectar TV', '$2y$10$f.3F2LkJXmXsjG5liWUSBeZitvj8TuxpMIj2zahqdwbdk5hy49NCe', 1, NULL, NULL, NULL, NULL, 'activo', '2026-07-31 09:00:22', '2026-08-05 17:51:44'),
(2, '101010', 'Seguridad Integral', '0', 'IGAM', 'HNAL', 'jhonnytor@gmail.com', '.', 'Bogotá D.C.', 'millenium', '$2y$10$F8W20yxKzWWiTrA87yaPWOWrwyOKs1rgT1fVZUa1xkU0iyUBGTv4u', 2, 1, 1, 9, 2, 'activo', '2026-07-31 09:01:38', '2026-08-06 15:34:34'),
(4, '2020', 'Gestor2', '0', 'IGAM', 'HNAL', 'jhonnytorrez99@gmail.com', '0', 'Bogotá D.C.', 'millenium', '$2y$10$wsYV31LKg.AikWnlB2/Lk.xdpmGApV41fYkUANv2hb3H4gedAygmy', 2, 1, 1, 9, 2, 'activo', '2026-07-31 09:03:10', '2026-08-11 12:46:57'),
(7, '1010', 'Gestor2020', '0', '0', '0', 'jhonnytorrez@gmail.com', '0', '0', '0', '$2y$10$yrMlWRjg2PZVOqGPJq3tXe/e5IIuJ303KUrsaUiogyh898o/LerIa', 2, 2, NULL, NULL, NULL, 'activo', '2026-08-05 08:55:32', '2026-08-06 14:08:11'),
(8, '10101010', 'Jhonny', '0', '0', '0', 'jhonnytorre@gmail.com', '0', '0', '0', '$2y$10$a0zQqneJ/5bneFj00CHWK.FIxQa4tydeRkWykIfJt9ouh5spN5Erm', 3, 2, NULL, NULL, NULL, 'activo', '2026-08-05 08:56:53', '2026-08-06 14:08:13'),
(9, '00', 'solicitante', '0', '0', '0', 'solicitante@conectar.com', '0', '0', '0', '$2y$10$vf7UoRc5KBzKAi/HLS4yLuaHexUXbBDG16n6FWQZ8Gvqr5qj.0O76', 3, 1, NULL, NULL, NULL, 'activo', '2026-08-05 14:40:16', '2026-08-05 21:21:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para preferencias de correo por caso
--

CREATE TABLE `ticket_notificaciones_email_preferencias` (
  `id_preferencia` bigint(20) UNSIGNED NOT NULL,
  `id_ticket` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `habilitada` tinyint(1) NOT NULL DEFAULT 1,
  `creada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizada_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `catalogos`
--
ALTER TABLE `catalogos`
  ADD PRIMARY KEY (`id_catalogo`),
  ADD UNIQUE KEY `uq_catalogos_pais_nombre` (`id_pais_operacion`,`nombre`),
  ADD KEY `idx_catalogos_estado_orden` (`estado`,`orden`,`nombre`),
  ADD KEY `idx_catalogos_pais_estado_orden` (`id_pais_operacion`,`estado`,`orden`);

--
-- Indices de la tabla `configuraciones_servicio`
--
ALTER TABLE `configuraciones_servicio`
  ADD PRIMARY KEY (`id_opcion`),
  ADD UNIQUE KEY `uq_configuracion_pais_tipo_nombre` (`id_pais_operacion`,`tipo`,`nombre`),
  ADD KEY `idx_configuracion_tipo_estado` (`tipo`,`estado_registro`,`orden`,`nombre`),
  ADD KEY `idx_configuracion_padre` (`id_padre`),
  ADD KEY `idx_configuracion_pais_tipo_estado` (`id_pais_operacion`,`tipo`,`estado_registro`,`orden`);

--
-- Indices de la tabla `feriados`
--
ALTER TABLE `feriados`
  ADD PRIMARY KEY (`id_feriado`),
  ADD KEY `idx_feriados_estado_rango` (`estado`,`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_feriados_creado_por` (`id_creado_por`),
  ADD KEY `idx_feriados_pais_estado_fecha` (`id_pais_operacion`,`estado`,`fecha_inicio`,`fecha_fin`);

--
-- Indices de la tabla `historial_acciones`
--
ALTER TABLE `historial_acciones`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_historial_acciones_ticket` (`id_ticket`,`fecha_accion`),
  ADD KEY `idx_historial_acciones_usuario` (`id_usuario`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `idx_mensajes_ticket_fecha` (`id_ticket`,`fecha_envio`),
  ADD KEY `idx_mensajes_emisor` (`id_emisor`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_notificaciones_usuario` (`id_usuario`,`leida`,`creada_en`),
  ADD KEY `fk_notificaciones_ticket` (`id_ticket`),
  ADD KEY `fk_notificaciones_etapa` (`id_ticket_etapa`);

--
-- Indices de preferencias de correo por caso
--
ALTER TABLE `ticket_notificaciones_email_preferencias`
  ADD PRIMARY KEY (`id_preferencia`),
  ADD UNIQUE KEY `uq_preferencia_ticket_usuario` (`id_ticket`,`id_usuario`),
  ADD KEY `idx_preferencia_usuario` (`id_usuario`,`habilitada`);

--
-- Indices de la tabla `paises_operacion`
--
ALTER TABLE `paises_operacion`
  ADD PRIMARY KEY (`id_pais_operacion`),
  ADD UNIQUE KEY `uq_paises_operacion_codigo` (`codigo`),
  ADD UNIQUE KEY `uq_paises_operacion_nombre` (`nombre`);

--
-- Indices de la tabla `procesos`
--
ALTER TABLE `procesos`
  ADD PRIMARY KEY (`id_proceso`),
  ADD UNIQUE KEY `uq_procesos_pais_nombre` (`id_pais_operacion`,`nombre`),
  ADD KEY `idx_procesos_estado` (`estado`),
  ADD KEY `fk_procesos_creador` (`creado_por`),
  ADD KEY `fk_procesos_actualizador` (`actualizado_por`),
  ADD KEY `idx_procesos_pais_estado` (`id_pais_operacion`,`estado`,`nombre`);

--
-- Indices de la tabla `proceso_etapas`
--
ALTER TABLE `proceso_etapas`
  ADD PRIMARY KEY (`id_proceso_etapa`),
  ADD UNIQUE KEY `uq_proceso_orden` (`id_proceso`,`orden`),
  ADD KEY `idx_proceso_etapas_servicio` (`id_servicio`),
  ADD KEY `idx_proceso_etapas_gestor_sla` (`id_gestor`,`id_sla`),
  ADD KEY `idx_proceso_etapas_sla` (`id_sla`);

--
-- Indices de la tabla `proceso_etapa_checklist`
--
ALTER TABLE `proceso_etapa_checklist`
  ADD PRIMARY KEY (`id_checklist`),
  ADD KEY `idx_checklist_etapa` (`id_proceso_etapa`,`estado`,`orden`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `uq_roles_nombre` (`nombre_rol`);

--
-- Indices de la tabla `seguridad_intentos_login`
--
ALTER TABLE `seguridad_intentos_login`
  ADD PRIMARY KEY (`clave`),
  ADD KEY `idx_seguridad_bloqueo` (`bloqueado_hasta`),
  ADD KEY `idx_seguridad_ultimo_intento` (`ultimo_intento`);

--
-- Indices de la tabla `recuperaciones_password`
--
ALTER TABLE `recuperaciones_password`
  ADD PRIMARY KEY (`id_recuperacion`),
  ADD KEY `idx_recuperacion_usuario_activa` (`id_usuario`,`usado_en`,`expira_en`),
  ADD KEY `idx_recuperacion_ip_fecha` (`solicitado_ip_hash`,`creado_en`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`),
  ADD UNIQUE KEY `uq_servicio_catalogo_nombre` (`id_catalogo`,`nombre`),
  ADD KEY `idx_servicios_catalogo_estado` (`id_catalogo`,`estado`,`nombre`),
  ADD KEY `idx_servicios_sla` (`id_sla`),
  ADD KEY `idx_servicios_pais` (`id_pais`),
  ADD KEY `idx_servicios_ciudad` (`id_ciudad`),
  ADD KEY `idx_servicios_departamento` (`id_departamento`),
  ADD KEY `idx_servicios_prioridad` (`id_prioridad`),
  ADD KEY `idx_servicios_urgencia` (`id_urgencia`),
  ADD KEY `idx_servicios_nivel` (`id_nivel`),
  ADD KEY `idx_servicios_impacto` (`id_impacto`),
  ADD KEY `idx_servicios_estado_opcion` (`id_estado`),
  ADD KEY `idx_servicios_gestor` (`id_gestor`),
  ADD KEY `idx_servicios_pais_estado_catalogo` (`id_pais_operacion`,`estado`,`id_catalogo`),
  ADD KEY `idx_servicios_tipo_solicitud` (`tipo_solicitud`);

--
-- Indices de la tabla `sla`
--
ALTER TABLE `sla`
  ADD PRIMARY KEY (`id_sla`),
  ADD UNIQUE KEY `uq_sla_pais_nombre` (`id_pais_operacion`,`nombre`),
  ADD KEY `idx_sla_estado_tiempo` (`estado`,`tiempo_respuesta`,`nombre`),
  ADD KEY `idx_sla_pais_estado` (`id_pais_operacion`,`estado`,`nombre`);

--
-- Indices de la tabla `solicitud_actividades`
--
ALTER TABLE `solicitud_actividades`
  ADD PRIMARY KEY (`id_actividad`),
  ADD KEY `idx_actividades_ticket` (`id_ticket`,`estado`,`fecha_programada`),
  ADD KEY `idx_actividades_responsable` (`id_responsable`),
  ADD KEY `idx_actividades_creador` (`creado_por`);

--
-- Indices de la tabla `solicitud_adjuntos`
--
ALTER TABLE `solicitud_adjuntos`
  ADD PRIMARY KEY (`id_adjunto`),
  ADD KEY `idx_adjuntos_ticket` (`id_ticket`,`creado_en`),
  ADD KEY `idx_adjuntos_usuario` (`id_usuario`),
  ADD KEY `idx_adjuntos_etapa` (`id_ticket_etapa`);

--
-- Indices de la tabla `solicitud_calificaciones`
--
ALTER TABLE `solicitud_calificaciones`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `uq_calificacion_etapa` (`id_ticket`,`id_ticket_etapa`),
  ADD KEY `idx_calificacion_gestor` (`id_gestor`,`creado_en`),
  ADD KEY `idx_calificacion_solicitante` (`id_solicitante`),
  ADD KEY `idx_calificaciones_etapa` (`id_ticket_etapa`),
  ADD KEY `idx_calificaciones_ticket_fk` (`id_ticket`),
  ADD KEY `idx_calificaciones_tipo_fecha` (`tipo_calificacion`,`creado_en`);

--
-- Indices de la tabla `solicitud_comunicaciones`
--
ALTER TABLE `solicitud_comunicaciones`
  ADD PRIMARY KEY (`id_comunicacion`),
  ADD KEY `idx_comunicaciones_ticket_tipo` (`id_ticket`,`tipo`,`creado_en`),
  ADD KEY `idx_comunicaciones_emisor` (`id_emisor`),
  ADD KEY `idx_comunicaciones_etapa` (`id_ticket_etapa`);

--
-- Indices de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_historial_ticket` (`id_ticket`,`creado_en`),
  ADD KEY `idx_historial_usuario` (`id_usuario`),
  ADD KEY `idx_historial_caso_fecha` (`id_ticket`,`id_ticket_etapa`,`creado_en`),
  ADD KEY `fk_historial_ticket_etapa` (`id_ticket_etapa`);

--
-- Indices de la tabla `solicitud_resoluciones`
--
ALTER TABLE `solicitud_resoluciones`
  ADD PRIMARY KEY (`id_ticket`),
  ADD KEY `idx_resolucion_usuario` (`id_resuelto_por`);

--
-- Indices de la tabla `solicitud_vinculos`
--
ALTER TABLE `solicitud_vinculos`
  ADD PRIMARY KEY (`id_vinculo`),
  ADD UNIQUE KEY `uq_solicitud_vinculo` (`id_ticket`,`id_ticket_vinculado`),
  ADD KEY `idx_vinculo_ticket_relacionado` (`id_ticket_vinculado`),
  ADD KEY `idx_vinculo_creador` (`creado_por`);

--
-- Indices de la tabla `soluciones_servicio`
--
ALTER TABLE `soluciones_servicio`
  ADD PRIMARY KEY (`id_solucion`),
  ADD KEY `idx_soluciones_servicio_estado` (`id_servicio`,`estado`,`orden`),
  ADD KEY `idx_soluciones_creado_por` (`creado_por`),
  ADD KEY `idx_soluciones_actualizado_por` (`actualizado_por`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id_ticket`),
  ADD KEY `idx_tickets_solicitante_estado` (`id_usuario`,`estado`,`fecha_creacion`),
  ADD KEY `idx_tickets_tecnico_estado` (`id_tecnico`,`estado`,`fecha_creacion`),
  ADD KEY `idx_tickets_servicio` (`id_servicio`),
  ADD KEY `idx_tickets_espera` (`estado`,`esperando_solicitante_desde`),
  ADD KEY `idx_tickets_proceso` (`id_proceso`),
  ADD KEY `idx_tickets_estado_flujo` (`estado_flujo`),
  ADD KEY `idx_tickets_etapa_actual` (`id_etapa_actual`),
  ADD KEY `idx_tickets_pais_estado_fecha` (`id_pais_operacion`,`estado_flujo`,`fecha_creacion`),
  ADD KEY `idx_tickets_tipo_solicitud` (`tipo_solicitud`);

--
-- Indices de la tabla `ticket_etapas`
--
ALTER TABLE `ticket_etapas`
  ADD PRIMARY KEY (`id_ticket_etapa`),
  ADD UNIQUE KEY `uq_ticket_etapa_orden` (`id_ticket`,`orden`),
  ADD KEY `idx_ticket_etapas_gestor` (`id_gestor`,`estado`),
  ADD KEY `idx_ticket_etapas_ticket_estado` (`id_ticket`,`estado`),
  ADD KEY `idx_ticket_etapas_plantilla` (`id_proceso_etapa`),
  ADD KEY `fk_ticket_etapas_catalogo` (`id_catalogo`),
  ADD KEY `fk_ticket_etapas_servicio` (`id_servicio`),
  ADD KEY `fk_ticket_etapas_sla` (`id_sla`),
  ADD KEY `fk_ticket_etapas_completado` (`completado_por`),
  ADD KEY `idx_ticket_etapas_padre` (`id_ticket_etapa_padre`,`estado`),
  ADD KEY `idx_ticket_etapas_creador` (`creado_por`),
  ADD KEY `idx_ticket_etapas_activas` (`id_ticket`,`estado`,`id_gestor`),
  ADD KEY `idx_ticket_etapas_solucion` (`id_solucion`,`id_servicio`,`estado`),
  ADD KEY `idx_ticket_etapas_aprobacion` (`estado`,`creado_por`,`id_gestor`,`fecha_marcado_listo`);

--
-- Indices de la tabla `ticket_etapa_checklist`
--
ALTER TABLE `ticket_etapa_checklist`
  ADD PRIMARY KEY (`id_ticket_checklist`),
  ADD KEY `idx_ticket_checklist_etapa` (`id_ticket_etapa`,`orden`),
  ADD KEY `fk_ticket_checklist_plantilla` (`id_checklist_plantilla`),
  ADD KEY `fk_ticket_checklist_usuario` (`completado_por`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuarios_cedula` (`cedula`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_rol_estado` (`id_rol`,`estado`,`nombre`),
  ADD KEY `idx_usuarios_pais_rol_estado` (`id_pais_operacion`,`id_rol`,`estado`),
  ADD KEY `idx_usuarios_pais` (`id_pais`),
  ADD KEY `idx_usuarios_departamento` (`id_departamento`),
  ADD KEY `idx_usuarios_ciudad` (`id_ciudad`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `catalogos`
--
ALTER TABLE `catalogos`
  MODIFY `id_catalogo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `configuraciones_servicio`
--
ALTER TABLE `configuraciones_servicio`
  MODIFY `id_opcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `feriados`
--
ALTER TABLE `feriados`
  MODIFY `id_feriado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `historial_acciones`
--
ALTER TABLE `historial_acciones`
  MODIFY `id_historial` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id_mensaje` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de preferencias de correo por caso
--
ALTER TABLE `ticket_notificaciones_email_preferencias`
  MODIFY `id_preferencia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `procesos`
--
ALTER TABLE `procesos`
  MODIFY `id_proceso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `proceso_etapas`
--
ALTER TABLE `proceso_etapas`
  MODIFY `id_proceso_etapa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `proceso_etapa_checklist`
--
ALTER TABLE `proceso_etapa_checklist`
  MODIFY `id_checklist` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `recuperaciones_password`
--
ALTER TABLE `recuperaciones_password`
  MODIFY `id_recuperacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `sla`
--
ALTER TABLE `sla`
  MODIFY `id_sla` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `solicitud_actividades`
--
ALTER TABLE `solicitud_actividades`
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_adjuntos`
--
ALTER TABLE `solicitud_adjuntos`
  MODIFY `id_adjunto` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_calificaciones`
--
ALTER TABLE `solicitud_calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_comunicaciones`
--
ALTER TABLE `solicitud_comunicaciones`
  MODIFY `id_comunicacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  MODIFY `id_historial` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_vinculos`
--
ALTER TABLE `solicitud_vinculos`
  MODIFY `id_vinculo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `soluciones_servicio`
--
ALTER TABLE `soluciones_servicio`
  MODIFY `id_solucion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id_ticket` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ticket_etapas`
--
ALTER TABLE `ticket_etapas`
  MODIFY `id_ticket_etapa` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ticket_etapa_checklist`
--
ALTER TABLE `ticket_etapa_checklist`
  MODIFY `id_ticket_checklist` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `catalogos`
--
ALTER TABLE `catalogos`
  ADD CONSTRAINT `fk_catalogos_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `configuraciones_servicio`
--
ALTER TABLE `configuraciones_servicio`
  ADD CONSTRAINT `fk_configuracion_padre` FOREIGN KEY (`id_padre`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_configuracion_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `feriados`
--
ALTER TABLE `feriados`
  ADD CONSTRAINT `fk_feriados_creado_por` FOREIGN KEY (`id_creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feriados_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_acciones`
--
ALTER TABLE `historial_acciones`
  ADD CONSTRAINT `fk_historial_acciones_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_acciones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `fk_mensajes_emisor` FOREIGN KEY (`id_emisor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mensajes_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notificaciones_etapa` FOREIGN KEY (`id_ticket_etapa`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notificaciones_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para preferencias de correo por caso
--
ALTER TABLE `ticket_notificaciones_email_preferencias`
  ADD CONSTRAINT `fk_preferencia_email_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_preferencia_email_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `procesos`
--
ALTER TABLE `procesos`
  ADD CONSTRAINT `fk_procesos_actualizador` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_procesos_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_procesos_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `proceso_etapas`
--
ALTER TABLE `proceso_etapas`
  ADD CONSTRAINT `fk_proceso_etapas_gestor` FOREIGN KEY (`id_gestor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_proceso_etapas_proceso` FOREIGN KEY (`id_proceso`) REFERENCES `procesos` (`id_proceso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_proceso_etapas_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_proceso_etapas_sla` FOREIGN KEY (`id_sla`) REFERENCES `sla` (`id_sla`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `proceso_etapa_checklist`
--
ALTER TABLE `proceso_etapa_checklist`
  ADD CONSTRAINT `fk_checklist_etapa` FOREIGN KEY (`id_proceso_etapa`) REFERENCES `proceso_etapas` (`id_proceso_etapa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `recuperaciones_password`
--
ALTER TABLE `recuperaciones_password`
  ADD CONSTRAINT `fk_recuperacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `fk_servicios_catalogo` FOREIGN KEY (`id_catalogo`) REFERENCES `catalogos` (`id_catalogo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_ciudad` FOREIGN KEY (`id_ciudad`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_estado_opcion` FOREIGN KEY (`id_estado`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_gestor` FOREIGN KEY (`id_gestor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_impacto` FOREIGN KEY (`id_impacto`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_nivel` FOREIGN KEY (`id_nivel`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_pais` FOREIGN KEY (`id_pais`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_prioridad` FOREIGN KEY (`id_prioridad`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_sla` FOREIGN KEY (`id_sla`) REFERENCES `sla` (`id_sla`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_urgencia` FOREIGN KEY (`id_urgencia`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `sla`
--
ALTER TABLE `sla`
  ADD CONSTRAINT `fk_sla_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_actividades`
--
ALTER TABLE `solicitud_actividades`
  ADD CONSTRAINT `fk_solicitud_actividad_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_actividad_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_actividad_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_adjuntos`
--
ALTER TABLE `solicitud_adjuntos`
  ADD CONSTRAINT `fk_adjuntos_etapa` FOREIGN KEY (`id_ticket_etapa`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_adjunto_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_adjunto_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_calificaciones`
--
ALTER TABLE `solicitud_calificaciones`
  ADD CONSTRAINT `fk_calificacion_gestor` FOREIGN KEY (`id_gestor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calificacion_solicitante` FOREIGN KEY (`id_solicitante`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calificacion_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calificaciones_etapa` FOREIGN KEY (`id_ticket_etapa`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_comunicaciones`
--
ALTER TABLE `solicitud_comunicaciones`
  ADD CONSTRAINT `fk_comunicaciones_etapa` FOREIGN KEY (`id_ticket_etapa`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_comunicacion_emisor` FOREIGN KEY (`id_emisor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_comunicacion_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  ADD CONSTRAINT `fk_historial_ticket_etapa` FOREIGN KEY (`id_ticket_etapa`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_historial_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_historial_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_resoluciones`
--
ALTER TABLE `solicitud_resoluciones`
  ADD CONSTRAINT `fk_solicitud_resolucion_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_resolucion_usuario` FOREIGN KEY (`id_resuelto_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_vinculos`
--
ALTER TABLE `solicitud_vinculos`
  ADD CONSTRAINT `fk_solicitud_vinculo_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_vinculo_destino` FOREIGN KEY (`id_ticket_vinculado`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitud_vinculo_origen` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `soluciones_servicio`
--
ALTER TABLE `soluciones_servicio`
  ADD CONSTRAINT `fk_soluciones_actualizado_por` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_soluciones_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_soluciones_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_etapa_actual` FOREIGN KEY (`id_etapa_actual`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_proceso` FOREIGN KEY (`id_proceso`) REFERENCES `procesos` (`id_proceso`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_solicitante` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `ticket_etapas`
--
ALTER TABLE `ticket_etapas`
  ADD CONSTRAINT `fk_ticket_etapas_catalogo` FOREIGN KEY (`id_catalogo`) REFERENCES `catalogos` (`id_catalogo`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_completado` FOREIGN KEY (`completado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_gestor` FOREIGN KEY (`id_gestor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_padre` FOREIGN KEY (`id_ticket_etapa_padre`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_plantilla` FOREIGN KEY (`id_proceso_etapa`) REFERENCES `proceso_etapas` (`id_proceso_etapa`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_sla` FOREIGN KEY (`id_sla`) REFERENCES `sla` (`id_sla`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_etapas_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `ticket_etapa_checklist`
--
ALTER TABLE `ticket_etapa_checklist`
  ADD CONSTRAINT `fk_ticket_checklist_etapa` FOREIGN KEY (`id_ticket_etapa`) REFERENCES `ticket_etapas` (`id_ticket_etapa`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_checklist_plantilla` FOREIGN KEY (`id_checklist_plantilla`) REFERENCES `proceso_etapa_checklist` (`id_checklist`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_checklist_usuario` FOREIGN KEY (`completado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_ciudad` FOREIGN KEY (`id_ciudad`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_pais` FOREIGN KEY (`id_pais`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
