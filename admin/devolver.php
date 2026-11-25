<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

exigirBibliotecario();

$erro = '';
$sucesso = '';
$dataHoje = date('Y-m-d');

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

// Filtro de pesquisa
$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    $like = '%' . $busca . '%';
    $stmt = $pdo->prepare('
        SELECT emp.id, emp.data_emprestimo, emp.data_prevista_devolucao, emp.status,
        u.nome AS usuario_nome, u.email AS usuario_email, u.matricula AS usuario_matricula,
        l.titulo AS livro_titulo,
        e.codigo_exemplar
        FROM emprestimos emp
        JOIN usuarios u ON u.id = emp.usuario_id
        JOIN livros l ON l.id = emp.livro_id
        LEFT JOIN exemplares e ON e.id = emp.exemplar_id
        WHERE emp.status = "emprestado"
        AND (
            u.nome LIKE :b_nome
            OR u.matricula LIKE :b_mat
            OR u.email LIKE :b_email
            OR l.titulo LIKE :b_titulo
            OR e.codigo_exemplar LIKE :b_cod
        )
        ORDER BY emp.data_prevista_devolucao ASC, emp.id DESC
    ');
    $stmt->execute([
        ':b_nome'   => $like,
        ':b_mat'    => $like,
        ':b_email'  => $like,
        ':b_titulo' => $like,
        ':b_cod'    => $like,
    ]);
    $emprestimosAtivos = $stmt->fetchAll();
} else {
    // Busca os empréstimos em andamento
    $stmt = $pdo->prepare('
        SELECT emp.id, emp.data_emprestimo, emp.data_prevista_devolucao, emp.status,
        u.nome AS usuario_nome, u.email AS usuario_email, u.matricula AS usuario_matricula,
        l.titulo AS livro_titulo,
        e.codigo_exemplar
        FROM emprestimos emp
        JOIN usuarios u ON u.id = emp.usuario_id
        JOIN livros l ON l.id = emp.livro_id
        LEFT JOIN exemplares e ON e.id = emp.exemplar_id
        WHERE emp.status = "emprestado"
        ORDER BY emp.data_prevista_devolucao ASC, emp.id DESC
    ');
    $stmt->execute();
    $emprestimosAtivos = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/cabecalho.php';
?>

<main class="section">
    <div class="container container-admin">
        <div class="admin-top">
            <div>
                <h1>Devolução de Livros</h1>
                <p class="saudacao">Filtre por leitor, matrícula, título ou código do exemplar para registrar a devolução.</p>
            </div>
            <div class="admin-actions">
                <a class="btn-emprestar" href="emprestar.php">+ Novo Empréstimo</a>
                <a class="btn-clear" href="livros.php">← Voltar para Livros</a>
            </div>
        </div>

        <?php if ($erro): ?>
            <p class="msg-erro"><?= e($erro) ?></p>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <p class="msg-ok"><?= e($sucesso) ?></p>
        <?php endif; ?>

        <!-- Barra de Filtro / Busca -->
        <section class="admin-filter-section">
            <form method="get" action="devolver.php" class="admin-filter-form">
                <input
                    type="text"
                    name="busca"
                    value="<?= e($busca) ?>"
                    placeholder="Filtrar por nome do leitor, matrícula, e-mail, título do livro ou código do exemplar..."
                    class="admin-filter-input"
                >
                <button type="submit" class="btn-search">Filtrar</button>

                <?php if ($busca !== ''): ?>
                    <a href="devolver.php" class="btn-clear">Limpar</a>
                <?php endif; ?>
            </form>
            <p class="filter-count"><?= count($emprestimosAtivos) ?> empréstimo(s) ativo(s) encontrado(s)</p>
        </section>

        <!-- Empréstimos em Andamento -->
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
                        <th class="th-acoes">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emprestimosAtivos)): ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                <?= $busca !== '' ? 'Nenhum empréstimo ativo encontrado para esta pesquisa.' : 'Nenhum empréstimo ativo no momento.' ?>
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
                            <td class="col-acoes">
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
</main>

<?php require_once __DIR__ . '/../includes/rodape.php'; ?>
