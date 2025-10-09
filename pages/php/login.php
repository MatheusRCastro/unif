<?php
// ✅ ATIVA ERROS TEMPORARIAMENTE para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// ✅ VERIFICA SE O ARQUIVO DE CONEXÃO EXISTE
$conexao_path = 'conexao.php';
if (!file_exists($conexao_path)) {
    echo json_encode([
        'success' => false, 
        'error' => 'file_not_found',
        'message' => 'Arquivo conexao.php não encontrado'
    ]);
    exit();
}

require_once $conexao_path;

// Verificação de conexão
if (!$conn) {
    echo json_encode([
        'success' => false, 
        'error' => 'database_error',
        'message' => 'Conexão não estabelecida - variável $conn é nula'
    ]);
    exit();
}

if ($conn->connect_error) {
    echo json_encode([
        'success' => false, 
        'error' => 'database_error',
        'message' => 'Erro de conexão: ' . $conn->connect_error
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_cpf = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email_cpf) || empty($senha)) {
        echo json_encode(['success' => false, 'error' => 'emptyfields']);
        exit();
    }
    
    try {
        // ✅ DEBUG - REMOVA ESTAS 3 LINHAS DEPOIS
        echo json_encode([
            'success' => true,
            'debug' => '✅ Chegou até a consulta SQL - REMOVER ESTAS LINHAS',
            'email_cpf' => $email_cpf
        ]);
        exit();
        // ✅ FIM DO DEBUG - REMOVER ATÉ AQUI
        
        // ✅ CONSULTA REAL (atualmente comentada para debug)
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
                
                echo json_encode([
                    'success' => true, 
                    'adm' => $usuario['adm'],
                    'nome' => $usuario['nome']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'wrongpassword']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'usernotfound']);
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'exception',
            'message' => 'Exceção: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>