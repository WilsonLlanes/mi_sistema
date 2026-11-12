<?php
// Copiar este archivo como config.php y completar los datos de conexión.
// Esta carpeta (app/) NO debe quedar dentro del alcance público del servidor.
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => '5432',
        'nombre'   => 'mi_sistema',
        'usuario'  => 'postgres',
        'clave'    => 'cambiar_esta_clave',
    ],
    'app' => [
        'nombre'           => 'Mini-sistema de Compras y Stock',
        'max_intentos'     => 3,     // intentos fallidos antes de bloquear el usuario
        'minutos_inactivo' => 30,    // cierre automático de la sesión por inactividad
    ],
];
