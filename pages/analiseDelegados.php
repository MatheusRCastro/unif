<?php
session_start();
require_once 'php/conexao.php';

// Verificar se é admin ou secretário
if (!isset($_SESSION["cpf"]) || (!$_SESSION["adm"] && !$_SESSION["secretario"])) {
    header("Location: login.html");
    exit();
}

// Buscar dados
$comites = [];
$delegados_sem_representacao = [];
$estatisticas = [];
$current_unif_id = 1;

if ($conn && $conn->connect_error === null) {
    // Buscar o ID da UNIF atual
    $sql_unif = "SELECT id_unif FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
    $result_unif = $conn->query($sql_unif);
    if ($result_unif && $result_unif->num_rows > 0) {
        $unif_data = $result_unif->fetch_assoc();
        $current_unif_id = $unif_data['id_unif'];
    }
    
    // Buscar comitês aprovados da UNIF atual
    $sql_comites = "
        SELECT 
            c.id_comite,
            c.nome_comite,
            c.tipo_comite,
            c.num_delegados,
            COUNT(r.id_representacao) as total_representacoes,
            SUM(CASE WHEN r.cpf_delegado IS NOT NULL THEN 1 ELSE 0 END) as representacoes_preenchidas
        FROM comite c
        LEFT JOIN representacao r ON c.id_comite = r.id_comite
        WHERE c.id_unif = ?
        AND c.status = 'aprovado'
        GROUP BY c.id_comite
        ORDER BY c.nome_comite";
    
    $stmt_comites = $conn->prepare($sql_comites);
    $stmt_comites->bind_param("i", $current_unif_id);
    $stmt_comites->execute();
    $result_comites = $stmt_comites->get_result();
    
    if ($result_comites && $result_comites->num_rows > 0) {
        while ($row = $result_comites->fetch_assoc()) {
            $comites[$row['id_comite']] = $row;
            
            // Buscar representações deste comitê
            $sql_representacoes = "
                SELECT 
                    r.id_representacao,
                    r.nome_representacao,
                    r.cpf_delegado,
                    u.nome as nome_delegado,
                    u.instituicao,
                    u.cpf as cpf_delegado_full
                FROM representacao r
                LEFT JOIN usuario u ON r.cpf_delegado = u.cpf
                WHERE r.id_comite = ?
                ORDER BY r.nome_representacao";
            
            $stmt_rep = $conn->prepare($sql_representacoes);
            $stmt_rep->bind_param("i", $row['id_comite']);
            $stmt_rep->execute();
            $result_rep = $stmt_rep->get_result();
            
            $representacoes = [];
            while ($rep = $result_rep->fetch_assoc()) {
                $representacoes[] = $rep;
            }
            $comites[$row['id_comite']]['representacoes'] = $representacoes;
            $stmt_rep->close();
        }
    }
    $stmt_comites->close();
    
    // Buscar TODOS os delegados APROVADOS que NÃO estão atribuídos a representações
    // Correção: Buscar delegados que não estão na tabela representacao OU estão com cpf_delegado NULL
    $sql_delegados_nao_atribuidos = "
        SELECT DISTINCT
            d.cpf, 
            u.nome, 
            u.email, 
            u.telefone, 
            u.instituicao,
            u.restricao_alimentar,
            u.alergia,
            d.comite_desejado as comite_op1_id,
            c1.nome_comite as comite_op1_nome,
            d.segunda_op_comite as comite_op2_id,
            c2.nome_comite as comite_op2_nome,
            d.terceira_op_comite as comite_op3_id,
            c3.nome_comite as comite_op3_nome,
            d.primeira_op_representacao as rep_op1_id,
            r1.nome_representacao as rep_op1_nome,
            d.segunda_op_representacao as rep_op2_id,
            r2.nome_representacao as rep_op2_nome,
            d.terceira_op_representacao as rep_op3_id,
            r3.nome_representacao as rep_op3_nome
        FROM delegado d
        JOIN usuario u ON d.cpf = u.cpf
        LEFT JOIN comite c1 ON d.comite_desejado = c1.id_comite
        LEFT JOIN comite c2 ON d.segunda_op_comite = c2.id_comite
        LEFT JOIN comite c3 ON d.terceira_op_comite = c3.id_comite
        LEFT JOIN representacao r1 ON d.primeira_op_representacao = r1.id_representacao
        LEFT JOIN representacao r2 ON d.segunda_op_representacao = r2.id_representacao
        LEFT JOIN representacao r3 ON d.terceira_op_representacao = r3.id_representacao
        WHERE d.status_pagamento = 'aprovado'
        AND (d.cpf NOT IN (SELECT cpf_delegado FROM representacao WHERE cpf_delegado IS NOT NULL)
             OR d.cpf IN (SELECT cpf_delegado FROM representacao WHERE cpf_delegado IS NULL))
        ORDER BY u.nome";
    
    $result_delegados = $conn->query($sql_delegados_nao_atribuidos);
    
    if ($result_delegados && $result_delegados->num_rows > 0) {
        while ($row = $result_delegados->fetch_assoc()) {
            $delegados_sem_representacao[] = $row;
        }
    }
    
    // Estatísticas gerais - APENAS DELEGADOS APROVADOS
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT d.cpf) as total_delegados,
            COUNT(DISTINCT CASE WHEN r.cpf_delegado IS NOT NULL THEN d.cpf END) as delegados_atribuidos,
            COUNT(DISTINCT r.id_representacao) as total_representacoes,
            COUNT(DISTINCT CASE WHEN r.cpf_delegado IS NOT NULL THEN r.id_representacao END) as representacoes_preenchidas
        FROM delegado d
        LEFT JOIN representacao r ON d.cpf = r.cpf_delegado
        WHERE d.status_pagamento = 'aprovado'";
    
    $result_stats = $conn->query($sql_stats);
    
    if ($result_stats && $result_stats->num_rows > 0) {
        $estatisticas = $result_stats->fetch_assoc();
    }
}

// Processar atribuição via AJAX - CORREÇÃO CRÍTICA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] == 'atribuir_delegado') {
        $cpf = $_POST['cpf'] ?? '';
        $id_representacao = $_POST['id_representacao'] ?? 0;
        
        if ($cpf && $id_representacao) {
            // Primeiro, remover o delegado de qualquer outra representação
            $sql_remover = "UPDATE representacao SET cpf_delegado = NULL WHERE cpf_delegado = ?";
            $stmt_remover = $conn->prepare($sql_remover);
            $stmt_remover->bind_param("s", $cpf);
            
            if ($stmt_remover->execute()) {
                // Agora atribuir à nova representação
                $sql_atribuir = "UPDATE representacao SET cpf_delegado = ? WHERE id_representacao = ?";
                $stmt_atribuir = $conn->prepare($sql_atribuir);
                $stmt_atribuir->bind_param("si", $cpf, $id_representacao);
                
                if ($stmt_atribuir->execute()) {
                    // Buscar o comitê desta representação para atualizar o delegado
                    $sql_comite_rep = "SELECT id_comite FROM representacao WHERE id_representacao = ?";
                    $stmt_comite = $conn->prepare($sql_comite_rep);
                    $stmt_comite->bind_param("i", $id_representacao);
                    $stmt_comite->execute();
                    $result_comite = $stmt_comite->get_result();
                    
                    if ($result_comite->num_rows > 0) {
                        $comite_data = $result_comite->fetch_assoc();
                        $id_comite = $comite_data['id_comite'];
                        
                        // Atualizar o id_comite do delegado
                        $sql_update_delegado = "UPDATE delegado SET id_comite = ? WHERE cpf = ?";
                        $stmt_update = $conn->prepare($sql_update_delegado);
                        $stmt_update->bind_param("is", $id_comite, $cpf);
                        $stmt_update->execute();
                        $stmt_update->close();
                        
                        // NOVO: Atualizar o campo representacao na tabela delegado
                        $sql_update_representacao = "UPDATE delegado SET representacao = ? WHERE cpf = ?";
                        $stmt_update_rep = $conn->prepare($sql_update_representacao);
                        $stmt_update_rep->bind_param("is", $id_representacao, $cpf);
                        $stmt_update_rep->execute();
                        $stmt_update_rep->close();
                    }
                    $stmt_comite->close();
                    
                    // Buscar dados do delegado para resposta
                    $sql_delegado = "SELECT u.nome, u.instituicao FROM usuario u WHERE u.cpf = ?";
                    $stmt_delegado = $conn->prepare($sql_delegado);
                    $stmt_delegado->bind_param("s", $cpf);
                    $stmt_delegado->execute();
                    $result_delegado = $stmt_delegado->get_result();
                    $delegado_data = $result_delegado->fetch_assoc();
                    $stmt_delegado->close();
                    
                    $response['success'] = true;
                    $response['delegado'] = [
                        'nome' => $delegado_data['nome'],
                        'instituicao' => $delegado_data['instituicao'],
                        'cpf' => $cpf
                    ];
                } else {
                    $response['message'] = 'Erro ao atribuir delegado: ' . $stmt_atribuir->error;
                }
                $stmt_atribuir->close();
            } else {
                $response['message'] = 'Erro ao remover delegado anterior: ' . $stmt_remover->error;
            }
            $stmt_remover->close();
        } else {
            $response['message'] = 'Dados incompletos: CPF ou ID da representação ausente';
        }
    } 
    elseif ($_POST['action'] == 'remover_delegado') {
        $id_representacao = $_POST['id_representacao'] ?? 0;
        
        if ($id_representacao) {
            // Primeiro, buscar o CPF do delegado para poder limpar o id_comite
            $sql_buscar_cpf = "SELECT cpf_delegado FROM representacao WHERE id_representacao = ?";
            $stmt_buscar = $conn->prepare($sql_buscar_cpf);
            $stmt_buscar->bind_param("i", $id_representacao);
            $stmt_buscar->execute();
            $result_buscar = $stmt_buscar->get_result();
            
            if ($result_buscar->num_rows > 0) {
                $dados = $result_buscar->fetch_assoc();
                $cpf = $dados['cpf_delegado'];
                
                // Remover o delegado da representação
                $sql_remover = "UPDATE representacao SET cpf_delegado = NULL WHERE id_representacao = ?";
                $stmt_remover = $conn->prepare($sql_remover);
                $stmt_remover->bind_param("i", $id_representacao);
                
                if ($stmt_remover->execute()) {
                    // Limpar o id_comite do delegado
                    $sql_limpar_comite = "UPDATE delegado SET id_comite = NULL WHERE cpf = ?";
                    $stmt_limpar = $conn->prepare($sql_limpar_comite);
                    $stmt_limpar->bind_param("s", $cpf);
                    $stmt_limpar->execute();
                    $stmt_limpar->close();
                    
                    // NOVO: Limpar também o campo representacao
                    $sql_limpar_rep = "UPDATE delegado SET representacao = NULL WHERE cpf = ?";
                    $stmt_limpar_rep = $conn->prepare($sql_limpar_rep);
                    $stmt_limpar_rep->bind_param("s", $cpf);
                    $stmt_limpar_rep->execute();
                    $stmt_limpar_rep->close();
                    
                    $response['success'] = true;
                    $response['cpf'] = $cpf;
                } else {
                    $response['message'] = 'Erro ao remover delegado: ' . $stmt_remover->error;
                }
                $stmt_remover->close();
            }
            $stmt_buscar->close();
        } else {
            $response['message'] = 'ID da representação ausente';
        }
    }
    
    // Se for uma requisição AJAX, enviar JSON e sair
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Delegados</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: rgb(24, 153, 63);
            --secondary-green: rgb(19, 194, 48);
            --light-green: #d4edda;
            --dark-green: #155724;
            --warning: #ffc107;
            --danger: #dc3545;
            --success: #28a745;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            color: var(--dark);
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }
        
        .header h1 {
            color: var(--dark);
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header .info {
            background: var(--light-green);
            padding: 12px 18px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
            color: var(--dark-green);
            border-left: 4px solid var(--success);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-top: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-top: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.total-delegados { border-top-color: var(--primary-green); }
        .stat-card.delegados-atribuidos { border-top-color: var(--success); }
        .stat-card.representacoes-total { border-top-color: var(--warning); }
        .stat-card.representacoes-preenchidas { border-top-color: var(--info); }
        
        .stat-card h3 {
            margin: 0;
            color: var(--gray);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin: 5px 0;
            line-height: 1;
        }
        
        .content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 25px;
        }
        
        @media (max-width: 1200px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
        
        .comites-section, .delegados-section {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            height: fit-content;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--light);
        }
        
        .comite-card {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border: 2px solid var(--light);
            transition: var(--transition);
        }
        
        .comite-card:hover {
            border-color: #dee2e6;
        }
        
        .comite-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--light);
        }
        
        .comite-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .comite-stats {
            font-size: 14px;
            color: var(--gray);
            background: var(--light);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .representacoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .representacoes-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
        
        .representacao-card {
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 15px;
            min-height: 100px;
            transition: var(--transition);
            position: relative;
        }
        
        .representacao-card.empty {
            border-style: dashed;
            border-color: #dee2e6;
            background: linear-gradient(135deg, rgba(248, 249, 250, 0.3), white);
        }
        
        .representacao-card.filled {
            border-style: solid;
            border-color: var(--success);
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), white);
        }
        
        .representacao-card.drag-over {
            border-color: var(--primary-green);
            background: linear-gradient(135deg, rgba(24, 153, 63, 0.1), white);
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(24, 153, 63, 0.2);
        }
        
        .rep-nome {
            font-weight: 700;
            color: var(--dark);
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .delegado-info {
            font-size: 13px;
            color: var(--dark);
            padding: 10px;
            background: linear-gradient(135deg, rgba(24, 153, 63, 0.08), rgba(19, 194, 48, 0.05));
            border-radius: 6px;
            margin-top: 8px;
            cursor: move;
            user-select: none;
            border: 1px solid rgba(24, 153, 63, 0.2);
            transition: var(--transition);
        }
        
        .delegado-info:hover {
            background: linear-gradient(135deg, rgba(24, 153, 63, 0.12), rgba(19, 194, 48, 0.08));
            border-color: rgba(24, 153, 63, 0.4);
        }
        
        .delegado-info.dragging {
            opacity: 0.4;
            transform: scale(0.98);
        }
        
        .delegado-info .name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .delegado-info .institution {
            font-size: 12px;
            color: var(--gray);
        }
        
        .delegado-info .remove-hint {
            font-size: 11px;
            color: var(--danger);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0.7;
        }
        
        .empty-rep-message {
            color: var(--gray);
            font-size: 13px;
            text-align: center;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .delegados-list {
            max-height: 800px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .delegados-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .delegados-list::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 4px;
        }
        
        .delegados-list::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 4px;
        }
        
        .delegado-card {
            background: white;
            border: 2px solid var(--primary-green);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 15px;
            cursor: move;
            transition: var(--transition);
            user-select: none;
        }
        
        .delegado-card:hover {
            box-shadow: 0 6px 16px rgba(24, 153, 63, 0.2);
            transform: translateY(-3px);
            border-color: var(--secondary-green);
        }
        
        .delegado-card.dragging {
            opacity: 0.5;
            transform: scale(0.98) rotate(2deg);
            box-shadow: 0 8px 25px rgba(24, 153, 63, 0.3);
        }
        
        .delegado-card.drag-target {
            border-color: var(--warning);
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), white);
        }
        
        .delegado-nome {
            font-weight: 700;
            color: var(--dark);
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .delegado-details {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 12px;
        }
        
        .opcoes-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            margin-bottom: 10px;
        }
        
        .opcao-tag {
            background: white;
            border: 1px solid;
            border-radius: 12px;
            padding: 5px 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 11px;
            font-weight: 600;
        }
        
        .opcao-tag.comite {
            background: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.3);
            color: var(--success);
        }
        
        .opcao-tag.representacao {
            background: rgba(255, 193, 7, 0.1);
            border-color: rgba(255, 193, 7, 0.3);
            color: #d35400;
        }
        
        .opcao-number {
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
        }
        
        .opcao-number.comite {
            background: var(--success);
        }
        
        .opcao-number.representacao {
            background: var(--warning);
        }
        
        .drag-hint {
            font-size: 11px;
            color: var(--primary-green);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        
        .empty-message {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray);
            font-style: italic;
            background: var(--light);
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }
        
        .success-message {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.9), rgba(33, 136, 56, 0.9));
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 10px 0;
            display: none;
            box-shadow: var(--shadow);
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }
        
        .error-message {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(200, 35, 51, 0.9));
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 10px 0;
            display: none;
            box-shadow: var(--shadow);
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .drop-zone-hint {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(33, 136, 56, 0.05));
            border: 2px dashed var(--success);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
            color: var(--dark);
            font-weight: 600;
            display: none;
            transition: var(--transition);
        }
        
        .drop-zone-hint.drag-over {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.2), rgba(33, 136, 56, 0.1));
            border-color: var(--success);
            transform: scale(1.02);
        }
        
        .approved-badge {
            background: linear-gradient(135deg, var(--success), #218838);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 10px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
            gap: 20px;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--light);
            border-top: 5px solid var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div style="color: var(--dark); font-weight: 600;">Processando...</div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users-cog"></i> Atribuição de Delegados 
                <span class="approved-badge"><i class="fas fa-check-circle"></i> APENAS APROVADOS</span>
            </h1>
            <div class="info">
                <i class="fas fa-info-circle"></i> Esta página mostra apenas delegados com <strong>pagamento aprovado</strong>. 
                Para remover um delegado de uma representação, arraste-o de volta para a lista.
            </div>
            
            <div class="stats-grid">
                <div class="stat-card total-delegados">
                    <h3>Total de Delegados Aprovados</h3>
                    <div class="number"><?php echo $estatisticas['total_delegados'] ?? 0; ?></div>
                </div>
                <div class="stat-card delegados-atribuidos">
                    <h3>Delegados Atribuídos</h3>
                    <div class="number"><?php echo $estatisticas['delegados_atribuidos'] ?? 0; ?></div>
                </div>
                <div class="stat-card representacoes-total">
                    <h3>Total Representações</h3>
                    <div class="number"><?php echo $estatisticas['total_representacoes'] ?? 0; ?></div>
                </div>
                <div class="stat-card representacoes-preenchidas">
                    <h3>Representações Preenchidas</h3>
                    <div class="number"><?php echo $estatisticas['representacoes_preenchidas'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        
        <div id="successMessage" class="success-message"></div>
        <div id="errorMessage" class="error-message"></div>
        
        <div class="content">
            <!-- Seção de Comitês e Representações -->
            <div class="comites-section">
                <h2 class="section-title"><i class="fas fa-landmark"></i> Comitês e Representações</h2>
                
                <?php if (empty($comites)): ?>
                    <div class="empty-message">
                        <i class="fas fa-inbox fa-3x" style="margin-bottom: 15px;"></i>
                        <p>Nenhum comitê aprovado encontrado.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($comites as $comite): ?>
                        <div class="comite-card">
                            <div class="comite-header">
                                <div class="comite-title">
                                    <i class="fas fa-users"></i>
                                    <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                    <span style="color: var(--gray); font-size: 14px;">(<?php echo htmlspecialchars($comite['tipo_comite']); ?>)</span>
                                </div>
                                <div class="comite-stats">
                                    <?php echo $comite['representacoes_preenchidas']; ?>/<?php echo $comite['total_representacoes']; ?> vagas
                                </div>
                            </div>
                            
                            <div class="representacoes-grid" data-comite="<?php echo $comite['id_comite']; ?>">
                                <?php foreach ($comite['representacoes'] as $representacao): ?>
                                    <div class="representacao-card <?php echo $representacao['cpf_delegado'] ? 'filled' : 'empty'; ?>" 
                                         data-rep-id="<?php echo $representacao['id_representacao']; ?>"
                                         ondragover="allowDrop(event)" 
                                         ondragenter="handleDragEnter(event)"
                                         ondragleave="handleDragLeave(event)"
                                         ondrop="dropDelegado(event)">
                                        
                                        <div class="rep-nome">
                                            <i class="fas fa-flag"></i>
                                            <?php echo htmlspecialchars($representacao['nome_representacao']); ?>
                                        </div>
                                        
                                        <?php if ($representacao['cpf_delegado']): ?>
                                            <div class="delegado-info" 
                                                 draggable="true" 
                                                 ondragstart="dragDelegadoFromRep(event)" 
                                                 data-cpf="<?php echo htmlspecialchars($representacao['cpf_delegado']); ?>">
                                                <div class="name">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($representacao['nome_delegado']); ?>
                                                </div>
                                                <div class="institution">
                                                    <?php echo htmlspecialchars($representacao['instituicao']); ?>
                                                </div>
                                                <div class="remove-hint">
                                                    <i class="fas fa-arrows-alt"></i> Arraste para a lista para remover
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-rep-message">
                                                <i class="fas fa-arrow-down fa-lg"></i>
                                                <span>Solte um delegado aqui</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Seção de Delegados -->
            <div class="delegados-section">
                <h2 class="section-title"><i class="fas fa-user-friends"></i> Delegados Disponíveis 
                    <span style="font-size: 14px; color: var(--gray);">(<?php echo count($delegados_sem_representacao); ?>)</span>
                </h2>
                <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> Arraste para as representações vazias. 
                    Para remover um delegado, arraste-o de volta para esta área.
                </p>
                
                <div class="drop-zone-hint" id="dropZoneHint">
                    <i class="fas fa-sign-in-alt fa-lg"></i>
                    <div style="margin-top: 10px;">Solte aqui para remover da representação</div>
                </div>
                
                <div class="delegados-list" 
                     ondragover="allowDrop(event)"
                     ondragenter="handleDragEnterDelegado(event)"
                     ondragleave="handleDragLeaveDelegado(event)"
                     ondrop="dropToRemove(event)">
                    
                    <?php if (empty($delegados_sem_representacao)): ?>
                        <div class="empty-message">
                            <i class="fas fa-check-circle fa-3x" style="color: var(--success); margin-bottom: 15px;"></i>
                            <p>Todos os delegados aprovados já estão atribuídos!</p>
                        </div>
                    <?php else: ?>
                        <div id="delegadosList">
                            <?php foreach ($delegados_sem_representacao as $delegado): ?>
                                <div class="delegado-card" 
                                     draggable="true" 
                                     ondragstart="dragDelegado(event)" 
                                     data-cpf="<?php echo htmlspecialchars($delegado['cpf']); ?>">
                                    
                                    <div class="delegado-nome">
                                        <i class="fas fa-user-graduate"></i>
                                        <?php echo htmlspecialchars($delegado['nome']); ?>
                                    </div>
                                    
                                    <div class="delegado-details">
                                        <strong><?php echo htmlspecialchars($delegado['instituicao']); ?></strong>
                                    </div>
                                    
                                    <div class="opcoes-grid">
                                        <?php 
                                        // Mostrar todas as 3 opções de comitê
                                        for ($i = 1; $i <= 3; $i++): 
                                            $comite_op = 'comite_op' . $i . '_nome';
                                            if (!empty($delegado[$comite_op])): ?>
                                                <div class="opcao-tag comite" title="<?php echo $i; ?>ª Opção de Comitê: <?php echo htmlspecialchars($delegado[$comite_op]); ?>">
                                                    <span class="opcao-number comite"><?php echo $i; ?></span>
                                                    <span><?php echo htmlspecialchars($delegado[$comite_op]); ?></span>
                                                </div>
                                            <?php endif;
                                        endfor; ?>
                                        
                                        <?php 
                                        // Mostrar todas as 3 opções de representação
                                        for ($i = 1; $i <= 3; $i++): 
                                            $rep_op = 'rep_op' . $i . '_nome';
                                            if (!empty($delegado[$rep_op])): ?>
                                                <div class="opcao-tag representacao" title="<?php echo $i; ?>ª Opção de Representação: <?php echo htmlspecialchars($delegado[$rep_op]); ?>">
                                                    <span class="opcao-number representacao"><?php echo $i; ?></span>
                                                    <span><?php echo htmlspecialchars($delegado[$rep_op]); ?></span>
                                                </div>
                                            <?php endif;
                                        endfor; ?>
                                    </div>
                                    
                                    <div class="drag-hint">
                                        <i class="fas fa-arrows-alt"></i> Arraste para uma representação
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let delegadoArrastado = null;
        let delegadoFromRep = null;
        let currentDropTarget = null;
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => successDiv.style.display = 'none', 3000);
        }
        
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => errorDiv.style.display = 'none', 4000);
        }
        
        function dragDelegado(event) {
            delegadoArrastado = event.target;
            event.target.classList.add('dragging');
            event.dataTransfer.setData('text/plain', event.target.dataset.cpf);
            event.dataTransfer.setData('type', 'delegado');
            event.dataTransfer.effectAllowed = 'move';
        }
        
        function dragDelegadoFromRep(event) {
            delegadoFromRep = event.target;
            event.target.classList.add('dragging');
            event.dataTransfer.setData('text/plain', event.target.dataset.cpf);
            event.dataTransfer.setData('type', 'delegado_atribuido');
            event.dataTransfer.effectAllowed = 'move';
            event.stopPropagation();
        }
        
        function allowDrop(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        }
        
        function handleDragEnter(event) {
            event.preventDefault();
            if (event.currentTarget.classList.contains('representacao-card')) {
                event.currentTarget.classList.add('drag-over');
                currentDropTarget = event.currentTarget;
            }
        }
        
        function handleDragLeave(event) {
            if (event.currentTarget.classList.contains('representacao-card')) {
                event.currentTarget.classList.remove('drag-over');
                if (currentDropTarget === event.currentTarget) {
                    currentDropTarget = null;
                }
            }
        }
        
        function handleDragEnterDelegado(event) {
            event.preventDefault();
            document.getElementById('dropZoneHint').style.display = 'block';
            document.getElementById('dropZoneHint').classList.add('drag-over');
        }
        
        function handleDragLeaveDelegado(event) {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                document.getElementById('dropZoneHint').classList.remove('drag-over');
                setTimeout(() => {
                    if (!document.getElementById('dropZoneHint').classList.contains('drag-over')) {
                        document.getElementById('dropZoneHint').style.display = 'none';
                    }
                }, 100);
            }
        }
        
        function dropDelegado(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            const type = event.dataTransfer.getData('type');
            let cpf;
            
            if (type === 'delegado_atribuido') {
                cpf = delegadoFromRep ? delegadoFromRep.dataset.cpf : event.dataTransfer.getData('text/plain');
            } else {
                cpf = delegadoArrastado ? delegadoArrastado.dataset.cpf : event.dataTransfer.getData('text/plain');
            }
            
            const repId = event.currentTarget.dataset.repId;
            
            if (cpf && repId) {
                showLoading();
                atribuirDelegado(cpf, repId, event.currentTarget);
            }
            
            cleanupDrag();
        }
        
        function dropToRemove(event) {
            event.preventDefault();
            document.getElementById('dropZoneHint').classList.remove('drag-over');
            document.getElementById('dropZoneHint').style.display = 'none';
            
            const type = event.dataTransfer.getData('type');
            const cpf = event.dataTransfer.getData('text/plain');
            
            if (type === 'delegado_atribuido' && cpf) {
                // Encontrar a representação onde o delegado está
                const repCard = document.querySelector(`.delegado-info[data-cpf="${cpf}"]`)?.closest('.representacao-card');
                if (repCard) {
                    const repId = repCard.dataset.repId;
                    showLoading();
                    removerDelegado(repId, cpf, repCard);
                }
            }
            
            cleanupDrag();
        }
        
        function cleanupDrag() {
            if (delegadoArrastado) {
                delegadoArrastado.classList.remove('dragging');
                delegadoArrastado = null;
            }
            if (delegadoFromRep) {
                delegadoFromRep.classList.remove('dragging');
                delegadoFromRep = null;
            }
            
            document.getElementById('dropZoneHint').classList.remove('drag-over');
            document.getElementById('dropZoneHint').style.display = 'none';
        }
        
        function atribuirDelegado(cpf, repId, repElement) {
            const formData = new FormData();
            formData.append('action', 'atribuir_delegado');
            formData.append('cpf', cpf);
            formData.append('id_representacao', repId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na rede: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    // Atualizar visual da representação
                    repElement.classList.remove('empty');
                    repElement.classList.add('filled');
                    
                    repElement.innerHTML = `
                        <div class="rep-nome">
                            <i class="fas fa-flag"></i>
                            ${repElement.querySelector('.rep-nome')?.textContent.trim() || 'Representação'}
                        </div>
                        <div class="delegado-info" 
                             draggable="true" 
                             ondragstart="dragDelegadoFromRep(event)" 
                             data-cpf="${cpf}">
                            <div class="name">
                                <i class="fas fa-user"></i>
                                ${data.delegado.nome}
                            </div>
                            <div class="institution">
                                ${data.delegado.instituicao}
                            </div>
                            <div class="remove-hint">
                                <i class="fas fa-arrows-alt"></i> Arraste para a lista para remover
                            </div>
                        </div>
                    `;
                    
                    // Remover da lista de delegados (se estiver lá)
                    const delegadoCard = document.querySelector(`.delegado-card[data-cpf="${cpf}"]`);
                    if (delegadoCard) {
                        delegadoCard.remove();
                        
                        // Se não há mais delegados, mostrar mensagem
                        if (document.querySelectorAll('.delegado-card').length === 0) {
                            const delegadosList = document.getElementById('delegadosList');
                            delegadosList.innerHTML = `
                                <div class="empty-message">
                                    <i class="fas fa-check-circle fa-3x" style="color: var(--success); margin-bottom: 15px;"></i>
                                    <p>Todos os delegados aprovados já estão atribuídos!</p>
                                </div>
                            `;
                        }
                    }
                    
                    // Atualizar estatísticas via recarregamento suave
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                    
                    showSuccess('Delegado atribuído com sucesso!');
                } else {
                    showError(data.message || 'Erro ao atribuir delegado.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Erro:', error);
                showError('Erro de comunicação com o servidor: ' + error.message);
            });
        }
        
        function removerDelegado(repId, cpf, repElement) {
            const formData = new FormData();
            formData.append('action', 'remover_delegado');
            formData.append('id_representacao', repId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na rede: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    // Atualizar a representação para vazia
                    repElement.classList.remove('filled');
                    repElement.classList.add('empty');
                    
                    repElement.innerHTML = `
                        <div class="rep-nome">
                            <i class="fas fa-flag"></i>
                            ${repElement.querySelector('.rep-nome')?.textContent.trim() || 'Representação'}
                        </div>
                        <div class="empty-rep-message">
                            <i class="fas fa-arrow-down fa-lg"></i>
                            <span>Solte um delegado aqui</span>
                        </div>
                    `;
                    
                    // Recarregar para mostrar o delegado na lista novamente
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                    
                    showSuccess('Delegado removido da representação!');
                } else {
                    showError(data.message || 'Erro ao remover delegado.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Erro:', error);
                showError('Erro de comunicação com o servidor: ' + error.message);
            });
        }
        
        // Limpar classes de drag-over no final
        document.addEventListener('dragend', cleanupDrag);
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltips para as opções
            document.querySelectorAll('.opcao-tag').forEach(tag => {
                const title = tag.getAttribute('title');
                if (title) {
                    tag.addEventListener('mouseenter', function(e) {
                        // Você pode adicionar tooltips personalizados aqui se quiser
                    });
                }
            });
        });
    </script>
</body>
</html>