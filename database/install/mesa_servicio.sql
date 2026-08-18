-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-08-2026 a las 23:08:24
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
(1, 1, 'Adquisiciones', 'Solicitudes de compras y adquisiciones.', 'private/catalogos/catalogo_8c784825f9bebc25.webp', 'activo', 4, '2026-07-31 09:00:22', '2026-08-14 16:25:14'),
(2, 1, 'Contabilidad', 'Servicios contables, tributarios y financieros.', 'uploads/catalogo_970357f00a43dc74.jpg', 'activo', 1, '2026-07-31 09:00:22', '2026-08-14 16:25:14'),
(3, 1, 'Jurídica', 'Consultas y solicitudes jurídicas.', 'uploads/catalogo_67a694e3abb98f60.webp', 'activo', 6, '2026-07-31 09:00:22', '2026-08-14 16:25:14'),
(4, 1, 'Seguridad Integral', 'Servicios de seguridad y gestión de riesgos.', 'uploads/catalogo_d4a0a7c721a2ac5e.png', 'activo', 5, '2026-07-31 09:00:22', '2026-08-14 16:25:14'),
(5, 1, 'Talento Humano', 'Servicios relacionados con los colaboradores.', 'uploads/catalogo_f0d10ff0f4568d10.png', 'activo', 3, '2026-07-31 09:00:22', '2026-08-14 16:25:14'),
(6, 1, 'TICs', 'Soporte tecnológico y sistemas de información.', 'uploads/catalogo_9a16b4f1330fbb56.webp', 'activo', 2, '2026-07-31 09:00:22', '2026-08-14 16:25:14'),
(7, 2, 'TICs', '', 'private/catalogos/catalogo_e1c91e52c28b77aa.webp', 'activo', 1, '2026-08-12 15:56:05', '2026-08-13 14:09:04'),
(8, 2, 'Seguridad Integral', '', 'private/catalogos/catalogo_1829b03c38c3593d.webp', 'activo', 2, '2026-08-12 16:00:04', '2026-08-13 14:09:04');

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
(32, 1, 'ciudad', NULL, 'Berlín', '', '#ff0000', 'activo', 3, '2026-07-31 09:12:52', '2026-08-06 15:34:34'),
(34, 2, 'pais', NULL, 'peru', '', '#ff0000', 'activo', 1, '2026-08-12 12:50:24', '2026-08-12 17:50:24'),
(35, 2, 'departamento', 34, 'departamento de lima', '', '#6d28d9', 'activo', 1, '2026-08-12 12:51:36', '2026-08-12 17:51:36'),
(36, 2, 'ciudad', 35, 'lima', '', '#8b5cf6', 'activo', 1, '2026-08-12 12:51:46', '2026-08-12 17:51:46'),
(37, 2, 'urgencia', NULL, 'baja', '', '#e11d48', 'activo', 1, '2026-08-12 15:57:12', '2026-08-12 20:57:12'),
(38, 2, 'nivel', NULL, 'bajo', '', '#0f6fec', 'activo', 1, '2026-08-12 15:57:21', '2026-08-12 20:57:21'),
(39, 2, 'impacto', NULL, 'bajo', '', '#0e9f9a', 'activo', 1, '2026-08-12 15:57:30', '2026-08-12 20:57:30'),
(40, 2, 'estado', NULL, 'abierto', '', '#d97706', 'activo', 1, '2026-08-12 15:57:39', '2026-08-12 20:57:39'),
(41, 2, 'prioridad', NULL, 'bajo', '', '#db2777', 'activo', 1, '2026-08-12 15:57:59', '2026-08-12 20:57:59');

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

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id_notificacion`, `id_usuario`, `id_ticket`, `id_ticket_etapa`, `titulo`, `mensaje`, `leida`, `creada_en`, `leida_en`) VALUES
(1, 4, 1, 1, 'Nuevo ticket asignado', 'El Caso 1, etapa 1, está disponible para su gestión.', 1, '2026-08-11 12:03:30', '2026-08-11 12:12:29'),
(2, 4, 1, 1, 'Nuevo mensaje en Caso 1 · etapa 1', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 1.', 1, '2026-08-11 12:10:29', '2026-08-11 12:12:29'),
(3, 4, 1, 1, 'Nuevo mensaje en Caso 1 · etapa 1', 'Recibió un mensaje o documento en el chat privado de Caso 1 · etapa 1.', 1, '2026-08-11 12:11:27', '2026-08-11 12:12:29'),
(4, 9, 1, 1, 'Atención lista para revisión', 'El gestor asignado marcó Caso 1 · etapa 1 como listo. Revise la solución y cierre o reabra la atención.', 1, '2026-08-11 12:13:04', '2026-08-14 09:20:48'),
(5, 4, 1, 1, 'Atención aprobada y cerrada', 'El creador aprobó y calificó Caso 1 · etapa 1. Gestión: 5/5; tiempo: 5/5.', 1, '2026-08-11 12:17:36', '2026-08-12 09:31:20'),
(6, 2, 1, 2, 'Caso habilitado para su área', 'El caso anterior del ticket #1 terminó. Ya puede atenderlo.', 1, '2026-08-11 12:17:36', '2026-08-11 12:17:50'),
(7, 9, 1, 2, 'Solicitud de cierre definitivo', 'El gestor resolvió Caso 1 · etapa 2 en primer contacto y solicita cerrar definitivamente el ticket sin continuar el flujo. Revise la solución y apruebe o reabra.', 1, '2026-08-11 12:18:23', '2026-08-14 09:20:48'),
(8, 2, 1, 2, 'Atención reabierta', 'El creador reabrió . Debe continuar la gestión y el SLA volvió a correr.', 1, '2026-08-12 10:49:19', '2026-08-12 10:50:45'),
(9, 2, 1, 4, 'Nuevo ticket derivado asignado', 'Se derivó a su área el Ticket 2.1 del Caso 1.', 1, '2026-08-12 11:02:14', '2026-08-12 11:02:14'),
(10, 9, 1, 4, 'Nueva dependencia en su ticket', 'Se creó el Ticket derivado 2.1 para Talento Humano / Ingreso.', 1, '2026-08-12 11:02:14', '2026-08-14 09:20:48'),
(11, 2, 1, 4, 'Atención lista para revisión', 'El gestor asignado marcó Ticket 2.1 como listo. Revise la solución y cierre o reabra la atención.', 1, '2026-08-12 11:09:37', '2026-08-12 11:09:38'),
(12, 2, 1, 2, 'Caso padre reanudado', 'Todos los hijos del ticket #1 finalizaron. Su SLA volvió a correr.', 1, '2026-08-12 11:11:46', '2026-08-12 11:11:47'),
(13, 2, 1, 5, 'Nuevo ticket derivado asignado', 'Se derivó a su área el Ticket 2.2 del Caso 1.', 1, '2026-08-12 11:12:07', '2026-08-12 11:12:07'),
(14, 9, 1, 5, 'Nueva dependencia en su ticket', 'Se creó el Ticket derivado 2.2 para Talento Humano / Ingreso.', 1, '2026-08-12 11:12:07', '2026-08-14 09:20:48'),
(15, 2, 1, 5, 'Atención lista para revisión', 'El gestor asignado marcó Ticket 2.2 como listo. Revise la solución y cierre o reabra la atención.', 1, '2026-08-12 11:21:24', '2026-08-12 11:21:24'),
(16, 2, 1, 2, 'Caso padre reanudado', 'Todos los hijos del ticket #1 finalizaron. Su SLA volvió a correr.', 1, '2026-08-12 11:21:36', '2026-08-12 11:21:37'),
(17, 9, 1, 2, 'Solicitud de cierre definitivo', 'El gestor resolvió Caso 1 · etapa 2 en primer contacto y solicita cerrar definitivamente el ticket sin continuar el flujo. Revise la solución y apruebe o reabra.', 1, '2026-08-12 11:26:46', '2026-08-14 09:20:48'),
(18, 2, 1, 2, 'Atención reabierta', 'El creador reabrió . Debe continuar la gestión y el SLA volvió a correr.', 1, '2026-08-12 11:28:49', '2026-08-12 11:29:34'),
(19, 2, 1, 6, 'Nuevo ticket derivado asignado', 'Se derivó a su área el Ticket 2.3 del Caso 1.', 1, '2026-08-12 11:29:58', '2026-08-12 11:29:58'),
(20, 9, 1, 6, 'Nueva dependencia en su ticket', 'Se creó el Ticket derivado 2.3 para Talento Humano / Ingreso.', 1, '2026-08-12 11:29:58', '2026-08-14 09:20:48'),
(21, 2, 1, 6, 'Atención lista para revisión', 'El gestor asignado marcó Ticket 2.3 como listo. Revise la solución y cierre o reabra la atención.', 1, '2026-08-12 11:30:39', '2026-08-12 11:30:40'),
(22, 2, 1, 2, 'Caso padre reanudado', 'Todos los hijos del ticket #1 finalizaron. Su SLA volvió a correr.', 1, '2026-08-12 11:36:23', '2026-08-12 11:36:23'),
(23, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-13 15:43:46', '2026-08-14 09:20:48'),
(24, 2, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-13 15:48:01', '2026-08-13 15:49:23'),
(25, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-13 15:50:45', '2026-08-14 09:20:48'),
(26, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-13 15:57:34', '2026-08-14 09:20:48'),
(27, 4, 2, 7, 'Nuevo ticket asignado', 'El Caso 2, etapa 1, está disponible para su gestión.', 1, '2026-08-13 16:17:33', '2026-08-13 16:30:00'),
(28, 4, 2, 7, 'Nuevo mensaje en Caso 2 · etapa 1', 'Recibió un mensaje en el chat privado de Caso 2 · etapa 1.', 1, '2026-08-13 16:19:50', '2026-08-13 16:30:00'),
(29, 4, 2, 7, 'Nuevo mensaje en Caso 2 · etapa 1', 'Recibió un mensaje en el chat privado de Caso 2 · etapa 1.', 1, '2026-08-13 16:20:33', '2026-08-13 16:30:00'),
(30, 4, 2, 7, 'Nuevo mensaje en Caso 2 · etapa 1', 'Recibió un mensaje o documento en el chat privado de Caso 2 · etapa 1.', 1, '2026-08-13 16:21:53', '2026-08-13 16:30:00'),
(31, 4, 2, 7, 'Nuevo mensaje en Caso 2 · etapa 1', 'Recibió un mensaje o documento en el chat privado de Caso 2 · etapa 1.', 1, '2026-08-13 16:22:32', '2026-08-13 16:30:00'),
(32, 9, 2, 7, 'Nuevo mensaje en Caso 2 · etapa 1', 'Recibió un mensaje en el chat privado de Caso 2 · etapa 1.', 1, '2026-08-13 16:30:48', '2026-08-14 09:20:48'),
(33, 9, 2, 7, 'Solicitud de cierre definitivo', 'El gestor resolvió Caso 2 · etapa 1 en primer contacto y solicita cerrar definitivamente el ticket sin continuar el flujo. Revise la solución y apruebe o reabra.', 1, '2026-08-13 16:32:29', '2026-08-14 09:20:48'),
(34, 4, 2, 7, 'Cierre definitivo aprobado', 'El solicitante aprobó el cierre definitivo del ticket #2 por resolución en primer contacto.', 1, '2026-08-13 16:34:23', '2026-08-14 09:22:00'),
(35, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 07:56:46', '2026-08-14 09:20:48'),
(36, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 07:57:38', '2026-08-14 09:20:48'),
(37, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:00:53', '2026-08-14 09:20:48'),
(38, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:01:06', '2026-08-14 09:20:48'),
(39, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:14:03', '2026-08-14 09:20:48'),
(40, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje o documento en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:44:31', '2026-08-14 09:20:48'),
(41, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:44:38', '2026-08-14 09:20:48'),
(42, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:44:41', '2026-08-14 09:20:48'),
(43, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:44:43', '2026-08-14 09:20:48'),
(44, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:44:45', '2026-08-14 09:20:48'),
(45, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:44:47', '2026-08-14 09:20:48'),
(46, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:44:50', '2026-08-14 09:20:48'),
(47, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje o documento en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:45:06', '2026-08-14 09:20:48'),
(48, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 08:45:12', '2026-08-14 09:20:48'),
(49, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 09:00:12', '2026-08-14 09:16:01'),
(50, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 09:00:16', '2026-08-14 09:15:46'),
(51, 9, 1, 2, 'Nuevo mensaje en Caso 1 · etapa 2', 'Recibió un mensaje o documento en el chat privado de Caso 1 · etapa 2.', 1, '2026-08-14 09:00:31', '2026-08-14 09:13:46'),
(52, 9, 1, 2, 'Atención lista para revisión', 'El gestor asignado marcó Caso 1 · etapa 2 como listo. Revise la solución y cierre o reabra la atención.', 1, '2026-08-14 09:21:44', '2026-08-14 09:22:44'),
(53, 2, 1, 2, 'Atención aprobada y cerrada', 'El creador aprobó y calificó Caso 1 · etapa 2. Gestión: 5/5; tiempo: 5/5.', 1, '2026-08-14 09:22:37', '2026-08-14 09:36:14'),
(54, 2, 1, 3, 'Caso habilitado para su área', 'El caso anterior del ticket #1 terminó. Ya puede atenderlo.', 1, '2026-08-14 09:22:37', '2026-08-14 09:36:14'),
(55, 2, 1, 3, 'Nuevo mensaje en Caso 1 · etapa 3', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 3.', 1, '2026-08-14 09:23:45', '2026-08-14 09:36:14'),
(56, 2, 1, 3, 'Nuevo mensaje en Caso 1 · etapa 3', 'Recibió un mensaje o documento en el chat privado de Caso 1 · etapa 3.', 1, '2026-08-14 10:26:34', '2026-08-14 10:28:30'),
(57, 2, 1, 3, 'Nuevo mensaje en Caso 1 · etapa 3', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 3.', 1, '2026-08-14 10:26:42', '2026-08-14 10:28:30'),
(58, 2, 1, 3, 'Nuevo mensaje en Caso 1 · etapa 3', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 3.', 1, '2026-08-14 10:34:15', '2026-08-14 10:34:47'),
(59, 2, 1, 3, 'Nuevo mensaje en Caso 1 · etapa 3', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 3.', 1, '2026-08-14 10:34:16', '2026-08-14 10:34:47'),
(60, 2, 1, 3, 'Nuevo mensaje en Caso 1 · etapa 3', 'Recibió un mensaje en el chat privado de Caso 1 · etapa 3.', 1, '2026-08-14 10:34:18', '2026-08-14 10:34:47');

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
(1, 'CO', 'Conectar', 'America/Bogota', '#0f6fec', '#facc15', 'activo', 1, '2026-08-05 08:50:04'),
(2, 'PE', 'Telecomunicaciones', 'America/Lima', '#c81e3a', '#ffffff', 'activo', 2, '2026-08-05 08:50:04');

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
(10, 1, 'TICs · Recepción de equipos y entrega de otro', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-05 14:31:20', '2026-08-05 19:31:20'),
(11, 2, 'TICs · reparacion de computador', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-12 15:59:21', '2026-08-12 20:59:21'),
(12, 2, 'Seguridad Integral · Incidente de seguridad', 'Flujo sincronizado automáticamente con el servicio.', 'activo', 1, 1, '2026-08-12 16:02:23', '2026-08-12 21:02:23');

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
(19, 9, 7, NULL, NULL, 3, 'Recepcion de computador', NULL, 0, 'activo', '2026-08-05 14:31:42', '2026-08-05 19:31:42'),
(20, 11, 11, NULL, NULL, 1, NULL, NULL, 0, 'activo', '2026-08-12 15:59:21', '2026-08-12 20:59:21'),
(21, 12, 12, NULL, NULL, 1, NULL, NULL, 0, 'activo', '2026-08-12 16:02:23', '2026-08-12 21:02:23'),
(22, 11, 12, NULL, NULL, 2, NULL, NULL, 0, 'activo', '2026-08-12 16:02:52', '2026-08-12 21:02:52');

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
(5, 5, '¿Se valido identidad?', NULL, 1, 0, 1, 'activo', '2026-08-06 12:07:35', '2026-08-06 17:07:35'),
(7, 17, '¿se generaron las validaciones correspondientes?', NULL, 1, 0, 1, 'activo', '2026-08-12 10:53:19', '2026-08-12 15:53:19'),
(8, 12, '¿Documentos diligenciados?', NULL, 1, 0, 1, 'activo', '2026-08-12 11:18:15', '2026-08-12 16:18:15'),
(9, 20, '¿se generaron las validaciones correspondientes?', NULL, 1, 0, 1, 'activo', '2026-08-12 15:59:43', '2026-08-12 20:59:43'),
(10, 22, '¿Documentos diligenciados?', NULL, 1, 0, 1, 'activo', '2026-08-12 16:03:04', '2026-08-12 21:03:04');

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

--
-- Volcado de datos para la tabla `recuperaciones_password`
--

INSERT INTO `recuperaciones_password` (`id_recuperacion`, `id_usuario`, `codigo_hash`, `intentos`, `solicitado_ip_hash`, `expira_en`, `usado_en`, `creado_en`) VALUES
(1, 9, '207cf1e168b1109c50d6971a20cf9c7db804945a9bd261a9465a46b15d71502e', 0, 'c87474d914c4146cc74c34eedab1b1223dcc261610abb054aca8f3bc96ce391d', '2026-08-13 15:14:05', '2026-08-13 15:05:05', '2026-08-13 15:04:05'),
(2, 9, 'e654ed948fe856d4462cdcc93d8ebea64d598d6e00d810217b5cc168c578b277', 0, 'c87474d914c4146cc74c34eedab1b1223dcc261610abb054aca8f3bc96ce391d', '2026-08-13 15:16:21', '2026-08-13 16:11:25', '2026-08-13 15:06:21'),
(3, 9, '30b89208bf5c2d403a8197f9693a12783244b9c1a5dd47057f98f584bffd1b26', 0, 'c87474d914c4146cc74c34eedab1b1223dcc261610abb054aca8f3bc96ce391d', '2026-08-13 16:21:25', '2026-08-13 16:11:55', '2026-08-13 16:11:25'),
(4, 9, '4e8bcae9fcc7d8963993143d4012c64b2e104985cc8d9d0e0fdb32a7da21873b', 1, 'c87474d914c4146cc74c34eedab1b1223dcc261610abb054aca8f3bc96ce391d', '2026-08-14 08:08:47', '2026-08-14 07:59:35', '2026-08-14 07:58:48'),
(5, 9, '9227abe235a2b204e521950f7113181f12c272f993ee258aafe0b5b2a24a9849', 0, 'c87474d914c4146cc74c34eedab1b1223dcc261610abb054aca8f3bc96ce391d', '2026-08-14 08:25:11', NULL, '2026-08-14 08:15:11');

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
('289496a0a5ef44375cfd09dea810e2fdfbc10258a07b244766a21d3b2be58973', 1, '2026-08-12 16:20:29', NULL),
('877e81f31325e9014fbbfe60c1ad7722e03bab38c47149640f70a50e305e658c', 1, '2026-08-12 16:06:16', NULL),
('b94edceade70d83214a30d9839bc23ed9752673e972d90e843ebccc1ccf6392d', 1, '2026-08-18 15:46:04', NULL),
('ce8ee4b118c04d130a3fa4abea2acf7be5b15c27cebe019ada8f2dd39e41484e', 1, '2026-08-12 15:11:10', NULL),
('ec7b96bca279f684c0eceabade661c79132a14b425e714264302fd8ebd73b158', 1, '2026-08-18 15:30:42', NULL);

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
(10, 1, 6, 1, NULL, 'Recepción de equipos y entrega de otro', 'Cuando el equipo falla y no hay solucion se valida la causa y se le entrega uno nuevo', 'requerimiento', 'activo', 1, 2, 9, 10, 14, 18, 21, 25, '2026-08-05 14:31:18', '2026-08-05 19:31:18'),
(11, 2, 7, 6, 7, 'reparacion de computador', 'reparar computador', 'incidente', 'activo', 34, 36, 35, 41, 37, 38, 39, 40, '2026-08-12 15:58:40', '2026-08-12 20:59:26'),
(12, 2, 8, 6, 7, 'Incidente de seguridad', 'cuando presencia algún incidente de seguridad', 'incidente', 'activo', 34, 36, 35, 41, 37, 38, 39, 40, '2026-08-12 16:00:56', '2026-08-12 21:02:29');

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
(5, 1, '30 dias tics', 30, 'dias', 'activo', '2026-08-03 12:02:54', '2026-08-05 13:50:04'),
(6, 2, '20 días', 20, 'dias', 'activo', '2026-08-12 15:56:40', '2026-08-12 20:56:40');

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
(1, 1, 1, 9, 'MILENIO PC.xlsx', 'flujo_1_48e1977eb90885b8c13e7659.xlsx', 'private/solicitudes/flujo_1_48e1977eb90885b8c13e7659.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 521578, '2026-08-11 12:03:30'),
(2, 1, 1, 9, 'inv (1).xlsx', 'flujo_1_edbbb93bbea92d6b8aa79d5f.xlsx', 'private/solicitudes/flujo_1_edbbb93bbea92d6b8aa79d5f.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 125125, '2026-08-11 12:11:27'),
(3, 2, 7, 9, 'Captura de pantalla 2026-07-28 110947.png', 'flujo_2_296a382af2e849704fcea371.png', 'private/solicitudes/flujo_2_296a382af2e849704fcea371.png', 'image/png', 102034, '2026-08-13 16:17:33'),
(4, 2, 7, 9, 'Captura de pantalla 2026-07-27 160123.png', 'flujo_2_f808f46963985cc7cc1f1eea.png', 'private/solicitudes/flujo_2_f808f46963985cc7cc1f1eea.png', 'image/png', 27042, '2026-08-13 16:21:53'),
(5, 2, 7, 9, 'Captura de pantalla 2026-07-27 160123.png', 'flujo_2_597bb668f3762e67c61ddc5b.png', 'private/solicitudes/flujo_2_597bb668f3762e67c61ddc5b.png', 'image/png', 27042, '2026-08-13 16:22:32'),
(6, 1, 2, 2, 'Captura de pantalla 2026-07-28 110947.png', 'flujo_1_e30d0d28d2a96ec0ed10d50c.png', 'private/solicitudes/flujo_1_e30d0d28d2a96ec0ed10d50c.png', 'image/png', 102034, '2026-08-14 08:44:31'),
(7, 1, 2, 2, 'Captura de pantalla 2026-07-28 110947.png', 'flujo_1_9a829e5b7265291606d0e694.png', 'private/solicitudes/flujo_1_9a829e5b7265291606d0e694.png', 'image/png', 102034, '2026-08-14 08:45:06'),
(8, 1, 2, 2, 'Captura de pantalla 2026-07-28 110947.png', 'flujo_1_07cc2714edb3d55de7445a80.png', 'private/solicitudes/flujo_1_07cc2714edb3d55de7445a80.png', 'image/png', 102034, '2026-08-14 09:00:31'),
(9, 1, 3, 9, 'base_tickets_2026-08-04_173239.csv', 'flujo_1_e221529531aee359602e1a9d.txt', 'private/solicitudes/flujo_1_e221529531aee359602e1a9d.txt', 'text/plain', 13761, '2026-08-14 10:26:34');

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
-- Volcado de datos para la tabla `solicitud_calificaciones`
--

INSERT INTO `solicitud_calificaciones` (`id_calificacion`, `id_ticket`, `id_ticket_etapa`, `id_solicitante`, `id_gestor`, `calificacion`, `calificacion_area`, `calificacion_tiempo`, `tipo_calificacion`, `comentario`, `creado_en`) VALUES
(1, 1, 1, 9, 4, 5, 5, 5, 'encuesta_servicio', 'prueba', '2026-08-11 12:17:36'),
(2, 1, 4, 2, 2, 5, 5, 5, 'evaluacion_derivacion', 'prueba', '2026-08-12 11:11:46'),
(3, 1, 5, 2, 2, 5, 5, 5, 'evaluacion_derivacion', 'na', '2026-08-12 11:21:36'),
(4, 1, 6, 2, 2, 5, 5, 5, 'evaluacion_derivacion', 'prueba', '2026-08-12 11:36:23'),
(5, 2, 7, 9, 4, 5, 5, 5, 'encuesta_servicio', 'na', '2026-08-13 16:34:23'),
(6, 1, 2, 9, 2, 5, 5, 5, 'evaluacion_caso', 'prueba', '2026-08-14 09:22:37');

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
-- Volcado de datos para la tabla `solicitud_comunicaciones`
--

INSERT INTO `solicitud_comunicaciones` (`id_comunicacion`, `id_ticket`, `id_ticket_etapa`, `id_emisor`, `tipo`, `mensaje`, `creado_en`) VALUES
(1, 1, 1, 9, 'publica', 'Hola', '2026-08-11 12:10:29'),
(2, 1, 1, 9, 'publica', '¿Me ayudas?', '2026-08-11 12:11:27'),
(3, 1, 2, 2, 'publica', 'hola', '2026-08-13 15:43:46'),
(4, 1, 2, 9, 'publica', 'holaaaaaaaaaa', '2026-08-13 15:48:01'),
(5, 1, 2, 2, 'publica', 'Prueba de notificación del caso', '2026-08-13 15:50:45'),
(6, 1, 2, 2, 'publica', 'Prueba de notificación del caso', '2026-08-13 15:57:34'),
(7, 2, 7, 9, 'publica', 'hola', '2026-08-13 16:19:50'),
(8, 2, 7, 9, 'publica', 'fdgfdg', '2026-08-13 16:20:33'),
(9, 2, 7, 9, 'publica', 'dgfdfg', '2026-08-13 16:21:53'),
(10, 2, 7, 9, 'publica', 'adsasd', '2026-08-13 16:22:32'),
(11, 2, 7, 4, 'publica', 'HOIUHOIHO', '2026-08-13 16:30:48'),
(12, 1, 2, 2, 'publica', 'Prueba de notificación del caso', '2026-08-14 07:56:46'),
(13, 1, 2, 2, 'publica', 'Prueba de notificación del caso', '2026-08-14 07:57:38'),
(14, 1, 2, 2, 'publica', 'hola', '2026-08-14 08:00:53'),
(15, 1, 2, 2, 'publica', 'hola', '2026-08-14 08:01:06'),
(16, 1, 2, 2, 'publica', 'hola', '2026-08-14 08:14:03'),
(17, 1, 2, 2, 'publica', 'mira', '2026-08-14 08:44:38'),
(18, 1, 2, 2, 'publica', 'mira', '2026-08-14 08:44:41'),
(19, 1, 2, 2, 'publica', 'mira', '2026-08-14 08:44:43'),
(20, 1, 2, 2, 'publica', 'mira', '2026-08-14 08:44:45'),
(21, 1, 2, 2, 'publica', 'mira', '2026-08-14 08:44:47'),
(22, 1, 2, 2, 'publica', 'mira', '2026-08-14 08:44:50'),
(23, 1, 2, 2, 'publica', 'mira', '2026-08-14 08:45:12'),
(24, 1, 2, 2, 'publica', 'hola', '2026-08-14 09:00:12'),
(25, 1, 2, 2, 'publica', 'como estas?', '2026-08-14 09:00:16'),
(26, 1, 3, 9, 'publica', 'hola', '2026-08-14 09:23:45'),
(27, 1, 3, 9, 'publica', 'hgh', '2026-08-14 10:26:42'),
(28, 1, 3, 9, 'publica', 'fdbdfb', '2026-08-14 10:34:15'),
(29, 1, 3, 9, 'publica', 'fd', '2026-08-14 10:34:16'),
(30, 1, 3, 9, 'publica', 'bdfbd', '2026-08-14 10:34:18');

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
-- Volcado de datos para la tabla `solicitud_historial`
--

INSERT INTO `solicitud_historial` (`id_historial`, `id_ticket`, `id_ticket_etapa`, `id_usuario`, `accion`, `detalle`, `creado_en`) VALUES
(1, 1, 1, 9, 'Caso principal abierto', 'Se creó el Caso 1 y se activó la etapa 1 para TICs / Inconvenientes tecnologicos. Asunto: prueba. Solicitud: prueba', '2026-08-11 12:03:30'),
(2, 1, 1, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 1.', '2026-08-11 12:10:29'),
(3, 1, 1, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 1.', '2026-08-11 12:11:27'),
(4, 1, 1, 4, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-11 12:12:38'),
(5, 1, 1, 4, 'Atención marcada como lista', 'El gestor asignado marcó Caso 1 · etapa 1 como listo para revisión. El indicador SLA quedó cortado en 0 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-17 18:00:00. Solución: no hubo solucion remota. Observación: El computador no enciende..', '2026-08-11 12:13:04'),
(6, 1, 1, 9, 'Atención cerrada', 'Se cerró Caso 1 · etapa 1. TICs / Inconvenientes tecnologicos finalizó su atención dentro del SLA. Solución: no hubo solucion remota. Observación: El computador no enciende.. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-11 12:17:36'),
(7, 1, 2, 9, 'Caso heredado habilitado', 'Se habilitó TICs / Soporte técnico general.', '2026-08-11 12:17:36'),
(8, 1, 2, 2, 'Cierre definitivo solicitado por primer contacto', 'El gestor asignado marcó Caso 1 · etapa 2 como resuelto en primer contacto y solicitó el cierre definitivo del ticket. Las etapas siguientes no se activarán si el solicitante aprueba. El indicador SLA quedó cortado en 0 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-11 16:17:36. Solución: Reinicio computador. Observación: funciono cambiandole el cargador.', '2026-08-11 12:18:23'),
(9, 1, 2, 9, 'Atención reabierta', 'El creador reabrió . Se invalidó el corte del 2026-08-11 12:18:23 por 0 minuto(s), resultado dentro_sla. Al reabrir acumulaba 511 minuto(s) hábil(es), incluido el tiempo de espera. Nuevo vencimiento visible: 2026-08-12 10:49:19. Motivo de reapertura: prueba.', '2026-08-12 10:49:19'),
(10, 1, 4, 2, 'Ticket abierto por derivación', 'Se abrió el Ticket 2.1 desde etapa 2 del Caso 1. Destino: Talento Humano / Ingreso. Motivo: prueba', '2026-08-12 11:02:14'),
(11, 1, 2, 2, 'Derivación creada', 'El caso 1 pausó su SLA y creó 1 caso(s) hijo(s): 2.1 (Talento Humano / Ingreso).', '2026-08-12 11:02:14'),
(12, 1, 4, 2, 'Atención marcada como lista', 'El gestor asignado marcó Ticket 2.1 como listo para revisión. El indicador SLA quedó cortado en 0 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-17 18:00:00. Solución: Exitoso. Observación: prueba.', '2026-08-12 11:09:37'),
(13, 1, 4, 2, 'Atención cerrada', 'Se cerró Ticket 2.1. Talento Humano / Ingreso finalizó su atención dentro del SLA. Solución: Exitoso. Observación: prueba. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-12 11:11:46'),
(14, 1, 2, 2, 'Caso padre reanudado', 'Todos los casos hijos finalizaron. Se reanudó TICs / Soporte técnico general con 0 minuto(s) hábil(es) restantes.', '2026-08-12 11:11:46'),
(15, 1, 5, 2, 'Ticket abierto por derivación', 'Se abrió el Ticket 2.2 desde etapa 2 del Caso 1. Destino: Talento Humano / Ingreso. Motivo: prueba', '2026-08-12 11:12:07'),
(16, 1, 2, 2, 'Derivación creada', 'El caso 1 pausó su SLA y creó 1 caso(s) hijo(s): 2.2 (Talento Humano / Ingreso).', '2026-08-12 11:12:07'),
(17, 1, 5, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-12 11:21:14'),
(18, 1, 5, 2, 'Atención marcada como lista', 'El gestor asignado marcó Ticket 2.2 como listo para revisión. El indicador SLA quedó cortado en 0 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-17 18:00:00. Solución: Exitoso. Observación: prueba.', '2026-08-12 11:21:24'),
(19, 1, 5, 2, 'Atención cerrada', 'Se cerró Ticket 2.2. Talento Humano / Ingreso finalizó su atención dentro del SLA. Solución: Exitoso. Observación: prueba. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-12 11:21:36'),
(20, 1, 2, 2, 'Caso padre reanudado', 'Todos los casos hijos finalizaron. Se reanudó TICs / Soporte técnico general con 0 minuto(s) hábil(es) restantes.', '2026-08-12 11:21:36'),
(21, 1, 2, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-12 11:23:22'),
(22, 1, 2, 2, 'Cierre definitivo solicitado por primer contacto', 'El gestor asignado marcó Caso 1 · etapa 2 como resuelto en primer contacto y solicitó el cierre definitivo del ticket. Las etapas siguientes no se activarán si el solicitante aprueba. El indicador SLA quedó cortado en 528 minuto(s) hábil(es), con resultado fuera del SLA. El vencimiento visible se conserva en 2026-08-12 11:21:36. Solución: Reinicio computador. Observación: prueba.', '2026-08-12 11:26:46'),
(23, 1, 2, 9, 'Atención reabierta', 'El creador reabrió . Se invalidó el corte del 2026-08-12 11:26:46 por 528 minuto(s), resultado fuera_sla. Al reabrir acumulaba 530 minuto(s) hábil(es), incluido el tiempo de espera. Nuevo vencimiento visible: 2026-08-12 11:28:49. Motivo de reapertura: prueba.', '2026-08-12 11:28:49'),
(24, 1, 6, 2, 'Ticket abierto por derivación', 'Se abrió el Ticket 2.3 desde etapa 2 del Caso 1. Destino: Talento Humano / Ingreso. Motivo: prueba', '2026-08-12 11:29:58'),
(25, 1, 2, 2, 'Derivación creada', 'El caso 1 pausó su SLA y creó 1 caso(s) hijo(s): 2.3 (Talento Humano / Ingreso).', '2026-08-12 11:29:58'),
(26, 1, 6, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-12 11:30:24'),
(27, 1, 6, 2, 'Atención marcada como lista', 'El gestor asignado marcó Ticket 2.3 como listo para revisión. El indicador SLA quedó cortado en 0 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-17 18:00:00. Solución: Exitoso. Observación: prueba.', '2026-08-12 11:30:39'),
(28, 1, 6, 2, 'Atención cerrada', 'Se cerró Ticket 2.3. Talento Humano / Ingreso finalizó su atención dentro del SLA. Solución: Exitoso. Observación: prueba. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-12 11:36:23'),
(29, 1, 2, 2, 'Caso padre reanudado', 'Todos los casos hijos finalizaron. Se reanudó TICs / Soporte técnico general con 0 minuto(s) hábil(es) restantes.', '2026-08-12 11:36:23'),
(30, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-13 15:43:46'),
(31, 1, 2, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-13 15:48:01'),
(32, 1, 2, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-13 15:49:32'),
(33, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-13 15:50:45'),
(34, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-13 15:57:34'),
(35, 2, 7, 9, 'Caso principal abierto', 'Se creó el Caso 2 y se activó la etapa 1 para Adquisiciones / Solicitud de compra. Asunto: Necesito 3 computadores. Solicitud: bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', '2026-08-13 16:17:33'),
(36, 2, 7, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 2 · etapa 1.', '2026-08-13 16:19:50'),
(37, 2, 7, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 2 · etapa 1.', '2026-08-13 16:20:33'),
(38, 2, 7, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 2 · etapa 1.', '2026-08-13 16:21:53'),
(39, 2, 7, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 2 · etapa 1.', '2026-08-13 16:22:32'),
(40, 2, 7, 4, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 2 · etapa 1.', '2026-08-13 16:30:48'),
(41, 2, 7, 4, 'Cierre definitivo solicitado por primer contacto', 'El gestor asignado marcó Caso 2 · etapa 1 como resuelto en primer contacto y solicitó el cierre definitivo del ticket. Las etapas siguientes no se activarán si el solicitante aprueba. El indicador SLA quedó cortado en 14 minuto(s) hábil(es), con resultado dentro del SLA. El vencimiento visible se conserva en 2026-08-14 10:17:32. Solución: Compra de computador. Observación: HOLA.', '2026-08-13 16:32:29'),
(42, 2, 7, 9, 'Ticket cerrado por resolución en primer contacto', 'El solicitante aprobó el cierre definitivo solicitado por el gestor. Se cancelaron 0 etapa(s) futura(s) que no habían iniciado.', '2026-08-13 16:34:23'),
(43, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 07:56:46'),
(44, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 07:57:38'),
(45, 1, 2, 2, 'Checklist actualizado', 'El gestor actualizó el checklist de la etapa activa.', '2026-08-14 07:58:14'),
(46, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:00:53'),
(47, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:01:06'),
(48, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:14:03'),
(49, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:44:31'),
(50, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:44:38'),
(51, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:44:41'),
(52, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:44:43'),
(53, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:44:45'),
(54, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:44:47'),
(55, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:44:50'),
(56, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:45:06'),
(57, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 08:45:12'),
(58, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 09:00:12'),
(59, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 09:00:16'),
(60, 1, 2, 2, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 2.', '2026-08-14 09:00:31'),
(61, 1, 2, 2, 'Atención marcada como lista', 'El gestor asignado marcó Caso 1 · etapa 2 como listo para revisión. El indicador SLA quedó cortado en 1596 minuto(s) hábil(es), con resultado fuera del SLA. El vencimiento visible se conserva en 2026-08-12 11:36:23. Solución: Reinicio computador. Observación: prueba.', '2026-08-14 09:21:44'),
(62, 1, 2, 9, 'Atención cerrada', 'Se cerró Caso 1 · etapa 2. TICs / Soporte técnico general finalizó su atención fuera del SLA. Solución: Reinicio computador. Observación: prueba. Calificación: gestión 5/5 y tiempo 5/5.', '2026-08-14 09:22:37'),
(63, 1, 3, 9, 'Caso heredado habilitado', 'Se habilitó TICs / asignacion de computador.', '2026-08-14 09:22:37'),
(64, 1, 3, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 3.', '2026-08-14 09:23:45'),
(65, 1, 3, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 3.', '2026-08-14 10:26:34'),
(66, 1, 3, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 3.', '2026-08-14 10:26:42'),
(67, 1, 3, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 3.', '2026-08-14 10:34:15'),
(68, 1, 3, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 3.', '2026-08-14 10:34:16'),
(69, 1, 3, 9, 'Comunicación con el solicitante registrada', 'Se agregó un mensaje o archivo al chat de Caso 1 · etapa 3.', '2026-08-14 10:34:18');

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
(6, 9, 'no hubo solucion remota', 'no hubo solucion remota tocó ver el computador en fisico', 'activo', 1, 1, 1, '2026-08-05 15:23:35', '2026-08-05 20:23:35'),
(7, 8, 'Exitoso', 'Cuando se logra el ingreso de la persona sin novedades.', 'activo', 1, 1, 1, '2026-08-12 11:07:49', '2026-08-12 16:07:49'),
(8, 11, 'Exitosa', 'Reparación exitosa', 'activo', 1, 1, 1, '2026-08-12 15:59:05', '2026-08-12 20:59:05'),
(9, 12, 'Resuelto', 'Cuando se soluciono el incidente sin novedad', 'activo', 1, 1, 1, '2026-08-12 16:02:13', '2026-08-12 21:02:13');

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

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO `tickets` (`id_ticket`, `id_pais_operacion`, `titulo`, `descripcion`, `tipo_solicitud`, `estado`, `urgencia`, `prioridad`, `id_usuario`, `id_tecnico`, `id_servicio`, `id_proceso`, `estado_flujo`, `id_etapa_actual`, `fecha_creacion`, `fecha_finalizacion`, `esperando_solicitante_desde`, `cierre_tipo`, `motivo_cierre`, `actualizado_en`) VALUES
(1, 1, 'prueba', 'prueba', 'requerimiento', 'en_proceso', 'media', 'media', 9, 2, 7, 9, 'en_proceso', 3, '2026-08-11 12:03:30', NULL, NULL, NULL, NULL, '2026-08-18 20:59:47'),
(2, 1, 'Necesito 3 computadores', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'requerimiento', 'cerrada', 'media', 'media', 9, 4, 1, 5, 'cerrado', NULL, '2026-08-13 16:17:32', '2026-08-13 16:34:23', NULL, 'aprobacion_por_caso', 'Cierre definitivo aprobado por resolución en primer contacto.', '2026-08-13 21:34:23');

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
-- Volcado de datos para la tabla `ticket_etapas`
--

INSERT INTO `ticket_etapas` (`id_ticket_etapa`, `id_ticket_etapa_padre`, `id_ticket`, `id_proceso_etapa`, `nivel`, `orden`, `id_catalogo`, `catalogo_nombre`, `id_servicio`, `servicio_nombre`, `id_gestor`, `gestor_nombre`, `id_sla`, `sla_nombre`, `sla_tiempo`, `sla_unidad`, `sla_minutos_total`, `sla_minutos_consumidos`, `estado`, `fecha_activacion`, `fecha_vencimiento`, `fecha_ultima_reanudacion`, `fecha_pausa`, `fecha_marcado_listo`, `minutos_hasta_listo`, `resultado_sla_listo`, `marcado_listo_por`, `fecha_ultima_reapertura`, `cantidad_reaperturas`, `cantidad_pausas`, `fecha_finalizacion`, `minutos_atencion`, `resultado_sla`, `id_solucion`, `solucion_nombre`, `comentario_cierre`, `solicita_cierre_definitivo`, `motivo_derivacion`, `completado_por`, `creado_por`, `creado_en`, `actualizado_en`) VALUES
(1, NULL, 1, 16, 0, 1, 6, 'TICs', 9, 'Inconvenientes tecnologicos', 4, 'Gestor 02', 4, '4 días', 4, 'dias', 2400, 0, 'completada', '2026-08-11 12:03:30', '2026-08-17 18:00:00', NULL, NULL, '2026-08-11 12:13:04', 0, 'dentro_sla', 4, NULL, 0, 0, '2026-08-11 12:17:36', 0, 'dentro_sla', 6, 'no hubo solucion remota', 'El computador no enciende.', 0, NULL, 9, 9, '2026-08-11 12:03:30', '2026-08-11 17:17:36'),
(2, NULL, 1, 17, 0, 2, 6, 'TICs', 6, 'Soporte técnico general', 2, 'Gestor 01', 2, 'SLA prioritario - 4 horas', 4, 'horas', 240, 1596, 'completada', '2026-08-11 12:17:36', '2026-08-12 11:36:23', NULL, NULL, '2026-08-14 09:21:44', 1596, 'fuera_sla', 2, '2026-08-12 11:28:49', 2, 3, '2026-08-14 09:22:37', 1596, 'fuera_sla', 1, 'Reinicio computador', 'prueba', 0, NULL, 9, 9, '2026-08-11 12:03:30', '2026-08-14 14:22:37'),
(3, NULL, 1, 19, 0, 3, 6, 'TICs', 7, 'asignacion de computador', 2, 'Gestor 01', 4, '4 días', 4, 'dias', 2400, 0, 'pendiente', '2026-08-14 09:22:37', '2026-08-20 18:00:00', '2026-08-14 09:22:37', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 'sin_iniciar', NULL, NULL, NULL, 0, NULL, NULL, 9, '2026-08-11 12:03:30', '2026-08-14 14:22:37'),
(4, 2, 1, 12, 1, 4, 5, 'Talento Humano', 8, 'Ingreso', 2, 'Gestor 01', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-12 11:02:14', '2026-08-17 18:00:00', NULL, NULL, '2026-08-12 11:09:37', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-12 11:11:46', 0, 'dentro_sla', 7, 'Exitoso', 'prueba', 0, 'prueba', 2, 2, '2026-08-12 11:02:14', '2026-08-12 16:11:46'),
(5, 2, 1, 12, 1, 5, 5, 'Talento Humano', 8, 'Ingreso', 2, 'Gestor 01', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-12 11:12:07', '2026-08-17 18:00:00', NULL, NULL, '2026-08-12 11:21:24', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-12 11:21:36', 0, 'dentro_sla', 7, 'Exitoso', 'prueba', 0, 'prueba', 2, 2, '2026-08-12 11:12:07', '2026-08-12 16:21:36'),
(6, 2, 1, 12, 1, 6, 5, 'Talento Humano', 8, 'Ingreso', 2, 'Gestor 01', 3, 'SLA estándar - 3 días', 3, 'dias', 1800, 0, 'completada', '2026-08-12 11:29:58', '2026-08-17 18:00:00', NULL, NULL, '2026-08-12 11:30:39', 0, 'dentro_sla', 2, NULL, 0, 0, '2026-08-12 11:36:23', 0, 'dentro_sla', 7, 'Exitoso', 'prueba', 0, 'prueba', 2, 2, '2026-08-12 11:29:58', '2026-08-12 16:36:23'),
(7, NULL, 2, 7, 0, 1, 1, 'Adquisiciones', 1, 'Solicitud de compra', 4, 'Gestor 02', 2, 'SLA prioritario - 4 horas', 4, 'horas', 240, 14, 'completada', '2026-08-13 16:17:32', '2026-08-14 10:17:32', NULL, NULL, '2026-08-13 16:32:29', 14, 'dentro_sla', 4, NULL, 0, 0, '2026-08-13 16:34:23', 14, 'dentro_sla', 4, 'Compra de computador', 'HOLA', 1, NULL, 9, 9, '2026-08-13 16:17:32', '2026-08-13 21:34:23');

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
-- Volcado de datos para la tabla `ticket_etapa_checklist`
--

INSERT INTO `ticket_etapa_checklist` (`id_ticket_checklist`, `id_ticket_etapa`, `id_checklist_plantilla`, `nombre`, `descripcion`, `obligatorio`, `requiere_evidencia`, `orden`, `completado`, `observacion`, `evidencia_ruta`, `completado_por`, `completado_en`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 3, 'Documentos diligenciados', NULL, 1, 0, 1, 1, NULL, NULL, 4, '2026-08-11 12:12:38', '2026-08-11 12:03:30', '2026-08-11 17:12:38'),
(2, 1, 4, '¿se generaron las validaciones correspondientes?', NULL, 1, 0, 2, 1, NULL, NULL, 4, '2026-08-11 12:12:38', '2026-08-11 12:03:30', '2026-08-11 17:12:38'),
(3, 2, 7, '¿se generaron las validaciones correspondientes?', NULL, 1, 0, 1, 1, NULL, NULL, 2, '2026-08-14 07:58:14', '2026-08-12 10:53:54', '2026-08-14 12:58:14'),
(4, 5, 8, '¿Documentos diligenciados?', NULL, 1, 0, 1, 1, NULL, NULL, 2, '2026-08-12 11:21:14', '2026-08-12 11:18:39', '2026-08-12 16:21:14'),
(5, 6, 8, '¿Documentos diligenciados?', NULL, 1, 0, 1, 1, NULL, NULL, 2, '2026-08-12 11:30:24', '2026-08-12 11:29:58', '2026-08-12 16:30:24');

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
-- Estructura de tabla para la tabla `ticket_notificaciones_email_preferencias`
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
-- Volcado de datos para la tabla `ticket_notificaciones_email_preferencias`
--

INSERT INTO `ticket_notificaciones_email_preferencias` (`id_preferencia`, `id_ticket`, `id_usuario`, `habilitada`, `creada_en`, `actualizada_en`) VALUES
(1, 2, 9, 1, '2026-08-13 16:18:00', '2026-08-13 21:18:07'),
(3, 1, 2, 0, '2026-08-14 08:00:57', '2026-08-14 13:00:57'),
(4, 1, 9, 1, '2026-08-14 11:22:32', '2026-08-14 16:22:34');

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
(1, 'ADMIN001', 'Administrador', 'Administración', 'N/A', 'N/A', 'admin@gmail.com', 'Cuenta administradora inicial del sistema.', 'No aplica', 'Conectar TV', '$2y$10$f.3F2LkJXmXsjG5liWUSBeZitvj8TuxpMIj2zahqdwbdk5hy49NCe', 1, NULL, NULL, NULL, NULL, 'activo', '2026-07-31 09:00:22', '2026-08-11 16:25:17'),
(2, '101010', 'Gestor 01', '0', 'IGAM', 'HNAL', 'jhonnytor@gmail.com', '.', 'Bogotá', 'millenium', '$2y$10$F8W20yxKzWWiTrA87yaPWOWrwyOKs1rgT1fVZUa1xkU0iyUBGTv4u', 2, 1, 1, 9, 2, 'activo', '2026-07-31 09:01:38', '2026-08-14 16:24:41'),
(4, '2020', 'Gestor 02', '0', 'IGAM', 'HNAL', 'jhonnytorrez99@gmail.com', '0', 'Bogotá', 'millenium', '$2y$10$wsYV31LKg.AikWnlB2/Lk.xdpmGApV41fYkUANv2hb3H4gedAygmy', 2, 1, 1, 9, 2, 'activo', '2026-07-31 09:03:10', '2026-08-11 16:24:53'),
(7, '1010', 'Gestor2020', '0', '0', '0', 'jhonnytorrez@gmail.com', '0', 'lima', '0', '$2y$10$KxaTqZXsz1258F3ImiySZel205sFWWsYXEEeqxf/0qheV4Y92PJRy', 2, 2, 34, 35, 36, 'activo', '2026-08-05 08:55:32', '2026-08-18 20:32:22'),
(8, '10101010', 'Jhonny', '0', '0', '0', 'jhonnytorre@gmail.com', '0', 'lima', '0', '$2y$10$3mJ3CrvjsN3xKH9Tg4Ovy.xbo/orbiSxUpi8U/R6XNLhYmPBrxdb6', 3, 2, 34, 35, 36, 'activo', '2026-08-05 08:56:53', '2026-08-18 20:32:33'),
(9, '00', 'solicitante', '0', '0', '0', 'solicitante@conectar.com', '0', '0', '0', '$2y$10$NHRICdCTv7OvHdiwM6nPkOxySufUHCV3YIjBK8jM1dNn7JuYwFf3i', 3, 1, NULL, NULL, NULL, 'activo', '2026-08-05 14:40:16', '2026-08-14 12:59:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_preferencias_interfaz`
--

CREATE TABLE `usuario_preferencias_interfaz` (
  `id_usuario` int(11) NOT NULL,
  `tema` varchar(40) NOT NULL DEFAULT 'corporativo',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario_preferencias_interfaz`
--

INSERT INTO `usuario_preferencias_interfaz` (`id_usuario`, `tema`, `actualizado_en`) VALUES
(1, 'azul_rosado', '2026-08-18 20:45:38'),
(2, 'rojo_negro', '2026-08-14 16:11:57'),
(4, 'rojo_negro', '2026-08-18 20:46:16'),
(9, 'arena_terracota', '2026-08-14 17:13:41');

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
-- Indices de la tabla `recuperaciones_password`
--
ALTER TABLE `recuperaciones_password`
  ADD PRIMARY KEY (`id_recuperacion`),
  ADD KEY `idx_recuperacion_usuario_activa` (`id_usuario`,`usado_en`,`expira_en`),
  ADD KEY `idx_recuperacion_ip_fecha` (`solicitado_ip_hash`,`creado_en`);

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
-- Indices de la tabla `ticket_notificaciones_email_preferencias`
--
ALTER TABLE `ticket_notificaciones_email_preferencias`
  ADD PRIMARY KEY (`id_preferencia`),
  ADD UNIQUE KEY `uq_preferencia_ticket_usuario` (`id_ticket`,`id_usuario`),
  ADD KEY `idx_preferencia_usuario` (`id_usuario`,`habilitada`);

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
-- Indices de la tabla `usuario_preferencias_interfaz`
--
ALTER TABLE `usuario_preferencias_interfaz`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `catalogos`
--
ALTER TABLE `catalogos`
  MODIFY `id_catalogo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `configuraciones_servicio`
--
ALTER TABLE `configuraciones_servicio`
  MODIFY `id_opcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

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
  MODIFY `id_notificacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de la tabla `procesos`
--
ALTER TABLE `procesos`
  MODIFY `id_proceso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `proceso_etapas`
--
ALTER TABLE `proceso_etapas`
  MODIFY `id_proceso_etapa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `proceso_etapa_checklist`
--
ALTER TABLE `proceso_etapa_checklist`
  MODIFY `id_checklist` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `recuperaciones_password`
--
ALTER TABLE `recuperaciones_password`
  MODIFY `id_recuperacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `sla`
--
ALTER TABLE `sla`
  MODIFY `id_sla` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `solicitud_actividades`
--
ALTER TABLE `solicitud_actividades`
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_adjuntos`
--
ALTER TABLE `solicitud_adjuntos`
  MODIFY `id_adjunto` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `solicitud_calificaciones`
--
ALTER TABLE `solicitud_calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `solicitud_comunicaciones`
--
ALTER TABLE `solicitud_comunicaciones`
  MODIFY `id_comunicacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  MODIFY `id_historial` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT de la tabla `solicitud_vinculos`
--
ALTER TABLE `solicitud_vinculos`
  MODIFY `id_vinculo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `soluciones_servicio`
--
ALTER TABLE `soluciones_servicio`
  MODIFY `id_solucion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id_ticket` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `ticket_etapas`
--
ALTER TABLE `ticket_etapas`
  MODIFY `id_ticket_etapa` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `ticket_etapa_checklist`
--
ALTER TABLE `ticket_etapa_checklist`
  MODIFY `id_ticket_checklist` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `ticket_notificaciones_email_preferencias`
--
ALTER TABLE `ticket_notificaciones_email_preferencias`
  MODIFY `id_preferencia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- Filtros para la tabla `ticket_notificaciones_email_preferencias`
--
ALTER TABLE `ticket_notificaciones_email_preferencias`
  ADD CONSTRAINT `fk_preferencia_email_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_preferencia_email_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_ciudad` FOREIGN KEY (`id_ciudad`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_pais` FOREIGN KEY (`id_pais`) REFERENCES `configuraciones_servicio` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_pais_operacion` FOREIGN KEY (`id_pais_operacion`) REFERENCES `paises_operacion` (`id_pais_operacion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_preferencias_interfaz`
--
ALTER TABLE `usuario_preferencias_interfaz`
  ADD CONSTRAINT `fk_preferencia_interfaz_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
