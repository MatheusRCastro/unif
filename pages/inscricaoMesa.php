<?php
<<<<<<< HEAD
session_start();
=======
  session_start();
>>>>>>> 8e08e1d (verificação de sessions concluida)
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
<<<<<<< HEAD
  <script src="scripts/inscricaoMesa.js" defer></script>
=======

>>>>>>> 8e08e1d (verificação de sessions concluida)
  <title>Inscrever Mesa Diretora</title>
  <link rel="stylesheet" href="styles/inscriçãoMesa.css" />
  <link rel="stylesheet" href="styles/global.css" />
</head>

<body>

  <?php
  //verifica se foi iniciada a seção do usuário
  if (isset($_SESSION["cpf"])) {
  ?>

<<<<<<< HEAD
    <div id="caixa">
      <img src="images/unif.png" alt="imagem da unif" id="imagem" />
    </div>

    <div class="right-box">
      <div class="container">
        <h3>Dados dos diretores</h3>
        <p>DIRETOR 1</p>
        <input type="text" placeholder="CPF do diretor 1" />
        <p>DIRETOR 2</p>
        <input type="text" placeholder="CPF do diretor 2" />
        <p>DIRETOR 3</p>
        <input type="text" placeholder="CPF do diretor 3" />
      </div>

      <div class="container">
        <h3>Especificações do comitê</h3>
        <input type="text" placeholder="Tipo do comitê (ex.: CSNU)" />
        <input type="text" placeholder="Nome do comitê" />
        <input type="text" placeholder="Número de delegados" />
        <input type="text" placeholder="Data histórica do comitê" />
        <textarea name="descricao_comite" id="descricaoComite" rows="10"
          placeholder="Digite aqui a descrição do comitê..."></textarea>

        <p>O COMITE É EM DUPLA?</p>
        <div class="dupla-btns">
          <button>Sim</button>
          <button>Não</button>
        </div>

        <button class="submit-btn">Submeter</button>
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
=======
  <div id="caixa">
    <img src="images/unif.png" alt="imagem da unif" id="imagem" />
  </div>

  <div class="right-box">
    <div class="container">
      <h3>Dados dos diretores</h3>
      <p>DIRETOR 1</p>
      <input type="text" placeholder="CPF do diretor 1" />
      <p>DIRETOR 2</p>
      <input type="text" placeholder="CPF do diretor 2" />
      <p>DIRETOR 3</p>
      <input type="text" placeholder="CPF do diretor 3" />
    </div>

    <div class="container">
      <h3>Especificações do comitê</h3>
      <input type="text" placeholder="Tipo do comitê (ex.: CSNU)" />
      <input type="text" placeholder="Nome do comitê" />
      <input type="text" placeholder="Número de delegados" />
      <input type="text" placeholder="Data histórica do comitê" />
      <textarea name="descricao_comite" id="descricaoComite" rows="10"
        placeholder="Digite aqui a descrição do comitê..."></textarea>

      <p>O COMITE É EM DUPLA?</p>
      <div class="dupla-btns">
        <button>Sim</button>
        <button>Não</button>
      </div>

      <button class="submit-btn">Submeter</button>
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

>>>>>>> 8e08e1d (verificação de sessions concluida)
</body>

</html>