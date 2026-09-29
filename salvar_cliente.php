<?php

include("config/conexao.php");

$nome = $_POST["nome"];
$cpf = $_POST["cpf"];
$rg = $_POST["rg"];
$telefone = $_POST["telefone"];
$whatsapp = $_POST["whatsapp"];
$email = $_POST["email"];
$cep = $_POST["cep"];
$rua = $_POST["rua"];
$numero = $_POST["numero"];
$bairro = $_POST["bairro"];
$cidade = $_POST["cidade"];
$estado = $_POST["estado"];
$observacoes = $_POST["observacoes"];

if (!empty($cpf)) {

    $sql = "SELECT id FROM clientes WHERE cpf = ?";

    $stmt = $conexao->prepare($sql);

    $stmt->bind_param("s", $cpf);

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        echo "
        <div style='max-width:600px;margin:50px auto;padding:20px;background:#ffe5e5;border:1px solid #ff4d4d;border-radius:8px;font-family:Arial'>
            <h2>CPF já cadastrado!</h2>
            <p>Já existe um cliente utilizando este CPF.</p>
            <br>
            <a href='javascript:history.back()'>Voltar</a>
        </div>";

        exit;

    }

}

$sql = "INSERT INTO clientes
(nome, cpf, rg, telefone, whatsapp, email, cep, rua, numero, bairro, cidade, estado, observacoes)
VALUES
(?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
"sssssssssssss",
$nome,
$cpf,
$rg,
$telefone,
$whatsapp,
$email,
$cep,
$rua,
$numero,
$bairro,
$cidade,
$estado,
$observacoes
);

if ($stmt->execute()) {

    header("Location: index.php?pagina=clientes");
    exit;

} else {

    if ($conexao->errno == 1062) {

        echo "
        <div style='
            max-width:600px;
            margin:50px auto;
            padding:20px;
            border-radius:10px;
            background:#ffe5e5;
            border:1px solid #ff6b6b;
            font-family:Arial;
        '>

            <h3>CPF já cadastrado.</h3>

            <p>Já existe um cliente utilizando esse CPF.</p>

            <br>

            <a href='javascript:history.back()'
               style='
                    padding:10px 20px;
                    background:#0d6efd;
                    color:white;
                    text-decoration:none;
                    border-radius:6px;
               '>

                Voltar

            </a>

        </div>";
    } else {

        echo "Erro: " . $conexao->error;

    }

}