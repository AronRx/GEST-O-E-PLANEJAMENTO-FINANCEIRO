<?php

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT * FROM clientes WHERE id = ?";

$stmt = $conexao->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {

    echo "<div class='alert alert-danger'>Cliente não encontrado.</div>";

    return;

}

$cliente = $resultado->fetch_assoc();
// Total emprestado

$sql = "SELECT
            COUNT(*) AS quantidade,
            SUM(valor_emprestado) AS total_emprestado,
            SUM(valor_total) AS total_financiado
        FROM emprestimos
        WHERE cliente_id = ?";

$stmt = $conexao->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$resumo = $stmt->get_result()->fetch_assoc();

$quantidadeEmprestimos = $resumo["quantidade"] ?? 0;
$totalEmprestado = $resumo["total_emprestado"] ?? 0;
$totalFinanciado = $resumo["total_financiado"] ?? 0;
// Total recebido

$sql = "SELECT
            SUM(p.valor_pago) AS total_recebido
        FROM parcelas p
        INNER JOIN emprestimos e
            ON e.id = p.emprestimo_id
        WHERE e.cliente_id = ?
        AND p.status = 'PAGA'";

$stmt = $conexao->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$pagamentos = $stmt->get_result()->fetch_assoc();

$totalRecebido = $pagamentos["total_recebido"] ?? 0;

$saldoDevedor = $totalFinanciado - $totalRecebido;

$sql = "SELECT COUNT(*) AS atrasadas
        FROM parcelas p
        INNER JOIN emprestimos e
            ON e.id = p.emprestimo_id
        WHERE e.cliente_id = ?
        AND p.status <> 'PAGA'
        AND p.vencimento < CURDATE()";

$stmt = $conexao->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$dados = $stmt->get_result()->fetch_assoc();

$parcelasAtrasadas = $dados["atrasadas"] ?? 0;

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2><?= htmlspecialchars($cliente['nome']) ?></h2>

    <a href="index.php?pagina=clientes" class="btn btn-secondary">

        Voltar

    </a>

</div>

<div class="card shadow-sm">

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">

                <p><strong>CPF:</strong> <?= htmlspecialchars($cliente['cpf']) ?></p>

                <p><strong>RG:</strong> <?= htmlspecialchars($cliente['rg']) ?></p>

                <p><strong>Telefone:</strong> <?= htmlspecialchars($cliente['telefone']) ?></p>

                <p><strong>WhatsApp:</strong> <?= htmlspecialchars($cliente['whatsapp']) ?></p>

                <p><strong>E-mail:</strong> <?= htmlspecialchars($cliente['email']) ?></p>

            </div>

            <div class="col-md-6">

                <p><strong>CEP:</strong> <?= htmlspecialchars($cliente['cep']) ?></p>

                <p><strong>Rua:</strong> <?= htmlspecialchars($cliente['rua']) ?></p>

                <p><strong>Número:</strong> <?= htmlspecialchars($cliente['numero']) ?></p>

                <p><strong>Bairro:</strong> <?= htmlspecialchars($cliente['bairro']) ?></p>

                <p><strong>Cidade:</strong> <?= htmlspecialchars($cliente['cidade']) ?> / <?= htmlspecialchars($cliente['estado']) ?></p>

            </div>

        </div>

        <hr>

        <h5>Observações</h5>

        <p>

            <?= nl2br(htmlspecialchars($cliente['observacoes'])) ?: 'Nenhuma observação cadastrada.' ?>

        </p>

    </div>

</div>

<div class="row mt-4">

    <div class="col-md-3">

        <div class="card text-center shadow-sm">

            <div class="card-body">

                <h6>Total Emprestado</h6>

                <h4>R$ <?= number_format($totalEmprestado,2,",",".") ?></h4>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card text-center shadow-sm">

            <div class="card-body">

                <h6>Total Recebido</h6>

                <h4>R$ <?= number_format($totalRecebido,2,",",".") ?></h4>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card text-center shadow-sm">

            <div class="card-body">

                <h6>Saldo Devedor</h6>

                <h4>R$ <?= number_format($saldoDevedor,2,",",".") ?></h4>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card text-center shadow-sm">

            <div class="card-body">

                <h6>Parcelas em atraso</h6>

                <h4><?= $parcelasAtrasadas ?></h4>

            </div>

        </div>

    </div>

</div>

<hr class="my-4">

<h4 class="mb-3">Histórico de Empréstimos</h4>

<?php

$sql = "SELECT *
        FROM emprestimos
        WHERE cliente_id = ?
        ORDER BY data_cadastro DESC";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$emprestimos = $stmt->get_result();

?>

<div class="card">

    <div class="card-body">

        <table class="table table-hover">

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Valor</th>
                    <th>Juros</th>
                    <th>Parcelas</th>
                    <th>Data</th>
                    <th>Status</th>
                    <th width="120">Ações</th>

                </tr>

            </thead>

            <tbody>

            <?php if($emprestimos->num_rows > 0){ ?>

                <?php while($e = $emprestimos->fetch_assoc()){ ?>

                <tr>

                    <td><?= $e["id"] ?></td>

                    <td>
                        R$ <?= number_format($e["valor_emprestado"],2,",",".") ?>
                    </td>

                    <td><?= number_format($e["juros"],2,",",".") ?>%</td>

                    <td><?= $e["quantidade_parcelas"] ?></td>

                    <td><?= date("d/m/Y", strtotime($e["data_cadastro"])) ?></td>

                    <td>

                        <?php

                        if($e["status"]=="EM_ANDAMENTO"){
                            echo "<span class='badge bg-warning'>Em andamento</span>";
                        }

                        if($e["status"]=="QUITADO"){
                            echo "<span class='badge bg-success'>Quitado</span>";
                        }

                        if($e["status"]=="CANCELADO"){
                            echo "<span class='badge bg-danger'>Cancelado</span>";
                        }

                        ?>

                    </td>

                    <td>

                        <a href="index.php?pagina=visualizar_emprestimo&id=<?= $e["id"] ?>"
                           class="btn btn-primary btn-sm">

                            <i class="bi bi-eye-fill"></i>

                        </a>

                    </td>

                </tr>

                <?php } ?>

            <?php }else{ ?>

                <tr>

                    <td colspan="7" class="text-center">

                        Este cliente ainda não possui empréstimos.

                    </td>

                </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>