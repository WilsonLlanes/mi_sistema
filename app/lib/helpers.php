<?php
/**
 * Funciones auxiliares: escape de salida, mensajes flash, CSRF y formato.
 */
function config(string $clave)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/config.php';
    }
    return $cfg['app'][$clave] ?? null;
}

/** Escapa un valor para mostrarlo en HTML (previene XSS). */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtener_flash(): array
{
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verificar_csrf(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('Solicitud no válida (token CSRF incorrecto). Vuelva a cargar la página.');
    }
}

function gs($monto): string
{
    return number_format((float) $monto, 0, ',', '.');
}

/** Valida una fecha con formato AAAA-MM-DD; devuelve la fecha o null. */
function fecha_valida(?string $f): ?string
{
    if ($f === null || $f === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return ($d && $d->format('Y-m-d') === $f) ? $f : null;
}
