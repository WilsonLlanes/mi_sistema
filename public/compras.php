<?php
require __DIR__ . '/../app/bootstrap.php';
requerir_permiso('compras.php');

$compras = db()->query(
    'SELECT c.id, c.fecha, c.nro_factura, c.total, p.nombre AS proveedor, u.usuario,
            (SELECT COUNT(*) FROM compra_detalle d WHERE d.compra_id = c.id) AS items
       FROM compras c
       JOIN proveedores p ON p.id = c.proveedor_id
       JOIN usuarios u ON u.id = c.usuario_id
      ORDER BY c.fecha DESC, c.id DESC'
)->fetchAll();

$titulo = 'Compras';
require __DIR__ . '/../app/views/header.php';
?>
<div class="acciones"><a class="boton" href="compra_nueva.php">+ Nueva compra</a></div>
<table>
    <thead><tr><th>N.º</th><th>Fecha</th><th>Factura</th><th>Proveedor</th><th class="num">Ítems</th><th class="num">Total (Gs.)</th><th>Registrado por</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($compras as $c): ?>
        <tr>
            <td><?= (int) $c['id'] ?></td>
            <td><?= e(date('d/m/Y', strtotime($c['fecha']))) ?></td>
            <td><?= e($c['nro_factura']) ?></td>
            <td><?= e($c['proveedor']) ?></td>
            <td class="num"><?= (int) $c['items'] ?></td>
            <td class="num"><?= gs($c['total']) ?></td>
            <td><?= e($c['usuario']) ?></td>
            <td><a class="boton chico" href="compra_ver.php?id=<?= (int) $c['id'] ?>">Ver</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$compras): ?><tr><td colspan="8">Todavía no hay compras registradas.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
