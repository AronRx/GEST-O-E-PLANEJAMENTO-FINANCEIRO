<?php

include("config/conexao.php");

$id = trim((string) ($_POST["id"] ?? ""));
if (!Database::isUuid($id)) {
    http_response_code(422);
    exit("Cliente inválido.");
}
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

if ($cpf !== "") {
    $stmtDuplicado = $conexao->prepare("SELECT id FROM clientes WHERE cpf = ? AND id <> ? LIMIT 1");
    $stmtDuplicado->bind_param("ss", $cpf, $id);
    $stmtDuplicado->execute();
    if ($stmtDuplicado->get_result()->num_rows > 0) {
        echo "<div style='max-width:600px;margin:50px auto;padding:20px;background:#ffe5e5;border:1px solid #ff4d4d;border-radius:8px;font-family:Arial'>
            <h2>CPF já cadastrado!</h2>
            <p>Outro cliente já utiliza este CPF.</p>
            <br><a href='javascript:history.back()'>Voltar</a>
        </div>";
        exit;
    }
}

$sql = "UPDATE clientes SET
nome=?,
cpf=?,
rg=?,
telefone=?,
whatsapp=?,
email=?,
cep=?,
rua=?,
numero=?,
bairro=?,
cidade=?,
estado=?,
observacoes=?
WHERE id=?";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
"ssssssssssssss",
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
$observacoes,
$id
);

$stmt->execute();

header("Location: index.php?pagina=clientes");
exit;
