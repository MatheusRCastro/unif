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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: rgb(28, 112, 28);
            --primary-light: rgb(48, 170, 74);
            --primary-dark: rgb(25, 196, 68);
            --secondary: rgb(14, 138, 35);
            --accent: #ff4081;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 16px;
            --box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f5e9 100%);
            color: var(--dark);
            min-height: 100vh;
            line-height: 1.6;
            padding: 40px 20px;
        }

        .main-container {
            max-width: 1600px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 40px;
            align-items: start;
        }

        @media (max-width: 1200px) {
            .main-container {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
            
            .sidebar-section {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            body {
                padding: 20px 15px;
            }
        }

        /* Header */
        .header {
            grid-column: 1 / -1;
            text-align: center;
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            color: white;
        }

        .header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Sidebar Sections */
        .sidebar-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 35px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-top: 5px solid var(--primary);
        }

        .sidebar-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-gray);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.6rem;
            color: var(--dark);
            font-weight: 600;
        }

        /* Comitê Info */
        .comite-info-grid {
            display: grid;
            gap: 20px;
        }

        .info-card {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .info-value {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(25, 196, 68, 0.15);
            color: var(--secondary);
            border: 1px solid rgba(25, 196, 68, 0.3);
        }

        /* Form Section */
        .form-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--box-shadow);
            grid-column: 2;
            transition: var(--transition);
            border-top: 5px solid var(--secondary);
        }

        .form-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        .form-intro {
            color: var(--gray);
            margin-bottom: 30px;
            font-size: 1.05rem;
            line-height: 1.7;
        }

        .input-group {
            margin-bottom: 30px;
        }

        .input-label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .form-input {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid var(--light-gray);
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(28, 112, 28, 0.1);
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 18px 30px;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(28, 112, 28, 0.3);
        }

        /* Lista de Representações */
        .lista-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
            margin-top: 20px;
        }

        .lista-container::-webkit-scrollbar {
            width: 8px;
        }

        .lista-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }

        .lista-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .representacao-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
            border: 2px solid var(--light-gray);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .representacao-card:hover {
            border-color: var(--primary);
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .representacao-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 6px 15px;
            border-radius: 20px;
        }

        .status-free {
            background: rgba(25, 196, 68, 0.1);
            color: var(--secondary);
        }

        .status-occupied {
            background: rgba(255, 64, 129, 0.1);
            color: var(--accent);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-free .status-dot {
            background: var(--secondary);
        }

        .status-occupied .status-dot {
            background: var(--accent);
        }

        /* Mensagens */
        .mensagem {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem.sucesso {
            background: rgba(25, 196, 68, 0.1);
            color: var(--secondary);
            border: 2px solid rgba(25, 196, 68, 0.2);
        }

        .mensagem.erro {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 2px solid rgba(244, 67, 54, 0.2);
        }

        .mensagem.info {
            background: rgba(33, 150, 243, 0.1);
            color: #2196f3;
            border: 2px solid rgba(33, 150, 243, 0.2);
        }

        /* Navigation */
        .navigation {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: var(--light-gray);
            color: var(--dark);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: var(--transition);
        }

        .nav-link:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Counter */
        .counter {
            display: inline-block;
            padding: 4px 12px;
            background: var(--primary);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: 10px;
        }

        /* Logo */
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
            grid-column: 1 / -1;
        }

        .logo {
            max-width: 200px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            transition: var(--transition);
        }

        .logo:hover {
            transform: rotate(-5deg) scale(1.05);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: var(--light-gray);
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--gray);
        }
    </style>
</head>
<body>

<div class="logo-container">
    <img src="images/unif.png" alt="Logo UNIF" class="logo">
</div>

<div class="main-container">
    <!-- Header -->
    <div class="header">
        <h1>
            <i class="fas fa-globe-americas"></i>
            Gerenciar Representações
        </h1>
        <p>Adicione países, organizações ou entidades que serão representadas no comitê</p>
    </div>

    <!-- Sidebar: Informações do Comitê -->
    <div class="sidebar-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <h3 class="section-title">Informações do Comitê</h3>
        </div>

        <?php if (!$is_diretor): ?>
            <div class="mensagem erro">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Acesso negado</strong><br>
                    Você não está registrado como diretor.
                </div>
            </div>
        <?php elseif (!$comite_aprovado): ?>
            <div class="mensagem erro">
                <i class="fas fa-clock"></i>
                <div>
                    <strong>Aguardando Aprovação</strong><br>
                    Nenhum dos seus comitês está com status "aprovado".
                </div>
            </div>
        <?php else: ?>
            <?php if (isset($info_multiplos)): ?>
                <div class="mensagem info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $info_multiplos; ?>
                </div>
            <?php endif; ?>
            
            <div class="comite-info-grid">
                <div class="info-card">
                    <span class="info-label">Comitê</span>
                    <div class="info-value"><?php echo htmlspecialchars($nome_comite); ?></div>
                </div>
                
                <div class="info-card">
                    <span class="info-label">Tipo</span>
                    <div class="info-value"><?php echo htmlspecialchars($tipo_comite ?? 'CSNU'); ?></div>
                </div>
                
                <div class="info-card">
                    <span class="info-label">Status</span>
                    <div class="info-value">
                        <span class="status-badge"><?php echo htmlspecialchars($status_comite); ?></span>
                    </div>
                </div>
                
                <div class="info-card">
                    <span class="info-label">ID do Comitê</span>
                    <div class="info-value">#<?php echo $id_comite_aprovado; ?></div>
                </div>
                
                <?php if (!empty($nome_diretor)): ?>
                <div class="info-card">
                    <span class="info-label">Diretor Responsável</span>
                    <div class="info-value"><?php echo htmlspecialchars($nome_diretor); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="info-card">
                    <span class="info-label">CPF do Diretor</span>
                    <div class="info-value"><?php echo substr($cpf_usuario_logado, 0, 3) . '.***.***-**'; ?></div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($representacoes); ?></div>
                    <div class="stat-label">Representações</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php
                        $livres = 0;
                        foreach ($representacoes as $rep) {
                            if (empty($rep['cpf_delegado'])) {
                                $livres++;
                            }
                        }
                        echo $livres;
                        ?>
                    </div>
                    <div class="stat-label">Disponíveis</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Section: Formulário -->
    <div class="form-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h3 class="section-title">Adicionar Nova Representação</h3>
        </div>
        
        <?php if (!empty($sucesso)): ?>
            <div class="mensagem sucesso">
                <i class="fas fa-check-circle"></i>
                <?php echo $sucesso; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($erro)): ?>
            <div class="mensagem erro">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <?php if ($comite_aprovado): ?>
            <p class="form-intro">
                Adicione os países, organizações internacionais ou entidades que serão representadas pelos delegados neste comitê.
                Cada representação pode ser atribuída a um delegado posteriormente.
            </p>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="input-group">
                    <label class="input-label" for="nome_representacao">
                        <i class="fas fa-flag"></i> Nome da Representação
                    </label>
                    <input type="text" 
                           id="nome_representacao" 
                           name="nome_representacao" 
                           class="form-input"
                           placeholder="Ex: Brasil, França, Estados Unidos, ACNUR, União Europeia, ONU..." 
                           required
                           value="<?php echo isset($_POST['nome_representacao']) ? htmlspecialchars($_POST['nome_representacao']) : ''; ?>"
                           autocomplete="off">
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Adicionar Representação
                </button>
            </form>

            <div class="navigation">
                <a href="painel_diretor.php" class="nav-link">
                    <i class="fas fa-arrow-left"></i> Voltar ao Painel
                </a>
                <a href="painelDiretor.php" class="nav-link">
                    <i class="fas fa-cog"></i> Gerenciar Comitê
                </a>
            </div>
        <?php elseif ($is_diretor && !$comite_aprovado): ?>
            <div class="mensagem info">
                <i class="fas fa-hourglass-half"></i>
                <div>
                    <strong>Aguardando Aprovação</strong><br>
                    Você é diretor, mas precisa aguardar a aprovação do seu comitê para adicionar representações.
                </div>
            </div>
            <div class="navigation">
                <a href="painel_diretor.php" class="nav-link">
                    <i class="fas fa-arrow-left"></i> Voltar ao Painel
                </a>
                <a href="status_comite.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Verificar Status
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Lista de Representações -->
    <?php if ($comite_aprovado): ?>
    <div class="sidebar-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-list-alt"></i>
            </div>
            <h3 class="section-title">
                Representações Cadastradas
                <span class="counter"><?php echo count($representacoes); ?></span>
            </h3>
        </div>
        
        <?php if (empty($representacoes)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-globe-americas"></i>
                </div>
                <h4>Nenhuma representação cadastrada</h4>
                <p>Use o formulário ao lado para adicionar a primeira representação ao comitê.</p>
            </div>
        <?php else: ?>
            <div class="lista-container">
                <?php foreach ($representacoes as $rep): ?>
                    <div class="representacao-card">
                        <div class="representacao-name">
                            <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                        </div>
                        <div class="status-indicator <?php echo empty($rep['cpf_delegado']) ? 'status-free' : 'status-occupied'; ?>">
                            <span class="status-dot"></span>
                            <?php echo empty($rep['cpf_delegado']) ? 'Disponível' : 'Ocupada'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    // Foco automático no campo de entrada
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('nome_representacao');
        if (input) {
            input.focus();
        }
        
        // Adicionar animação às mensagens
        const mensagens = document.querySelectorAll('.mensagem');
        mensagens.forEach((msg, index) => {
            msg.style.animationDelay = `${index * 0.1}s`;
        });
        
        // Animar cards ao passar o mouse
        const cards = document.querySelectorAll('.representacao-card, .sidebar-section, .form-section');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
</script>
</body>
</html>