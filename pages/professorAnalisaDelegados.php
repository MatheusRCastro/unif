<?php
session_start();
require_once 'php/conexao.php';

// Verificar se o usuário está logado e é um professor
if (!isset($_SESSION["cpf"])) {
    header("Location: login.html");
    exit();
}

// Verificar se o usuário é professor (tem delegação)
$sql_verifica_professor = "SELECT d.id_delegacao, d.nome as nome_delegacao, u.nome as nome_professor 
                           FROM delegacao d 
                           INNER JOIN usuario u ON d.cpf = u.cpf 
                           WHERE d.cpf = ? AND d.verificacao_delegacao = 'aprovado'";
$stmt = $conn->prepare($sql_verifica_professor);
$stmt->bind_param("s", $_SESSION['cpf']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Não é professor (não tem delegação aprovada)
    header("Location: inicio.php");
    exit();
}

$professor = $result->fetch_assoc();
$id_delegacao = $professor['id_delegacao'];
$nome_delegacao = $professor['nome_delegacao'];
$nome_professor = $professor['nome_professor'];
$stmt->close();

// Processar aprovações/reprovações
$mensagem = "";
$erro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao'])) {
    $cpf_aluno = $_POST['cpf_aluno'];
    $acao = $_POST['acao'];
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
    
    // Validar que o aluno realmente pertence à delegação do professor
    $sql_valida = "SELECT d.* FROM delegado d WHERE d.cpf = ? AND d.id_delegacao = ? AND d.aprovado_delegacao = 'pendente'";
    $stmt_valida = $conn->prepare($sql_valida);
    $stmt_valida->bind_param("si", $cpf_aluno, $id_delegacao);
    $stmt_valida->execute();
    $result_valida = $stmt_valida->get_result();
    
    if ($result_valida->num_rows == 0) {
        $erro = "Aluno não encontrado ou já processado.";
    } else {
        $aluno = $result_valida->fetch_assoc();
        
        if ($acao == 'aprovar') {
            $sql_atualiza = "UPDATE delegado SET aprovado_delegacao = 'aprovado' WHERE cpf = ? AND id_delegacao = ?";
            $mensagem = "Aluno aprovado com sucesso!";
        } else {
            $sql_atualiza = "UPDATE delegado SET aprovado_delegacao = 'reprovado' WHERE cpf = ? AND id_delegacao = ?";
            $mensagem = "Aluno reprovado com sucesso!";
            
            // Se quiser armazenar o motivo da reprovação (crie uma tabela para isso se necessário)
            // $sql_motivo = "INSERT INTO reprovacoes (cpf_aluno, id_delegacao, motivo) VALUES (?, ?, ?)";
        }
        
        $stmt_atualiza = $conn->prepare($sql_atualiza);
        $stmt_atualiza->bind_param("si", $cpf_aluno, $id_delegacao);
        
        if ($stmt_atualiza->execute()) {
            // Sucesso
        } else {
            $erro = "Erro ao processar solicitação: " . $stmt_atualiza->error;
        }
        $stmt_atualiza->close();
    }
    $stmt_valida->close();
}

// Buscar alunos pendentes na delegação do professor
$sql_alunos_pendentes = "
    SELECT d.cpf, d.id_delegacao, d.id_comite, d.representacao, d.comite_desejado, 
        d.primeira_op_representacao, d.segunda_op_representacao, d.terceira_op_representacao, 
        d.segunda_op_comite, d.terceira_op_comite, d.aprovado_delegacao, u.nome as nome_aluno, 
        u.email, u.telefone, c.nome_comite, c.tipo_comite, r.nome_representacao
    FROM delegado d
    INNER JOIN usuario u ON d.cpf = u.cpf
    LEFT JOIN comite c ON d.id_comite = c.id_comite
    LEFT JOIN representacao r ON d.representacao = r.id_representacao
    WHERE d.id_delegacao = ? AND d.aprovado_delegacao = 'pendente'
    ORDER BY u.nome";

$stmt_pendentes = $conn->prepare($sql_alunos_pendentes);
$stmt_pendentes->bind_param("i", $id_delegacao);
$stmt_pendentes->execute();
$result_pendentes = $stmt_pendentes->get_result();
$alunos_pendentes = $result_pendentes->fetch_all(MYSQLI_ASSOC);
$stmt_pendentes->close();

// Buscar alunos já processados (aprovados/reprovados)
$sql_alunos_processados = "
    SELECT 
        d.cpf,
        d.id_delegacao,
        d.id_comite,
        d.representacao,
        d.comite_desejado,
        d.aprovado_delegacao,
        u.nome as nome_aluno,
        u.email,
        c.nome_comite,
        c.tipo_comite
    FROM delegado d
    INNER JOIN usuario u ON d.cpf = u.cpf
    LEFT JOIN comite c ON d.id_comite = c.id_comite
    LEFT JOIN representacao r ON d.representacao = r.id_representacao
    WHERE d.id_delegacao = ?
    AND d.aprovado_delegacao IN ('aprovado', 'reprovado')
    ORDER BY d.id_comite DESC
    LIMIT 50";

$stmt_processados = $conn->prepare($sql_alunos_processados);
$stmt_processados->bind_param("i", $id_delegacao);
$stmt_processados->execute();
$result_processados = $stmt_processados->get_result();
$alunos_processados = $result_processados->fetch_all(MYSQLI_ASSOC);
$stmt_processados->close();

// Buscar estatísticas da delegação
$sql_estatisticas = "
    SELECT 
        COUNT(CASE WHEN aprovado_delegacao = 'pendente' THEN 1 END) as total_pendentes,
        COUNT(CASE WHEN aprovado_delegacao = 'aprovado' THEN 1 END) as total_aprovados,
        COUNT(CASE WHEN aprovado_delegacao = 'reprovado' THEN 1 END) as total_reprovados,
        COUNT(*) as total_alunos
    FROM delegado 
    WHERE id_delegacao = ?";

$stmt_estatisticas = $conn->prepare($sql_estatisticas);
$stmt_estatisticas->bind_param("i", $id_delegacao);
$stmt_estatisticas->execute();
$result_estatisticas = $stmt_estatisticas->get_result();
$estatisticas = $result_estatisticas->fetch_assoc();
$stmt_estatisticas->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Delegação - UNIF</title>
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary:rgb(26, 182, 20);
            --primary-dark:rgb(2, 116, 21);
            --secondary:rgb(13, 165, 39);
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
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
            color: var(--dark);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-info h1 {
            color: var(--dark);
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-info h1 i {
            color: var(--primary);
        }

        .header-info p {
            color: var(--gray);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-light {
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--light-gray);
        }

        .btn-light:hover {
            background: var(--light-gray);
        }

        /* Cards de Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .stat-icon.approved {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .stat-icon.rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .stat-icon.total {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            color: var(--gray);
            border-bottom: 3px solid transparent;
        }

        .tab:hover {
            background: var(--light);
        }

        .tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }

        .tab-badge {
            background: var(--light-gray);
            color: var(--dark);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-left: 8px;
        }

        .tab.active .tab-badge {
            background: var(--primary);
            color: white;
        }

        /* Content Sections */
        .content-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .content-section.active {
            display: block;
        }

        /* Cards de Alunos */
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .student-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        }

        .student-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-info h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .student-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .student-body {
            padding: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--light-gray);
        }

        .info-label {
            color: var(--gray);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .info-value {
            font-weight: 600;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }

        .committee-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 5px;
        }

        .student-footer {
            padding: 15px 20px;
            background: var(--light);
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85rem;
            flex: 1;
        }

        .btn-sm i {
            font-size: 0.9rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--light-gray);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--gray);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .empty-state p {
            color: var(--gray);
            max-width: 400px;
            margin: 0 auto 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .students-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
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

        .alert i {
            font-size: 1.2rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-info">
                <h1><i class="fas fa-user-tie"></i> Gerenciar Delegação</h1>
                <p>Professor: <strong><?php echo htmlspecialchars($nome_professor); ?></strong> | Delegação: <strong><?php echo htmlspecialchars($nome_delegacao); ?></strong></p>
            </div>
            <div class="header-actions">
                <a href="inicio.php" class="btn btn-light">
                    <i class="fas fa-home"></i> Início
                </a>
                <a href="entraComite.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Nova Inscrição
                </a>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($mensagem): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $estatisticas['total_pendentes']; ?></h3>
                    <p>Solicitações Pendentes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $estatisticas['total_aprovados']; ?></h3>
                    <p>Alunos Aprovados</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $estatisticas['total_reprovados']; ?></h3>
                    <p>Alunos Reprovados</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $estatisticas['total_alunos']; ?></h3>
                    <p>Total na Delegação</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="pendentes">
                Solicitações Pendentes
                <span class="tab-badge"><?php echo count($alunos_pendentes); ?></span>
            </div>
            <div class="tab" data-tab="processados">
                Processados Recentemente
                <span class="tab-badge"><?php echo count($alunos_processados); ?></span>
            </div>
        </div>

        <!-- Conteúdo - Pendentes -->
        <div class="content-section active" id="pendentes">
            <?php if (empty($alunos_pendentes)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Nenhuma solicitação pendente</h3>
                    <p>Não há alunos aguardando aprovação na sua delegação no momento.</p>
                </div>
            <?php else: ?>
                <div class="students-grid">
                    <?php foreach ($alunos_pendentes as $aluno): ?>
                        <div class="student-card" id="aluno-<?php echo $aluno['cpf']; ?>">
                            <div class="student-header">
                                <div class="student-info">
                                    <h4>
                                        <i class="fas fa-user-graduate"></i>
                                        <?php echo htmlspecialchars($aluno['nome_aluno']); ?>
                                    </h4>
                                    <p><?php echo htmlspecialchars($aluno['email']); ?></p>
                                </div>
                                <span class="status-badge status-pending">Pendente</span>
                            </div>
                            
                            <div class="student-body">
                                <div class="info-row">
                                    <span class="info-label">CPF:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($aluno['cpf']); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Telefone:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($aluno['telefone'] ?? 'Não informado'); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Comitê:</span>
                                    <span class="info-value">
                                        <?php echo htmlspecialchars($aluno['nome_comite'] ?? 'Não definido'); ?>
                                        <?php if ($aluno['tipo_comite']): ?>
                                            <span class="committee-badge"><?php echo htmlspecialchars($aluno['tipo_comite']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Representação:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($aluno['nome_representacao'] ?? 'Não definida'); ?></span>
                                </div>
                                
                                <?php if ($aluno['segunda_op_comite']): ?>
                                    <div class="info-row">
                                        <span class="info-label">2ª Opção Comitê:</span>
                                        <span class="info-value">ID: <?php echo $aluno['segunda_op_comite']; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($aluno['terceira_op_comite']): ?>
                                    <div class="info-row">
                                        <span class="info-label">3ª Opção Comitê:</span>
                                        <span class="info-value">ID: <?php echo $aluno['terceira_op_comite']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="student-footer">
                                <button class="btn btn-success btn-sm" onclick="aprovarAluno('<?php echo $aluno['cpf']; ?>', '<?php echo htmlspecialchars(addslashes($aluno['nome_aluno'])); ?>')">
                                    <i class="fas fa-check"></i> Aprovar
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="reprovarAluno('<?php echo $aluno['cpf']; ?>', '<?php echo htmlspecialchars(addslashes($aluno['nome_aluno'])); ?>')">
                                    <i class="fas fa-times"></i> Reprovar
                                </button>
                                <button class="btn btn-light btn-sm" onclick="verDetalhes('<?php echo $aluno['cpf']; ?>')">
                                    <i class="fas fa-eye"></i> Detalhes
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Conteúdo - Processados -->
        <div class="content-section" id="processados">
            <?php if (empty($alunos_processados)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>Nenhum aluno processado</h3>
                    <p>Ainda não há histórico de aprovações/reprovações na sua delegação.</p>
                </div>
            <?php else: ?>
                <div class="students-grid">
                    <?php foreach ($alunos_processados as $aluno): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <div class="student-info">
                                    <h4>
                                        <i class="fas fa-user-graduate"></i>
                                        <?php echo htmlspecialchars($aluno['nome_aluno']); ?>
                                    </h4>
                                    <p><?php echo htmlspecialchars($aluno['email']); ?></p>
                                </div>
                                <span class="status-badge <?php echo $aluno['aprovado_delegacao'] == 'aprovado' ? 'status-approved' : 'status-rejected'; ?>">
                                    <?php echo $aluno['aprovado_delegacao'] == 'aprovado' ? 'Aprovado' : 'Reprovado'; ?>
                                </span>
                            </div>
                            
                            <div class="student-body">
                                <div class="info-row">
                                    <span class="info-label">Comitê:</span>
                                    <span class="info-value">
                                        <?php echo htmlspecialchars($aluno['nome_comite'] ?? 'Não definido'); ?>
                                        <?php if ($aluno['tipo_comite']): ?>
                                            <span class="committee-badge"><?php echo htmlspecialchars($aluno['tipo_comite']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Representação:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($aluno['nome_representacao'] ?? 'Não definida'); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Data Inscrição:</span>
                                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($aluno['data_inscricao'])); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Data Processamento:</span>
                                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($aluno['data_processamento'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Aprovação -->
    <div class="modal" id="modalAprovar">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Confirmar Aprovação</h3>
                <button class="modal-close" onclick="fecharModal('modalAprovar')">&times;</button>
            </div>
            <form id="formAprovar" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="cpf_aluno" id="cpf_aluno_aprovar">
                    <input type="hidden" name="acao" value="aprovar">
                    <p>Tem certeza que deseja aprovar <strong id="nome_aluno_aprovar"></strong> na delegação?</p>
                    <p>O aluno será notificado sobre a aprovação e poderá participar da UNIF como membro da sua delegação.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" onclick="fecharModal('modalAprovar')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar Aprovação</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Reprovação -->
    <div class="modal" id="modalReprovar">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Confirmar Reprovação</h3>
                <button class="modal-close" onclick="fecharModal('modalReprovar')">&times;</button>
            </div>
            <form id="formReprovar" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="cpf_aluno" id="cpf_aluno_reprovar">
                    <input type="hidden" name="acao" value="reprovar">
                    
                    <div class="form-group">
                        <label for="motivo" class="form-label">Motivo da Reprovação (Opcional)</label>
                        <textarea name="motivo" id="motivo" class="form-control" placeholder="Informe o motivo da reprovação, se desejar..."></textarea>
                    </div>
                    
                    <p>Ao reprovar, o aluno será removido da sua delegação e poderá tentar outra inscrição.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" onclick="fecharModal('modalReprovar')">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Reprovação</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal" id="modalDetalhes">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Detalhes do Aluno</h3>
                <button class="modal-close" onclick="fecharModal('modalDetalhes')">&times;</button>
            </div>
            <div class="modal-body" id="detalhesConteudo">
                <!-- Conteúdo carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="fecharModal('modalDetalhes')">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        // Funções para abrir/fechar modais
        function abrirModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        // Funções para aprovar/reprovar alunos
        function aprovarAluno(cpf, nome) {
            document.getElementById('cpf_aluno_aprovar').value = cpf;
            document.getElementById('nome_aluno_aprovar').textContent = nome;
            abrirModal('modalAprovar');
        }

        function reprovarAluno(cpf, nome) {
            document.getElementById('cpf_aluno_reprovar').value = cpf;
            document.getElementById('nome_aluno_reprovar').textContent = nome;
            abrirModal('modalReprovar');
        }

        async function verDetalhes(cpf) {
            try {
                // Mostrar loading
                document.getElementById('detalhesConteudo').innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                        <p style="margin-top: 15px; color: var(--gray);">Carregando detalhes...</p>
                    </div>
                `;
                
                abrirModal('modalDetalhes');
                
                // Fazer requisição AJAX para buscar detalhes
                const response = await fetch(`php/buscar_detalhes_aluno.php?cpf=${cpf}&delegacao=<?php echo $id_delegacao; ?>`);
                const data = await response.json();
                
                if (data.success) {
                    const aluno = data.aluno;
                    let html = `
                        <div class="info-row">
                            <span class="info-label">Nome:</span>
                            <span class="info-value"><strong>${aluno.nome_aluno}</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">CPF:</span>
                            <span class="info-value">${aluno.cpf}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">${aluno.email}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Telefone:</span>
                            <span class="info-value">${aluno.telefone || 'Não informado'}</span>
                        </div>
                    `;
                    
                    if (aluno.justificativa) {
                        html += `
                            <div class="form-group" style="margin-top: 20px;">
                                <label class="form-label">Justificativa do Aluno:</label>
                                <div style="background: var(--light); padding: 15px; border-radius: 8px; margin-top: 8px;">
                                    ${aluno.justificativa}
                                </div>
                            </div>
                        `;
                    }
                    
                    document.getElementById('detalhesConteudo').innerHTML = html;
                } else {
                    document.getElementById('detalhesConteudo').innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--warning);"></i>
                            <p style="margin-top: 15px; color: var(--danger);">${data.message || 'Erro ao carregar detalhes'}</p>
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('detalhesConteudo').innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--danger);"></i>
                        <p style="margin-top: 15px; color: var(--danger);">Erro ao carregar detalhes</p>
                    </div>
                `;
                console.error('Erro:', error);
            }
        }

        // Sistema de tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remover classe active de todas as tabs
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
                
                // Adicionar classe active à tab clicada
                tab.classList.add('active');
                
                // Mostrar conteúdo correspondente
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Prevenir envio duplo dos formulários
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                }
            });
        });
    </script>
</body>
</html>

<?php
// Fechar conexão
if ($conn && $conn->connect_error === null) {
    $conn->close();
}
?>