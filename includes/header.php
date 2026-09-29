<?php

$paginaAtual = isset($_GET["pagina"]) ? $_GET["pagina"] : "dashboard";

$tituloPagina = "Dashboard";

switch($paginaAtual){

    case "clientes":
        $tituloPagina = "Clientes";
    break;

    case "novo_cliente":
        $tituloPagina = "Novo Cliente";
    break;

    case "editar_cliente":
        $tituloPagina = "Editar Cliente";
    break;

    case "visualizar_cliente":
        $tituloPagina = "Cliente";
    break;

    case "emprestimos":
        $tituloPagina = "Empréstimos";
    break;

    case "novo_emprestimo":
        $tituloPagina = "Novo Empréstimo";
    break;

    case "parcelas":
        $tituloPagina = "Parcelas";
    break;

    case "receber_parcela":
        $tituloPagina = "Receber Parcela";
    break;

    case "visualizar_emprestimo":
    $tituloPagina = "Empréstimo";
    break;

}

?>

<div class="topo">

    <div>

        <h1><?= $tituloPagina ?></h1>

        <span><?= date("d/m/Y") ?></span>

    </div>

    <div class="topo-direita">

        <i class="bi bi-person-circle"></i>

        <span>Administrador</span>

    </div>

</div>