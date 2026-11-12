<?php
require __DIR__ . '/../app/bootstrap.php';
iniciar_sesion_segura();

if (usuario_actual()) {
    redirigir('dashboard.php');
}

$error = '';
$usuario = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave   = (string) ($_POST['clave'] ?? '');

    if ($usuario === '' || $clave === '' || mb_strlen($usuario) > 30) {
        $error = 'Complete usuario y contraseña.';
    } else {
        $error = intentar_login($usuario, $clave) ?? '';
        if ($error === '') {
            flash('exito', 'Bienvenido/a, ' . usuario_actual()['nombre'] . '.');
            redirigir('dashboard.php');
        }
    }
}
$titulo = 'Iniciar sesión';
require __DIR__ . '/../app/views/header.php';
?>
<form method="post" class="formulario angosto" autocomplete="off">
    <?= csrf_campo() ?>
    <?php if ($error !== ''): ?><div class="alerta error"><?= e($error) ?></div><?php endif; ?>
    <label>Usuario
        <input type="text" name="usuario" maxlength="30" required value="<?= e($usuario) ?>">
    </label>
    <label>Contraseña
        <input type="password" name="clave" required>
    </label>
    <button type="submit" class="boton">Ingresar</button>
</form>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
