<?php
require './config/conexao.php';
require './includes/funcoes.php';
// garante que é um número inteiro
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$conect = $pdo -> prepare('SELECT l.*, c.nome AS categoria
                            FROM livros l
                            INNER JOIN categorias c ON l.categoria_id = c.id
                            WHERE l.id = :id');

$conect -> execute([':id => $id?>0']);

$livros = $conect -> fetch();

include __DIR__ . "/includes/cabecalho.php";
?>