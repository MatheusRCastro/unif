<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Ajuda - UNIF</title>

  <link rel="stylesheet" href="styles/global.css">
  <link rel="stylesheet" href="styles/ajuda.css">
  <script src="scripts/sidebar.js" defer></script>
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>

<body>

<?php if (isset($_SESSION["cpf"])) { ?>

  <div class="container-sidebar">

    <!-- Sidebar -->
    <nav class="sidebar">
      <div class="sidebar-header">
        <h2>Menu</h2>
      </div>
      <ul class="sidebar-menu">
        <li><a href="inicio.html"><i class="fas fa-home"></i> <span>Início</span></a></li>
        <li><a href="perfil.html"><i class="fas fa-user"></i> <span>Perfil</span></a></li>
        <li><a href="mensagens.html"><i class="fas fa-envelope"></i> <span>Mensagens</span></a></li>
        <li><a href="#"><i class="fas fa-cog"></i> <span>Configurações</span></a></li>
        <li class="active"><a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a></li>
      </ul>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
      <div class="container">
        <div class="help-card">
          <h2>Central de Ajuda</h2>
          <p class="intro">Descreva abaixo o problema...</p>

          <form class="help-form">
            <div class="input-group">
              <label for="assunto">Assunto:</label>
              <input type="text" id="assunto" name="assunto" required>
            </div>

            <div class="input-group">
              <label for="descricao">Descrição do problema:</label>
              <textarea id="descricao" name="descricao" rows="8" required></textarea>
            </div>

            <div class="button-group">
              <button type="submit" class="send-btn">Enviar Solicitação</button>
              <button type="reset" class="clear-btn">Limpar</button>
            </div>
          </form>
        </div>
      </div>
    </main>

  </div>

<?php } else { ?>

  Usuário não autenticado!
  <a href="login.html" class="erro-php">Se identifique aqui</a>

<?php } ?>

</body>
</html>
