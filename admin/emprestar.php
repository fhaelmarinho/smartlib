<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

exigirBibliotecario();

$erro = '';
$sucesso = '';

// Data padrão de devolução (+7 dias a partir de hoje, o bibliotecário pode alterar a data sugerida, caso haja feriados, a biblioteca esteja fechada ou qualquer situação que justifique)
$dataHoje = date('Y-m-d');
$dataDevolucaoPadrao = date('Y-m-d', strtotime('+7 days'));

// Processamento do formulário de empréstimo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'novo_emprestimo') {
    $identificadorUsuario  = trim($_POST['identificador_usuario'] ?? '');
    $codigoExemplar         = strtoupper(trim($_POST['codigo_exemplar'] ?? ''));
    $dataPrevistaDevolucao  = trim($_POST['data_prevista_devolucao'] ?? '');

    if ($identificadorUsuario === '' || $codigoExemplar === '' || $dataPrevistaDevolucao === '') {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif ($dataPrevistaDevolucao < $dataHoje) {
        $erro = 'A data prevista de devolução deve ser uma data futura.';
    } else {
        // 1. Localiza o usuário por e-mail ou matrícula
        $stmtUsuario = $pdo->prepare('
            SELECT id, nome, email, matricula 
            FROM usuarios 
            WHERE email = :email OR matricula = :matricula
            LIMIT 1
        ');
        $stmtUsuario->execute([
            ':email' => $identificadorUsuario,
            ':matricula' => $identificadorUsuario,
        ]);
        $usuario = $stmtUsuario->fetch();

        if (!$usuario) {
            $erro = "Nenhum usuário encontrado com o e-mail ou matrícula \"{$identificadorUsuario}\".";
        } else {
            // Localiza o exemplar pelo código
            $stmtEx = $pdo->prepare('
                SELECT e.id, e.livro_id, e.codigo_exemplar, e.status, l.titulo
                FROM exemplares e
                JOIN livros l ON l.id = e.livro_id
                WHERE e.codigo_exemplar = :cod
                LIMIT 1
            ');
            $stmtEx->execute([':cod' => $codigoExemplar]);
            $exemplar = $stmtEx->fetch();

            if (!$exemplar) {
                $erro = "Exemplar \"{$codigoExemplar}\" não encontrado.";
            } elseif ($exemplar['status'] !== 'disponivel') {
                $erro = "O exemplar \"{$codigoExemplar}\" não está disponível no momento (Status: " . ucfirst($exemplar['status']) . ").";
            } else {
                // 3 Realiza a transação de empréstimo
                try {
                    $pdo->beginTransaction();

                    // Cria o registro na tabela emprestimos
                    $stmtEmp = $pdo->prepare('
                        INSERT INTO emprestimos (usuario_id, livro_id, exemplar_id, data_emprestimo, data_prevista_devolucao, status)
                        VALUES (:usuario_id, :livro_id, :exemplar_id, :data_emp, :data_prev, "emprestado")
                    ');
                    $stmtEmp->execute([
                        ':usuario_id' => $usuario['id'],
                        ':livro_id' => $exemplar['livro_id'],
                        ':exemplar_id' => $exemplar['id'],
                        ':data_emp' => $dataHoje,
                        ':data_prev' => $dataPrevistaDevolucao,
                    ]);

                    // Altera o status do exemplar para emprestado
                    $stmtUpEx = $pdo->prepare('UPDATE exemplares SET status = "emprestado" WHERE id = :id');
                    $stmtUpEx->execute([':id' => $exemplar['id']]);

                    // Subtrai da quantidade disponível no livro
                    $stmtUpLivro = $pdo->prepare('
                        UPDATE livros 
                        SET quantidade_disponivel = GREATEST(0, quantidade_disponivel - 1) 
                        WHERE id = :id
                    ');
                    $stmtUpLivro->execute([':id' => $exemplar['livro_id']]);

                    $pdo->commit();

                    $sucesso = "Empréstimo do exemplar <strong>" . e($exemplar['codigo_exemplar']) . "</strong> (" . e($exemplar['titulo']) . ") realizado com sucesso para <strong>" . e($usuario['nome']) . "</strong>!";

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $erro = 'Erro ao registrar empréstimo: ' . $e->getMessage();
                }
            }
        }
    }
}

// Processamento: Devolução rápida de exemplar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'devolver') {
    $emprestimoId = filter_input(INPUT_POST, 'emprestimo_id', FILTER_VALIDATE_INT);

    if ($emprestimoId) {
        try {
            $stmtBuscaEmp = $pdo->prepare('
                SELECT id, livro_id, exemplar_id, status 
                FROM emprestimos 
                WHERE id = :id AND status = "emprestado"
            ');
            $stmtBuscaEmp->execute([':id' => $emprestimoId]);
            $emp = $stmtBuscaEmp->fetch();

            if ($emp) {
                $pdo->beginTransaction();

                // Marca empréstimo como devolvido
                $stmtDev = $pdo->prepare('
                    UPDATE emprestimos 
                    SET status = "devolvido", data_devolucao = :hoje 
                    WHERE id = :id
                ');
                $stmtDev->execute([':hoje' => $dataHoje, ':id' => $emprestimoId]);

                // Devolve o status do exemplar para disponivel
                if (!empty($emp['exemplar_id'])) {
                    $pdo->prepare('UPDATE exemplares SET status = "disponivel" WHERE id = :id')
                        ->execute([':id' => $emp['exemplar_id']]);
                }

                // Incrementa estoque do livro
                $pdo->prepare('UPDATE livros SET quantidade_disponivel = quantidade_disponivel + 1 WHERE id = :id')
                    ->execute([':id' => $emp['livro_id']]);

                $pdo->commit();
                $sucesso = 'Devolução registrada com sucesso! Livro retornado ao acervo.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = 'Erro ao registrar devolução: ' . $e->getMessage();
        }
    }
}

// Busca a lista de exemplares atualmente disponíveis para sugestão no formulário
$exemplaresDisponiveis = $pdo->query('
    SELECT e.codigo_exemplar, l.titulo, l.autor
    FROM exemplares e
    JOIN livros l ON l.id = e.livro_id
    WHERE e.status = "disponivel"
    ORDER BY e.codigo_exemplar ASC
')->fetchAll();

// Busca os últimos empréstimos em andamento
$emprestimosAtivos = $pdo->query('
    SELECT emp.id, emp.data_emprestimo, emp.data_prevista_devolucao, emp.status,
    u.nome AS usuario_nome, u.email AS usuario_email, u.matricula AS usuario_matricula,
    l.titulo AS livro_titulo,
    e.codigo_exemplar
    FROM emprestimos emp
    JOIN usuarios u ON u.id = emp.usuario_id
    JOIN livros l ON l.id = emp.livro_id
    LEFT JOIN exemplares e ON e.id = emp.exemplar_id
    WHERE emp.status = "emprestado"
    ORDER BY emp.id DESC
    LIMIT 20
')->fetchAll();

require_once __DIR__ . '/../includes/cabecalho.php';
?>

<main class="section">
    <div class="container container-admin">
        <div class="admin-top">
            <div>
                <h1>Realizar Empréstimo</h1>
                <p class="saudacao">Informe o Usuário (e-mail ou matrícula) e o exemplar físico a ser emprestado.</p>
            </div>
            <a class="btn-clear" href="livros.php">← Voltar para Livros</a>
        </div>

        <?php if ($erro): ?>
            <p class="msg-erro"><?= $erro ?></p>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <p class="msg-ok"><?= $sucesso ?></p>
        <?php endif; ?>

        <!-- Formulário de Empréstimo -->
        <div class="form-wrapper form-wrapper-livro">
            <form class="auth-form" method="post" action="">
                <input type="hidden" name="acao" value="novo_emprestimo">

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="identificador_usuario">Leitor (Matrícula ou E-mail) *</label>
                        <input
                            type="text"
                            id="identificador_usuario"
                            name="identificador_usuario"
                            value="<?= e($_POST['identificador_usuario'] ?? '') ?>"
                            placeholder="Ex: 20260101 ou leitor@email.com"
                            required
                        >
                    </div>

                    <div class="form-group flex-1">
                        <label for="codigo_exemplar">Código do Exemplar *</label>
                        <input
                            type="text"
                            id="codigo_exemplar"
                            name="codigo_exemplar"
                            value="<?= e($_POST['codigo_exemplar'] ?? '') ?>"
                            placeholder="Ex: LIV001-EX01"
                            list="lista_exemplares"
                            required
                            autocomplete="off"
                        >
                        <datalist id="lista_exemplares">
                            <?php foreach ($exemplaresDisponiveis as $ed): ?>
                                <option value="<?= e($ed['codigo_exemplar']) ?>">
                                    <?= e($ed['codigo_exemplar']) ?> — <?= e($ed['titulo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="data_emprestimo_visivel">Data do Empréstimo</label>
                        <input
                            type="text"
                            id="data_emprestimo_visivel"
                            value="<?= date('d/m/Y') ?> (Hoje)"
                            disabled
                            style="background-color: #f1f5f9; cursor: not-allowed;"
                        >
                    </div>

                    <div class="form-group flex-1">
                        <label for="data_prevista_devolucao">Data Prevista de Devolução *</label>
                        <input
                            type="date"
                            id="data_prevista_devolucao"
                            name="data_prevista_devolucao"
                            value="<?= e($_POST['data_prevista_devolucao'] ?? $dataDevolucaoPadrao) ?>"
                            min="<?= $dataHoje ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-emprestar" style="cursor: pointer; border: none;">
                        Confirmar Empréstimo
                    </button>
                    <a href="livros.php" class="btn-clear">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Empréstimos em Andamento -->
        <div style="margin-top: 40px;">
            <h2 style="font-size: 18px; color: var(--mainblue); margin-bottom: 14px;">
                Empréstimos em Andamento (<?= count($emprestimosAtivos) ?>)
            </h2>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Leitor</th>
                            <th>Matrícula / E-mail</th>
                            <th>Livro</th>
                            <th>Exemplar</th>
                            <th>Data Empréstimo</th>
                            <th>Devolução Prevista</th>
                            <th style="text-align: center; width: 130px;">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($emprestimosAtivos)): ?>
                            <tr>
                                <td colspan="7" class="table-empty">
                                    Nenhum empréstimo ativo no momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($emprestimosAtivos as $ea): ?>
                            <tr>
                                <td><strong><?= e($ea['usuario_nome']) ?></strong></td>
                                <td>
                                    <?= e($ea['usuario_matricula'] ?: '—') ?><br>
                                    <small style="color: var(--text-muted);"><?= e($ea['usuario_email']) ?></small>
                                </td>
                                <td><?= e($ea['livro_titulo']) ?></td>
                                <td>
                                    <span class="cod-tag"><?= e($ea['codigo_exemplar'] ?: 'Geral') ?></span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($ea['data_emprestimo'])) ?></td>
                                <td>
                                    <?php 
                                        $prev = strtotime($ea['data_prevista_devolucao']);
                                        $hoje = strtotime($dataHoje);
                                        $atrasado = $prev < $hoje;
                                    ?>
                                    <span style="<?= $atrasado ? 'color: #b91c1c; font-weight: 700;' : '' ?>">
                                        <?= date('d/m/Y', $prev) ?>
                                        <?= $atrasado ? ' (Vencido)' : '' ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <form method="post" action="" style="display: inline;" onsubmit="return confirm('Confirmar a devolução deste exemplar?');">
                                        <input type="hidden" name="acao" value="devolver">
                                        <input type="hidden" name="emprestimo_id" value="<?= $ea['id'] ?>">
                                        <button type="submit" class="btn-acao btn-editar" style="cursor: pointer;">
                                            Devolver
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/rodape.php'; ?>
