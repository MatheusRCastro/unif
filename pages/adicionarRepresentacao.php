<?php
// =================================================================================
// 1. CONFIGURAÇÃO DE SESSÃO E CONEXÃO
// =================================================================================

session_start();

// Inclui o arquivo de conexão. O caminho deve ser relativo ao script atual.
// Se este script estiver na raiz e 'conexao.php' estiver em 'php/', o caminho é 'php/conexao.php'.
// Nota: O nome do arquivo foi corrigido de 'conxao.php' para 'conexao.php' com base no código fornecido.
include 'php/conexao.php'; 

// --- ⚠️ VERIFICAÇÃO DE CONEXÃO ⚠️ ---
// Verifica se o objeto $conn foi criado com sucesso no 'conexao.php'
if (!isset($conn) || $conn === null) {
    // Se a conexão falhou no 'conexao.php', exibe uma mensagem de erro fatal e interrompe a execução.
    // Isso evita erros no restante do código que depende da conexão.
    die("<h1>Erro Crítico de Conexão com o Banco de Dados</h1><p>Não foi possível estabelecer a conexão. Verifique o arquivo php/conexao.php.</p>");
}

// --- ⚠️ ATENÇÃO: Obtenção do CPF do Diretor ⚠️ ---
// Este é um placeholder. Garanta que o CPF venha da sua autenticação.
$cpf_diretor_logado = $_SESSION['user_cpf'] ?? '136.204.356-77'; 

// Inicializar variáveis
$erro = "";
$sucesso = "";
$comite_aprovado = false;
$id_comite_aprovado = null;
$id_unif_associado = null;
$nome_comite = "";

// =================================================================================
// 2. VERIFICAÇÃO DE AUTORIZAÇÃO E OBTENÇÃO DOS DADOS DO COMITÊ ATIVO
// =================================================================================

// SQL para buscar o Comitê onde o Diretor atua E cujo status é 'aprovado'
$sql_auth = "SELECT 
                c.id_comite, 
                c.id_unif, 
                c.nome_comite 
             FROM comite c
             INNER JOIN diretor d ON c.id_comite = d.id_comite
             WHERE d.cpf = ? AND c.status = 'aprovado'"; 

if ($stmt = $conn->prepare($sql_auth)) {
    $stmt->bind_param("s", $cpf_diretor_logado); 
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $id_comite_aprovado = $row['id_comite'];
        $id_unif_associado = $row['id_unif'];
        $nome_comite = $row['nome_comite'];
        $comite_aprovado = true;
    }
    $stmt->close();
}

// =================================================================================
// 3. PROCESSAMENTO DO FORMULÁRIO (INSERÇÃO DA REPRESENTAÇÃO)
// =================================================================================
if ($comite_aprovado && $_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_representacao = trim($_POST["nome_representacao"]);

    if (empty($nome_representacao)) {
        $erro = "O nome da representação não pode ser vazio.";
    } else {
        $sql_insert = "INSERT INTO representacao 
                       (nome_representacao, id_comite, id_unif, cpf_delegado) 
                       VALUES (?, ?, ?, NULL)";

        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("sii", $nome_representacao, $id_comite_aprovado, $id_unif_associado);

            if ($stmt_insert->execute()) {
                $sucesso = "Representação '{$nome_representacao}' adicionada com sucesso ao comitê '{$nome_comite}'.";
            } else {
                if ($conn->errno == 1062) {
                     $erro = "Esta representação ('{$nome_representacao}') já foi adicionada a este comitê.";
                } else {
                     $erro = "Erro ao adicionar representação: " . $stmt_insert->error;
                }
            }
            $stmt_insert->close();
        }
    }
}

// Fecha a conexão no final do script
if ($conn !== null) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Representação</title>
    <style>
        /* CSS aplicado diretamente para garantir o carregamento */
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px;
            background-color: #f8f9fa; 
        }
        .container { 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 30px; 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
            background-color: #fff; 
            box-shadow: 0 0 10px rgba(0,0,0,0.05); 
        }
        h2 { 
            border-bottom: 3px solid #007bff; 
            padding-bottom: 10px; 
            color: #343a40; 
            margin-top: 0;
        }
        .success { 
            color: #38761d; 
            background-color: #d9ead3; 
            border: 1px solid #c9d9c7; 
            padding: 10px; 
            margin-bottom: 15px; 
            border-radius: 4px; 
        }
        .error { 
            color: #cc0000; 
            background-color: #f4cccc; 
            border: 1px solid #ebc3c3; 
            padding: 10px; 
            margin-bottom: 15px; 
            border-radius: 4px; 
        }
        label { 
            display: block; 
            margin-top: 15px; 
            font-weight: bold; 
            color: #495057; 
        }
        input[type="text"] { 
            width: 100%; 
            padding: 10px; 
            margin-top: 5px; 
            border: 1px solid #ced4da; 
            border-radius: 4px; 
            box-sizing: border-box; 
            transition: border-color 0.3s;
        }
        input[type="text"]:focus {
            border-color: #007bff;
            outline: none;
        }
        button { 
            padding: 10px 20px; 
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-top: 20px; 
            font-size: 16px; 
            transition: background-color 0.3s; 
        }
        button:hover { 
            background-color: #0056b3; 
        }
    </style>
</head>
<body>

<div class="container">
    <h2>🗺️ Adicionar Representações ao Comitê</h2>

    <?php if (!empty($sucesso)): ?>
        <p class="success"><?php echo $sucesso; ?></p>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
        <p class="error"><?php echo $erro; ?></p>
    <?php endif; ?>

    <?php if (!$comite_aprovado): ?>
        <p class="error">
            Acesso negado. Você precisa estar registrado como Diretor e o seu Comitê precisa ter o status 'aprovado' para adicionar representações.
        </p>
    <?php else: ?>
        <p><strong>Comitê Ativo:</strong> <?php echo htmlspecialchars($nome_comite); ?> (ID: <?php echo $id_comite_aprovado; ?>)</p>
        <p>Preencha o nome da Representação que será adicionada à lista deste Comitê na UNIF (ID: <?php echo $id_unif_associado; ?>).</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <label for="nome_representacao">Nome da Representação (Ex: Espanha, Rússia, ACNUR):</label>
            <input type="text" id="nome_representacao" name="nome_representacao" required>
            
            <button type="submit">Adicionar Representação</button>
        </form>
    <?php endif; ?>

</div>

</body>
</html>