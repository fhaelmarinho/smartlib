<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

exigirBibliotecario();

$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    $like = '%' . $busca . '%';
    $stmt = $pdo->prepare('
        SELECT l.*, c.nome AS categoria,
        GROUP_CONCAT(e.codigo_exemplar ORDER BY e.id SEPARATOR ", ") AS codigos_exemplares
        FROM livros l
        LEFT JOIN categorias c ON c.id = l.categoria_id
        LEFT JOIN exemplares e ON e.livro_id = l.id
        WHERE l.titulo LIKE :b_titulo
        OR l.autor LIKE :b_autor
        OR l.editora LIKE :b_editora
        OR l.isbn LIKE :b_isbn
        OR l.classificacao LIKE :b_class
        OR c.nome LIKE :b_cat
        OR e.codigo_exemplar LIKE :b_cod
        GROUP BY l.id
        ORDER BY l.titulo ASC
    ');
    $stmt->execute([
        ':b_titulo' => $like,
        ':b_autor' => $like,
        ':b_editora' => $like,
        ':b_isbn' => $like,
        ':b_class' => $like,
        ':b_cat' => $like,
        ':b_cod' => $like,
    ]);
} else {
    $stmt = $pdo->prepare('
        SELECT l.*, c.nome AS categoria,
        GROUP_CONCAT(e.codigo_exemplar ORDER BY e.id SEPARATOR ", ") AS codigos_exemplares
        FROM livros l
        LEFT JOIN categorias c ON c.id = l.categoria_id
        LEFT JOIN exemplares e ON e.livro_id = l.id
        GROUP BY l.id
        ORDER BY l.titulo ASC
    ');
    $stmt->execute();
}

$livros = $stmt->fetchAll();

require_once __DIR__ . '/../includes/cabecalho.php';
?>

<main class="section">
    <div class="container container-admin">
        <div class="admin-top">
            <div>
                <h1>Administração de Livros</h1>
                <p class="saudacao">Olá, <strong><?= e($_SESSION['usuario_nome'] ?? '') ?></strong></p>
            </div>
            <div class="admin-actions">
                <a class="btn-emprestar" href="emprestar.php">-> Emprestar Livro</a>
                <a class="btn-adicionar" href="formulario.php">+ Cadastrar Livro</a>
            </div>
        </div>

        <!-- Barra de Filtro / Busca -->
        <section class="admin-filter-section">
            <form method="get" action="livros.php" class="admin-filter-form">
                <input
                    type="text"
                    name="busca"
                    value="<?= e($busca) ?>"
                    placeholder="Filtrar por título, autor, editora, ISBN ou categoria..."
                    class="admin-filter-input"
                >
                <button type="submit" class="btn-search">Filtrar</button>

                <?php if ($busca !== ''): ?>
                    <a href="livros.php" class="btn-clear">Limpar</a>
                <?php endif; ?>
            </form>
            <p class="filter-count"><?= count($livros) ?> livro(s) encontrado(s)</p>
        </section>

        <!-- Tabela de Livros -->
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Autor</th>
                        <th>Editora</th>
                        <th>ISBN</th>
                        <th>Classificação</th>
                        <th>Categoria</th>
                        <th>Ano</th>
                        <th>Disponíveis</th>
                        <th class="th-acoes">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($livros)): ?>
                        <tr>
                            <td colspan="9" class="table-empty">
                                Nenhum livro encontrado para esta pesquisa.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($livros as $l): ?>
                        <tr>
                            <td><strong><?= e($l['titulo']) ?></strong></td>
                            <td><?= e($l['autor']) ?></td>
                            <td><?= e($l['editora']) ?></td>
                            <td><?= e($l['isbn']) ?></td>
                            <td><?= e($l['classificacao']) ?></td>
                            <td><?= e($l['categoria']) ?></td>
                            <td><?= e($l['ano_publicacao']) ?></td>
                            <td>
                                <a href="exemplares.php?livro_id=<?= $l['id'] ?>" class="badge-link-exemplares" title="Clique para visualizar e adicionar exemplares físicos">
                                    <?= e($l['quantidade_disponivel']) ?> exemplares ↗
                                </a>
                            </td>
                            <td class="col-acoes">
                                <a href="formulario.php?id=<?= $l['id'] ?>" class="btn-acao btn-editar">Editar</a>
                                <a href="excluir.php?id=<?= $l['id'] ?>" 
                                    class="btn-acao btn-excluir" 
                                    onclick="return confirm('Tem certeza que deseja excluir o livro \'<?= e(addslashes($l['titulo'])) ?>\'?');">
                                    Excluir
                                </a>
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
