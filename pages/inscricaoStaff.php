<?php
session_start();
require_once 'php/conexao.php';

if (!isset($_SESSION["cpf"])) {
    header("Location: login.html");
    exit();
}

$cpf_usuario = $_SESSION["cpf"];
$mensagem = "";
$erro = "";

// Obter o UNIF mais recente (com base na data de início)
$sql_unif = "SELECT id_unif, data_inicio_unif, data_fim_unif,
                    data_inicio_inscricao_staff, data_fim_inscricao_staff
             FROM unif 
             ORDER BY data_inicio_unif DESC 
             LIMIT 1";
$result_unif = $conn->query($sql_unif);

if ($result_unif->num_rows == 0) {
    $erro = "Não há UNIF cadastrado no momento.";
} else {
    $unif = $result_unif->fetch_assoc();
    $id_unif = $unif['id_unif'];
    $data_inicio_inscricao = $unif['data_inicio_inscricao_staff'];
    $data_fim_inscricao = $unif['data_fim_inscricao_staff'];
    
    // Verificar se estamos dentro do período de inscrição
    $data_atual = date('Y-m-d');
    
    if ($data_atual < $data_inicio_inscricao) {
        $erro = "As inscrições para staff começam em " . date('d/m/Y', strtotime($data_inicio_inscricao));
    } elseif ($data_atual > $data_fim_inscricao) {
        $erro = "As inscrições para staff encerraram em " . date('d/m/Y', strtotime($data_fim_inscricao));
    } else {
        // Verificar se o usuário já está inscrito como staff
        $sql_verifica_staff = "SELECT * FROM staff WHERE cpf = ? AND id_unif = ?";
        $stmt_verifica_staff = $conn->prepare($sql_verifica_staff);
        $stmt_verifica_staff->bind_param("si", $cpf_usuario, $id_unif);
        $stmt_verifica_staff->execute();
        $result_verifica_staff = $stmt_verifica_staff->get_result();
        
        if ($result_verifica_staff->num_rows > 0) {
            $staff_data = $result_verifica_staff->fetch_assoc();
            $status = $staff_data['inscricao_aprovada'] ? "aprovada" : "pendente";
            $erro = "Você já está inscrito como staff para este UNIF! Status: " . $status;
        }
        $stmt_verifica_staff->close();
        
        // Verificar se o usuário já está inscrito como delegado
        if (empty($erro)) {
            // CORREÇÃO: Removido o LEFT JOIN com delegacao que causa o erro
            // Verificamos apenas se está na tabela delegado
            $sql_verifica_delegado = "SELECT * FROM delegado WHERE cpf = ?";
            $stmt_verifica_delegado = $conn->prepare($sql_verifica_delegado);
            $stmt_verifica_delegado->bind_param("s", $cpf_usuario);
            $stmt_verifica_delegado->execute();
            $result_verifica_delegado = $stmt_verifica_delegado->get_result();
            
            if ($result_verifica_delegado->num_rows > 0) {
                $delegado_data = $result_verifica_delegado->fetch_assoc();
                $erro = "Você já está inscrito como delegado para este UNIF. Não é possível se inscrever como staff.";
            }
            $stmt_verifica_delegado->close();
        }
        
        // Verificar se o usuário já está inscrito como diretor
        if (empty($erro)) {
            $sql_verifica_diretor = "SELECT d.*, c.nome_comite 
                                    FROM diretor d 
                                    JOIN comite c ON d.id_comite = c.id_comite 
                                    WHERE d.cpf = ? AND c.id_unif = ?";
            $stmt_verifica_diretor = $conn->prepare($sql_verifica_diretor);
            $stmt_verifica_diretor->bind_param("si", $cpf_usuario, $id_unif);
            $stmt_verifica_diretor->execute();
            $result_verifica_diretor = $stmt_verifica_diretor->get_result();
            
            if ($result_verifica_diretor->num_rows > 0) {
                $diretor_data = $result_verifica_diretor->fetch_assoc();
                $status = $diretor_data['aprovado'] ? "aprovado" : "pendente";
                $erro = "Você já está inscrito como diretor do comitê '{$diretor_data['nome_comite']}' para este UNIF! Status: " . $status;
            }
            $stmt_verifica_diretor->close();
        }
        
        // Processar o formulário quando enviado
        if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($erro)) {
            $justificativa = trim($_POST["justificativa"] ?? '');
            
            if (empty($justificativa)) {
                $erro = "Por favor, preencha a justificativa.";
            } elseif (strlen($justificativa) < 50) {
                $erro = "A justificativa deve ter pelo menos 50 caracteres.";
            } else {
                // Inserir inscrição de staff
                $sql_inserir = "INSERT INTO staff (cpf, id_unif, justificativa, inscricao_aprovada) 
                               VALUES (?, ?, ?, 0)";
                $stmt_inserir = $conn->prepare($sql_inserir);
                $stmt_inserir->bind_param("sis", $cpf_usuario, $id_unif, $justificativa);
                
                if ($stmt_inserir->execute()) {
                    $mensagem = "Inscrição realizada com sucesso! Aguarde aprovação da equipe organizadora.";
                } else {
                    if ($conn->errno == 1062) { // Duplicate entry
                        $erro = "Você já está inscrito como staff para este UNIF!";
                    } else {
                        $erro = "Erro ao realizar inscrição: " . htmlspecialchars($conn->error);
                    }
                }
                $stmt_inserir->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição de Staff - UNIF</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/inscricaoStaff.css">
    <style>
        .info-unif {
            background-color: #e8f4f8;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-unif h3 {
            margin-top: 0;
            color: #0056b3;
        }
        
        .restricoes {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
        }
        
        .contador {
            font-size: 12px;
            text-align: right;
            margin-top: -15px;
            margin-bottom: 15px;
            color: #666;
        }
        
        .contador.baixo {
            color: #e74c3c;
        }
        
        .contador.suficiente {
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Logo -->
        <div class="logo">
            <img src="images/unif.png" alt="Logo UNIF">
        </div>

        <!-- Formulário -->
        <div class="formulario">
            <h2>Inscrição de Staff</h2>
            
            <?php if (isset($unif)): ?>
            <div class="info-unif">
                <h3>UNIF Atual</h3>
                <p><strong>Período:</strong> <?php echo date('d/m/Y', strtotime($unif['data_inicio_unif'])); ?> 
                   a <?php echo date('d/m/Y', strtotime($unif['data_fim_unif'])); ?></p>
                <p><strong>Inscrições staff:</strong> <?php echo date('d/m/Y', strtotime($data_inicio_inscricao)); ?> 
                   a <?php echo date('d/m/Y', strtotime($data_fim_inscricao)); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="restricoes">
                <strong>⚠️ Restrições:</strong><br>
                1. Não é possível ser staff e delegado/diretor no mesmo UNIF<br>
                2. A inscrição está sujeita à aprovação da equipe organizadora<br>
                3. Após aprovação, você será contactado para atividades
            </div>
            
            <?php if ($erro): ?>
                <div class="mensagem erro">
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($mensagem): ?>
                <div class="mensagem sucesso">
                    <?php echo $mensagem; ?>
                    <p style="margin-top: 10px;">
                        <a href="inicio.php" class="btn-voltar">Voltar ao início</a>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($erro) && empty($mensagem)): ?>
                <form id="formStaff" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="campo">
                        <label>CPF:</label>
                        <input type="text" value="<?php echo htmlspecialchars($cpf_usuario); ?>" readonly>
                    </div>
                    
                    <div class="campo">
                        <label>Justificativa *</label>
                        <textarea id="justificativa" name="justificativa" rows="10" 
                                  placeholder="Quais são as razões pelas quais você deveria fazer parte da equipe de staffs da UNIF?
Descreva suas experiências anteriores, habilidades relevantes e motivação para participar.
Mínimo 50 caracteres." 
                                  required
                                  oninput="atualizarContador(this)"></textarea>
                        <div id="contador" class="contador">0/50 caracteres</div>
                    </div>
                    
                    <button type="submit">Submeter Inscrição</button>
                </form>
            <?php endif; ?>
            
            <div class="links">
                <a href="inicio.php">← Voltar ao início</a>
            </div>
        </div>
    </div>

    <script>
        function atualizarContador(textarea) {
            const contador = document.getElementById('contador');
            const texto = textarea.value;
            const comprimento = texto.length;
            
            contador.textContent = comprimento + "/50 caracteres";
            
            if (comprimento < 50) {
                contador.className = 'contador baixo';
            } else {
                contador.className = 'contador suficiente';
            }
        }
        
        // Validação antes do envio
        document.getElementById('formStaff')?.addEventListener('submit', function(e) {
            const justificativa = document.getElementById('justificativa').value.trim();
            
            if (justificativa.length < 50) {
                e.preventDefault();
                alert('A justificativa deve ter pelo menos 50 caracteres.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>