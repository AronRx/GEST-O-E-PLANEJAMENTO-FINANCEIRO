<?php $paginaAtual = $pagina ?? 'dashboard'; ?>
<aside class="sidebar">
    <div class="logo">💰 Nymbrio</div>
    <ul>
        <li class="<?= $paginaAtual === 'dashboard' ? 'ativo' : '' ?>"><a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="<?= in_array($paginaAtual, ['clientes', 'novo_cliente', 'editar_cliente', 'visualizar_cliente'], true) ? 'ativo' : '' ?>"><a href="index.php?pagina=clientes"><i class="bi bi-people"></i> Clientes</a></li>
        <li class="<?= in_array($paginaAtual, ['emprestimos', 'novo_emprestimo', 'visualizar_emprestimo'], true) ? 'ativo' : '' ?>"><a href="index.php?pagina=emprestimos"><i class="bi bi-cash-stack"></i> Empréstimos</a></li>
        <li class="<?= in_array($paginaAtual, ['parcelas', 'receber_parcela'], true) ? 'ativo' : '' ?>"><a href="index.php?pagina=parcelas"><i class="bi bi-calendar-check"></i> Parcelas</a></li>
        <li class="<?= $paginaAtual === 'relatorios' ? 'ativo' : '' ?>"><a href="index.php?pagina=relatorios"><i class="bi bi-graph-up"></i> Relatórios</a></li>
    </ul>
    <div class="sidebar-versao">Nymbrio<br>PostgreSQL + PHP</div>
</aside>
