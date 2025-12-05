<?php
session_start();

$host = 'localhost:3307';
$user = 'root';
$password = '';
$database = 'unif_db';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    echo "0:Erro de conexão com o banco";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

    // Validações
    if (empty($cpf) || empty($email) || empty($senha) || empty($telefone) || empty($instituicao)) {
        echo "0:Preencha todos os campos obrigatórios";
        exit();
    }

    try {
        // Verificar CPF
        $sql = "SELECT cpf FROM usuario WHERE cpf = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "0:CPF já cadastrado";
            $stmt->close();
            exit();
        }
        $stmt->close();

        // Verificar email
        $sql = "SELECT email FROM usuario WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "0:Email já cadastrado";
            $stmt->close();
            exit();
        }
        $stmt->close();

        // Processar opção alimentar
        $restricao_final = '';
        if (!empty($restricao_alimentar)) {
            $restricao_final = $restricao_alimentar;
        } elseif (!empty($opcao_alimentar)) {
            $opcoes_alimentares = [
                'Oa1' => 'Veganismo',
                'Oa2' => 'Vegetarianismo', 
                'Oa3' => 'Tradicional'
            ];
            $restricao_final = $opcoes_alimentares[$opcao_alimentar] ?? '';
        }

        $nome_temp = "Usuário " . substr($cpf, 0, 5);

        // Inserir usuário
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
            echo "1:Cadastro realizado com sucesso";
        } else {
            echo "0:Erro no cadastro: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        echo "0:Erro: " . $e->getMessage();
    }
} else {
    echo "0:Método não permitido";
}
?>