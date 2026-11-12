<?php
require __DIR__ . '/../app/bootstrap.php';
requerir_permiso('productos.php');

$id = null;
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        flash('error', 'Producto no válido.');
        redirigir('productos.php');
    }
}

$datos = ['descripcion' => '', 'stock' => '0', 'precio_compra' => ''];
$errores = [];

if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = db()->prepare('SELECT descripcion, stock, precio_compra FROM productos WHERE id = :id');
    $st->execute([':id' => $id]);
    $fila = $st->fetch();
    if (!$fila) {
        flash('error', 'El producto no existe.');
        redirigir('productos.php');
    }
    $datos = $fila;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $datos['descripcion']   = trim(strip_tags((string) ($_POST['descripcion'] ?? '')));
    $datos['stock']         = trim((string) ($_POST['stock'] ?? ''));
    $datos['precio_compra'] = trim((string) ($_POST['precio_compra'] ?? ''));

    // Validaciones del lado servidor
    $largo = mb_strlen($datos['descripcion']);
    if ($largo === 0) {
        $errores[] = 'La descripción es obligatoria.';
    } elseif ($largo < 3 || $largo > 100) {
        $errores[] = 'La descripción debe tener entre 3 y 100 caracteres.';
    }
    $stock = filter_var($datos['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
    if ($stock === false) {
        $errores[] = 'El stock debe ser un número entero entre 0 y 1.000.000.';
    }
    $precio = filter_var($datos['precio_compra'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999999]]);
    if ($precio === false) {
        $errores[] = 'El precio de compra debe ser un número entero en guaraníes (sin puntos ni comas).';
    }

    if (!$errores) {
        try {
            // Alta o modificación mediante la función PL/pgSQL con sentencia preparada
            $st = db()->prepare('SELECT fn_producto_guardar(:id, :descripcion, :stock, :precio)');
            $st->bindValue(':id', $id, $id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $st->bindValue(':descripcion', $datos['descripcion']);
            $st->bindValue(':stock', $stock, PDO::PARAM_INT);
            $st->bindValue(':precio', $precio, PDO::PARAM_INT);
            $st->execute();
            flash('exito', $id ? 'Producto actualizado.' : 'Producto registrado.');
            redirigir('productos.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23505') { // valor duplicado
                $errores[] = 'Ya existe un producto con esa descripción.';
            } else {
                error_log($ex->getMessage());
                $errores[] = 'No se pudo guardar el producto.';
            }
        }
    }
}

$titulo = $id ? 'Editar producto' : 'Nuevo producto';
require __DIR__ . '/../app/views/header.php';
?>
<?php if ($errores): ?>
    <div class="alerta error"><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" class="formulario angosto" novalidate>
    <?= csrf_campo() ?>
    <label>Descripción *
        <input type="text" name="descripcion" required minlength="3" maxlength="100" value="<?= e($datos['descripcion']) ?>">
    </label>
    <label>Stock *
        <input type="number" name="stock" required min="0" step="1" value="<?= e($datos['stock']) ?>">
    </label>
    <label>Precio de compra (Gs.) *
        <input type="number" name="precio_compra" required min="0" step="1" value="<?= e($datos['precio_compra']) ?>">
    </label>
    <div>
        <button type="submit" class="boton">Guardar</button>
        <a href="productos.php" class="boton secundario">Cancelar</a>
    </div>
</form>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
