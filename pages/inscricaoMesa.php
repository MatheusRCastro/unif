<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscrição - Mesa Diretora | UNIF XXXX</title>
  <link rel="stylesheet" href="styles/global.css" />
  <link rel="stylesheet" href="styles/inscriçãoMesa.css" />
  <link rel="stylesheet" href="https://cdnjs.oudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

  <?php
  if (isset($_SESSION["cpf"])) {
  ?>

  <div class="page-wrapper">
    <!-- Cabeçalho -->
    <div class="page-header">
      <div class="header-content">
        <img src="images/unif.png" alt="Logo UNIF" class="logo-small">
        <div class="header-text">
          <h1>Inscrição de Mesa Diretora</h1>
          <p>Preencha os dados da equipe diretora e as especificações do comitê</p>
        </div>
      </div>
    </div>

    <div class="form-wrapper">
      <form id="inscricaoForm" class="inscricao-form">
        
        <!-- Coluna 1: Dados da Mesa -->
        <div class="form-column">
          <!-- Seção 1: Dados da Mesa Diretora -->
          <div class="form-card">
            <div class="card-header">
              <i class="fas fa-user-tie"></i>
              <h2>Dados da Mesa Diretora</h2>
            </div>
            
            <div class="input-group">
              <label for="cpf1">
                <i class="fas fa-id-card"></i> CPF do Diretor 1
              </label>
              <input type="text" id="cpf1" name="cpf1" placeholder="Digite o CPF" required>
            </div>
            
            <div class="input-group">
              <label for="cpf2">
                <i class="fas fa-id-card"></i> CPF do Diretor 2
              </label>
              <input type="text" id="cpf2" name="cpf2" placeholder="Digite o CPF">
            </div>
            
            <div class="input-group">
              <label for="cpf3">
                <i class="fas fa-id-card"></i> CPF do Diretor 3
              </label>
              <input type="text" id="cpf3" name="cpf3" placeholder="Digite o CPF">
            </div>
            
            <div class="form-note">
              <i class="fas fa-info-circle"></i>
              <p>Todos os diretores devem estar previamente cadastrados no sistema.</p>
            </div>
          </div>

          <!-- Seção 2: Comitê em Dupla -->
          <div class="form-card">
            <div class="card-header">
              <i class="fas fa-handshake"></i>
              <h2>Formação do Comitê</h2>
            </div>
            
            <div class="radio-section">
              <label class="radio-label">O Comitê é em Dupla?</label>
              <div class="radio-buttons">
                <label class="radio-button">
                  <input type="radio" name="comiteDupla" value="sim" required>
                  <span class="radio-custom"></span>
                  <span class="radio-text">Sim</span>
                </label>
                <label class="radio-button">
                  <input type="radio" name="comiteDupla" value="nao" required>
                  <span class="radio-custom"></span>
                  <span class="radio-text">Não</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Coluna 2: Especificações do Comitê -->
        <div class="form-column">
          <!-- Seção 3: Especificações do Comitê -->
          <div class="form-card">
            <div class="card-header">
              <i class="fas fa-chalkboard-teacher"></i>
              <h2>Especificações do Comitê</h2>
            </div>
            
            <div class="input-group">
              <label for="tipoComite">
                <i class="fas fa-chess-board"></i> Tipo do Comitê
              </label>
              <select id="tipoComite" name="tipoComite" required>
                <option value="">Selecione o tipo</option>
                <option value="CSNU">CSNU</option>
                <option value="OEA">OEA</option>
                <option value="OMS">OMS</option>
                <option value="UNESCO">UNESCO</option>
                <option value="OTAN">OTAN</option>
                <option value="outro">Outro</option>
              </select>
            </div>
            
            <div class="input-group">
              <label for="nomeComite">
                <i class="fas fa-font"></i> Nome do Comitê
              </label>
              <input type="text" id="nomeComite" name="nomeComite" placeholder="Ex: CSNU - Crise na Ucrânia" required>
            </div>
            
            <div class="form-row">
              <div class="input-group half-width">
                <label for="numDelegados">
                  <i class="fas fa-user-friends"></i> Nº de Delegados
                </label>
                <input type="number" id="numDelegados" name="numDelegados" min="10" max="100" placeholder="10-100" required>
              </div>
              
              <div class="input-group half-width">
                <label for="dataHistorica">
                  <i class="fas fa-calendar-day"></i> Data Histórica
                </label>
                <input type="text" id="dataHistorica" name="dataHistorica" placeholder="Ex: 2024, 1991, etc.">
              </div>
            </div>
            
            <div class="input-group">
              <label for="descricaoComite">
                <i class="fas fa-align-left"></i> Descrição do Comitê
              </label>
              <textarea id="descricaoComite" name="descricaoComite" rows="4" 
                placeholder="Descreva o tema, contexto histórico, objetivos e outros detalhes relevantes..."></textarea>
              <div class="char-counter">
                <span id="charCount">0</span> / 500 caracteres
              </div>
            </div>
          </div>

          <!-- Seção 4: Motivação -->
          <div class="form-card">
            <div class="card-header">
              <i class="fas fa-question-circle"></i>
              <h2>Motivação</h2>
            </div>
            
            <div class="input-group">
              <label for="motivacao">
                <i class="fas fa-pen-fancy"></i> Por que você deseja fazer parte da equipe de Staff da UNIF?
              </label>
              <textarea id="motivacao" name="motivacao" rows="4" 
                placeholder="Descreva suas motivações, experiências relevantes e o que você espera contribuir..."></textarea>
              <div class="char-counter">
                <span id="motivacaoCount">0</span> / 300 caracteres
              </div>
            </div>
          </div>
        </div>

      </form>
    </div>

    <!-- Botões de Ação -->
    <div class="action-buttons">
      <button type="button" class="btn-back" onclick="window.history.back()">
        <i class="fas fa-arrow-left"></i> Voltar
      </button>
      <button type="submit" form="inscricaoForm" class="btn-submit">
        <i class="fas fa-paper-plane"></i> Submeter Inscrição
      </button>
    </div>
  </div>

  <?php
  } else {
  ?>
  <div class="auth-error-page">
    <div class="error-card">
      <i class="fas fa-exclamation-circle"></i>
      <h2>Acesso Restrito</h2>
      <p>Você precisa estar logado para acessar esta página.</p>
      <a href="login.html" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Fazer Login
      </a>
    </div>
  </div>
  <?php
  }
  ?>

  <script>
    // Contador de caracteres
    document.getElementById('descricaoComite').addEventListener('input', function() {
      document.getElementById('charCount').textContent = this.value.length;
    });
    
    document.getElementById('motivacao').addEventListener('input', function() {
      document.getElementById('motivacaoCount').textContent = this.value.length;
    });
    
    // Validação do formulário
    document.getElementById('inscricaoForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Validação básica
      const cpf1 = document.getElementById('cpf1').value;
      const tipoComite = document.getElementById('tipoComite').value;
      const nomeComite = document.getElementById('nomeComite').value;
      
      if (!cpf1 || !tipoComite || !nomeComite) {
        alert('Por favor, preencha os campos obrigatórios marcados com *');
        return;
      }
      
      // Simulação de envio
      alert('Inscrição enviada com sucesso! Aguarde o contato da organização.');
    });
  </script>

</body>

</html>