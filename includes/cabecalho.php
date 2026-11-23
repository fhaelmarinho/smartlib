<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMART lib</title>
<?php
if (session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}
?>
    <link rel="stylesheet" href="/asset/css/style.css">
</head>

<header class="site-header">
    <a class="logo" href="/index.php">SMART<span>lib</span></a>
    <nav class="site-nav">
        <a href="/index.php">Início</a>
        <a href="/auth/cadastro.php">Cadastrar</a>
        <a href="/auth/login.php">Login</a>
    </nav>
</header>