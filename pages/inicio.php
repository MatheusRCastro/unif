<?php
session_start();
require_once 'php/conexao.php';
// Verificar se o usuário está autenticado
if (!isset($_SESSION["cpf"])) {
    header("Location: login.php");
    exit();
}

$proxima_unif_ano = "2026"; // Valor padrão
$unif_atual = null;

if ($conn && $conn->connect_error === null) {
    $sql = "SELECT * FROM unif 
            ORDER BY data_inicio_unif DESC 
            LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $unif_atual = $result->fetch_assoc();
        
        // Extrair ano da data de início ou do nome
        if (!empty($unif_atual['data_inicio_unif'])) {
            $proxima_unif_ano = date('Y', strtotime($unif_atual['data_inicio_unif']));
        } elseif (!empty($unif_atual['nome']) && preg_match('/\d{4}/', $unif_atual['nome'], $matches)) {
            $proxima_unif_ano = $matches[0];
        }
    }
}

// Dados do usuário (simulação - você pode pegar do banco de dados)
$nome_usuario = isset($_SESSION['nome']) ? $_SESSION['nome'] : 'Usuário UNIF';
$primeiro_nome = explode(' ', $nome_usuario)[0];
$cpf_formatado = isset($_SESSION['cpf']) ? substr($_SESSION['cpf'], 0, 3) . '.***.***-**' : '';

// Verificar se é admin
$is_admin = isset($_SESSION["adm"]) && $_SESSION["adm"] == true;

// Verificar se é professor aprovado
$is_professor = isset($_SESSION['professor']) && $_SESSION['professor'] == 'aprovado';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>UNIF - Dashboard</title>
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary:rgb(28, 112, 28);
      --primary-light:rgb(48, 170, 74);
      --primary-dark:rgb(25, 196, 68);
      --secondary:rgb(14, 138, 35);
      --accent: #ff4081;
      --light: #f8f9fa;
      --dark: #212529;
      --gray: #6c757d;
      --light-gray: #e9ecef;
      --success: #28a745;
      --warning: #ffc107;
      --danger: #dc3545;
      --border-radius: 12px;
      --box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
      color: var(--dark);
      min-height: 100vh;
      line-height: 1.6;
    }

    .auth-error {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      text-align: center;
      padding: 40px 20px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
    }

    .auth-error h2 {
      font-family: 'Montserrat', sans-serif;
      font-size: 2.5rem;
      margin-bottom: 20px;
    }

    .auth-error p {
      font-size: 1.2rem;
      margin-bottom: 30px;
      max-width: 500px;
      opacity: 0.9;
    }

    .auth-button {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: white;
      color: var(--primary);
      text-decoration: none;
      padding: 15px 30px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 1.1rem;
      transition: var(--transition);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .auth-button:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    /* Container Principal */
    .container {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar Moderna */
    .sidebar {
      width: 260px;
      background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 25px 0;
      display: flex;
      flex-direction: column;
      box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
      z-index: 100;
    }

    .sidebar-header {
      padding: 0 25px 25px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 25px;
    }

    .sidebar-header h2 {
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      font-size: 1.8rem;
      background: linear-gradient(90deg, #ffffff, var(--secondary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .sidebar-header h2 i {
      background: linear-gradient(90deg, var(--secondary), var(--accent));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .sidebar-menu {
      list-style: none;
      flex-grow: 1;
    }

    .sidebar-menu li {
      margin-bottom: 5px;
      position: relative;
    }

    .sidebar-menu li a {
      display: flex;
      align-items: center;
      padding: 15px 25px;
      color: rgba(255, 255, 255, 0.85);
      text-decoration: none;
      font-weight: 500;
      transition: var(--transition);
      border-left: 4px solid transparent;
    }

    .sidebar-menu li a:hover,
    .sidebar-menu li.active a {
      background: rgba(255, 255, 255, 0.1);
      color: white;
      border-left: 4px solid var(--accent);
      padding-left: 30px;
    }

    .sidebar-menu li a i {
      width: 24px;
      margin-right: 15px;
      font-size: 1.2rem;
      text-align: center;
    }

    .sidebar-footer {
      padding: 20px 25px 0;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      margin-top: auto;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      color: white;
      font-size: 0.9rem;
    }

    .user-details h4 {
      font-size: 0.95rem;
      margin-bottom: 2px;
      color: white;
    }

    .user-details p {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.7);
    }

    .user-badges {
      display: flex;
      gap: 5px;
      margin-top: 5px;
    }

    .badge {
      font-size: 0.7rem;
      padding: 2px 8px;
      border-radius: 10px;
      font-weight: 600;
    }

    .badge-admin {
      background: var(--accent);
      color: white;
    }

    .badge-professor {
      background: var(--secondary);
      color: white;
    }

    /* Conteúdo Principal */
    .main-content {
      flex: 1;
      padding: 30px;
      overflow-y: auto;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    /* Seção de Boas-vindas */
    .welcome-section {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      padding: 40px;
      border-radius: var(--border-radius);
      margin-bottom: 40px;
      box-shadow: var(--box-shadow);
      position: relative;
      overflow: hidden;
    }

    .welcome-section::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 300px;
      height: 300px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 50%;
      transform: translate(30%, -30%);
    }

    .welcome-section h1 {
      font-family: 'Montserrat', sans-serif;
      font-size: 2.8rem;
      margin-bottom: 10px;
      position: relative;
      z-index: 1;
    }

    .welcome-section p {
      font-size: 1.2rem;
      opacity: 0.9;
      max-width: 600px;
      position: relative;
      z-index: 1;
      margin-bottom: 20px;
    }

    .welcome-stats {
      display: flex;
      gap: 20px;
      margin-top: 30px;
      position: relative;
      z-index: 1;
    }

    .stat-item {
      background: rgba(255, 255, 255, 0.15);
      padding: 15px 20px;
      border-radius: var(--border-radius);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .stat-number {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 0.9rem;
      opacity: 0.8;
    }

    /* Cards de Ação Rápida */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }

    .action-card {
      background: white;
      border-radius: var(--border-radius);
      padding: 25px;
      box-shadow: var(--box-shadow);
      transition: var(--transition);
      border-left: 5px solid var(--primary);
      text-align: center;
    }

    .action-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
    }

    .action-card.editions {
      border-left-color: var(--secondary);
    }

    .action-card.profile {
      border-left-color: var(--accent);
    }

    .action-card.messages {
      border-left-color: #ff9800;
    }

    .action-card.help {
      border-left-color: #4caf50;
    }

    .action-icon {
      font-size: 2.5rem;
      margin-bottom: 15px;
      display: block;
    }

    .action-card.editions .action-icon {
      color: var(--secondary);
    }

    .action-card.profile .action-icon {
      color: var(--accent);
    }

    .action-card.messages .action-icon {
      color: #ff9800;
    }

    .action-card.help .action-icon {
      color: #4caf50;
    }

    .action-card h3 {
      font-family: 'Montserrat', sans-serif;
      margin-bottom: 10px;
      color: var(--dark);
    }

    .action-card p {
      color: var(--gray);
      margin-bottom: 20px;
      font-size: 0.95rem;
    }

    .action-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--primary);
      color: white;
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: var(--transition);
    }

    .action-btn:hover {
      background: var(--primary-dark);
      transform: translateX(5px);
    }

    /* Logo Central */
    .unif-logo {
      display: block;
      max-width: 200px;
      margin: 30px auto;
      filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
      opacity: 0.9;
    }

    /* Responsividade */
    @media (max-width: 1024px) {
      .sidebar {
        width: 80px;
      }
      
      .sidebar-header h2 span,
      .sidebar-menu li a span,
      .user-details,
      .sidebar-footer {
        display: none;
      }
      
      .sidebar-header h2 {
        justify-content: center;
      }
      
      .sidebar-menu li a {
        justify-content: center;
        padding: 15px;
      }
      
      .sidebar-menu li a i {
        margin-right: 0;
        font-size: 1.4rem;
      }
      
      .welcome-section h1 {
        font-size: 2.2rem;
      }
    }

    @media (max-width: 768px) {
      .container {
        flex-direction: column;
      }
      
      .sidebar {
        width: 100%;
        height: auto;
        padding: 15px 0;
      }
      
      .sidebar-menu {
        display: flex;
        justify-content: space-around;
        padding: 0 15px;
      }
      
      .sidebar-header {
        display: none;
      }
      
      .sidebar-footer {
        display: none;
      }
      
      .main-content {
        padding: 20px;
      }
      
      .welcome-section {
        padding: 30px 20px;
      }
      
      .welcome-section h1 {
        font-size: 2rem;
      }
      
      .welcome-stats {
        flex-wrap: wrap;
      }
      
      .quick-actions {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 576px) {
      .welcome-section h1 {
        font-size: 1.8rem;
      }
      
      .welcome-stats {
        flex-direction: column;
      }
      
      .stat-item {
        width: 100%;
      }
    }

    /* Animações */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
      animation: fadeIn 0.6s ease forwards;
    }

    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }

    /* Tooltip para ícones na sidebar (mobile) */
    .sidebar-menu li:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      left: 100%;
      top: 50%;
      transform: translateY(-50%);
      background: var(--dark);
      color: white;
      padding: 6px 12px;
      border-radius: 4px;
      font-size: 0.85rem;
      white-space: nowrap;
      z-index: 1000;
      margin-left: 10px;
      pointer-events: none;
    }
  </style>
</head>
<body>

<?php
// Se não estiver autenticado, mostrar mensagem de erro
if (!isset($_SESSION["cpf"])) {
?>
  <div class="auth-error">
    <h2>Acesso Restrito</h2>
    <p>Para acessar o dashboard da UNIF, você precisa estar autenticado no sistema.</p>
    <a href="login.php" class="auth-button">
      <i class="fas fa-sign-in-alt"></i> Fazer Login
    </a>
  </div>
<?php
  exit();
}
?>

<div class="container">
  <!-- Sidebar Moderna -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h2><i class="fas fa-flag"></i> <span>UNIF</span></h2>
    </div>
    
    <ul class="sidebar-menu">
      <li class="active" data-tooltip="Início">
        <a href="inicio.php"><i class="fas fa-home"></i> <span>Início</span></a>
      </li>
      <li data-tooltip="Edições">
        <a href="eventos.php"><i class="fas fa-calendar-alt"></i> <span>Edições</span></a>
      </li>
      <li data-tooltip="Perfil">
        <a href="perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a>
      </li>
      <li data-tooltip="Mensagens">
        <a href="mensagens.php"><i class="fas fa-envelope"></i> <span>Mensagens</span></a>
      </li>
      <li data-tooltip="Ajuda">
        <a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a>
      </li>
    </ul>
    
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar"><?php echo strtoupper(substr($primeiro_nome, 0, 2)); ?></div>
        <div class="user-details">
          <h4><?php echo htmlspecialchars($primeiro_nome); ?></h4>
          <p><?php echo $cpf_formatado; ?></p>
          <div class="user-badges">
            <?php if ($is_admin): ?>
              <span class="badge badge-admin">ADMIN</span>
            <?php endif; ?>
            <?php if ($is_professor): ?>
              <span class="badge badge-professor">PROFESSOR</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </nav>
  
  <!-- Conteúdo Principal -->
  <main class="main-content">
    <!-- Seção de Boas-vindas -->
    <section class="welcome-section fade-in">
      <h1>Olá, <?php echo htmlspecialchars($primeiro_nome); ?>!</h1>
      <p>Bem-vindo ao sistema da UNIF - Um dos melhores eventos de simulação das Nações Unidas para estudantes. Prepare-se para uma experiência única de diplomacia e debate.</p>
      
      <div class="welcome-stats">
        <div class="stat-item fade-in delay-1">
          <div class="stat-number"><?php echo htmlspecialchars($proxima_unif_ano); ?></div>
          <div class="stat-label">Próxima Edição</div>
        </div>
        <div class="stat-item fade-in delay-2">
          <div class="stat-number">7+</div>
          <div class="stat-label">Comitês</div>
        </div>
        <div class="stat-item fade-in delay-3">
          <div class="stat-number">300+</div>
          <div class="stat-label">Participantes</div>
        </div>
      </div>
    </section>

    <!-- Logo UNIF -->
    <img src="images/unif.png" alt="Logo UNIF" class="unif-logo fade-in delay-1">

    <!-- Cards de Ação Rápida -->
    <div class="quick-actions">
      <div class="action-card editions fade-in delay-2">
        <i class="fas fa-calendar-alt action-icon"></i>
        <h3>Edições da UNIF</h3>
        <p>Explore todas as edições do evento, desde as históricas até as próximas.</p>
        <a href="eventos.php" class="action-btn">
          Ver Edições <i class="fas fa-arrow-right"></i>
        </a>
      </div>
      
      <div class="action-card profile fade-in delay-3">
        <i class="fas fa-user action-icon"></i>
        <h3>Meu Perfil</h3>
        <p>Atualize suas informações pessoais, preferências e configurações.</p>
        <a href="perfil.php" class="action-btn">
          Ver Perfil <i class="fas fa-arrow-right"></i>
        </a>
      </div>
      
      <div class="action-card messages fade-in delay-4">
        <i class="fas fa-envelope action-icon"></i>
        <h3>Mensagens</h3>
        <p>Verifique suas notificações e mensagens importantes.</p>
        <a href="mensagens.php" class="action-btn">
          Ver Mensagens <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
  </main>
</div>

<script>
  // Inicializar tooltips
  document.addEventListener('DOMContentLoaded', function() {
    // Adicionar animação aos cards ao passar o mouse
    const cards = document.querySelectorAll('.action-card');
    cards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px)';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });
    
    // Atualizar hora atual
    function updateTime() {
      const now = new Date();
      const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      };
      const timeElement = document.getElementById('current-time');
      if (timeElement) {
        timeElement.textContent = now.toLocaleDateString('pt-BR', options);
      }
    }
    
    updateTime();
    setInterval(updateTime, 60000); // Atualizar a cada minuto
  });
</script>
</body>
</html>