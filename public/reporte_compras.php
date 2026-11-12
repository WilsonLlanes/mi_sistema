<?php
require __DIR__ . '/../app/bootstrap.php';
requerir_permiso('reporte_compras.php');

// ---- Filtros (todos validados) ----
$desde = fecha_valida($_GET['desde'] ?? '') ?? date('Y-m-01');
$hasta = fecha_valida($_GET['hasta'] ?? '') ?? date('Y-m-d');
$proveedorId = filter_var($_GET['proveedor_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$proveedorId = $proveedorId === false ? null : $proveedorId;
$aviso = '';
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
    $aviso = 'La fecha "desde" era posterior a "hasta"; se invirtieron.';
}

$sql = 'SELECT c.id, c.fecha, c.nro_factura, p.nombre AS proveedor, p.ruc, u.usuario,
               (SELECT COALESCE(SUM(d.cantidad), 0) FROM compra_detalle d WHERE d.compra_id = c.id) AS unidades,
               c.total
          FROM compras c
          JOIN proveedores p ON p.id = c.proveedor_id
          JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.fecha BETWEEN :desde AND :hasta';
$params = [':desde' => $desde, ':hasta' => $hasta];
if ($proveedorId !== null) {
    $sql .= ' AND c.proveedor_id = :prov';   // filtro opcional
    $params[':prov'] = $proveedorId;
}
$sql .= ' ORDER BY c.fecha, c.id';
$st = db()->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll();
$totalGeneral = array_sum(array_column($filas, 'total'));

// ---- Exportación CSV ----
if (($_GET['exportar'] ?? '') === 'csv') {
    $nombre = 'reporte_compras_' . $desde . '_al_' . $hasta . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel reconozca UTF-8
    fputcsv($out, ['N.º', 'Fecha', 'Factura', 'Proveedor', 'RUC', 'Usuario', 'Unidades', 'Total (Gs.)'], ';', '"', '');
    foreach ($filas as $f) {
        fputcsv($out, [$f['id'], date('d/m/Y', strtotime($f['fecha'])), $f['nro_factura'], $f['proveedor'],
                       $f['ruc'], $f['usuario'], $f['unidades'], $f['total']], ';', '"', '');
    }
    fputcsv($out, ['', '', '', '', '', '', 'TOTAL', $totalGeneral], ';', '"', '');
    fclose($out);
    exit;
}

$proveedores = db()->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll();
$titulo = 'Reporte de compras';
require __DIR__ . '/../app/views/header.php';
$qs = http_build_query(['desde' => $desde, 'hasta' => $hasta, 'proveedor_id' => $proveedorId, 'exportar' => 'csv']);
?>
<?php if ($aviso): ?><div class="alerta aviso"><?= e($aviso) ?></div><?php endif; ?>
<form method="get" class="formulario fila">
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    <label>Proveedor (opcional)
        <select name="proveedor_id">
            <option value="">Todos</option>
            <?php foreach ($proveedores as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= $proveedorId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="botones-filtro">
        <button class="boton">Filtrar</button>
        <a class="boton secundario" href="reporte_compras.php?<?= e($qs) ?>">Exportar CSV</a>
    </div>
</form>
<table>
    <thead><tr><th>N.º</th><th>Fecha</th><th>Factura</th><th>Proveedor</th><th>Usuario</th><th class="num">Unidades</th><th class="num">Total (Gs.)</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <tr>
            <td><?= (int) $f['id'] ?></td>
            <td><?= e(date('d/m/Y', strtotime($f['fecha']))) ?></td>
            <td><?= e($f['nro_factura']) ?></td>
            <td><?= e($f['proveedor']) ?></td>
            <td><?= e($f['usuario']) ?></td>
            <td class="num"><?= (int) $f['unidades'] ?></td>
            <td class="num"><?= gs($f['total']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$filas): ?><tr><td colspan="7">No hay compras en el período seleccionado.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><th colspan="6" class="num">Total del período (<?= count($filas) ?> compras)</th><th class="num"><?= gs($totalGeneral) ?></th></tr></tfoot>
</table>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
