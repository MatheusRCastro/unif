<?php
// conexao.php - CORRIGIDO
define('HOST', 'localhost');
define('USER', 'root');
define('PASSWORD', '');
define('DB', 'unif_db');

// Criar um objeto de conexão
$conn = new mysqli(HOST, USER, PASSWORD, DB);

// Checar a conexão SEM exibir erros na tela
if ($conn->connect_error) {
    // Em produção, apenas define como null sem exibir erro
    $conn = null;
}
?>