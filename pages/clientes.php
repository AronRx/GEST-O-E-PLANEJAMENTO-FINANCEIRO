<?php

$pesquisa = "";

if (isset($_GET["pesquisa"])) {
    $pesquisa = trim($_GET["pesquisa"]);
}

if ($pesquisa != "") {

    $sql = "SELECT * FROM clientes
            WHERE nome LIKE ?
               OR cpf LIKE ?
               OR telefone LIKE ?
            ORDER BY nome";

    $stmt = $conexao->prepare($sql);

    $like = "%" . $pesquisa . "%";

    $stmt->bind_param("sss", $like, $like, $like);

    $stmt->execute();

    $resultado = $stmt->get_result();

} else {

    $resultado = $conexao->query("SELECT * FROM clientes ORDER BY nome");

}

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">Clientes</h2>

            <p class="text-muted mb-0">
                Gerencie os clientes cadastrados.
            </p>

        </div>

        <a href="index.php?pagina=novo_cliente" class="btn btn-primary">

            <i class="bi bi-plus-circle me-1"></i>

            Novo Cliente

        </a>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <form method="GET" class="mb-4">

                <input type="hidden" name="pagina" value="clientes">

                <div class="input-group">

                    <input
                        type="text"
                        class="form-control"
                        name="pesquisa"
                        placeholder="Pesquisar por nome, CPF ou telefone..."
                        value="<?= htmlspecialchars($pesquisa) ?>"
                    >

                    <button class="btn btn-primary">

                        <i class="bi bi-search"></i>

                        Pesquisar

                    </button>

                </div>

            </form>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>

                            <th>Nome</th>

                            <th>CPF</th>

                            <th>Telefone</th>

                            <th>WhatsApp</th>

                            <th class="text-center">Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if($resultado->num_rows > 0){ ?>

                        <?php while($cliente = $resultado->fetch_assoc()){ ?>

                            <tr>

                                <td><?= $cliente["id"] ?></td>

                                <td>

                                    <a href="index.php?pagina=visualizar_cliente&id=<?= $cliente["id"] ?>" class="fw-semibold text-decoration-none">

                                        <?= htmlspecialchars($cliente["nome"]) ?>

                                    </a>

                                </td>

                                <td><?= htmlspecialchars($cliente["cpf"]) ?></td>

                                <td><?= htmlspecialchars($cliente["telefone"]) ?></td>

                                <td><?= htmlspecialchars($cliente["whatsapp"]) ?></td>

                                <td class="text-center">

                                    <a href="index.php?pagina=visualizar_cliente&id=<?= $cliente["id"] ?>" class="btn btn-primary btn-sm">

                                        <i class="bi bi-eye-fill"></i>

                                    </a>

                                    <a href="index.php?pagina=editar_cliente&id=<?= $cliente["id"] ?>" class="btn btn-warning btn-sm">

                                        <i class="bi bi-pencil-fill"></i>

                                    </a>

                                    <a href="excluir_cliente.php?id=<?= $cliente["id"] ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Deseja realmente excluir este cliente?')">

                                        <i class="bi bi-trash-fill"></i>

                                    </a>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="6" class="text-center text-muted py-4">

                                Nenhum cliente encontrado.

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>