<?php
// Punto de carga común para todas las páginas públicas

ini_set('display_errors', '0');   // en producción no se muestran errores
ini_set('log_errors', '1');
date_default_timezone_set('America/Asuncion');

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';
