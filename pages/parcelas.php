<?php

$sql = "SELECT
            parcelas.*,
            clientes.nome,
            emprestimos.id AS emprestimo_id
        FROM parcelas
        INNER JOIN emprestimos
            ON parcelas.emprestimo_id = emprestimos.id
        INNER JOIN clientes
            ON emprestimos.cliente_id = clientes.id
        ORDER BY clientes.nome, parcelas.numero_parcela";

$resultado = $conexao->query($sql);

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Controle de Parcelas</h2>
</div>

<?php

$clienteAtual = "";

while($linha = $resultado->fetch_assoc()){

    if($clienteAtual != $linha["nome"]){

        if($clienteAtual != ""){
            ?>

                    </tbody>

                </table>

            </div>

        </div>

            <?php
        }

        $clienteAtual = $linha["nome"];
        ?>

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-light">

                <h5 class="mb-0">
                    <i class="bi bi-person-fill"></i>
                    <?= htmlspecialchars($clienteAtual) ?>
                </h5>

            </div>

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-dark">

                            <tr>

                                <th width="100">Parcela</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th width="160">Ações</th>

                            </tr>

                        </thead>

                        <tbody>

        <?php
    }

    $dataHoje = date("Y-m-d");

    if($linha["data_pagamento"] != NULL){

        $status = "PAGA";
        $cor = "success";

    }else{

        if($linha["vencimento"] < $dataHoje){

            $status = "ATRASADA";
            $cor = "danger";

        }else{

            $dias = (strtotime($linha["vencimento"]) - strtotime($dataHoje)) / 86400;

            if($dias <= 5){

                $status = "VENCENDO";
                $cor = "warning";

            }else{

                $status = "EM DIA";
                $cor = "secondary";

            }

        }

    }

?>

<tr>

    <td><?= $linha["numero_parcela"] ?></td>

    <td><?= date("d/m/Y", strtotime($linha["vencimento"])) ?></td>

    <td>
        R$ <?= number_format($linha["valor_parcela"],2,",",".") ?>
    </td>

    <td>

        <span class="badge bg-<?= $cor ?>">
            <?= $status ?>
        </span>

    </td>

    <td>

        <?php if($linha["data_pagamento"] == NULL){ ?>

            <a href="index.php?pagina=receber_parcela&id=<?= $linha["id"] ?>"
               class="btn btn-success btn-sm">

                Receber

            </a>

        <?php }else{ ?>

            <button class="btn btn-secondary btn-sm" disabled>

                ✔ Pago

            </button>

        <?php } ?>

    </td>

</tr>

<?php

}

if($clienteAtual != ""){

?>

                    </tbody>

                </table>

            </div>

        </div>

<?php

}else{

?>

<div class="card shadow-sm">

    <div class="card-body text-center">

        Nenhuma parcela cadastrada.

    </div>

</div>

<?php } ?>