<?php
  session_start();
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Formulário UNIF</title>
  <link rel="stylesheet" href="styles/global.css">
  <link rel="stylesheet" href="styles/delegacaoComite.css">
  <!-- Fonte moderna -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

  <?php
  //verifica se foi iniciada a seção do usuário
  if (isset($_SESSION["cpf"])) {
  ?>

  <!-- Botões do topo -->
  <div class="tabs">
    <button onclick="window.location.href='#'">Inscrever delegação</button>
    <button onclick="window.location.href='entraComite.php'">Inscrição individual</button>
  </div>

  <!-- Formulário -->
  <div class="form-container">
    <!-- Coluna esquerda -->
    <div class="form-section">
      <h3>Dados da delegação</h3>
      <input type="text" placeholder="Nome da escola">
    </div>

    <!-- Coluna direita -->
    <div class="form-section">
      <h3>Dados do aluno</h3>
      <input type="text" placeholder="CPF">
      <input type="text" placeholder="Comitê desejado">
      <input type="text" placeholder="Segunda opção de comitê">
      <input type="text" placeholder="Terceira opção de comitê">
      <input type="text" placeholder="Representação desejada">
      <input type="text" placeholder="Justificativa da escolha">
    </div>
  </div>

  <!-- Botão enviar -->
  <button class="submit-btn">SUBMETER INSCRIÇÃO</button>

  <!-- Logo -->
  <img src="images/unif.png"  alt="Logo UNIF" class="logo">


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
