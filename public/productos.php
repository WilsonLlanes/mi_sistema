<?php
require __DIR__ . '/../app/bootstrap.php';
requerir_permiso('productos.php');

// Baja de producto (solo por POST y con token CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    verificar_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        flash('error', 'Producto no válido.');
    } else {
        try {
            $st = db()->prepare('DELETE FROM productos WHERE id = :id');
            $st->execute([':id' => $id]);
            flash($st->rowCount() ? 'exito' : 'error',
                  $st->rowCount() ? 'Producto eliminado.' : 'El producto no existe.');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23503') { // violación de clave foránea
                flash('error', 'No se puede eliminar: el producto ya figura en compras registradas.');
            } else {
                error_log($ex->getMessage());
                flash('error', 'No se pudo eliminar el producto.');
            }
        }
    }
    redirigir('productos.php');
}

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$st = db()->prepare(
    'SELECT id, descripcion, stock, precio_compra FROM productos
      WHERE descripcion ILIKE :b ORDER BY descripcion'
);
$st->execute([':b' => '%' . $buscar . '%']);
$productos = $st->fetchAll();

$titulo = 'Productos';
require __DIR__ . '/../app/views/header.php';
?>
<div class="acciones">
    <a class="boton" href="producto_form.php">+ Nuevo producto</a>
    <form method="get" class="en-linea">
        <input type="text" name="buscar" placeholder="Buscar por descripción" value="<?= e($buscar) ?>" maxlength="100">
        <button class="boton secundario">Buscar</button>
    </form>
</div>
<table>
    <thead><tr><th>ID</th><th>Descripción</th><th class="num">Stock</th><th class="num">Precio de compra (Gs.)</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($productos as $p): ?>
        <tr>
            <td><?= (int) $p['id'] ?></td>
            <td><?= e($p['descripcion']) ?></td>
            <td class="num"><?= (int) $p['stock'] ?></td>
            <td class="num"><?= gs($p['precio_compra']) ?></td>
            <td>
                <a class="boton chico" href="producto_form.php?id=<?= (int) $p['id'] ?>">Editar</a>
                <form method="post" class="en-linea" onsubmit="return confirm('¿Eliminar este producto?');">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button class="boton chico peligro">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$productos): ?>
        <tr><td colspan="5">No hay productos que coincidan con la búsqueda.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
