<?php
session_start();
require_once 'php/conexao.php';

// Verificar autenticação
if (!isset($_SESSION["cpf"])) {
    header("Location: login.php");
    exit();
}

// Verificar se tem permissão
$is_admin = isset($_SESSION["adm"]) && $_SESSION["adm"] == true;
$cpf_usuario = $_SESSION["cpf"];

if (!$is_admin) {
    // Verificar se é diretor
    $is_diretor = false;
    if ($conn && $conn->connect_error === null) {
        $sql_diretor = "SELECT 1 FROM diretor WHERE cpf = ?";
        $stmt = $conn->prepare($sql_diretor);
        $stmt->bind_param("s", $cpf_usuario);
        $stmt->execute();
        $stmt->store_result();
        $is_diretor = $stmt->num_rows > 0;
        $stmt->close();
    }
    
    if (!$is_diretor) {
        die("Acesso negado. Apenas administradores e diretores podem exportar listas.");
    }
}

// Verificar se foi passado o ID do comitê
if (!isset($_GET['comite']) || empty($_GET['comite'])) {
    die("ID do comitê não especificado.");
}

$id_comite = $_GET['comite'];

// Buscar dados
$comite_info = null;
$delegados = array();
$presencas = array();

if ($conn && $conn->connect_error === null) {
    // Buscar informações do comitê
    $sql_comite = "SELECT c.nome_comite, c.tipo_comite, u.nome as unif_nome
                  FROM comite c
                  JOIN unif u ON c.id_unif = u.id_unif
                  WHERE c.id_comite = ?";
    $stmt_comite = $conn->prepare($sql_comite);
    $stmt_comite->bind_param("i", $id_comite);
    $stmt_comite->execute();
    $result_comite = $stmt_comite->get_result();
    
    if ($result_comite->num_rows > 0) {
        $comite_info = $result_comite->fetch_assoc();
    } else {
        die("Comitê não encontrado.");
    }
    $stmt_comite->close();
    
    // Verificar se o usuário tem acesso a este comitê
    if (!$is_admin) {
        $sql_acesso = "SELECT 1 FROM diretor WHERE cpf = ? AND id_comite = ?";
        $stmt_acesso = $conn->prepare($sql_acesso);
        $stmt_acesso->bind_param("si", $cpf_usuario, $id_comite);
        $stmt_acesso->execute();
        $stmt_acesso->store_result();
        
        if ($stmt_acesso->num_rows === 0) {
            die("Você não tem acesso a este comitê.");
        }
        $stmt_acesso->close();
    }
    
    // Buscar delegados do comitê
    $sql_delegados = "SELECT d.cpf, u.nome, u.email, u.instituicao, d.representacao,
                     r.nome_representacao
                     FROM delegado d
                     INNER JOIN usuario u ON d.cpf = u.cpf
                     LEFT JOIN representacao r ON d.representacao = r.id_representacao
                     WHERE d.id_comite = ?
                     ORDER BY u.nome";
    $stmt_delegados = $conn->prepare($sql_delegados);
    $stmt_delegados->bind_param("i", $id_comite);
    $stmt_delegados->execute();
    $result_delegados = $stmt_delegados->get_result();
    
    while ($row = $result_delegados->fetch_assoc()) {
        $delegados[] = $row;
    }
    $stmt_delegados->close();
    
    // Buscar presenças dos delegados
    if (!empty($delegados)) {
        $cpfs = array_column($delegados, 'cpf');
        $placeholders = str_repeat('?,', count($cpfs) - 1) . '?';
        
        // Buscar UNIF atual
        $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
        $result_unif = $conn->query($sql_unif);
        $unif_atual = $result_unif->fetch_assoc();
        $id_unif = $unif_atual['id_unif'];
        
        $sql_presencas = "SELECT cpf_delegado, sabado_manha_1, sabado_manha_2,
                         sabado_tarde_1, sabado_tarde_2, domingo_manha_1, domingo_manha_2
                         FROM presenca_delegado
                         WHERE id_unif = ? AND id_comite = ?
                         AND cpf_delegado IN ($placeholders)";
        
        $stmt_presencas = $conn->prepare($sql_presencas);
        $types = "ii" . str_repeat('s', count($cpfs));
        $params = array_merge([$id_unif, $id_comite], $cpfs);
        $stmt_presencas->bind_param($types, ...$params);
        $stmt_presencas->execute();
        $result_presencas = $stmt_presencas->get_result();
        
        while ($row = $result_presencas->fetch_assoc()) {
            $presencas[$row['cpf_delegado']] = $row;
        }
        $stmt_presencas->close();
    }
}

// Gerar Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="chamada_' . $comite_info['nome_comite'] . '_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
        }
        .header {
            background-color: #2E7D32;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 18px;
        }
        .subheader {
            background-color: #81C784;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }
        .presente {
            background-color: #C8E6C9;
            text-align: center;
        }
        .ausente {
            background-color: #FFCDD2;
            text-align: center;
        }
        .total {
            background-color: #BBDEFB;
            font-weight: bold;
        }
        .summary {
            background-color: #FFF3E0;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #FFB74D;
        }
    </style>
</head>
<body>
    <!-- Cabeçalho -->
    <table>
        <tr>
            <td colspan="13" class="header">
                <strong>UNIF - LISTA DE CHAMADA</strong><br>
                <?php echo htmlspecialchars($comite_info['unif_nome']); ?>
            </td>
        </tr>
        <tr>
            <td colspan="13" class="subheader">
                <strong>Comitê:</strong> <?php echo htmlspecialchars($comite_info['nome_comite']); ?> | 
                <strong>Tipo:</strong> <?php echo htmlspecialchars($comite_info['tipo_comite']); ?> | 
                <strong>Data de Exportação:</strong> <?php echo date('d/m/Y H:i:s'); ?>
            </td>
        </tr>
        <tr>
            <td colspan="13" class="summary">
                <strong>Resumo:</strong> <?php echo count($delegados); ?> delegados | 
                Exportado por: <?php echo htmlspecialchars($_SESSION['nome'] ?? 'Usuário'); ?>
            </td>
        </tr>
    </table>

    <!-- Tabela de Chamada -->
    <table>
        <thead>
            <tr>
                <th rowspan="2">#</th>
                <th rowspan="2">CPF</th>
                <th rowspan="2">Nome</th>
                <th rowspan="2">Email</th>
                <th rowspan="2">Instituição</th>
                <th rowspan="2">Representação</th>
                <th colspan="2" style="text-align: center;">Sábado - Manhã</th>
                <th colspan="2" style="text-align: center;">Sábado - Tarde</th>
                <th colspan="2" style="text-align: center;">Domingo - Manhã</th>
                <th rowspan="2">Total</th>
            </tr>
            <tr>
                <th>Sessão 1</th>
                <th>Sessão 2</th>
                <th>Sessão 1</th>
                <th>Sessão 2</th>
                <th>Sessão 1</th>
                <th>Sessão 2</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tipos_presenca = ['sabado_manha_1', 'sabado_manha_2', 'sabado_tarde_1', 'sabado_tarde_2', 'domingo_manha_1', 'domingo_manha_2'];
            $total_geral = 0;
            $sessoes_totais = 0;
            
            foreach ($delegados as $index => $delegado):
                $presenca_delegado = $presencas[$delegado['cpf']] ?? array();
                $total_presente = 0;
                
                foreach ($tipos_presenca as $tipo) {
                    if (!empty($presenca_delegado[$tipo]) && $presenca_delegado[$tipo] == 1) {
                        $total_presente++;
                        $total_geral++;
                    }
                    $sessoes_totais++;
                }
            ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo $delegado['cpf']; ?></td>
                    <td><?php echo htmlspecialchars($delegado['nome']); ?></td>
                    <td><?php echo htmlspecialchars($delegado['email']); ?></td>
                    <td><?php echo htmlspecialchars($delegado['instituicao']); ?></td>
                    <td><?php echo htmlspecialchars($delegado['nome_representacao'] ?? $delegado['representacao']); ?></td>
                    
                    <?php foreach ($tipos_presenca as $tipo):
                        $marcado = !empty($presenca_delegado[$tipo]) && $presenca_delegado[$tipo] == 1;
                    ?>
                        <td class="<?php echo $marcado ? 'presente' : 'ausente'; ?>">
                            <?php echo $marcado ? '✓ Presente' : '✗ Ausente'; ?>
                        </td>
                    <?php endforeach; ?>
                    
                    <td class="total"><?php echo $total_presente; ?>/6</td>
                </tr>
            <?php endforeach; ?>
            
            <!-- Linha de resumo -->
            <tr>
                <td colspan="6" style="text-align: right; font-weight: bold;">TOTAIS:</td>
                <?php
                // Calcular totais por sessão
                $totais_sessao = array_fill_keys($tipos_presenca, 0);
                foreach ($delegados as $delegado) {
                    $presenca_delegado = $presencas[$delegado['cpf']] ?? array();
                    foreach ($tipos_presenca as $tipo) {
                        if (!empty($presenca_delegado[$tipo]) && $presenca_delegado[$tipo] == 1) {
                            $totais_sessao[$tipo]++;
                        }
                    }
                }
                
                foreach ($tipos_presenca as $tipo):
                ?>
                    <td style="text-align: center; font-weight: bold; background-color: #E3F2FD;">
                        <?php echo $totais_sessao[$tipo]; ?>/<?php echo count($delegados); ?>
                    </td>
                <?php endforeach; ?>
                <td style="text-align: center; font-weight: bold; background-color: #E8F5E9;">
                    <?php echo $total_geral; ?>/<?php echo $sessoes_totais; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Resumo Estatístico -->
    <br>
    <table>
        <tr>
            <td colspan="4" style="background-color: #2E7D32; color: white; font-weight: bold; text-align: center;">
                RESUMO ESTATÍSTICO
            </td>
        </tr>
        <tr>
            <th>Indicador</th>
            <th>Valor</th>
            <th>Indicador</th>
            <th>Valor</th>
        </tr>
        <tr>
            <td>Total de Delegados</td>
            <td><?php echo count($delegados); ?></td>
            <td>Delegados Presentes (≥1 sessão)</td>
            <td>
                <?php
                $presentes = 0;
                foreach ($delegados as $delegado) {
                    $presenca_delegado = $presencas[$delegado['cpf']] ?? array();
                    $tem_presenca = false;
                    foreach ($tipos_presenca as $tipo) {
                        if (!empty($presenca_delegado[$tipo]) && $presenca_delegado[$tipo] == 1) {
                            $tem_presenca = true;
                            break;
                        }
                    }
                    if ($tem_presenca) $presentes++;
                }
                echo $presentes;
                ?>
            </td>
        </tr>
        <tr>
            <td>Taxa de Presença Geral</td>
            <td><?php echo count($delegados) > 0 ? round(($presentes / count($delegados)) * 100, 1) : 0; ?>%</td>
            <td>Sessões Registradas</td>
            <td><?php echo $total_geral; ?>/<?php echo $sessoes_totais; ?></td>
        </tr>
        <tr>
            <td>Taxa de Preenchimento</td>
            <td><?php echo $sessoes_totais > 0 ? round(($total_geral / $sessoes_totais) * 100, 1) : 0; ?>%</td>
            <td>Data da Exportação</td>
            <td><?php echo date('d/m/Y H:i:s'); ?></td>
        </tr>
    </table>

    <!-- Legenda -->
    <br>
    <table>
        <tr>
            <td colspan="2" style="background-color: #5C6BC0; color: white; font-weight: bold; text-align: center;">
                LEGENDA
            </td>
        </tr>
        <tr>
            <td style="background-color: #C8E6C9; text-align: center;">✓ Presente</td>
            <td>Delegado presente na sessão</td>
        </tr>
        <tr>
            <td style="background-color: #FFCDD2; text-align: center;">✗ Ausente</td>
            <td>Delegado ausente na sessão</td>
        </tr>
        <tr>
            <td style="background-color: #FFF3E0; text-align: center;">X/Y</td>
            <td>X presentes de Y possíveis</td>
        </tr>
    </table>
    
    <!-- Rodapé -->
    <br>
    <table>
        <tr>
            <td colspan="13" style="text-align: center; font-size: 10px; color: #666; padding: 10px; border-top: 2px solid #2E7D32;">
                <strong>UNIF - Sistema de Simulação Diplomática</strong><br>
                Exportado automaticamente pelo sistema | <?php echo date('d/m/Y H:i:s'); ?>
            </td>
        </tr>
    </table>
</body>
</html>