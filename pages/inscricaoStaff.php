<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscrição de Staff</title>
  <link rel="stylesheet" href="styles/global.css">
  <link rel="stylesheet" href="styles/inscricaoStaff.css">
</head>

<body>

  <?php
  //verifica se foi iniciada a seção do usuário
  if (isset($_SESSION["cpf"])) {
  ?>


  <div class="container">
    <!-- Logo -->
    <div class="logo">
      <img src="images/unif.png" alt="Logo UNIF">
    </div>

    <!-- Formulário -->
    <div class="formulario">
      <h2>Inscrição de staff</h2>
      <form action="#" method="post">
        <input type="text" placeholder="CPF" required>
        <textarea rows="10" placeholder="Quais são as razões pelas quais você deveria fazer parte da equipe de staffs da UNIF ?" required></textarea>
        <button type="submit">Submeter</button>
      </form>
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
