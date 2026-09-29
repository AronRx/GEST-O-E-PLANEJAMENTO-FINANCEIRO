<?php

$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "sistema_financeiro";

$conexao = new mysqli($servidor, $usuario, $senha, $banco);

if ($conexao->connect_error) {
    die("Erro ao conectar: " . $conexao->connect_error);
}

?>