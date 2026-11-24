<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

$erro    = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome            = trim($_POST['nome'] ?? '');
    $matricula       = trim($_POST['matricula'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $senha           = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmacao'] ?? '';

    // valida se os campos estão preenchidos corretamente
    if ($nome === '' || $matricula === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';

    // cria a trava para que a senha tenha pelo menos 8 caracteres
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';

    // cria a trava caso a senha e a confirmação de senha não sejam iguais    
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';

    } else {
        // Verifica se a matrícula ou o e-mail já estão cadastrados
        $chk = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e OR matricula = :m');
        $chk->execute([':e' => $email, ':m' => $matricula]);

        if ($chk->fetch()) {
            $erro = 'Matrícula ou E-mail já cadastrado.';
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nome, matricula, email, senha, tipo)
                 VALUES (:nome, :matricula, :email, :senha, "leitor")'
            );
            $stmt->execute([
                ':nome'      => $nome,
                ':matricula' => $matricula,
                ':email'     => $email,
                ':senha'     => $hash,
            ]);

            $sucesso = 'Cadastro realizado com sucesso! Faça seu login.';
        }
    }
}

require __DIR__ . '/../includes/cabecalho.php';
?>

<main>
    <section class="form-section">
        <div class="form-wrapper">
            <h1 class="form-title">Criar conta</h1>

            <?php if ($erro): ?>
                <p class="msg-erro"><?= e($erro) ?></p>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <p class="msg-ok"><?= e($sucesso) ?></p>
                <p><a href="../auth/login.php">Ir para o login →</a></p>
            <?php else: ?>
                <form class="auth-form" method="post" action="">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome"
                        value="<?= e($_POST['nome'] ?? '') ?>"
                        placeholder="Seu nome" required>

                    <label for="matricula">Matrícula</label>
                    <input type="text" id="matricula" name="matricula"
                        value="<?= e($_POST['matricula'] ?? '') ?>"
                        placeholder="Ex: 20260101" required>

                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email"
                        value="<?= e($_POST['email'] ?? '') ?>"
                        placeholder="seu@email.com" required>

                    <label for="senha">Senha <small>(mínimo 8 caracteres)</small></label>
                    <input type="password" id="senha" name="senha"
                        placeholder="••••••••" required>

                    <label for="confirmacao">Confirmar senha</label>
                    <input type="password" id="confirmacao" name="confirmacao"
                        placeholder="••••••••" required>

                    <button class="btn-search" type="submit">Cadastrar</button>
                </form>

                <p class="form-link">Já tem conta? <a href="../auth/login.php">Faça o login</a></p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
