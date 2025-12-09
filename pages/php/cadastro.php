<?php
session_start();
require_once 'conexao.php'; // Inclui o arquivo de conexão

// Verificar se a conexão foi estabelecida
if (!$conn || $conn->connect_error) {
    echo "0:Erro de conexão com o banco de dados";
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

        // Processar campo professor
        $professor_value = 0;
        if ($eh_professor === 'true' || $eh_professor === true) {
            $professor_value = 1;
        }

        // Inserir usuário
        $sql = "INSERT INTO usuario (cpf, nome, email, restricao_alimentar, alergia, telefone, senha, instituicao, adm, professor) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", 
            $cpf, 
            $nome_temp, 
            $email, 
            $restricao_final, 
            $alergia, 
            $telefone, 
            $senha, 
            $instituicao,
            $professor_value
        );

        if ($stmt->execute()) {
            echo "1:Cadastro realizado com sucesso!";
        } else {
            // Melhor tratamento de erro
            $erro = $stmt->error;
            
            // Verifica se é erro de duplicidade (embora já tenhamos verificado antes)
            if (strpos($erro, 'Duplicate entry') !== false) {
                if (strpos($erro, 'cpf') !== false) {
                    echo "0:CPF já cadastrado";
                } elseif (strpos($erro, 'email') !== false) {
                    echo "0:Email já cadastrado";
                } else {
                    echo "0:Dados duplicados. Tente novamente.";
                }
            } else {
                // Para outros erros, mensagem mais amigável
                echo "0:Erro no cadastro. Tente novamente.";
            }
            
            // Para debug (remova em produção)
            // echo "0:Erro: " . $erro;
        }

        $stmt->close();

    } catch (Exception $e) {
        // Tratamento mais amigável para exceções
        echo "0:Ocorreu um erro inesperado. Tente novamente.";
        
        // Para debug (remova em produção)
        // echo "0:Erro: " . $e->getMessage();
    }
} else {
    echo "0:Método não permitido";
}

// Não fechar a conexão aqui, pois ela é gerenciada pelo arquivo conexao.php
?>