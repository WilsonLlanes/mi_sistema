<?php
require __DIR__ . '/../app/bootstrap.php';
iniciar_sesion_segura();
redirigir(usuario_actual() ? 'dashboard.php' : 'login.php');
