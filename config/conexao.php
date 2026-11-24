<?php
$env = parse_ini_file(__DIR__ . '/.env');

if ($env === false) {
    die('Erro ao carregar configurações do ambiente.');
}

try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'],
        $env['DB_PASS'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Erro de conexão com o banco de dados.');
}
unset($host, $dbname, $dbusuario, $dbsenha);