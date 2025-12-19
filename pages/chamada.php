<?php
session_start();
require_once 'php/conexao.php';

// Processar marcação de presença
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['marcar_presenca'])) {
        if ($conn && $conn->connect_error === null) {
            $cpf_delegado = $_POST['cpf_delegado'];
            $id_comite = $_POST['id_comite'];
            $tipo_presenca = $_POST['tipo_presenca'];
            $valor = $_POST['valor'];

            // Buscar ID da UNIF atual
            $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
            $result_unif = $conn->query($sql_unif);

            if ($result_unif && $result_unif->num_rows > 0) {
                $unif = $result_unif->fetch_assoc();
                $id_unif = $unif['id_unif'];

                // Verificar se já existe registro de presença
                $sql_check = "SELECT id_presenca FROM presenca_delegado 
                             WHERE cpf_delegado = ? AND id_unif = ? AND id_comite = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("sii", $cpf_delegado, $id_unif, $id_comite);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($result_check->num_rows > 0) {
                    // Atualizar presença existente
                    $row = $result_check->fetch_assoc();
                    $id_presenca = $row['id_presenca'];

                    $sql_update = "UPDATE presenca_delegado SET $tipo_presenca = ? 
                                  WHERE id_presenca = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("ii", $valor, $id_presenca);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    // Inserir nova presença
                    $sql_insert = "INSERT INTO presenca_delegado 
                                  (cpf_delegado, id_unif, id_comite, $tipo_presenca) 
                                  VALUES (?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("siii", $cpf_delegado, $id_unif, $id_comite, $valor);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }

                $stmt_check->close();
                $_SESSION['mensagem'] = "Presença atualizada com sucesso!";
                $_SESSION['tipo_mensagem'] = 'sucesso';
            }

            header("Location: " . $_SERVER['PHP_SELF'] . "?comite=" . $id_comite);
            exit();
        }
    }

    // Processar exportação para Excel
    if (isset($_POST['exportar_excel'])) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="chamada_comite_' . $_POST['id_comite'] . '_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Gerar conteúdo Excel aqui (será feito abaixo)
        // Por enquanto redireciona de volta
        header("Location: " . $_SERVER['PHP_SELF'] . "?comite=" . $_POST['id_comite']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Chamada - UNIF</title>
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
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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

        .comite-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 5px solid #3498db;
        }

        .comite-info h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .controles-superiores {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .selecionar-comite {
            flex: 1;
            min-width: 300px;
        }

        .selecionar-comite select {
            padding: 12px 20px;
            border: 2px solid #3498db;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            width: 100%;
            max-width: 400px;
        }

        .export-btn {
            padding: 12px 25px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .export-btn:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .lista-chamada {
            overflow-x: auto;
        }

        .tabela-chamada {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .tabela-chamada th {
            background: #34495e;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        .tabela-chamada td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }

        .tabela-chamada tr:hover {
            background: #f8f9fa;
        }

        .delegado-info {
            text-align: left;
            padding-left: 20px !important;
        }

        .delegado-nome {
            font-weight: bold;
            color: #2c3e50;
        }

        .delegado-cpf {
            color: #6c757d;
            font-size: 0.9em;
        }

        .checkbox-presenca {
            transform: scale(1.5);
            cursor: pointer;
        }

        .sessao-header {
            background: #2c3e50 !important;
            color: white;
            font-size: 1.1em;
        }

        .sessao-title {
            background: #3498db !important;
            color: white;
            font-weight: bold;
        }

        .presenca-marcada {
            background: #d4edda;
            color: #155724;
        }

        .presenca-ausente {
            background: #f8d7da;
            color: #721c24;
        }

        .voltar-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
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

        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
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

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>

<body>

    <?php
    if (isset($_SESSION["cpf"])) {
        $is_admin = isset($_SESSION["adm"]) && $_SESSION["adm"] == true;
        $cpf_usuario = $_SESSION["cpf"];

        $comites = array();
        $delegados = array();
        $presencas = array();
        $comite_selecionado = $_GET['comite'] ?? null;
        $comite_info = null;

        if ($conn && $conn->connect_error === null) {
            // Buscar a UNIF atual
            $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
            $result_unif = $conn->query($sql_unif);

            if ($result_unif && $result_unif->num_rows > 0) {
                $unif_atual = $result_unif->fetch_assoc();
                $id_unif = $unif_atual['id_unif'];

                // Se for ADMIN: pode ver todos os comitês aprovados
                if ($is_admin) {
                    $sql_comites = "SELECT id_comite, nome_comite FROM comite 
                                   WHERE id_unif = ? AND status = 'aprovado' 
                                   ORDER BY nome_comite";
                    $stmt_comites = $conn->prepare($sql_comites);
                    $stmt_comites->bind_param("i", $id_unif);
                    $stmt_comites->execute();
                    $result_comites = $stmt_comites->get_result();

                    if ($result_comites && $result_comites->num_rows > 0) {
                        while ($row = $result_comites->fetch_assoc()) {
                            $comites[] = $row;
                        }
                    }
                    $stmt_comites->close();

                    // Se não houver comitê selecionado e houver comitês, selecionar o primeiro
                    if (!$comite_selecionado && !empty($comites)) {
                        $comite_selecionado = $comites[0]['id_comite'];
                    }
                }
                // Se for DIRETOR: só pode ver o comitê que é diretor
                else {
                    // Buscar comitê(s) onde o usuário é diretor
                    $sql_diretor = "SELECT c.id_comite, c.nome_comite 
                                   FROM diretor d 
                                   JOIN comite c ON d.id_comite = c.id_comite 
                                   WHERE d.cpf = ? AND c.id_unif = ? AND c.status = 'aprovado' 
                                   LIMIT 1";
                    $stmt_diretor = $conn->prepare($sql_diretor);
                    $stmt_diretor->bind_param("si", $cpf_usuario, $id_unif);
                    $stmt_diretor->execute();
                    $result_diretor = $stmt_diretor->get_result();

                    if ($result_diretor && $result_diretor->num_rows > 0) {
                        $row = $result_diretor->fetch_assoc();
                        $comites[] = $row;
                        $comite_selecionado = $row['id_comite'];
                        $comite_info = $row;
                    }
                    $stmt_diretor->close();
                }

                // Se um comitê foi selecionado, buscar seus delegados e presenças
                if ($comite_selecionado) {
                    // Buscar informações do comitê
                    if (!$comite_info) {
                        $sql_comite_info = "SELECT nome_comite FROM comite WHERE id_comite = ?";
                        $stmt_info = $conn->prepare($sql_comite_info);
                        $stmt_info->bind_param("i", $comite_selecionado);
                        $stmt_info->execute();
                        $result_info = $stmt_info->get_result();
                        if ($result_info->num_rows > 0) {
                            $comite_info = $result_info->fetch_assoc();
                        }
                        $stmt_info->close();
                    }

                    // Buscar delegados do comitê
                    $sql_delegados = "SELECT d.cpf, u.nome, d.representacao 
                                     FROM delegado d 
                                     INNER JOIN usuario u ON d.cpf = u.cpf 
                                     WHERE d.id_comite = ? 
                                     ORDER BY u.nome";
                    $stmt_delegados = $conn->prepare($sql_delegados);
                    $stmt_delegados->bind_param("i", $comite_selecionado);
                    $stmt_delegados->execute();
                    $result_delegados = $stmt_delegados->get_result();

                    if ($result_delegados && $result_delegados->num_rows > 0) {
                        while ($row = $result_delegados->fetch_assoc()) {
                            $delegados[] = $row;
                        }
                    }
                    $stmt_delegados->close();

                    // Buscar presenças dos delegados
                    if (!empty($delegados)) {
                        $cpfs = array_column($delegados, 'cpf');
                        $placeholders = str_repeat('?,', count($cpfs) - 1) . '?';

                        $sql_presencas = "SELECT cpf_delegado, sabado_manha_1, sabado_manha_2, 
                                       sabado_tarde_1, sabado_tarde_2, domingo_manha_1, domingo_manha_2 
                                       FROM presenca_delegado 
                                       WHERE id_unif = ? AND id_comite = ? 
                                       AND cpf_delegado IN ($placeholders)";

                        $stmt_presencas = $conn->prepare($sql_presencas);
                        $types = "ii" . str_repeat('s', count($cpfs));
                        $params = array_merge([$id_unif, $comite_selecionado], $cpfs);
                        $stmt_presencas->bind_param($types, ...$params);
                        $stmt_presencas->execute();
                        $result_presencas = $stmt_presencas->get_result();

                        if ($result_presencas && $result_presencas->num_rows > 0) {
                            while ($row = $result_presencas->fetch_assoc()) {
                                $presencas[$row['cpf_delegado']] = $row;
                            }
                        }
                        $stmt_presencas->close();
                    }
                }
            }
        }

        // Verificar se o usuário tem acesso (admin ou diretor com comitê)
        $tem_acesso = $is_admin || (!empty($comites) && $comite_selecionado);

        if ($tem_acesso) {
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
                    <h1>Lista de Chamada</h1>
                    <p>Controle de Presença dos Delegados</p>
                </div>

                <!-- Controles Superiores -->
                <div class="controles-superiores">
                    <?php if ($is_admin && !empty($comites)): ?>
                        <div class="selecionar-comite">
                            <select onchange="window.location.href='?comite=' + this.value">
                                <option value="">Selecione um comitê</option>
                                <?php foreach ($comites as $comite): ?>
                                    <option value="<?php echo $comite['id_comite']; ?>"
                                        <?php echo $comite_selecionado == $comite['id_comite'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div></div> <!-- Espaço vazio para alinhamento -->
                    <?php endif; ?>

                    <?php if ($comite_selecionado && !empty($delegados)): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="exportar_excel" value="1">
                            <input type="hidden" name="id_comite" value="<?php echo $comite_selecionado; ?>">
                            <?php if ($comite_selecionado && !empty($delegados)): ?>
                                <a href="exportar_excel.php?comite=<?php echo $comite_selecionado; ?>" class="export-btn" target="_blank">
                                    <i class="fas fa-file-excel"></i> Exportar para Excel
                                </a>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($comite_selecionado && $comite_info): ?>
                    <div class="comite-info">
                        <h2><?php echo htmlspecialchars($comite_info['nome_comite']); ?></h2>
                        <p><?php echo count($delegados); ?> delegados inscritos</p>
                    </div>
                <?php endif; ?>

                <?php if ($comite_selecionado && !empty($delegados)):
                    // Calcular estatísticas
                    $total_presentes = 0;
                    $total_sessoes = 0;
                    $delegados_presentes = 0;

                    foreach ($delegados as $delegado) {
                        $presenca_delegado = $presencas[$delegado['cpf']] ?? array();
                        $presente_delegado = 0;
                        $tipos_presenca = ['sabado_manha_1', 'sabado_manha_2', 'sabado_tarde_1', 'sabado_tarde_2', 'domingo_manha_1', 'domingo_manha_2'];

                        foreach ($tipos_presenca as $tipo) {
                            if (!empty($presenca_delegado[$tipo]) && $presenca_delegado[$tipo] == 1) {
                                $total_presentes++;
                                $presente_delegado++;
                            }
                            $total_sessoes++;
                        }

                        if ($presente_delegado > 0) {
                            $delegados_presentes++;
                        }
                    }
                ?>
                    <!-- Estatísticas -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo count($delegados); ?></div>
                            <div class="stat-label">Total de Delegados</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $delegados_presentes; ?></div>
                            <div class="stat-label">Delegados Presentes</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_presentes; ?>/<?php echo $total_sessoes; ?></div>
                            <div class="stat-label">Presenças Registradas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo round(($delegados_presentes / max(1, count($delegados))) * 100, 1); ?>%</div>
                            <div class="stat-label">Taxa de Presença</div>
                        </div>
                    </div>

                    <!-- Lista de Chamada -->
                    <div class="lista-chamada">
                        <table class="tabela-chamada">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width: 250px;">Delegado</th>
                                    <th colspan="2" class="sessao-header">Sábado - Manhã</th>
                                    <th colspan="2" class="sessao-header">Sábado - Tarde</th>
                                    <th colspan="2" class="sessao-header">Domingo - Manhã</th>
                                    <th rowspan="2" style="width: 100px;">Total</th>
                                </tr>
                                <tr>
                                    <th class="sessao-title">Sessão 1</th>
                                    <th class="sessao-title">Sessão 2</th>
                                    <th class="sessao-title">Sessão 1</th>
                                    <th class="sessao-title">Sessão 2</th>
                                    <th class="sessao-title">Sessão 1</th>
                                    <th class="sessao-title">Sessão 2</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($delegados as $delegado):
                                    $presenca_delegado = $presencas[$delegado['cpf']] ?? array();
                                    $total_presente = 0;
                                    $tipos_presenca = ['sabado_manha_1', 'sabado_manha_2', 'sabado_tarde_1', 'sabado_tarde_2', 'domingo_manha_1', 'domingo_manha_2'];

                                    foreach ($tipos_presenca as $tipo) {
                                        if (!empty($presenca_delegado[$tipo]) && $presenca_delegado[$tipo] == 1) {
                                            $total_presente++;
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td class="delegado-info">
                                            <div class="delegado-nome"><?php echo htmlspecialchars($delegado['nome']); ?></div>
                                            <div class="delegado-cpf"><?php echo $delegado['cpf']; ?></div>
                                            <div class="delegado-representacao" style="color: #666; font-size: 0.9em;">
                                                <?php echo htmlspecialchars($delegado['representacao']); ?>
                                            </div>
                                        </td>

                                        <?php foreach ($tipos_presenca as $tipo):
                                            $marcado = !empty($presenca_delegado[$tipo]) && $presenca_delegado[$tipo] == 1;
                                        ?>
                                            <td class="<?php echo $marcado ? 'presenca-marcada' : 'presenca-ausente'; ?>">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="marcar_presenca" value="1">
                                                    <input type="hidden" name="cpf_delegado" value="<?php echo $delegado['cpf']; ?>">
                                                    <input type="hidden" name="id_comite" value="<?php echo $comite_selecionado; ?>">
                                                    <input type="hidden" name="tipo_presenca" value="<?php echo $tipo; ?>">
                                                    <input type="hidden" name="valor" value="<?php echo $marcado ? '0' : '1'; ?>">
                                                    <input type="checkbox" class="checkbox-presenca"
                                                        <?php echo $marcado ? 'checked' : ''; ?>
                                                        onchange="this.form.submit()">
                                                </form>
                                            </td>
                                        <?php endforeach; ?>

                                        <td style="font-weight: bold; background: #f8f9fa;">
                                            <?php echo $total_presente; ?>/6
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($comite_selecionado && empty($delegados)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <h3>Nenhum delegado inscrito neste comitê</h3>
                        <p>Não há delegados para controlar presença.</p>
                    </div>
                <?php elseif (!$comite_selecionado && $is_admin): ?>
                    <div style="text-align: center; padding: 40px;">
                        <h3>Selecione um comitê</h3>
                        <p>Use o seletor acima para escolher um comitê.</p>
                    </div>
                <?php elseif (!$tem_acesso): ?>
                    <div style="text-align: center; padding: 40px;">
                        <h3>Acesso Restrito</h3>
                        <p>Você não é diretor de nenhum comitê aprovado.</p>
                    </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="painelControle.php" class="voltar-btn">
                        <i class="fas fa-arrow-left"></i> Voltar ao Painel de Controle
                    </a>
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