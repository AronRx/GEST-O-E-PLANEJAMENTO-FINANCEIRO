<?php

$paginaAtual = isset($_GET["pagina"]) ? $_GET["pagina"] : "dashboard";

?>

<div class="sidebar">

    <div class="logo">

        💰 Sistema Financeiro

    </div>

    <ul>

        <li class="<?= $paginaAtual == 'dashboard' ? 'ativo' : '' ?>">

            <a href="index.php">

                🏠 Dashboard

            </a>

        </li>

        <li class="<?= in_array($paginaAtual, ['clientes','novo_cliente','editar_cliente','visualizar_cliente']) ? 'ativo' : '' ?>">

            <a href="index.php?pagina=clientes">

                👥 Clientes

            </a>

        </li>

        <li class="<?= in_array($paginaAtual, ['emprestimos','novo_emprestimo']) ? 'ativo' : '' ?>">

            <a href="index.php?pagina=emprestimos">

                💰 Empréstimos

            </a>

        </li>

        <li class="<?= $paginaAtual == 'parcelas' ? 'ativo' : '' ?>">

            <a href="index.php?pagina=parcelas">

                📅 Parcelas

            </a>

        </li>

        <li class="<?= $paginaAtual == 'relatorios' ? 'ativo' : '' ?>">

            <a href="index.php?pagina=relatorios">

                📊 Relatórios

            </a>

        </li>

    </ul>

    <div style="margin-top:auto;padding-top:30px;font-size:13px;color:#94a3b8;text-align:center;">

        Sistema Financeiro<br>

        Versão 1.0

    </div>

</div>