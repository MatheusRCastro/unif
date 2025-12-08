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
    <title>Escolha Sua Participação - UNIF XXXX</title>
</head>

<body class="body">

    <?php
  //verifica se foi iniciada a seção do usuário
  if (isset($_SESSION["cpf"])) {
  ?>

    <div class="container">
        <header class="header">
            <h1 class="main-title">Como você deseja participar da UNIF XXXX?</h1>
            <p class="subtitle">Selecione uma das funções abaixo para conhecer mais sobre cada papel</p>
        </header>

        <main class="main-content-wrapper">
            <div class="roles-container">
                <div class="role-card">
                    <button class="role-btn" onclick="window.location.href='entraComite.php'">
                        <div class="role-icon">👨‍💼</div>
                        <span class="role-title">Delegado</span>
                    </button>
                    <div class="role-description">
                        <p>O delegado representa um país ou organização, defendendo sua posição oficial nos debates e negociações. Ele deve pesquisar previamente sobre o tema e sobre a política externa de sua nação, elaborar discursos, propor resoluções e articular alianças diplomáticas.</p>
                    </div>
                </div>

                <div class="role-card">
                    <button class="role-btn" onclick="window.location.href='inscricaoMesa.php'">
                        <div class="role-icon">👨‍⚖️</div>
                        <span class="role-title">Mesa Diretora</span>
                    </button>
                    <div class="role-description">
                        <p>A mesa diretora é responsável por decidir o tema do comitê e conduzir as sessões, garantindo que as regras de procedimento sejam cumpridas. Organiza a ordem de fala, administra o tempo, mantém a disciplina e orienta o fluxo dos trabalhos.</p>
                    </div>
                </div>

                <div class="role-card">
                    <button class="role-btn" onclick="window.location.href='inscriçaoStaff.php'">
                        <div class="role-icon">👨‍💻</div>
                        <span class="role-title">Staff</span>
                    </button>
                    <div class="role-description">
                        <p>O staff atua nos bastidores e no apoio logístico. Suas funções incluem distribuir documentos, controlar o acesso à sala, auxiliar na comunicação entre delegados e mesa diretora, além de resolver problemas técnicos e administrativos.</p>
                    </div>
                </div>
            </div>

            <div class="logo-section">
                <img src="images/unif.png" alt="Logo UNIF" class="logo">
                <p class="logo-text">Simulação Diplomática<br>XXXX Edition</p>
            </div>
        </main>

        <div class="instructions">
            <p><strong>Dica:</strong> Passe o mouse sobre cada função para saber mais sobre suas responsabilidades</p>
        </div>
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