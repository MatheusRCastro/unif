<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/participacao.css">
    <title>inscriçao</title>
</head>

<body class="body">

    <?php
  //verifica se foi iniciada a seção do usuário
  if (isset($_SESSION["cpf"])) {
  ?>

    <div class="usavel">
        <h1 class="text"> Como deseja participar da UNIF XXXX?</h1>

        <div> <button class="buttons" onclick="window.location.href='entraComite.html'">Delegado</button></div>
        <div> <button class="buttons" onclick="window.location.href='inscricaoMesa.html'">Mesa diretora</button></div>
        <div> <button class="buttons" onclick="window.location.href='inscriçaoStaff.html'">Staff</button></div>

        <img src="images/unif.png" alt="logo" id="imagem">
    </div>

    <div class="leitura">
        <h2>O que um delegado faz?</h2>
        <br>
        <p>
            <span></span>O delegado representa um país ou organização, defendendo sua posição <br>oficial nos debates e negociações. Ele
            deve pesquisar previamente sobre <br>o tema e sobre a política externa de sua nação, elaborar discursos, <br>propor
            resoluções e articular alianças diplomáticas.
        </p>
        <br>
        <h2>O que um diretor faz?</h2>
        <br>
        <p>
            <span></span>A mesa diretora é responsável por decidir o tema do comitê e conduzir <br>as sessões, garantindo que as regras
            de procedimento sejam cumpridas. <br>Ela organiza a ordem de fala, administra o tempo, mantém a disciplina e<br>
            orienta o fluxo dos trabalhos, garantindo que todos os delegados tenham <br>oportunidade de participar.
        </p>
        <br>
        <h2>O que um staff faz?</h2>
        <br>
        <p>
            <span></span>O staff atua nos bastidores e no apoio logístico. Suas funções incluem <br>distribuir documentos, controlar o
            acesso à sala, auxiliar na comunicação<br> entre delegados e mesa diretora, além de resolver problemas técnicos<br>
            e administrativos que possam surgir, permitindo que a simulaçãoocorra <br> de forma fluida e organizada.
        </p>
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