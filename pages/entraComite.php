<?php
session_start();
require_once 'php/conexao.php';

// Função para buscar representações de um comitê
function buscarRepresentacoes($conn, $id_comite)
{
    $representacoes = array();
    if ($id_comite) {
        $sql = "SELECT 
                r.id_representacao,
                r.nome_representacao
            FROM representacao r
            WHERE r.id_comite = ?
            AND r.cpf_delegado IS NULL
            ORDER BY r.nome_representacao";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_comite);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $representacoes[] = $row;
        }
        $stmt->close();
    }
    return $representacoes;
}

// PROCESSAR FORMULÁRIO DE INSCRIÇÃO
$mensagem = "";
$erro = "";
$dados_preenchidos = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $carregar_rep = isset($_POST['carregar_representacoes']);

    if (!$carregar_rep) {
        // Coletar dados do formulário
        // CORREÇÃO: Usar -1 para "nenhuma delegação"
        $id_delegacao = isset($_POST['id_delegacao']) && $_POST['id_delegacao'] !== '' ? intval($_POST['id_delegacao']) : -1;
        
        $comite_desejado = isset($_POST['comite_desejado']) ? intval($_POST['comite_desejado']) : 0;
        $segunda_opcao_comite = isset($_POST['segunda_opcao_comite']) && $_POST['segunda_opcao_comite'] !== '' ? intval($_POST['segunda_opcao_comite']) : null;
        $terceira_opcao_comite = isset($_POST['terceira_opcao_comite']) && $_POST['terceira_opcao_comite'] !== '' ? intval($_POST['terceira_opcao_comite']) : null;
        $representacao_desejada = isset($_POST['representacao_desejada']) ? intval($_POST['representacao_desejada']) : 0;
        $segunda_opcao_representacao = isset($_POST['segunda_opcao_representacao']) && $_POST['segunda_opcao_representacao'] !== '' ? intval($_POST['segunda_opcao_representacao']) : null;
        $terceira_opcao_representacao = isset($_POST['terceira_opcao_representacao']) && $_POST['terceira_opcao_representacao'] !== '' ? intval($_POST['terceira_opcao_representacao']) : null;
        $justificativa = isset($_POST['justificativa']) ? trim($_POST['justificativa']) : '';

        $dados_preenchidos = [
            'id_delegacao' => $id_delegacao,
            'comite_desejado' => $comite_desejado,
            'segunda_opcao_comite' => $segunda_opcao_comite,
            'terceira_opcao_comite' => $terceira_opcao_comite,
            'representacao_desejada' => $representacao_desejada,
            'segunda_opcao_representacao' => $segunda_opcao_representacao,
            'terceira_opcao_representacao' => $terceira_opcao_representacao,
            'justificativa' => $justificativa
        ];

        // Validações
        if ($comite_desejado <= 0) {
            $erro = "Selecione um comitê desejado.";
        } elseif ($representacao_desejada <= 0) {
            $erro = "Selecione uma representação.";
        } elseif (empty($justificativa)) {
            $erro = "A justificativa é obrigatória.";
        } elseif (strlen($justificativa) < 20) {
            $erro = "A justificativa deve ter pelo menos 20 caracteres.";
        } else {
            // Verificar se já está inscrito
            $sql_verifica = "SELECT * FROM delegado WHERE cpf = ?";
            $stmt_verifica = $conn->prepare($sql_verifica);
            $stmt_verifica->bind_param("s", $_SESSION['cpf']);
            $stmt_verifica->execute();
            $result_verifica = $stmt_verifica->get_result();

            if ($result_verifica->num_rows > 0) {
                $erro = "Você já está inscrito como delegado!";
                $stmt_verifica->close();
            } else {
                $stmt_verifica->close();

                // Verificar se a representação está disponível
                $sql_verifica_rep = "SELECT * FROM representacao WHERE id_representacao = ? AND cpf_delegado IS NULL";
                $stmt_verifica_rep = $conn->prepare($sql_verifica_rep);
                $stmt_verifica_rep->bind_param("i", $representacao_desejada);
                $stmt_verifica_rep->execute();
                $result_verifica_rep = $stmt_verifica_rep->get_result();

                if ($result_verifica_rep->num_rows == 0) {
                    $erro = "Esta representação não está mais disponível. Por favor, selecione outra.";
                    $stmt_verifica_rep->close();
                } else {
                    $stmt_verifica_rep->close();

                    // CORREÇÃO: Verificar se a delegação existe (apenas se for diferente de -1)
                    if ($id_delegacao != -1) {
                        $sql_verifica_delegacao = "SELECT id_delegacao FROM delegacao WHERE id_delegacao = ? AND verificacao_delegacao = 'aprovado'";
                        $stmt_verifica_delegacao = $conn->prepare($sql_verifica_delegacao);
                        $stmt_verifica_delegacao->bind_param("i", $id_delegacao);
                        $stmt_verifica_delegacao->execute();
                        $result_verifica_delegacao = $stmt_verifica_delegacao->get_result();
                        
                        if ($result_verifica_delegacao->num_rows == 0) {
                            $erro = "A delegação selecionada não existe ou não está aprovada.";
                            $stmt_verifica_delegacao->close();
                            $id_delegacao = -1; // Reverter para -1 (sem delegação)
                            $dados_preenchidos['id_delegacao'] = -1;
                        } else {
                            $stmt_verifica_delegacao->close();
                        }
                    }

                    if (empty($erro)) {
                        // CORREÇÃO: Definir valor de aprovado_delegacao com base na delegação
                        $aprovado_delegacao = ($id_delegacao == -1) ? null : 'pendente';
                        
                        // Usar uma única query para ambos os casos
                        if ($id_delegacao == -1) {
                            // Quando NÃO há delegação (-1)
                            $sql_inserir = "INSERT INTO delegado (cpf, id_delegacao, aprovado_delegacao, id_comite, representacao, comite_desejado, 
                                                                    primeira_op_representacao, segunda_op_representacao, 
                                                                    terceira_op_representacao, segunda_op_comite, terceira_op_comite) 
                                             VALUES (?, -1, NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
                        } else {
                            // Quando HÁ delegação (id válido)
                            $sql_inserir = "INSERT INTO delegado (cpf, id_delegacao, aprovado_delegacao, id_comite, representacao, comite_desejado, 
                                                                    primeira_op_representacao, segunda_op_representacao, 
                                                                    terceira_op_representacao, segunda_op_comite, terceira_op_comite) 
                                             VALUES (?, ?, 'pendente', ?, ?, ?, ?, ?, ?, ?, ?)";
                        }
                        
                        $stmt_inserir = $conn->prepare($sql_inserir);
                        
                        if ($stmt_inserir === false) {
                            $erro = "Erro ao preparar consulta: " . $conn->error;
                        } else {
                            if ($id_delegacao == -1) {
                                // Bind para quando não há delegação
                                $stmt_inserir->bind_param(
                                    "siiiiiiii",
                                    $_SESSION['cpf'],
                                    $comite_desejado,
                                    $representacao_desejada,
                                    $comite_desejado,
                                    $representacao_desejada,
                                    $segunda_opcao_representacao,
                                    $terceira_opcao_representacao,
                                    $segunda_opcao_comite,
                                    $terceira_opcao_comite
                                );
                            } else {
                                // Bind para quando há delegação
                                $stmt_inserir->bind_param(
                                    "siiiisiiii",
                                    $_SESSION['cpf'],
                                    $id_delegacao,
                                    $comite_desejado,
                                    $representacao_desejada,
                                    $comite_desejado,
                                    $representacao_desejada,
                                    $segunda_opcao_representacao,
                                    $terceira_opcao_representacao,
                                    $segunda_opcao_comite,
                                    $terceira_opcao_comite
                                );
                            }
                            
                            if ($stmt_inserir->execute()) {
                                // Atualizar a representação
                                $sql_atualizar_rep = "UPDATE representacao SET cpf_delegado = ? WHERE id_representacao = ?";
                                $stmt_atualizar_rep = $conn->prepare($sql_atualizar_rep);
                                
                                if ($stmt_atualizar_rep === false) {
                                    $erro = "Erro ao preparar atualização: " . $conn->error;
                                } else {
                                    $stmt_atualizar_rep->bind_param("si", $_SESSION['cpf'], $representacao_desejada);
                                    
                                    if ($stmt_atualizar_rep->execute()) {
                                        $mensagem = "Inscrição realizada com sucesso!";
                                        $dados_preenchidos = [];
                                    } else {
                                        $erro = "Erro ao atualizar representação: " . $stmt_atualizar_rep->error;
                                    }
                                    $stmt_atualizar_rep->close();
                                }
                            } else {
                                $erro = "Erro ao realizar inscrição: " . $stmt_inserir->error;
                            }
                            $stmt_inserir->close();
                        }
                    }
                }
            }
        }
    }
}

// Buscar dados para o formulário
if (isset($_SESSION["cpf"])) {
    $comites = array();
    $delegacoes = array();

    if ($conn && $conn->connect_error === null) {
        // Buscar comitês APROVADOS
        $sql_comites = "
            SELECT 
                c.id_comite,
                c.nome_comite,
                c.tipo_comite,
                c.status
            FROM comite c
            INNER JOIN unif uf ON c.id_unif = uf.id_unif
            WHERE uf.id_unif = (
                SELECT id_unif 
                FROM unif 
                ORDER BY data_inicio_unif DESC 
                LIMIT 1
            )
            AND c.status = 'aprovado'
            ORDER BY c.nome_comite";

        $result_comites = $conn->query($sql_comites);

        if ($result_comites && $result_comites->num_rows > 0) {
            while ($row = $result_comites->fetch_assoc()) {
                $comites[] = $row;
            }
        }

        // Buscar delegações disponíveis
        $sql_delegacoes = "
            SELECT DISTINCT d.id_delegacao, d.nome as nome_delegacao, u.nome as responsavel
            FROM delegacao d
            INNER JOIN unif uf ON d.id_unif = uf.id_unif
            INNER JOIN usuario u ON d.cpf = u.cpf
            WHERE uf.id_unif = (
                SELECT id_unif 
                FROM unif 
                ORDER BY data_inicio_unif DESC 
                LIMIT 1
            )
            AND d.verificacao_delegacao = 'aprovado'
            ORDER BY d.nome";

        $result_delegacoes = $conn->query($sql_delegacoes);

        if ($result_delegacoes && $result_delegacoes->num_rows > 0) {
            while ($row = $result_delegacoes->fetch_assoc()) {
                $delegacoes[] = $row;
            }
        }

        // Verificar comitê selecionado
        $comite_selecionado = isset($_POST['comite_desejado']) ? $_POST['comite_desejado'] : (isset($dados_preenchidos['comite_desejado']) ? $dados_preenchidos['comite_desejado'] : '');
        $representacoes_comite = array();

        if ($comite_selecionado) {
            $representacoes_comite = buscarRepresentacoes($conn, $comite_selecionado);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição - Comitê UNIF</title>
    <link rel="stylesheet" href="styles/global.css">
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary:rgb(16, 148, 56);
            --primary-dark:rgb(15, 122, 20);
            --secondary:rgb(0, 153, 33);
            --success:rgb(27, 173, 63);
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #000, #0f5132, #2ecc71, #000);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-top: 20px;
        }

        .header h1 {
            color: var(--dark);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: var(--gray);
            font-size: 1.1rem;
        }

        .tabs {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
        }

        .tab-btn {
            padding: 14px 32px;
            background: white;
            border: 2px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tab-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .form-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
        }

        .form-section h3 {
            color: var(--dark);
            font-size: 1.3rem;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .required::after {
            content: " *";
            color: #e63946;
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-control[readonly] {
            background-color: var(--light);
            cursor: not-allowed;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 45px;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .info-text {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            font-size: 0.85rem;
            margin-top: 8px;
        }

        .info-text i {
            color: var(--primary);
        }

        .representations-grid {
            display: grid;
            gap: 20px;
        }

        .representation-card {
            background: var(--light);
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid var(--primary);
        }

        .representation-card h4 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .representation-card h4 i {
            color: var(--secondary);
        }

        .submit-section {
            text-align: center;
            margin-top: 40px;
        }

        .submit-btn {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 16px 45px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        .logo-container {
            text-align: center;
            margin-top: 40px;
        }

        .logo {
            max-width: 150px;
            height: auto;
            opacity: 0.8;
            transition: var(--transition);
        }

        .logo:hover {
            opacity: 1;
            transform: scale(1.05);
        }

        .message-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            border-radius: var(--border-radius);
            text-align: center;
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

        .message-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #155724;
            color: #155724;
        }

        .message-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border: 2px solid #721c24;
            color: #721c24;
        }

        .message-container i {
            font-size: 3rem;
            margin-bottom: 20px;
        }

        .message-container h3 {
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .message-container p {
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.3);
            transition: var(--transition);
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateX(-5px);
        }

        .not-authenticated {
            text-align: center;
            padding: 100px 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .not-authenticated h2 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 2rem;
        }

        .not-authenticated p {
            color: var(--gray);
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .auth-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--primary);
            color: white;
            padding: 14px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .auth-link:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .delegation-info {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--primary);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .form-card {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .tabs {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if (isset($_SESSION["cpf"])): ?>
            
            <?php if ($erro): ?>
                <div class="message-container message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Erro na Inscrição</h3>
                    <p><?php echo htmlspecialchars($erro); ?></p>
                    <a href="entraComite.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Voltar e corrigir
                    </a>
                </div>
                
            <?php elseif ($mensagem): ?>
                <div class="message-container message-success">
                    <i class="fas fa-check-circle"></i>
                    <h3>Inscrição Enviada!</h3>
                    <p><?php echo htmlspecialchars($mensagem); ?></p>
                    <?php if (isset($id_delegacao) && $id_delegacao != -1): ?>
                        <div class="info-text">
                            <i class="fas fa-check"></i>
                            Inscrito como parte de uma delegação
                        </div>
                    <?php else: ?>
                        <div class="info-text">
                            <i class="fas fa-user"></i>
                            Inscrição individual realizada
                        </div>
                    <?php endif; ?>
                    <a href="inicio.php" class="back-link">
                        <i class="fas fa-home"></i> Ir para início
                    </a>
                </div>
                
            <?php else: ?>
                <div class="header">
                    <h1><i class="fas fa-users"></i> Inscrição em Comitê</h1>
                    <p>Preencha o formulário abaixo para participar da UNIF</p>
                </div>

                <div class="tabs">
                    <button class="tab-btn" onclick="window.location.href='criarDelegacao.php'">
                        <i class="fas fa-plus-circle"></i> Criar Delegação
                    </button>
                    <button class="tab-btn active">
                        <i class="fas fa-user-graduate"></i> Fazer Inscrição
                    </button>
                </div>

                <form class="form-card" id="formInscricao" method="POST" action="">
                    <div class="form-grid">
                        <!-- Coluna Esquerda - Delegação -->
                        <div class="form-section">
                            <h3><i class="fas fa-school"></i> Associação com Delegação</h3>
                            
                            <div class="form-group">
                                <label class="form-label">Delegação (Opcional)</label>
                                <select name="id_delegacao" id="id_delegacao" class="form-control">
                                    <option value="-1">Nenhuma delegação (inscrição individual)</option>
                                    <?php if (!empty($delegacoes)): ?>
                                        <?php foreach ($delegacoes as $delegacao): ?>
                                            <option value="<?php echo $delegacao['id_delegacao']; ?>"
                                                <?php echo (isset($dados_preenchidos['id_delegacao']) && $dados_preenchidos['id_delegacao'] == $delegacao['id_delegacao']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($delegacao['nome_delegacao']); ?>
                                                <span class="badge badge-info">Delegação</span>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                
                                <?php if (isset($dados_preenchidos['id_delegacao']) && $dados_preenchidos['id_delegacao'] != -1): 
                                    $delegacao_selecionada = array_filter($delegacoes, function ($d) use ($dados_preenchidos) {
                                        return $d['id_delegacao'] == $dados_preenchidos['id_delegacao'];
                                    });
                                    $delegacao_selecionada = reset($delegacao_selecionada);
                                ?>
                                    <div class="delegation-info">
                                        <div class="info-text">
                                            <i class="fas fa-user-tie"></i>
                                            <strong>Responsável:</strong> <?php echo htmlspecialchars($delegacao_selecionada['responsavel']); ?>
                                        </div>
                                        <div class="info-text">
                                            <i class="fas fa-check-circle"></i>
                                            Delegação aprovada
                                        </div>
                                    </div>
                                <?php elseif (isset($dados_preenchidos['id_delegacao']) && $dados_preenchidos['id_delegacao'] == -1): ?>
                                    <div class="delegation-info">
                                        <div class="info-text">
                                            <i class="fas fa-user"></i>
                                            <strong>Inscrição Individual:</strong> Você fará parte do comitê sem associação a uma delegação
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="info-text">
                                    <i class="fas fa-lightbulb"></i>
                                    Escolha uma delegação para se associar a uma escola/grupo, ou selecione "Nenhuma delegação" para inscrição individual
                                </div>
                            </div>
                            
                            <div class="info-text">
                                <i class="fas fa-plus-circle"></i>
                                <a href="criarDelegacao.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                    Criar nova delegação
                                </a>
                            </div>
                        </div>

                        <!-- Coluna Direita - Dados do Aluno -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-graduate"></i> Dados do Aluno</h3>
                            
                            <div class="form-group">
                                <label class="form-label required">CPF</label>
                                <input type="text" name="cpf" class="form-control" value="<?php echo htmlspecialchars($_SESSION['cpf']); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required">Comitê Desejado</label>
                                <select name="comite_desejado" id="comite_desejado" class="form-control" required onchange="this.form.submit()">
                                    <option value="">Selecione um comitê...</option>
                                    <?php foreach ($comites as $comite): ?>
                                        <option value="<?php echo $comite['id_comite']; ?>"
                                            <?php echo ($comite_selecionado == $comite['id_comite']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                            <span class="badge badge-success"><?php echo htmlspecialchars($comite['tipo_comite']); ?></span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Segunda Opção de Comitê (Opcional)</label>
                                <select name="segunda_opcao_comite" id="segunda_opcao_comite" class="form-control">
                                    <option value="">Selecione uma segunda opção...</option>
                                    <?php foreach ($comites as $comite): ?>
                                        <option value="<?php echo $comite['id_comite']; ?>"
                                            <?php echo (isset($dados_preenchidos['segunda_opcao_comite']) && $dados_preenchidos['segunda_opcao_comite'] == $comite['id_comite']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Terceira Opção de Comitê (Opcional)</label>
                                <select name="terceira_opcao_comite" id="terceira_opcao_comite" class="form-control">
                                    <option value="">Selecione uma terceira opção...</option>
                                    <?php foreach ($comites as $comite): ?>
                                        <option value="<?php echo $comite['id_comite']; ?>"
                                            <?php echo (isset($dados_preenchidos['terceira_opcao_comite']) && $dados_preenchidos['terceira_opcao_comite'] == $comite['id_comite']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="representations-grid">
                                <div class="representation-card">
                                    <h4><i class="fas fa-flag"></i> Representações Disponíveis</h4>
                                    
                                    <?php if ($comite_selecionado): ?>
                                        <?php if (!empty($representacoes_comite)): ?>
                                            <div class="info-text">
                                                <i class="fas fa-check-circle"></i>
                                                <?php echo count($representacoes_comite); ?> representação(ões) disponível(is)
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label required">Primeira Opção</label>
                                                <select name="representacao_desejada" id="representacao_desejada" class="form-control" required>
                                                    <option value="">Selecione uma representação...</option>
                                                    <?php foreach ($representacoes_comite as $rep): ?>
                                                        <option value="<?php echo $rep['id_representacao']; ?>"
                                                            <?php echo (isset($dados_preenchidos['representacao_desejada']) && $dados_preenchidos['representacao_desejada'] == $rep['id_representacao']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Segunda Opção (Opcional)</label>
                                                <select name="segunda_opcao_representacao" id="segunda_opcao_representacao" class="form-control">
                                                    <option value="">Selecione uma segunda opção...</option>
                                                    <?php foreach ($representacoes_comite as $rep): ?>
                                                        <option value="<?php echo $rep['id_representacao']; ?>"
                                                            <?php echo (isset($dados_preenchidos['segunda_opcao_representacao']) && $dados_preenchidos['segunda_opcao_representacao'] == $rep['id_representacao']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Terceira Opção (Opcional)</label>
                                                <select name="terceira_opcao_representacao" id="terceira_opcao_representacao" class="form-control">
                                                    <option value="">Selecione uma terceira opção...</option>
                                                    <?php foreach ($representacoes_comite as $rep): ?>
                                                        <option value="<?php echo $rep['id_representacao']; ?>"
                                                            <?php echo (isset($dados_preenchidos['terceira_opcao_representacao']) && $dados_preenchidos['terceira_opcao_representacao'] == $rep['id_representacao']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php else: ?>
                                            <div class="info-text">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Não há representações disponíveis para este comitê no momento
                                            </div>
                                            <input type="hidden" name="representacao_desejada" value="0">
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="info-text">
                                            <i class="fas fa-info-circle"></i>
                                            Selecione um comitê primeiro para ver as representações disponíveis
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required">Justificativa da Escolha</label>
                                <textarea name="justificativa" class="form-control" placeholder="Explique por que escolheu esta representação (mínimo 20 caracteres)..."><?php echo isset($dados_preenchidos['justificativa']) ? htmlspecialchars($dados_preenchidos['justificativa']) : ''; ?></textarea>
                                <div class="info-text">
                                    <i class="fas fa-edit"></i>
                                    Sua justificativa será avaliada pela equipe organizadora
                                </div>
                            </div>
                            
                            <input type="hidden" name="carregar_representacoes" value="1">
                        </div>
                    </div>
                    
                    <div class="submit-section">
                        <button type="button" class="submit-btn" onclick="enviarFormulario()">
                            <i class="fas fa-paper-plane"></i> SUBMETER INSCRIÇÃO
                        </button>
                        <div class="info-text" style="margin-top: 15px;">
                            <i class="fas fa-shield-alt"></i>
                            Seus dados estão seguros e serão usados apenas para fins de inscrição
                        </div>
                    </div>
                </form>
                
                <div class="logo-container">
                    <img src="images/unif.png" alt="Logo UNIF" class="logo">
                </div>
                
            <?php endif; ?>
            
        <?php else: ?>
            <div class="not-authenticated">
                <h2>Acesso Restrito</h2>
                <p>Você precisa estar autenticado para realizar a inscrição.</p>
                <a href="login.html" class="auth-link">
                    <i class="fas fa-sign-in-alt"></i> Fazer Login
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function enviarFormulario() {
            console.log('Validando formulário...');
            
            // Remove campo hidden antes de enviar
            const hiddenField = document.querySelector('input[name="carregar_representacoes"]');
            if (hiddenField) {
                hiddenField.remove();
            }
            
            // Validações
            const comiteDesejado = document.getElementById('comite_desejado').value;
            const representacao = document.getElementById('representacao_desejada') ? document.getElementById('representacao_desejada').value : '';
            const segundaRepresentacao = document.getElementById('segunda_opcao_representacao') ? document.getElementById('segunda_opcao_representacao').value : '';
            const terceiraRepresentacao = document.getElementById('terceira_opcao_representacao') ? document.getElementById('terceira_opcao_representacao').value : '';
            const justificativa = document.querySelector('textarea[name="justificativa"]').value.trim();
            
            let erros = [];
            
            if (!comiteDesejado) {
                erros.push("Selecione um comitê desejado!");
                document.getElementById('comite_desejado').style.borderColor = '#e63946';
            }
            
            if (!representacao || representacao === "") {
                erros.push("Selecione uma representação como primeira opção!");
                if (document.getElementById('representacao_desejada')) {
                    document.getElementById('representacao_desejada').style.borderColor = '#e63946';
                }
            }
            
            // Validar opções diferentes
            if (representacao && segundaRepresentacao && representacao === segundaRepresentacao) {
                erros.push("A primeira e segunda opções de representação não podem ser iguais!");
            }
            
            if (representacao && terceiraRepresentacao && representacao === terceiraRepresentacao) {
                erros.push("A primeira e terceira opções de representação não podem ser iguais!");
            }
            
            if (segundaRepresentacao && terceiraRepresentacao && segundaRepresentacao === terceiraRepresentacao) {
                erros.push("A segunda e terceira opções de representação não podem ser iguais!");
            }
            
            if (!justificativa || justificativa.length < 20) {
                erros.push("A justificativa deve ter pelo menos 20 caracteres!");
                document.querySelector('textarea[name="justificativa"]').style.borderColor = '#e63946';
            }
            
            if (erros.length > 0) {
                alert("Por favor, corrija os seguintes erros:\n\n" + erros.join('\n'));
                return false;
            }
            
            const delegaçãoSelect = document.getElementById('id_delegacao');
            const delegaçãoValor = delegaçãoSelect ? delegaçãoSelect.value : '-1';
            const delegaçãoTexto = delegaçãoSelect ? delegaçãoSelect.options[delegaçãoSelect.selectedIndex].text : 'Nenhuma delegação';
            
            let confirmMessage = 'Deseja enviar sua inscrição?\n\n';
            confirmMessage += 'Comitê: ' + document.getElementById('comite_desejado').options[document.getElementById('comite_desejado').selectedIndex].text + '\n';
            confirmMessage += 'Delegação: ' + delegaçãoTexto + '\n';
            confirmMessage += '\nApós o envio, não será possível alterar os dados.';
            
            if (confirm(confirmMessage)) {
                console.log('Enviando formulário...');
                document.getElementById('formInscricao').submit();
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Evento para carregar representações ao mudar comitê
            const comiteSelect = document.getElementById('comite_desejado');
            if (comiteSelect) {
                comiteSelect.addEventListener('change', function() {
                    console.log('Carregando representações para o comitê selecionado...');
                    const existingField = document.querySelector('input[name="carregar_representacoes"]');
                    if (existingField) {
                        existingField.value = '1';
                    } else {
                        const newField = document.createElement('input');
                        newField.type = 'hidden';
                        newField.name = 'carregar_representacoes';
                        newField.value = '1';
                        document.getElementById('formInscricao').appendChild(newField);
                    }
                    
                    setTimeout(function() {
                        document.getElementById('formInscricao').submit();
                    }, 300);
                });
            }
            
            // Validação em tempo real para representações
            const primeiraRep = document.getElementById('representacao_desejada');
            const segundaRep = document.getElementById('segunda_opcao_representacao');
            const terceiraRep = document.getElementById('terceira_opcao_representacao');
            
            function validarRepresentacoes() {
                if (primeiraRep && segundaRep && primeiraRep.value && segundaRep.value && primeiraRep.value === segundaRep.value) {
                    segundaRep.style.borderColor = '#e63946';
                } else if (segundaRep) {
                    segundaRep.style.borderColor = '';
                }
                
                if (primeiraRep && terceiraRep && primeiraRep.value && terceiraRep.value && primeiraRep.value === terceiraRep.value) {
                    terceiraRep.style.borderColor = '#e63946';
                } else if (terceiraRep) {
                    terceiraRep.style.borderColor = '';
                }
                
                if (segundaRep && terceiraRep && segundaRep.value && terceiraRep.value && segundaRep.value === terceiraRep.value) {
                    terceiraRep.style.borderColor = '#e63946';
                } else if (terceiraRep) {
                    terceiraRep.style.borderColor = '';
                }
            }
            
            if (primeiraRep) primeiraRep.addEventListener('change', validarRepresentacoes);
            if (segundaRep) segundaRep.addEventListener('change', validarRepresentacoes);
            if (terceiraRep) terceiraRep.addEventListener('change', validarRepresentacoes);
            
            // Remover bordas vermelhas ao digitar
            const inputs = document.querySelectorAll('select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.style.borderColor = '';
                });
            });
            
            // Aplicar estilo ao carregar a página
            const delegaçãoSelect = document.getElementById('id_delegacao');
            if (delegaçãoSelect && delegaçãoSelect.value == '-1') {
                delegaçãoSelect.style.borderLeft = '4px solid #4cc9f0';
            }
            
            // Mudar estilo da delegação baseado na seleção
            if (delegaçãoSelect) {
                delegaçãoSelect.addEventListener('change', function() {
                    if (this.value == '-1') {
                        this.style.borderLeft = '4px solid #4cc9f0';
                    } else {
                        this.style.borderLeft = '4px solid #4361ee';
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// Fechar conexão se existir
if ($conn && $conn->connect_error === null) {
    $conn->close();
}
?>