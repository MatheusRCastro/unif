<?php
session_start();
require_once 'php/conexao.php'; // Inclui o arquivo de conexão

// Função para buscar representações de um comitê
function buscarRepresentacoes($conn, $id_comite)
{
  $representacoes = array();
  if ($id_comite) {
    $sql = "SELECT 
                    r.id_representacao,
                    r.nome_representacao
                FROM representacao r
                WHERE r.id_comite = ?
                AND r.cpf_delegado IS NULL
                ORDER BY r.nome_representacao";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_comite);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $representacoes[] = $row;
    }
    $stmt->close();
  }
  return $representacoes;
}

// PROCESSAR FORMULÁRIO DE INSCRIÇÃO
$mensagem = "";
$erro = "";
$dados_preenchidos = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Verificar se é para carregar representações ou enviar inscrição
  $carregar_rep = isset($_POST['carregar_representacoes']);

  if (!$carregar_rep) {
    // Coletar dados do formulário (delegação agora é opcional)
    $id_delegacao = isset($_POST['id_delegacao']) ? intval($_POST['id_delegacao']) : null; // NULL permitido
    $comite_desejado = isset($_POST['comite_desejado']) ? intval($_POST['comite_desejado']) : 0;
    $segunda_opcao_comite = isset($_POST['segunda_opcao_comite']) ? intval($_POST['segunda_opcao_comite']) : null;
    $terceira_opcao_comite = isset($_POST['terceira_opcao_comite']) ? intval($_POST['terceira_opcao_comite']) : null;
    $representacao_desejada = isset($_POST['representacao_desejada']) ? intval($_POST['representacao_desejada']) : 0;
    $justificativa = isset($_POST['justificativa']) ? trim($_POST['justificativa']) : '';

    // Salvar dados para preenchimento automático
    $dados_preenchidos = [
      'id_delegacao' => $id_delegacao,
      'comite_desejado' => $comite_desejado,
      'segunda_opcao_comite' => $segunda_opcao_comite,
      'terceira_opcao_comite' => $terceira_opcao_comite,
      'representacao_desejada' => $representacao_desejada,
      'justificativa' => $justificativa
    ];

    // Validações básicas (delegação NÃO é mais obrigatória)
    if ($comite_desejado <= 0) {
      $erro = "Selecione um comitê desejado.";
    } elseif ($representacao_desejada <= 0) {
      $erro = "Selecione uma representação.";
    } elseif (empty($justificativa)) {
      $erro = "A justificativa é obrigatória.";
    } elseif (strlen($justificativa) < 20) {
      $erro = "A justificativa deve ter pelo menos 20 caracteres.";
    } else {
      // Verificar se o usuário já está inscrito como delegado
      $sql_verifica = "SELECT * FROM delegado WHERE cpf = ?";
      $stmt_verifica = $conn->prepare($sql_verifica);
      $stmt_verifica->bind_param("s", $_SESSION['cpf']);
      $stmt_verifica->execute();
      $result_verifica = $stmt_verifica->get_result();

      if ($result_verifica->num_rows > 0) {
        $erro = "Você já está inscrito como delegado!";
        $stmt_verifica->close();
      } else {
        $stmt_verifica->close();

        // Verificar se a representação ainda está disponível
        $sql_verifica_rep = "SELECT * FROM representacao WHERE id_representacao = ? AND cpf_delegado IS NULL";
        $stmt_verifica_rep = $conn->prepare($sql_verifica_rep);
        $stmt_verifica_rep->bind_param("i", $representacao_desejada);
        $stmt_verifica_rep->execute();
        $result_verifica_rep = $stmt_verifica_rep->get_result();

        if ($result_verifica_rep->num_rows == 0) {
          $erro = "Esta representação não está mais disponível. Por favor, selecione outra.";
          $stmt_verifica_rep->close();
        } else {
          $stmt_verifica_rep->close();

          // Inserir na tabela delegado (delegação pode ser NULL)
          $sql_inserir = "INSERT INTO delegado (cpf, id_comite, representacao, comite_desejado, 
                                                          primeira_op_representacao, segunda_op_representacao, 
                                                          terceira_op_representacao, segunda_op_comite, terceira_op_comite) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

          $stmt_inserir = $conn->prepare($sql_inserir);

          // Para simplificar, usando valores fixos para as opções de representação
          $primeira_op_representacao = $representacao_desejada;
          $segunda_op_representacao = null;
          $terceira_op_representacao = null;

          $stmt_inserir->bind_param(
            "siiiiiiii",
            $_SESSION['cpf'],          // cpf
            $comite_desejado,          // id_comite
            $representacao_desejada,   // representacao
            $comite_desejado,          // comite_desejado
            $primeira_op_representacao, // primeira_op_representacao
            $segunda_op_representacao, // segunda_op_representacao
            $terceira_op_representacao, // terceira_op_representacao
            $segunda_opcao_comite,     // segunda_op_comite
            $terceira_opcao_comite     // terceira_op_comite
          );

          if ($stmt_inserir->execute()) {
            // Atualizar a representação com o CPF do delegado
            $sql_atualizar_rep = "UPDATE representacao SET cpf_delegado = ? WHERE id_representacao = ?";
            $stmt_atualizar_rep = $conn->prepare($sql_atualizar_rep);
            $stmt_atualizar_rep->bind_param("si", $_SESSION['cpf'], $representacao_desejada);

            if ($stmt_atualizar_rep->execute()) {
              $mensagem = "Inscrição realizada com sucesso!";
              $dados_preenchidos = []; // Limpar dados após sucesso
            } else {
              $erro = "Erro ao atualizar representação: " . $stmt_atualizar_rep->error;
            }
            $stmt_atualizar_rep->close();
          } else {
            $erro = "Erro ao realizar inscrição: " . $stmt_inserir->error;
          }
          $stmt_inserir->close();
        }
      }
    }
  }
}

//verifica se foi iniciada a seção do usuário
if (isset($_SESSION["cpf"])) {

  // Buscar comitês da UNIF mais recente
  $comites = array();
  $delegacoes = array();

  if ($conn && $conn->connect_error === null) {
    // Buscar comitês APROVADOS (status = 'aprovado')
    $sql_comites = "
        SELECT 
            c.id_comite,
            c.nome_comite,
            c.tipo_comite,
            c.status
        FROM comite c
        INNER JOIN unif uf ON c.id_unif = uf.id_unif
        WHERE uf.id_unif = (
            SELECT id_unif 
            FROM unif 
            ORDER BY data_inicio_unif DESC 
            LIMIT 1
        )
        AND c.status = 'aprovado'
        ORDER BY c.nome_comite";

    $result_comites = $conn->query($sql_comites);

    if ($result_comites && $result_comites->num_rows > 0) {
      while ($row = $result_comites->fetch_assoc()) {
        $comites[] = $row;
      }
    }

    // Buscar delegações da UNIF mais recente (apenas para quem quiser selecionar)
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
      while ($row = $result_delegacoes->fetch_assoc()) {
        $delegacoes[] = $row;
      }
    }

    // Verificar comitê selecionado (do POST ou dos dados preenchidos)
    $comite_selecionado = isset($_POST['comite_desejado']) ? $_POST['comite_desejado'] : (isset($dados_preenchidos['comite_desejado']) ? $dados_preenchidos['comite_desejado'] : '');

    $representacoes_comite = array();

    if ($comite_selecionado) {
      $representacoes_comite = buscarRepresentacoes($conn, $comite_selecionado);
    }
  }
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
    /* Estilos apenas para mensagens - não afeta layout original */
    .mensagem-container {
      max-width: 800px;
      margin: 20px auto;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
    }

    .mensagem-sucesso {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .mensagem-erro {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .opcional {
      color: #666;
      font-weight: normal;
    }
  </style>
</head>

<body>

  <?php if (isset($_SESSION["cpf"])) { ?>

    <!-- Mensagens de erro/sucesso -->
    <?php if ($erro): ?>
      <div class="mensagem-container mensagem-erro">
        <i class="fas fa-exclamation-circle"></i>
        <h3>Erro na Inscrição</h3>
        <p><?php echo $erro; ?></p>
        <a href="entraComite.php" style="color: #721c24; font-weight: bold; display: inline-block; margin-top: 10px;">
          <i class="fas fa-arrow-left"></i> Voltar
        </a>
      </div>
    <?php elseif ($mensagem): ?>
      <div class="mensagem-container mensagem-sucesso">
        <i class="fas fa-check-circle"></i>
        <h3>Inscrição Enviada!</h3>
        <p><?php echo $mensagem; ?></p>
        <a href="inicio.php" style="color: #155724; font-weight: bold; display: inline-block; margin-top: 10px;">
          <i class="fas fa-home"></i> Ir para início
        </a>
      </div>
    <?php else: ?>

      <!-- Botões do topo -->
      <div class="tabs">
        <button onclick="window.location.href='criarDelegacao.php'">Criar delegação</button>
        <button onclick="window.location.href='#'">Inscrição individual</button>
      </div>

      <!-- Formulário -->
      <form class="form-container" id="formInscricao" method="POST" action="">
        <!-- Coluna esquerda -->
        <div class="form-section">
          <h3>Dados da delegação <span class="opcional">(opcional)</span></h3>

          <?php if (!empty($delegacoes)): ?>
            <select name="id_delegacao" id="id_delegacao">
              <option value="">Nenhuma delegação (inscrição individual)</option>
              <?php foreach ($delegacoes as $delegacao): ?>
                <option value="<?php echo $delegacao['id_delegacao']; ?>"
                  <?php echo (isset($dados_preenchidos['id_delegacao']) && $dados_preenchidos['id_delegacao'] == $delegacao['id_delegacao']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($delegacao['nome_escola']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p style="color: #666; font-size: 12px; margin-top: 5px;">
              Você pode se inscrever individualmente ou selecionar uma delegação existente.
              <br>
              <a href="criarDelegacao.php" style="color: #2ecc71; text-decoration: none; font-weight: bold;">
                Clique aqui para criar uma nova delegação
              </a>
            </p>
          <?php else: ?>
            <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
              Você está se inscrevendo individualmente.
              <br>
              <a href="criarDelegacao.php" style="color: #2ecc71; text-decoration: none; font-weight: bold;">
                Clique aqui se quiser criar uma delegação
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
          <select name="comite_desejado" id="comite_desejado" required onchange="this.form.submit()" style="width: 100%">
            <option value="">Selecione o comitê desejado *</option>
            <?php foreach ($comites as $comite): ?>
              <option value="<?php echo $comite['id_comite']; ?>"
                <?php echo ($comite_selecionado == $comite['id_comite']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($comite['nome_comite'] . ' - ' . $comite['tipo_comite']); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select name="segunda_opcao_comite" id="segunda_opcao_comite" style="width: 100%">
            <option value="">Segunda opção de comitê (opcional)</option>
            <?php foreach ($comites as $comite): ?>
              <option value="<?php echo $comite['id_comite']; ?>"
                <?php echo (isset($dados_preenchidos['segunda_opcao_comite']) && $dados_preenchidos['segunda_opcao_comite'] == $comite['id_comite']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($comite['nome_comite'] . ' - ' . $comite['tipo_comite']); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select name="terceira_opcao_comite" id="terceira_opcao_comite" style="width: 100%">
            <option value="">Terceira opção de comitê (opcional)</option>
            <?php foreach ($comites as $comite): ?>
              <option value="<?php echo $comite['id_comite']; ?>"
                <?php echo (isset($dados_preenchidos['terceira_opcao_comite']) && $dados_preenchidos['terceira_opcao_comite'] == $comite['id_comite']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($comite['nome_comite'] . ' - ' . $comite['tipo_comite']); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- REPRESENTAÇÃO DESEJADA -->
          <select name="representacao_desejada" id="representacao_desejada" required style="width: 100%">
            <option value="">Selecione uma representação *</option>
            <?php if (!empty($representacoes_comite)): ?>
              <?php foreach ($representacoes_comite as $rep): ?>
                <option value="<?php echo $rep['id_representacao']; ?>"
                  <?php echo (isset($dados_preenchidos['representacao_desejada']) && $dados_preenchidos['representacao_desejada'] == $rep['id_representacao']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                </option>
              <?php endforeach; ?>
            <?php elseif ($comite_selecionado): ?>
              <option value="">Nenhuma representação disponível para este comitê</option>
            <?php endif; ?>
          </select>
          <?php if ($comite_selecionado && empty($representacoes_comite)): ?>
            <p style="color: #e74c3c; font-size: 12px; margin-top: 5px;">
              Não há representações disponíveis para este comitê no momento.
            </p>
          <?php endif; ?>

          <textarea name="justificativa" placeholder="Justificativa da escolha *" style="resize: none; width: 100%; height: 10em;"><?php echo isset($dados_preenchidos['justificativa']) ? htmlspecialchars($dados_preenchidos['justificativa']) : ''; ?></textarea>
          <p style="color: #666; font-size: 12px; margin-top: 5px;">* Campos obrigatórios</p>

          <!-- Campo hidden para indicar que é apenas para carregar representações -->
          <input type="hidden" name="carregar_representacoes" value="1">
        </div>
      </form>

      <!-- Botão enviar - MANTENDO A POSIÇÃO ORIGINAL -->
      <button type="button" class="submit-btn" onclick="enviarFormulario()">SUBMETER INSCRIÇÃO</button>

      <!-- Logo -->
      <img src="images/unif.png" alt="Logo UNIF" class="logo">

      <script>
        function enviarFormulario() {
          console.log('Função enviarFormulario() chamada');
          
          // Remove o campo hidden antes de enviar
          const hiddenField = document.querySelector('input[name="carregar_representacoes"]');
          if (hiddenField) {
            console.log('Removendo campo hidden');
            hiddenField.remove();
          }
          
          // Validações antes de enviar
          const comiteDesejado = document.getElementById('comite_desejado').value;
          const representacao = document.getElementById('representacao_desejada').value;
          const justificativa = document.querySelector('textarea[name="justificativa"]') ? document.querySelector('textarea[name="justificativa"]').value.trim() : '';
          
          console.log('Valores:', {
            comiteDesejado: comiteDesejado,
            representacao: representacao,
            justificativa: justificativa
          });
          
          // Validações (delegação NÃO é mais obrigatória)
          let erros = [];
          
          if (!comiteDesejado || comiteDesejado === "") {
            erros.push("Selecione um comitê desejado!");
          }
          
          if (!representacao || representacao === "" || representacao === "Selecione uma representação") {
            erros.push("Selecione uma representação!");
          }
          
          if (!justificativa || justificativa.length < 20) {
            erros.push("A justificativa deve ter pelo menos 20 caracteres!");
          }
          
          if (erros.length > 0) {
            alert(erros.join('\n'));
            return false;
          }
          
          if (confirm('Deseja enviar sua inscrição?')) {
            console.log('Enviando formulário...');
            document.getElementById('formInscricao').submit();
          }
        }
        
        // Adiciona evento para quando o comitê for alterado
        document.addEventListener('DOMContentLoaded', function() {
          const comiteSelect = document.getElementById('comite_desejado');
          if (comiteSelect) {
            comiteSelect.addEventListener('change', function() {
              console.log('Comitê alterado, enviando formulário para carregar representações');
              // Adiciona um campo hidden para indicar que é apenas para carregar representações
              const existingField = document.querySelector('input[name="carregar_representacoes"]');
              if (existingField) {
                existingField.value = '1';
              } else {
                const newField = document.createElement('input');
                newField.type = 'hidden';
                newField.name = 'carregar_representacoes';
                newField.value = '1';
                document.getElementById('formInscricao').appendChild(newField);
              }
              
              // Submete o formulário
              setTimeout(function() {
                document.getElementById('formInscricao').submit();
              }, 100);
            });
          }
          
          // Teste: adiciona listener ao botão para debug
          const submitBtn = document.querySelector('.submit-btn');
          if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
              console.log('Botão clicado!');
            });
          }
        });
      </script>

    <?php endif; // Fim da verificação de mensagem/erro 
    ?>

  <?php } else { ?>
    <div style='text-align: center; padding: 50px;'>
      Usuário não autenticado!
      <br><a href='login.html' style='color: #2ecc71; text-decoration: none; font-weight: bold;'>Se identifique aqui</a>
    </div>
  <?php } ?>

  <?php
  // Fechar conexão se existir
  if ($conn && $conn->connect_error === null) {
    $conn->close();
  }
  ?>
</body>

</html>