<?php

include("config/conexao.php");

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Sistema Financeiro</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<div class="layout">

    <?php include("includes/sidebar.php"); ?>

    <div class="principal">

        <?php include("includes/header.php"); ?>

        <div class="conteudo">

        <?php

        $pagina = isset($_GET['pagina']) ? $_GET['pagina'] : 'dashboard';

        $arquivo = "pages/" . $pagina . ".php";

        if(file_exists($arquivo)){
            include($arquivo);
        }else{
            echo "<h2>Página não encontrada.</h2>";
        }

        ?>

        </div>

    </div>

</div>

<?php include("includes/footer.php"); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>