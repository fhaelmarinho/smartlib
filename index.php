<?php
require_once __DIR__ .'/includes/cabecalho.php';
require './config/conexao.php';
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
                <button class="btn-search" type="submit">Pesquisar</button>
            </form>
        </div>
    </section>
</main>
<?php
require_once __DIR__ .'/includes/rodape.php';
?>