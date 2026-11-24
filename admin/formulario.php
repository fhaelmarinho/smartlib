<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

exigirBibliotecario();

// Busca as categorias para o <select>
$categorias = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome ASC')->fetchAll();

// Verifica se é edição de um livro existente
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$livro = [
    'titulo'  => '',
    'autor' => '',
    'editora' => '',
    'isbn' => '',
    'classificacao' => '',
    'categoria_id' => '',
    'ano_publicacao' => '',
    'quantidade_disponivel' => 1,
];
$modoEdicao = false;
$exemplares = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM livros WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $dadosLivro = $stmt->fetch();

    if ($dadosLivro) {
        $livro = $dadosLivro;
        $modoEdicao = true;

        // Busca os exemplares deste livro
        $stmtEx = $pdo->prepare('SELECT * FROM exemplares WHERE livro_id = :id ORDER BY id ASC');
        $stmtEx->execute([':id' => $id]);
        $exemplares = $stmtEx->fetchAll();
    }
}

$erro = '';

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $editora = trim($_POST['editora'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $classificacao = trim($_POST['classificacao'] ?? '');
    $categoria_id  = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
    $ano = trim($_POST['ano_publicacao'] ?? '');
    $quantidade = filter_input(INPUT_POST, 'quantidade_disponivel', FILTER_VALIDATE_INT);

    // Mantém os dados preenchidos no formulário se houver erro
    $livro = [
        'titulo' => $titulo,
        'autor' => $autor,
        'editora' => $editora,
        'isbn' => $isbn,
        'classificacao' => $classificacao,
        'categoria_id'  => $categoria_id,
        'ano_publicacao'  => $ano,
        'quantidade_disponivel' => $quantidade ?? 1,
    ];

    if ($titulo === '' || $autor === '' || $editora === '' || $isbn === '') {
        $erro = 'Preencha todos os campos obrigatórios (Título, Autor, Editora e ISBN).';
    } elseif ($quantidade === false || $quantidade < 0) {
        $erro = 'A quantidade disponível deve ser um número válido (0 ou maior).';
    } else {
        try {
            if ($modoEdicao) {
                // UPDATE DO LIVRO
                $stmt = $pdo->prepare('
                    UPDATE livros
                    SET titulo = :titulo,
                        autor = :autor,
                        editora = :editora,
                        isbn = :isbn,
                        classificacao = :classificacao,
                        categoria_id = :categoria_id,
                        ano_publicacao = :ano,
                        quantidade_disponivel = :qtd
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':autor' => $autor,
                    ':editora' => $editora,
                    ':isbn' => $isbn,
                    ':classificacao' => $classificacao,
                    ':categoria_id' => $categoria_id,
                    ':ano' => $ano,
                    ':qtd' => $quantidade,
                    ':id' => $id,
                ]);

                // Se a quantidade aumentou, gera os novos exemplares adicionais 
                $stmtQtdEx = $pdo->prepare('SELECT COUNT(*) FROM exemplares WHERE livro_id = :id');
                $stmtQtdEx->execute([':id' => $id]);
                $qtdAtualEx = (int)$stmtQtdEx->fetchColumn();

                if ($quantidade > $qtdAtualEx) {
                    $idFormatado = str_pad($id, 3, '0', STR_PAD_LEFT);
                    $stmtNovoEx = $pdo->prepare('
                        INSERT INTO exemplares (livro_id, codigo_exemplar, status)
                        VALUES (:livro_id, :codigo, "disponivel")
                    ');
                    for ($i = $qtdAtualEx + 1; $i <= $quantidade; $i++) {
                        $numExemplar = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $codigo = "LIV{$idFormatado}-EX{$numExemplar}";
                        $stmtNovoEx->execute([
                            ':livro_id' => $id,
                            ':codigo'   => $codigo,
                        ]);
                    }
                }

            } else {
                // INSERT DO NOVO LIVRO
                $stmt = $pdo->prepare('
                    INSERT INTO livros (titulo, autor, editora, isbn, classificacao, categoria_id, ano_publicacao, quantidade_disponivel)
                    VALUES (:titulo, :autor, :editora, :isbn, :classificacao, :categoria_id, :ano, :qtd)
                ');
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':autor' => $autor,
                    ':editora' => $editora,
                    ':isbn' => $isbn,
                    ':classificacao' => $classificacao,
                    ':categoria_id' => $categoria_id,
                    ':ano' => $ano,
                    ':qtd' => $quantidade,
                ]);

                $livroId = (int)$pdo->lastInsertId();

                // Gera automaticamente os registro únicos para cada exemplar (LIV001-EX01...)
                $idFormatado = str_pad($livroId, 3, '0', STR_PAD_LEFT);
                $stmtNovoEx = $pdo->prepare('
                    INSERT INTO exemplares (livro_id, codigo_exemplar, status)
                    VALUES (:livro_id, :codigo, "disponivel")
                ');

                for ($i = 1; $i <= $quantidade; $i++) {
                    $numExemplar = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $codigo = "LIV{$idFormatado}-EX{$numExemplar}";
                    $stmtNovoEx->execute([
                        ':livro_id' => $livroId,
                        ':codigo'   => $codigo,
                    ]);
                }
            }

            // Sucesso -> Redireciona para a lista
            header('Location: livros.php');
            exit;

        } catch (PDOException $e) {
            $erro = 'Erro ao salvar no banco de dados: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/cabecalho.php';
?>

<main class="section">
    <div class="container">
        <div class="admin-top">
            <div>
                <h1><?= $modoEdicao ? 'Editar Livro' : 'Cadastrar Novo Livro' ?></h1>
                <p class="saudacao"><?= $modoEdicao ? 'Atualize as informações do livro abaixo' : 'Preencha os campos para adicionar ao acervo' ?></p>
            </div>
            <a class="btn-clear" href="livros.php">← Voltar para a lista</a>
        </div>

        <div class="form-wrapper form-wrapper-livro">
            <?php if ($erro): ?>
                <p class="msg-erro"><?= e($erro) ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="">
                <div class="form-group">
                    <label for="titulo">Título do Livro *</label>
                    <input type="text" id="titulo" name="titulo" value="<?= e($livro['titulo']) ?>" placeholder="Ex: Dom Casmurro" required>
                </div>

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="autor">Autor *</label>
                        <input type="text" id="autor" name="autor" value="<?= e($livro['autor']) ?>" placeholder="Ex: Machado de Assis" required>
                    </div>

                    <div class="form-group flex-1">
                        <label for="editora">Editora *</label>
                        <input type="text" id="editora" name="editora" value="<?= e($livro['editora']) ?>" placeholder="Ex: Companhia das Letras" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="isbn">ISBN *</label>
                        <input type="text" id="isbn" name="isbn" value="<?= e($livro['isbn']) ?>" placeholder="Ex: 9788535914849" required>
                    </div>

                    <div class="form-group flex-1">
                        <label for="classificacao">Classificação (CDD/CDU)</label>
                        <input type="text" id="classificacao" name="classificacao" value="<?= e($livro['classificacao']) ?>" placeholder="Ex: 869.3">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="categoria_id">Categoria</label>
                        <select id="categoria_id" name="categoria_id" class="form-select">
                            <option value="">-- Selecione uma Categoria --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($livro['categoria_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="ano_publicacao">Ano de Publicação</label>
                        <input type="number" id="ano_publicacao" name="ano_publicacao" value="<?= e($livro['ano_publicacao']) ?>" placeholder="Ex: 2022" min="1500" max="2100">
                    </div>

                    <div class="form-group">
                        <label for="quantidade_disponivel">Quantidade Disponível *</label>
                        <input type="number" id="quantidade_disponivel" name="quantidade_disponivel" value="<?= e($livro['quantidade_disponivel']) ?>" min="0" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-adicionar btn-submit-livro">
                        <?= $modoEdicao ? 'Salvar Alterações' : 'Cadastrar Livro' ?>
                    </button>
                    <a href="livros.php" class="btn-clear">Cancelar</a>
                </div>
            </form>

            <?php if ($modoEdicao && !empty($exemplares)): ?>
                <div class="exemplares-box">
                    <h3>Exemplares Físicos Cadastrados (<?= count($exemplares) ?>)</h3>
                    <p class="exemplares-desc">Estes são os códigos únicos gerados para cada cópia física deste livro:</p>
                    <div class="exemplares-lista">
                        <?php foreach ($exemplares as $ex): ?>
                            <div class="exemplar-card status-<?= e($ex['status']) ?>">
                                <span class="cod-tag"><?= e($ex['codigo_exemplar']) ?></span>
                                <span class="badge-status badge-<?= e($ex['status']) ?>">
                                    <?= ucfirst(e($ex['status'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/rodape.php'; ?>
