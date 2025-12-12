<?php
session_start();
require_once 'php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_unif'])) {
    if ($conn && $conn->connect_error === null) {
        $data_inicio_unif = $_POST['data_inicio_unif'];
        $data_fim_unif = $_POST['data_fim_unif'];
        $data_inicio_inscricao_delegado = $_POST['data_inicio_inscricao_delegado'];
        $data_fim_inscricao_delegado = $_POST['data_fim_inscricao_delegado'];
        $data_inicio_inscricao_comite = $_POST['data_inicio_inscricao_comite'];
        $data_fim_inscricao_comite = $_POST['data_fim_inscricao_comite'];
        $data_inicio_inscricao_staff = $_POST['data_inicio_inscricao_staff'];
        $data_fim_inscricao_staff = $_POST['data_fim_inscricao_staff'];
        
        $sql_inserir_unif = "INSERT INTO unif (
            data_inicio_unif, data_fim_unif,
            data_inicio_inscricao_delegado, data_fim_inscricao_delegado,
            data_inicio_inscricao_comite, data_fim_inscricao_comite,
            data_inicio_inscricao_staff, data_fim_inscricao_staff
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_inserir_unif);
        $stmt->bind_param(
            "ssssssss",
            $data_inicio_unif,
            $data_fim_unif,
            $data_inicio_inscricao_delegado,
            $data_fim_inscricao_delegado,
            $data_inicio_inscricao_comite,
            $data_fim_inscricao_comite,
            $data_inicio_inscricao_staff,
            $data_fim_inscricao_staff
        );
        
        if ($stmt->execute()) {
            $id_nova_unif = $conn->insert_id;
            
            $funcoes_secretarios = [
                'Geral' => $_POST['cpf_geral'] ?? '',
                'Academico' => $_POST['cpf_academico'] ?? '',
                'Relacoes Publicas' => $_POST['cpf_relacoes_publicas'] ?? '',
                'Marketing' => $_POST['cpf_marketing'] ?? '',
                'Financas' => $_POST['cpf_financas'] ?? '',
                'Logistica' => $_POST['cpf_logistica'] ?? '',
                'Administrativo' => $_POST['cpf_administrativo'] ?? ''
            ];
            
            $sql_inserir_secretario = "INSERT INTO secretario (cpf, funcao, id_unif) VALUES (?, ?, ?)";
            $stmt_secretario = $conn->prepare($sql_inserir_secretario);
            
            foreach ($funcoes_secretarios as $funcao => $cpf) {
                if (!empty($cpf)) {
                    $stmt_secretario->bind_param("ssi", $cpf, $funcao, $id_nova_unif);
                    $stmt_secretario->execute();
                }
            }
            
            $stmt_secretario->close();
            $mensagem_sucesso = "UNIF criada com sucesso!";
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $mensagem_erro = "Erro ao criar UNIF: " . $conn->error;
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle - UNIF</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #000, #0f5132, #2ecc71, #000);
            min-height: 100vh;
            padding: 20px;
            color: var(--dark);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            min-height: calc(100vh - 40px);
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), #1a252f);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .main-content {
            padding: 30px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Cards de Configuração */
        .config-section {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .config-section h2 {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: var(--dark);
            font-weight: 600;
        }
        
        input[type="date"], input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input[type="date"]:focus, input[type="text"]:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        input[readonly] {
            background-color: #f5f5f5;
            color: var(--gray);
            cursor: not-allowed;
        }
        
        /* Cards de Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--secondary);
            margin: 10px 0;
        }
        
        .stat-card .label {
            color: var(--gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card.inscritos { border-top: 4px solid var(--success); }
        .stat-card.staffs { border-top: 4px solid var(--warning); }
        .stat-card.mesas { border-top: 4px solid var(--danger); }
        
        /* Painel de Ações */
        .actions-panel {
            display: grid;
            gap: 20px;
        }
        
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .action-card h3 {
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-buttons {
            display: grid;
            gap: 12px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: var(--light);
            border: none;
            border-radius: 10px;
            color: var(--dark);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .action-btn:hover {
            background: var(--secondary);
            color: white;
            transform: translateX(5px);
        }
        
        .action-btn i {
            font-size: 1.2rem;
        }
        
        .action-btn.comite { border-left: 4px solid var(--success); }
        .action-btn.staff { border-left: 4px solid var(--warning); }
        .action-btn.professor { border-left: 4px solid var(--danger); }
        .action-btn.delegado { border-left: 4px solid var(--secondary); }
        .action-btn.pagamento { border-left: 4px solid #9b59b6; }
        .action-btn.delegacao { border-left: 4px solid #1abc9c; }
        
        /* Botões principais */
        .primary-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #27ae60, #219653);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
        }
        
        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 2px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        
        .btn-disabled {
            background: #e0e0e0;
            color: #999;
            cursor: not-allowed;
        }
        
        /* Mensagens */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background: #e3f2fd;
            color: #1565c0;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Tabela de Secretários */
        .secretarios-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .secretarios-table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            color: var(--dark);
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .secretarios-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .secretarios-table tr:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php
    if (isset($_SESSION["cpf"])) {
        if (isset($_SESSION["adm"]) && $_SESSION["adm"] == true) {
            
            $unif_atual = null;
            $estatisticas = array(
                'inscritos' => 0,
                'staffs' => 0,
                'mesas' => 0
            );
            $pode_criar_unif = false;
            $secretarios = array();
            
            if ($conn && $conn->connect_error === null) {
                $sql_unif = "SELECT * FROM unif ORDER BY data_fim_unif DESC LIMIT 1";
                $result_unif = $conn->query($sql_unif);
                
                if ($result_unif && $result_unif->num_rows > 0) {
                    $unif_atual = $result_unif->fetch_assoc();
                    
                    $data_fim = new DateTime($unif_atual['data_fim_unif']);
                    $data_atual = new DateTime();
                    $pode_criar_unif = ($data_fim < $data_atual);
                    
                    $id_unif = $unif_atual['id_unif'];
                    
                    // Delegados inscritos
                    $sql_inscritos = "SELECT COUNT(*) as total FROM delegado WHERE id_comite IS NOT NULL";
                    $result_inscritos = $conn->query($sql_inscritos);
                    if ($result_inscritos && $row = $result_inscritos->fetch_assoc()) {
                        $estatisticas['inscritos'] = $row['total'];
                    }
                    
                    // Staffs aprovados
                    $sql_staffs = "SELECT COUNT(*) as total FROM staff WHERE status_inscricao = 'aprovado'";
                    $result_staffs = $conn->query($sql_staffs);
                    if ($result_staffs && $row = $result_staffs->fetch_assoc()) {
                        $estatisticas['staffs'] = $row['total'];
                    }
                    
                    // Comitês aprovados
                    $sql_mesas = "SELECT COUNT(*) as total FROM comite WHERE status = 'aprovado'";
                    $result_mesas = $conn->query($sql_mesas);
                    if ($result_mesas && $row = $result_mesas->fetch_assoc()) {
                        $estatisticas['mesas'] = $row['total'];
                    }
                    
                    // Buscar secretários
                    $sql_secretarios = "SELECT s.funcao, u.cpf, u.nome 
                                       FROM secretario s 
                                       INNER JOIN usuario u ON s.cpf = u.cpf 
                                       WHERE s.id_unif = ?";
                    $stmt = $conn->prepare($sql_secretarios);
                    $stmt->bind_param("i", $id_unif);
                    $stmt->execute();
                    $result_secretarios = $stmt->get_result();
                    
                    if ($result_secretarios && $result_secretarios->num_rows > 0) {
                        while($row = $result_secretarios->fetch_assoc()) {
                            $secretarios[$row['funcao']] = $row;
                        }
                    }
                } else {
                    $pode_criar_unif = true;
                }
            }
    ?>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-cogs"></i> Painel de Controle UNIF</h1>
            <p>Gerencie todas as configurações e análises do sistema</p>
        </div>
        
        <div class="main-content">
            <!-- Coluna Esquerda: Configurações e Secretários -->
            <div>
                <?php if (isset($mensagem_sucesso)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($mensagem_erro)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Configurações da UNIF -->
                <div class="config-section">
                    <h2><i class="fas fa-calendar-alt"></i> Configurações da UNIF <?php echo $unif_atual ? "(Edição #" . $unif_atual['id_unif'] . ")" : ""; ?></h2>
                    
                    <form method="POST" id="formUnif">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inicio_unif">Data Início UNIF</label>
                                <input type="date" id="data_inicio_unif" name="data_inicio_unif" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_inicio_unif'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="data_fim_unif">Data Fim UNIF</label>
                                <input type="date" id="data_fim_unif" name="data_fim_unif" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_fim_unif'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inicio_inscricao_delegado">Início Inscrição Delegados</label>
                                <input type="date" id="data_inicio_inscricao_delegado" name="data_inicio_inscricao_delegado" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_inicio_inscricao_delegado'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="data_fim_inscricao_delegado">Fim Inscrição Delegados</label>
                                <input type="date" id="data_fim_inscricao_delegado" name="data_fim_inscricao_delegado" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_fim_inscricao_delegado'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inicio_inscricao_comite">Início Inscrição Comitês</label>
                                <input type="date" id="data_inicio_inscricao_comite" name="data_inicio_inscricao_comite" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_inicio_inscricao_comite'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="data_fim_inscricao_comite">Fim Inscrição Comitês</label>
                                <input type="date" id="data_fim_inscricao_comite" name="data_fim_inscricao_comite" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_fim_inscricao_comite'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inicio_inscricao_staff">Início Inscrição Staff</label>
                                <input type="date" id="data_inicio_inscricao_staff" name="data_inicio_inscricao_staff" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_inicio_inscricao_staff'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="data_fim_inscricao_staff">Fim Inscrição Staff</label>
                                <input type="date" id="data_fim_inscricao_staff" name="data_fim_inscricao_staff" 
                                       value="<?php echo $unif_atual ? $unif_atual['data_fim_inscricao_staff'] : ''; ?>" 
                                       <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Secretários -->
                <div class="config-section" style="margin-top: 30px;">
                    <h2><i class="fas fa-users-cog"></i> Secretários da UNIF</h2>
                    
                    <table class="secretarios-table">
                        <thead>
                            <tr>
                                <th>Função</th>
                                <th>Nome</th>
                                <th>CPF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $funcoes = ['Geral', 'Academico', 'Relacoes Publicas', 'Marketing', 'Financas', 'Logistica', 'Administrativo'];
                            foreach ($funcoes as $funcao):
                                $dados = isset($secretarios[$funcao]) ? $secretarios[$funcao] : null;
                            ?>
                            <tr>
                                <td><?php echo $funcao; ?></td>
                                <td><?php echo $dados ? $dados['nome'] : '<em style="color:#999">Não definido</em>'; ?></td>
                                <td>
                                    <?php if ($dados): ?>
                                        <?php echo $dados['cpf']; ?>
                                    <?php else: ?>
                                        <input type="text" name="cpf_<?php echo strtolower(str_replace(' ', '_', $funcao)); ?>" 
                                               value="" placeholder="Digite o CPF"
                                               <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif" style="width: 100%;">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Botão Criar UNIF -->
                <div class="primary-buttons">
                    <?php if (!$unif_atual || $pode_criar_unif): ?>
                        <button type="submit" name="criar_unif" value="1" class="btn btn-primary" form="formUnif">
                            <i class="fas fa-plus-circle"></i> Criar Nova UNIF
                        </button>
                    <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            <i class="fas fa-calendar-times"></i> UNIF Ativa
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (!$pode_criar_unif && $unif_atual): ?>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        Só será possível criar uma nova UNIF após o término da atual 
                        (<?php echo date('d/m/Y', strtotime($unif_atual['data_fim_unif'])); ?>)
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Coluna Direita: Estatísticas e Ações -->
            <div class="actions-panel">
                <!-- Estatísticas -->
                <div class="action-card">
                    <h3><i class="fas fa-chart-bar"></i> Estatísticas Atuais</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card inscritos">
                            <div class="number"><?php echo $estatisticas['inscritos']; ?></div>
                            <div class="label">Delegados Inscritos</div>
                        </div>
                        
                        <div class="stat-card staffs">
                            <div class="number"><?php echo $estatisticas['staffs']; ?></div>
                            <div class="label">Staffs Aprovados</div>
                        </div>
                        
                        <div class="stat-card mesas">
                            <div class="number"><?php echo $estatisticas['mesas']; ?></div>
                            <div class="label">Comitês Aprovados</div>
                        </div>
                    </div>
                </div>
                
                <!-- Análises -->
                <div class="action-card">
                    <h3><i class="fas fa-search"></i> Análises e Avaliações</h3>
                    
                    <div class="action-buttons">
                        <a href="analiseComites.php" class="action-btn comite">
                            <i class="fas fa-landmark"></i>
                            Avaliar Comitês
                        </a>
                        
                        <a href="analiseStaff.php" class="action-btn staff">
                            <i class="fas fa-user-tie"></i>
                            Analisar Staffs
                        </a>
                        
                        <a href="analiseProfessores.php" class="action-btn professor">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Gerenciar Professores
                        </a>
                        
                        <a href="analiseDelegados.php" class="action-btn delegado">
                            <i class="fas fa-users"></i>
                            Analisar Delegados
                        </a>
                        
                        <a href="avaliarPagamentos.php" class="action-btn pagamento">
                            <i class="fas fa-money-bill-wave"></i>
                            Avaliar Pagamentos
                        </a>
                        
                        <a href="analiseDelegacoes.php" class="action-btn delegacao">
                            <i class="fas fa-flag"></i>
                            Avaliar Delegações
                        </a>
                    </div>
                </div>
                
                <!-- Ações Rápidas -->
                <div class="action-card">
                    <h3><i class="fas fa-bolt"></i> Ações Rápidas</h3>
                    
                    <div class="action-buttons">
                        <a href="inicio.php" class="action-btn">
                            <i class="fas fa-home"></i>
                            Página Inicial
                        </a>
                        
                        <a href="relatorios.php" class="action-btn">
                            <i class="fas fa-file-alt"></i>
                            Gerar Relatórios
                        </a>
                        
                        <a href="logs.php" class="action-btn">
                            <i class="fas fa-history"></i>
                            Ver Logs do Sistema
                        </a>
                        
                        <button onclick="window.location.reload()" class="action-btn">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar Dados
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
        } else {
            echo "<div style='text-align: center; padding: 50px; color: white;'>
                    <h2><i class='fas fa-exclamation-triangle'></i> Acesso Restrito</h2>
                    <p>Você não tem permissão para acessar esta página.</p>
                    <a href='login.php' style='color: white; text-decoration: underline;'>Faça login como administrador</a>
                  </div>";
        }
    } else {
        echo "<div style='text-align: center; padding: 50px; color: white;'>
                <h2><i class='fas fa-lock'></i> Acesso Não Autorizado</h2>
                <p>Você precisa estar logado para acessar esta página.</p>
                <a href='login.php' style='color: white; text-decoration: underline;'>Faça login aqui</a>
              </div>";
    }
    
    if ($conn && $conn->connect_error === null) {
        $conn->close();
    }
    ?>
    
    <script>
        // Validação de datas
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formUnif');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const inicioUnif = document.getElementById('data_inicio_unif').value;
                    const fimUnif = document.getElementById('data_fim_unif').value;
                    
                    if (inicioUnif && fimUnif) {
                        const inicio = new Date(inicioUnif);
                        const fim = new Date(fimUnif);
                        
                        if (fim <= inicio) {
                            e.preventDefault();
                            alert('A data de fim da UNIF deve ser posterior à data de início.');
                            return false;
                        }
                    }
                    
                    // Validar secretários
                    const secretariosInputs = document.querySelectorAll('input[name^="cpf_"]');
                    let secretariosPreenchidos = 0;
                    
                    secretariosInputs.forEach(input => {
                        if (input.value.trim() !== '') {
                            secretariosPreenchidos++;
                        }
                    });
                    
                    if (secretariosPreenchidos === 0) {
                        if (!confirm('Nenhum secretário foi definido. Deseja continuar mesmo assim?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    return true;
                });
            }
            
            // Adicionar máscara de CPF nos campos de secretários
            const cpfInputs = document.querySelectorAll('input[name^="cpf_"]');
            cpfInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    
                    if (value.length > 9) {
                        value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
                    } else if (value.length > 6) {
                        value = value.replace(/^(\d{3})(\d{3})(\d{1,3}).*/, '$1.$2.$3');
                    } else if (value.length > 3) {
                        value = value.replace(/^(\d{3})(\d{1,3}).*/, '$1.$2');
                    }
                    
                    e.target.value = value;
                });
            });
        });
    </script>
</body>
</html>