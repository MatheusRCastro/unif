<?php
// adm_analisar_delegacoes.php
session_start();
require_once 'php/conexao.php';

// Verificar se o usuário é admin
if (!isset($_SESSION["cpf"]) || !isset($_SESSION["adm"]) || $_SESSION["adm"] != 1) {
  header("Location: login.php");
  exit();
}

$mensagem = '';
$filtro_status = $_GET['status'] ?? 'todos';
$busca = $_GET['busca'] ?? '';

// Processar ações de aprovação/reprovação
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['acao']) && isset($_POST['id_delegacao'])) {
    $id_delegacao = $_POST['id_delegacao'];
    $acao = $_POST['acao'];

    // Buscar dados da delegação para email/notificação
    $sql_info = "SELECT d.*, u.email, u.nome as nome_professor 
                     FROM delegacao d 
                     JOIN usuario u ON d.cpf = u.cpf 
                     WHERE d.id_delegacao = ?";
    $stmt_info = $conn->prepare($sql_info);
    $stmt_info->bind_param("i", $id_delegacao);
    $stmt_info->execute();
    $result_info = $stmt_info->get_result();
    $info_delegacao = $result_info->fetch_assoc();

    // Atualizar status conforme ação
    if ($acao == 'aprovar') {
      $novo_status = 'aprovado';
      $mensagem_status = "aprovada";
      $sql = "UPDATE delegacao SET verificacao_delegacao = 'aprovado' WHERE id_delegacao = ?";
    } elseif ($acao == 'reprovar') {
      $novo_status = 'reprovado';
      $mensagem_status = "reprovada";
      $sql = "UPDATE delegacao SET verificacao_delegacao = 'reprovado' WHERE id_delegacao = ?";
    } elseif ($acao == 'pendente') {
      $novo_status = 'pendente';
      $mensagem_status = "retornada para pendente";
      $sql = "UPDATE delegacao SET verificacao_delegacao = 'pendente' WHERE id_delegacao = ?";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_delegacao);

    if ($stmt->execute()) {
      $mensagem = "Delegação <strong>{$info_delegacao['nome']}</strong> {$mensagem_status} com sucesso!";

      // Opcional: Registrar log da ação
      // ...dentro do if ($stmt->execute()) {...


      // Opcional: Enviar email para o professor
      // enviar_email_status($info_delegacao['email'], $info_delegacao['nome_professor'], $info_delegacao['nome'], $novo_status);

      // ...

      // Opcional: Enviar email para o professor
      // enviar_email_status($info_delegacao['email'], $info_delegacao['nome_professor'], $info_delegacao['nome'], $novo_status);

    } else {
      $mensagem = "Erro ao atualizar status: " . $conn->error;
    }
    $stmt->close();
  }
}

// Buscar delegações com filtros
$sql_where = "WHERE 1=1";
$params = [];
$types = "";

if ($filtro_status != 'todos') {
  $sql_where .= " AND d.verificacao_delegacao = ?";
  $params[] = $filtro_status;
  $types .= "s";
}

if (!empty($busca)) {
  $sql_where .= " AND (d.nome LIKE ? OR u.nome LIKE ? OR d.cpf LIKE ?)";
  $search_term = "%$busca%";
  $params[] = $search_term;
  $params[] = $search_term;
  $params[] = $search_term;
  $types .= "sss";
}

// Contar delegações por status para os filtros
$sql_counts = "SELECT 
    verificacao_delegacao,
    COUNT(*) as total,
    SUM(CASE WHEN verificacao_delegacao = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN verificacao_delegacao = 'aprovado' THEN 1 ELSE 0 END) as aprovados,
    SUM(CASE WHEN verificacao_delegacao = 'reprovado' THEN 1 ELSE 0 END) as reprovados
    FROM delegacao d
    INNER JOIN unif u ON d.id_unif = u.id_unif
    WHERE u.data_fim_unif >= CURDATE() OR u.data_fim_unif IS NULL";

$result_counts = $conn->query($sql_counts);
$totais = [
  'pendente' => 0,
  'aprovado' => 0,
  'reprovado' => 0,
  'total' => 0
];

if ($result_counts) {
  while ($row = $result_counts->fetch_assoc()) {
    if ($row['verificacao_delegacao']) {
      $totais[$row['verificacao_delegacao']] = $row['total'];
    }
  }
  $totais['total'] = array_sum($totais);
}

// Buscar delegações
$sql = "SELECT 
    d.id_delegacao,
    d.nome as nome_delegacao,
    d.cpf,
    d.verificacao_delegacao,
    u.nome as nome_professor,
    u.email,
    u.telefone,
    un.nome as nome_unif,
    un.data_inicio_unif,
    un.data_fim_unif,
    (SELECT COUNT(*) FROM delegado WHERE id_delegacao = d.id_delegacao) as total_delegados
    FROM delegacao d
    INNER JOIN usuario u ON d.cpf = u.cpf
    INNER JOIN unif un ON d.id_unif = un.id_unif
    $sql_where
    ORDER BY 
        CASE d.verificacao_delegacao 
            WHEN 'pendente' THEN 1
            WHEN 'aprovado' THEN 2
            WHEN 'reprovado' THEN 3
        END,
        d.id_delegacao DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Análise de Delegações - Painel Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="styles/global.css">
  <style>
    :root {
      --color-pendente: #f39c12;
      --color-aprovado: #27ae60;
      --color-reprovado: #e74c3c;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }

    .header {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 25px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .header h1 {
      margin: 0 0 10px 0;
      color: #2c3e50;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .header h1 i {
      color: #3498db;
    }

    .stats-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin: 25px 0;
    }

    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      border-top: 4px solid;
    }

    .stat-card.pendente {
      border-color: var(--color-pendente);
    }

    .stat-card.aprovado {
      border-color: var(--color-aprovado);
    }

    .stat-card.reprovado {
      border-color: var(--color-reprovado);
    }

    .stat-card.total {
      border-color: #3498db;
    }

    .stat-number {
      font-size: 2.5em;
      font-weight: bold;
      margin: 10px 0;
    }

    .stat-card.pendente .stat-number {
      color: var(--color-pendente);
    }

    .stat-card.aprovado .stat-number {
      color: var(--color-aprovado);
    }

    .stat-card.reprovado .stat-number {
      color: var(--color-reprovado);
    }

    .stat-card.total .stat-number {
      color: #3498db;
    }

    .filters {
      display: flex;
      gap: 15px;
      margin-bottom: 25px;
      flex-wrap: wrap;
    }

    .filter-group {
      flex: 1;
      min-width: 200px;
    }

    .filter-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #34495e;
    }

    select,
    input[type="text"] {
      width: 100%;
      padding: 10px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.3s;
    }

    select:focus,
    input[type="text"]:focus {
      border-color: #3498db;
      outline: none;
    }

    .filter-actions {
      display: flex;
      gap: 10px;
      align-items: flex-end;
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s;
    }

    .btn-primary {
      background: #3498db;
      color: white;
    }

    .btn-primary:hover {
      background: #2980b9;
    }

    .btn-secondary {
      background: #95a5a6;
      color: white;
    }

    .btn-secondary:hover {
      background: #7f8c8d;
    }

    .btn-success {
      background: var(--color-aprovado);
      color: white;
    }

    .btn-success:hover {
      background: #219653;
    }

    .btn-danger {
      background: var(--color-reprovado);
      color: white;
    }

    .btn-danger:hover {
      background: #c0392b;
    }

    .btn-warning {
      background: var(--color-pendente);
      color: white;
    }

    .btn-warning:hover {
      background: #d35400;
    }

    .alert {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .delegacoes-table {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: #f8f9fa;
    }

    th {
      padding: 15px;
      text-align: left;
      font-weight: 600;
      color: #2c3e50;
      border-bottom: 2px solid #e9ecef;
    }

    td {
      padding: 15px;
      border-bottom: 1px solid #e9ecef;
    }

    tbody tr:hover {
      background: #f8f9fa;
    }

    .status-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      display: inline-block;
    }

    .status-pendente {
      background: #fff3cd;
      color: #856404;
    }

    .status-aprovado {
      background: #d4edda;
      color: #155724;
    }

    .status-reprovado {
      background: #f8d7da;
      color: #721c24;
    }

    .actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .btn-icon {
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 12px;
    }

    .delegacao-details {
      background: #f8f9fa;
      border-left: 4px solid #3498db;
      padding: 15px;
      margin: 10px 0;
      border-radius: 8px;
      font-size: 13px;
    }

    .detail-row {
      display: flex;
      margin-bottom: 8px;
    }

    .detail-label {
      font-weight: 600;
      min-width: 150px;
      color: #495057;
    }

    .detail-value {
      color: #6c757d;
    }

    .empty-state {
      text-align: center;
      padding: 50px 20px;
      color: #6c757d;
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: 15px;
      color: #bdc3c7;
    }

    .pagination {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
      padding: 20px;
    }

    .page-link {
      padding: 8px 12px;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      text-decoration: none;
      color: #3498db;
    }

    .page-link.active {
      background: #3498db;
      color: white;
      border-color: #3498db;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
      background: white;
      margin: 10% auto;
      padding: 30px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
    }

    .modal-header {
      margin-bottom: 20px;
    }

    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px;
    }

    textarea {
      width: 100%;
      padding: 10px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      margin-top: 10px;
      min-height: 100px;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-clipboard-check"></i> Análise de Delegações</h1>
      <p>Gerencie e analise as delegações inscritas no sistema</p>
    </div>

    <?php if ($mensagem): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $mensagem; ?>
      </div>
    <?php endif; ?>

    <div class="stats-cards">
      <div class="stat-card total">
        <div class="stat-label">Total de Delegações</div>
        <div class="stat-number"><?php echo $totais['total']; ?></div>
        <div class="stat-desc">Todas as delegações ativas</div>
      </div>

      <div class="stat-card pendente">
        <div class="stat-label">Pendentes</div>
        <div class="stat-number"><?php echo $totais['pendente']; ?></div>
        <div class="stat-desc">Aguardando análise</div>
      </div>

      <div class="stat-card aprovado">
        <div class="stat-label">Aprovadas</div>
        <div class="stat-number"><?php echo $totais['aprovado']; ?></div>
        <div class="stat-desc">Delegações validadas</div>
      </div>

      <div class="stat-card reprovado">
        <div class="stat-label">Reprovadas</div>
        <div class="stat-number"><?php echo $totais['reprovado']; ?></div>
        <div class="stat-desc">Delegações não aprovadas</div>
      </div>
    </div>

    <form method="GET" class="filters">
      <div class="filter-group">
        <label for="status"><i class="fas fa-filter"></i> Status</label>
        <select name="status" id="status" onchange="this.form.submit()">
          <option value="todos" <?php echo $filtro_status == 'todos' ? 'selected' : ''; ?>>Todos os Status</option>
          <option value="pendente" <?php echo $filtro_status == 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
          <option value="aprovado" <?php echo $filtro_status == 'aprovado' ? 'selected' : ''; ?>>Aprovadas</option>
          <option value="reprovado" <?php echo $filtro_status == 'reprovado' ? 'selected' : ''; ?>>Reprovadas</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="busca"><i class="fas fa-search"></i> Buscar</label>
        <input type="text"
          name="busca"
          id="busca"
          placeholder="Nome da delegação, professor ou CPF..."
          value="<?php echo htmlspecialchars($busca); ?>">
      </div>

      <div class="filter-actions">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-search"></i> Filtrar
        </button>
        <button type="button" class="btn btn-secondary" onclick="window.location.href='adm_analisar_delegacoes.php'">
          <i class="fas fa-redo"></i> Limpar
        </button>
      </div>
    </form>

    <div class="delegacoes-table">
      <?php if ($result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Delegação</th>
              <th>Professor</th>
              <th>Status</th>
              <th>UNIF</th>
              <th>Delegados</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($delegacao = $result->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($delegacao['nome_delegacao']); ?></strong><br>
                  <small style="color: #6c757d;">ID: <?php echo $delegacao['id_delegacao']; ?></small>
                </td>
                <td>
                  <strong><?php echo htmlspecialchars($delegacao['nome_professor']); ?></strong><br>
                  <small><?php echo htmlspecialchars($delegacao['email']); ?></small><br>
                  <small><?php echo htmlspecialchars($delegacao['telefone']); ?></small>
                </td>
                <td>
                  <?php
                  $status_class = 'status-' . $delegacao['verificacao_delegacao'];
                  $status_text = ucfirst($delegacao['verificacao_delegacao']);
                  ?>
                  <span class="status-badge <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                  </span>
                </td>
                <td>
                  <?php echo htmlspecialchars($delegacao['nome_unif']); ?><br>
                  <small>
                    <?php echo date('d/m/Y', strtotime($delegacao['data_inicio_unif'])); ?>
                    a <?php echo date('d/m/Y', strtotime($delegacao['data_fim_unif'])); ?>
                  </small>
                </td>
                <td>
                  <span style="font-weight: bold; font-size: 18px;"><?php echo $delegacao['total_delegados']; ?></span>
                  delegados
                </td>
                <td>
                  <div class="actions">
                    <!-- Botões de ação -->
                    <?php if ($delegacao['verificacao_delegacao'] == 'pendente'): ?>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_delegacao" value="<?php echo $delegacao['id_delegacao']; ?>">
                        <input type="hidden" name="acao" value="aprovar">
                        <button type="submit" class="btn btn-success btn-icon"
                          onclick="return confirm('Tem certeza que deseja APROVAR esta delegação?')">
                          <i class="fas fa-check"></i> Aprovar
                        </button>
                      </form>

                      <button type="button" class="btn btn-danger btn-icon"
                        onclick="showReprovarModal(<?php echo $delegacao['id_delegacao']; ?>, '<?php echo addslashes($delegacao['nome_delegacao']); ?>')">
                        <i class="fas fa-times"></i> Reprovar
                      </button>

                      <button type="button" class="btn btn-secondary btn-icon"
                        onclick="toggleDetails(<?php echo $delegacao['id_delegacao']; ?>)">
                        <i class="fas fa-eye"></i> Detalhes
                      </button>

                    <?php elseif ($delegacao['verificacao_delegacao'] == 'aprovado'): ?>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_delegacao" value="<?php echo $delegacao['id_delegacao']; ?>">
                        <input type="hidden" name="acao" value="reprovar">
                        <button type="submit" class="btn btn-danger btn-icon"
                          onclick="return confirm('Tem certeza que deseja REPROVAR esta delegação?')">
                          <i class="fas fa-times"></i> Reprovar
                        </button>
                      </form>

                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_delegacao" value="<?php echo $delegacao['id_delegacao']; ?>">
                        <input type="hidden" name="acao" value="pendente">
                        <button type="submit" class="btn btn-warning btn-icon">
                          <i class="fas fa-history"></i> Reverter
                        </button>
                      </form>

                    <?php elseif ($delegacao['verificacao_delegacao'] == 'reprovado'): ?>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_delegacao" value="<?php echo $delegacao['id_delegacao']; ?>">
                        <input type="hidden" name="acao" value="aprovar">
                        <button type="submit" class="btn btn-success btn-icon">
                          <i class="fas fa-check"></i> Aprovar
                        </button>
                      </form>

                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_delegacao" value="<?php echo $delegacao['id_delegacao']; ?>">
                        <input type="hidden" name="acao" value="pendente">
                        <button type="submit" class="btn btn-warning btn-icon">
                          <i class="fas fa-history"></i> Reverter
                        </button>
                      </form>
                    <?php endif; ?>

                    <button type="button" class="btn btn-primary btn-icon"
                      onclick="window.location.href='adm_visualizar_delegados.php?id=<?php echo $delegacao['id_delegacao']; ?>'">
                      <i class="fas fa-users"></i> Delegados
                    </button>
                  </div>

                  <!-- Detalhes da delegação (expandível) -->
              <tr id="details-<?php echo $delegacao['id_delegacao']; ?>" style="display: none;">
                <td colspan="6">
                  <div class="delegacao-details">
                    <div class="detail-row">
                      <div class="detail-label">CPF do Responsável:</div>
                      <div class="detail-value"><?php echo htmlspecialchars($delegacao['cpf']); ?></div>
                    </div>
                    <div class="detail-row">
                      <div class="detail-label">Data de Criação:</div>
                      <div class="detail-value">
                        <?php echo date('d/m/Y H:i', strtotime($delegacao['data_criacao'] ?? date('Y-m-d H:i:s'))); ?>
                      </div>
                    </div>
                    <div class="detail-row">
                      <div class="detail-label">Contato:</div>
                      <div class="detail-value">
                        Email: <?php echo htmlspecialchars($delegacao['email']); ?><br>
                        Telefone: <?php echo htmlspecialchars($delegacao['telefone']); ?>
                      </div>
                    </div>
                    <div class="detail-row">
                      <div class="detail-label">Ações Rápidas:</div>
                      <div class="detail-value">
                        <a href="mailto:<?php echo htmlspecialchars($delegacao['email']); ?>"
                          class="btn btn-primary btn-icon">
                          <i class="fas fa-envelope"></i> Enviar Email
                        </a>
                        <button class="btn btn-secondary btn-icon"
                          onclick="copiarTexto('<?php echo addslashes($delegacao['email']); ?>')">
                          <i class="fas fa-copy"></i> Copiar Email
                        </button>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
              </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-inbox"></i>
          <h3>Nenhuma delegação encontrada</h3>
          <p><?php echo !empty($busca) ? 'Tente ajustar os filtros de busca.' : 'Não há delegações para análise no momento.'; ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal para reprovação com motivo -->
  <div id="reprovarModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-exclamation-triangle"></i> Reprovar Delegação</h3>
      </div>
      <form method="POST" id="reprovarForm">
        <input type="hidden" name="id_delegacao" id="modalIdDelegacao">
        <input type="hidden" name="acao" value="reprovar">

        <p>Você está prestes a reprovar a delegação: <strong id="modalNomeDelegacao"></strong></p>

        <div class="form-group">
          <label for="motivo_reprovacao">Motivo da reprovação (opcional):</label>
          <textarea name="motivo_reprovacao" id="motivo_reprovacao"
            placeholder="Informe o motivo da reprovação para o professor..."></textarea>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal()">
            <i class="fas fa-times"></i> Cancelar
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-check"></i> Confirmar Reprovação
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Função para mostrar/ocultar detalhes
    function toggleDetails(id) {
      const detailsRow = document.getElementById(`details-${id}`);
      if (detailsRow.style.display === 'table-row') {
        detailsRow.style.display = 'none';
      } else {
        detailsRow.style.display = 'table-row';
      }
    }

    // Funções para o modal de reprovação
    function showReprovarModal(id, nome) {
      document.getElementById('modalIdDelegacao').value = id;
      document.getElementById('modalNomeDelegacao').textContent = nome;
      document.getElementById('reprovarModal').style.display = 'block';
    }

    function closeModal() {
      document.getElementById('reprovarModal').style.display = 'none';
      document.getElementById('motivo_reprovacao').value = '';
    }

    // Fechar modal ao clicar fora
    window.onclick = function(event) {
      const modal = document.getElementById('reprovarModal');
      if (event.target == modal) {
        closeModal();
      }
    }

    // Copiar texto para área de transferência
    function copiarTexto(texto) {
      navigator.clipboard.writeText(texto).then(() => {
        alert('Email copiado para a área de transferência!');
      });
    }

    // Atualizar página a cada 60 segundos para ver novas delegações
    setTimeout(() => {
      if (!document.hidden) {
        window.location.reload();
      }
    }, 60000);

    // Exportar para Excel (opcional)
    function exportarExcel() {
      window.location.href = 'exportar_delegacoes.php?status=<?php echo $filtro_status; ?>&busca=<?php echo urlencode($busca); ?>';
    }
  </script>
</body>

</html>

<?php
if (isset($conn)) {
  $conn->close();
}
?>