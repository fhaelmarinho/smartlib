<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

// Consulta todos os livros trazendo o nome da categoria
$stmt = $pdo->prepare('
    SELECT l.*, c.nome AS categoria
    FROM livros l
    LEFT JOIN categorias c ON c.id = l.categoria_id
    ORDER BY l.titulo ASC
');

$stmt->execute();

$livros = $stmt->fetchAll();

require_once __DIR__ . '/../includes/cabecalho.php';
?>

<main class="section">
    <div class="container">
        <h1>Gestão de Livros</h1>
        <p><a class="btn" href="formulario.php">Incluir Novo</a></p>

        <div>
            <table>
                <tr>
                    <th>Título</th>
                    <th>Autor</th>
                    <th>Editora</th>
                    <th>ISBN</th>
                    <th>Classificação</th>
                    <th>Categoria</th>
                    <th>Ano</th>
                    <th>Disponíveis</th>
                </tr>
                <?php foreach($livros as $l): ?>
                <tr>
                    <td><?= e($l['titulo']) ?></td>
                    <td><?= e($l['autor']) ?></td>
                    <td><?= e($l['editora']) ?></td>
                    <td><?= e($l['isbn']) ?></td>
                    <td><?= e($l['classificacao']) ?></td>
                    <td><?= e($l['categoria']) ?></td>
                    <td><?= e($l['ano_publicacao']) ?></td>
                    <td><?= e($l['quantidade_disponivel']) ?></td>
                </tr>
                <?php endforeach; ?>    
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/rodape.php'; ?>
