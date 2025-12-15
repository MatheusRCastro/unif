<?php
session_start();
require_once 'php/conexao.php';

// Verificar se é admin ou secretário
if (!isset($_SESSION["cpf"]) || (!$_SESSION["adm"] && !$_SESSION["secretario"])) {
    header("Location: login.html");
    exit();
}

// Buscar dados
$comites = [];
$delegados_sem_representacao = [];
$estatisticas = [];
$current_unif_id = 1;

if ($conn && $conn->connect_error === null) {
    // Buscar o ID da UNIF atual
    $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
    $result_unif = $conn->query($sql_unif);
    if ($result_unif && $result_unif->num_rows > 0) {
        $unif_data = $result_unif->fetch_assoc();
        $current_unif_id = $unif_data['id_unif'];
    }
    
    // Buscar comitês aprovados da UNIF atual
    $sql_comites = "
        SELECT 
            c.id_comite,
            c.nome_comite,
            c.tipo_comite,
            c.num_delegados,
            COUNT(r.id_representacao) as total_representacoes,
            SUM(CASE WHEN r.cpf_delegado IS NOT NULL THEN 1 ELSE 0 END) as representacoes_preenchidas
        FROM comite c
        LEFT JOIN representacao r ON c.id_comite = r.id_comite
        WHERE c.id_unif = ?
        AND c.status = 'aprovado'
        GROUP BY c.id_comite
        ORDER BY c.nome_comite";
    
    $stmt_comites = $conn->prepare($sql_comites);
    $stmt_comites->bind_param("i", $current_unif_id);
    $stmt_comites->execute();
    $result_comites = $stmt_comites->get_result();
    
    if ($result_comites && $result_comites->num_rows > 0) {
        while ($row = $result_comites->fetch_assoc()) {
            $comites[$row['id_comite']] = $row;
            
            // Buscar representações deste comitê
            $sql_representacoes = "
                SELECT 
                    r.id_representacao,
                    r.nome_representacao,
                    r.cpf_delegado,
                    u.nome as nome_delegado,
                    u.instituicao
                FROM representacao r
                LEFT JOIN usuario u ON r.cpf_delegado = u.cpf
                WHERE r.id_comite = ?
                ORDER BY r.nome_representacao";
            
            $stmt_rep = $conn->prepare($sql_representacoes);
            $stmt_rep->bind_param("i", $row['id_comite']);
            $stmt_rep->execute();
            $result_rep = $stmt_rep->get_result();
            
            $representacoes = [];
            while ($rep = $result_rep->fetch_assoc()) {
                $representacoes[] = $rep;
            }
            $comites[$row['id_comite']]['representacoes'] = $representacoes;
            $stmt_rep->close();
        }
    }
    $stmt_comites->close();
    
    // Buscar delegados NÃO atribuídos ainda
    $sql_delegados_nao_atribuidos = "
        SELECT 
            d.cpf, 
            u.nome, 
            u.email, 
            u.telefone, 
            u.instituicao,
            u.restricao_alimentar,
            u.alergia,
            
            -- Comitê desejado
            d.comite_desejado as comite_op1_id,
            c2.nome_comite as comite_op1_nome,
            
            -- Segunda opção de comitê
            d.segunda_op_comite as comite_op2_id,
            c3.nome_comite as comite_op2_nome,
            
            -- Terceira opção de comitê
            d.terceira_op_comite as comite_op3_id,
            c4.nome_comite as comite_op3_nome,
            
            -- Representações (opções)
            d.primeira_op_representacao as rep_op1_id,
            r1.nome_representacao as rep_op1_nome,
            
            d.segunda_op_representacao as rep_op2_id,
            r2.nome_representacao as rep_op2_nome,
            
            d.terceira_op_representacao as rep_op3_id,
            r3.nome_representacao as rep_op3_nome
            
        FROM delegado d
        JOIN usuario u ON d.cpf = u.cpf
        LEFT JOIN comite c2 ON d.comite_desejado = c2.id_comite
        LEFT JOIN comite c3 ON d.segunda_op_comite = c3.id_comite
        LEFT JOIN comite c4 ON d.terceira_op_comite = c4.id_comite
        LEFT JOIN representacao r1 ON d.primeira_op_representacao = r1.id_representacao
        LEFT JOIN representacao r2 ON d.segunda_op_representacao = r2.id_representacao
        LEFT JOIN representacao r3 ON d.terceira_op_representacao = r3.id_representacao
        WHERE d.cpf NOT IN (SELECT cpf_delegado FROM representacao WHERE cpf_delegado IS NOT NULL)
        AND d.id_comite IS NOT NULL
        ORDER BY u.nome";
    
    $stmt_delegados = $conn->prepare($sql_delegados_nao_atribuidos);
    $stmt_delegados->execute();
    $result_delegados = $stmt_delegados->get_result();
    
    if ($result_delegados && $result_delegados->num_rows > 0) {
        while ($row = $result_delegados->fetch_assoc()) {
            $delegados_sem_representacao[] = $row;
        }
    }
    $stmt_delegados->close();
    
    // Estatísticas gerais
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT d.cpf) as total_delegados,
            COUNT(DISTINCT CASE WHEN r.cpf_delegado IS NOT NULL THEN d.cpf END) as delegados_atribuidos,
            COUNT(DISTINCT r.id_representacao) as total_representacoes,
            COUNT(DISTINCT CASE WHEN r.cpf_delegado IS NOT NULL THEN r.id_representacao END) as representacoes_preenchidas
        FROM delegado d
        LEFT JOIN representacao r ON d.cpf = r.cpf_delegado
        INNER JOIN comite c ON d.id_comite = c.id_comite
        WHERE c.id_unif = ?";
    
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("i", $current_unif_id);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    
    if ($result_stats && $result_stats->num_rows > 0) {
        $estatisticas = $result_stats->fetch_assoc();
    }
    $stmt_stats->close();
}

// Processar atribuição via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false];
    
    if ($_POST['action'] == 'atribuir_delegado') {
        $cpf = $_POST['cpf'] ?? '';
        $id_representacao = $_POST['id_representacao'] ?? 0;
        
        if ($cpf && $id_representacao) {
            // Remover de outras representações (se houver)
            $sql_remover = "UPDATE representacao SET cpf_delegado = NULL WHERE cpf_delegado = ?";
            $stmt = $conn->prepare($sql_remover);
            $stmt->bind_param("s", $cpf);
            $stmt->execute();
            $stmt->close();
            
            // Atribuir à nova representação
            $sql_atribuir = "UPDATE representacao SET cpf_delegado = ? WHERE id_representacao = ?";
            $stmt = $conn->prepare($sql_atribuir);
            $stmt->bind_param("si", $cpf, $id_representacao);
            
            if ($stmt->execute()) {
                // Buscar dados atualizados do delegado
                $sql_delegado = "SELECT u.nome, u.instituicao FROM usuario u WHERE u.cpf = ?";
                $stmt4 = $conn->prepare($sql_delegado);
                $stmt4->bind_param("s", $cpf);
                $stmt4->execute();
                $result4 = $stmt4->get_result();
                $delegado = $result4->fetch_assoc();
                
                $response = [
                    'success' => true,
                    'delegado' => [
                        'nome' => $delegado['nome'],
                        'instituicao' => $delegado['instituicao'],
                        'cpf' => $cpf
                    ]
                ];
                $stmt4->close();
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] == 'remover_delegado') {
        $id_representacao = $_POST['id_representacao'] ?? 0;
        
        if ($id_representacao) {
            $sql = "UPDATE representacao SET cpf_delegado = NULL WHERE id_representacao = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_representacao);
            
            if ($stmt->execute()) {
                $response['success'] = true;
            }
            $stmt->close();
        }
    }
    
    // Se for uma requisição AJAX, enviar JSON e sair
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Delegados</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .stat-card h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #3498db;
            margin: 5px 0;
        }
        
        .content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .comites-section, .delegados-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .comite-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #e1e5eb;
        }
        
        .comite-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e5eb;
        }
        
        .comite-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .comite-stats {
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .representacoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }
        
        .representacao-card {
            background: white;
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 10px;
            min-height: 80px;
            transition: all 0.3s ease;
            cursor: grab;
        }
        
        .representacao-card.filled {
            border-style: solid;
            border-color: #3498db;
            background: #ebf5fb;
            cursor: grab;
        }
        
        .representacao-card.drag-over {
            border-color: #2ecc71;
            background: #e8f6f3;
            transform: scale(1.02);
        }
        
        .rep-nome {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .delegado-info {
            font-size: 12px;
            color: #34495e;
            padding: 6px;
            background: #f1f8ff;
            border-radius: 4px;
            margin-top: 6px;
            cursor: grab;
            user-select: none;
        }
        
        .delegado-card {
            background: white;
            border: 2px solid #3498db;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: grab;
            transition: all 0.3s ease;
            user-select: none;
        }
        
        .delegado-card:hover {
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.2);
            transform: translateY(-2px);
        }
        
        .delegado-card.dragging {
            opacity: 0.5;
        }
        
        .delegado-nome {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
            margin-bottom: 5px;
        }
        
        .delegado-details {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        
        /* Estilos para as opções compactas */
        .opcoes-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            font-size: 10px;
        }
        
        .opcao-tag {
            background: #e8f4fd;
            border: 1px solid #b3d9ff;
            border-radius: 10px;
            padding: 2px 6px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            white-space: nowrap;
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .opcao-tag.comite {
            background: #e8f7ec;
            border-color: #a3e9c4;
        }
        
        .opcao-tag.representacao {
            background: #fff4e6;
            border-color: #ffd8b3;
        }
        
        .opcao-number {
            background: #3498db;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            margin-right: 3px;
            flex-shrink: 0;
        }
        
        .opcao-number.comite {
            background: #2ecc71;
        }
        
        .opcao-number.representacao {
            background: #e67e22;
        }
        
        /* Área para remover delegados via drag-and-drop */
        .trash-zone {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #e74c3c;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .trash-zone.drag-over {
            transform: scale(1.1);
            background: #c0392b;
            box-shadow: 0 6px 16px rgba(192, 57, 43, 0.4);
        }
        
        .trash-zone:hover {
            background: #c0392b;
        }
        
        /* Mensagens */
        .empty-message {
            text-align: center;
            padding: 30px;
            color: #95a5a6;
            font-style: italic;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            display: none;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            display: none;
        }
        
        .drag-hint {
            font-size: 10px;
            color: #3498db;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        @media (max-width: 1024px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            .representacoes-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users-cog"></i> Análise e Atribuição de Delegados</h1>
            <p>Arraste delegados para as representações desejadas</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Delegados</h3>
                    <div class="number"><?php echo $estatisticas['total_delegados'] ?? 0; ?></div>
                    <small>Inscritos no sistema</small>
                </div>
                <div class="stat-card">
                    <h3>Delegados Atribuídos</h3>
                    <div class="number"><?php echo $estatisticas['delegados_atribuidos'] ?? 0; ?></div>
                    <small>Com representação</small>
                </div>
                <div class="stat-card">
                    <h3>Representações</h3>
                    <div class="number">
                        <?php echo ($estatisticas['representacoes_preenchidas'] ?? 0) . '/' . ($estatisticas['total_representacoes'] ?? 0); ?>
                    </div>
                    <small>Preenchidas/Total</small>
                </div>
            </div>
        </div>
        
        <div id="successMessage" class="success-message"></div>
        <div id="errorMessage" class="error-message"></div>
        
        <div class="content">
            <!-- Seção de Comitês e Representações -->
            <div class="comites-section">
                <h2><i class="fas fa-landmark"></i> Comitês e Representações</h2>
                
                <?php if (empty($comites)): ?>
                    <div class="empty-message">
                        <i class="fas fa-inbox fa-3x"></i>
                        <p>Nenhum comitê aprovado encontrado.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($comites as $comite): ?>
                        <div class="comite-card">
                            <div class="comite-header">
                                <div class="comite-title">
                                    <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                    <small style="color: #7f8c8d;">(<?php echo htmlspecialchars($comite['tipo_comite']); ?>)</small>
                                </div>
                                <div class="comite-stats">
                                    <?php echo $comite['representacoes_preenchidas']; ?>/<?php echo $comite['total_representacoes']; ?> rep.
                                </div>
                            </div>
                            
                            <div class="representacoes-grid" data-comite="<?php echo $comite['id_comite']; ?>">
                                <?php foreach ($comite['representacoes'] as $representacao): ?>
                                    <div class="representacao-card <?php echo $representacao['cpf_delegado'] ? 'filled' : ''; ?>" 
                                         data-rep-id="<?php echo $representacao['id_representacao']; ?>"
                                         ondragover="allowDrop(event)" 
                                         ondrop="dropDelegado(event)">
                                        
                                        <div class="rep-nome">
                                            <i class="fas fa-flag" style="font-size: 12px;"></i>
                                            <?php echo htmlspecialchars($representacao['nome_representacao']); ?>
                                        </div>
                                        
                                        <?php if ($representacao['cpf_delegado']): ?>
                                            <div class="delegado-info" 
                                                 draggable="true" 
                                                 ondragstart="dragDelegadoFromRep(event)" 
                                                 data-cpf="<?php echo htmlspecialchars($representacao['cpf_delegado']); ?>">
                                                <div style="font-weight: 600; font-size: 11px;">
                                                    <i class="fas fa-user" style="font-size: 10px;"></i>
                                                    <?php 
                                                    $nome = htmlspecialchars($representacao['nome_delegado']);
                                                    echo strlen($nome) > 15 ? substr($nome, 0, 15) . '...' : $nome;
                                                    ?>
                                                </div>
                                                <div style="font-size: 10px; color: #7f8c8d;">
                                                    <?php 
                                                    $inst = htmlspecialchars($representacao['instituicao']);
                                                    echo strlen($inst) > 20 ? substr($inst, 0, 20) . '...' : $inst;
                                                    ?>
                                                </div>
                                                <div class="drag-hint">
                                                    <i class="fas fa-arrows-alt"></i> Arraste para a lixeira para remover
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="color: #95a5a6; font-size: 11px; text-align: center; padding-top: 15px;">
                                                <i class="fas fa-arrow-down"></i><br>
                                                Solte um delegado aqui
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Seção de Delegados (apenas não atribuídos) -->
            <div class="delegados-section">
                <h2><i class="fas fa-user-friends"></i> Delegados Disponíveis</h2>
                <p style="color: #7f8c8d; font-size: 13px; margin-bottom: 20px;">
                    Arraste para as representações vazias
                </p>
                
                <?php if (empty($delegados_sem_representacao)): ?>
                    <div class="empty-message">
                        <i class="fas fa-check-circle fa-3x" style="color: #2ecc71;"></i>
                        <p>Todos os delegados já estão atribuídos!</p>
                    </div>
                <?php else: ?>
                    <div id="delegadosList">
                        <?php foreach ($delegados_sem_representacao as $delegado): ?>
                            <div class="delegado-card" 
                                 draggable="true" 
                                 ondragstart="dragDelegado(event)" 
                                 data-cpf="<?php echo htmlspecialchars($delegado['cpf']); ?>">
                                
                                <div class="delegado-nome">
                                    <i class="fas fa-user-graduate"></i>
                                    <?php 
                                    $nome = htmlspecialchars($delegado['nome']);
                                    echo strlen($nome) > 25 ? substr($nome, 0, 25) . '...' : $nome;
                                    ?>
                                </div>
                                
                                <div class="delegado-details">
                                    <strong><?php echo htmlspecialchars($delegado['instituicao']); ?></strong>
                                </div>
                                
                                <!-- Opções compactas -->
                                <div class="opcoes-grid">
                                    <?php if (!empty($delegado['comite_op1_nome'])): ?>
                                        <div class="opcao-tag comite" title="1ª Opção de Comitê: <?php echo htmlspecialchars($delegado['comite_op1_nome']); ?>">
                                            <span class="opcao-number comite">1</span>
                                            <span><?php echo htmlspecialchars(substr($delegado['comite_op1_nome'], 0, 12)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($delegado['comite_op2_nome'])): ?>
                                        <div class="opcao-tag comite" title="2ª Opção de Comitê: <?php echo htmlspecialchars($delegado['comite_op2_nome']); ?>">
                                            <span class="opcao-number comite">2</span>
                                            <span><?php echo htmlspecialchars(substr($delegado['comite_op2_nome'], 0, 10)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($delegado['comite_op3_nome'])): ?>
                                        <div class="opcao-tag comite" title="3ª Opção de Comitê: <?php echo htmlspecialchars($delegado['comite_op3_nome']); ?>">
                                            <span class="opcao-number comite">3</span>
                                            <span><?php echo htmlspecialchars(substr($delegado['comite_op3_nome'], 0, 10)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($delegado['rep_op1_nome'])): ?>
                                        <div class="opcao-tag representacao" title="1ª Opção de Representação: <?php echo htmlspecialchars($delegado['rep_op1_nome']); ?>">
                                            <span class="opcao-number representacao">1</span>
                                            <span><?php echo htmlspecialchars(substr($delegado['rep_op1_nome'], 0, 10)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($delegado['rep_op2_nome'])): ?>
                                        <div class="opcao-tag representacao" title="2ª Opção de Representação: <?php echo htmlspecialchars($delegado['rep_op2_nome']); ?>">
                                            <span class="opcao-number representacao">2</span>
                                            <span><?php echo htmlspecialchars(substr($delegado['rep_op2_nome'], 0, 8)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($delegado['rep_op3_nome'])): ?>
                                        <div class="opcao-tag representacao" title="3ª Opção de Representação: <?php echo htmlspecialchars($delegado['rep_op3_nome']); ?>">
                                            <span class="opcao-number representacao">3</span>
                                            <span><?php echo htmlspecialchars(substr($delegado['rep_op3_nome'], 0, 8)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="drag-hint">
                                    <i class="fas fa-arrows-alt"></i> Arraste para uma representação
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Zona de lixeira para remover via drag-and-drop -->
        <div class="trash-zone" 
             ondragover="allowDrop(event)" 
             ondrop="dropInTrash(event)"
             ondragenter="this.classList.add('drag-over')"
             ondragleave="this.classList.remove('drag-over')"
             title="Arraste um delegado aqui para remover da representação">
            <i class="fas fa-trash"></i>
        </div>
    </div>
    
    <script>
        let delegadoArrastado = null;
        let delegadoFromRep = null;
        
        function dragDelegado(event) {
            delegadoArrastado = event.target;
            event.target.classList.add('dragging');
            event.dataTransfer.setData('text/plain', event.target.dataset.cpf);
            event.dataTransfer.setData('type', 'delegado');
        }
        
        function dragDelegadoFromRep(event) {
            delegadoFromRep = event.target;
            event.dataTransfer.setData('text/plain', event.target.dataset.cpf);
            event.dataTransfer.setData('type', 'delegado_atribuido');
            event.stopPropagation();
        }
        
        function allowDrop(event) {
            event.preventDefault();
            if (event.currentTarget.classList.contains('representacao-card')) {
                event.currentTarget.classList.add('drag-over');
            }
        }
        
        function dropDelegado(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            const type = event.dataTransfer.getData('type');
            let cpf;
            
            if (type === 'delegado_atribuido') {
                cpf = delegadoFromRep ? delegadoFromRep.dataset.cpf : event.dataTransfer.getData('text/plain');
            } else {
                cpf = delegadoArrastado ? delegadoArrastado.dataset.cpf : event.dataTransfer.getData('text/plain');
            }
            
            const repId = event.currentTarget.dataset.repId;
            
            if (cpf && repId) {
                atribuirDelegado(cpf, repId, event.currentTarget);
            }
            
            if (delegadoArrastado) {
                delegadoArrastado.classList.remove('dragging');
                delegadoArrastado = null;
            }
            if (delegadoFromRep) {
                delegadoFromRep = null;
            }
        }
        
        function dropInTrash(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            const type = event.dataTransfer.getData('type');
            const cpf = event.dataTransfer.getData('text/plain');
            
            if (type === 'delegado_atribuido' && cpf) {
                // Encontrar a representação onde o delegado está
                const repCard = document.querySelector(`.delegado-info[data-cpf="${cpf}"]`)?.closest('.representacao-card');
                if (repCard) {
                    const repId = repCard.dataset.repId;
                    removerDelegado(repId);
                }
            }
        }
        
        function atribuirDelegado(cpf, repId, repElement) {
            const formData = new FormData();
            formData.append('action', 'atribuir_delegado');
            formData.append('cpf', cpf);
            formData.append('id_representacao', repId);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar visual da representação
                    const nomeAbrev = data.delegado.nome.length > 15 ? 
                        data.delegado.nome.substring(0, 15) + '...' : data.delegado.nome;
                    const instAbrev = data.delegado.instituicao.length > 20 ? 
                        data.delegado.instituicao.substring(0, 20) + '...' : data.delegado.instituicao;
                    
                    repElement.classList.add('filled');
                    repElement.innerHTML = `
                        <div class="rep-nome">
                            <i class="fas fa-flag" style="font-size: 12px;"></i>
                            ${repElement.querySelector('.rep-nome').textContent.trim()}
                        </div>
                        <div class="delegado-info" 
                             draggable="true" 
                             ondragstart="dragDelegadoFromRep(event)" 
                             data-cpf="${cpf}">
                            <div style="font-weight: 600; font-size: 11px;">
                                <i class="fas fa-user" style="font-size: 10px;"></i>
                                ${nomeAbrev}
                            </div>
                            <div style="font-size: 10px; color: #7f8c8d;">
                                ${instAbrev}
                            </div>
                            <div class="drag-hint">
                                <i class="fas fa-arrows-alt"></i> Arraste para a lixeira para remover
                            </div>
                        </div>
                    `;
                    
                    // Remover da lista de delegados (se estiver lá)
                    const delegadoCard = document.querySelector(`.delegado-card[data-cpf="${cpf}"]`);
                    if (delegadoCard) {
                        delegadoCard.remove();
                    }
                    
                    // Atualizar estatísticas
                    atualizarEstatisticas();
                    showSuccess('Delegado atribuído com sucesso!');
                } else {
                    showError('Erro ao atribuir delegado.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro de comunicação com o servidor.');
            });
        }
        
        function removerDelegado(repId) {
            const formData = new FormData();
            formData.append('action', 'remover_delegado');
            formData.append('id_representacao', repId);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Recarregar para mostrar o delegado novamente na lista
                } else {
                    showError('Erro ao remover delegado.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro de comunicação com o servidor.');
            });
        }
        
        function atualizarEstatisticas() {
            // Simplesmente recarrega a página para atualizar estatísticas
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => successDiv.style.display = 'none', 3000);
        }
        
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => errorDiv.style.display = 'none', 3000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Limpar classes de drag-over
            document.addEventListener('dragend', function() {
                document.querySelectorAll('.representacao-card.drag-over').forEach(card => {
                    card.classList.remove('drag-over');
                });
                document.querySelector('.trash-zone.drag-over')?.classList.remove('drag-over');
                
                if (delegadoArrastado) {
                    delegadoArrastado.classList.remove('dragging');
                    delegadoArrastado = null;
                }
            });
            
            // Adicionar tooltips para as opções
            document.querySelectorAll('.opcao-tag').forEach(tag => {
                tag.addEventListener('mouseenter', function(e) {
                    const title = this.getAttribute('title');
                    if (title) {
                        this.setAttribute('data-tooltip', title);
                    }
                });
            });
        });
    </script>
</body>
</html>