<?php
session_start();
header('Content-Type: application/json');

// ✅ CONFIGURAÇÃO DE CONEXÃO
$host = 'localhost:3307';
$user = 'root';
$password = '';
$database = 'unif_db';

// Conectar ao banco
$conn = new mysqli($host, $user, $password, $database);

// Verificar conexão
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'database_error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Coletar dados do formulário
    $cpf = $_POST['cpf'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $instituicao = $_POST['instituicao'] ?? '';
    $medicamento = $_POST['medicamento'] ?? '';
    $opcao_alimentar = $_POST['opcao_alimentar'] ?? '';
    $alergia = $_POST['alergia'] ?? '';
    $restricao_alimentar = $_POST['restricao_alimentar'] ?? '';
    $eh_professor = $_POST['eh_professor'] ?? 'false';
    
    // Campos opcionais do professor
    $telefone_instituicao = $_POST['telefone_instituicao'] ?? '';
    $email_instituicao = $_POST['email_instituicao'] ?? '';

    // Validações básicas
    if (empty($cpf) || empty($email) || empty($senha) || empty($telefone) || empty($instituicao)) {
        echo json_encode(['success' => false, 'error' => 'campos_vazios']);
        exit();
    }

    try {
        // Verificar se CPF já existe
        $sql = "SELECT cpf FROM usuario WHERE cpf = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'cpf_existe']);
            exit();
        }
        $stmt->close();

        // Verificar se email já existe
        $sql = "SELECT email FROM usuario WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'email_existe']);
            exit();
        }
        $stmt->close();

        // Processar opção alimentar para o banco
        $restricao_final = '';
        if (!empty($restricao_alimentar)) {
            $restricao_final = $restricao_alimentar;
        } else {
            // Usar a opção do select se não tiver restrição específica
            $opcoes_alimentares = [
                'Oa1' => 'Veganismo',
                'Oa2' => 'Vegetarianismo', 
                'Oa3' => 'Tradicional'
            ];
            $restricao_final = $opcoes_alimentares[$opcao_alimentar] ?? '';
        }

        // Gerar um nome temporário (o usuário pode editar depois)
        $nome_temp = "Usuário " . substr($cpf, 0, 5);

        // Inserir usuário no banco
        $sql = "INSERT INTO usuario (cpf, nome, email, restricao_alimentar, alergia, telefone, senha, instituicao, adm) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", 
            $cpf, 
            $nome_temp, 
            $email, 
            $restricao_final, 
            $alergia, 
            $telefone, 
            $senha, 
            $instituicao
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Usuário cadastrado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar: ' . $stmt->error]);
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
}
?>