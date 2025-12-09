<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscrição de Delegações</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="styles/global.css">
  <link rel="stylesheet" href="styles/criarDelegação.CSS">
</head>

<body>

<?php
if (isset($_SESSION["cpf"])) {
  if (isset($_SESSION["professor"]) && $_SESSION["professor"] == "true") {
?>

  <div class="container-sidebar">
    <!-- Sidebar -->
    <nav class="sidebar">
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
        <li data-tooltip="Ajuda" class="active">
          <a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a>
        </li>
      </ul>
    </nav>

    <!-- Conteúdo Principal -->
    <main class="main-content">
      <div class="container-form">
        <h2>Inscrição de Delegações</h2>
        <form>
          <div class="field">
            <label for="cpf">CPF do Responsável</label>
            <input type="text" id="cpf" name="cpf" placeholder="Digite o CPF">
          </div>

          <div class="field">
            <label for="escola">Nome da Escola</label>
            <input type="text" id="escola" name="escola" placeholder="Digite o nome da escola">
          </div>

          <div class="buttons">
            <button type="submit" class="btn enviar">Criar</button>
          </div>
        </form>
      </div>
    </main>
  </div>

<?php
  } else {
    echo "<div class='container-error'><p>Usuário não autorizado!</p></div>";
  }
} else {
  echo "<div class='container-error'><p>Usuário não autenticado!</p>";
  echo "<a href='login.php' class='erro-php'>Se identifique aqui</a></div>";
}
?>

</body>
</html>