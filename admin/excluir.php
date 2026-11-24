<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';

exigirBibliotecario();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    try {
        $stmt = $pdo->prepare('DELETE FROM livros WHERE id = :id');
        $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {
        // Se houver restrição de chave estrangeira (ex: empréstimo com este livro)
        error_log('Erro ao excluir livro: ' . $e->getMessage());
    }
}

header('Location: livros.php');
exit;
