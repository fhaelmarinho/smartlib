<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMART lib</title>
    <link rel="stylesheet" href="./asset/css/style.css">
</head>

<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start(); 
}
$prefixo = (str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/') || str_contains($_SERVER['PHP_SELF'] ?? '', '/auth/')) ? '../' : '';
?>
<header class="site-header">
    <a class="logo" href="<?= $prefixo ?>index.php">SMART<span>lib</span></a>
    <nav class="site-nav">
        <a href="index.php">Início</a>
        <a href="">Cadastrar</a>
        <a href="">Login</a>
    </nav>
</header>