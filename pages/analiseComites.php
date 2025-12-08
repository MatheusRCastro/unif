<?php
session_start();
require_once 'php/conexao.php';

// Processar aprovação/reprovação de comitês
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao_comite']) && $conn && $conn->connect_error === null) {
        $id_comite = $_POST['id_comite'];
        $acao = $_POST['acao_comite'];
        
        if ($acao === 'aprovar') {
            $sql = "UPDATE comite SET comite_aprovado = true WHERE id_comite = ?";
            $mensagem = "Comitê aprovado com sucesso!";
        } elseif ($acao === 'reprovar') {
            $sql = "UPDATE comite SET comite_aprovado = false WHERE id_comite = ?";
            $mensagem = "Comitê reprovado com sucesso!";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_comite);
        
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = $mensagem;
            $_SESSION['tipo_mensagem'] = 'sucesso';
        } else {
            $_SESSION['mensagem'] = "Erro ao processar comitê: " . $conn->error;
            $_SESSION['tipo_mensagem'] = 'erro';
        }
        
        $stmt->close();
        
        // Redirecionar para evitar reenvio do formulário
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Avaliar Comitês - UNIF</title>
  <link rel="stylesheet" href="styles/global.css">
  <style>
    body {
      background: linear-gradient(-45deg, #000, #0f5132, #2ecc71, #000);
      background-size: 400% 400%;
      animation: gradientMove 12s ease infinite;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }

    @keyframes gradientMove {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 15px;
      padding: 30px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.3);
      backdrop-filter: blur(10px);
    }

    .header {
      text-align: center;
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 2px solid #e9ecef;
    }

    .header h1 {
      color: #2c3e50;
      margin-bottom: 10px;
      font-size: 2.5em;
    }

    .header p {
      color: #6c757d;
      font-size: 1.1em;
    }

    .comites-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .comite-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.1);
      border-left: 6px solid #ccc;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .comite-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    }

    .comite-card.aprovado {
      border-left-color: #2ecc71;
      background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
    }

    .comite-card.reprovado {
      border-left-color: #e74c3c;
      background: linear-gradient(135deg, #fff8f8 0%, #ffe8e8 100%);
    }

    .comite-card.pendente {
      border-left-color: #f39c12;
      background: linear-gradient(135deg, #fffbf0 0%, #fff0d8 100%);
    }

    .status {
      display: inline-block;
      padding: 6px 16px;
      border-radius: 25px;
      font-size: 12px;
      font-weight: bold;
      margin-bottom: 15px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status.aprovado {
      background: #2ecc71;
      color: white;
    }

    .status.reprovado {
      background: #e74c3c;
      color: white;
    }

    .status.pendente {
      background: #f39c12;
      color: white;
    }

    .comite-info {
      margin-bottom: 20px;
    }

    .comite-info h3 {
      margin: 0 0 15px 0;
      color: #2c3e50;
      font-size: 1.4em;
      border-bottom: 2px solid #f8f9fa;
      padding-bottom: 10px;
    }

    .comite-info p {
      margin: 8px 0;
      color: #555;
      line-height: 1.5;
    }

    .comite-info strong {
      color: #34495e;
    }

    .diretoria {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
      border-left: 4px solid #3498db;
    }

    .diretoria h4 {
      margin: 0 0 12px 0;
      color: #34495e;
      font-size: 1.1em;
    }

    .acoes {
      display: flex;
      gap: 12px;
      margin-top: 20px;
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: bold;
      transition: all 0.3s ease;
      font-size: 14px;
      flex: 1;
    }

    .btn-aprovar {
      background: #2ecc71;
      color: white;
    }

    .btn-aprovar:hover:not(:disabled) {
      background: #27ae60;
      transform: translateY(-2px);
    }

    .btn-reprovar {
      background: #e74c3c;
      color: white;
    }

    .btn-reprovar:hover:not(:disabled) {
      background: #c0392b;
      transform: translateY(-2px);
    }

    .btn:disabled {
      background: #bdc3c7;
      cursor: not-allowed;
      transform: none !important;
    }

    .mensagem {
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 25px;
      text-align: center;
      font-weight: bold;
      font-size: 16px;
    }

    .mensagem.sucesso {
      background: #d4edda;
      color: #155724;
      border: 2px solid #c3e6cb;
    }

    .mensagem.erro {
      background: #f8d7da;
      color: #721c24;
      border: 2px solid #f5c6cb;
    }

    .filtros {
      display: flex;
      gap: 15px;
      margin-bottom: 30px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .filtro-btn {
      padding: 12px 24px;
      border: 2px solid #3498db;
      background: white;
      color: #3498db;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: bold;
      font-size: 14px;
    }

    .filtro-btn.ativo {
      background: #3498db;
      color: white;
      transform: translateY(-2px);
    }

    .filtro-btn:hover {
      background: #3498db;
      color: white;
      transform: translateY(-2px);
    }

    .voltar-btn {
      display: inline-block;
      padding: 12px 30px;
      background: #2c3e50;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      margin-top: 20px;
      text-align: center;
      font-weight: bold;
      transition: all 0.3s ease;
    }

    .voltar-btn:hover {
      background: #34495e;
      transform: translateY(-2px);
    }

    .sem-comites {
      text-align: center;
      padding: 60px 40px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin: 20px 0;
    }

    .sem-comites h3 {
      color: #6c757d;
      font-size: 1.8em;
      margin-bottom: 15px;
    }

    .sem-comites p {
      color: #8a8a8a;
      font-size: 1.1em;
      line-height: 1.6;
    }

    .sem-comites .icon {
      font-size: 4em;
      margin-bottom: 20px;
      color: #bdc3c7;
    }

    .representacoes-info {
      background: #f0f7ff;
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
      border-left: 4px solid #3498db;
    }

    .representacoes-info h4 {
      margin: 0 0 12px 0;
      color: #34495e;
      font-size: 1.1em;
    }
  </style>
</head>
<body>

  <?php
  if (isset($_SESSION["cpf"])) {
    if (isset($_SESSION["adm"]) && $_SESSION["adm"] == true) {
      
      $comites = array();
      $representacoes_por_comite = array(); // Nova variável para armazenar representações
      $filtro = $_GET['filtro'] ?? 'todos';
      $unif_atual = null;
      $id_unif = null;
      
      if ($conn && $conn->connect_error === null) {
        // Buscar a UNIF atual
        $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
        $result_unif = $conn->query($sql_unif);
        
        if ($result_unif && $result_unif->num_rows > 0) {
          $unif_atual = $result_unif->fetch_assoc();
          $id_unif = $unif_atual['id_unif'];
          
          // Buscar comitês da UNIF atual (SEM a coluna representacao)
          $sql_comites = "
          SELECT 
              c.id_comite,
              c.nome_comite,
              c.tipo_comite,
              c.data_comite,
              c.num_delegados,
              c.descricao_comite,
              c.comite_aprovado,
              u1.nome as diretor1,
              u2.nome as diretor2,
              u3.nome as diretor3,
              u4.nome as diretor4,
              u1.cpf as cpf1,
              u2.cpf as cpf2,
              u3.cpf as cpf3,
              u4.cpf as cpf4
          FROM comite c
          LEFT JOIN usuario u1 ON c.cpf_d1 = u1.cpf
          LEFT JOIN usuario u2 ON c.cpf_d2 = u2.cpf
          LEFT JOIN usuario u3 ON c.cpf_d3 = u3.cpf
          LEFT JOIN usuario u4 ON c.cpf_d4 = u4.cpf
          WHERE c.id_unif = ?
          ORDER BY c.comite_aprovado DESC, c.nome_comite";
          
          $stmt = $conn->prepare($sql_comites);
          $stmt->bind_param("i", $id_unif);
          $stmt->execute();
          $result_comites = $stmt->get_result();
          
          if ($result_comites && $result_comites->num_rows > 0) {
            while($row = $result_comites->fetch_assoc()) {
              $comites[] = $row;
              
              // Buscar representações para este comitê (se a tabela existir)
              $sql_reps = "SELECT nome_representacao FROM representacao WHERE id_comite = ?";
              $stmt_reps = $conn->prepare($sql_reps);
              $stmt_reps->bind_param("i", $row['id_comite']);
              $stmt_reps->execute();
              $result_reps = $stmt_reps->get_result();
              
              $representacoes = array();
              if ($result_reps && $result_reps->num_rows > 0) {
                while($rep_row = $result_reps->fetch_assoc()) {
                  $representacoes[] = $rep_row['nome_representacao'];
                }
              }
              $stmt_reps->close();
              
              $representacoes_por_comite[$row['id_comite']] = $representacoes;
            }
          }
          $stmt->close();
        }
      }
  ?>

  <div class="container">
    <!-- Mensagens -->
    <?php if (isset($_SESSION['mensagem'])): ?>
      <div class="mensagem <?php echo $_SESSION['tipo_mensagem']; ?>">
        <?php 
          echo $_SESSION['mensagem'];
          unset($_SESSION['mensagem']);
          unset($_SESSION['tipo_mensagem']);
        ?>
      </div>
    <?php endif; ?>

    <div class="header">
      <h1>📋 Avaliar Comitês</h1>
      <p>UNIF Atual - <?php echo isset($unif_atual) ? "ID: " . $id_unif : "Nenhuma UNIF encontrada"; ?></p>
    </div>

    <?php if (empty($comites)): ?>
      <div class="sem-comites">
        <div class="icon">📭</div>
        <h3>Nenhum comitê inscrito na UNIF atual</h3>
        <p>Não há comitês para avaliar no momento. Os comitês aparecerão aqui quando forem inscritos.</p>
      </div>
    <?php else: ?>
      <!-- Filtros -->
      <div class="filtros">
        <button class="filtro-btn <?php echo $filtro === 'todos' ? 'ativo' : ''; ?>" 
                onclick="window.location.href='?filtro=todos'">📋 Todos</button>
        <button class="filtro-btn <?php echo $filtro === 'pendentes' ? 'ativo' : ''; ?>" 
                onclick="window.location.href='?filtro=pendentes'">⏳ Pendentes</button>
        <button class="filtro-btn <?php echo $filtro === 'aprovados' ? 'ativo' : ''; ?>" 
                onclick="window.location.href='?filtro=aprovados'">✅ Aprovados</button>
        <button class="filtro-btn <?php echo $filtro === 'reprovados' ? 'ativo' : ''; ?>" 
                onclick="window.location.href='?filtro=reprovados'">❌ Reprovados</button>
      </div>

      <!-- Grid de Comitês -->
      <div class="comites-grid">
        <?php foreach($comites as $comite): 
          // Determinar status e classe CSS
          if ($comite['comite_aprovado'] === null) {
            $status = 'pendente';
            $status_text = 'Pendente';
          } elseif ($comite['comite_aprovado'] == 1) {
            $status = 'aprovado';
            $status_text = 'Aprovado';
          } else {
            $status = 'reprovado';
            $status_text = 'Reprovado';
          }
          
          // Aplicar filtro
          if ($filtro === 'aprovados' && $status !== 'aprovado') continue;
          if ($filtro === 'reprovados' && $status !== 'reprovado') continue;
          if ($filtro === 'pendentes' && $status !== 'pendente') continue;
          
          // Obter representações para este comitê
          $representacoes = $representacoes_por_comite[$comite['id_comite']] ?? array();
        ?>
          <div class="comite-card <?php echo $status; ?>">
            <div class="status <?php echo $status; ?>"><?php echo $status_text; ?></div>
            
            <div class="comite-info">
              <h3><?php echo htmlspecialchars($comite['nome_comite']); ?></h3>
              <p><strong>🏷️ Tipo:</strong> <?php echo htmlspecialchars($comite['tipo_comite']); ?></p>
              <p><strong>📅 Data:</strong> <?php echo date('d/m/Y', strtotime($comite['data_comite'])); ?></p>
              <p><strong>👥 Número de delegados:</strong> <?php echo $comite['num_delegados']; ?></p>
              
              <?php if (!empty($representacoes)): ?>
                <div class="representacoes-info">
                  <h4>🌍 Representações disponíveis:</h4>
                  <p><?php echo htmlspecialchars(implode(', ', $representacoes)); ?></p>
                  <p><em>Total: <?php echo count($representacoes); ?> representação(ões)</em></p>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($comite['descricao_comite'])): ?>
                <p><strong>📝 Descrição:</strong> <?php echo htmlspecialchars($comite['descricao_comite']); ?></p>
              <?php endif; ?>
            </div>

            <div class="diretoria">
              <h4>👨‍💼 Diretoria</h4>
              <?php if (!empty($comite['diretor1'])): ?>
                <p><strong>Diretor 1:</strong> <?php echo htmlspecialchars($comite['diretor1']); ?> (<?php echo $comite['cpf1']; ?>)</p>
              <?php endif; ?>
              <?php if (!empty($comite['diretor2'])): ?>
                <p><strong>Diretor 2:</strong> <?php echo htmlspecialchars($comite['diretor2']); ?> (<?php echo $comite['cpf2']; ?>)</p>
              <?php endif; ?>
              <?php if (!empty($comite['diretor3'])): ?>
                <p><strong>Diretor 3:</strong> <?php echo htmlspecialchars($comite['diretor3']); ?> (<?php echo $comite['cpf3']; ?>)</p>
              <?php endif; ?>
              <?php if (!empty($comite['diretor4'])): ?>
                <p><strong>Diretor 4:</strong> <?php echo htmlspecialchars($comite['diretor4']); ?> (<?php echo $comite['cpf4']; ?>)</p>
              <?php endif; ?>
            </div>

            <div class="acoes">
              <form method="POST" style="display: inline; flex: 1;">
                <input type="hidden" name="id_comite" value="<?php echo $comite['id_comite']; ?>">
                <input type="hidden" name="acao_comite" value="aprovar">
                <button type="submit" class="btn btn-aprovar" 
                        <?php echo $status === 'aprovado' ? 'disabled' : ''; ?>>
                  ✅ Aprovar
                </button>
              </form>
              
              <form method="POST" style="display: inline; flex: 1;">
                <input type="hidden" name="id_comite" value="<?php echo $comite['id_comite']; ?>">
                <input type="hidden" name="acao_comite" value="reprovar">
                <button type="submit" class="btn btn-reprovar" 
                        <?php echo $status === 'reprovado' ? 'disabled' : ''; ?>>
                  ❌ Reprovar
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="text-align: center;">
      <a href="painelControle.php" class="voltar-btn">← Voltar ao Painel de Controle</a>
    </div>
  </div>

  <?php
    } else {
      echo "<div class='container' style='text-align: center; padding: 40px;'>
              <h3>Usuário não autorizado!</h3>
              <p>Você não tem permissão para acessar esta página.</p>
              <a href='painelControle.php' class='voltar-btn'>Voltar</a>
            </div>";
    }
  } else {
    echo "<div class='container' style='text-align: center; padding: 40px;'>
            <h3>Usuário não autenticado!</h3>
            <p>Você precisa fazer login para acessar esta página.</p>
            <a href='login.php' class='voltar-btn'>Fazer Login</a>
          </div>";
  }
  
  if ($conn && $conn->connect_error === null) {
      $conn->close();
  }
  ?>
</body>
</html>