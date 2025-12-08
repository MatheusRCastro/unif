<?php
  session_start();
  require_once 'php/conexao.php'; // Inclui o arquivo de conexão
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
    
    // Buscar comitês da UNIF mais recente
    $comites = array();
    $delegacoes = array();
    
    if ($conn && $conn->connect_error === null) {
        // Buscar comitês
        $sql_comites = "
        SELECT 
            c.id_comite,
            c.nome_comite,
            c.tipo_comite,
            c.representacao
        FROM comite c
        INNER JOIN unif uf ON c.id_unif = uf.id_unif
        WHERE uf.id_unif = (
            SELECT id_unif 
            FROM unif 
            ORDER BY data_inicio_unif DESC 
            LIMIT 1
        )
        AND c.comite_aprovado = true
        ORDER BY c.nome_comite";
        
        $result_comites = $conn->query($sql_comites);
        
        if ($result_comites && $result_comites->num_rows > 0) {
            while($row = $result_comites->fetch_assoc()) {
                $comites[] = $row;
            }
        }
        
        // Buscar delegações da UNIF mais recente
        $sql_delegacoes = "
        SELECT DISTINCT d.id_delegacao, u.instituicao as nome_escola 
        FROM delegacao d
        INNER JOIN unif uf ON d.id_unif = uf.id_unif
        INNER JOIN usuario u ON d.cpf = u.cpf
        WHERE uf.id_unif = (
            SELECT id_unif 
            FROM unif 
            ORDER BY data_inicio_unif DESC 
            LIMIT 1
        )
        AND d.verificacao_delegacao = true
        ORDER BY u.instituicao";
        
        $result_delegacoes = $conn->query($sql_delegacoes);
        
        if ($result_delegacoes && $result_delegacoes->num_rows > 0) {
            while($row = $result_delegacoes->fetch_assoc()) {
                $delegacoes[] = $row;
            }
        }
    }
  ?>

  <!-- Botões do topo -->
  <div class="tabs">
    <button onclick="window.location.href='criarDelegacao.php'">Criar delegação</button>
    <button onclick="window.location.href='#'">Inscrição individual</button>
  </div>

  <!-- Formulário -->
  <form class="form-container" id="formInscricao" method="POST" action="processa_inscricao.php">
    <!-- Coluna esquerda -->
    <div class="form-section">
      <h3>Dados da delegação</h3>
      
      <?php if (!empty($delegacoes)): ?>
        <select name="id_delegacao" id="id_delegacao" required>
          <option value="">Selecione sua delegação</option>
          <?php foreach($delegacoes as $delegacao): ?>
            <option value="<?php echo $delegacao['id_delegacao']; ?>">
              <?php echo htmlspecialchars($delegacao['nome_escola']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p style="color: #666; font-size: 12px; margin-top: 5px;">
          Não encontrou sua delegação? 
          <a href="criarDelegacao.php" style="color: #2ecc71; text-decoration: none; font-weight: bold;">
            Clique aqui para criar uma nova
          </a>
        </p>
      <?php else: ?>
        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
          Nenhuma delegação encontrada. 
          <a href="criarDelegacao.php" style="color: #2ecc71; text-decoration: none; font-weight: bold;">
            Clique aqui para criar uma nova delegação
          </a>
        </p>
        <input type="hidden" name="id_delegacao" value="">
      <?php endif; ?>
    </div>

    <!-- Coluna direita -->
    <div class="form-section">
      <h3>Dados do aluno</h3>
      <input type="text" name="cpf" placeholder="CPF" value="<?php echo $_SESSION['cpf']; ?>" readonly style="width: 100%">
      
      <!-- COMITÊ DESEJADO -->
      <select name="comite_desejado" id="comite_desejado" required onchange="carregarRepresentacoes()" style="width: 100%">
        <option value="">Selecione o comitê desejado</option>
        <?php foreach($comites as $comite): ?>
          <option value="<?php echo $comite['id_comite']; ?>" data-representacao="<?php echo htmlspecialchars($comite['representacao']); ?>">
            <?php echo htmlspecialchars($comite['nome_comite'] . ' - ' . $comite['tipo_comite']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <select name="segunda_opcao_comite" id="segunda_opcao_comite" style="width: 100%">
        <option value="">Segunda opção de comitê (opcional)</option>
        <?php foreach($comites as $comite): ?>
          <option value="<?php echo $comite['id_comite']; ?>">
            <?php echo htmlspecialchars($comite['nome_comite'] . ' - ' . $comite['tipo_comite']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <select name="terceira_opcao_comite" id="terceira_opcao_comite" style="width: 100%">
        <option value="">Terceira opção de comitê (opcional)</option>
        <?php foreach($comites as $comite): ?>
          <option value="<?php echo $comite['id_comite']; ?>">
            <?php echo htmlspecialchars($comite['nome_comite'] . ' - ' . $comite['tipo_comite']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <!-- REPRESENTAÇÃO DESEJADA - AGORA É UM SELECT DINÂMICO -->
      <select name="representacao_desejada" id="representacao_desejada" required style="width: 100%">
        <option value="">Primeiro selecione um comitê</option>
      </select>
      
      <textarea name="justificativa" placeholder="Justificativa da escolha" style="resize: none; width: 100%; height: 10em;"></textarea>
    </div>
  </form>

  <!-- Botão enviar -->
  <button type="submit" class="submit-btn" onclick="document.getElementById('formInscricao').submit()">SUBMETER INSCRIÇÃO</button>

  <!-- Logo -->
  <img src="images/unif.png" alt="Logo UNIF" class="logo">

  <script>
    // Função para carregar as representações baseadas no comitê selecionado
    function carregarRepresentacoes() {
        const comiteSelect = document.getElementById('comite_desejado');
        const representacaoSelect = document.getElementById('representacao_desejada');
        const selectedOption = comiteSelect.options[comiteSelect.selectedIndex];
        
        // Limpa as opções atuais
        representacaoSelect.innerHTML = '';
        
        if (comiteSelect.value === '') {
            // Se nenhum comitê foi selecionado
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Primeiro selecione um comitê';
            representacaoSelect.appendChild(option);
        } else {
            // Obtém as representações do atributo data-representacao
            const representacoesString = selectedOption.getAttribute('data-representacao');
            
            if (representacoesString) {
                // Divide as representações (assumindo que estão separadas por vírgula)
                const representacoes = representacoesString.split(',');
                
                // Adiciona a opção padrão
                const optionPadrao = document.createElement('option');
                optionPadrao.value = '';
                optionPadrao.textContent = 'Selecione uma representação';
                representacaoSelect.appendChild(optionPadrao);
                
                // Adiciona cada representação como uma opção
                representacoes.forEach(function(representacao) {
                    const rep = representacao.trim();
                    if (rep) {
                        const option = document.createElement('option');
                        option.value = rep;
                        option.textContent = rep;
                        representacaoSelect.appendChild(option);
                    }
                });
            } else {
                // Caso não haja representações definidas
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Nenhuma representação disponível';
                representacaoSelect.appendChild(option);
            }
        }
    }

    // Adiciona evento para quando a página carregar
    document.addEventListener('DOMContentLoaded', function() {
        const comiteSelect = document.getElementById('comite_desejado');
        if (comiteSelect.value !== '') {
            carregarRepresentacoes();
        }
    });
  </script>

  <?php
    } else {
        echo "Usuário não autenticado!";
    ?>
        <a href="login.html" class="erro-php">Se identifique aqui</a>
    <?php
    }
    
    // Fechar conexão se existir
    if ($conn && $conn->connect_error === null) {
        $conn->close();
    }
  ?>
</body>
</html>