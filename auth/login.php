<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        // Busca o usuário pelo e-mail
        $stmt = $pdo->prepare('SELECT id, nome, senha, tipo FROM usuarios WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $usuario = $stmt->fetch();

        // Verifica se existe e se a senha confere com o hash
        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $erro = 'E-mail ou senha incorretos.';
        } else {
            // ── Login bem-sucedido: salva dados na sessão ──
            $_SESSION['usuario_id']   = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            // Se for bibliotecário vai para a administração, senão para a home
            if ($usuario['tipo'] === 'bibliotecario') {
                header('Location: /admin/livros.php');
            } else {
                header('Location: /index.php');
            }
            exit;
        }
    }
}

require __DIR__ . '/../includes/cabecalho.php';
?>

<main>
    <section class="form-section">
        <div class="form-wrapper">
            <h1 class="form-title">Entrar</h1>

            <?php if ($erro): ?>
                <p class="msg-erro"><?= e($erro) ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    placeholder="seu@email.com" required>

                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha"
                    placeholder="••••••••" required>

                <button class="btn-search" type="submit">Entrar</button>
            </form>

            <p class="form-link">Não tem conta? <a href="../auth/cadastro.php">Cadastre-se</a></p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
