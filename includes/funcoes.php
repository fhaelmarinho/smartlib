<?php

// Escapa valores antes de exibi-los no HTML.
function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

// Exige que exista uma sessão autenticada.
function exigirLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

// Exige uma sessão autenticada com perfil de bibliotecário.
function exigirBibliotecario(): void
{
    exigirLogin();

    if (($_SESSION['usuario_tipo'] ?? '') !== 'bibliotecario') {
        header('Location: /index.php?acesso=negado');
        exit;
    }
}

// // Exige uma sessão autenticada com perfil de cliente.
// // Use esta função futuramente nas páginas exclusivas do cliente.
// function exigirCliente(): void
// {
//     exigirLogin();

//     if (($_SESSION['e_admin'] ?? '') !== 'cliente') {
//         header('Location: ../admin/produtos.php');
//         exit;
//     }
// }
