<?php
session_start();
require_once 'php/conexao.php';

// Verificar se o usuário está logado e é professor aprovado OU admin
$usuario_autorizado = false;
$usuario_nome = '';
$usuario_eh_admin = false;
$mensagem_erro = '';
$mensagem_sucesso = '';
$ja_tem_delegacao = false;
$delegacao_atual = null;

if (isset($_SESSION["cpf"])) {
  // Buscar dados do usuário no banco
  $sql_usuario = "SELECT nome, professor, adm FROM usuario WHERE cpf = ?";
  $stmt_usuario = $conn->prepare($sql_usuario);
  $stmt_usuario->bind_param("s", $_SESSION["cpf"]);
  $stmt_usuario->execute();
  $result_usuario = $stmt_usuario->get_result();

  if ($result_usuario->num_rows > 0) {
    $usuario = $result_usuario->fetch_assoc();
    $usuario_nome = $usuario['nome'];
    $usuario_eh_admin = ($usuario['adm'] == 1);

    // Verificar se é professor aprovado OU admin
    if ($usuario['professor'] == 'aprovado' || $usuario_eh_admin) {
      $usuario_autorizado = true;

      // Verificar se já tem delegação no UNIF atual
      $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
      $result_unif = $conn->query($sql_unif);

      if ($result_unif && $result_unif->num_rows > 0) {
        $unif = $result_unif->fetch_assoc();
        $id_unif_atual = $unif['id_unif'];

        // Verificar se já tem delegação neste UNIF
        $sql_verifica_delegacao = "SELECT * FROM delegacao 
                                           WHERE cpf = ? AND id_unif = ?";
        $stmt_verifica = $conn->prepare($sql_verifica_delegacao);
        $stmt_verifica->bind_param("si", $_SESSION["cpf"], $id_unif_atual);
        $stmt_verifica->execute();
        $result_verifica = $stmt_verifica->get_result();

        if ($result_verifica->num_rows > 0) {
          $ja_tem_delegacao = true;
          $delegacao_atual = $result_verifica->fetch_assoc();
        }
        $stmt_verifica->close();
      }
    } else {
      $mensagem_erro = "Apenas professores aprovados ou administradores podem criar delegações. Seu status: " . ucfirst($usuario['professor']);
    }
  } else {
    $mensagem_erro = "Usuário não encontrado no sistema.";
  }
  $stmt_usuario->close();
} else {
  $mensagem_erro = "Usuário não autenticado.";
}

// Processar criação da delegação
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $usuario_autorizado && !$ja_tem_delegacao) {
  $nome_escola = trim($_POST['escola'] ?? '');

  if (empty($nome_escola)) {
    $mensagem_erro = "Por favor, informe o nome da escola.";
  } elseif (strlen($nome_escola) < 3) {
    $mensagem_erro = "O nome da escola deve ter pelo menos 3 caracteres.";
  } else {
    // Buscar o UNIF atual
    $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
    $result_unif = $conn->query($sql_unif);

    if ($result_unif && $result_unif->num_rows > 0) {
      $unif = $result_unif->fetch_assoc();
      $id_unif = $unif['id_unif'];

      // Inserir delegação COM NOME (usando 'pendente' como valor padrão do ENUM)
      $sql_inserir = "INSERT INTO delegacao (id_unif, nome, cpf, verificacao_delegacao) 
                           VALUES (?, ?, ?, 'pendente')";
      $stmt_inserir = $conn->prepare($sql_inserir);
      $stmt_inserir->bind_param("iss", $id_unif, $nome_escola, $_SESSION["cpf"]);

      if ($stmt_inserir->execute()) {
        $id_delegacao = $conn->insert_id;
        $mensagem_sucesso = "Delegação '$nome_escola' criada com sucesso! ID: $id_delegacao";

        // Atualizar flag e buscar dados da nova delegação
        $ja_tem_delegacao = true;

        $sql_nova_delegacao = "SELECT * FROM delegacao WHERE id_delegacao = ?";
        $stmt_nova = $conn->prepare($sql_nova_delegacao);
        $stmt_nova->bind_param("i", $id_delegacao);
        $stmt_nova->execute();
        $result_nova = $stmt_nova->get_result();
        $delegacao_atual = $result_nova->fetch_assoc();
        $stmt_nova->close();
      } else {
        // Verificar se já existe delegação (pode ter sido criada por outra sessão)
        if ($conn->errno == 1062) { // Duplicate entry
          $ja_tem_delegacao = true;
          $mensagem_erro = "Você já possui uma delegação cadastrada para este UNIF.";
        } else {
          $mensagem_erro = "Erro ao criar delegação: " . $conn->error;
        }
      }
      $stmt_inserir->close();
    } else {
      $mensagem_erro = "Não há UNIF ativo no momento.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscrição de Delegações</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="styles/global.css">
  <link rel="stylesheet" href="styles/criarDelegação.CSS">
  <style>
    .info-box {
      background: #e3f2fd;
      border-left: 4px solid #2196F3;
      padding: 15px;
      margin: 15px 0;
      border-radius: 5px;
      color: #1565c0;
      font-size: 14px;
    }

    .info-box i {
      margin-right: 8px;
    }

    .alert {
      padding: 12px 15px;
      border-radius: 5px;
      margin: 15px 0;
      font-size: 14px;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .alert-warning {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 8px;
      border: 1px solid #e9ecef;
    }

    .user-info i {
      color: #6c757d;
      font-size: 18px;
    }

    .user-info .nome {
      font-weight: 600;
      color: #495057;
    }

    .user-info .tipo {
      margin-left: auto;
      padding: 4px 10px;
      background: #007bff;
      color: white;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .user-info .tipo.admin {
      background: #dc3545;
    }

    .user-info .tipo.professor {
      background: #28a745;
    }

    .delegacao-atual {
      background: #fff8e1;
      border-left: 4px solid #f39c12;
      padding: 20px;
      margin: 20px 0;
      border-radius: 8px;
    }

    .delegacao-info {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-top: 15px;
    }

    .info-item {
      background: white;
      padding: 12px;
      border-radius: 6px;
      border: 1px solid #e9ecef;
    }

    .info-item .label {
      font-size: 12px;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .info-item .value {
      font-size: 16px;
      font-weight: 600;
      color: #495057;
      margin-top: 5px;
    }

    /* Estilos para os status do ENUM */
    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      display: inline-block;
    }

    .status-aprovado {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .status-pendente {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .status-reprovado {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>

<body>

  <?php if ($usuario_autorizado): ?>

    <div class="container-sidebar">
      <!-- Sidebar -->
      <nav class="sidebar">
        <div class="sidebar-header">
          <h2>Menu</h2>
          <div class="user-status">
            <span class="status-dot <?php echo $usuario_eh_admin ? 'admin' : 'professor'; ?>"></span>
            <?php echo $usuario_eh_admin ? 'Admin' : 'Professor'; ?>
          </div>
        </div>
        <ul class="sidebar-menu">
          <li>
            <a href="inicio.php"><i class="fas fa-home"></i> <span>Início</span></a>
          </li>
          <li>
            <a href="perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a>
          </li>
          <li class="active">
            <a href="professorAnalisaDelegados.php"><i class="fas fa-users"></i> <span>Minha Delegação</span></a>
          </li>
          <?php if ($usuario_eh_admin): ?>
            <li>
              <a href="painelControle.php"><i class="fas fa-cogs"></i> <span>Painel Admin</span></a>
            </li>
          <?php endif; ?>
          <li>
            <a href="php/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a>
          </li>
        </ul>
      </nav>

      <!-- Conteúdo Principal -->
      <main class="main-content">
        <div class="container-form">
          <h2><i class="fas fa-school"></i> Inscrição de Delegações</h2>

          <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div>
              <div class="nome"><?php echo htmlspecialchars($usuario_nome); ?></div>
              <div style="font-size: 12px; color: #6c757d;">
                CPF: <?php echo htmlspecialchars($_SESSION["cpf"]); ?>
              </div>
            </div>
            <span class="tipo <?php echo $usuario_eh_admin ? 'admin' : 'professor'; ?>">
              <?php echo $usuario_eh_admin ? 'Administrador' : 'Professor Aprovado'; ?>
            </span>
          </div>

          <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success">
              <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
            </div>
          <?php endif; ?>

          <?php if ($mensagem_erro): ?>
            <div class="alert alert-error">
              <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
            </div>
          <?php endif; ?>

          <?php if ($ja_tem_delegacao && $delegacao_atual): ?>
            <div class="delegacao-atual">
              <h3 style="color: #f39c12; margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i> Delegação Atual
              </h3>

              <div class="delegacao-info">
                <div class="info-item">
                  <div class="label">Nome da Delegação</div>
                  <div class="value"><?php echo htmlspecialchars($delegacao_atual['nome']); ?></div>
                </div>

                <div class="info-item">
                  <div class="label">ID da Delegação</div>
                  <div class="value">#<?php echo $delegacao_atual['id_delegacao']; ?></div>
                </div>

                <div class="info-item">
                  <div class="label">Status</div>
                  <div class="value">
                    <?php
                    $status = $delegacao_atual['verificacao_delegacao'];
                    $status_text = ucfirst($status);
                    $status_class = "status-" . $status;
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                      <?php echo $status_text; ?>
                    </span>
                  </div>
                </div>
              </div>

              <div class="info-box" style="margin-top: 15px;">
                <i class="fas fa-info-circle"></i>
                Você já possui uma delegação cadastrada para o UNIF atual.
                Cada professor pode ter apenas UMA delegação por edição do UNIF.
              </div>

              <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button class="btn enviar" onclick="window.location.href='professorAnalisaDelegados.php?id=<?php echo $delegacao_atual['id_delegacao']; ?>'">
                  <i class="fas fa-cog"></i> Gerenciar Delegação
                </button>
                <?php if ($delegacao_atual['verificacao_delegacao'] == 'aprovado'): ?>
                  <button class="btn cancelar" onclick="window.location.href='adicionarDelegados.php?id=<?php echo $delegacao_atual['id_delegacao']; ?>'">
                    <i class="fas fa-user-plus"></i> Adicionar Delegados
                  </button>
                <?php else: ?>
                  <button class="btn cancelar" disabled style="opacity: 0.6; cursor: not-allowed;">
                    <i class="fas fa-user-plus"></i> Adicionar Delegados
                    <small>(Aguardando aprovação)</small>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <div class="info-box">
              <i class="fas fa-info-circle"></i>
              <strong>Informações importantes:</strong>
              <ul style="margin: 8px 0 0 20px;">
                <li>Cada professor pode criar APENAS UMA delegação por UNIF</li>
                <li>Após criar a delegação, você poderá adicionar delegados a ela</li>
                <li>O nome da delegação será usado para identificação no sistema</li>
                <li>Você será o responsável por esta delegação</li>
                <li>A delegação será criada com status <strong>"pendente"</strong> e aguardará aprovação administrativa</li>
                <?php if ($usuario_eh_admin): ?>
                  <li><strong>Como administrador, você pode criar múltiplas delegações</strong></li>
                <?php endif; ?>
              </ul>
            </div>

            <form method="POST">
              <div class="field">
                <label for="cpf">CPF do Responsável</label>
                <input type="text" id="cpf" name="cpf"
                  value="<?php echo htmlspecialchars($_SESSION["cpf"]); ?>"
                  readonly style="background-color: #f5f5f5;">
                <small>Este CPF será associado como responsável da delegação</small>
              </div>

              <div class="field">
                <label for="escola">Nome da Delegação *</label>
                <input type="text" id="escola" name="escola"
                  placeholder="Ex: Delegação do Colégio Exemplo" required
                  maxlength="100">
                <small>Informe o nome que identificará sua delegação (ex: nome da escola)</small>
              </div>

              <div class="buttons">
                <button type="submit" class="btn enviar">
                  <i class="fas fa-plus-circle"></i> Criar Delegação
                </button>
                <button type="button" class="btn cancelar" onclick="window.location.href='inicio.php'">
                  <i class="fas fa-times"></i> Cancelar
                </button>
              </div>
            </form>
          <?php endif; ?>

          <?php if ($usuario_autorizado): ?>
            <div style="margin-top: 30px; border-top: 1px solid #e0e0e0; padding-top: 20px;">
              <h3><i class="fas fa-history"></i> Histórico de Delegações</h3>
              <?php
              // Buscar delegações do usuário (todas, não apenas da UNIF atual)
              $sql_delegacoes = "SELECT d.id_delegacao, d.id_unif, d.nome, d.verificacao_delegacao, 
                                      u.data_inicio_unif, u.data_fim_unif
                              FROM delegacao d
                              INNER JOIN unif u ON d.id_unif = u.id_unif
                              WHERE d.cpf = ?
                              ORDER BY u.data_inicio_unif DESC";
              $stmt_delegacoes = $conn->prepare($sql_delegacoes);
              $stmt_delegacoes->bind_param("s", $_SESSION["cpf"]);
              $stmt_delegacoes->execute();
              $result_delegacoes = $stmt_delegacoes->get_result();

              if ($result_delegacoes->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                  <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                      <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">UNIF</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Nome da Delegação</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Período</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php while ($delegacao = $result_delegacoes->fetch_assoc()):
                        $eh_atual = ($ja_tem_delegacao && isset($delegacao_atual) && $delegacao['id_delegacao'] == $delegacao_atual['id_delegacao']);
                        $status = $delegacao['verificacao_delegacao'];
                        $status_class = "status-" . $status;
                      ?>
                        <tr style="<?php echo $eh_atual ? 'background: #fff8e1;' : ''; ?>">
                          <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                            UNIF #<?php echo $delegacao['id_unif']; ?>
                            <?php if ($eh_atual): ?>
                              <span style="font-size: 10px; color: #f39c12;">(Atual)</span>
                            <?php endif; ?>
                          </td>
                          <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                            <?php echo htmlspecialchars($delegacao['nome']); ?>
                          </td>
                          <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                            <span class="status-badge <?php echo $status_class; ?>">
                              <?php echo ucfirst($status); ?>
                            </span>
                          </td>
                          <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                            <?php echo date('d/m/Y', strtotime($delegacao['data_inicio_unif'])); ?>
                            a <?php echo date('d/m/Y', strtotime($delegacao['data_fim_unif'])); ?>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p style="color: #6c757d; text-align: center; padding: 20px;">
                  <i class="fas fa-inbox"></i> Nenhuma delegação encontrada.
                </p>
              <?php endif;
              $stmt_delegacoes->close(); ?>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>

  <?php else: ?>
    <div class="container-error">
      <div style="text-align: center; padding: 50px; max-width: 600px; margin: 0 auto;">
        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #e74c3c; margin-bottom: 20px;"></i>
        <h2 style="color: #e74c3c; margin-bottom: 20px;">Acesso Restrito</h2>
        <p style="font-size: 16px; color: #666; margin-bottom: 30px;">
          <?php echo $mensagem_erro ?: 'Apenas professores aprovados ou administradores podem criar delegações.'; ?>
        </p>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <a href="login.php" class="btn" style="background: #3498db; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px;">
            <i class="fas fa-sign-in-alt"></i> Fazer Login
          </a>
          <a href="inicio.php" class="btn" style="background: #95a5a6; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px;">
            <i class="fas fa-home"></i> Página Inicial
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php
  if (isset($conn)) {
    $conn->close();
  }
  ?>

  <script>
    // Formatar CPF automaticamente
    document.getElementById('cpf')?.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');

      if (value.length > 11) {
        value = value.substring(0, 11);
      }

      if (value.length > 9) {
        value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
      } else if (value.length > 6) {
        value = value.replace(/^(\d{3})(\d{3})(\d{1,3}).*/, '$1.$2.$3');
      } else if (value.length > 3) {
        value = value.replace(/^(\d{3})(\d{1,3}).*/, '$1.$2');
      }

      e.target.value = value;
    });

    // Validar formulário antes de enviar
    document.querySelector('form')?.addEventListener('submit', function(e) {
      const escolaInput = document.getElementById('escola');

      if (!escolaInput) return true;

      const nomeDelegacao = escolaInput.value.trim();

      if (nomeDelegacao.length < 3) {
        e.preventDefault();
        alert('O nome da delegação deve ter pelo menos 3 caracteres.');
        escolaInput.focus();
        return false;
      }

      if (nomeDelegacao.length > 100) {
        e.preventDefault();
        alert('O nome da delegação deve ter no máximo 100 caracteres.');
        escolaInput.focus();
        return false;
      }

      return true;
    });
  </script>
</body>

</html>