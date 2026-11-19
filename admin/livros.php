<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | SMART lib</title>
    <link rel="stylesheet" href="asset/css/style.css">
</head>
<body>
    <?php require __DIR__ . '/../includes/cabecalho.php'; ?>
    <main class="section">
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
                <td><?= $l['titulo'] ?></td>
                <td><?= $l['autor'] ?></td>
                <td><?= $l['editora'] ?></td>
                <td><?= $l['isbn'] ?></td>
                <td><?= $l['classificacao'] ?></td>
                <td><?= $l['categoria'] ?></td>
                <td><?= $l['ano_publicacao'] ?></td>
                <td><?= $l['quantidade_total'] ?></td>
            </tr>
            <?php endforeach; ?>    
        </table>
    </div>

    </main>
</body>
</html>