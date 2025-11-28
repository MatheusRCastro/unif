<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inicio</title>

  <!-- Estilos -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="styles/inicio.css" />
  <link rel="stylesheet" href="styles/global.css" />

  <!-- Scripts -->
  <script src="scripts/sidebar.js" defer></script>
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
        <li data-tooltip="Perfil">
          <a href="perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a>
        </li>
        <li data-tooltip="Mensagens">
          <a href="mensagens.php"><i class="fas fa-envelope"></i> <span>Mensagens</span></a>
        </li>
        <li data-tooltip="Configurações">
          <a href="#"><i class="fas fa-cog"></i> <span>Configurações</span></a>
        </li>
        <li data-tooltip="Ajuda">
          <a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a>
        </li>
      </ul>
    </nav>

    <!-- Conteúdo Principal -->
    <main class="main-content">
      <div class="wraper">
        <div class="cabecalho">
          <h2>Bem vindo a UNIF!</h2>
        </div>

        <img id="imagem" src="images/unif.png" alt="logo da unif" />

        <div class="contain">
          <a href="eventos.php" id="ajudaCor" style="font-weight: bold">EDIÇÕES</a>
        </div>
      </div>
    </main>

  </div>

  <?php
  } else {
      echo "Usuário não autenticado!";
  ?>
      <a href="login.html" class="erro-php">Se identifique aqui</a>
  <?php

  }

  ?>

  
</body>
</html>
