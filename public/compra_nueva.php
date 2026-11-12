<?php
require __DIR__ . '/../app/bootstrap.php';
$u = requerir_permiso('compras.php');

$proveedores = db()->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll();
$productos   = db()->query('SELECT id, descripcion, stock, precio_compra FROM productos ORDER BY descripcion')->fetchAll();

$errores = [];
$cab = ['fecha' => date('Y-m-d'), 'proveedor_id' => '', 'nro_factura' => ''];
$lineas = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    // ---- Validación de la cabecera ----
    $cab['fecha']        = (string) ($_POST['fecha'] ?? '');
    $cab['proveedor_id'] = (string) ($_POST['proveedor_id'] ?? '');
    $cab['nro_factura']  = trim(strip_tags((string) ($_POST['nro_factura'] ?? '')));

    $fecha = fecha_valida($cab['fecha']);
    if ($fecha === null) {
        $errores[] = 'La fecha no es válida.';
    } elseif ($fecha > date('Y-m-d')) {
        $errores[] = 'La fecha de la compra no puede ser futura.';
    }
    $proveedorId = filter_var($cab['proveedor_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($proveedorId === false) {
        $errores[] = 'Seleccione un proveedor.';
    }
    if (!preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{1,7}$/', $cab['nro_factura'])) {
        $errores[] = 'El número de factura debe tener el formato 001-001-0000123.';
    }

    // ---- Validación del detalle ----
    $ids   = (array) ($_POST['producto_id'] ?? []);
    $cants = (array) ($_POST['cantidad'] ?? []);
    $precs = (array) ($_POST['precio'] ?? []);
    $usados = [];
    foreach ($ids as $i => $pid) {
        $pid  = filter_var($pid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cant = filter_var($cants[$i] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        $prec = filter_var($precs[$i] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999999]]);
        $lineas[] = ['producto_id' => $pid ?: '', 'cantidad' => $cants[$i] ?? '', 'precio' => $precs[$i] ?? ''];
        $n = $i + 1;
        if ($pid === false || $cant === false || $prec === false) {
            $errores[] = "Línea $n: producto, cantidad (entero mayor a 0) y precio (entero) son obligatorios.";
            continue;
        }
        if (isset($usados[$pid])) {
            $errores[] = "Línea $n: el producto está repetido; sume la cantidad en una sola línea.";
            continue;
        }
        $usados[$pid] = ['cantidad' => $cant, 'precio' => $prec];
    }
    if (!$usados && !$errores) {
        $errores[] = 'Agregue al menos un producto al detalle.';
    }

    // ---- Grabación en una transacción ----
    if (!$errores) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare(
                'INSERT INTO compras (fecha, proveedor_id, usuario_id, nro_factura, total)
                 VALUES (:fecha, :prov, :usr, :fact, 0) RETURNING id'
            );
            $st->execute([':fecha' => $fecha, ':prov' => $proveedorId, ':usr' => $u['id'], ':fact' => $cab['nro_factura']]);
            $compraId = (int) $st->fetchColumn();

            $existe = $pdo->prepare('SELECT id FROM productos WHERE id = :id FOR UPDATE');
            $insDet = $pdo->prepare(
                'INSERT INTO compra_detalle (compra_id, producto_id, cantidad, precio_unitario, subtotal)
                 VALUES (:compra, :prod, :cant, :precio, :subtotal)'
            );
            $sumarStock = $pdo->prepare('UPDATE productos SET stock = stock + :cant WHERE id = :id');

            foreach ($usados as $pid => $l) {
                $existe->execute([':id' => $pid]);
                if (!$existe->fetchColumn()) {
                    throw new RuntimeException("El producto $pid ya no existe.");
                }
                $insDet->execute([
                    ':compra'   => $compraId,
                    ':prod'     => $pid,
                    ':cant'     => $l['cantidad'],
                    ':precio'   => $l['precio'],
                    ':subtotal' => $l['cantidad'] * $l['precio'],
                ]);
                // Al confirmar la compra se incrementa el stock del producto
                $sumarStock->execute([':cant' => $l['cantidad'], ':id' => $pid]);
            }

            // Se recalcula el total a partir del detalle guardado
            $tot = $pdo->prepare(
                'UPDATE compras SET total = (SELECT COALESCE(SUM(subtotal), 0) FROM compra_detalle WHERE compra_id = :id_det)
                  WHERE id = :id_compra RETURNING total'
            );
            $tot->execute([':id_det' => $compraId, ':id_compra' => $compraId]);
            $total = $tot->fetchColumn();

            $pdo->commit();
            flash('exito', "Compra N.º $compraId registrada por Gs. " . gs($total) . '. El stock fue actualizado.');
            redirigir('compra_ver.php?id=' . $compraId);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack(); // nada queda grabado a medias
            }
            if ($ex instanceof PDOException && $ex->getCode() === '23505') {
                $errores[] = 'Esa factura ya fue registrada para el proveedor seleccionado.';
            } else {
                error_log('Error al registrar compra: ' . $ex->getMessage());
                $errores[] = 'No se pudo registrar la compra. No se guardó ningún cambio.';
            }
        }
    }
}

if (!$lineas) {
    $lineas[] = ['producto_id' => '', 'cantidad' => '1', 'precio' => ''];
}

$titulo = 'Nueva compra';
require __DIR__ . '/../app/views/header.php';
?>
<?php if ($errores): ?>
    <div class="alerta error"><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" class="formulario" id="form-compra">
    <?= csrf_campo() ?>
    <div class="fila">
        <label>Fecha *
            <input type="date" name="fecha" required max="<?= date('Y-m-d') ?>" value="<?= e($cab['fecha']) ?>">
        </label>
        <label>Proveedor *
            <select name="proveedor_id" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($proveedores as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $cab['proveedor_id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>N.º de factura *
            <input type="text" name="nro_factura" required maxlength="20" placeholder="001-001-0000123"
                   pattern="[0-9]{3}-[0-9]{3}-[0-9]{1,7}" value="<?= e($cab['nro_factura']) ?>">
        </label>
    </div>

    <h2>Detalle</h2>
    <table id="detalle">
        <thead><tr><th>Producto</th><th class="num">Stock actual</th><th class="num">Cantidad</th><th class="num">Precio unit. (Gs.)</th><th class="num">Subtotal</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lineas as $l): ?>
            <tr>
                <td>
                    <select name="producto_id[]" class="producto" required>
                        <option value="">-- Producto --</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" data-precio="<?= (int) $p['precio_compra'] ?>" data-stock="<?= (int) $p['stock'] ?>"
                                <?= (string) $p['id'] === (string) $l['producto_id'] ? 'selected' : '' ?>><?= e($p['descripcion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="num stock">-</td>
                <td><input type="number" name="cantidad[]" class="cantidad" min="1" step="1" required value="<?= e($l['cantidad']) ?>"></td>
                <td><input type="number" name="precio[]" class="precio" min="0" step="1" required value="<?= e($l['precio']) ?>"></td>
                <td class="num subtotal">0</td>
                <td><button type="button" class="boton chico peligro quitar">Quitar</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4" class="num">Total</th><th class="num" id="total">0</th><th></th></tr></tfoot>
    </table>
    <p><button type="button" class="boton secundario" id="agregar">+ Agregar línea</button></p>
    <div>
        <button type="submit" class="boton">Confirmar compra</button>
        <a href="compras.php" class="boton secundario">Cancelar</a>
    </div>
</form>
<script src="assets/js/compra.js"></script>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
