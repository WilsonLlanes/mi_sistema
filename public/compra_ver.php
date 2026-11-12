<?php
require __DIR__ . '/../app/bootstrap.php';
requerir_permiso('compras.php');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$cab = false;
if ($id !== false) {
    $st = db()->prepare(
        'SELECT c.id, c.fecha, c.nro_factura, c.total, p.nombre AS proveedor, p.ruc, u.nombre AS usuario
           FROM compras c JOIN proveedores p ON p.id = c.proveedor_id JOIN usuarios u ON u.id = c.usuario_id
          WHERE c.id = :id'
    );
    $st->execute([':id' => $id]);
    $cab = $st->fetch();
}
if (!$cab) {
    flash('error', 'La compra no existe.');
    redirigir('compras.php');
}
$st = db()->prepare(
    'SELECT pr.descripcion, d.cantidad, d.precio_unitario, d.subtotal, pr.stock
       FROM compra_detalle d JOIN productos pr ON pr.id = d.producto_id
      WHERE d.compra_id = :id ORDER BY d.id'
);
$st->execute([':id' => $id]);
$detalle = $st->fetchAll();

$titulo = 'Compra N.º ' . $cab['id'];
require __DIR__ . '/../app/views/header.php';
?>
<p><strong>Fecha:</strong> <?= e(date('d/m/Y', strtotime($cab['fecha']))) ?> &nbsp;
   <strong>Factura:</strong> <?= e($cab['nro_factura']) ?> &nbsp;
   <strong>Proveedor:</strong> <?= e($cab['proveedor']) ?> (RUC <?= e($cab['ruc']) ?>) &nbsp;
   <strong>Registrada por:</strong> <?= e($cab['usuario']) ?></p>
<table>
    <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Precio unit. (Gs.)</th><th class="num">Subtotal (Gs.)</th><th class="num">Stock actual</th></tr></thead>
    <tbody>
    <?php foreach ($detalle as $d): ?>
        <tr>
            <td><?= e($d['descripcion']) ?></td>
            <td class="num"><?= (int) $d['cantidad'] ?></td>
            <td class="num"><?= gs($d['precio_unitario']) ?></td>
            <td class="num"><?= gs($d['subtotal']) ?></td>
            <td class="num"><?= (int) $d['stock'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="3" class="num">Total</th><th class="num"><?= gs($cab['total']) ?></th><th></th></tr></tfoot>
</table>
<p><a class="boton secundario" href="compras.php">Volver al listado</a></p>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
