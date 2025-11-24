<?php
session_start()
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Perfil - UNIF</title>
  <link rel="stylesheet" href="styles/perfil.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <script src="scripts/sidebar.js" defer></script>

</head>

<body>

  <?php
  //verifica se foi iniciada a seção do usuário
  if (isset($_SESSION["cpf"])) {
  ?>

    <div class="background">
      <div class="logo-box">
        <img src="images/unif.png" alt="Logo UNIF" class="logo">
      </div>

      <div class="profile-box">
        <h2>Editar Perfil</h2>
        <div class="info-section">

          <div class="form-section">
            <div class="input-group">
              <label>Nome:</label>
              <input type="text" value="<?php echo$_SESSION["nome"]; ?>">
            </div>

            <div class="input-group">
              <label>E-mail:</label>
              <input type="email" value="<?php echo$_SESSION["email"]; ?>">
            </div>

            <div class="input-group">
              <label>Telefone:</label>
              <input type="text" value="<?php echo$_SESSION["telefone"]; ?>">
            </div>

            <div class="input-group">
              <label>Instituição:</label>
              <input type="text" value="<?php echo$_SESSION["instituicao"]; ?>">
            </div>

            <div class="button-group">
              <button class="update-btn">Salvar</button>
              <button class="cancel-btn">Cancelar</button>
            </div>
          </div>
        </div>
      </div>
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