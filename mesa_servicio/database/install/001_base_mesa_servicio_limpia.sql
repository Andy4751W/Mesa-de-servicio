-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-08-2026 a las 19:48:34
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
CREATE DATABASE IF NOT EXISTS `mesa_servicio` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mesa_servicio`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogos`
--

CREATE TABLE `catalogos` (
  `id_catalogo` int(11) NOT NULL,
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

INSERT INTO `catalogos` (`id_catalogo`, `nombre`, `descripcion`, `imagen`, `estado`, `orden`, `creado_en`, `actualizado_en`) VALUES
(1, 'Adquisiciones', 'Solicitudes de compras y adquisiciones.', 'uploads/catalogo_464e3d921b3dcaab.webp', 'activo', 3, '2026-07-31 09:00:22', '2026-08-03 16:54:16'),
(2, 'Contabilidad', 'Servicios contables, tributarios y financieros.', 'uploads/catalogo_970357f00a43dc74.jpg', 'activo', 2, '2026-07-31 09:00:22', '2026-08-03 16:54:16'),
(3, 'Jurídica', 'Consultas y solicitudes jurídicas.', 'uploads/catalogo_67a694e3abb98f60.webp', 'activo', 1, '2026-07-31 09:00:22', '2026-08-03 16:54:16'),
(4, 'Seguridad Integral', 'Servicios de seguridad y gestión de riesgos.', 'uploads/catalogo_d4a0a7c721a2ac5e.png', 'activo', 4, '2026-07-31 09:00:22', '2026-07-31 14:10:05'),
(5, 'Talento Humano', 'Servicios relacionados con los colaboradores.', 'uploads/catalogo_f0d10ff0f4568d10.png', 'activo', 6, '2026-07-31 09:00:22', '2026-08-03 17:02:04'),
(6, 'TICs', 'Soporte tecnológico y sistemas de información.', 'uploads/catalogo_9a16b4f1330fbb56.webp', 'activo', 5, '2026-07-31 09:00:22', '2026-08-03 17:02:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones_servicio`
--

CREATE TABLE `configuraciones_servicio` (
  `id_opcion` int(11) NOT NULL,
  `tipo` enum('pais','ciudad','lugar','departamento','prioridad','urgencia','nivel','impacto','estado') NOT NULL,
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

INSERT INTO `configuraciones_servicio` (`id_opcion`, `tipo`, `id_padre`, `nombre`, `descripcion`, `color`, `estado_registro`, `orden`, `creado_en`, `actualizado_en`) VALUES
(1, 'pais', NULL, 'Colombia', 'País de operación.', '#7c3aed', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(2, 'ciudad', 1, 'Bogotá', 'Ciudad principal de operación.', '#8b5cf6', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(3, 'lugar', 2, 'kennedy', 'Sede principal de la empresa.', '#9333ea', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:16:13'),
(9, 'departamento', 3, 'cundinamarca', 'n', '#0f6fec', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:16:21'),
(10, 'prioridad', NULL, 'Baja', 'Prioridad de impacto reducido.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(11, 'prioridad', NULL, 'Media', 'Prioridad de atención regular.', '#eab308', 'activo', 2, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(12, 'prioridad', NULL, 'Alta', 'Prioridad que requiere pronta atención.', '#f97316', 'activo', 3, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(13, 'prioridad', NULL, 'Crítica', 'Prioridad con afectación crítica.', '#dc2626', 'activo', 4, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(14, 'urgencia', NULL, 'Baja', 'Puede atenderse dentro del tiempo normal.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(15, 'urgencia', NULL, 'Moderada', 'Requiere atención regular.', '#eab308', 'activo', 2, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(16, 'urgencia', NULL, 'Alta', 'Requiere atención prioritaria.', '#f97316', 'activo', 3, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(17, 'urgencia', NULL, 'Urgente', 'Requiere atención inmediata.', '#dc2626', 'activo', 4, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(18, 'nivel', NULL, 'Nivel 1', 'Atención básica o primer nivel.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(19, 'nivel', NULL, 'Nivel 2', 'Atención especializada o segundo nivel.', '#3b82f6', 'activo', 2, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(20, 'nivel', NULL, 'Nivel 3', 'Atención avanzada o tercer nivel.', '#8b5cf6', 'activo', 3, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(21, 'impacto', NULL, 'Usuario', 'Afecta a un usuario individual.', '#22c55e', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(22, 'impacto', NULL, 'Área', 'Afecta a un área o equipo de trabajo.', '#3b82f6', 'activo', 2, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(23, 'impacto', NULL, 'Empresa', 'Afecta a una empresa o filial.', '#f97316', 'activo', 3, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(24, 'impacto', NULL, 'Negocio', 'Afecta la continuidad del negocio.', '#dc2626', 'activo', 4, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(25, 'estado', NULL, 'Abierto', 'Solicitud registrada y pendiente.', '#16a34a', 'activo', 1, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(26, 'estado', NULL, 'En proceso', 'Solicitud actualmente en gestión.', '#2563eb', 'activo', 2, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(27, 'estado', NULL, 'En espera', 'Pendiente de información o validación.', '#ca8a04', 'activo', 3, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(28, 'estado', NULL, 'Resuelta', 'Solicitud solucionada.', '#64748b', 'activo', 4, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(29, 'estado', NULL, 'Cerrada', 'Solicitud finalizada y cerrada.', '#475569', 'activo', 5, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(30, 'estado', NULL, 'Cancelada', 'Solicitud cancelada.', '#dc2626', 'activo', 6, '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(31, 'pais', NULL, 'Alemania', '', '#ff0033', 'activo', 3, '2026-07-31 09:12:18', '2026-07-31 14:12:22'),
(32, 'ciudad', 31, 'Berlín', '', '#ff0000', 'activo', 3, '2026-07-31 09:12:52', '2026-07-31 14:12:56'),
(33, 'lugar', 32, 'Kerpen', '', '#ff0000', 'activo', 3, '2026-07-31 09:14:32', '2026-07-31 14:14:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `feriados`
--

CREATE TABLE `feriados` (
  `id_feriado` int(11) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `tipo` enum('dia_completo','rango_horario') NOT NULL DEFAULT 'dia_completo',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `id_creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

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

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id_notificacion`, `id_usuario`, `id_ticket`, `id_ticket_etapa`, `titulo`, `mensaje`, `leida`, `creada_en`, `leida_en`) VALUES
(1, 2, 10, 1, 'Nuevo ticket asignado', 'El ticket #10 está disponible para su gestión.', 1, '2026-08-03 09:50:59', '2026-08-03 09:53:57'),
(2, 2, 11, 2, 'Nuevo ticket asignado', 'El ticket #11 está disponible para su gestión.', 1, '2026-08-03 09:53:16', '2026-08-03 09:53:55'),
(3, 4, 10, NULL, 'Proceso finalizado', 'Todas las áreas terminaron el ticket #10. Califique cada etapa para cerrarlo.', 1, '2026-08-03 09:54:17', '2026-08-03 09:54:49'),
(4, 2, 11, 3, 'Ticket habilitado para su área', 'La etapa anterior del ticket #11 terminó. Ya puede atenderlo.', 1, '2026-08-03 09:55:55', '2026-08-03 09:55:55'),
(5, 2, 12, 4, 'Nuevo ticket asignado', 'El ticket #12 está disponible para su gestión.', 1, '2026-08-03 12:39:55', '2026-08-03 16:14:47'),
(6, 2, 12, 8, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso hijo #8 del ticket #12.', 1, '2026-08-03 16:52:23', '2026-08-03 16:52:23'),
(7, 4, 12, 8, 'Nueva dependencia en su ticket', 'Se creó el caso hijo #8 para Talento Humano / Ingreso.', 1, '2026-08-03 16:52:23', '2026-08-03 16:59:16'),
(8, 2, 12, 9, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso hijo #9 del ticket #12.', 1, '2026-08-03 16:58:17', '2026-08-03 16:58:17'),
(9, 4, 12, 9, 'Nueva dependencia en su ticket', 'Se creó el caso hijo #9 para Talento Humano / Novedades de nómina.', 1, '2026-08-03 16:58:17', '2026-08-03 16:59:16'),
(10, 2, 12, 8, 'Caso padre reanudado', 'Todos los hijos del ticket #12 finalizaron. Su SLA volvió a correr.', 1, '2026-08-04 07:39:16', '2026-08-04 07:39:17'),
(11, 2, 12, 4, 'Nuevo mensaje en el caso 4', 'Recibió un mensaje en el chat privado del caso 4.', 1, '2026-08-04 08:38:20', '2026-08-04 08:38:58'),
(12, 2, 12, 10, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso 4.1.2 del ticket #12.', 1, '2026-08-04 09:14:08', '2026-08-04 09:14:08'),
(13, 4, 12, 10, 'Nueva dependencia en su ticket', 'Se creó el caso hijo 4.1.2 para Jurídica / Revisión de contratos.', 1, '2026-08-04 09:14:08', '2026-08-04 09:57:15'),
(14, 2, 12, 11, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso 4.1.3 del ticket #12.', 1, '2026-08-04 09:14:08', '2026-08-04 09:14:08'),
(15, 4, 12, 11, 'Nueva dependencia en su ticket', 'Se creó el caso hijo 4.1.3 para TICs / asignacion de computador.', 1, '2026-08-04 09:14:08', '2026-08-04 09:57:15'),
(16, 2, 12, 12, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso 4.1.4 del ticket #12.', 1, '2026-08-04 09:14:08', '2026-08-04 09:14:08'),
(17, 4, 12, 12, 'Nueva dependencia en su ticket', 'Se creó el caso hijo 4.1.4 para TICs / Soporte técnico general.', 1, '2026-08-04 09:14:08', '2026-08-04 09:57:15'),
(18, 2, 12, 13, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso 4.1.5 del ticket #12.', 1, '2026-08-04 09:14:08', '2026-08-04 09:14:08'),
(19, 4, 12, 13, 'Nueva dependencia en su ticket', 'Se creó el caso hijo 4.1.5 para Talento Humano / Novedades de nómina.', 1, '2026-08-04 09:14:08', '2026-08-04 09:57:15'),
(20, 2, 12, 14, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso 4.1.6 del ticket #12.', 1, '2026-08-04 09:14:08', '2026-08-04 09:14:08'),
(21, 4, 12, 14, 'Nueva dependencia en su ticket', 'Se creó el caso hijo 4.1.6 para Talento Humano / Ingreso.', 1, '2026-08-04 09:14:08', '2026-08-04 09:57:15'),
(22, 2, 12, 8, 'Caso padre reanudado', 'Todos los hijos del ticket #12 finalizaron. Su SLA volvió a correr.', 1, '2026-08-04 09:59:23', '2026-08-04 09:59:24'),
(23, 2, 12, 4, 'Caso padre reanudado', 'Todos los hijos del ticket #12 finalizaron. Su SLA volvió a correr.', 1, '2026-08-04 09:59:38', '2026-08-04 09:59:38'),
(24, 2, 12, 5, 'Caso habilitado para su área', 'El caso anterior del ticket #12 terminó. Ya puede atenderlo.', 1, '2026-08-04 10:00:05', '2026-08-04 10:00:05'),
(25, 2, 12, 6, 'Caso habilitado para su área', 'El caso anterior del ticket #12 terminó. Ya puede atenderlo.', 1, '2026-08-04 10:00:17', '2026-08-04 10:00:17'),
(26, 2, 12, 7, 'Caso habilitado para su área', 'El caso anterior del ticket #12 terminó. Ya puede atenderlo.', 1, '2026-08-04 10:00:26', '2026-08-04 10:00:26'),
(27, 4, 12, NULL, 'Proceso finalizado', 'Todos los casos del ticket #12 terminaron. Califique las áreas para cerrarlo.', 1, '2026-08-04 10:00:34', '2026-08-04 10:25:08'),
(28, 2, 13, 15, 'Nuevo ticket asignado', 'El caso raíz 15 del ticket #13 está disponible para su gestión.', 1, '2026-08-04 10:01:29', '2026-08-04 10:01:29'),
(29, 2, 13, 16, 'Caso habilitado para su área', 'El caso anterior del ticket #13 terminó. Ya puede atenderlo.', 1, '2026-08-04 10:01:44', '2026-08-04 10:01:44'),
(30, 2, 13, NULL, 'Proceso finalizado', 'Todos los casos del ticket #13 terminaron. Califique las áreas para cerrarlo.', 1, '2026-08-04 10:01:53', '2026-08-04 10:01:53'),
(31, 4, 14, 17, 'Nuevo ticket asignado', 'El caso raíz 17 del ticket #14 está disponible para su gestión.', 1, '2026-08-04 10:02:03', '2026-08-04 10:23:07'),
(32, 4, 14, 17, 'Nuevo mensaje en el caso 17', 'Recibió un mensaje en el chat privado del caso 17.', 1, '2026-08-04 10:02:51', '2026-08-04 10:23:07'),
(33, 2, 15, 18, 'Nuevo ticket asignado', 'El caso raíz 18 del ticket #15 está disponible para su gestión.', 1, '2026-08-04 10:25:59', '2026-08-04 10:26:54'),
(34, 4, 15, 18, 'Caso listo para cerrar', 'El gestor asignado marcó el caso 18 como listo. Revise la solución y cierre o reabra la derivación.', 1, '2026-08-04 11:29:06', '2026-08-04 11:29:22'),
(35, 2, 15, 18, 'Caso aprobado y cerrado', 'El creador aprobó y calificó el caso 18. Gestión: 5/5; tiempo: 5/5.', 1, '2026-08-04 11:29:43', '2026-08-04 11:30:23'),
(36, 2, 15, 19, 'Caso habilitado para su área', 'El caso anterior del ticket #15 terminó. Ya puede atenderlo.', 1, '2026-08-04 11:29:43', '2026-08-04 11:30:23'),
(37, 4, 15, 20, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso 19.1 del ticket #15.', 1, '2026-08-04 11:30:53', '2026-08-04 11:31:56'),
(38, 4, 15, 21, 'Nuevo caso hijo asignado', 'Se derivó a su área el caso 19.2 del ticket #15.', 1, '2026-08-04 11:30:53', '2026-08-04 11:31:56'),
(39, 2, 15, 20, 'Caso listo para cerrar', 'El gestor asignado marcó el caso 19.1 como listo. Revise la solución y cierre o reabra la derivación.', 1, '2026-08-04 11:38:47', '2026-08-04 11:40:34'),
(40, 2, 15, 21, 'Caso listo para cerrar', 'El gestor asignado marcó el caso 19.2 como listo. Revise la solución y cierre o reabra la derivación.', 1, '2026-08-04 11:40:15', '2026-08-04 11:40:34'),
(41, 4, 15, 20, 'Caso aprobado y cerrado', 'El creador aprobó y calificó el caso 19.1. Gestión: 5/5; tiempo: 5/5.', 1, '2026-08-04 11:40:48', '2026-08-04 11:45:25'),
(42, 4, 15, 21, 'Caso aprobado y cerrado', 'El creador aprobó y calificó el caso 19.2. Gestión: 5/5; tiempo: 5/5.', 1, '2026-08-04 11:41:01', '2026-08-04 11:45:25'),
(43, 2, 15, 19, 'Caso padre reanudado', 'Todos los hijos del ticket #15 finalizaron. Su SLA volvió a correr.', 1, '2026-08-04 11:41:01', '2026-08-04 11:41:01'),
(44, 4, 15, 19, 'Caso listo para cerrar', 'El gestor asignado marcó el caso 19 como listo. Revise la solución y cierre o reabra la derivación.', 1, '2026-08-04 11:43:40', '2026-08-04 11:45:25'),
(45, 2, 15, 19, 'Caso aprobado y cerrado', 'El creador aprobó y calificó el caso 19. Gestión: 5/5; tiempo: 4/5.', 0, '2026-08-04 11:45:41', NULL),
(46, 4, 15, NULL, 'Ticket cerrado definitivamente', 'Todos los casos del ticket #15 fueron aprobados y calificados. El ticket quedó cerrado.', 1, '2026-08-04 11:45:41', '2026-08-04 11:45:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procesos`
--

CREATE TABLE `procesos` (
  `id_proceso` int(11) NOT NULL,
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

INSERT INTO `procesos` (`id_proceso`, `nombre`, `descripcion`, `estado`, `creado_por`, `actualizado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 'Ingreso', 'Ingreso', 'activo', 1, 1, '2026-08-03 09:39:57', '2026-08-03 14:39:57'),
(2, 'asignacion de computador', 'diligenciado', 'activo', 1, 1, '2026-08-03 11:52:26', '2026-08-03 17:15:33'),
(3, 'Jurídica · Revisión de contratos', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-03 17:30:34'),
(4, 'Contabilidad · Declaración de impuestos', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-03 17:30:34'),
(5, 'Adquisiciones · Solicitud de compra', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-03 17:30:34'),
(6, 'Seguridad Integral · Reporte de incidente de seguridad', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-03 17:30:34'),
(7, 'TICs · asignacion de computador', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:30:34', '2026-08-03 17:30:34'),
(8, 'Talento Humano · Ingreso', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-03 12:37:50', '2026-08-03 17:37:50');

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
(15, 8, 3, 2, 3, 2, 'contratación', NULL, 0, 'activo', '2026-08-03 12:39:13', '2026-08-03 21:51:44');

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
(2, 2, 'Documentos diligenciados', NULL, 1, 0, 1, 'activo', '2026-08-03 09:52:03', '2026-08-03 14:52:03');

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
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id_servicio` int(11) NOT NULL,
  `id_catalogo` int(11) NOT NULL,
  `id_sla` int(11) NOT NULL,
  `id_gestor` int(11) DEFAULT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `id_pais` int(11) DEFAULT NULL,
  `id_ciudad` int(11) DEFAULT NULL,
  `id_lugar` int(11) DEFAULT NULL,
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

INSERT INTO `servicios` (`id_servicio`, `id_catalogo`, `id_sla`, `id_gestor`, `nombre`, `descripcion`, `estado`, `id_pais`, `id_ciudad`, `id_lugar`, `id_departamento`, `id_prioridad`, `id_urgencia`, `id_nivel`, `id_impacto`, `id_estado`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 2, 4, 'Solicitud de compra', 'Registro de requerimientos de compra.', 'activo', 1, 2, 3, 9, 11, 15, 18, 21, 25, '2026-07-31 09:00:22', '2026-08-03 21:58:57'),
(2, 2, 5, 4, 'Declaración de impuestos', 'Gestión tributaria y declaración de impuestos.', 'activo', 1, 2, 3, NULL, 11, 15, 18, 22, 25, '2026-07-31 09:00:22', '2026-08-04 14:38:18'),
(3, 3, 3, 2, 'Revisión de contratos', 'Revisión y análisis de documentos contractuales.', 'activo', 1, 2, 3, NULL, 11, 15, 18, 22, 25, '2026-07-31 09:00:22', '2026-08-03 15:28:02'),
(4, 4, 2, 2, 'Reporte de incidente de seguridad', 'Atención de novedades de seguridad integral.', 'activo', 1, 2, 3, NULL, 12, 16, 18, 23, 25, '2026-07-31 09:00:22', '2026-08-03 15:28:11'),
(5, 5, 4, 2, 'Novedades de nómina', 'Gestión de novedades relacionadas con nómina.', 'activo', 1, 2, 3, NULL, 11, 15, 18, 21, 25, '2026-07-31 09:00:22', '2026-08-03 14:40:44'),
(6, 6, 2, 2, 'Soporte técnico general', 'Atención de incidentes y requerimientos de TICs.', 'activo', 1, 2, 3, NULL, 12, 16, 18, 22, 25, '2026-07-31 09:00:22', '2026-08-03 15:28:15'),
(7, 6, 4, 2, 'asignacion de computador', 'na', 'activo', 1, 2, 3, 9, 11, 14, 18, 21, 25, '2026-08-03 09:41:53', '2026-08-03 14:43:35'),
(8, 5, 3, 2, 'Ingreso', 'Ingreso del colaborador a la empresa', 'activo', 1, 2, 3, 9, 11, 15, 18, 23, 25, '2026-08-03 12:37:47', '2026-08-03 17:37:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sla`
--

CREATE TABLE `sla` (
  `id_sla` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `tiempo_respuesta` int(10) UNSIGNED NOT NULL,
  `unidad` enum('minutos','horas','dias') NOT NULL DEFAULT 'dias',
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `sla`
--

INSERT INTO `sla` (`id_sla`, `nombre`, `tiempo_respuesta`, `unidad`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 'SLA inicial - 1 día', 1, 'dias', 'activo', '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(2, 'SLA prioritario - 4 horas', 4, 'horas', 'activo', '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(3, 'SLA estándar - 3 días', 3, 'dias', 'activo', '2026-07-31 09:00:22', '2026-07-31 14:00:22'),
(4, '4 días', 4, 'dias', 'activo', '2026-07-31 12:21:27', '2026-07-31 17:21:27'),
(5, '30 dias tics', 30, 'dias', 'activo', '2026-08-03 12:02:54', '2026-08-03 17:02:54');

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
-- Volcado de datos para la tabla `solicitud_adjuntos`
--

INSERT INTO `solicitud_adjuntos` (`id_adjunto`, `id_ticket`, `id_ticket_etapa`, `id_usuario`, `nombre_original`, `nombre_guardado`, `ruta`, `tipo_mime`, `tamano`, `creado_en`) VALUES
(1, 3, NULL, 4, 'Captura de pantalla 2026-07-27 160123.png', 'solicitud_3_69d1f92cdb71ce1ace692d7d.png', 'uploads/solicitudes/solicitud_3_69d1f92cdb71ce1ace692d7d.png', 'image/png', 27042, '2026-07-31 10:55:17');

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
) ;

--
-- Volcado de datos para la tabla `solicitud_calificaciones`
--

INSERT INTO `solicitud_calificaciones` (`id_calificacion`, `id_ticket`, `id_ticket_etapa`, `id_solicitante`, `id_gestor`, `calificacion`, `calificacion_area`, `calificacion_tiempo`, `tipo_calificacion`, `comentario`, `creado_en`) VALUES
(1, 1, NULL, 4, 2, 5, 5, 5, 'historica', 'nice', '2026-07-31 09:19:27'),
(2, 2, NULL, 4, 2, 5, 5, 5, 'historica', 'nice', '2026-07-31 09:31:51'),
(3, 3, NULL, 4, 2, 5, 5, 5, 'historica', 'na', '2026-07-31 10:58:42'),
(7, 10, 1, 4, 2, 5, 5, 5, 'encuesta_servicio', NULL, '2026-08-03 09:55:02'),
(8, 15, 18, 4, 2, 5, 5, 5, 'encuesta_servicio', 'OK', '2026-08-04 11:29:43'),
(9, 15, 20, 2, 4, 5, 5, 5, 'evaluacion_derivacion', 'ok', '2026-08-04 11:40:48'),
(10, 15, 21, 2, 4, 5, 5, 5, 'evaluacion_derivacion', 'ok', '2026-08-04 11:41:01'),
(11, 15, 19, 4, 2, 5, 5, 4, 'evaluacion_caso', 'ok', '2026-08-04 11:45:41');

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
-- Volcado de datos para la tabla `solicitud_comunicaciones`
--

INSERT INTO `solicitud_comunicaciones` (`id_comunicacion`, `id_ticket`, `id_ticket_etapa`, `id_emisor`, `tipo`, `mensaje`, `creado_en`) VALUES
(1, 1, NULL, 4, 'publica', 'holaaaaaaaaaaaaaaaaaaaaaaa', '2026-07-31 09:17:37'),
(2, 1, NULL, 2, 'publica', 'listo', '2026-07-31 09:18:34'),
(3, 1, NULL, 2, 'publica', 'El gestor marcó la solicitud como resuelta. Por favor califique la atención para cerrar el ticket.', '2026-07-31 09:18:39'),
(4, 2, NULL, 4, 'publica', 'necesito urgencia', '2026-07-31 09:29:47'),
(5, 2, NULL, 2, 'publica', 'hola ya lo solucione', '2026-07-31 09:30:55'),
(6, 2, NULL, 2, 'publica', 'El gestor marcó la solicitud como resuelta. Por favor califique la atención para cerrar el ticket.', '2026-07-31 09:31:06'),
(7, 3, NULL, 4, 'publica', 'hola si mira necesiro tu ayuda', '2026-07-31 10:55:02'),
(8, 3, NULL, 4, 'publica', 'Archivo(s) adjunto(s).', '2026-07-31 10:55:17'),
(9, 3, NULL, 2, 'publica', 'Hola ya te ayude', '2026-07-31 10:56:55'),
(10, 3, NULL, 2, 'publica', 'El gestor marcó la solicitud como resuelta. Por favor califique la atención para cerrar el ticket.', '2026-07-31 10:57:10'),
(14, 10, 1, 2, 'publica', 'hola', '2026-08-03 09:54:02'),
(15, 11, 2, 2, 'publica', 'hola', '2026-08-03 09:55:35'),
(16, 12, 8, 2, 'publica', 'hola', '2026-08-04 07:42:40'),
(17, 12, 4, 4, 'publica', 'hola ya?', '2026-08-04 08:38:20'),
(18, 14, 17, 2, 'publica', 'hola necesito 2 computadores', '2026-08-04 10:02:51');

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
-- Volcado de datos para la tabla `solicitud_historial`
--

INSERT INTO `solicitud_historial` (`id_historial`, `id_ticket`, `id_ticket_etapa`, `id_usuario`, `accion`, `detalle`, `creado_en`) VALUES
(1, 1, NULL, 4, 'Solicitud creada', 'El solicitante registró la solicitud.', '2026-07-31 09:17:28'),
(2, 1, NULL, 4, 'Comunicación del solicitante', 'Mensaje enviado desde el chat.', '2026-07-31 09:17:37'),
(3, 1, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 09:18:08'),
(4, 1, NULL, 2, 'Comunicación del gestor', 'Mensaje enviado al solicitante.', '2026-07-31 09:18:34'),
(5, 1, NULL, 2, 'Estado actualizado por el gestor', 'Nuevo estado: Resuelta; pendiente de calificación', '2026-07-31 09:18:39'),
(6, 1, NULL, 4, 'Calificación y cierre', 'El solicitante calificó la atención con 5 de 5 estrellas.', '2026-07-31 09:19:27'),
(7, 2, NULL, 4, 'Solicitud creada', 'El solicitante registró la solicitud.', '2026-07-31 09:29:33'),
(8, 2, NULL, 4, 'Comunicación del solicitante', 'Mensaje enviado desde el chat.', '2026-07-31 09:29:47'),
(9, 2, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 09:30:16'),
(10, 2, NULL, 2, 'Comunicación del gestor', 'Mensaje enviado al solicitante.', '2026-07-31 09:30:55'),
(11, 2, NULL, 2, 'Estado actualizado por el gestor', 'Nuevo estado: Resuelta; pendiente de calificación', '2026-07-31 09:31:06'),
(12, 2, NULL, 4, 'Calificación y cierre', 'El solicitante calificó la atención con 5 de 5 estrellas.', '2026-07-31 09:31:51'),
(13, 3, NULL, 4, 'Solicitud creada', 'El solicitante registró la solicitud.', '2026-07-31 10:54:21'),
(14, 3, NULL, 4, 'Comunicación del solicitante', 'Mensaje enviado desde el chat.', '2026-07-31 10:55:02'),
(15, 3, NULL, 4, 'Comunicación del solicitante', 'Mensaje enviado con 1 archivo(s).', '2026-07-31 10:55:17'),
(16, 3, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 10:56:11'),
(17, 3, NULL, 2, 'Comunicación del gestor', 'Mensaje enviado al solicitante.', '2026-07-31 10:56:55'),
(18, 3, NULL, 2, 'Estado actualizado por el gestor', 'Nuevo estado: Resuelta; pendiente de calificación', '2026-07-31 10:57:10'),
(19, 3, NULL, 4, 'Calificación y cierre', 'El solicitante calificó la atención con 5 de 5 estrellas.', '2026-07-31 10:58:42'),
(33, 8, NULL, NULL, 'Solicitud creada', 'El solicitante registró la solicitud.', '2026-07-31 11:33:38'),
(34, 8, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 12:07:40'),
(35, 8, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 12:08:05'),
(36, 8, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 12:23:14'),
(37, 8, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 12:30:02'),
(38, 9, NULL, 4, 'Solicitud creada', 'El solicitante registró la solicitud.', '2026-07-31 12:33:41'),
(39, 8, NULL, 1, 'Información actualizada', 'Se modificaron los datos principales de la solicitud.', '2026-07-31 12:44:19'),
(40, 2, NULL, 1, 'Solicitud vinculada', 'Se vinculó con la solicitud #1.', '2026-07-31 13:22:11'),
(41, 3, NULL, 1, 'Solicitud vinculada', 'Se vinculó con la solicitud #1.', '2026-07-31 13:22:30'),
(42, 10, 1, 4, 'Proceso iniciado', 'Se creó el ticket con un flujo secuencial de 1 etapa(s). La primera área habilitada es TICs.', '2026-08-03 09:50:59'),
(43, 11, NULL, 4, 'Proceso iniciado', 'Se creó el ticket con un flujo secuencial de 2 etapa(s). La primera área habilitada es Talento Humano.', '2026-08-03 09:53:16'),
(44, 10, 1, 2, 'Comunicación registrada', 'Se agregó un mensaje o archivo al chat unificado.', '2026-08-03 09:54:02'),
(45, 10, 1, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-03 09:54:10'),
(46, 10, 1, 2, 'Etapa completada', 'TICs / asignacion de computador finalizó su atención dentro del SLA.', '2026-08-03 09:54:17'),
(47, 10, 1, 2, 'Proceso finalizado', 'Todas las áreas completaron su atención. El ticket está pendiente de calificación.', '2026-08-03 09:54:17'),
(48, 10, 1, 4, 'Ticket cerrado', 'Cerrado automáticamente después de calificar todas las áreas.', '2026-08-03 09:55:02'),
(49, 11, NULL, 2, 'Comunicación registrada', 'Se agregó un mensaje o archivo al chat unificado.', '2026-08-03 09:55:35'),
(50, 11, NULL, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-03 09:55:39'),
(51, 11, NULL, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-03 09:55:46'),
(52, 11, NULL, 2, 'Etapa completada', 'Talento Humano / Novedades de nómina finalizó su atención dentro del SLA.', '2026-08-03 09:55:55'),
(53, 11, NULL, 2, 'Siguiente área habilitada', 'Se habilitó TICs / asignacion de computador. Las áreas posteriores permanecen bloqueadas.', '2026-08-03 09:55:55'),
(54, 12, NULL, 4, 'Proceso iniciado', 'Se creó el ticket con un flujo secuencial de 4 etapa(s). La primera área habilitada es Talento Humano.', '2026-08-03 12:39:54'),
(55, 12, NULL, 2, 'Caso hijo creado', 'Se abrió el caso hijo #8 desde el caso padre inmediato #4. Talento Humano / Ingreso pausó su SLA y derivó a Talento Humano / Ingreso. Motivo: necesito un computador', '2026-08-03 16:52:23'),
(56, 12, NULL, 2, 'Caso hijo creado', 'Se abrió el caso hijo #9 desde el caso padre inmediato #8. Talento Humano / Ingreso pausó su SLA y derivó a Talento Humano / Novedades de nómina. Motivo: b a', '2026-08-03 16:58:17'),
(57, 12, NULL, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-04 07:39:07'),
(58, 12, NULL, 2, 'Caso cerrado', 'Se cerró el caso #9, hijo de #8. Talento Humano / Novedades de nómina finalizó su atención dentro del SLA. Solución: Listo', '2026-08-04 07:39:16'),
(59, 12, NULL, 2, 'Caso padre reanudado', 'Todos los casos hijos finalizaron. Se reanudó Talento Humano / Ingreso con 1800 minuto(s) hábil(es) restantes.', '2026-08-04 07:39:16'),
(60, 12, NULL, 2, 'Comunicación registrada', 'Se agregó un mensaje o archivo al chat unificado.', '2026-08-04 07:42:40'),
(61, 12, NULL, 4, 'Comunicación registrada', 'Se agregó un mensaje o archivo al chat privado del caso 4.', '2026-08-04 08:38:20'),
(62, 12, 10, 2, 'Caso abierto por derivación', 'Se abrió el caso 4.1.2 desde el caso padre inmediato 4.1. Destino: Jurídica / Revisión de contratos. Motivo: na', '2026-08-04 09:14:08'),
(63, 12, 11, 2, 'Caso abierto por derivación', 'Se abrió el caso 4.1.3 desde el caso padre inmediato 4.1. Destino: TICs / asignacion de computador. Motivo: na', '2026-08-04 09:14:08'),
(64, 12, 12, 2, 'Caso abierto por derivación', 'Se abrió el caso 4.1.4 desde el caso padre inmediato 4.1. Destino: TICs / Soporte técnico general. Motivo: na', '2026-08-04 09:14:08'),
(65, 12, 13, 2, 'Caso abierto por derivación', 'Se abrió el caso 4.1.5 desde el caso padre inmediato 4.1. Destino: Talento Humano / Novedades de nómina. Motivo: na', '2026-08-04 09:14:08'),
(66, 12, 14, 2, 'Caso abierto por derivación', 'Se abrió el caso 4.1.6 desde el caso padre inmediato 4.1. Destino: Talento Humano / Ingreso. Motivo: na', '2026-08-04 09:14:08'),
(67, 12, 8, 2, 'Derivación múltiple creada', 'El caso 4.1 pausó su SLA y creó 5 caso(s) hijo(s): 4.1.2 (Jurídica / Revisión de contratos), 4.1.3 (TICs / asignacion de computador), 4.1.4 (TICs / Soporte técnico general), 4.1.5 (Talento Humano / Novedades de nómina), 4.1.6 (Talento Humano / Ingreso).', '2026-08-04 09:14:08'),
(68, 12, 14, 2, 'Caso cerrado', 'Se cerró el caso 4.1.6, hijo de 4.1. Talento Humano / Ingreso finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 09:58:15'),
(69, 12, 13, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-04 09:58:44'),
(70, 12, 13, 2, 'Caso cerrado', 'Se cerró el caso 4.1.5, hijo de 4.1. Talento Humano / Novedades de nómina finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 09:58:52'),
(71, 12, 12, 2, 'Caso cerrado', 'Se cerró el caso 4.1.4, hijo de 4.1. TICs / Soporte técnico general finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 09:59:04'),
(72, 12, 11, 2, 'Caso cerrado', 'Se cerró el caso 4.1.3, hijo de 4.1. TICs / asignacion de computador finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 09:59:14'),
(73, 12, 10, 2, 'Caso cerrado', 'Se cerró el caso 4.1.2, hijo de 4.1. Jurídica / Revisión de contratos finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 09:59:23'),
(74, 12, 8, 2, 'Caso padre reanudado', 'Todos los casos hijos finalizaron. Se reanudó Talento Humano / Ingreso con 1800 minuto(s) hábil(es) restantes.', '2026-08-04 09:59:23'),
(75, 12, 8, 2, 'Caso cerrado', 'Se cerró el caso 4.1, hijo de 4. Talento Humano / Ingreso finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 09:59:38'),
(76, 12, 4, 2, 'Caso padre reanudado', 'Todos los casos hijos finalizaron. Se reanudó Talento Humano / Ingreso con 1800 minuto(s) hábil(es) restantes.', '2026-08-04 09:59:38'),
(77, 12, 4, 2, 'Caso cerrado', 'Se cerró el caso 4, caso padre. Talento Humano / Ingreso finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 10:00:05'),
(78, 12, 5, 2, 'Caso heredado habilitado', 'Se habilitó Jurídica / Revisión de contratos.', '2026-08-04 10:00:05'),
(79, 12, 5, 2, 'Caso cerrado', 'Se cerró el caso 5, caso padre. Jurídica / Revisión de contratos finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 10:00:17'),
(80, 12, 6, 2, 'Caso heredado habilitado', 'Se habilitó Talento Humano / Novedades de nómina.', '2026-08-04 10:00:17'),
(81, 12, 6, 2, 'Caso cerrado', 'Se cerró el caso 6, caso padre. Talento Humano / Novedades de nómina finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 10:00:26'),
(82, 12, 7, 2, 'Caso heredado habilitado', 'Se habilitó TICs / asignacion de computador.', '2026-08-04 10:00:26'),
(83, 12, 7, 2, 'Caso cerrado', 'Se cerró el caso 7, caso padre. TICs / asignacion de computador finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 10:00:34'),
(84, 12, 7, 2, 'Proceso finalizado', 'El caso padre y todos sus descendientes finalizaron. El ticket está pendiente de calificación.', '2026-08-04 10:00:34'),
(85, 13, 15, 2, 'Caso padre abierto', 'Se creó el caso raíz 15 para Jurídica / Revisión de contratos. Asunto: ok. Solicitud: na', '2026-08-04 10:01:29'),
(86, 13, 15, 2, 'Caso cerrado', 'Se cerró el caso 15, caso padre. Jurídica / Revisión de contratos finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 10:01:44'),
(87, 13, 16, 2, 'Caso heredado habilitado', 'Se habilitó TICs / asignacion de computador.', '2026-08-04 10:01:44'),
(88, 13, 16, 2, 'Caso cerrado', 'Se cerró el caso 16, caso padre. TICs / asignacion de computador finalizó su atención dentro del SLA. Solución: ok', '2026-08-04 10:01:53'),
(89, 13, 16, 2, 'Proceso finalizado', 'El caso padre y todos sus descendientes finalizaron. El ticket está pendiente de calificación.', '2026-08-04 10:01:53'),
(90, 14, 17, 2, 'Caso padre abierto', 'Se creó el caso raíz 17 para Contabilidad / Declaración de impuestos. Asunto: ok. Solicitud: ok', '2026-08-04 10:02:03'),
(91, 14, 17, 2, 'Comunicación registrada', 'Se agregó un mensaje o archivo al chat privado del caso 17.', '2026-08-04 10:02:51'),
(92, 15, 18, 4, 'Caso padre abierto', 'Se creó el caso raíz 18 para TICs / Soporte técnico general. Asunto: Se me daño el pc. Solicitud: Se me daño el computador de tal manera', '2026-08-04 10:25:59'),
(93, 15, 18, 1, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-04 11:22:58'),
(94, 15, 18, 2, 'Caso marcado como listo', 'El gestor asignado marcó el caso 18 como listo para revisión. El indicador SLA quedó cortado en 63 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-04 14:25:59. Solución: Reinicio computador. Observación: nicA.', '2026-08-04 11:29:06'),
(95, 15, 18, 4, 'Caso cerrado', 'Se cerró el caso 18, caso padre. TICs / Soporte técnico general finalizó su atención dentro del SLA. Solución: Reinicio computador. Observación: nicA. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-04 11:29:43'),
(96, 15, 19, 4, 'Caso heredado habilitado', 'Se habilitó TICs / asignacion de computador.', '2026-08-04 11:29:43'),
(97, 15, 20, 2, 'Caso abierto por derivación', 'Se abrió el caso 19.1 desde el caso padre inmediato 19. Destino: Contabilidad / Declaración de impuestos. Motivo: na', '2026-08-04 11:30:53'),
(98, 15, 21, 2, 'Caso abierto por derivación', 'Se abrió el caso 19.2 desde el caso padre inmediato 19. Destino: Adquisiciones / Solicitud de compra. Motivo: na', '2026-08-04 11:30:53'),
(99, 15, 19, 2, 'Derivación múltiple creada', 'El caso 19 pausó su SLA y creó 2 caso(s) hijo(s): 19.1 (Contabilidad / Declaración de impuestos), 19.2 (Adquisiciones / Solicitud de compra).', '2026-08-04 11:30:53'),
(100, 15, 20, 4, 'Caso marcado como listo', 'El gestor asignado marcó el caso 19.1 como listo para revisión. El indicador SLA quedó cortado en 0 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-09-17 18:00:00. Solución: Impuestos liquidados. Observación: impuestos liquidados.', '2026-08-04 11:38:47'),
(101, 15, 21, 4, 'Caso marcado como listo', 'El gestor asignado marcó el caso 19.2 como listo para revisión. El indicador SLA quedó cortado en 9 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-04 15:30:53. Solución: Compra de computador. Observación: ok.', '2026-08-04 11:40:15'),
(102, 15, 20, 2, 'Caso cerrado', 'Se cerró el caso 19.1, hijo de 19. Contabilidad / Declaración de impuestos finalizó su atención dentro del SLA. Solución: Impuestos liquidados. Observación: impuestos liquidados. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-04 11:40:48'),
(103, 15, 21, 2, 'Caso cerrado', 'Se cerró el caso 19.2, hijo de 19. Adquisiciones / Solicitud de compra finalizó su atención dentro del SLA. Solución: Compra de computador. Observación: ok. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-04 11:41:01'),
(104, 15, 19, 2, 'Caso padre reanudado', 'Todos los casos hijos finalizaron. Se reanudó TICs / asignacion de computador con 2400 minuto(s) hábil(es) restantes.', '2026-08-04 11:41:01'),
(105, 15, 19, 2, 'Caso marcado como listo', 'El gestor asignado marcó el caso 19 como listo para revisión. El indicador SLA quedó cortado en 0 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-11 18:00:00. Solución: Asignado. Observación: asignado con exito.', '2026-08-04 11:43:40'),
(106, 15, 19, 4, 'Caso cerrado', 'Se cerró el caso 19, caso padre. TICs / asignacion de computador finalizó su atención dentro del SLA. Solución: Asignado. Observación: asignado con exito. Calificación: gestión 5/5 y tiempo 4/5.', '2026-08-04 11:45:41'),
(107, 15, 19, 4, 'Ticket cerrado definitivamente', 'Todos los casos fueron marcados como listos, aprobados y calificados por sus respectivos creadores. La encuesta principal corresponde únicamente al servicio solicitado.', '2026-08-04 11:45:41');

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
) ;

--
-- Volcado de datos para la tabla `solicitud_vinculos`
--

INSERT INTO `solicitud_vinculos` (`id_vinculo`, `id_ticket`, `id_ticket_vinculado`, `tipo_vinculo`, `creado_por`, `creado_en`) VALUES
(1, 2, 1, 'depende_de', 1, '2026-07-31 13:22:11'),
(2, 3, 1, 'duplicada', 1, '2026-07-31 13:22:30');

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
(5, 7, 'Asignado', 'Computador asignado', 'activo', 1, 1, 1, '2026-08-04 11:41:56', '2026-08-04 16:41:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id_ticket` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text NOT NULL,
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

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO `tickets` (`id_ticket`, `titulo`, `descripcion`, `estado`, `urgencia`, `prioridad`, `id_usuario`, `id_tecnico`, `id_servicio`, `id_proceso`, `estado_flujo`, `id_etapa_actual`, `fecha_creacion`, `fecha_finalizacion`, `esperando_solicitante_desde`, `cierre_tipo`, `motivo_cierre`, `actualizado_en`) VALUES
(1, 'Incidencia', 'hola', 'cerrada', 'urgente', 'alta', 4, 2, 1, NULL, 'sin_flujo', NULL, '2026-07-31 09:17:28', '2026-07-31 09:19:27', NULL, 'solicitante', 'Cerrado por el solicitante después de calificar la atención.', '2026-07-31 14:19:27'),
(2, 'No se declarar impuestos', 'ayuda', 'cerrada', 'urgente', 'alta', 4, 2, 2, NULL, 'sin_flujo', NULL, '2026-07-31 09:29:33', '2026-07-31 09:31:51', NULL, 'solicitante', 'Cerrado por el solicitante después de calificar la atención.', '2026-07-31 14:31:51'),
(3, 'necesito un abogado', 'na', 'cerrada', 'moderada', 'media', 4, 2, 2, NULL, 'sin_flujo', NULL, '2026-07-31 10:54:21', '2026-07-31 10:58:42', NULL, 'solicitante', 'Cerrado por el solicitante después de calificar la atención.', '2026-07-31 15:58:42'),
(8, 'na', 'na', 'abierto', 'moderada', 'media', NULL, 2, 3, NULL, 'sin_flujo', NULL, '2026-07-31 11:33:38', NULL, NULL, NULL, NULL, '2026-07-31 17:44:19'),
(9, 'na', 'ma', 'abierto', 'baja', 'baja', 4, NULL, 2, NULL, 'sin_flujo', NULL, '2026-07-31 12:33:41', NULL, NULL, NULL, NULL, '2026-07-31 17:33:41'),
(10, 'na', 'ingreso', 'cerrada', 'moderada', 'media', 4, 2, 7, 1, 'cerrado', NULL, '2026-08-03 09:50:59', '2026-08-03 09:55:02', NULL, 'solicitante', 'Cerrado automáticamente después de calificar todas las áreas.', '2026-08-03 14:55:02'),
(11, 'Incidencia', 'na', 'en_proceso', 'moderada', 'media', 4, 2, 7, 1, 'en_proceso', 3, '2026-08-03 09:53:16', NULL, NULL, NULL, NULL, '2026-08-03 14:55:55'),
(12, 'Ingreso', 'la', 'resuelta', 'baja', 'baja', 4, 2, 7, 8, 'pendiente_calificacion', NULL, '2026-08-03 12:39:54', NULL, '2026-08-04 10:00:34', NULL, NULL, '2026-08-04 15:00:34'),
(13, 'ok', 'na', 'resuelta', 'moderada', 'media', 2, 2, 7, 3, 'pendiente_calificacion', NULL, '2026-08-04 10:01:29', NULL, '2026-08-04 10:01:53', NULL, NULL, '2026-08-04 15:01:53'),
(14, 'ok', 'ok', 'abierto', 'moderada', 'media', 2, 4, 2, 4, 'en_proceso', 17, '2026-08-04 10:02:03', NULL, NULL, NULL, NULL, '2026-08-04 15:02:03'),
(15, 'Se me daño el pc', 'Se me daño el computador de tal manera', 'cerrada', 'alta', 'alta', 4, 2, 7, 2, 'cerrado', NULL, '2026-08-04 10:25:59', '2026-08-04 11:45:41', NULL, 'aprobacion_por_caso', 'Cerrado automáticamente después de que el creador aprobó y calificó cada caso.', '2026-08-04 16:45:41');

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
  `motivo_derivacion` varchar(2000) DEFAULT NULL,
  `completado_por` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket_etapas`
--

INSERT INTO `ticket_etapas` (`id_ticket_etapa`, `id_ticket_etapa_padre`, `id_ticket`, `id_proceso_etapa`, `nivel`, `orden`, `id_catalogo`, `catalogo_nombre`, `id_servicio`, `servicio_nombre`, `id_gestor`, `gestor_nombre`, `id_sla`, `sla_nombre`, `sla_tiempo`, `sla_unidad`, `sla_minutos_total`, `sla_minutos_consumidos`, `estado`, `fecha_activacion`, `fecha_vencimiento`, `fecha_ultima_reanudacion`, `fecha_pausa`, `fecha_marcado_listo`, `minutos_hasta_listo`, `resultado_sla_listo`, `marcado_listo_por`, `fecha_ultima_reapertura`, `cantidad_reaperturas`, `cantidad_pausas`, `fecha_finalizacion`, `minutos_atencion`, `resultado_sla`, `id_solucion`, `solucion_nombre`, `comentario_cierre`, `motivo_derivacion`, `completado_por`, `creado_por`, `creado_en`, `actualizado_en`) VALUES
(1, NULL, 10, 1, 0, 1, 6, 'TICs', 7, 'asignacion de computador', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-03 09:50:59', '2026-08-12 09:50:59', NULL, NULL, '2026-08-03 09:54:16', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-03 09:54:16', 0, 'dentro_sla', NULL, NULL, 'listo', NULL, 2, 4, '2026-08-03 09:50:59', '2026-08-04 16:27:49'),
(2, NULL, 11, 2, 0, 1, 5, 'Talento Humano', 5, 'Novedades de nómina', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-03 09:53:16', '2026-08-12 09:53:16', NULL, NULL, '2026-08-03 09:55:55', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-03 09:55:55', 0, 'dentro_sla', NULL, NULL, 'na', NULL, 2, 4, '2026-08-03 09:53:16', '2026-08-04 16:27:49'),
(3, NULL, 11, 1, 0, 2, 6, 'TICs', 7, 'asignacion de computador', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'pendiente', '2026-08-03 09:55:55', '2026-08-10 18:00:00', '2026-08-03 09:55:55', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 'sin_iniciar', NULL, NULL, NULL, NULL, NULL, 4, '2026-08-03 09:53:16', '2026-08-04 16:27:49'),
(4, NULL, 12, 12, 0, 1, 5, 'Talento Humano', 8, 'Ingreso', 2, 'Seguridad Integral', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-03 12:39:54', '2026-08-10 18:00:00', NULL, NULL, '2026-08-04 10:00:05', 0, 'dentro_sla', 2, NULL, 0, 1, '2026-08-04 10:00:05', 0, 'dentro_sla', NULL, NULL, 'ok', NULL, 2, 4, '2026-08-03 12:39:54', '2026-08-04 16:27:49'),
(5, NULL, 12, 15, 0, 2, 3, 'Jurídica', 3, 'Revisión de contratos', 2, 'Seguridad Integral', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-04 10:00:05', '2026-08-10 18:00:00', NULL, NULL, '2026-08-04 10:00:17', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 10:00:17', 0, 'dentro_sla', NULL, NULL, 'ok', NULL, 2, 4, '2026-08-03 12:39:54', '2026-08-04 16:27:49'),
(6, NULL, 12, 14, 0, 3, 5, 'Talento Humano', 5, 'Novedades de nómina', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-04 10:00:17', '2026-08-11 18:00:00', NULL, NULL, '2026-08-04 10:00:26', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 10:00:26', 0, 'dentro_sla', NULL, NULL, 'ok', NULL, 2, 4, '2026-08-03 12:39:54', '2026-08-04 16:27:49'),
(7, NULL, 12, 13, 0, 4, 6, 'TICs', 7, 'asignacion de computador', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-04 10:00:26', '2026-08-11 18:00:00', NULL, NULL, '2026-08-04 10:00:34', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 10:00:34', 0, 'dentro_sla', NULL, NULL, 'ok', NULL, 2, 4, '2026-08-03 12:39:54', '2026-08-04 16:27:49'),
(8, 4, 12, 12, 1, 5, 5, 'Talento Humano', 8, 'Ingreso', 2, 'Seguridad Integral', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-03 16:52:23', '2026-08-10 18:00:00', NULL, NULL, '2026-08-04 09:59:38', 0, 'dentro_sla', 2, NULL, 0, 2, '2026-08-04 09:59:38', 0, 'dentro_sla', NULL, NULL, 'ok', 'necesito un computador', 2, 2, '2026-08-03 16:52:23', '2026-08-04 16:27:49'),
(9, 8, 12, 2, 2, 6, 5, 'Talento Humano', 5, 'Novedades de nómina', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-03 16:58:17', '2026-08-12 16:58:17', NULL, NULL, '2026-08-04 07:39:16', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 07:39:16', 0, 'dentro_sla', NULL, NULL, 'Listo', 'b a', 2, 2, '2026-08-03 16:58:17', '2026-08-04 16:27:49'),
(10, 8, 12, 5, 2, 7, 3, 'Jurídica', 3, 'Revisión de contratos', 2, 'Seguridad Integral', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-04 09:14:08', '2026-08-10 18:00:00', NULL, NULL, '2026-08-04 09:59:23', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 09:59:23', 0, 'dentro_sla', NULL, NULL, 'ok', 'na', 2, 2, '2026-08-04 09:14:08', '2026-08-04 16:27:49'),
(11, 8, 12, 9, 2, 8, 6, 'TICs', 7, 'asignacion de computador', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-04 09:14:08', '2026-08-11 18:00:00', NULL, NULL, '2026-08-04 09:59:14', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 09:59:14', 0, 'dentro_sla', NULL, NULL, 'ok', 'na', 2, 2, '2026-08-04 09:14:08', '2026-08-04 16:27:49'),
(12, 8, 12, 3, 2, 9, 6, 'TICs', 6, 'Soporte técnico general', 2, 'Seguridad Integral', 2, 'SLA prioritario - 4 horas', 4, 'horas', 240, 44, 'completada', '2026-08-04 09:14:08', '2026-08-04 13:14:08', NULL, NULL, '2026-08-04 09:59:04', 44, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 09:59:04', 44, 'dentro_sla', NULL, NULL, 'ok', 'na', 2, 2, '2026-08-04 09:14:08', '2026-08-04 16:27:49'),
(13, 8, 12, 2, 2, 10, 5, 'Talento Humano', 5, 'Novedades de nómina', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-04 09:14:08', '2026-08-11 18:00:00', NULL, NULL, '2026-08-04 09:58:52', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 09:58:52', 0, 'dentro_sla', NULL, NULL, 'ok', 'na', 2, 2, '2026-08-04 09:14:08', '2026-08-04 16:27:49'),
(14, 8, 12, 12, 2, 11, 5, 'Talento Humano', 8, 'Ingreso', 2, 'Seguridad Integral', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-04 09:14:08', '2026-08-10 18:00:00', NULL, NULL, '2026-08-04 09:58:15', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 09:58:15', 0, 'dentro_sla', NULL, NULL, 'ok', 'na', 2, 2, '2026-08-04 09:14:08', '2026-08-04 16:27:49'),
(15, NULL, 13, 5, 0, 1, 3, 'Jurídica', 3, 'Revisión de contratos', 2, 'Seguridad Integral', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-04 10:01:29', '2026-08-10 18:00:00', NULL, NULL, '2026-08-04 10:01:44', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 10:01:44', 0, 'dentro_sla', NULL, NULL, 'ok', NULL, 2, 2, '2026-08-04 10:01:29', '2026-08-04 16:27:49'),
(16, NULL, 13, 10, 0, 2, 6, 'TICs', 7, 'asignacion de computador', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-04 10:01:44', '2026-08-11 18:00:00', NULL, NULL, '2026-08-04 10:01:53', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 10:01:53', 0, 'dentro_sla', NULL, NULL, 'ok', NULL, 2, 2, '2026-08-04 10:01:29', '2026-08-04 16:27:49'),
(17, NULL, 14, 6, 0, 1, 2, 'Contabilidad', 2, 'Declaración de impuestos', 4, 'Gestor2', 5, '30 dias tics', 30, 'dias', 18000, 0, 'pendiente', '2026-08-04 10:02:03', '2026-09-17 18:00:00', '2026-08-04 10:02:03', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 'sin_iniciar', NULL, NULL, NULL, NULL, NULL, 2, '2026-08-04 10:02:03', '2026-08-04 15:02:03'),
(18, NULL, 15, 3, 0, 1, 6, 'TICs', 6, 'Soporte técnico general', 2, 'Seguridad Integral', 2, 'SLA prioritario - 4 horas', 4, 'horas', 240, 63, 'completada', '2026-08-04 10:25:59', '2026-08-04 14:25:59', NULL, NULL, '2026-08-04 11:29:06', 63, 'dentro_sla', 2, NULL, 0, 0, '2026-08-04 11:29:43', 63, 'dentro_sla', 1, 'Reinicio computador', 'nicA', NULL, 4, 4, '2026-08-04 10:25:59', '2026-08-04 16:29:43'),
(19, NULL, 15, 4, 0, 2, 6, 'TICs', 7, 'asignacion de computador', 2, 'Seguridad Integral', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-04 11:29:43', '2026-08-11 18:00:00', NULL, NULL, '2026-08-04 11:43:40', 0, 'dentro_sla', 2, NULL, 0, 1, '2026-08-04 11:45:41', 0, 'dentro_sla', 5, 'Asignado', 'asignado con exito', NULL, 4, 4, '2026-08-04 10:25:59', '2026-08-04 16:45:41'),
(20, 19, 15, 6, 1, 3, 2, 'Contabilidad', 2, 'Declaración de impuestos', 4, 'Gestor2', 5, '30 dias tics', 30, 'dias', 18000, 0, 'completada', '2026-08-04 11:30:53', '2026-09-17 18:00:00', NULL, NULL, '2026-08-04 11:38:47', 0, 'dentro_sla', 4, NULL, 0, 0, '2026-08-04 11:40:48', 0, 'dentro_sla', 3, 'Impuestos liquidados', 'impuestos liquidados', 'na', 2, 2, '2026-08-04 11:30:53', '2026-08-04 16:40:48'),
(21, 19, 15, 7, 1, 4, 1, 'Adquisiciones', 1, 'Solicitud de compra', 4, 'Gestor2', 2, 'SLA prioritario - 4 horas', 4, 'horas', 240, 9, 'completada', '2026-08-04 11:30:53', '2026-08-04 15:30:53', NULL, NULL, '2026-08-04 11:40:14', 9, 'dentro_sla', 4, NULL, 0, 0, '2026-08-04 11:41:01', 9, 'dentro_sla', 4, 'Compra de computador', 'ok', 'na', 2, 2, '2026-08-04 11:30:53', '2026-08-04 16:41:01');

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
-- Volcado de datos para la tabla `ticket_etapa_checklist`
--

INSERT INTO `ticket_etapa_checklist` (`id_ticket_checklist`, `id_ticket_etapa`, `id_checklist_plantilla`, `nombre`, `descripcion`, `obligatorio`, `requiere_evidencia`, `orden`, `completado`, `observacion`, `evidencia_ruta`, `completado_por`, `completado_en`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 1, 'Documentos diligenciados', NULL, 1, 0, 1, 1, NULL, NULL, 2, '2026-08-03 09:54:10', '2026-08-03 09:50:59', '2026-08-03 14:54:10'),
(2, 2, 2, 'Documentos diligenciados', NULL, 1, 0, 1, 1, NULL, NULL, 2, '2026-08-03 09:55:46', '2026-08-03 09:53:16', '2026-08-03 14:55:46'),
(3, 3, 1, 'Documentos diligenciados', NULL, 1, 0, 1, 0, NULL, NULL, NULL, NULL, '2026-08-03 09:53:16', '2026-08-03 14:53:16'),
(4, 9, 2, 'Documentos diligenciados', NULL, 1, 0, 1, 1, 'ok', NULL, 2, '2026-08-04 07:39:07', '2026-08-03 16:58:17', '2026-08-04 12:39:07'),
(5, 13, 2, 'Documentos diligenciados', NULL, 1, 0, 1, 1, NULL, NULL, 2, '2026-08-04 09:58:44', '2026-08-04 09:14:08', '2026-08-04 14:58:44');

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
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `cedula`, `nombre`, `proceso`, `cu1`, `cu3`, `email`, `descripcion_cu1`, `ciudad`, `empresa`, `password`, `id_rol`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 'ADMIN001', 'Administrador General', 'Administración', 'N/A', 'N/A', 'jhonnytorrez999@gmail.com', 'Cuenta administradora inicial del sistema.', 'Bogotá', 'Conectar TV', '$2y$10$mnwkjabwDj3PdMYRgiuhvepn8XQibz/I4BJ1TFWsq7zUu6fN1/D7e', 1, 'activo', '2026-07-31 09:00:22', '2026-07-31 14:02:05'),
(2, '101010', 'Seguridad Integral', '0', 'IGAM', 'HNAL', 'jhonnytor@gmail.com', '.', 'Bogotá D.C.', 'millenium', '$2y$10$F8W20yxKzWWiTrA87yaPWOWrwyOKs1rgT1fVZUa1xkU0iyUBGTv4u', 2, 'activo', '2026-07-31 09:01:38', '2026-07-31 14:01:38'),
(4, '2020', 'Gestor2', '0', 'IGAM', 'HNAL', 'jhonnytorrez99@gmail.com', '0', 'Bogotá D.C.', 'millenium', '$2y$10$wsYV31LKg.AikWnlB2/Lk.xdpmGApV41fYkUANv2hb3H4gedAygmy', 2, 'activo', '2026-07-31 09:03:10', '2026-08-03 21:16:00');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `catalogos`
--
ALTER TABLE `catalogos`
  ADD PRIMARY KEY (`id_catalogo`),
  ADD UNIQUE KEY `uq_catalogos_nombre` (`nombre`),
  ADD KEY `idx_catalogos_estado_orden` (`estado`,`orden`,`nombre`);

--
-- Indices de la tabla `configuraciones_servicio`
--
ALTER TABLE `configuraciones_servicio`
  ADD PRIMARY KEY (`id_opcion`),
  ADD UNIQUE KEY `uq_configuracion_tipo_nombre` (`tipo`,`nombre`),
  ADD KEY `idx_configuracion_tipo_estado` (`tipo`,`estado_registro`,`orden`,`nombre`),
  ADD KEY `idx_configuracion_padre` (`id_padre`);

--
-- Indices de la tabla `feriados`
--
ALTER TABLE `feriados`
  ADD PRIMARY KEY (`id_feriado`),
  ADD KEY `idx_feriados_estado_rango` (`estado`,`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_feriados_creado_por` (`id_creado_por`);

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
-- Indices de la tabla `procesos`
--
ALTER TABLE `procesos`
  ADD PRIMARY KEY (`id_proceso`),
  ADD UNIQUE KEY `uq_procesos_nombre` (`nombre`),
  ADD KEY `idx_procesos_estado` (`estado`),
  ADD KEY `fk_procesos_creador` (`creado_por`),
  ADD KEY `fk_procesos_actualizador` (`actualizado_por`);

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
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`),
  ADD UNIQUE KEY `uq_servicio_catalogo_nombre` (`id_catalogo`,`nombre`),
  ADD KEY `idx_servicios_catalogo_estado` (`id_catalogo`,`estado`,`nombre`),
  ADD KEY `idx_servicios_sla` (`id_sla`),
  ADD KEY `idx_servicios_pais` (`id_pais`),
  ADD KEY `idx_servicios_ciudad` (`id_ciudad`),
  ADD KEY `idx_servicios_lugar` (`id_lugar`),
  ADD KEY `idx_servicios_departamento` (`id_departamento`),
  ADD KEY `idx_servicios_prioridad` (`id_prioridad`),
  ADD KEY `idx_servicios_urgencia` (`id_urgencia`),
  ADD KEY `idx_servicios_nivel` (`id_nivel`),
  ADD KEY `idx_servicios_impacto` (`id_impacto`),
  ADD KEY `idx_servicios_estado_opcion` (`id_estado`),
  ADD KEY `idx_servicios_gestor` (`id_gestor`);

--
-- Indices de la tabla `sla`
--
ALTER TABLE `sla`
  ADD PRIMARY KEY (`id_sla`),
  ADD UNIQUE KEY `uq_sla_nombre` (`nombre`),
  ADD KEY `idx_sla_estado_tiempo` (`estado`,`tiempo_respuesta`,`nombre`);

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
  ADD KEY `idx_tickets_etapa_actual` (`id_etapa_actual`);

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
  ADD KEY `idx_usuarios_rol_estado` (`id_rol`,`estado`,`nombre`);

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
  MODIFY `id_feriado` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_notificacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `procesos`
--
ALTER TABLE `procesos`
  MODIFY `id_proceso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `proceso_etapas`
--
ALTER TABLE `proceso_etapas`
  MODIFY `id_proceso_etapa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `proceso_etapa_checklist`
--
ALTER TABLE `proceso_etapa_checklist`
  MODIFY `id_checklist` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `sla`
--
ALTER TABLE `sla`
  MODIFY `id_sla` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_actividades`
--
ALTER TABLE `solicitud_actividades`
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_adjuntos`
--
ALTER TABLE `solicitud_adjuntos`
  MODIFY `id_adjunto` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `solicitud_calificaciones`
--
ALTER TABLE `solicitud_calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_comunicaciones`
--
ALTER TABLE `solicitud_comunicaciones`
  MODIFY `id_comunicacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  MODIFY `id_historial` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT de la tabla `solicitud_vinculos`
--
ALTER TABLE `solicitud_vinculos`
  MODIFY `id_vinculo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `soluciones_servicio`
--
ALTER TABLE `soluciones_servicio`
  MODIFY `id_solucion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id_ticket` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `ticket_etapas`
--
ALTER TABLE `ticket_etapas`
  MODIFY `id_ticket_etapa` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `ticket_etapa_checklist`
--
ALTER TABLE `ticket_etapa_checklist`
  MODIFY `id_ticket_checklist` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `configuraciones_servicio`
--
ALTER TABLE `configuraciones_servicio`
  ADD CONSTRAINT `fk_configuracion_padre` FOREIGN KEY (`id_padre`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `feriados`
--
ALTER TABLE `feriados`
  ADD CONSTRAINT `fk_feriados_creado_por` FOREIGN KEY (`id_creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Filtros para la tabla `procesos`
--
ALTER TABLE `procesos`
  ADD CONSTRAINT `fk_procesos_actualizador` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_procesos_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `fk_servicios_catalogo` FOREIGN KEY (`id_catalogo`) REFERENCES `catalogos` (`id_catalogo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_ciudad` FOREIGN KEY (`id_ciudad`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_estado_opcion` FOREIGN KEY (`id_estado`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_gestor` FOREIGN KEY (`id_gestor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_impacto` FOREIGN KEY (`id_impacto`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_lugar` FOREIGN KEY (`id_lugar`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_nivel` FOREIGN KEY (`id_nivel`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_pais` FOREIGN KEY (`id_pais`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_prioridad` FOREIGN KEY (`id_prioridad`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_sla` FOREIGN KEY (`id_sla`) REFERENCES `sla` (`id_sla`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servicios_urgencia` FOREIGN KEY (`id_urgencia`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
