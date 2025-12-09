<?php
session_start();
require_once 'php/conexao.php';

// Verificar se o usuário está autenticado
if (!isset($_SESSION["cpf"])) {
    header("Location: login.html");
    exit();
}

$cpf_usuario = $_SESSION["cpf"];
$erro = "";
$mensagem = "";
$comites = array();
$delegacoes = array();
$periodo_inscricao_valido = false;

// Obter o UNIF mais recente
$sql_unif = "SELECT id_unif, data_inicio_unif, data_fim_unif,
                    data_inicio_inscricao_delegado, data_fim_inscricao_delegado
             FROM unif 
             ORDER BY data_inicio_unif DESC 
             LIMIT 1";
$result_unif = $conn->query($sql_unif);

if ($result_unif->num_rows == 0) {
    $erro = "Não há UNIF cadastrado no momento.";
} else {
    $unif = $result_unif->fetch_assoc();
    $id_unif = $unif['id_unif'];
    $data_inicio_inscricao = $unif['data_inicio_inscricao_delegado'];
    $data_fim_inscricao = $unif['data_fim_inscricao_delegado'];
    
    // Verificar se estamos dentro do período de inscrição de delegados
    $data_atual = date('Y-m-d');
    
    if ($data_atual < $data_inicio_inscricao) {
        $erro = "As inscrições para delegados começam em " . date('d/m/Y', strtotime($data_inicio_inscricao));
    } elseif ($data_atual > $data_fim_inscricao) {
        $erro = "As inscrições para delegados encerraram em " . date('d/m/Y', strtotime($data_fim_inscricao));
    } else {
        $periodo_inscricao_valido = true;
        
        // Verificar se o usuário já é staff APROVADO no UNIF atual
        $sql_verifica_staff = "SELECT * FROM staff WHERE cpf = ? AND id_unif = ? AND status_inscricao = 'aprovado'";
        $stmt_staff = $conn->prepare($sql_verifica_staff);
        $stmt_staff->bind_param("si", $cpf_usuario, $id_unif);
        $stmt_staff->execute();
        $result_staff = $stmt_staff->get_result();
        
        if ($result_staff->num_rows > 0) {
            $erro = "Você já é staff aprovado para este UNIF. Não é possível se inscrever como delegado.";
        }
        $stmt_staff->close();
        
        // Verificar se o usuário já é diretor APROVADO no UNIF atual
        if (empty($erro)) {
            $sql_verifica_diretor = "SELECT d.*, c.nome_comite 
                                    FROM diretor d 
                                    JOIN comite c ON d.id_comite = c.id_comite 
                                    WHERE d.cpf = ? AND c.id_unif = ? AND d.aprovado = 1";
            $stmt_diretor = $conn->prepare($sql_verifica_diretor);
            $stmt_diretor->bind_param("si", $cpf_usuario, $id_unif);
            $stmt_diretor->execute();
            $result_diretor = $stmt_diretor->get_result();
            
            if ($result_diretor->num_rows > 0) {
                $diretor_data = $result_diretor->fetch_assoc();
                $erro = "Você já é diretor aprovado do comitê '{$diretor_data['nome_comite']}' para este UNIF. Não é possível se inscrever como delegado.";
            }
            $stmt_diretor->close();
        }
        
        // Verificar se o usuário já é delegado no UNIF atual
        if (empty($erro)) {
            $sql_verifica_delegado = "SELECT * FROM delegacao WHERE cpf = ? AND id_unif = ?";
            $stmt_delegado = $conn->prepare($sql_verifica_delegado);
            $stmt_delegado->bind_param("si", $cpf_usuario, $id_unif);
            $stmt_delegado->execute();
            $result_delegado = $stmt_delegado->get_result();
            
            if ($result_delegado->num_rows > 0) {
                $erro = "Você já está inscrito como delegado para este UNIF.";
            }
            $stmt_delegado->close();
        }
        
        // Se passar em todas as verificações, buscar comitês e delegações
        if (empty($erro)) {
            // Buscar comitês APROVADOS da UNIF atual
            $sql_comites = "
            SELECT 
                c.id_comite,
                c.nome_comite,
                c.tipo_comite,
                c.representacao
            FROM comite c
            WHERE c.id_unif = ?
            AND c.comite_aprovado = true
            ORDER BY c.nome_comite";
            
            $stmt_comites = $conn->prepare($sql_comites);
            $stmt_comites->bind_param("i", $id_unif);
            $stmt_comites->execute();
            $result_comites = $stmt_comites->get_result();
            
            if ($result_comites && $result_comites->num_rows > 0) {
                while($row = $result_comites->fetch_assoc()) {
                    $comites[] = $row;
                }
            }
            $stmt_comites->close();
            
            // Buscar delegações VERIFICADAS da UNIF atual
            $sql_delegacoes = "
            SELECT DISTINCT d.id_delegacao, u.instituicao as nome_escola 
            FROM delegacao d
            INNER JOIN usuario u ON d.cpf = u.cpf
            WHERE d.id_unif = ?
            AND d.verificacao_delegacao = true
            ORDER BY u.instituicao";
            
            $stmt_delegacoes = $conn->prepare($sql_delegacoes);
            $stmt_delegacoes->bind_param("i", $id_unif);
            $stmt_delegacoes->execute();
            $result_delegacoes = $stmt_delegacoes->get_result();
            
            if ($result_delegacoes && $result_delegacoes->num_rows > 0) {
                while($row = $result_delegacoes->fetch_assoc()) {
                    $delegacoes[] = $row;
                }
            }
            $stmt_delegacoes->close();
            
            // Verificar se há comitês disponíveis
            if (empty($comites)) {
                $erro = "Não há comitês disponíveis para inscrição no momento.";
            }
        }
    }
}

// Fechar conexão se não houver erro e ainda estiver aberta
if (isset($conn) && $conn->connect_error === null && empty($erro)) {
    $conn->close();
}
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
  <style>
    .info-unif {
      background-color: #e8f4f8;
      border-left: 4px solid #007bff;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
    }
    
    .info-unif h3 {
      margin-top: 0;
      color: #0056b3;
    }
    
    .mensagem-erro {
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      color: #721c24;
      padding: 15px;
      border-radius: 5px;
      margin: 20px auto;
      max-width: 800px;
      text-align: center;
    }
    
    .mensagem-sucesso {
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
      color: #155724;
      padding: 15px;
      border-radius: 5px;
      margin: 20px auto;
      max-width: 800px;
      text-align: center;
    }
    
    .restricoes {
      background-color: #fff3cd;
      border: 1px solid #ffeaa7;
      color: #856404;
      padding: 15px;
      border-radius: 5px;
      margin: 20px auto;
      max-width: 800px;
      font-size: 14px;
    }
  </style>
</head>
<body>

  <?php if (isset($erro) && !empty($erro)): ?>
    <div class="mensagem-erro">
      <?php echo $erro; ?>
      <p style="margin-top: 10px;">
        <a href="inicio.php" style="color: #721c24; font-weight: bold;">← Voltar ao início</a>
      </p>
    </div>
  <?php elseif (!$periodo_inscricao_valido): ?>
    <div class="mensagem-erro">
      Período de inscrição inválido.
      <p style="margin-top: 10px;">
        <a href="inicio.php" style="color: #721c24; font-weight: bold;">← Voltar ao início</a>
      </p>
    </div>
  <?php else: ?>
  
  <!-- Informações do UNIF atual -->
  <?php if (isset($unif)): ?>
  <div class="info-unif">
    <h3>UNIF Atual</h3>
    <p><strong>Período do evento:</strong> <?php echo date('d/m/Y', strtotime($unif['data_inicio_unif'])); ?> 
       a <?php echo date('d/m/Y', strtotime($unif['data_fim_unif'])); ?></p>
    <p><strong>Inscrições delegados:</strong> <?php echo date('d/m/Y', strtotime($data_inicio_inscricao)); ?> 
       a <?php echo date('d/m/Y', strtotime($data_fim_inscricao)); ?></p>
  </div>
  <?php endif; ?>
  
  <div class="restricoes">
    <strong>⚠️ Restrições:</strong><br>
    1. Staffs aprovados não podem ser delegados<br>
    2. Diretores aprovados não podem ser delegados<br>
    3. Você só pode estar em uma delegação por UNIF<br>
    4. É obrigatório selecionar uma delegação
  </div>

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
      <input type="text" name="cpf" placeholder="CPF" value="<?php echo htmlspecialchars($_SESSION['cpf']); ?>" readonly style="width: 100%">
      
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
      
      <textarea name="justificativa" placeholder="Justificativa da escolha" style="resize: none; width: 100%; height: 10em;" required></textarea>
    </div>
  </form>

  <!-- Botão enviar -->
  <button type="submit" class="submit-btn" onclick="validarFormulario()">SUBMETER INSCRIÇÃO</button>

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

    // Função para validar o formulário antes do envio
    function validarFormulario() {
        const form = document.getElementById('formInscricao');
        const delegacaoSelect = document.getElementById('id_delegacao');
        const comiteSelect = document.getElementById('comite_desejado');
        const representacaoSelect = document.getElementById('representacao_desejada');
        const justificativa = document.querySelector('textarea[name="justificativa"]');
        
        let erro = '';
        
        // Verificar delegação
        if (!delegacaoSelect.value) {
            erro = 'Por favor, selecione uma delegação.';
        }
        
        // Verificar comitê desejado
        else if (!comiteSelect.value) {
            erro = 'Por favor, selecione um comitê desejado.';
        }
        
        // Verificar representação
        else if (!representacaoSelect.value || representacaoSelect.value === '') {
            erro = 'Por favor, selecione uma representação.';
        }
        
        // Verificar justificativa
        else if (!justificativa.value.trim()) {
            erro = 'Por favor, preencha a justificativa.';
        } else if (justificativa.value.trim().length < 30) {
            erro = 'A justificativa deve ter pelo menos 30 caracteres.';
        }
        
        if (erro) {
            alert(erro);
            return false;
        }
        
        // Confirmar envio
        if (confirm('Deseja enviar sua inscrição como delegado?')) {
            form.submit();
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

  <?php endif; // Fim da verificação de erro e período ?>
</body>
</html>