<?php
/**
 * Conexión única a PostgreSQL mediante PDO.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/../config/config.php';
        $c   = $cfg['db'];
        $dsn = "pgsql:host={$c['host']};port={$c['port']};dbname={$c['nombre']}";
        try {
            $pdo = new PDO($dsn, $c['usuario'], $c['clave'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET client_encoding TO 'UTF8'");
        } catch (PDOException $ex) {
            // El detalle técnico va al log, nunca a la pantalla
            error_log('Error de conexión: ' . $ex->getMessage());
            http_response_code(500);
            exit('No se pudo conectar con la base de datos. Intente más tarde.');
        }
    }
    return $pdo;
}
