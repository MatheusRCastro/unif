<?php
session_start();
require_once 'php/conexao.php'; // Conexão com o banco

// Verificar se o usuário está autenticado
if (!isset($_SESSION["cpf"])) {
    header("Location: login.php");
    exit();
}

// Buscar eventos do banco de dados
$eventos = [];
if ($conn && $conn->connect_error === null) {
    // Buscar todos os eventos (UNIFs) ordenados por ano
    $sql = "SELECT * FROM unif ORDER BY data_inicio_unif DESC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Determinar status do evento baseado nas datas
            $data_atual = date('Y-m-d');
            $data_inicio = $row['data_inicio_unif'];
            $data_fim = $row['data_fim_unif'];
            
            // Extrair ano do nome ou data
            $ano = $row['nome'];
            if (preg_match('/\d{4}/', $ano, $matches)) {
                $ano = $matches[0];
            } else {
                $ano = date('Y', strtotime($data_inicio));
            }
            
            // Determinar status
            if ($data_atual > $data_fim) {
                $status = 'concluido';
                $status_text = 'Concluído';
                $status_class = 'concluido';
            } elseif ($data_atual >= $data_inicio && $data_atual <= $data_fim) {
                $status = 'em_andamento';
                $status_text = 'Em Andamento';
                $status_class = 'andamento';
            } elseif ($data_atual < $data_inicio) {
                $status = 'futuro';
                $status_text = 'Próximo';
                $status_class = 'futuro';
            } else {
                $status = 'desconhecido';
                $status_text = 'Indefinido';
                $status_class = 'desconhecido';
            }
            
            // Contar comitês aprovados deste evento
            $sql_comites = "SELECT COUNT(*) as total FROM comite WHERE id_unif = ? AND status = 'aprovado'";
            $stmt_comites = $conn->prepare($sql_comites);
            $stmt_comites->bind_param("i", $row['id_unif']);
            $stmt_comites->execute();
            $result_comites = $stmt_comites->get_result();
            $comites_count = $result_comites->fetch_assoc()['total'] ?? 0;
            $stmt_comites->close();
            
            $eventos[] = [
                'id' => $row['id_unif'],
                'nome' => $row['nome'],
                'ano' => $ano,
                'data_inicio' => $data_inicio,
                'data_fim' => $data_fim,
                'status' => $status,
                'status_text' => $status_text,
                'status_class' => $status_class,
                'comites_count' => $comites_count,
                'data_inicio_formatada' => date('d/m/Y', strtotime($data_inicio)),
                'data_fim_formatada' => date('d/m/Y', strtotime($data_fim))
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>UNIF - Eventos</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
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

        /* Conteúdo Principal */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 2.8rem;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 10px;
        }

        .header p {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Estatísticas */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Grid de Eventos */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .event-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .event-header {
            padding: 20px 25px;
            color: white;
            position: relative;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .event-header.concluido {
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
        }

        .event-header.andamento {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
        }

        .event-header.futuro {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .event-header.desconhecido {
            background: linear-gradient(135deg, #6c757d, #495057);
        }

        .event-year {
            font-family: 'Montserrat', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            opacity: 0.9;
            line-height: 1;
            margin-bottom: 5px;
        }

        .event-name {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .event-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
        }

        .event-body {
            padding: 25px;
        }

        .event-dates {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .date-item {
            text-align: center;
        }

        .date-label {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .date-value {
            font-weight: 600;
            color: var(--dark);
        }

        .event-info {
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--gray);
        }

        .info-item i {
            color: var(--primary);
            width: 20px;
        }

        .event-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .event-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray);
            color: white;
            transform: translateY(-2px);
        }

        /* Logo UNIF */
        .unif-logo {
            display: block;
            max-width: 300px;
            margin: 40px auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            opacity: 0.9;
        }

        /* Mensagem sem eventos */
        .no-events {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin: 20px 0;
        }

        .no-events .icon {
            font-size: 4rem;
            color: var(--gray);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-events h3 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .no-events p {
            color: var(--gray);
            max-width: 500px;
            margin: 0 auto 25px;
        }

        /* Filtros */
        .filters {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .filter-btn:hover:not(.active) {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
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
            
            .header h1 {
                font-size: 2.2rem;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .stats-section {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: center;
            }
            
            .filter-btn {
                width: 200px;
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
    </style>
</head>
<body>

<?php
// Se não estiver autenticado, mostrar mensagem de erro
if (!isset($_SESSION["cpf"])) {
?>
  <div class="auth-error">
    <h2>Acesso Restrito</h2>
    <p>Para acessar os eventos da UNIF, você precisa estar autenticado no sistema.</p>
    <a href="login.php" class="auth-button">
      <i class="fas fa-sign-in-alt"></i> Fazer Login
    </a>
  </div>
<?php
  exit();
}
?>

<div class="container">
  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h2><i class="fas fa-calendar-alt"></i> <span>UNIF Eventos</span></h2>
    </div>
    
    <ul class="sidebar-menu">
      <li data-tooltip="Início">
        <a href="inicio.php"><i class="fas fa-home"></i> <span>Início</span></a>
      </li>
      <li class="active" data-tooltip="Eventos">
        <a href="eventos.php"><i class="fas fa-calendar-alt"></i> <span>Eventos</span></a>
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
  </nav>
  
  <!-- Conteúdo Principal -->
  <main class="main-content">
    <!-- Header -->
    <div class="header fade-in">
      <h1>📅 Eventos UNIF</h1>
      <p>Explore todas as edições da UNIF. Participe, acompanhe ou veja como foram os eventos anteriores.</p>
    </div>

    <!-- Estatísticas -->
    <div class="stats-section fade-in delay-1">
      <div class="stat-card">
        <div class="stat-number"><?php echo count($eventos); ?></div>
        <div class="stat-label">Eventos Cadastrados</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo count(array_filter($eventos, function($e) { return $e['status'] === 'em_andamento'; })); ?></div>
        <div class="stat-label">Em Andamento</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo count(array_filter($eventos, function($e) { return $e['status'] === 'concluido'; })); ?></div>
        <div class="stat-label">Concluídos</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo count(array_filter($eventos, function($e) { return $e['status'] === 'futuro'; })); ?></div>
        <div class="stat-label">Próximos</div>
      </div>
    </div>

    <?php if (empty($eventos)): ?>
      <!-- Mensagem quando não há eventos -->
      <div class="no-events fade-in">
        <div class="icon">📅</div>
        <h3>Nenhum evento cadastrado</h3>
        <p>Não há eventos da UNIF cadastrados no sistema no momento.</p>
        <?php if (isset($_SESSION["adm"]) && $_SESSION["adm"] == true): ?>
          <a href="cadastrar_unif.php" class="event-btn btn-primary">
            <i class="fas fa-plus"></i> Cadastrar Novo Evento
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- Grid de Eventos -->
      <div class="events-grid">
        <?php foreach($eventos as $index => $evento): ?>
          <div class="event-card fade-in delay-<?php echo ($index % 4) + 1; ?>">
            <div class="event-header <?php echo $evento['status_class']; ?>">
              <div class="event-year"><?php echo $evento['ano']; ?></div>
              <div class="event-name"><?php echo htmlspecialchars($evento['nome']); ?></div>
              <span class="event-status"><?php echo $evento['status_text']; ?></span>
            </div>
            
            <div class="event-body">
              <div class="event-dates">
                <div class="date-item">
                  <div class="date-label">Início</div>
                  <div class="date-value"><?php echo $evento['data_inicio_formatada']; ?></div>
                </div>
                <div class="date-item">
                  <div class="date-label">Término</div>
                  <div class="date-value"><?php echo $evento['data_fim_formatada']; ?></div>
                </div>
              </div>
              
              <div class="event-info">
                <div class="info-item">
                  <i class="fas fa-users"></i>
                  <span><?php echo $evento['comites_count']; ?> comitê(s) aprovado(s)</span>
                </div>
                <div class="info-item">
                  <i class="fas fa-calendar-check"></i>
                  <span>Período: <?php echo $evento['data_inicio_formatada']; ?> a <?php echo $evento['data_fim_formatada']; ?></span>
                </div>
              </div>
              
              <div class="event-actions">
                <?php if ($evento['status'] === 'concluido'): ?>
                  <a href="relatorio_evento.php?id=<?php echo $evento['id']; ?>" class="event-btn btn-secondary">
                    <i class="fas fa-chart-bar"></i> Relatório
                  </a>
                  <a href="galeria.php?id=<?php echo $evento['id']; ?>" class="event-btn btn-primary">
                    <i class="fas fa-images"></i> Galeria
                  </a>
                <?php elseif ($evento['status'] === 'em_andamento'): ?>
                  <a href="participacao.php?id_unif=<?php echo $evento['id']; ?>" class="event-btn btn-primary">
                    <i class="fas fa-play-circle"></i> Participar
                  </a>
                  <a href="acompanhar.php?id=<?php echo $evento['id']; ?>" class="event-btn btn-secondary">
                    <i class="fas fa-eye"></i> Acompanhar
                  </a>
                <?php elseif ($evento['status'] === 'futuro'): ?>
                  <a href="participacao.php?id_unif=<?php echo $evento['id']; ?>" class="event-btn btn-primary">
                    <i class="fas fa-user-plus"></i> Inscrever-se
                  </a>
                  <button class="event-btn btn-secondary" onclick="notificarEvento(<?php echo $evento['id']; ?>)">
                    <i class="fas fa-bell"></i> Lembrete
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Logo UNIF -->
    <img src="images/unif.png" alt="Logo UNIF" class="unif-logo fade-in delay-2">

  </main>
</div>

<script>
  // Função para definir lembrete de evento
  function notificarEvento(idEvento) {
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('Lembrete de Evento UNIF', {
        body: 'Você será notificado sobre o início deste evento!',
        icon: 'images/unif.png'
      });
      alert('Lembrete configurado para este evento!');
    } else if ('Notification' in window && Notification.permission !== 'denied') {
      Notification.requestPermission().then(function(permission) {
        if (permission === 'granted') {
          notificarEvento(idEvento);
        }
      });
    } else {
      alert('Lembrete configurado! Você será avisado sobre o início do evento.');
    }
  }

  // Inicializar animações
  document.addEventListener('DOMContentLoaded', function() {
    // Animar cards ao passar o mouse
    const cards = document.querySelectorAll('.event-card');
    cards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px)';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });
  });
</script>
</body>
</html>