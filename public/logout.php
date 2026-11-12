<?php
require __DIR__ . '/../app/bootstrap.php';
iniciar_sesion_segura();
cerrar_sesion();
// Se abre una sesión nueva solo para mostrar el mensaje
iniciar_sesion_segura();
flash('exito', 'Sesión cerrada correctamente.');
redirigir('login.php');
