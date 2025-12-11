<?php
session_start();
require_once 'conexao.php'; // Inclui o arquivo de conexão

// Verificar se a conexão foi estabelecida
if (!$conn || $conn->connect_error) {
    echo "0:Erro de conexão com o banco de dados";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
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
    $telefone_instituicao = $_POST['telefone_instituicao'] ?? ''; // NOVO CAMPO
    $email_instituicao = $_POST['email_instituicao'] ?? ''; // NOVO CAMPO

    // Validações (TODAS MANTIDAS)
    if (empty($nome) || empty($cpf) || empty($email) || empty($senha) || empty($telefone) || empty($instituicao)) {
        echo "0:Preencha todos os campos obrigatórios";
        exit();
    }

    // Validação adicional para professores (MANTIDA)
    if ($eh_professor === 'true' && (empty($telefone_instituicao) || empty($email_instituicao))) {
        echo "0:Para professores, preencha os dados de contato da instituição";
        exit();
    }

    try {
        // Verificar CPF (MANTIDA)
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

        // Verificar email (MANTIDA)
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

        // Processar opção alimentar (MANTIDA)
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

        // Processar campo professor - MODIFICADO para ENUM
        // ANTES: 0 ou 1 (integer)
        // DEPOIS: 'aluno', 'pendente', 'aprovado', 'reprovado' (string ENUM)
        $professor_value = 'aluno'; // Default é 'aluno'
        if ($eh_professor === 'true' || $eh_professor === true) {
            $professor_value = 'pendente'; // Professores começam como 'pendente' (precisam de aprovação)
        }

        // Inserir usuário - MODIFICADO para incluir novos campos
        $sql = "INSERT INTO usuario (cpf, nome, email, restricao_alimentar, alergia, telefone, 
                senha, instituicao, adm, professor, telefone_instituicao, email_instituicao) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        // MODIFICADO: adicionados 2 novos parâmetros no bind_param
        $stmt->bind_param("sssssssssss", 
            $cpf, 
            $nome,
            $email, 
            $restricao_final, 
            $alergia, 
            $telefone, 
            $senha, 
            $instituicao,
            $professor_value, // AGORA é string
            $telefone_instituicao, // NOVO
            $email_instituicao // NOVO
        );

        if ($stmt->execute()) {
            // MANTIDO o tratamento especial para professores
            if ($professor_value == 'pendente') {
                // ADICIONADO: mensagem específica para professores
                echo "1:Cadastro realizado com sucesso! Como professor, sua conta precisa de aprovação.";
            } else {
                echo "1:Cadastro realizado com sucesso!";
            }
        } else {
            // MANTIDO tratamento de erros
            $erro = $stmt->error;
            
            if (strpos($erro, 'Duplicate entry') !== false) {
                if (strpos($erro, 'cpf') !== false) {
                    echo "0:CPF já cadastrado";
                } elseif (strpos($erro, 'email') !== false) {
                    echo "0:Email já cadastrado";
                } else {
                    echo "0:Dados duplicados. Tente novamente.";
                }
            } else {
                echo "0:Erro no cadastro. Tente novamente.";
            }
            
            // Para debug (remova em produção)
            // error_log("Erro cadastro: " . $erro);
        }

        $stmt->close();

    } catch (Exception $e) {
        // MANTIDO tratamento de exceções
        error_log("Exception cadastro: " . $e->getMessage());
        echo "0:Ocorreu um erro inesperado. Tente novamente.";
    }
} else {
    echo "0:Método não permitido";
}

// Não fechar a conexão aqui, pois ela é gerenciada pelo arquivo conexao.php
?>