<?php
/** @var string $titulo */
$u = usuario_actual();
$menu = menu_usuario();
$actual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Inicio') ?> | <?= e(config('nombre')) ?></title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<header class="barra">
    <span class="marca"><?= e(config('nombre')) ?></span>
    <?php if ($u): ?>
        <span class="usuario">
            <?= e($u['nombre']) ?> (<?= e($u['rol']) ?>)
            <a class="boton chico" href="logout.php">Cerrar sesión</a>
        </span>
    <?php endif; ?>
</header>
<div class="contenedor">
    <?php if ($u): ?>
    <nav class="menu">
        <?php foreach ($menu as $item): ?>
            <a href="<?= e($item['url']) ?>" class="<?= $item['url'] === $actual ? 'activo' : '' ?>">
                <?= e($item['titulo']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <main class="contenido">
        <h1><?= e($titulo ?? '') ?></h1>
        <?php foreach (obtener_flash() as $f): ?>
            <div class="alerta <?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div>
        <?php endforeach; ?>
