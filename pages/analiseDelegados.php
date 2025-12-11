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
$current_unif_id = 1; // Pode ser obtido dinamicamente

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
    
    // Buscar delegados sem representação atribuída
    // NOTA: Pelo esquema do banco, não há tabela 'delegacao'. Vou buscar da tabela 'delegado'
    $sql_delegados_sem_rep = "
        SELECT 
            d.cpf, 
            u.nome, 
            u.email, 
            u.telefone, 
            u.instituicao,
            u.restricao_alimentar,
            u.alergia,
            c.nome_comite
        FROM delegado d
        JOIN usuario u ON d.cpf = u.cpf
        LEFT JOIN representacao r ON d.cpf = r.cpf_delegado
        LEFT JOIN comite c ON d.id_comite = c.id_comite
        WHERE r.cpf_delegado IS NULL
        AND d.id_comite IS NOT NULL
        AND c.id_unif = ?";
    
    $stmt_delegados = $conn->prepare($sql_delegados_sem_rep);
    $stmt_delegados->bind_param("i", $current_unif_id);
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
                // Atualizar a tabela delegado com o id_comite da representação
                $sql_get_comite = "SELECT id_comite FROM representacao WHERE id_representacao = ?";
                $stmt2 = $conn->prepare($sql_get_comite);
                $stmt2->bind_param("i", $id_representacao);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                if ($comite_row = $result2->fetch_assoc()) {
                    $sql_update_delegado = "UPDATE delegado SET id_comite = ? WHERE cpf = ?";
                    $stmt3 = $conn->prepare($sql_update_delegado);
                    $stmt3->bind_param("is", $comite_row['id_comite'], $cpf);
                    $stmt3->execute();
                    $stmt3->close();
                }
                $stmt2->close();
                
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
                        'instituicao' => $delegado['instituicao']
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
            
            $response['success'] = $stmt->execute();
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
        /* Estilos permanecem os mesmos */
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
        
        .comites-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .delegados-section {
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
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .comite-stats {
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .representacoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .representacao-card {
            background: white;
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 12px;
            min-height: 100px;
            transition: all 0.3s ease;
            cursor: move;
        }
        
        .representacao-card.filled {
            border-style: solid;
            border-color: #3498db;
            background: #ebf5fb;
        }
        
        .representacao-card.drag-over {
            border-color: #2ecc71;
            background: #e8f6f3;
            transform: scale(1.02);
        }
        
        .rep-nome {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .delegado-info {
            font-size: 13px;
            color: #34495e;
            padding: 8px;
            background: #f1f8ff;
            border-radius: 4px;
            margin-top: 8px;
        }
        
        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 8px;
            display: none;
        }
        
        .representacao-card.filled:hover .remove-btn {
            display: inline-block;
        }
        
        .delegado-card {
            background: white;
            border: 2px solid #3498db;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.3s ease;
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
        }
        
        .delegado-details {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .empty-message {
            text-align: center;
            padding: 30px;
            color: #95a5a6;
            font-style: italic;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #3498db;
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
        
        .progress-bar {
            height: 6px;
            background: #ecf0f1;
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #2ecc71;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .content {
                grid-template-columns: 1fr;
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
                <div class="stat-card">
                    <h3>Taxa de Preenchimento</h3>
                    <div class="number">
                        <?php 
                        $total = $estatisticas['total_representacoes'] ?? 1;
                        $preenchidas = $estatisticas['representacoes_preenchidas'] ?? 0;
                        echo $total > 0 ? round(($preenchidas / $total) * 100) : 0;
                        ?>%
                    </div>
                    <small>Progresso geral</small>
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
                                    <?php echo $comite['representacoes_preenchidas']; ?>/<?php echo $comite['total_representacoes']; ?> representações
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $comite['total_representacoes'] > 0 ? ($comite['representacoes_preenchidas'] / $comite['total_representacoes'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="representacoes-grid" data-comite="<?php echo $comite['id_comite']; ?>">
                                <?php foreach ($comite['representacoes'] as $representacao): ?>
                                    <div class="representacao-card <?php echo $representacao['cpf_delegado'] ? 'filled' : ''; ?>" 
                                         data-rep-id="<?php echo $representacao['id_representacao']; ?>"
                                         ondragover="allowDrop(event)" 
                                         ondrop="dropDelegado(event)">
                                        
                                        <div class="rep-nome">
                                            <i class="fas fa-flag"></i>
                                            <?php echo htmlspecialchars($representacao['nome_representacao']); ?>
                                        </div>
                                        
                                        <?php if ($representacao['cpf_delegado']): ?>
                                            <div class="delegado-info">
                                                <div class="delegado-nome">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($representacao['nome_delegado']); ?>
                                                </div>
                                                <div class="delegado-details">
                                                    <?php echo htmlspecialchars($representacao['instituicao']); ?>
                                                </div>
                                                <button class="remove-btn" onclick="removerDelegado(<?php echo $representacao['id_representacao']; ?>)">
                                                    <i class="fas fa-times"></i> Remover
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div style="color: #95a5a6; font-size: 12px; margin-top: 20px; text-align: center;">
                                                <i class="fas fa-arrow-down"></i><br>
                                                Arraste um delegado aqui
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Seção de Delegados Sem Representação -->
            <div class="delegados-section">
                <h2><i class="fas fa-user-friends"></i> Delegados Sem Representação</h2>
                <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 20px;">
                    Arraste estes delegados para as representações
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
                                    <?php echo htmlspecialchars($delegado['nome']); ?>
                                </div>
                                
                                <div class="delegado-details">
                                    <strong>Instituição:</strong> <?php echo htmlspecialchars($delegado['instituicao']); ?><br>
                                    <?php if (isset($delegado['nome_comite'])): ?>
                                    <strong>Comitê Desejado:</strong> <?php echo htmlspecialchars($delegado['nome_comite']); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($delegado['justificativa'])): ?>
                                    <div class="justificativa">
                                        <strong>Justificativa:</strong> 
                                        <?php echo strlen($delegado['justificativa']) > 100 ? 
                                            htmlspecialchars(substr($delegado['justificativa'], 0, 100)) . '...' : 
                                            htmlspecialchars($delegado['justificativa']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // JavaScript permanece o mesmo
        let delegadoArrastado = null;
        
        function dragDelegado(event) {
            delegadoArrastado = event.target;
            event.target.classList.add('dragging');
            event.dataTransfer.setData('text/plain', event.target.dataset.cpf);
        }
        
        function allowDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.add('drag-over');
        }
        
        function dropDelegado(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            const cpf = delegadoArrastado ? delegadoArrastado.dataset.cpf : event.dataTransfer.getData('text/plain');
            const repId = event.currentTarget.dataset.repId;
            
            if (cpf && repId) {
                atribuirDelegado(cpf, repId, event.currentTarget);
            }
            
            if (delegadoArrastado) {
                delegadoArrastado.classList.remove('dragging');
                delegadoArrastado = null;
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
                    repElement.classList.add('filled');
                    repElement.innerHTML = `
                        <div class="rep-nome">
                            <i class="fas fa-flag"></i>
                            ${repElement.querySelector('.rep-nome').textContent}
                        </div>
                        <div class="delegado-info">
                            <div class="delegado-nome">
                                <i class="fas fa-user"></i>
                                ${data.delegado.nome}
                            </div>
                            <div class="delegado-details">
                                ${data.delegado.instituicao}
                            </div>
                            <button class="remove-btn" onclick="removerDelegado(${repId})">
                                <i class="fas fa-times"></i> Remover
                            </button>
                        </div>
                    `;
                    
                    const delegadoCard = document.querySelector(`.delegado-card[data-cpf="${cpf}"]`);
                    if (delegadoCard) {
                        delegadoCard.remove();
                    }
                    
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
            if (!confirm('Remover delegado desta representação?')) {
                return;
            }
            
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
                    location.reload();
                } else {
                    showError('Erro ao remover delegado.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro de comunicação com o servidor.');
            });
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
        
        function atualizarEstatisticas() {
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const repCards = document.querySelectorAll('.representacao-card');
            repCards.forEach(card => {
                card.addEventListener('dragleave', function() {
                    this.classList.remove('drag-over');
                });
            });
            
            const delegadoCards = document.querySelectorAll('.delegado-card');
            delegadoCards.forEach(card => {
                card.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                });
            });
        });
    </script>
</body>
</html>