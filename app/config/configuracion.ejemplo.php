<?php
declare(strict_types=1);

/**
 * Copie este archivo como configuracion.local.php y reemplace los valores.
 * configuracion.local.php está excluido de Git y nunca debe publicarse.
 */
return [
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_user' => 'mesa_usuario',
    'db_password' => 'REEMPLAZAR',
    'db_name' => 'mesa_servicio',
    'storage_path' => '/ruta/privada/mesa_servicio',

    // URL de producción que entrega el nodo Webhook de n8n.
    'n8n_webhook_url' => 'https://n8n.empresa.com/webhook/mesa-servicio-correo',

    // Debe coincidir con la credencial Header Auth del Webhook de n8n.
    'n8n_webhook_secret' => 'REEMPLAZAR_CON_UN_SECRETO_ALEATORIO_DE_64_CARACTERES',

    // Secreto diferente al del webhook. Protege los códigos guardados en BD.
    'recovery_pepper' => 'REEMPLAZAR_CON_OTRO_SECRETO_ALEATORIO_DE_64_CARACTERES',
];
