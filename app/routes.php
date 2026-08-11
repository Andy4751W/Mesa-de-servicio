<?php
declare(strict_types=1);

/**
 * Relación única entre las URL públicas heredadas y el código organizado.
 * Para publicar un módulo nuevo, agréguelo aquí y cree su entrada mínima en
 * public/ con el mismo nombre.
 *
 * @return array<string, string>
 */
return [
    'actualizarTicket.php' => APP_ROOT . '/modules/tickets/actualizarTicket.php',
    'adminAcciones.php' => APP_ROOT . '/modules/tickets/adminAcciones.php',
    'asignarTicket.php' => APP_ROOT . '/modules/tickets/asignarTicket.php',
    'catalogos.php' => APP_ROOT . '/modules/catalogos/catalogos.php',
    'chat.php' => APP_ROOT . '/modules/tickets/chat.php',
    'configuraciones.php' => APP_ROOT . '/modules/admin/configuraciones.php',
    'consultarEncuesta.php' => APP_ROOT . '/modules/tickets/consultarEncuesta.php',
    'crearUsuarios.php' => APP_ROOT . '/modules/admin/crearUsuarios.php',
    'crearticket.php' => APP_ROOT . '/modules/tickets/crearticket.php',
    'descargarAdjunto.php' => APP_ROOT . '/modules/tickets/descargarAdjunto.php',
    'descargarSolicitudesExcel.php' => APP_ROOT . '/modules/tickets/descargarSolicitudesExcel.php',
    'diagnostico_relaciones.php' => APP_ROOT . '/modules/admin/diagnostico_relaciones.php',
    'editarUsuario.php' => APP_ROOT . '/modules/admin/editarUsuario.php',
    'editarcatalogos.php' => APP_ROOT . '/modules/catalogos/editarcatalogos.php',
    'enviarMensaje.php' => APP_ROOT . '/modules/tickets/enviarMensaje.php',
    'feriados.php' => APP_ROOT . '/modules/sla/feriados.php',
    'flujoTicket.php' => APP_ROOT . '/modules/tickets/flujoTicket.php',
    'imagenCatalogo.php' => APP_ROOT . '/modules/catalogos/imagenCatalogo.php',
    'imagenPerfil.php' => APP_ROOT . '/modules/perfil/imagenPerfil.php',
    'indicadores.php' => APP_ROOT . '/modules/reportes/indicadores.php',
    'login.php' => APP_ROOT . '/modules/auth/login.php',
    'logout.php' => APP_ROOT . '/modules/auth/logout.php',
    'panelAdmin.php' => APP_ROOT . '/modules/admin/panelAdmin.php',
    'seleccionarPais.php' => APP_ROOT . '/modules/admin/seleccionarPais.php',
    'panelGestor.php' => APP_ROOT . '/modules/tickets/panelGestor.php',
    'panelSolicitante.php' => APP_ROOT . '/modules/tickets/panelSolicitante.php',
    'perfil.php' => APP_ROOT . '/modules/perfil/perfil.php',
    'prioridades.php' => APP_ROOT . '/modules/admin/prioridades.php',
    'procesos.php' => APP_ROOT . '/modules/tickets/procesos.php',
    'Registro.php' => APP_ROOT . '/modules/admin/Registro.php',
    'servicios.php' => APP_ROOT . '/modules/catalogos/servicios.php',
    'sesionActividad.php' => APP_ROOT . '/security/sesionActividad.php',
    'sla.php' => APP_ROOT . '/modules/sla/sla.php',
    'solicitudes.php' => APP_ROOT . '/modules/tickets/solicitudes.php',
    'soluciones.php' => APP_ROOT . '/modules/catalogos/soluciones.php',
    'verificarActualizacionSolicitudes.php' => APP_ROOT . '/modules/sistema/verificarActualizacionSolicitudes.php',
    'verificarCalendarioSla.php' => APP_ROOT . '/modules/sla/verificarCalendarioSla.php',
    'verificarFlujos.php' => APP_ROOT . '/modules/sistema/verificarFlujos.php',
];
