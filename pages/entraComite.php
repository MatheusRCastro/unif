<?php
session_start();
require_once 'php/conexao.php';

// Configurações de upload
$upload_dir = 'uploads/pagamentos/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

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
    $carregar_rep = isset($_POST['carregar_representacoes']);

    if (!$carregar_rep) {
        // Coletar dados do formulário
        $id_delegacao = isset($_POST['id_delegacao']) && $_POST['id_delegacao'] !== '' ? intval($_POST['id_delegacao']) : -1;
        
        $comite_desejado = isset($_POST['comite_desejado']) ? intval($_POST['comite_desejado']) : 0;
        $segunda_opcao_comite = isset($_POST['segunda_opcao_comite']) && $_POST['segunda_opcao_comite'] !== '' ? intval($_POST['segunda_opcao_comite']) : null;
        $terceira_opcao_comite = isset($_POST['terceira_opcao_comite']) && $_POST['terceira_opcao_comite'] !== '' ? intval($_POST['terceira_opcao_comite']) : null;
        $representacao_desejada = isset($_POST['representacao_desejada']) ? intval($_POST['representacao_desejada']) : 0;
        $segunda_opcao_representacao = isset($_POST['segunda_opcao_representacao']) && $_POST['segunda_opcao_representacao'] !== '' ? intval($_POST['segunda_opcao_representacao']) : null;
        $terceira_opcao_representacao = isset($_POST['terceira_opcao_representacao']) && $_POST['terceira_opcao_representacao'] !== '' ? intval($_POST['terceira_opcao_representacao']) : null;
        $justificativa = isset($_POST['justificativa']) ? trim($_POST['justificativa']) : '';

        $dados_preenchidos = [
            'id_delegacao' => $id_delegacao,
            'comite_desejado' => $comite_desejado,
            'segunda_opcao_comite' => $segunda_opcao_comite,
            'terceira_opcao_comite' => $terceira_opcao_comite,
            'representacao_desejada' => $representacao_desejada,
            'segunda_opcao_representacao' => $segunda_opcao_representacao,
            'terceira_opcao_representacao' => $terceira_opcao_representacao,
            'justificativa' => $justificativa
        ];

        // Validações
        if ($comite_desejado <= 0) {
            $erro = "Selecione um comitê desejado.";
        } elseif ($representacao_desejada <= 0) {
            $erro = "Selecione uma representação.";
        } elseif (empty($justificativa)) {
            $erro = "A justificativa é obrigatória.";
        } elseif (strlen($justificativa) < 20) {
            $erro = "A justificativa deve ter pelo menos 20 caracteres.";
        } else {
            // NOVA VERIFICAÇÃO: Verificar se já é diretor ou staff (pendente ou aprovado)
            $sql_verifica_diretor_staff = "
                SELECT 'diretor' as tipo, 
                       CASE WHEN aprovado = 1 THEN 'aprovado' ELSE 'pendente' END as status
                FROM diretor 
                WHERE cpf = ?
                UNION ALL
                SELECT 'staff' as tipo, 
                       status_inscricao as status
                FROM staff 
                WHERE cpf = ? AND status_inscricao IN ('pendente', 'aprovado')";
                
            $stmt_verifica_ds = $conn->prepare($sql_verifica_diretor_staff);
            $stmt_verifica_ds->bind_param("ss", $_SESSION['cpf'], $_SESSION['cpf']);
            $stmt_verifica_ds->execute();
            $result_verifica_ds = $stmt_verifica_ds->get_result();

            if ($result_verifica_ds->num_rows > 0) {
                $tipos = [];
                $statuses = [];
                while ($row = $result_verifica_ds->fetch_assoc()) {
                    $tipos[] = $row['tipo'];
                    $statuses[] = $row['status'];
                }
                
                // Criar mensagem personalizada
                $tipos_str = implode(" ou ", array_unique($tipos));
                $status_str = implode("/", array_unique($statuses));
                $erro = "Você já está inscrito como $tipos_str ($status_str) e não pode se inscrever como delegado.";
                $stmt_verifica_ds->close();
            } else {
                $stmt_verifica_ds->close();

                // Verificar se já está inscrito como delegado
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

                    // Verificar se a representação está disponível
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

                        // Verificar se a delegação existe (apenas se for diferente de -1)
                        if ($id_delegacao != -1) {
                            $sql_verifica_delegacao = "SELECT id_delegacao FROM delegacao WHERE id_delegacao = ? AND verificacao_delegacao = 'aprovado'";
                            $stmt_verifica_delegacao = $conn->prepare($sql_verifica_delegacao);
                            $stmt_verifica_delegacao->bind_param("i", $id_delegacao);
                            $stmt_verifica_delegacao->execute();
                            $result_verifica_delegacao = $stmt_verifica_delegacao->get_result();
                            
                            if ($result_verifica_delegacao->num_rows == 0) {
                                $erro = "A delegação selecionada não existe ou não está aprovada.";
                                $stmt_verifica_delegacao->close();
                                $id_delegacao = -1; // Reverter para -1 (sem delegação)
                                $dados_preenchidos['id_delegacao'] = -1;
                            } else {
                                $stmt_verifica_delegacao->close();
                            }
                        }

                        // Verificar se arquivo PDF foi enviado
                        if (!isset($_FILES['comprovante_pagamento']) || $_FILES['comprovante_pagamento']['error'] !== UPLOAD_ERR_OK) {
                            $erro = "É obrigatório enviar o comprovante de pagamento em formato PDF.";
                        } else {
                            // Validar arquivo PDF
                            $arquivo = $_FILES['comprovante_pagamento'];
                            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
                            
                            if ($extensao !== 'pdf') {
                                $erro = "O arquivo deve ser um PDF (.pdf).";
                            } elseif ($arquivo['size'] > 5242880) { // 5MB
                                $erro = "O arquivo PDF não pode ultrapassar 5MB.";
                            } elseif (!in_array($arquivo['type'], ['application/pdf', 'application/x-pdf'])) {
                                $erro = "O arquivo enviado não é um PDF válido.";
                            }
                        }

                        if (empty($erro)) {
                            // Processar upload do PDF
                            $nome_arquivo = 'pagamento_' . $_SESSION['cpf'] . '_' . time() . '.pdf';
                            $caminho_arquivo = $upload_dir . $nome_arquivo;
                            
                            if (move_uploaded_file($arquivo['tmp_name'], $caminho_arquivo)) {
                                // AGORA: Armazenar apenas o caminho relativo no banco
                                $caminho_relativo = 'uploads/pagamentos/' . $nome_arquivo;
                                
                                // CORREÇÃO: Se não há delegação, usar NULL em vez de -1
                                if ($id_delegacao == -1) {
                                    $id_delegacao_value = NULL; // Usar NULL para foreign key
                                    $status_delegacao = 'individual';
                                } else {
                                    $id_delegacao_value = $id_delegacao;
                                    $status_delegacao = 'pendente';
                                }
                                
                                // CORREÇÃO: Query única que funciona para ambos os casos
                                // Usar NULL quando não há delegação
                                $sql_inserir = "INSERT INTO delegado 
                                    (cpf, id_delegacao, aprovado_delegacao, comite_desejado, 
                                     primeira_op_representacao, segunda_op_representacao, 
                                     terceira_op_representacao, segunda_op_comite, terceira_op_comite,
                                     pdf_pagamento, status_pagamento) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')";
                                 
                                $stmt_inserir = $conn->prepare($sql_inserir);
                                
                                if ($stmt_inserir === false) {
                                    $erro = "Erro ao preparar consulta: " . $conn->error;
                                    unlink($caminho_arquivo);
                                } else {
                                    // Bind os parâmetros
                                    $stmt_inserir->bind_param(
                                        "sisiisiiis", // Alterado 'b' para 's' (string para caminho)
                                        $_SESSION['cpf'],           // 1 - s
                                        $id_delegacao_value,        // 2 - i (pode ser NULL)
                                        $status_delegacao,          // 3 - s
                                        $comite_desejado,           // 4 - i
                                        $representacao_desejada,    // 5 - i
                                        $segunda_opcao_representacao, // 6 - i
                                        $terceira_opcao_representacao, // 7 - i
                                        $segunda_opcao_comite,      // 8 - i
                                        $terceira_opcao_comite,     // 9 - i
                                        $caminho_relativo           // 10 - s (caminho do arquivo)
                                    );
                                    
                                    if ($stmt_inserir->execute()) {
                                        // Atualizar a representação
                                        $sql_atualizar_rep = "UPDATE representacao SET cpf_delegado = ? WHERE id_representacao = ?";
                                        $stmt_atualizar_rep = $conn->prepare($sql_atualizar_rep);
                                        
                                        if ($stmt_atualizar_rep === false) {
                                            $erro = "Erro ao preparar atualização: " . $conn->error;
                                            unlink($caminho_arquivo);
                                        } else {
                                            $stmt_atualizar_rep->bind_param("si", $_SESSION['cpf'], $representacao_desejada);
                                            
                                            if ($stmt_atualizar_rep->execute()) {
                                                $mensagem = "Inscrição realizada com sucesso! Seu comprovante de pagamento foi enviado para análise.";
                                                $dados_preenchidos = [];
                                            } else {
                                                $erro = "Erro ao atualizar representação: " . $stmt_atualizar_rep->error;
                                                unlink($caminho_arquivo);
                                            }
                                            $stmt_atualizar_rep->close();
                                        }
                                    } else {
                                        $erro = "Erro ao realizar inscrição: " . $stmt_inserir->error;
                                        unlink($caminho_arquivo);
                                    }
                                    $stmt_inserir->close();
                                }
                            } else {
                                $erro = "Erro ao fazer upload do comprovante de pagamento. Tente novamente.";
                            }
                        }
                    }
                }
            }
        }
    }
}

// Buscar dados para o formulário
if (isset($_SESSION["cpf"])) {
    $comites = array();
    $delegacoes = array();

    if ($conn && $conn->connect_error === null) {
        // Buscar comitês APROVADOS
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

        // Buscar delegações disponíveis
        $sql_delegacoes = "
            SELECT DISTINCT d.id_delegacao, d.nome as nome_delegacao, u.nome as responsavel
            FROM delegacao d
            INNER JOIN unif uf ON d.id_unif = uf.id_unif
            INNER JOIN usuario u ON d.cpf = u.cpf
            WHERE uf.id_unif = (
                SELECT id_unif 
                FROM unif 
                ORDER BY data_inicio_unif DESC 
                LIMIT 1
            )
            AND d.verificacao_delegacao = 'aprovado'
            ORDER BY d.nome";

        $result_delegacoes = $conn->query($sql_delegacoes);

        if ($result_delegacoes && $result_delegacoes->num_rows > 0) {
            while ($row = $result_delegacoes->fetch_assoc()) {
                $delegacoes[] = $row;
            }
        }

        // Verificar comitê selecionado
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
    <title>Inscrição - Comitê UNIF</title>
    <link rel="stylesheet" href="styles/global.css">
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary:rgb(28, 112, 28);
            --primary-dark:rgb(25, 196, 68);
            --secondary:rgb(14, 138, 35);
            --accent: #4cc9f0;
            --success: #38b000;
            --warning: #ff9e00;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #1a1a2e;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-bg: #ffffff;
            --gradient-primary: linear-gradient(135deg, #27ae60, #219653);
            --gradient-success: linear-gradient(135deg, #38b000 0%, #70e000 100%);
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 50px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Header Styles */
        .header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        .header p {
            color: var(--gray);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Tabs */
        .tabs-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .tabs {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .tab-btn {
            padding: 16px 40px;
            background: transparent;
            border: 2px solid var(--light-gray);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tab-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.1);
        }

        .tab-btn.active {
            background: var(--gradient-primary);
            color: white;
            border-color: transparent;
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
        }

        /* Main Form Card */
        .form-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 50px;
            margin-bottom: 50px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient-primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 60px;
        }

        /* Form Sections */
        .form-section {
            position: relative;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-gray);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #4361ee20 0%, #7209b720 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 30px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .required::after {
            content: " *";
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 18px 20px;
            background: var(--light);
            border: 2px solid transparent;
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
            color: var(--dark);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
            transform: translateY(-2px);
        }

        .form-control[readonly] {
            background: var(--light-gray);
            cursor: not-allowed;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' fill='%234361ee' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
            padding-right: 50px;
        }

        textarea.form-control {
            min-height: 140px;
            resize: vertical;
            line-height: 1.6;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            gap: 6px;
        }

        .badge-primary {
            background: linear-gradient(135deg, #4361ee20 0%, #7209b720 100%);
            color: var(--primary);
            border: 1px solid rgba(67, 97, 238, 0.2);
        }

        .badge-success {
            background: linear-gradient(135deg, #38b00020 0%, #70e00020 100%);
            color: var(--success);
            border: 1px solid rgba(56, 176, 0, 0.2);
        }

        .badge-info {
            background: linear-gradient(135deg, #4cc9f020 0%, #4895ef20 100%);
            color: var(--accent);
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        /* File Upload Styles */
        .file-upload-container {
            position: relative;
            margin-top: 25px;
        }

        .file-upload-label {
            display: block;
            padding: 50px 30px;
            background: var(--light);
            border: 3px dashed var(--light-gray);
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .file-upload-label:hover {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
            transform: translateY(-3px);
        }

        .file-upload-label.drag-over {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
            border-style: solid;
        }

        .file-upload-icon {
            font-size: 56px;
            color: var(--gray);
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .file-upload-label:hover .file-upload-icon {
            color: var(--primary);
        }

        .file-upload-text h4 {
            color: var(--dark);
            margin-bottom: 12px;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .file-upload-text p {
            color: var(--gray);
            margin-bottom: 25px;
            font-size: 1rem;
            line-height: 1.6;
        }

        .file-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
        }

        .file-input {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            z-index: -1;
        }

        .file-preview {
            display: none;
            margin-top: 25px;
            padding: 25px;
            background: var(--light);
            border-radius: var(--border-radius);
            border: 2px solid var(--light-gray);
            animation: slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .file-preview.show {
            display: block;
        }

        .file-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .file-preview-title {
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
        }

        .file-preview-title i {
            color: var(--success);
            font-size: 1.3rem;
        }

        .file-remove {
            background: transparent;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1.3rem;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-remove:hover {
            background: rgba(230, 57, 70, 0.1);
            transform: rotate(90deg);
        }

        .file-details {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .file-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 6px;
            font-size: 1.1rem;
            word-break: break-all;
        }

        .file-size {
            color: var(--gray);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-status {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .file-status.ready {
            background: linear-gradient(135deg, #38b00020 0%, #70e00020 100%);
            color: var(--success);
            border: 1px solid rgba(56, 176, 0, 0.2);
        }

        .file-requirements {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 25px;
            margin-top: 25px;
            border-left: 4px solid var(--primary);
        }

        .file-requirements h5 {
            color: var(--dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
        }

        .file-requirements ul {
            list-style: none;
            padding-left: 0;
        }

        .file-requirements li {
            margin-bottom: 12px;
            color: var(--gray);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            line-height: 1.5;
        }

        .file-requirements li i {
            color: var(--success);
            font-size: 0.9rem;
        }

        /* Info Text */
        .info-text {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 10px;
            line-height: 1.5;
        }

        .info-text i {
            color: var(--primary);
            font-size: 1rem;
        }

        .warning-text {
            color: var(--danger);
            font-weight: 600;
            padding: 12px 20px;
            background: rgba(230, 57, 70, 0.1);
            border-radius: var(--border-radius-sm);
            border-left: 4px solid var(--danger);
        }

        /* Representation Card */
        .representations-grid {
            margin-top: 20px;
        }

        .representation-card {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 30px;
            border-left: 4px solid var(--primary);
            margin-bottom: 30px;
            transition: var(--transition);
        }

        .representation-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .representation-card h4 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .representation-card h4 i {
            color: var(--secondary);
        }

        /* Delegation Info */
        .delegation-info {
            background: linear-gradient(135deg, #4cc9f010 0%, #4895ef10 100%);
            border-radius: var(--border-radius-sm);
            padding: 20px;
            margin-top: 15px;
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        /* Submit Section */
        .submit-section {
            text-align: center;
            margin-top: 60px;
            padding-top: 40px;
            border-top: 2px solid var(--light-gray);
        }

        .submit-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 20px 60px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(67, 97, 238, 0.4);
        }

        .submit-btn:hover:not(:disabled)::before {
            left: 100%;
        }

        .submit-btn:active:not(:disabled) {
            transform: translateY(-2px);
        }

        .submit-btn:disabled {
            background: var(--light-gray);
            color: var(--gray);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Logo */
        .logo-container {
            text-align: center;
            margin-top: 60px;
            opacity: 0.7;
            transition: var(--transition);
        }

        .logo-container:hover {
            opacity: 1;
        }

        .logo {
            max-width: 180px;
            height: auto;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.1));
        }

        /* Messages */
        .message-container {
            max-width: 700px;
            margin: 80px auto;
            padding: 50px;
            border-radius: var(--border-radius);
            text-align: center;
            animation: slideIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-success {
            background: linear-gradient(135deg, #38b00010 0%, #70e00010 100%);
            border: 2px solid rgba(56, 176, 0, 0.2);
            color: var(--success);
        }

        .message-error {
            background: linear-gradient(135deg, #e6394610 0%, #f7258510 100%);
            border: 2px solid rgba(230, 57, 70, 0.2);
            color: var(--danger);
        }

        .message-container i {
            font-size: 4rem;
            margin-bottom: 25px;
        }

        .message-container h3 {
            margin-bottom: 20px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .message-container p {
            margin-bottom: 25px;
            line-height: 1.7;
            font-size: 1.1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.3);
            transition: var(--transition);
            border: 2px solid currentColor;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateX(-8px);
        }

        /* Not Authenticated */
        .not-authenticated {
            text-align: center;
            padding: 100px 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .not-authenticated h2 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .not-authenticated p {
            color: var(--gray);
            margin-bottom: 40px;
            font-size: 1.2rem;
            line-height: 1.7;
        }

        .auth-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: var(--gradient-primary);
            color: white;
            padding: 18px 36px;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
        }

        .auth-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.4);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .container {
                padding: 20px;
            }
            
            .form-card {
                padding: 30px;
            }
            
            .header h1 {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                width: 100%;
                justify-content: center;
            }
            
            .file-upload-label {
                padding: 30px 20px;
            }
            
            .file-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .section-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .submit-btn {
                width: 100%;
                justify-content: center;
                padding: 20px;
            }
        }

        /* Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if (isset($_SESSION["cpf"])): ?>
            
            <?php if ($erro): ?>
                <div class="message-container message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Erro na Inscrição</h3>
                    <p><?php echo htmlspecialchars($erro); ?></p>
                    <a href="entraComite.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Voltar e corrigir
                    </a>
                </div>
                
            <?php elseif ($mensagem): ?>
                <div class="message-container message-success">
                    <i class="fas fa-check-circle pulse"></i>
                    <h3>Inscrição Enviada com Sucesso!</h3>
                    <p><?php echo htmlspecialchars($mensagem); ?></p>
                    <div class="info-text" style="justify-content: center; margin-top: 20px;">
                        <i class="fas fa-info-circle"></i>
                        Seu comprovante de pagamento será analisado pela equipe administrativa.
                    </div>
                    <?php if (isset($id_delegacao) && $id_delegacao != -1): ?>
                        <div class="info-text" style="justify-content: center;">
                            <i class="fas fa-users"></i>
                            Inscrito como parte de uma delegação
                        </div>
                    <?php else: ?>
                        <div class="info-text" style="justify-content: center;">
                            <i class="fas fa-user"></i>
                            Inscrição individual realizada
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 30px;">
                        <a href="inicio.php" class="back-link">
                            <i class="fas fa-home"></i> Ir para início
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="header">
                    <h1><i class="fas fa-users"></i> Inscrição em Comitê</h1>
                    <p>Preencha o formulário abaixo para participar da UNIF</p>
                </div>

                <div class="tabs-container">
                    <div class="tabs">
                        <button class="tab-btn" onclick="window.location.href='criarDelegacao.php'">
                            <i class="fas fa-plus-circle"></i> Criar Delegação
                        </button>
                        <button class="tab-btn active">
                            <i class="fas fa-user-graduate"></i> Fazer Inscrição
                        </button>
                    </div>
                </div>

                <form class="form-card" id="formInscricao" method="POST" action="" enctype="multipart/form-data">
                    <div class="form-grid">
                        <!-- Coluna Esquerda - Delegação -->
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-school"></i>
                                </div>
                                <h3 class="section-title">Associação com Delegação</h3>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-users"></i>
                                    Delegação (Opcional)
                                </label>
                                <select name="id_delegacao" id="id_delegacao" class="form-control">
                                    <option value="-1">Nenhuma delegação (inscrição individual)</option>
                                    <?php if (!empty($delegacoes)): ?>
                                        <?php foreach ($delegacoes as $delegacao): ?>
                                            <option value="<?php echo $delegacao['id_delegacao']; ?>"
                                                <?php echo (isset($dados_preenchidos['id_delegacao']) && $dados_preenchidos['id_delegacao'] == $delegacao['id_delegacao']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($delegacao['nome_delegacao']); ?>
                                                <span class="badge badge-info">Delegação</span>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                
                                <?php if (isset($dados_preenchidos['id_delegacao']) && $dados_preenchidos['id_delegacao'] != -1): 
                                    $delegacao_selecionada = array_filter($delegacoes, function ($d) use ($dados_preenchidos) {
                                        return $d['id_delegacao'] == $dados_preenchidos['id_delegacao'];
                                    });
                                    $delegacao_selecionada = reset($delegacao_selecionada);
                                ?>
                                    <div class="delegation-info">
                                        <div class="info-text">
                                            <i class="fas fa-user-tie"></i>
                                            <strong>Responsável:</strong> <?php echo htmlspecialchars($delegacao_selecionada['responsavel']); ?>
                                        </div>
                                        <div class="info-text">
                                            <i class="fas fa-check-circle"></i>
                                            Delegação aprovada
                                        </div>
                                    </div>
                                <?php elseif (isset($dados_preenchidos['id_delegacao']) && $dados_preenchidos['id_delegacao'] == -1): ?>
                                    <div class="delegation-info">
                                        <div class="info-text">
                                            <i class="fas fa-user"></i>
                                            <strong>Inscrição Individual:</strong> Você fará parte do comitê sem associação a uma delegação
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="info-text">
                                    <i class="fas fa-lightbulb"></i>
                                    Escolha uma delegação para se associar a uma escola/grupo, ou selecione "Nenhuma delegação" para inscrição individual
                                </div>
                            </div>
                            
                            <div class="info-text" style="margin-top: 20px;">
                                <i class="fas fa-plus-circle"></i>
                                <a href="criarDelegacao.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                    Criar nova delegação
                                </a>
                            </div>
                        </div>

                        <!-- Coluna Direita - Dados do Aluno -->
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <h3 class="section-title">Dados do Aluno</h3>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required">
                                    <i class="fas fa-id-card"></i>
                                    CPF
                                </label>
                                <input type="text" name="cpf" class="form-control" value="<?php echo htmlspecialchars($_SESSION['cpf']); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required">
                                    <i class="fas fa-clipboard-list"></i>
                                    Comitê Desejado
                                </label>
                                <select name="comite_desejado" id="comite_desejado" class="form-control" required onchange="this.form.submit()">
                                    <option value="">Selecione um comitê...</option>
                                    <?php foreach ($comites as $comite): ?>
                                        <option value="<?php echo $comite['id_comite']; ?>"
                                            <?php echo ($comite_selecionado == $comite['id_comite']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($comite['tipo_comite']); ?></span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-list-ol"></i>
                                    Segunda Opção de Comitê (Opcional)
                                </label>
                                <select name="segunda_opcao_comite" id="segunda_opcao_comite" class="form-control">
                                    <option value="">Selecione uma segunda opção...</option>
                                    <?php foreach ($comites as $comite): ?>
                                        <option value="<?php echo $comite['id_comite']; ?>"
                                            <?php echo (isset($dados_preenchidos['segunda_opcao_comite']) && $dados_preenchidos['segunda_opcao_comite'] == $comite['id_comite']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-list-ol"></i>
                                    Terceira Opção de Comitê (Opcional)
                                </label>
                                <select name="terceira_opcao_comite" id="terceira_opcao_comite" class="form-control">
                                    <option value="">Selecione uma terceira opção...</option>
                                    <?php foreach ($comites as $comite): ?>
                                        <option value="<?php echo $comite['id_comite']; ?>"
                                            <?php echo (isset($dados_preenchidos['terceira_opcao_comite']) && $dados_preenchidos['terceira_opcao_comite'] == $comite['id_comite']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comite['nome_comite']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Representações -->
                    <div class="form-section" style="margin-top: 40px;">
                        <div class="representations-grid">
                            <div class="representation-card">
                                <h4><i class="fas fa-flag"></i> Representações Disponíveis</h4>
                                
                                <?php if ($comite_selecionado): ?>
                                    <?php if (!empty($representacoes_comite)): ?>
                                        <div class="info-text">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo count($representacoes_comite); ?> representação(ões) disponível(is)
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label required">
                                                <i class="fas fa-medal"></i>
                                                Primeira Opção
                                            </label>
                                            <select name="representacao_desejada" id="representacao_desejada" class="form-control" required>
                                                <option value="">Selecione uma representação...</option>
                                                <?php foreach ($representacoes_comite as $rep): ?>
                                                    <option value="<?php echo $rep['id_representacao']; ?>"
                                                        <?php echo (isset($dados_preenchidos['representacao_desejada']) && $dados_preenchidos['representacao_desejada'] == $rep['id_representacao']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-list-ol"></i>
                                                Segunda Opção (Opcional)
                                            </label>
                                            <select name="segunda_opcao_representacao" id="segunda_opcao_representacao" class="form-control">
                                                <option value="">Selecione uma segunda opção...</option>
                                                <?php foreach ($representacoes_comite as $rep): ?>
                                                    <option value="<?php echo $rep['id_representacao']; ?>"
                                                        <?php echo (isset($dados_preenchidos['segunda_opcao_representacao']) && $dados_preenchidos['segunda_opcao_representacao'] == $rep['id_representacao']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-list-ol"></i>
                                                Terceira Opção (Opcional)
                                            </label>
                                            <select name="terceira_opcao_representacao" id="terceira_opcao_representacao" class="form-control">
                                                <option value="">Selecione uma terceira opção...</option>
                                                <?php foreach ($representacoes_comite as $rep): ?>
                                                    <option value="<?php echo $rep['id_representacao']; ?>"
                                                        <?php echo (isset($dados_preenchidos['terceira_opcao_representacao']) && $dados_preenchidos['terceira_opcao_representacao'] == $rep['id_representacao']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($rep['nome_representacao']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <div class="info-text" style="color: var(--warning);">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Não há representações disponíveis para este comitê no momento
                                        </div>
                                        <input type="hidden" name="representacao_desejada" value="0">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="info-text">
                                        <i class="fas fa-info-circle"></i>
                                        Selecione um comitê primeiro para ver as representações disponíveis
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-edit"></i>
                                Justificativa da Escolha
                            </label>
                            <textarea name="justificativa" class="form-control" placeholder="Explique por que escolheu esta representação (mínimo 20 caracteres)..."><?php echo isset($dados_preenchidos['justificativa']) ? htmlspecialchars($dados_preenchidos['justificativa']) : ''; ?></textarea>
                            <div class="info-text">
                                <i class="fas fa-clipboard-check"></i>
                                Sua justificativa será avaliada pela equipe organizadora
                            </div>
                        </div>
                    </div>

                    <!-- Upload do Comprovante de Pagamento -->
                    <div class="form-section" style="margin-top: 40px;">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <h3 class="section-title">Comprovante de Pagamento</h3>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-file-pdf"></i>
                                Comprovante de Pagamento (PDF)
                            </label>
                            
                            <div class="file-upload-container">
                                <label for="comprovante_pagamento" class="file-upload-label" id="fileUploadLabel">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <h4>Envie seu comprovante de pagamento</h4>
                                        <p>Clique para selecionar ou arraste um arquivo PDF</p>
                                        <span class="file-upload-btn">
                                            <i class="fas fa-cloud-upload-alt"></i> Selecionar Arquivo
                                        </span>
                                    </div>
                                </label>
                                
                                <input type="file" name="comprovante_pagamento" id="comprovante_pagamento" class="file-input" accept=".pdf" required>
                                
                                <div class="file-preview" id="filePreview">
                                    <div class="file-preview-header">
                                        <div class="file-preview-title">
                                            <i class="fas fa-check-circle"></i>
                                            Arquivo Selecionado
                                        </div>
                                        <button type="button" class="file-remove" id="fileRemove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="file-details">
                                        <div class="file-icon">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name" id="fileName"></div>
                                            <div class="file-size" id="fileSize"></div>
                                        </div>
                                        <div class="file-status ready" id="fileStatus">Pronto para envio</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="file-requirements">
                                <h5><i class="fas fa-info-circle"></i> Requisitos do arquivo:</h5>
                                <ul>
                                    <li><i class="fas fa-check"></i> Formato: PDF (.pdf) apenas</li>
                                    <li><i class="fas fa-check"></i> Tamanho máximo: 5MB</li>
                                    <li><i class="fas fa-check"></i> O arquivo deve conter seu nome e CPF</li>
                                    <li><i class="fas fa-check"></i> Comprovante deve ser legível</li>
                                </ul>
                            </div>
                            
                            <div class="warning-text">
                                <i class="fas fa-exclamation-triangle"></i>
                                A inscrição só será processada após a análise do comprovante de pagamento.
                            </div>
                        </div>
                    </div>
                    
                    <div class="submit-section">
                        <button type="button" class="submit-btn" id="submitBtn" onclick="enviarFormulario()">
                            <i class="fas fa-paper-plane"></i> SUBMETER INSCRIÇÃO
                        </button>
                        <div class="info-text" style="justify-content: center; margin-top: 20px;">
                            <i class="fas fa-shield-alt"></i>
                            Seus dados estão seguros e serão usados apenas para fins de inscrição
                        </div>
                    </div>
                    
                    <input type="hidden" name="carregar_representacoes" value="1">
                </form>
                
                <div class="logo-container">
                    <img src="images/unif.png" alt="Logo UNIF" class="logo">
                </div>
                
            <?php endif; ?>
            
        <?php else: ?>
            <div class="not-authenticated">
                <h2>Acesso Restrito</h2>
                <p>Você precisa estar autenticado para realizar a inscrição.</p>
                <a href="login.html" class="auth-link">
                    <i class="fas fa-sign-in-alt"></i> Fazer Login
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Variáveis para controle do upload
        let arquivoSelecionado = null;
        const tamanhoMaximoMB = 5;
        
        function formatarTamanho(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function atualizarBotaoEnvio() {
            const submitBtn = document.getElementById('submitBtn');
            const arquivoValido = arquivoSelecionado && arquivoSelecionado.type === 'application/pdf' && arquivoSelecionado.size <= tamanhoMaximoMB * 1024 * 1024;
            
            // Também verificar os campos obrigatórios
            const comiteDesejado = document.getElementById('comite_desejado').value;
            const representacao = document.getElementById('representacao_desejada') ? document.getElementById('representacao_desejada').value : '';
            const justificativa = document.querySelector('textarea[name="justificativa"]').value.trim();
            
            const camposValidos = comiteDesejado && representacao && justificativa.length >= 20;
            
            submitBtn.disabled = !(arquivoValido && camposValidos);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('comprovante_pagamento');
            const fileUploadLabel = document.getElementById('fileUploadLabel');
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileRemove = document.getElementById('fileRemove');
            
            // Evento para selecionar arquivo
            fileInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    arquivoSelecionado = this.files[0];
                    
                    // Validar arquivo
                    if (arquivoSelecionado.type !== 'application/pdf') {
                        alert('Por favor, selecione apenas arquivos PDF.');
                        this.value = '';
                        arquivoSelecionado = null;
                        filePreview.classList.remove('show');
                        atualizarBotaoEnvio();
                        return;
                    }
                    
                    if (arquivoSelecionado.size > tamanhoMaximoMB * 1024 * 1024) {
                        alert(`O arquivo é muito grande. Tamanho máximo: ${tamanhoMaximoMB}MB`);
                        this.value = '';
                        arquivoSelecionado = null;
                        filePreview.classList.remove('show');
                        atualizarBotaoEnvio();
                        return;
                    }
                    
                    // Atualizar preview
                    fileName.textContent = arquivoSelecionado.name;
                    fileSize.textContent = formatarTamanho(arquivoSelecionado.size);
                    filePreview.classList.add('show');
                    
                    atualizarBotaoEnvio();
                }
            });
            
            // Evento para remover arquivo
            fileRemove.addEventListener('click', function() {
                fileInput.value = '';
                arquivoSelecionado = null;
                filePreview.classList.remove('show');
                atualizarBotaoEnvio();
            });
            
            // Drag and drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileUploadLabel.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                fileUploadLabel.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                fileUploadLabel.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                fileUploadLabel.classList.add('drag-over');
            }
            
            function unhighlight() {
                fileUploadLabel.classList.remove('drag-over');
            }
            
            fileUploadLabel.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
            
            // Verificar campos obrigatórios em tempo real
            const camposObservar = [
                document.getElementById('comite_desejado'),
                document.getElementById('representacao_desejada'),
                document.querySelector('textarea[name="justificativa"]')
            ];
            
            camposObservar.forEach(campo => {
                if (campo) {
                    campo.addEventListener('input', atualizarBotaoEnvio);
                    campo.addEventListener('change', atualizarBotaoEnvio);
                }
            });
            
            // Evento para carregar representações ao mudar comitê
            const comiteSelect = document.getElementById('comite_desejado');
            if (comiteSelect) {
                comiteSelect.addEventListener('change', function() {
                    console.log('Carregando representações para o comitê selecionado...');
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
                    
                    setTimeout(function() {
                        document.getElementById('formInscricao').submit();
                    }, 300);
                });
            }
            
            // Validação em tempo real para representações
            const primeiraRep = document.getElementById('representacao_desejada');
            const segundaRep = document.getElementById('segunda_opcao_representacao');
            const terceiraRep = document.getElementById('terceira_opcao_representacao');
            
            function validarRepresentacoes() {
                if (primeiraRep && segundaRep && primeiraRep.value && segundaRep.value && primeiraRep.value === segundaRep.value) {
                    segundaRep.style.borderColor = '#e63946';
                } else if (segundaRep) {
                    segundaRep.style.borderColor = '';
                }
                
                if (primeiraRep && terceiraRep && primeiraRep.value && terceiraRep.value && primeiraRep.value === terceiraRep.value) {
                    terceiraRep.style.borderColor = '#e63946';
                } else if (terceiraRep) {
                    terceiraRep.style.borderColor = '';
                }
                
                if (segundaRep && terceiraRep && segundaRep.value && terceiraRep.value && segundaRep.value === terceiraRep.value) {
                    terceiraRep.style.borderColor = '#e63946';
                } else if (terceiraRep) {
                    terceiraRep.style.borderColor = '';
                }
                
                atualizarBotaoEnvio();
            }
            
            if (primeiraRep) primeiraRep.addEventListener('change', validarRepresentacoes);
            if (segundaRep) segundaRep.addEventListener('change', validarRepresentacoes);
            if (terceiraRep) terceiraRep.addEventListener('change', validarRepresentacoes);
            
            // Remover bordas vermelhas ao digitar
            const inputs = document.querySelectorAll('select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.style.borderColor = '';
                    atualizarBotaoEnvio();
                });
            });
            
            // Aplicar estilo ao carregar a página
            const delegaçãoSelect = document.getElementById('id_delegacao');
            if (delegaçãoSelect && delegaçãoSelect.value == '-1') {
                delegaçãoSelect.style.borderLeft = '4px solid var(--accent)';
            }
            
            // Mudar estilo da delegação baseado na seleção
            if (delegaçãoSelect) {
                delegaçãoSelect.addEventListener('change', function() {
                    if (this.value == '-1') {
                        this.style.borderLeft = '4px solid var(--accent)';
                    } else {
                        this.style.borderLeft = '4px solid var(--primary)';
                    }
                });
            }
            
            // Inicializar estado do botão
            atualizarBotaoEnvio();
        });
        
        function enviarFormulario() {
            console.log('Validando formulário...');
            
            // Remove campo hidden antes de enviar
            const hiddenField = document.querySelector('input[name="carregar_representacoes"]');
            if (hiddenField) {
                hiddenField.remove();
            }
            
            // Validações
            const comiteDesejado = document.getElementById('comite_desejado').value;
            const representacao = document.getElementById('representacao_desejada') ? document.getElementById('representacao_desejada').value : '';
            const segundaRepresentacao = document.getElementById('segunda_opcao_representacao') ? document.getElementById('segunda_opcao_representacao').value : '';
            const terceiraRepresentacao = document.getElementById('terceira_opcao_representacao') ? document.getElementById('terceira_opcao_representacao').value : '';
            const justificativa = document.querySelector('textarea[name="justificativa"]').value.trim();
            const arquivoInput = document.getElementById('comprovante_pagamento');
            
            let erros = [];
            
            if (!comiteDesejado) {
                erros.push("Selecione um comitê desejado!");
                document.getElementById('comite_desejado').classList.add('error');
            }
            
            if (!representacao || representacao === "") {
                erros.push("Selecione uma representação como primeira opção!");
                if (document.getElementById('representacao_desejada')) {
                    document.getElementById('representacao_desejada').classList.add('error');
                }
            }
            
            // Validar opções diferentes
            if (representacao && segundaRepresentacao && representacao === segundaRepresentacao) {
                erros.push("A primeira e segunda opções de representação não podem ser iguais!");
            }
            
            if (representacao && terceiraRepresentacao && representacao === terceiraRepresentacao) {
                erros.push("A primeira e terceira opções de representação não podem ser iguais!");
            }
            
            if (segundaRepresentacao && terceiraRepresentacao && segundaRepresentacao === terceiraRepresentacao) {
                erros.push("A segunda e terceira opções de representação não podem ser iguais!");
            }
            
            if (!justificativa || justificativa.length < 20) {
                erros.push("A justificativa deve ter pelo menos 20 caracteres!");
                document.querySelector('textarea[name="justificativa"]').classList.add('error');
            }
            
            // Validar arquivo PDF
            if (!arquivoInput.files.length) {
                erros.push("É obrigatório enviar o comprovante de pagamento em formato PDF!");
                document.getElementById('fileUploadLabel').style.borderColor = '#e63946';
            } else {
                const arquivo = arquivoInput.files[0];
                if (arquivo.type !== 'application/pdf') {
                    erros.push("O arquivo deve ser um PDF (.pdf).");
                    document.getElementById('fileUploadLabel').style.borderColor = '#e63946';
                }
                if (arquivo.size > 5 * 1024 * 1024) {
                    erros.push("O arquivo PDF não pode ultrapassar 5MB.");
                    document.getElementById('fileUploadLabel').style.borderColor = '#e63946';
                }
            }
            
            if (erros.length > 0) {
                alert("Por favor, corrija os seguintes erros:\n\n" + erros.join('\n'));
                return false;
            }
            
            const delegaçãoSelect = document.getElementById('id_delegacao');
            const delegaçãoValor = delegaçãoSelect ? delegaçãoSelect.value : '-1';
            const delegaçãoTexto = delegaçãoSelect ? delegaçãoSelect.options[delegaçãoSelect.selectedIndex].text : 'Nenhuma delegação';
            
            let confirmMessage = 'Deseja enviar sua inscrição?\n\n';
            confirmMessage += 'Comitê: ' + document.getElementById('comite_desejado').options[document.getElementById('comite_desejado').selectedIndex].text + '\n';
            confirmMessage += 'Delegação: ' + delegaçãoTexto + '\n';
            confirmMessage += 'Arquivo: ' + arquivoInput.files[0].name + '\n';
            confirmMessage += '\nApós o envio, não será possível alterar os dados.\n';
            confirmMessage += 'Seu comprovante de pagamento será analisado pela equipe.';
            
            if (confirm(confirmMessage)) {
                console.log('Enviando formulário...');
                document.getElementById('formInscricao').submit();
            }
        }
    </script>
</body>
</html>

<?php
// Fechar conexão se existir
if ($conn && $conn->connect_error === null) {
    $conn->close();
}
?>