<?php
require __DIR__ . '/../app/bootstrap.php';
$yo = requerir_permiso('usuarios.php'); // solo el rol Admin tiene este permiso

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'desbloquear') {
    verificar_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id !== false) {
        $st = db()->prepare('UPDATE usuarios SET bloqueado = FALSE, intentos_fallidos = 0 WHERE id = :id');
        $st->execute([':id' => $id]);
        flash('exito', 'Usuario desbloqueado. Sus intentos fallidos volvieron a cero.');
    }
    redirigir('usuarios.php');
}

$usuarios = db()->query(
    'SELECT u.id, u.usuario, u.nombre, r.nombre AS rol, u.intentos_fallidos, u.bloqueado, u.ultimo_acceso
       FROM usuarios u JOIN roles r ON r.id = u.rol_id ORDER BY u.usuario'
)->fetchAll();

$titulo = 'Usuarios';
require __DIR__ . '/../app/views/header.php';
?>
<p>Los usuarios se registran previamente mediante el script SQL. Desde aquí el administrador puede desbloquear cuentas.</p>
<table>
    <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th class="num">Intentos fallidos</th><th>Estado</th><th>Último acceso</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $us): ?>
        <tr>
            <td><?= e($us['usuario']) ?></td>
            <td><?= e($us['nombre']) ?></td>
            <td><?= e($us['rol']) ?></td>
            <td class="num"><?= (int) $us['intentos_fallidos'] ?></td>
            <td><?= $us['bloqueado'] ? '<span class="etiqueta roja">Bloqueado</span>' : '<span class="etiqueta verde">Activo</span>' ?></td>
            <td><?= $us['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($us['ultimo_acceso']))) : '-' ?></td>
            <td>
                <?php if ($us['bloqueado']): ?>
                <form method="post" class="en-linea">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="desbloquear">
                    <input type="hidden" name="id" value="<?= (int) $us['id'] ?>">
                    <button class="boton chico">Desbloquear</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
