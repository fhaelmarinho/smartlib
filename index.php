<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$livros             = [];
$buscado            = false;
$argumento_de_busca = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buscado = true;
    $argumento_de_busca = trim($_POST['busca'] ?? '');

    if ($argumento_de_busca !== '') {
        $like = '%' . $argumento_de_busca . '%';

        $stmt = $pdo->prepare(
            'SELECT l.titulo, l.autor, l.editora, l.ano_publicacao,
                    l.quantidade_disponivel, l.isbn, l.classificacao,
                    c.nome AS categoria
            FROM livros l
            INNER JOIN categorias c ON c.id = l.categoria_id
            WHERE l.titulo LIKE :t
            OR l.autor  LIKE :a
            ORDER BY l.titulo
            LIMIT 30'
        );

        $stmt->execute([':t' => $like, ':a' => $like]);
        $livros = $stmt->fetchAll();
    }
}

require __DIR__ . '/includes/cabecalho.php';
?>

<main>
    <!-- Hero banner -->
    <section class="hero">
        <img src="/asset/img/cover.webp" alt="imagem de livros e um smartphone">
    </section>

    <section class="search-section">
        <div class="search-wrapper">
            <span class="search-label">Campo de Busca</span>
            <form class="search-form" action="" method="post">
                <input
                    class="search-input"
                    type="text"
                    name="busca"
                    id="busca"
                    placeholder="Digite a sua pesquisa por Título ou Autor..."
                >
                <button class="btn-search" type="submit">
                    <img src="/asset/img/lupa.png" alt="Pesquisar" class="icon-lupa"><span>Pesquisa</span>
                </button>
            </form>
        </div>
    </section>

    <!-- Resultados da busca -->
    <?php if ($buscado): ?>
    <section class="result-section">
        <div class="container">

            <?php if (empty($livros)): ?>
                <p class="no-results">
                    Nenhum livro encontrado para '<strong><?= e($argumento_de_busca) ?></strong>'.
                </p>

            <?php else: ?>
                <p class="result-count"><b><?= count($livros) ?></b> resultado(s) encontrado(s)</p>

                <?php foreach ($livros as $livro): ?>
                <div class="book-card">
                    <h2 class="book-title"><?= e($livro['titulo'])?> <small><em><?= '('. ($livro['ano_publicacao']) .')' ?></em></small></h2>
                    <dl class="book-meta">
                        <dt>Autor:</dt> <dd><?= e($livro['autor']) ?></dd>
                        <dt>Editora:</dt>  <dd><?= e($livro['editora']) ?></dd>
                        <dt>Ano:</dt> <dd><?= e($livro['ano_publicacao']) ?></dd>
                        <dt>Classificação:</dt> <dd><?= e($livro['classificacao']) ?></dd>
                        <dt>ISBN:</dt> <dd><?= e($livro['isbn']) ?></dd>
                        <dt>Categoria:</dt> <dd><?= e($livro['categoria']) ?></dd>
                        <dt>Disponível para empréstimo:</dt>
                        <dd><?= $livro['quantidade_disponivel'] > 0 ? 'Sim' : 'Não' ?></dd>
                    </dl>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php
require_once __DIR__ .'/includes/rodape.php';
?>