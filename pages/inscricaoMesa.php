<!DOCTYPE html>
<?php
session_start();
require_once 'php/conexao.php';

// Função para formatar CPF (adiciona pontos e traço)
function formatarCPF($cpf)
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11) {
        return $cpf; // Retorna como está se não tiver 11 dígitos
    }
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

// Função para validar CPF
function validarCPF($cpf)
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);

    if (strlen($cpf) != 11) {
        return false;
    }

    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    // Validação do CPF
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

// Função para validar e converter data para formato MySQL
function validarDataParaMySQL($data_input)
{
    // Se for apenas ano (ex: "1945")
    if (preg_match('/^\d{4}$/', $data_input)) {
        return $data_input . '-01-01'; // Primeiro dia do ano
    }
    
    // Se for formato brasileiro (ex: "01/01/1945")
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data_input)) {
        $data_obj = DateTime::createFromFormat('d/m/Y', $data_input);
        if ($data_obj) {
            return $data_obj->format('Y-m-d');
        }
    }
    
    // Se for formato americano (ex: "1945-01-01")
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_input)) {
        return $data_input; // Já está no formato correto
    }
    
    return false; // Formato inválido
}

// Verificar se o usuário está autenticado
if (!isset($_SESSION["cpf"])) {
    header("Location: login.html");
    exit();
}

$cpf_logado = $_SESSION["cpf"]; // CPF já formatado (com pontos e traço)
$mensagem = "";
$erro = "";
$dados_preenchidos = [];

// Obter o UNIF mais recente
$sql_unif = "SELECT id_unif, data_inicio_unif, data_fim_unif,
                    data_inicio_inscricao_comite, data_fim_inscricao_comite
             FROM unif 
             ORDER BY data_inicio_unif DESC 
             LIMIT 1";
$result_unif = $conn->query($sql_unif);

if ($result_unif->num_rows == 0) {
    $erro = "Não há UNIF cadastrado no momento.";
    $id_unif = null;
} else {
    $unif = $result_unif->fetch_assoc();
    $id_unif = $unif['id_unif'];
    $data_inicio_inscricao = $unif['data_inicio_inscricao_comite'];
    $data_fim_inscricao = $unif['data_fim_inscricao_comite'];

    // Verificar se estamos dentro do período de inscrição de comitês
    $data_atual = date('Y-m-d');

    if ($data_atual < $data_inicio_inscricao) {
        $erro = "As inscrições para comitês começam em " . date('d/m/Y', strtotime($data_inicio_inscricao));
    } elseif ($data_atual > $data_fim_inscricao) {
        $erro = "As inscrições para comitês encerraram em " . date('d/m/Y', strtotime($data_fim_inscricao));
    } else {
        // Verificar se o usuário logado já é staff APROVADO no UNIF atual
        // CPF já está formatado no banco, então usamos direto
        $sql_verifica_staff = "SELECT * FROM staff WHERE cpf = ? AND id_unif = ? AND status_inscricao = 'aprovado'";
        $stmt_staff = $conn->prepare($sql_verifica_staff);
        $stmt_staff->bind_param("si", $cpf_logado, $id_unif);
        $stmt_staff->execute();
        $result_staff = $stmt_staff->get_result();

        if ($result_staff->num_rows > 0) {
            $erro = "Você já é staff aprovado para este UNIF. Não é possível se inscrever como diretor.";
        }
        $stmt_staff->close();

        // Verificar se o usuário logado já é delegado no UNIF atual
        if (empty($erro)) {
            $sql_verifica_delegado = "SELECT * FROM delegacao WHERE cpf = ? AND id_unif = ?";
            $stmt_delegado = $conn->prepare($sql_verifica_delegado);
            $stmt_delegado->bind_param("si", $cpf_logado, $id_unif);
            $stmt_delegado->execute();
            $result_delegado = $stmt_delegado->get_result();

            if ($result_delegado->num_rows > 0) {
                $erro = "Você já está inscrito como delegado para este UNIF. Não é possível se inscrever como diretor.";
            }
            $stmt_delegado->close();
        }

        // Verificar se o usuário já é diretor (aprovado ou pendente) no UNIF atual
        if (empty($erro)) {
            $sql_verifica_diretor = "SELECT d.*, c.nome_comite 
                                    FROM diretor d 
                                    JOIN comite c ON d.id_comite = c.id_comite 
                                    WHERE d.cpf = ? AND c.id_unif = ?";
            $stmt_diretor = $conn->prepare($sql_verifica_diretor);
            $stmt_diretor->bind_param("si", $cpf_logado, $id_unif);
            $stmt_diretor->execute();
            $result_diretor = $stmt_diretor->get_result();

            if ($result_diretor->num_rows > 0) {
                $diretor_data = $result_diretor->fetch_assoc();
                $erro = "Você já está inscrito como diretor do comitê '{$diretor_data['nome_comite']}' para este UNIF! Um diretor só pode participar de um comitê.";
            }
            $stmt_diretor->close();
        }
    }
}

// Processar o formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($erro)) {
    // Coletar e limpar dados do formulário
    $diretor1_cpf = trim($_POST['diretor1_cpf'] ?? '');
    $diretor2_cpf = trim($_POST['diretor2_cpf'] ?? '');
    $diretor3_cpf = trim($_POST['diretor3_cpf'] ?? '');
    $tipo_comite = trim($_POST['tipo_comite'] ?? '');
    $nome_comite = trim($_POST['nome_comite'] ?? '');
    $num_delegados = intval($_POST['num_delegados'] ?? 0);
    $data_historica_input = trim($_POST['data_historica'] ?? '');
    $descricao_comite = trim($_POST['descricao_comite'] ?? '');

    // Salvar dados para preenchimento automático em caso de erro
    $dados_preenchidos = [
        'diretor1_cpf' => $diretor1_cpf,
        'diretor2_cpf' => $diretor2_cpf,
        'diretor3_cpf' => $diretor3_cpf,
        'tipo_comite' => $tipo_comite,
        'nome_comite' => $nome_comite,
        'num_delegados' => $num_delegados,
        'data_historica' => $data_historica_input,
        'descricao_comite' => $descricao_comite
    ];

    // Formatar CPFs do formulário para o padrão do banco
    $diretor1_cpf_formatado = formatarCPF($diretor1_cpf);
    $diretor2_cpf_formatado = $diretor2_cpf ? formatarCPF($diretor2_cpf) : null;
    $diretor3_cpf_formatado = $diretor3_cpf ? formatarCPF($diretor3_cpf) : null;

    // Para validação matemática, usamos CPF limpo
    $diretor1_cpf_limpo = preg_replace('/[^0-9]/', '', $diretor1_cpf);

    // DEBUG: Mostrar CPFs para verificação
    error_log("CPF Login (sessão): $cpf_logado");
    error_log("CPF Diretor 1 (form): $diretor1_cpf");
    error_log("CPF Diretor 1 (formatado): $diretor1_cpf_formatado");

    // Validações básicas
    if (empty($diretor1_cpf)) {
        $erro = "CPF do Diretor 1 é obrigatório.";
    } elseif (!validarCPF($diretor1_cpf_limpo)) {
        $erro = "CPF do Diretor 1 inválido.";
    } elseif (empty($tipo_comite)) {
        $erro = "Tipo do Comitê é obrigatório.";
    } elseif (empty($nome_comite)) {
        $erro = "Nome do Comitê é obrigatório.";
    } elseif ($num_delegados < 1) {
        $erro = "Número de delegados deve ser pelo menos 1.";
    } elseif (empty($data_historica_input)) {
        $erro = "Data histórica é obrigatória.";
    } elseif (strlen($descricao_comite) < 50) {
        $erro = "Descrição do comitê deve ter pelo menos 50 caracteres.";
    } else {
        // VALIDAÇÃO DA DATA HISTÓRICA - Converter para formato MySQL
        $data_comite = validarDataParaMySQL($data_historica_input);
        if (!$data_comite) {
            $erro = "Formato de data inválido. Use: AAAA (ex: 1945) ou DD/MM/AAAA (ex: 01/01/1945) ou AAAA-MM-DD (ex: 1945-01-01)";
        }

        // Verificar se os diretores existem no sistema
        if (empty($erro)) {
            $diretores_cpfs = array_filter([$diretor1_cpf_formatado, $diretor2_cpf_formatado, $diretor3_cpf_formatado]);

            foreach ($diretores_cpfs as $cpf_formatado) {
                // Buscar no banco com CPF formatado (igual ao que está armazenado)
                $sql_verifica_usuario = "SELECT cpf, nome FROM usuario WHERE cpf = ?";
                $stmt_usuario = $conn->prepare($sql_verifica_usuario);
                $stmt_usuario->bind_param("s", $cpf_formatado);
                $stmt_usuario->execute();
                $result_usuario = $stmt_usuario->get_result();

                if ($result_usuario->num_rows == 0) {
                    $erro = "CPF $cpf_formatado não encontrado no sistema. Todos os diretores devem estar cadastrados.";
                    break;
                } else {
                    // DEBUG: Mostrar que encontrou o usuário
                    $usuario = $result_usuario->fetch_assoc();
                    error_log("Usuário encontrado: " . $usuario['nome'] . " - CPF: " . $usuario['cpf']);
                }
                $stmt_usuario->close();
            }
        }

        // Verificar conflitos de staff/delegado/diretor para os diretores
        if (empty($erro)) {
            $diretores_cpfs = array_filter([$diretor1_cpf_formatado, $diretor2_cpf_formatado, $diretor3_cpf_formatado]);
            
            foreach ($diretores_cpfs as $cpf_formatado) {

                // Verificar se é staff aprovado
                $sql_staff_diretor = "SELECT * FROM staff WHERE cpf = ? AND id_unif = ? AND status_inscricao = 'aprovado'";
                $stmt_staff_d = $conn->prepare($sql_staff_diretor);
                $stmt_staff_d->bind_param("si", $cpf_formatado, $id_unif);
                $stmt_staff_d->execute();

                if ($stmt_staff_d->get_result()->num_rows > 0) {
                    $erro = "O CPF $cpf_formatado já é staff aprovado para este UNIF.";
                    break;
                }
                $stmt_staff_d->close();

                // Verificar se é delegado
                $sql_delegado_diretor = "SELECT * FROM delegacao WHERE cpf = ? AND id_unif = ?";
                $stmt_delegado_d = $conn->prepare($sql_delegado_diretor);
                $stmt_delegado_d->bind_param("si", $cpf_formatado, $id_unif);
                $stmt_delegado_d->execute();

                if ($stmt_delegado_d->get_result()->num_rows > 0) {
                    $erro = "O CPF $cpf_formatado já está inscrito como delegado para este UNIF.";
                    break;
                }
                $stmt_delegado_d->close();

                // Verificar se já é diretor em outro comitê
                $sql_diretor_outro_comite = "SELECT d.*, c.nome_comite 
                                           FROM diretor d 
                                           JOIN comite c ON d.id_comite = c.id_comite 
                                           WHERE d.cpf = ? AND c.id_unif = ?";
                $stmt_diretor_outro = $conn->prepare($sql_diretor_outro_comite);
                $stmt_diretor_outro->bind_param("si", $cpf_formatado, $id_unif);
                $stmt_diretor_outro->execute();

                if ($stmt_diretor_outro->get_result()->num_rows > 0) {
                    $diretor_data = $stmt_diretor_outro->get_result()->fetch_assoc();
                    $erro = "O CPF $cpf_formatado já é diretor do comitê '{$diretor_data['nome_comite']}'. Um diretor só pode participar de um comitê por UNIF.";
                    break;
                }
                $stmt_diretor_outro->close();
            }
        }

        // Se todas as validações passaram
        if (empty($erro)) {
            // Preparar CPFs formatados para o banco
            $status_comite = 'pendente';
            
            // $data_comite já está no formato correto YYYY-MM-DD da função validarDataParaMySQL()

            // Query SEM cpf_d4 (10 parâmetros)
            $sql_inserir_comite = "INSERT INTO comite (id_unif, cpf_d1, cpf_d2, cpf_d3,
                                                     tipo_comite, nome_comite, data_comite, 
                                                     num_delegados, descricao_comite, status)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
            $stmt_comite = $conn->prepare($sql_inserir_comite);
            if (!$stmt_comite) {
                $erro = "Erro ao preparar inserção: " . $conn->error;
            } else {
                // Bind dos parâmetros (10 parâmetros)
                $stmt_comite->bind_param(
                    "issssssiss",  // i + 9s = 10 parâmetros
                    $id_unif,                    // i
                    $diretor1_cpf_formatado,     // s
                    $diretor2_cpf_formatado,     // s
                    $diretor3_cpf_formatado,     // s
                    $tipo_comite,                // s
                    $nome_comite,                // s
                    $data_comite,                // s (NO FORMATO CORRETO YYYY-MM-DD)
                    $num_delegados,              // i
                    $descricao_comite,           // s
                    $status_comite               // s
                );
        
                if ($stmt_comite->execute()) {
                    // Obter o ID do comitê recém-inserido
                    $id_comite = $conn->insert_id;
        
                    // Inserir os diretores na tabela diretor
                    $diretores_inseridos = 0;
        
                    // Diretor 1 (obrigatório)
                    $sql_inserir_diretor = "INSERT INTO diretor (id_comite, cpf) VALUES (?, ?)";
                    $stmt_diretor = $conn->prepare($sql_inserir_diretor);
                    if ($stmt_diretor) {
                        $stmt_diretor->bind_param("is", $id_comite, $diretor1_cpf_formatado);
                        if ($stmt_diretor->execute()) $diretores_inseridos++;
                        $stmt_diretor->close();
                    }
        
                    // Diretor 2 (opcional)
                    if ($diretor2_cpf_formatado) {
                        $stmt_diretor = $conn->prepare($sql_inserir_diretor);
                        if ($stmt_diretor) {
                            $stmt_diretor->bind_param("is", $id_comite, $diretor2_cpf_formatado);
                            if ($stmt_diretor->execute()) $diretores_inseridos++;
                            $stmt_diretor->close();
                        }
                    }
        
                    // Diretor 3 (opcional)
                    if ($diretor3_cpf_formatado) {
                        $stmt_diretor = $conn->prepare($sql_inserir_diretor);
                        if ($stmt_diretor) {
                            $stmt_diretor->bind_param("is", $id_comite, $diretor3_cpf_formatado);
                            if ($stmt_diretor->execute()) $diretores_inseridos++;
                            $stmt_diretor->close();
                        }
                    }
        
                    $mensagem = "Comitê '$nome_comite' enviado com sucesso com $diretores_inseridos diretor(es)! Aguarde aprovação da equipe organizadora.";
                    // Limpar dados preenchidos após sucesso
                    $dados_preenchidos = [];
                } else {
                    $erro = "Erro ao cadastrar comitê: " . $stmt_comite->error;
                }
                $stmt_comite->close();
            }
        }
    }
}

$conn->close();
?>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição – Mesa Diretora</title>

    <!-- global do projeto -->
    <link rel="stylesheet" href="styles/global.css">

    <!-- CSS desta página -->
    <link rel="stylesheet" href="styles/inscriçãoMesa.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

        .info-unif {
            background-color: #e8f4f8;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px auto;
            max-width: 800px;
            border-radius: 5px;
        }

        .restricoes {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            margin: 20px auto;
            max-width: 800px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .formato-data {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>

<body class="body">

    <?php if (isset($_SESSION["cpf"])) { ?>

        <!-- Mensagens de erro/sucesso fora do container principal -->
        <?php if ($erro): ?>
            <div class="mensagem-container mensagem-erro">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Erro na Inscrição</h3>
                <p><?php echo $erro; ?></p>
                <a href="inicio.php" style="color: #721c24; font-weight: bold; display: inline-block; margin-top: 10px;">
                    <i class="fas fa-arrow-left"></i> Voltar ao início
                </a>
            </div>
        <?php elseif ($mensagem): ?>
            <div class="mensagem-container mensagem-sucesso">
                <i class="fas fa-check-circle"></i>
                <h3>Inscrição Enviada!</h3>
                <p><?php echo $mensagem; ?></p>
                <a href="inicio.php" style="color: #155724; font-weight: bold; display: inline-block; margin-top: 10px;">
                    <i class="fas fa-arrow-left"></i> Voltar ao início
                </a>
            </div>
        <?php else: ?>

            <!-- Informações do UNIF -->
            <?php if (isset($unif)): ?>
                <div class="info-unif">
                    <h3><i class="fas fa-calendar-alt"></i> UNIF Atual</h3>
                    <p><strong>Período do evento:</strong> <?php echo date('d/m/Y', strtotime($unif['data_inicio_unif'])); ?>
                        a <?php echo date('d/m/Y', strtotime($unif['data_fim_unif'])); ?></p>
                    <p><strong>Inscrições para comitês:</strong> <?php echo date('d/m/Y', strtotime($data_inicio_inscricao)); ?>
                        a <?php echo date('d/m/Y', strtotime($data_fim_inscricao)); ?></p>
                </div>
            <?php endif; ?>

            <!-- Restrições -->
            <div class="restricoes">
                <strong><i class="fas fa-exclamation-triangle"></i> Restrições Importantes:</strong><br>
                1. Staffs aprovados não podem ser diretores<br>
                2. Delegados não podem ser diretores<br>
                3. Um diretor só pode participar de UM comitê por UNIF<br>
                4. Todos os diretores devem estar cadastrados no sistema<br>
                5. Descrição deve ter pelo menos 50 caracteres<br>
                6. Data histórica: Use AAAA, DD/MM/AAAA ou AAAA-MM-DD
            </div>

            <div class="container">

                <header class="header">
                    <h1 class="main-title">Inscrição para Mesa Diretora</h1>
                    <p class="subtitle">Preencha o formulário abaixo para se candidatar à mesa da UNIF XXXX</p>
                </header>

                <div class="form-area">

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="form-card">

                        <!-- SEÇÃO: DADOS DOS DIRETORES -->
                        <h2 class="section-title"><i class="fas fa-users"></i> Dados dos Diretores</h2>

                        <div class="grid-2">
                            <div>
                                <label>Diretor 1 - CPF <span style="color: red;">*</span></label>
                                <input type="text" name="diretor1_cpf" placeholder="CPF do Diretor 1"
                                    value="<?php echo htmlspecialchars($dados_preenchidos['diretor1_cpf'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label>Diretor 2 - CPF</label>
                                <input type="text" name="diretor2_cpf" placeholder="CPF do Diretor 2"
                                    value="<?php echo htmlspecialchars($dados_preenchidos['diretor2_cpf'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div>
                                <label>Diretor 3 - CPF</label>
                                <input type="text" name="diretor3_cpf" placeholder="CPF do Diretor 3"
                                    value="<?php echo htmlspecialchars($dados_preenchidos['diretor3_cpf'] ?? ''); ?>">
                            </div>
                            <div>
                                <!-- Espaço vazio para alinhamento -->
                            </div>
                        </div>

                        <!-- SEÇÃO: ESPECIFICAÇÕES DO COMITÊ -->
                        <h2 class="section-title"><i class="fas fa-clipboard-list"></i> Especificações do Comitê</h2>

                        <div class="grid-2">
                            <div>
                                <label>Tipo do Comitê <span style="color: red;">*</span></label>
                                <input type="text" name="tipo_comite" placeholder="Ex: CSNU"
                                    value="<?php echo htmlspecialchars($dados_preenchidos['tipo_comite'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label>Nome do Comitê <span style="color: red;">*</span></label>
                                <input type="text" name="nome_comite" placeholder="Nome completo do comitê"
                                    value="<?php echo htmlspecialchars($dados_preenchidos['nome_comite'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="grid-2">
                            <div>
                                <label>Número de Delegados <span style="color: red;">*</span></label>
                                <input type="number" name="num_delegados" min="1"
                                    value="<?php echo htmlspecialchars($dados_preenchidos['num_delegados'] ?? 20); ?>" required>
                            </div>
                            <div>
                                <label>Data Histórica do Comitê <span style="color: red;">*</span></label>
                                <input type="text" name="data_historica" 
                                    placeholder="Ex: 1945, 01/01/1945 ou 1945-01-01"
                                    value="<?php echo htmlspecialchars($dados_preenchidos['data_historica'] ?? ''); ?>" required>
                                <div class="formato-data">Formatos aceitos: AAAA, DD/MM/AAAA ou AAAA-MM-DD</div>
                            </div>
                        </div>

                        <div>
                            <label>Descrição do Comitê <span style="color: red;">*</span></label>
                            <textarea name="descricao_comite" maxlength="1000" required><?php echo htmlspecialchars($dados_preenchidos['descricao_comite'] ?? ''); ?></textarea>
                            <p class="char-counter" id="contador">Máximo: 1000 caracteres (Mínimo: 50 caracteres)</p>
                        </div>

                        <!-- BOTÕES DE AÇÃO -->
                        <div class="actions">
                            <button type="button" class="btn-back" onclick="window.location.href='participacao.php'">
                                <i class="fas fa-arrow-left"></i> Voltar
                            </button>

                            <button type="submit" class="btn-submit">
                                Enviar Inscrição <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>

                    </form>

                    <aside class="side-logo">
                        <img src="images/unif.png" class="logo" alt="Logo UNIF">
                        <p class="logo-text">Simulação Diplomática<br>UNIF XXXX</p>
                    </aside>

                </div>

            </div>

        <?php endif; // Fim da verificação de mensagem/erro 
        ?>

    <?php } else { ?>

        <div class="auth-error">
            <div class="error-container">
                <h2>Usuário não autenticado!</h2>
                <p>Para acessar esta página, faça login primeiro.</p>
                <a href="login.html" class="auth-btn">Fazer login</a>
            </div>
        </div>

    <?php } ?>

    <script>
        // Validação do formulário
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('textarea[name="descricao_comite"]');
            const contador = document.getElementById('contador');

            if (textarea && contador) {
                textarea.addEventListener('input', function() {
                    const comprimento = this.value.length;

                    if (comprimento < 50) {
                        contador.innerHTML = `Máximo: 1000 caracteres (Mínimo: <span style="color: red; font-weight: bold;">${comprimento}/50</span> caracteres)`;
                    } else {
                        contador.innerHTML = `Máximo: 1000 caracteres (${comprimento}/1000)`;
                    }
                });

                // Atualizar contador inicial
                textarea.dispatchEvent(new Event('input'));
            }

            // Formatar CPF automaticamente
            const cpfs = document.querySelectorAll('input[name^="diretor"]');
            cpfs.forEach(input => {
                input.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');

                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }

                    if (value.length <= 11) {
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    }

                    this.value = value;
                });
            });

            // Formatar CPFs já preenchidos
            cpfs.forEach(input => {
                if (input.value) {
                    input.dispatchEvent(new Event('input'));
                }
            });

            // Validação no envio
            const form = document.querySelector('.form-card');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const diretor1_cpf = document.querySelector('input[name="diretor1_cpf"]').value;
                    const descricao = document.querySelector('textarea[name="descricao_comite"]').value;
                    const dataHistorica = document.querySelector('input[name="data_historica"]').value;

                    // Validar CPF
                    const cpfLimpo = diretor1_cpf.replace(/\D/g, '');
                    if (cpfLimpo.length !== 11) {
                        e.preventDefault();
                        alert('CPF do Diretor 1 deve ter 11 dígitos.');
                        return false;
                    }

                    // Validar descrição
                    if (descricao.length < 50) {
                        e.preventDefault();
                        alert('A descrição do comitê deve ter pelo menos 50 caracteres.');
                        return false;
                    }

                    // Validar data (padrões aceitos)
                    const padraoAno = /^\d{4}$/;
                    const padraoBrasileiro = /^\d{2}\/\d{2}\/\d{4}$/;
                    const padraoAmericano = /^\d{4}-\d{2}-\d{2}$/;
                    
                    if (!padraoAno.test(dataHistorica) && 
                        !padraoBrasileiro.test(dataHistorica) && 
                        !padraoAmericano.test(dataHistorica)) {
                        e.preventDefault();
                        alert('Formato de data inválido. Use: AAAA (ex: 1945) ou DD/MM/AAAA (ex: 01/01/1945) ou AAAA-MM-DD (ex: 1945-01-01)');
                        return false;
                    }

                    return confirm('Deseja enviar sua inscrição como diretor?');
                });
            }
        });
    </script>

</body>

</html>