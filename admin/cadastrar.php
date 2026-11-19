<?php 
require_once './config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $titulo = trim($_POST['titulo']);
    $autor = trim($_POST['autor']);
    $editora = trim($_POST['editora']);
    $isbn = trim($_POST['isbn']);
    $classificacao = trim($_POST['classificacao']);
    $ano = trim($_POST['ano_publicacao']);
    $_qtd = (int)($_POST['quantidade_disponivel']?? 1);

}

try{
    $sql = "INSERT INTO livros (titulo, autor, isbn, editora, ano_publicacao, quantidade_disponivel) 
                VALUES (:titulo, :autor, :isbn, :editora, :ano_publicacao, :qtd_disponivel)";

}catch (PDOException $e){
    
}

?>