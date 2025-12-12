<?php
session_start();
require_once 'php/conexao.php';

// Verificar se é administrador
if (!isset($_SESSION["cpf"]) || !isset($_SESSION["adm"]) || $_SESSION["adm"] != 1) {
    header("Location: login.html");
    exit();
}

$mensagem = '';
$erro = '';

// Processar ações de aprovação/reprovação
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $cpf_professor = $_POST['cpf'] ?? '';
    $acao = $_POST['acao'] ?? '';
    
    if (!empty($cpf_professor) && in_array($acao, ['aprovar', 'reprovar', 'aluno'])) {
        $novo_status = ($acao == 'aprovar') ? 'aprovado' : 
                      ($acao == 'reprovar' ? 'reprovado' : 'aluno');
        
        $sql = "UPDATE usuario SET professor = ? WHERE cpf = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $novo_status, $cpf_professor);
        
        if ($stmt->execute()) {
            $mensagem = "Status do professor atualizado para: " . ucfirst($novo_status);
        } else {
            $erro = "Erro ao atualizar status: " . $conn->error;
        }
        $stmt->close();
    } else {
        $erro = "Dados inválidos para processar a ação.";
    }
}

// Buscar professores pendentes
$sql_pendentes = "SELECT * FROM usuario WHERE professor = 'pendente' ORDER BY nome";
$result_pendentes = $conn->query($sql_pendentes);
$professores_pendentes = [];

if ($result_pendentes && $result_pendentes->num_rows > 0) {
    while ($row = $result_pendentes->fetch_assoc()) {
        $professores_pendentes[] = $row;
    }
}

// Buscar apenas professores aprovados e reprovados (NÃO alunos)
$sql_professores = "SELECT * FROM usuario WHERE professor IN ('aprovado', 'reprovado') 
                    ORDER BY 
                      CASE professor 
                          WHEN 'aprovado' THEN 1
                          WHEN 'reprovado' THEN 2
                      END, nome";
$result_professores = $conn->query($sql_professores);
$todos_professores = [];

if ($result_professores && $result_professores->num_rows > 0) {
    while ($row = $result_professores->fetch_assoc()) {
        $todos_professores[] = $row;
    }
}

// Estatísticas (apenas professores)
$sql_stats = "SELECT 
                SUM(CASE WHEN professor = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN professor = 'aprovado' THEN 1 ELSE 0 END) as aprovados,
                SUM(CASE WHEN professor = 'reprovado' THEN 1 ELSE 0 END) as reprovados
              FROM usuario 
              WHERE professor != 'aluno'";
$result_stats = $conn->query($sql_stats);
$estatisticas = $result_stats->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Professores - Admin</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50, #4a6572);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-card.pendentes { border-left: 5px solid #f39c12; }
        .stat-card.aprovados { border-left: 5px solid #2ecc71; }
        .stat-card.reprovados { border-left: 5px solid #e74c3c; }
        
        .content {
            margin-top: 20px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .section h2 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .professor-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .professor-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        
        .professor-card.pendente {
            border-left: 4px solid #f39c12;
            background: #fff8e1;
        }
        
        .professor-card.aprovado {
            border-left: 4px solid #2ecc71;
            background: #e8f5e9;
        }
        
        .professor-card.reprovado {
            border-left: 4px solid #e74c3c;
            background: #ffebee;
        }
        
        .professor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .professor-nome {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pendente { background: #f39c12; color: white; }
        .status-aprovado { background: #2ecc71; color: white; }
        .status-reprovado { background: #e74c3c; color: white; }
        
        .professor-info {
            font-size: 14px;
            color: #555;
            margin-bottom: 15px;
        }
        
        .professor-info div {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .acoes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-aprovar {
            background: #2ecc71;
            color: white;
        }
        
        .btn-aprovar:hover {
            background: #27ae60;
        }
        
        .btn-reprovar {
            background: #e74c3c;
            color: white;
        }
        
        .btn-reprovar:hover {
            background: #c0392b;
        }
        
        .btn-aluno {
            background: #3498db;
            color: white;
        }
        
        .btn-aluno:hover {
            background: #2980b9;
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }
        
        .empty-message i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
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
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .search-filter {
            margin-bottom: 20px;
        }
        
        .search-filter input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-filter input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .filtros {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filtro-btn {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filtro-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: #545b62;
            transform: translateY(-2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Gerenciar Professores Acompanhantes</h1>
            <p>Aprove ou reprove solicitações de professores acompanhantes</p>
            
            <div class="stats-grid">
                <div class="stat-card pendentes">
                    <div class="label">Pendentes</div>
                    <div class="number"><?php echo $estatisticas['pendentes'] ?? 0; ?></div>
                    <div class="description">Aguardando aprovação</div>
                </div>
                <div class="stat-card aprovados">
                    <div class="label">Aprovados</div>
                    <div class="number"><?php echo $estatisticas['aprovados'] ?? 0; ?></div>
                    <div class="description">Professores confirmados</div>
                </div>
                <div class="stat-card reprovados">
                    <div class="label">Reprovados</div>
                    <div class="number"><?php echo $estatisticas['reprovados'] ?? 0; ?></div>
                    <div class="description">Solicitações recusadas</div>
                </div>
            </div>
        </div>
        
        <!-- Mensagens de feedback -->
        <?php if ($mensagem): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $erro; ?>
            </div>
        <?php endif; ?>
        
        <div class="content">
            <!-- Seção: Professores Pendentes -->
            <div class="section">
                <h2><i class="fas fa-clock"></i> Solicitações Pendentes</h2>
                
                <?php if (empty($professores_pendentes)): ?>
                    <div class="empty-message">
                        <i class="fas fa-check-circle" style="color: #2ecc71; font-size: 48px;"></i>
                        <h3>Todas as solicitações foram processadas!</h3>
                        <p>Não há professores aguardando aprovação.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($professores_pendentes as $professor): ?>
                        <div class="professor-card pendente" data-nome="<?php echo htmlspecialchars(strtolower($professor['nome'])); ?>">
                            <div class="professor-header">
                                <div class="professor-nome">
                                    <i class="fas fa-user-graduate"></i>
                                    <?php echo htmlspecialchars($professor['nome']); ?>
                                </div>
                                <span class="status-badge status-pendente">PENDENTE</span>
                            </div>
                            
                            <div class="professor-info">
                                <div>
                                    <i class="fas fa-id-card"></i>
                                    <strong>CPF:</strong> <?php echo htmlspecialchars($professor['cpf']); ?>
                                </div>
                                <div>
                                    <i class="fas fa-envelope"></i>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($professor['email']); ?>
                                </div>
                                <div>
                                    <i class="fas fa-university"></i>
                                    <strong>Instituição:</strong> <?php echo htmlspecialchars($professor['instituicao']); ?>
                                </div>
                                <div>
                                    <i class="fas fa-phone"></i>
                                    <strong>Contato Inst.:</strong> <?php echo htmlspecialchars($professor['telefone_instituicao'] ?? 'Não informado'); ?>
                                </div>
                                <div>
                                    <i class="fas fa-envelope"></i>
                                    <strong>Email Inst.:</strong> <?php echo htmlspecialchars($professor['email_instituicao'] ?? 'Não informado'); ?>
                                </div>
                            </div>
                            
                            <div class="acoes">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($professor['cpf']); ?>">
                                    <input type="hidden" name="acao" value="aprovar">
                                    <button type="submit" class="btn btn-aprovar" onclick="return confirmarAcao('aprovar', '<?php echo htmlspecialchars(addslashes($professor['nome'])); ?>')">
                                        <i class="fas fa-check"></i> Aprovar
                                    </button>
                                </form>
                                
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($professor['cpf']); ?>">
                                    <input type="hidden" name="acao" value="reprovar">
                                    <button type="submit" class="btn btn-reprovar" onclick="return confirmarAcao('reprovar', '<?php echo htmlspecialchars(addslashes($professor['nome'])); ?>')">
                                        <i class="fas fa-times"></i> Reprovar
                                    </button>
                                </form>
                                
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($professor['cpf']); ?>">
                                    <input type="hidden" name="acao" value="aluno">
                                    <button type="submit" class="btn btn-aluno" onclick="return confirmarAcao('rebaixar para aluno', '<?php echo htmlspecialchars(addslashes($professor['nome'])); ?>')">
                                        <i class="fas fa-user-graduate"></i> Tornar Aluno
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Seção: Professores Aprovados/Reprovados -->
            <div class="section">
                <h2><i class="fas fa-users"></i> Professores Processados</h2>
                
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Buscar por nome, CPF ou instituição..." onkeyup="filtrarProfessores()">
                </div>
                
                <div class="filtros">
                    <button class="filtro-btn active" onclick="filtrarPorStatus('todos')">Todos</button>
                    <button class="filtro-btn" onclick="filtrarPorStatus('aprovado')">Aprovados</button>
                    <button class="filtro-btn" onclick="filtrarPorStatus('reprovado')">Reprovados</button>
                </div>
                
                <?php if (empty($todos_professores)): ?>
                    <div class="empty-message">
                        <i class="fas fa-user-slash" style="color: #95a5a6; font-size: 48px;"></i>
                        <h3>Nenhum professor processado</h3>
                        <p>Não há professores aprovados ou reprovados.</p>
                    </div>
                <?php else: ?>
                    <div id="professoresList">
                        <?php foreach ($todos_professores as $professor): 
                            $status_class = strtolower($professor['professor']);
                            $status_text = ucfirst($professor['professor']);
                        ?>
                            <div class="professor-card <?php echo $status_class; ?>" 
                                 data-status="<?php echo $status_class; ?>"
                                 data-nome="<?php echo htmlspecialchars(strtolower($professor['nome'])); ?>">
                                <div class="professor-header">
                                    <div class="professor-nome">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($professor['nome']); ?>
                                    </div>
                                    <span class="status-badge status-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                                
                                <div class="professor-info">
                                    <div>
                                        <i class="fas fa-id-card"></i>
                                        <strong>CPF:</strong> <?php echo htmlspecialchars($professor['cpf']); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-envelope"></i>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($professor['email']); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-university"></i>
                                        <strong>Instituição:</strong> <?php echo htmlspecialchars($professor['instituicao']); ?>
                                    </div>
                                    <?php if ($professor['professor'] == 'aprovado'): ?>
                                    <div>
                                        <i class="fas fa-calendar-check"></i>
                                        <strong>Professor aprovado</strong>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="acoes">
                                    <?php if ($professor['professor'] != 'aprovado'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($professor['cpf']); ?>">
                                        <input type="hidden" name="acao" value="aprovar">
                                        <button type="submit" class="btn btn-aprovar" onclick="return confirmarAcao('aprovar', '<?php echo htmlspecialchars(addslashes($professor['nome'])); ?>')">
                                            <i class="fas fa-check"></i> Aprovar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($professor['professor'] != 'reprovado'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($professor['cpf']); ?>">
                                        <input type="hidden" name="acao" value="reprovar">
                                        <button type="submit" class="btn btn-reprovar" onclick="return confirmarAcao('reprovar', '<?php echo htmlspecialchars(addslashes($professor['nome'])); ?>')">
                                            <i class="fas fa-times"></i> Reprovar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="cpf" value="<?php echo htmlspecialchars($professor['cpf']); ?>">
                                        <input type="hidden" name="acao" value="aluno">
                                        <button type="submit" class="btn btn-aluno" onclick="return confirmarAcao('rebaixar para aluno', '<?php echo htmlspecialchars(addslashes($professor['nome'])); ?>')">
                                            <i class="fas fa-user-graduate"></i> Tornar Aluno
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <a href="inicio.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Voltar ao Painel
        </a>
    </div>

    <!-- Modal de confirmação -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Confirmar ação</h3>
            <p id="modalMessage">Tem certeza que deseja realizar esta ação?</p>
            <div class="modal-buttons">
                <button onclick="cancelarAcao()" class="btn" style="background: #6c757d; color: white;">
                    Cancelar
                </button>
                <button onclick="executarAcao()" id="confirmButton" class="btn">
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variáveis para controle do modal
        let acaoPendente = null;
        let formPendente = null;
        let nomeProfessor = '';
        
        // Função para confirmar ação
        function confirmarAcao(acao, nome) {
            const modal = document.getElementById('confirmModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const confirmButton = document.getElementById('confirmButton');
            
            nomeProfessor = nome;
            acaoPendente = acao;
            formPendente = event.target.closest('form');
            
            // Configurar modal baseado na ação
            let titulo = '';
            let mensagem = '';
            let corBtn = '';
            
            switch(acao) {
                case 'aprovar':
                    titulo = 'Aprovar Professor';
                    mensagem = `Tem certeza que deseja APROVAR o professor ${nome}?`;
                    corBtn = 'btn-aprovar';
                    break;
                case 'reprovar':
                    titulo = 'Reprovar Professor';
                    mensagem = `Tem certeza que deseja REPROVAR o professor ${nome}?`;
                    corBtn = 'btn-reprovar';
                    break;
                case 'rebaixar para aluno':
                    titulo = 'Tornar Aluno';
                    mensagem = `Tem certeza que deseja alterar ${nome} para ALUNO?`;
                    corBtn = 'btn-aluno';
                    break;
            }
            
            modalTitle.textContent = titulo;
            modalMessage.textContent = mensagem;
            confirmButton.className = `btn ${corBtn}`;
            confirmButton.innerHTML = `<i class="fas fa-check"></i> ${titulo}`;
            
            modal.style.display = 'flex';
            return false;
        }
        
        // Funções do modal
        function cancelarAcao() {
            document.getElementById('confirmModal').style.display = 'none';
            acaoPendente = null;
            formPendente = null;
        }
        
        function executarAcao() {
            if (formPendente) {
                formPendente.submit();
            }
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cancelarAcao();
            }
        });
        
        // Filtragem de professores
        function filtrarProfessores() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const professores = document.querySelectorAll('#professoresList .professor-card');
            
            professores.forEach(professor => {
                const nome = professor.dataset.nome;
                const cpf = professor.querySelector('div:nth-child(1)').textContent.toLowerCase();
                const instituicao = professor.querySelector('div:nth-child(3)').textContent.toLowerCase();
                
                const match = nome.includes(searchTerm) || cpf.includes(searchTerm) || instituicao.includes(searchTerm);
                professor.style.display = match ? 'block' : 'none';
            });
        }
        
        function filtrarPorStatus(status) {
            // Ativar botão do filtro
            document.querySelectorAll('.filtro-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const professores = document.querySelectorAll('#professoresList .professor-card');
            
            professores.forEach(professor => {
                const professorStatus = professor.dataset.status;
                
                if (status === 'todos' || professorStatus === status) {
                    professor.style.display = 'block';
                } else {
                    professor.style.display = 'none';
                }
            });
        }
        
        // Fechar alertas automaticamente após 5 segundos
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>