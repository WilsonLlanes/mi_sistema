<?php
/**
 * Sesiones seguras, autenticación, bloqueo por intentos y permisos por rol.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('MISISTEMASESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Cierre automático por inactividad
    $limite = (int) config('minutos_inactivo') * 60;
    if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > $limite) {
        cerrar_sesion();
        session_start();
        flash('aviso', 'La sesión se cerró por inactividad. Ingrese de nuevo.');
    }
    $_SESSION['ultima_actividad'] = time();
}

/**
 * Intenta autenticar. Devuelve null si todo salió bien o un mensaje de error.
 */
function intentar_login(string $usuario, string $clave): ?string
{
    $max = (int) config('max_intentos');
    $st  = db()->prepare(
        'SELECT u.id, u.usuario, u.nombre, u.password_hash, u.intentos_fallidos, u.bloqueado,
                u.rol_id, r.nombre AS rol
           FROM usuarios u JOIN roles r ON r.id = u.rol_id
          WHERE u.usuario = :usuario'
    );
    $st->execute([':usuario' => $usuario]);
    $u = $st->fetch();

    if (!$u) {
        // Mensaje genérico: no se revela si el usuario existe
        return 'Usuario o contraseña incorrectos.';
    }
    if ($u['bloqueado']) {
        return 'El usuario está bloqueado por intentos fallidos. Contacte al administrador.';
    }
    if (!password_verify($clave, $u['password_hash'])) {
        $intentos = $u['intentos_fallidos'] + 1;
        $bloquear = $intentos >= $max;
        $up = db()->prepare('UPDATE usuarios SET intentos_fallidos = :i, bloqueado = :b WHERE id = :id');
        $up->bindValue(':i', $intentos, PDO::PARAM_INT);
        $up->bindValue(':b', $bloquear, PDO::PARAM_BOOL);
        $up->bindValue(':id', $u['id'], PDO::PARAM_INT);
        $up->execute();
        if ($bloquear) {
            return "Contraseña incorrecta. Se superaron los $max intentos y el usuario quedó bloqueado.";
        }
        $restan = $max - $intentos;
        return "Usuario o contraseña incorrectos. Intentos restantes: $restan.";
    }

    // Login correcto: se reinicia el contador y se regenera el ID de sesión
    $up = db()->prepare('UPDATE usuarios SET intentos_fallidos = 0, ultimo_acceso = NOW() WHERE id = :id');
    $up->execute([':id' => $u['id']]);

    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'      => (int) $u['id'],
        'usuario' => $u['usuario'],
        'nombre'  => $u['nombre'],
        'rol_id'  => (int) $u['rol_id'],
        'rol'     => $u['rol'],
    ];
    unset($_SESSION['csrf']);
    return null;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
    session_destroy();
}

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** Ítems de menú permitidos para el rol del usuario (desde la BD). */
function menu_usuario(): array
{
    $u = usuario_actual();
    if (!$u) {
        return [];
    }
    $st = db()->prepare(
        'SELECT m.titulo, m.url
           FROM menu m JOIN permisos p ON p.menu_id = m.id
          WHERE p.rol_id = :rol
          ORDER BY m.orden'
    );
    $st->execute([':rol' => $u['rol_id']]);
    return $st->fetchAll();
}

function tiene_permiso(string $url): bool
{
    foreach (menu_usuario() as $item) {
        if ($item['url'] === $url) {
            return true;
        }
    }
    return false;
}

/**
 * Protege una página: exige sesión iniciada y permiso sobre la opción de menú.
 * Si el rol no tiene permiso, responde 403 aunque se escriba la URL a mano.
 */
function requerir_permiso(string $url): array
{
    iniciar_sesion_segura();
    $u = usuario_actual();
    if (!$u) {
        flash('aviso', 'Debe iniciar sesión para continuar.');
        redirigir('login.php');
    }
    if (!tiene_permiso($url)) {
        http_response_code(403);
        $titulo = 'Acceso denegado';
        require __DIR__ . '/../views/header.php';
        echo '<div class="alerta error">No tiene permiso para acceder a esta sección.</div>';
        require __DIR__ . '/../views/footer.php';
        exit;
    }
    return $u;
}
