<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Mensagens - UNIF</title>
  <link rel="stylesheet" href="styles/global.css">
  <link rel="stylesheet" href="styles/mensagens.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>

<body>

  <?php
  //verifica se foi iniciada a seção do usuário
  if (isset($_SESSION["cpf"])) {
  ?>

  <div class="container">

    <!-- Sidebar -->
    <nav class="sidebar collapsed">
      <div class="sidebar-header">
        <h2>Menu</h2>
      </div>

      <ul class="sidebar-menu">
        <li data-tooltip="Início">
          <a href="inicio.php"><i class="fas fa-home"></i> <span>Início</span></a>
        </li>
        <li data-tooltip="Participação">
          <a href="participacao.php"><i class="fas fa-users"></i> <span>Participação</span></a>
        </li>
        <li data-tooltip="Perfil">
          <a href="perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a>
        </li>
        <li data-tooltip="Mensagens" class="active">
          <a href="mensagens.php"><i class="fas fa-envelope"></i> <span>Mensagens</span></a>
        </li>
        <li data-tooltip="Ajuda">
          <a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a>
        </li>
        <li data-tooltip="Configurações">
          <a href="#"><i class="fas fa-cog"></i> <span>Configurações</span></a>
        </li>
      </ul>
    </nav>

    <!-- Conteúdo Principal -->
    <main class="main-content">
      <div class="content-wrapper">
        
        <!-- Logo no topo -->
        <div class="logo-box">
          <img src="images/unif.png" alt="Logo UNIF" class="logo">
        </div>

        <!-- Caixa de mensagens -->
        <div class="messages-box">
          <h2>Mensagens e Comunicados</h2>
          <p class="intro">Aqui você encontra avisos e informações importantes sobre eventos e atividades da UNIF.</p>

          <div class="message-card">
            <div class="message-header">
              <h3><i class="fas fa-calendar-alt"></i> Evento de Tecnologia</h3>
              <span class="message-time">Hoje, 10:30</span>
            </div>
            <p>
              Participe do <strong>UNIF Tech Week 2025</strong> — uma semana cheia de palestras, workshops e desafios de
              programação!
            </p>
            <p><b>Data:</b> 20 a 24 de outubro</p>
            <div class="button-group">
              <button class="read-btn"><i class="fas fa-check"></i> Marcar como lida</button>
              <button class="delete-btn"><i class="fas fa-trash"></i> Excluir</button>
            </div>
          </div>

          <div class="message-card">
            <div class="message-header">
              <h3><i class="fas fa-graduation-cap"></i> Palestra com Egressos</h3>
              <span class="message-time">Ontem, 15:45</span>
            </div>
            <p>
              Ex-alunos do curso de Engenharia de Computação compartilham experiências do mercado de trabalho e projetos
              inovadores.
            </p>
            <p><b>Data:</b> 15 de outubro, às 19h - Auditório Principal</p>
            <div class="button-group">
              <button class="read-btn"><i class="fas fa-check"></i> Marcar como lida</button>
              <button class="delete-btn"><i class="fas fa-trash"></i> Excluir</button>
            </div>
          </div>

          <div class="message-card">
            <div class="message-header">
              <h3><i class="fas fa-exclamation-triangle"></i> Aviso Importante</h3>
              <span class="message-time">2 dias atrás</span>
            </div>
            <p>
              O sistema acadêmico estará temporariamente fora do ar para manutenção no dia <b>12 de outubro</b>, das 13h às
              17h.
            </p>
            <div class="button-group">
              <button class="read-btn"><i class="fas fa-check"></i> Marcar como lida</button>
              <button class="delete-btn"><i class="fas fa-trash"></i> Excluir</button>
            </div>
          </div>

          <div class="message-card">
            <div class="message-header">
              <h3><i class="fas fa-users"></i> Reunião de Comitê</h3>
              <span class="message-time">3 dias atrás</span>
            </div>
            <p>
              Reunião preparatória para o comitê de Direitos Humanos. Todos os delegados devem trazer suas pesquisas.
            </p>
            <p><b>Data:</b> 18 de outubro, às 14h - Sala 304</p>
            <div class="button-group">
              <button class="read-btn"><i class="fas fa-check"></i> Marcar como lida</button>
              <button class="delete-btn"><i class="fas fa-trash"></i> Excluir</button>
            </div>
          </div>

        </div>
      </div>
    </main>

  </div>

  <?php
  } else {
  ?>
    <div class="auth-error">
      <div class="error-container">
        <h2>Usuário não autenticado!</h2>
        <p>Para acessar esta página, é necessário fazer login.</p>
        <a href="login.html" class="auth-btn">Faça login aqui</a>
      </div>
    </div>
  <?php
  }
  ?>

</body>

</html>