<?php

include("config/conexao.php");

$id = $_POST["id"];
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
"sssssssssssssi",
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