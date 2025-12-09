<?php
  session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição – Mesa Diretora</title>

    <!-- global do projeto -->
    <link rel="stylesheet" href="styles/global.css">

    <!-- CSS desta página -->
    <link rel="stylesheet" href="styles/inscriçãoMesa.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="body">

<?php if (isset($_SESSION["cpf"])) { ?>

<div class="container">

    <header class="header">
        <h1 class="main-title">Inscrição para Mesa Diretora</h1>
        <p class="subtitle">Preencha o formulário abaixo para se candidatar à mesa da UNIF XXXX</p>
    </header>

    <div class="form-area">

        <form action="processa_inscricaoMesa.php" method="POST" class="form-card">

            <!-- SEÇÃO: DADOS DOS DIRETORES -->
            <h2 class="section-title"><i class="fas fa-users"></i> Dados dos Diretores</h2>
            
            <div class="grid-2">
                <div>
                    <label>Diretor 1 - CPF <span class="req">*</span></label>
                    <input type="text" name="diretor1_cpf" placeholder="CPF do Diretor 1" required>
                </div>
                <div>
                    <label>Diretor 2 - CPF</label>
                    <input type="text" name="diretor2_cpf" placeholder="CPF do Diretor 2">
                </div>
            </div>
            
            <div class="grid-2">
                <div>
                    <label>Diretor 3 - CPF</label>
                    <input type="text" name="diretor3_cpf" placeholder="CPF do Diretor 3">
                </div>
                <div>
                    <!-- Espaço vazio para alinhamento -->
                </div>
            </div>

            <!-- SEÇÃO: ESPECIFICAÇÕES DO COMITÊ -->
            <h2 class="section-title"><i class="fas fa-clipboard-list"></i> Especificações do Comitê</h2>
            
            <div class="grid-2">
                <div>
                    <label>Tipo do Comitê <span class="req">*</span></label>
                    <input type="text" name="tipo_comite" placeholder="Ex: CSNU" required>
                </div>
                <div>
                    <label>Nome do Comitê <span class="req">*</span></label>
                    <input type="text" name="nome_comite" placeholder="Nome completo do comitê" required>
                </div>
            </div>
            
            <div class="grid-2">
                <div>
                    <label>Número de Delegados <span class="req">*</span></label>
                    <input type="number" name="num_delegados" min="1" required>
                </div>
                <div>
                    <label>Data Histórica do Comitê <span class="req">*</span></label>
                    <input type="text" name="data_historica" placeholder="Ex: 1945" required>
                </div>
            </div>
            
            <div>
                <label>Descrição do Comitê <span class="req">*</span></label>
                <textarea name="descricao_comite" maxlength="1000" required></textarea>
                <p class="char-counter">Máximo: 1000 caracteres</p>
            </div>

            <!-- BOTÕES DE AÇÃO -->
            <div class="actions">
                <button type="button" class="btn-back" onclick="window.location.href='participacao.php'">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>

                <button type="submit" class="btn-submit">
                    Enviar Inscrição <i class="fas fa-paper-plane"></i>
                </button>
            </div>

        </form>

        <aside class="side-logo">
            <img src="images/unif.png" class="logo" alt="Logo UNIF">
            <p class="logo-text">Simulação Diplomática<br>UNIF XXXX</p>
        </aside>

    </div>

</div>

<?php } else { ?>

<div class="auth-error">
    <div class="error-container">
        <h2>Usuário não autenticado!</h2>
        <p>Para acessar esta página, faça login primeiro.</p>
        <a href="login.html" class="auth-btn">Fazer login</a>
    </div>
</div>

<?php } ?>

</body>
</html>