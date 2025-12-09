<?php
session_start();
require_once 'php/conexao.php';

// Verificar se o usuário está autenticado e é administrador
if (!isset($_SESSION["cpf"])) {
    header("Location: login.html");
    exit();
}

// Verificar se é administrador
$cpf_admin = $_SESSION["cpf"];
$sql_admin = "SELECT adm FROM usuario WHERE cpf = ?";
$stmt_admin = $conn->prepare($sql_admin);
$stmt_admin->bind_param("s", $cpf_admin);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();

if ($result_admin->num_rows == 0 || $result_admin->fetch_assoc()['adm'] != 1) {
    echo "Usuário não autorizado!";
    exit();
}

// Obter o UNIF mais recente
$sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
$result_unif = $conn->query($sql_unif);

if ($result_unif->num_rows == 0) {
    $erro = "Não há UNIF cadastrado no momento.";
    $id_unif = null;
} else {
    $unif = $result_unif->fetch_assoc();
    $id_unif = $unif['id_unif'];
}

// Verificar filtro selecionado via GET
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$filtros_validos = ['todos', 'pendentes', 'aprovados', 'reprovados'];

if (!in_array($filtro, $filtros_validos)) {
    $filtro = 'todos';
}

// Mapear filtro para condição SQL - AGORA usando status_inscricao
$condicoes_filtro = [
    'todos' => "",
    'pendentes' => "AND s.status_inscricao = 'pendente'",
    'aprovados' => "AND s.status_inscricao = 'aprovado'", 
    'reprovados' => "AND s.status_inscricao = 'reprovado'"
];

// Processar ações (aprovar/reprovar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['acao']) && isset($_POST['cpf_staff'])) {
        $cpf_staff = $_POST['cpf_staff'];
        $acao = $_POST['acao'];
        
        if ($acao == 'aprovar') {
            $sql = "UPDATE staff SET status_inscricao = 'aprovado' WHERE cpf = ? AND id_unif = ?";
        } elseif ($acao == 'reprovar') {
            $sql = "UPDATE staff SET status_inscricao = 'reprovado' WHERE cpf = ? AND id_unif = ?";
        } elseif ($acao == 'pendente') {
            $sql = "UPDATE staff SET status_inscricao = 'pendente' WHERE cpf = ? AND id_unif = ?";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $cpf_staff, $id_unif);
        $stmt->execute();
        $stmt->close();
        
        // Redirecionar mantendo o filtro atual
        header("Location: " . $_SERVER['PHP_SELF'] . "?filtro=" . $filtro);
        exit();
    }
}

// Buscar staffs conforme filtro selecionado
$staffs = [];
if ($id_unif) {
    // AGORA usando status_inscricao em vez de status
    $sql_staffs = "SELECT s.cpf, s.justificativa, s.status_inscricao, 
                          u.nome, u.email, u.instituicao, u.telefone
                   FROM staff s
                   JOIN usuario u ON s.cpf = u.cpf
                   WHERE s.id_unif = ? 
                   {$condicoes_filtro[$filtro]}
                   ORDER BY 
                       CASE s.status_inscricao 
                           WHEN 'pendente' THEN 1
                           WHEN 'aprovado' THEN 2
                           WHEN 'reprovado' THEN 3
                       END, 
                       u.nome";
    
    $stmt_staffs = $conn->prepare($sql_staffs);
    $stmt_staffs->bind_param("i", $id_unif);
    $stmt_staffs->execute();
    $result_staffs = $stmt_staffs->get_result();
    
    while ($staff = $result_staffs->fetch_assoc()) {
        $staffs[] = $staff;
    }
    $stmt_staffs->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/analiseStaffs.css">
    <title>Análise das Inscrições de Staff</title>
    <style>
        /* Estilos específicos para esta página */
        .staffs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .staffs-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .staffs-logo img {
            max-width: 200px;
        }
        
        .form-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .staffs-titulo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        
        /* Estilos para os filtros */
        .filtros-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .filtro-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background-color: #e9ecef;
            color: #495057;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .filtro-btn:hover {
            background-color: #dee2e6;
        }
        
        .filtro-btn.active {
            background-color: #007bff;
            color: white;
        }
        
        .filtro-btn.todos.active { background-color: #6c757d; }
        .filtro-btn.pendentes.active { background-color: #ffc107; }
        .filtro-btn.aprovados.active { background-color: #28a745; }
        .filtro-btn.reprovados.active { background-color: #dc3545; }
        
        /* Estilos para os itens de staff */
        .staffs-lista {
            margin-bottom: 20px;
        }
        
        .staff-item {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
        }
        
        .staff-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .staff-nome {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        
        .staff-cpf {
            color: #666;
            font-size: 14px;
        }
        
        .staff-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-aprovado {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-reprovado {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .staff-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            font-size: 14px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
        }
        
        .staff-justificativa {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        
        .justificativa-label {
            font-weight: bold;
            margin-bottom: 10px;
            color: #555;
        }
        
        .justificativa-text {
            line-height: 1.6;
            color: #333;
            white-space: pre-wrap;
        }
        
        .staff-botoes {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .staff-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .staff-aprovar {
            background-color: #28a745;
            color: white;
        }
        
        .staff-aprovar:hover {
            background-color: #218838;
        }
        
        .staff-reprovar {
            background-color: #dc3545;
            color: white;
        }
        
        .staff-reprovar:hover {
            background-color: #c82333;
        }
        
        .staff-pendente {
            background-color: #ffc107;
            color: #212529;
        }
        
        .staff-pendente:hover {
            background-color: #e0a800;
        }
        
        .sem-inscricoes {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #dee2e6;
        }
        
        .voltar-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: #007bff;
            text-decoration: none;
            padding: 10px;
        }
        
        .voltar-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="staffs-container">
        <div class="staffs-logo">
            <img src="images/unif.png" alt="Logo UNIF">
        </div>

        <div class="form-container">
            <div class="staffs-titulo">Análise das Inscrições de Staff</div>
            
            <?php if (isset($erro)): ?>
                <div style="color: #dc3545; text-align: center; padding: 20px; background-color: #f8d7da; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="filtros-container">
                <a href="?filtro=todos" class="filtro-btn todos <?php echo $filtro == 'todos' ? 'active' : ''; ?>">
                    Todos
                </a>
                <a href="?filtro=pendentes" class="filtro-btn pendentes <?php echo $filtro == 'pendentes' ? 'active' : ''; ?>">
                    Pendentes
                </a>
                <a href="?filtro=aprovados" class="filtro-btn aprovados <?php echo $filtro == 'aprovados' ? 'active' : ''; ?>">
                    Aprovados
                </a>
                <a href="?filtro=reprovados" class="filtro-btn reprovados <?php echo $filtro == 'reprovados' ? 'active' : ''; ?>">
                    Reprovados
                </a>
            </div>
            
            <?php if (empty($staffs)): ?>
                <div class="sem-inscricoes">
                    Não há inscrições de staff <?php echo $filtro != 'todos' ? $filtro : ''; ?>.
                </div>
            <?php else: ?>
                <div class="staffs-lista">
                    <?php foreach ($staffs as $staff): 
                        // AGORA usando status_inscricao em vez de status
                        $status_class = 'status-' . $staff['status_inscricao'];
                        $status_text = ucfirst($staff['status_inscricao']);
                    ?>
                    <div class="staff-item">
                        <div class="staff-item-header">
                            <div>
                                <div class="staff-nome"><?php echo htmlspecialchars($staff['nome']); ?></div>
                                <div class="staff-cpf">CPF: <?php echo htmlspecialchars($staff['cpf']); ?></div>
                            </div>
                            <div class="staff-status <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </div>
                        </div>
                        
                        <div class="staff-info">
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($staff['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Telefone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($staff['telefone']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Instituição:</span>
                                <span class="info-value"><?php echo htmlspecialchars($staff['instituicao']); ?></span>
                            </div>
                        </div>
                        
                        <div class="staff-justificativa">
                            <div class="justificativa-label">Justificativa para ser staff:</div>
                            <div class="justificativa-text"><?php echo nl2br(htmlspecialchars($staff['justificativa'])); ?></div>
                        </div>
                        
                        <form method="post" class="staff-botoes">
                            <input type="hidden" name="cpf_staff" value="<?php echo htmlspecialchars($staff['cpf']); ?>">
                            
                            <?php if ($staff['status_inscricao'] != 'aprovado'): ?>
                            <button type="submit" name="acao" value="aprovar" class="staff-btn staff-aprovar">
                                ✅ APROVAR
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($staff['status_inscricao'] != 'reprovado'): ?>
                            <button type="submit" name="acao" value="reprovar" class="staff-btn staff-reprovar">
                                ❌ REPROVAR
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($staff['status_inscricao'] != 'pendente'): ?>
                            <button type="submit" name="acao" value="pendente" class="staff-btn staff-pendente">
                                ⏳ VOLTAR PARA PENDENTE
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <a href="inicio.php" class="voltar-link">← Voltar ao início</a>
        </div>
    </div>
    
    <script>
        // Confirmar ações
        document.querySelectorAll('.staff-reprovar').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Tem certeza que deseja reprovar esta inscrição de staff?')) {
                    e.preventDefault();
                }
            });
        });
        
        document.querySelectorAll('.staff-aprovar').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Deseja aprovar esta inscrição de staff?')) {
                    e.preventDefault();
                }
            });
        });
        
        document.querySelectorAll('.staff-pendente').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Deseja marcar esta inscrição como pendente novamente?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>