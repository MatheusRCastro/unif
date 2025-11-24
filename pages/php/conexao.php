<?php
// conexao.php - CORRIGIDO
define('HOST', 'localhost:3307');
define('USER', 'root');
define('PASSWORD', '');
define('DB', 'unif_db');

// Criar um objeto de conexão
$conn = new mysqli(HOST, USER, PASSWORD, DB);

// Checar a conexão SEM exibir erros na tela
if ($conn->connect_error) {
    // Em produção, apenas define como null sem exibir erro
    $conn = null;
} else {
    // Define o charset para evitar problemas com acentos
    $conn->set_charset("utf8mb4");
}
?>