<?php
session_start();

// ✅ ATIVA ERROS TEMPORARIAMENTE para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ VERIFICA SE O ARQUIVO DE CONEXÃO EXISTE
$conexao_path = 'conexao.php';
if (!file_exists($conexao_path)) {
    header("Location: ../login.html?erro=database_error");
    exit();
}

require_once $conexao_path;

// Verificação de conexão
if (!$conn || $conn->connect_error) {
    header("Location: ../login.html?erro=database_error");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_cpf = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email_cpf) || empty($senha)) {
        header("Location: ../login.html?erro=campos_vazios");
        exit();
    }
    
    try {
        // ✅ CONSULTA REAL no banco
        $sql = "SELECT * FROM usuario WHERE (email = ? OR cpf = ?) LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Erro na preparação da consulta: ' . $conn->error);
        }
        
        $stmt->bind_param("ss", $email_cpf, $email_cpf);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro na execução da consulta: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $usuario = $result->fetch_assoc();
            
            // ⚠️ ATENÇÃO: Senha em texto puro - migre para hash depois!
            if ($senha === $usuario['senha']) {
                $_SESSION['cpf'] = $usuario['cpf'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['adm'] = $usuario['adm'];
                $_SESSION['telefone'] = $usuario['telefone'];
                $_SESSION['instituicao'] = $usuario['instituicao'];

                // Redireciona conforme o tipo de usuário
                if ($usuario['adm']) {
                    header("Location: ../painelControle.php");
                } else {
                    header("Location: ../inicio.php");
                }
                exit();
            } else {
                header("Location: ../login.html?erro=senha_incorreta");
                exit();
            }
        } else {
            header("Location: ../login.html?erro=email_nao_encontrado");
            exit();
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        // Log do erro (opcional)
        error_log("Erro no login: " . $e->getMessage());
        header("Location: ../login.html?erro=database_error");
        exit();
    }
} else {
    header("Location: ../login.html?erro=metodo_nao_permitido");
    exit();
}
?>