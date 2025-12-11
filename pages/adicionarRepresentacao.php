<?php
// =================================================================================
// 1. CONFIGURAÇÃO DE SESSÃO E CONEXÃO
// =================================================================================

session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['cpf'])) {
    header("Location: login.html");
    exit();
}

// Inclui o arquivo de conexão
include 'php/conexao.php'; 

// Verificação de conexão
if (!isset($conn) || $conn === null) {
    die("<h1>Erro Crítico de Conexão com o Banco de Dados</h1><p>Não foi possível estabelecer a conexão.</p>");
}

$cpf_usuario_logado = $_SESSION['cpf'];
$erro = "";
$sucesso = "";
$comite_aprovado = false;
$id_comite_aprovado = null;
$id_unif_associado = null;
$nome_comite = "";
$is_diretor = false;

// =================================================================================
// 2. VERIFICAÇÃO SE O USUÁRIO É DIRETOR (VERIFICA NA TABELA DIRETOR)
// =================================================================================

// Primeiro verifica se o CPF está na tabela diretor
$sql_check_diretor = "SELECT COUNT(*) as total FROM diretor WHERE cpf = ?";
if ($stmt_check = $conn->prepare($sql_check_diretor)) {
    $stmt_check->bind_param("s", $cpf_usuario_logado);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    $is_diretor = ($row_check['total'] > 0);
    $stmt_check->close();
}

// Se não for diretor, redireciona
if (!$is_diretor) {
    header("Location: acesso_negado.html");
    exit();
}

// =================================================================================
// 3. VERIFICAÇÃO DE AUTORIZAÇÃO E OBTENÇÃO DOS DADOS DO COMITÊ ATIVO
// =================================================================================

$sql_auth = "SELECT 
                c.id_comite, 
                c.id_unif, 
                c.nome_comite,
                c.tipo_comite,
                c.status
             FROM comite c
             INNER JOIN diretor d ON c.id_comite = d.id_comite
             WHERE d.cpf = ? AND c.status = 'aprovado'"; 

if ($stmt = $conn->prepare($sql_auth)) {
    $stmt->bind_param("s", $cpf_usuario_logado); 
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $id_comite_aprovado = $row['id_comite'];
        $id_unif_associado = $row['id_unif'];
        $nome_comite = $row['nome_comite'];
        $tipo_comite = $row['tipo_comite'];
        $status_comite = $row['status'];
        $comite_aprovado = true;
    } elseif ($result->num_rows > 1) {
        // Se o diretor for de mais de um comitê, pegamos o primeiro aprovado
        $row = $result->fetch_assoc();
        $id_comite_aprovado = $row['id_comite'];
        $id_unif_associado = $row['id_unif'];
        $nome_comite = $row['nome_comite'];
        $tipo_comite = $row['tipo_comite'];
        $status_comite = $row['status'];
        $comite_aprovado = true;
        
        // Se houver mais de um comitê, podemos mostrar uma mensagem informativa
        $info_multiplos = "Você é diretor de " . $result->num_rows . " comitês aprovados. Exibindo o primeiro.";
    }
    $stmt->close();
}

// =================================================================================
// 4. PROCESSAMENTO DO FORMULÁRIO (INSERÇÃO DA REPRESENTAÇÃO)
// =================================================================================
if ($comite_aprovado && $_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_representacao = trim($_POST["nome_representacao"]);

    if (empty($nome_representacao)) {
        $erro = "O nome da representação não pode ser vazio.";
    } else {
        // Verifica se a representação já existe no comitê
        $sql_check = "SELECT id_representacao FROM representacao 
                      WHERE nome_representacao = ? AND id_comite = ?";
        
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("si", $nome_representacao, $id_comite_aprovado);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $erro = "Esta representação ('" . htmlspecialchars($nome_representacao) . "') já foi adicionada a este comitê.";
                $stmt_check->close();
            } else {
                $stmt_check->close();
                
                // Insere a nova representação
                $sql_insert = "INSERT INTO representacao 
                               (nome_representacao, id_comite, id_unif, cpf_delegado) 
                               VALUES (?, ?, ?, NULL)";

                if ($stmt_insert = $conn->prepare($sql_insert)) {
                    $stmt_insert->bind_param("sii", $nome_representacao, $id_comite_aprovado, $id_unif_associado);

                    if ($stmt_insert->execute()) {
                        $sucesso = "Representação '" . htmlspecialchars($nome_representacao) . "' adicionada com sucesso!";
                        // Recarrega a lista de representações após inserção
                        $_POST = array(); // Limpa o formulário
                    } else {
                        $erro = "Erro ao adicionar representação. Tente novamente.";
                    }
                    $stmt_insert->close();
                }
            }
        }
    }
}

// =================================================================================
// 5. BUSCAR REPRESENTAÇÕES EXISTENTES
// =================================================================================
$representacoes = [];
if ($comite_aprovado) {
    $sql_lista = "SELECT nome_representacao, cpf_delegado 
                  FROM representacao 
                  WHERE id_comite = ? 
                  ORDER BY nome_representacao";
    
    if ($stmt_lista = $conn->prepare($sql_lista)) {
        $stmt_lista->bind_param("i", $id_comite_aprovado);
        $stmt_lista->execute();
        $result_lista = $stmt_lista->get_result();
        
        while ($row = $result_lista->fetch_assoc()) {
            $representacoes[] = $row;
        }
        $stmt_lista->close();
    }
    
    // Buscar também informações do diretor para exibição
    $sql_diretor_info = "SELECT u.nome FROM diretor d 
                        JOIN usuario u ON d.cpf = u.cpf 
                        WHERE d.cpf = ? AND d.id_comite = ? 
                        LIMIT 1";
    
    $nome_diretor = "";
    if ($stmt_diretor = $conn->prepare($sql_diretor_info)) {
        $stmt_diretor->bind_param("si", $cpf_usuario_logado, $id_comite_aprovado);
        $stmt_diretor->execute();
        $result_diretor = $stmt_diretor->get_result();
        if ($result_diretor->num_rows > 0) {
            $row_diretor = $result_diretor->fetch_assoc();
            $nome_diretor = $row_diretor['nome'];
        }
        $stmt_diretor->close();
    }
}

// Fecha a conexão
if ($conn !== null) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Representação - UNIF</title>
    <style>
        /* Fundo e fontes */
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e0f2fe 100%);
        }

        /* Container principal */
        .container {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 60px;
            padding: 40px;
            max-width: 1200px;
            width: 100%;
            animation: fadeIn 0.8s ease-in-out;
        }

        /* Logo */
        .logo-box {
            text-align: center;
        }

        .logo-box img {
            width: 320px;
            filter: drop-shadow(0px 4px 10px rgba(0, 0, 0, 0.5));
            transition: transform 0.3s ease;
        }

        .logo-box img:hover {
            transform: scale(1.05);
        }

        /* Painel de informações do comitê */
        .info-box {
            background: #fff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0px 6px 16px rgba(0, 0, 0, 0.4);
            width: 320px;
            margin-bottom: 30px;
            border-left: 5px solid #1a5c1a;
        }

        .info-box h3 {
            color: #1a5c1a;
            margin-top: 0;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            text-transform: uppercase;
        }

        .info-item {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-item strong {
            color: #333;
        }

        /* Formulário */
        .form-box {
            background: #fff;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0px 6px 16px rgba(0, 0, 0, 0.4);
            text-align: center;
            width: 420px;
            transition: transform 0.3s ease;
            border-top: 5px solid #1a5c1a;
        }

        .form-box:hover {
            transform: translateY(-5px);
        }

        .form-box h2 {
            margin-bottom: 25px;
            font-size: 22px;
            text-transform: uppercase;
            color: #1a5c1a;
            font-weight: bold;
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
        }

        /* Inputs */
        form input {
            width: 100%;
            margin-bottom: 15px;
            padding: 14px;
            border: 1px solid #aaa;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            transition: border 0.3s, box-shadow 0.3s;
        }

        form input:focus {
            border-color: #1fa51f;
            box-shadow: 0 0 6px rgba(31, 165, 31, 0.6);
        }

        /* Botão */
        form button {
            background: linear-gradient(90deg, #000, #1fa51f);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin: 20px 0 15px 0;
            text-transform: uppercase;
            font-weight: bold;
            transition: background 0.3s, transform 0.2s;
        }

        form button:hover {
            background: linear-gradient(90deg, #1fa51f, #000);
            transform: scale(1.03);
        }

        /* Lista de representações */
        .lista-box {
            background: #fff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0px 6px 16px rgba(0, 0, 0, 0.4);
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            border-left: 5px solid #1a5c1a;
        }

        .lista-box h3 {
            color: #1a5c1a;
            margin-top: 0;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            text-transform: uppercase;
        }

        .representacao-item {
            padding: 12px;
            margin: 8px 0;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #1fa51f;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .representacao-item span {
            font-weight: 600;
            color: #333;
        }

        .status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .status.livre {
            background: #e8f5e9;
            color: #1a5c1a;
        }

        .status.ocupado {
            background: #ffebee;
            color: #c62828;
        }

        /* Mensagens de feedback */
        .mensagem {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }

        .mensagem.sucesso {
            background: #e8f5e9;
            color: #1a5c1a;
            border: 1px solid #c8e6c9;
        }

        .mensagem.erro {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .mensagem.info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        /* Links */
        .links {
            margin-top: 20px;
            text-align: center;
        }

        .links a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
            margin: 0 10px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        /* Responsividade */
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                align-items: center;
                gap: 30px;
                padding: 20px;
            }
            
            .info-box, .form-box, .lista-box {
                width: 100%;
                max-width: 500px;
            }
        }

        /* Animações */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Logo -->
    <div class="logo-box">
        <img src="images/unif.png" alt="Logo UNIF" class="logo">
    </div>

    <!-- Painel de Informações -->
    <div class="info-box">
        <h3>📋 Informações do Comitê</h3>
        
        <?php if (!$is_diretor): ?>
            <div class="mensagem erro">
                ❌ Acesso negado. Você não está registrado como diretor.
            </div>
        <?php elseif (!$comite_aprovado): ?>
            <div class="mensagem erro">
                ⚠️ Você é diretor, mas nenhum dos seus comitês está com status "aprovado".
                <br><br>
                <small>Status necessário: <strong>aprovado</strong></small>
            </div>
        <?php else: ?>
            <?php if (isset($info_multiplos)): ?>
                <div class="mensagem info">
                    ℹ️ <?php echo $info_multiplos; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-item">
                <strong>Comitê:</strong><br>
                <?php echo htmlspecialchars($nome_comite); ?>
            </div>
            <div class="info-item">
                <strong>Tipo:</strong><br>
                <?php echo htmlspecialchars($tipo_comite ?? 'CSNU'); ?>
            </div>
            <div class="info-item">
                <strong>Status:</strong><br>
                <span style="color: #1a5c1a; font-weight: bold;"><?php echo htmlspecialchars($status_comite); ?></span>
            </div>
            <div class="info-item">
                <strong>ID do Comitê:</strong><br>
                <?php echo $id_comite_aprovado; ?>
            </div>
            <?php if (!empty($nome_diretor)): ?>
            <div class="info-item">
                <strong>Diretor:</strong><br>
                <?php echo htmlspecialchars($nome_diretor); ?>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <strong>CPF:</strong><br>
                <?php echo substr($cpf_usuario_logado, 0, 3) . '.***.***-**'; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Formulário -->
    <div class="form-box">
        <h2>🗺️ Adicionar Representação</h2>
        
        <?php if (!empty($sucesso)): ?>
            <div class="mensagem sucesso">✅ <?php echo $sucesso; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($erro)): ?>
            <div class="mensagem erro">❌ <?php echo $erro; ?></div>
        <?php endif; ?>

        <?php if ($comite_aprovado): ?>
            <p style="color: #666; margin-bottom: 25px;">
                Adicione países, organizações ou entidades que serão representadas neste comitê.
            </p>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="text" id="nome_representacao" name="nome_representacao" 
                       placeholder="Ex: Brasil, França, ACNUR, União Europeia" 
                       required
                       value="<?php echo isset($_POST['nome_representacao']) ? htmlspecialchars($_POST['nome_representacao']) : ''; ?>">
                
                <button type="submit">➕ Adicionar Representação</button>
            </form>

            <div class="links">
                <a href="painel_diretor.php">← Voltar ao Painel</a>
                <a href="gerenciar_comite.php">Gerenciar Comitê</a>
            </div>
        <?php elseif ($is_diretor && !$comite_aprovado): ?>
            <div class="mensagem info">
                ℹ️ Você é diretor, mas precisa aguardar a aprovação do seu comitê para adicionar representações.
            </div>
            <div class="links">
                <a href="painel_diretor.php">← Voltar ao Painel</a>
                <a href="status_comite.php">Verificar Status</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lista de Representações -->
    <?php if ($comite_aprovado): ?>
    <div class="lista-box">
        <h3>📋 Representações Cadastradas</h3>
        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
            Total: <?php echo count($representacoes); ?>
        </p>
        
        <?php if (empty($representacoes)): ?>
            <div style="text-align: center; padding: 20px; color: #666;">
                Nenhuma representação cadastrada ainda.
                <br>
                <small>Adicione a primeira usando o formulário ao lado.</small>
            </div>
        <?php else: ?>
            <?php foreach ($representacoes as $rep): ?>
                <div class="representacao-item">
                    <span><?php echo htmlspecialchars($rep['nome_representacao']); ?></span>
                    <div class="status <?php echo empty($rep['cpf_delegado']) ? 'livre' : 'ocupado'; ?>">
                        <?php echo empty($rep['cpf_delegado']) ? 'LIVRE' : 'OCUPADO'; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>