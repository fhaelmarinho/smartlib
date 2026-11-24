<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

exigirBibliotecario();

$livro_id = filter_input(INPUT_GET, 'livro_id', FILTER_VALIDATE_INT);

if (!$livro_id) {
    header('Location: livros.php');
    exit;
}

// Busca os dados do livro
$stmtLivro = $pdo->prepare('
    SELECT l.*, c.nome AS categoria
    FROM livros l
    LEFT JOIN categorias c ON c.id = l.categoria_id
    WHERE l.id = :id
');
$stmtLivro->execute([':id' => $livro_id]);
$livro = $stmtLivro->fetch();

if (!$livro) {
    header('Location: livros.php');
    exit;
}

$msgSucesso = '';
$msgErro = '';

// Processamento: Adicionar mais exemplares
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'adicionar') {
        $qtdAdicionar = filter_input(INPUT_POST, 'qtd_adicionar', FILTER_VALIDATE_INT);

        if ($qtdAdicionar && $qtdAdicionar > 0 && $qtdAdicionar <= 50) {
            // Descobre quantos exemplares já existem para continuar a numeração
            $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM exemplares WHERE livro_id = :id');
            $stmtCount->execute([':id' => $livro_id]);
            $totalExistente = (int)$stmtCount->fetchColumn();

            $idFormatado = str_pad($livro_id, 3, '0', STR_PAD_LEFT);
            $stmtNovo = $pdo->prepare('
                INSERT INTO exemplares (livro_id, codigo_exemplar, status)
                VALUES (:livro_id, :codigo, "disponivel")
            ');

            for ($i = 1; $i <= $qtdAdicionar; $i++) {
                $numExemplar = str_pad($totalExistente + $i, 2, '0', STR_PAD_LEFT);
                $codigo = "LIV{$idFormatado}-EX{$numExemplar}";
                $stmtNovo->execute([
                    ':livro_id' => $livro_id,
                    ':codigo'   => $codigo,
                ]);
            }

            // Atualiza a quantidade disponível na tabela principal de livros
            $stmtUp = $pdo->prepare('
                UPDATE livros 
                SET quantidade_disponivel = quantidade_disponivel + :qtd
                WHERE id = :id
            ');
            $stmtUp->execute([
                ':qtd' => $qtdAdicionar,
                ':id'  => $livro_id,
            ]);

            $msgSucesso = "{$qtdAdicionar} novo(s) exemplar(es) gerado(s) com sucesso!";
            // Atualiza o valor local do livro
            $livro['quantidade_disponivel'] += $qtdAdicionar;

        } else {
            $msgErro = 'Informe uma quantidade válida para adicionar (entre 1 e 50).';
        }

    } elseif ($acao === 'alterar_status') {
        $exemplar_id = filter_input(INPUT_POST, 'exemplar_id', FILTER_VALIDATE_INT);
        $novoStatus  = $_POST['novo_status'] ?? '';
        $statusValidos = ['disponivel', 'manutencao', 'perdido'];

        if ($exemplar_id && in_array($novoStatus, $statusValidos)) {
            // Pega o status anterior para saber se altera a quantidade_disponivel
            $stmtStatusAnt = $pdo->prepare('SELECT status FROM exemplares WHERE id = :id AND livro_id = :livro_id');
            $stmtStatusAnt->execute([':id' => $exemplar_id, ':livro_id' => $livro_id]);
            $statusAnterior = $stmtStatusAnt->fetchColumn();

            if ($statusAnterior && $statusAnterior !== $novoStatus) {
                $stmtUpdStatus = $pdo->prepare('UPDATE exemplares SET status = :status WHERE id = :id');
                $stmtUpdStatus->execute([':status' => $novoStatus, ':id' => $exemplar_id]);

                // Ajusta quantidade disponível no livro
                if ($statusAnterior === 'disponivel' && $novoStatus !== 'disponivel') {
                    $pdo->prepare('UPDATE livros SET quantidade_disponivel = GREATEST(0, quantidade_disponivel - 1) WHERE id = :id')
                        ->execute([':id' => $livro_id]);
                    $livro['quantidade_disponivel'] = max(0, $livro['quantidade_disponivel'] - 1);
                } elseif ($statusAnterior !== 'disponivel' && $novoStatus === 'disponivel') {
                    $pdo->prepare('UPDATE livros SET quantidade_disponivel = quantidade_disponivel + 1 WHERE id = :id')
                        ->execute([':id' => $livro_id]);
                    $livro['quantidade_disponivel'] += 1;
                }

                $msgSucesso = 'Status do exemplar atualizado!';
            }
        }
    }
}

// Busca todos os exemplares deste livro
$stmtExemplares = $pdo->prepare('SELECT * FROM exemplares WHERE livro_id = :id ORDER BY id ASC');
$stmtExemplares->execute([':id' => $livro_id]);
$exemplares = $stmtExemplares->fetchAll();

require_once __DIR__ . '/../includes/cabecalho.php';
?>

<main class="section">
    <div class="container container-admin">
        <div class="admin-top">
            <div>
                <h1>Gestão de Exemplares Físicos</h1>
                <p class="saudacao">
                    Livro: <strong><?= e($livro['titulo']) ?></strong> 
                    • Autor: <?= e($livro['autor']) ?> 
                    • ISBN: <?= e($livro['isbn']) ?>
                </p>
            </div>
            <a class="btn-clear" href="livros.php">← Voltar para a lista de livros</a>
        </div>

        <?php if ($msgSucesso): ?>
            <p class="msg-ok"><?= e($msgSucesso) ?></p>
        <?php endif; ?>

        <?php if ($msgErro): ?>
            <p class="msg-erro"><?= e($msgErro) ?></p>
        <?php endif; ?>

        <!-- Card de Adição de Novos Exemplares -->
        <section class="admin-filter-section add-exemplar-card">
            <div class="add-exemplar-header">
                <h3>+ Adicionar mais exemplares a este livro</h3>
                <p>O sistema gerará os códigos sequenciais automaticamente (ex: LIV<?= str_pad($livro_id, 3, '0', STR_PAD_LEFT) ?>-EX...)</p>
            </div>
            <form method="post" action="" class="add-exemplar-form">
                <input type="hidden" name="acao" value="adicionar">
                <label for="qtd_adicionar">Quantidade a adicionar:</label>
                <input
                    type="number"
                    id="qtd_adicionar"
                    name="qtd_adicionar"
                    value="1"
                    min="1"
                    max="50"
                    class="input-qtd-exemplar"
                    required
                >
                <button type="submit" class="btn-adicionar">
                    + Gerar Exemplares
                </button>
            </form>
        </section>

        <!-- Tabela de Exemplares -->
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Nº</th>
                        <th>Código do Exemplar</th>
                        <th>Status Atual</th>
                        <th>Data de Cadastro</th>
                        <th style="text-align: center; width: 220px;">Alterar Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exemplares)): ?>
                        <tr>
                            <td colspan="5" class="table-empty">
                                Nenhum exemplar físico cadastrado para este livro.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exemplares as $idx => $ex): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <span class="cod-tag-destaque"><?= e($ex['codigo_exemplar']) ?></span>
                            </td>
                            <td>
                                <span class="badge-status badge-<?= e($ex['status']) ?>">
                                    <?= ucfirst(e($ex['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d/m/Y H:i', strtotime($ex['criado_em'])) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($ex['status'] === 'emprestado'): ?>
                                    <small style="color: #b45309; font-weight: 600;">Emprestado a um leitor</small>
                                <?php else: ?>
                                    <form method="post" action="" style="display: inline-flex; gap: 6px; align-items: center;">
                                        <input type="hidden" name="acao" value="alterar_status">
                                        <input type="hidden" name="exemplar_id" value="<?= $ex['id'] ?>">
                                        <select name="novo_status" class="select-status-mini" onchange="this.form.submit()">
                                            <option value="disponivel" <?= $ex['status'] === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
                                            <option value="manutencao" <?= $ex['status'] === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                                            <option value="perdido"    <?= $ex['status'] === 'perdido' ? 'selected' : '' ?>>Perdido</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
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
