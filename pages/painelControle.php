<?php
session_start();
require_once 'php/conexao.php'; // Inclui o arquivo de conexão

// Processar o formulário de criação da UNIF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_unif'])) {
    if ($conn && $conn->connect_error === null) {
        // Coletar dados do formulário
        $data_inicio_unif = $_POST['data_inicio_unif'];
        $data_fim_unif = $_POST['data_fim_unif'];
        $data_inicio_inscricao_delegado = $_POST['data_inicio_inscricao_delegado'];
        $data_fim_inscricao_delegado = $_POST['data_fim_inscricao_delegado'];
        $data_inicio_inscricao_comite = $_POST['data_inicio_inscricao_comite'];
        $data_fim_inscricao_comite = $_POST['data_fim_inscricao_comite'];
        $data_inicio_inscricao_staff = $_POST['data_inicio_inscricao_staff'];
        $data_fim_inscricao_staff = $_POST['data_fim_inscricao_staff'];
        
        // Inserir a nova UNIF no banco
        $sql_inserir_unif = "INSERT INTO unif (
            data_inicio_unif, data_fim_unif,
            data_inicio_inscricao_delegado, data_fim_inscricao_delegado,
            data_inicio_inscricao_comite, data_fim_inscricao_comite,
            data_inicio_inscricao_staff, data_fim_inscricao_staff
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_inserir_unif);
        $stmt->bind_param(
            "ssssssss",
            $data_inicio_unif,
            $data_fim_unif,
            $data_inicio_inscricao_delegado,
            $data_fim_inscricao_delegado,
            $data_inicio_inscricao_comite,
            $data_fim_inscricao_comite,
            $data_inicio_inscricao_staff,
            $data_fim_inscricao_staff
        );
        
        if ($stmt->execute()) {
            $id_nova_unif = $conn->insert_id;
            
            // Inserir secretários se foram fornecidos
            $funcoes_secretarios = [
                'Geral' => $_POST['cpf_geral'] ?? '',
                'Academico' => $_POST['cpf_academico'] ?? '',
                'Relacoes Publicas' => $_POST['cpf_relacoes_publicas'] ?? '',
                'Marketing' => $_POST['cpf_marketing'] ?? '',
                'Financas' => $_POST['cpf_financas'] ?? '',
                'Logistica' => $_POST['cpf_logistica'] ?? '',
                'Administrativo' => $_POST['cpf_administrativo'] ?? ''
            ];
            
            $sql_inserir_secretario = "INSERT INTO secretario (cpf, funcao, id_unif) VALUES (?, ?, ?)";
            $stmt_secretario = $conn->prepare($sql_inserir_secretario);
            
            foreach ($funcoes_secretarios as $funcao => $cpf) {
                if (!empty($cpf)) {
                    $stmt_secretario->bind_param("ssi", $cpf, $funcao, $id_nova_unif);
                    $stmt_secretario->execute();
                }
            }
            
            $stmt_secretario->close();
            $mensagem_sucesso = "UNIF criada com sucesso!";
            
            // Recarregar a página para mostrar os dados atualizados
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $mensagem_erro = "Erro ao criar UNIF: " . $conn->error;
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel UNIF</title>
  <link rel="stylesheet" href="styles/global.css">
  <link rel="stylesheet" href="styles/painelControle.css">
</head>

<body>

  <?php
  if (isset($_SESSION["cpf"])) {
    if (isset($_SESSION["adm"]) && $_SESSION["adm"] == true) {
      
      // Buscar dados da UNIF mais recente
      $unif_atual = null;
      $estatisticas = array(
        'inscritos' => 0,
        'staffs' => 0,
        'mesas' => 0
      );
      $pode_criar_unif = false;
      $secretarios = array();
      
      if ($conn && $conn->connect_error === null) {
        // Buscar a UNIF mais recente
        $sql_unif = "SELECT * FROM unif ORDER BY data_fim_unif DESC LIMIT 1";
        $result_unif = $conn->query($sql_unif);
        
        if ($result_unif && $result_unif->num_rows > 0) {
          $unif_atual = $result_unif->fetch_assoc();
          
          // Verificar se a UNIF já terminou (pode criar nova)
          $data_fim = new DateTime($unif_atual['data_fim_unif']);
          $data_atual = new DateTime();
          $pode_criar_unif = ($data_fim < $data_atual);
          
          // Buscar estatísticas
          $id_unif = $unif_atual['id_unif'];
          
          // Número de delegados inscritos
          $sql_inscritos = "SELECT COUNT(*) as total FROM delegado d 
                           INNER JOIN delegacao del ON d.cpf = del.cpf 
                           WHERE del.id_unif = ?";
          $stmt = $conn->prepare($sql_inscritos);
          $stmt->bind_param("i", $id_unif);
          $stmt->execute();
          $result_inscritos = $stmt->get_result();
          if ($result_inscritos && $row = $result_inscritos->fetch_assoc()) {
            $estatisticas['inscritos'] = $row['total'];
          }
          
          // Número de staffs
          $sql_staffs = "SELECT COUNT(*) as total FROM staff WHERE id_unif = ? AND inscricao_aprovada = true";
          $stmt = $conn->prepare($sql_staffs);
          $stmt->bind_param("i", $id_unif);
          $stmt->execute();
          $result_staffs = $stmt->get_result();
          if ($result_staffs && $row = $result_staffs->fetch_assoc()) {
            $estatisticas['staffs'] = $row['total'];
          }
          
          // Número de comitês aprovados
          $sql_mesas = "SELECT COUNT(*) as total FROM comite WHERE id_unif = ? AND comite_aprovado = true";
          $stmt = $conn->prepare($sql_mesas);
          $stmt->bind_param("i", $id_unif);
          $stmt->execute();
          $result_mesas = $stmt->get_result();
          if ($result_mesas && $row = $result_mesas->fetch_assoc()) {
            $estatisticas['mesas'] = $row['total'];
          }
          
          // Buscar secretários da UNIF atual
          $sql_secretarios = "SELECT s.funcao, u.cpf, u.nome 
                             FROM secretario s 
                             INNER JOIN usuario u ON s.cpf = u.cpf 
                             WHERE s.id_unif = ?";
          $stmt = $conn->prepare($sql_secretarios);
          $stmt->bind_param("i", $id_unif);
          $stmt->execute();
          $result_secretarios = $stmt->get_result();
          
          if ($result_secretarios && $result_secretarios->num_rows > 0) {
            while($row = $result_secretarios->fetch_assoc()) {
              $secretarios[$row['funcao']] = $row['cpf'];
            }
          }
        } else {
          // Não existe UNIF no banco, pode criar a primeira
          $pode_criar_unif = true;
        }
      }
  ?>

      <?php if (isset($mensagem_sucesso)): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; margin: 10px; border-radius: 5px; text-align: center;">
          <?php echo $mensagem_sucesso; ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($mensagem_erro)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; margin: 10px; border-radius: 5px; text-align: center;">
          <?php echo $mensagem_erro; ?>
        </div>
      <?php endif; ?>

      <div class="container">
        <!-- Datas e Inscrições -->
        <div class="box">
          <h3>Datas e Inscrições <?php echo $unif_atual ? "(UNIF #" . $unif_atual['id_unif'] . ")" : ""; ?></h3>
          <form method="POST" id="formUnif">
            <div class="row">
              <span class="label">Data início UNIF</span>
              <input type="date" name="data_inicio_unif" 
                     value="<?php echo $unif_atual ? $unif_atual['data_inicio_unif'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
            <div class="row">
              <span class="label">Data fim UNIF</span>
              <input type="date" name="data_fim_unif" 
                     value="<?php echo $unif_atual ? $unif_atual['data_fim_unif'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
            <div class="row">
              <span class="label">Inscrição delegados - Início</span>
              <input type="date" name="data_inicio_inscricao_delegado" 
                     value="<?php echo $unif_atual ? $unif_atual['data_inicio_inscricao_delegado'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
            <div class="row">
              <span class="label">Inscrição delegados - Fim</span>
              <input type="date" name="data_fim_inscricao_delegado" 
                     value="<?php echo $unif_atual ? $unif_atual['data_fim_inscricao_delegado'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
            <div class="row">
              <span class="label">Inscrição comitês - Início</span>
              <input type="date" name="data_inicio_inscricao_comite" 
                     value="<?php echo $unif_atual ? $unif_atual['data_inicio_inscricao_comite'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
            <div class="row">
              <span class="label">Inscrição comitês - Fim</span>
              <input type="date" name="data_fim_inscricao_comite" 
                     value="<?php echo $unif_atual ? $unif_atual['data_fim_inscricao_comite'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
            <div class="row">
              <span class="label">Inscrição staff - Início</span>
              <input type="date" name="data_inicio_inscricao_staff" 
                     value="<?php echo $unif_atual ? $unif_atual['data_inicio_inscricao_staff'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
            <div class="row">
              <span class="label">Inscrição staff - Fim</span>
              <input type="date" name="data_fim_inscricao_staff" 
                     value="<?php echo $unif_atual ? $unif_atual['data_fim_inscricao_staff'] : ''; ?>" 
                     <?php echo $unif_atual ? 'readonly' : 'required'; ?>>
            </div>
          </form>
        </div>

        <!-- Números de inscritos -->
        <div class="box">
          <h3>Estatísticas <?php echo $unif_atual ? "(UNIF #" . $unif_atual['id_unif'] . ")" : ""; ?></h3>
          <div class="row">
            <span class="label">Número de inscritos</span>
            <input type="text" value="<?php echo $estatisticas['inscritos']; ?>" readonly>
          </div>
          <div class="row">
            <span class="label">Número de staffs inscritos</span>
            <input type="text" value="<?php echo $estatisticas['staffs']; ?>" readonly>
          </div>
          <div class="row">
            <span class="label">Número de comitês aprovados</span>
            <input type="text" value="<?php echo $estatisticas['mesas']; ?>" readonly>
          </div>
        </div>

        <!-- CPFs dos Secretários -->
        <div class="box">
          <h3>Secretários <?php echo $unif_atual ? "(UNIF #" . $unif_atual['id_unif'] . ")" : ""; ?></h3>
          <div class="row">
            <span class="label">CPF do sec. Geral</span>
            <input type="text" name="cpf_geral" 
                   value="<?php echo isset($secretarios['Geral']) ? $secretarios['Geral'] : ''; ?>" 
                   <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif">
          </div>
          <div class="row">
            <span class="label">CPF do sec. Acadêmico</span>
            <input type="text" name="cpf_academico" 
                   value="<?php echo isset($secretarios['Academico']) ? $secretarios['Academico'] : ''; ?>" 
                   <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif">
          </div>
          <div class="row">
            <span class="label">CPF do sec. de Relações Públicas</span>
            <input type="text" name="cpf_relacoes_publicas" 
                   value="<?php echo isset($secretarios['Relacoes Publicas']) ? $secretarios['Relacoes Publicas'] : ''; ?>" 
                   <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif">
          </div>
          <div class="row">
            <span class="label">CPF do sec. de Marketing</span>
            <input type="text" name="cpf_marketing" 
                   value="<?php echo isset($secretarios['Marketing']) ? $secretarios['Marketing'] : ''; ?>" 
                   <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif">
          </div>
          <div class="row">
            <span class="label">CPF do sec. de Finanças</span>
            <input type="text" name="cpf_financas" 
                   value="<?php echo isset($secretarios['Financas']) ? $secretarios['Financas'] : ''; ?>" 
                   <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif">
          </div>
          <div class="row">
            <span class="label">CPF do sec. de Logística</span>
            <input type="text" name="cpf_logistica" 
                   value="<?php echo isset($secretarios['Logistica']) ? $secretarios['Logistica'] : ''; ?>" 
                   <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif">
          </div>
          <div class="row">
            <span class="label">CPF do sec. Administrativo</span>
            <input type="text" name="cpf_administrativo" 
                   value="<?php echo isset($secretarios['Administrativo']) ? $secretarios['Administrativo'] : ''; ?>" 
                   <?php echo $unif_atual ? 'readonly' : ''; ?> form="formUnif">
          </div>
        </div>

        <!-- Botões -->
        <div class="buttons">
          <div class="col left">
            <?php if (!$unif_atual): ?>
              <button type="submit" name="criar_unif" value="1" class="btn" form="formUnif">
                Criar UNIF
              </button>
            <?php else: ?>
              <button class="btn <?php echo $pode_criar_unif ? '' : 'disabled'; ?>" 
                      <?php echo $pode_criar_unif ? '' : 'disabled'; ?>>
                <?php echo $pode_criar_unif ? 'Criar Nova UNIF' : 'Criar Nova UNIF'; ?>
              </button>
            <?php endif; ?>
            <button class="btn" onclick="window.location.href='analiseComites.php'">Avaliar Comitês</button>
          </div>
          <div class="col right">
            <button class="btn" onclick="window.location.href='avaliarPagamentos.php'">Avaliar pagamentos</button>
            <span class="arrow">↓</span>
            <button class="btn" onclick="window.location.href='avaliarDelegacoes.php'">Avaliar delegações</button>
          </div>
        </div>

        <?php if (!$pode_criar_unif && $unif_atual): ?>
          <div style="text-align: center; margin-top: 20px; color: #666;">
            <p>⚠️ Só será possível criar uma nova UNIF após o término da atual (<?php echo date('d/m/Y', strtotime($unif_atual['data_fim_unif'])); ?>)</p>
          </div>
        <?php endif; ?>

      </div>

    <?php
    } else {
      echo "Usuário não autorizado!";
    }
  } else {
    echo "Usuário não autenticado!";
    ?>

    <a href="login.php" class="erro-php">Se identifique aqui</a>

  <?php
  }
  
  // Fechar conexão se existir
  if ($conn && $conn->connect_error === null) {
      $conn->close();
  }
  ?>
</body>

</html>