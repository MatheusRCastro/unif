<?php
session_start();
header('Content-Type: application/json');

// Incluir a conexão com o banco de dados
require_once 'conexao.php';

// Verificar se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber os dados
    $email_cpf = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    // Verificar se os campos estão vazios
    if (empty($email_cpf) || empty($senha)) {
        echo json_encode(['success' => false, 'error' => 'emptyfields']);
        exit();
    }
    
    // Consulta para verificar se o usuário existe (por email ou CPF)
    $sql = "SELECT * FROM usuario WHERE (email = ? OR cpf = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email_cpf, $email_cpf);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $usuario = $result->fetch_assoc();
        
        // Verificar a senha (em texto simples)
        if ($senha === $usuario['senha']) {
            // Login bem-sucedido
            $_SESSION['cpf'] = $usuario['cpf'];
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['adm'] = $usuario['adm'];
            
            echo json_encode([
                'success' => true, 
                'adm' => $usuario['adm'],
                'nome' => $usuario['nome']
            ]);
        } else {
            // Senha incorreta
            echo json_encode(['success' => false, 'error' => 'wrongpassword']);
        }
    } else {
        // Usuário não encontrado
        echo json_encode(['success' => false, 'error' => 'usernotfound']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>