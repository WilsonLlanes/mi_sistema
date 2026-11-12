<?php
require __DIR__ . '/../app/bootstrap.php';
$u = requerir_permiso('dashboard.php');

$resumen = db()->query(
    "SELECT (SELECT COUNT(*) FROM productos) AS productos,
            (SELECT COALESCE(SUM(stock), 0) FROM productos) AS unidades,
            (SELECT COUNT(*) FROM compras) AS compras,
            (SELECT COALESCE(SUM(total), 0) FROM compras
              WHERE date_trunc('month', fecha) = date_trunc('month', CURRENT_DATE)) AS total_mes"
)->fetch();

$titulo = 'Inicio';
require __DIR__ . '/../app/views/header.php';
?>
<div class="tarjetas">
    <div class="tarjeta"><span><?= (int) $resumen['productos'] ?></span>Productos</div>
    <div class="tarjeta"><span><?= (int) $resumen['unidades'] ?></span>Unidades en stock</div>
    <div class="tarjeta"><span><?= (int) $resumen['compras'] ?></span>Compras registradas</div>
    <div class="tarjeta"><span><?= gs($resumen['total_mes']) ?></span>Gs. comprados este mes</div>
</div>
<p>Rol actual: <strong><?= e($u['rol']) ?></strong>. El menú de la izquierda muestra solo las opciones habilitadas para este rol.</p>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
