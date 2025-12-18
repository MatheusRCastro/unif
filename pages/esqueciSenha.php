<?php
// ==========================
// INCLUIR CONEXÃO COM BANCO
// ==========================
require_once 'php/conexao.php';

// Verificar se a conexão foi estabelecida
if (!$conn) {
    die("Erro ao conectar com o banco de dados. Por favor, tente novamente mais tarde.");
}

// ==========================
// FUNÇÃO DE VALIDAÇÃO CPF
// ==========================
function validarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) != 11) {
        return false;
    }

    // Evita CPFs com todos os dígitos iguais
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $cpf[$i] * (($t + 1) - $i);
        }

        $digito = (10 * $soma) % 11;
        $digito = ($digito == 10) ? 0 : $digito;

        if ($cpf[$t] != $digito) {
            return false;
        }
    }

    return true;
}

// ==========================
// FUNÇÃO PARA FORMATAR CPF
// ==========================
function formatarCPFBanco($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . 
               substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . 
               substr($cpf, 9, 2);
    }
    return $cpf;
}

// ==========================
// PROCESSAMENTO DO FORM
// ==========================
$msg = "";
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limpa e valida os dados
    $cpf_input = $_POST['cpf'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    
    // Validações
    if (!validarCPF($cpf_input)) {
        $msg = "CPF inválido.";
    } elseif (strlen($novaSenha) < 6) {
        $msg = "A senha deve ter no mínimo 6 caracteres.";
    } elseif ($novaSenha !== $confirmarSenha) {
        $msg = "As senhas não coincidem.";
    } else {
        try {
            // Formatar CPF para o padrão do banco
            $cpf_banco = formatarCPFBanco($cpf_input);
            
            // === VERIFICA SE CPF EXISTE NO BANCO ===
            $sql = "SELECT cpf, nome FROM usuario WHERE cpf = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $cpf_banco);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao verificar CPF: " . $conn->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $usuario = $result->fetch_assoc();
                
                // CPF encontrado, atualiza a senha
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                
                // DEBUG: Mostrar informações
                error_log("Atualizando senha para: " . $usuario['nome'] . " (" . $cpf_banco . ")");
                
                // Atualiza os campos de senha - IMPORTANTE: usar o CPF formatado
                $update_sql = "UPDATE usuario SET senha_hash = ?, senha = ? WHERE cpf = ?";
                $update = $conn->prepare($update_sql);
                
                if (!$update) {
                    throw new Exception("Erro ao preparar UPDATE: " . $conn->error);
                }
                
                $update->bind_param("sss", $hash, $novaSenha, $cpf_banco);
                
                if ($update->execute()) {
                    $affected_rows = $conn->affected_rows;
                    
                    if ($affected_rows > 0) {
                        $msg = "✅ Senha alterada com sucesso para " . $usuario['nome'] . "! Redirecionando para login...";
                        $sucesso = true;
                        
                        // Redireciona para login após 3 segundos
                        header("refresh:3;url=login.php");
                    } else {
                        // Verifica se a senha já era a mesma
                        $msg = "A senha não foi alterada. Verifique se já não era a mesma.";
                    }
                } else {
                    $msg = "❌ Erro ao atualizar a senha: " . $conn->error;
                }
                
                $update->close();
            } else {
                // CPF não encontrado
                $msg = "❌ CPF não encontrado no sistema. Verifique se digitou corretamente.";
                // Mostra o CPF que foi buscado para debug
                error_log("CPF não encontrado: " . $cpf_banco);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            // Log do erro
            error_log("ERRO: " . $e->getMessage());
            $msg = "⚠️ Ocorreu um erro: " . $e->getMessage();
        }
    }
}

// Fechar conexão
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha - UNIF</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/esqueciSenha.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            font-size: 14px;
            color: #495057;
        }
        .debug-info h4 {
            margin-top: 0;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-key"></i> Redefinir Senha</h1>
                <p>Digite seu CPF e uma nova senha</p>
            </div>

            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert <?= $sucesso ? 'alert-success' : 'alert-error' ?>" id="message">
                        <?php if ($sucesso): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($msg) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$sucesso): ?>
                    <!-- DEBUG: Mostrar informações (remover em produção) -->
                    <?php if (isset($cpf_banco)): ?>
                    <div class="debug-info">
                        <h4>Informações de Debug:</h4>
                        <p><strong>CPF Digitado:</strong> <?= htmlspecialchars($cpf_input ?? '') ?></p>
                        <p><strong>CPF para Banco:</strong> <?= htmlspecialchars($cpf_banco ?? '') ?></p>
                        <p><strong>Status:</strong> Processando...</p>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="redefinirForm">
                        <div class="form-group">
                            <label for="cpf">
                                <i class="fas fa-id-card"></i> CPF
                            </label>
                            <input type="text" 
                                   id="cpf" 
                                   name="cpf" 
                                   placeholder="123.456.789-00" 
                                   required 
                                   maxlength="14"
                                   oninput="formatarCPF(this)"
                                   value="<?= isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : '' ?>"
                                   class="<?= isset($msg) && !$sucesso && !validarCPF($_POST['cpf'] ?? '') ? 'input-error' : '' ?>">
                            <div class="input-info">
                                <i class="fas fa-info-circle"></i>
                                <span>Digite no formato: 123.456.789-00</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nova_senha">
                                <i class="fas fa-lock"></i> Nova Senha
                            </label>
                            <div class="password-wrapper">
                                <input type="password" 
                                       id="nova_senha" 
                                       name="nova_senha" 
                                       placeholder="Mínimo 6 caracteres" 
                                       required 
                                       minlength="6"
                                       class="<?= isset($msg) && !$sucesso && strlen($_POST['nova_senha'] ?? '') < 6 ? 'input-error' : '' ?>">
                                <button type="button" class="toggle-password" onclick="togglePassword('nova_senha')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <small id="strengthText">Força da senha: fraca</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirmar_senha">
                                <i class="fas fa-lock"></i> Confirmar Nova Senha
                            </label>
                            <div class="password-wrapper">
                                <input type="password" 
                                       id="confirmar_senha" 
                                       name="confirmar_senha" 
                                       placeholder="Repita a nova senha" 
                                       required 
                                       minlength="6"
                                       class="<?= isset($msg) && !$sucesso && ($_POST['nova_senha'] ?? '') !== ($_POST['confirmar_senha'] ?? '') ? 'input-error' : '' ?>">
                                <button type="button" class="toggle-password" onclick="togglePassword('confirmar_senha')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="input-info" id="passwordMatch">
                                <i class="fas fa-check"></i>
                                <span>As senhas coincidem</span>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-sync-alt"></i> Alterar Senha
                        </button>
                    </form>
                <?php else: ?>
                    <div class="success-container">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Senha Redefinida!</h3>
                        <p>Sua senha foi alterada com sucesso no sistema UNIF.</p>
                        <p class="redirect-info">
                            <i class="fas fa-spinner fa-spin"></i>
                            Redirecionando para a página de login em <span id="countdown">3</span> segundos...
                        </p>
                        <a href="login.html" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i> Ir para Login
                        </a>
                    </div>
                <?php endif; ?>

                <div class="card-footer">
                    <a href="login.html" class="back-link">
                        <i class="fas fa-arrow-left"></i> Voltar para o Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Formatar CPF
        function formatarCPF(input) {
            let value = input.value.replace(/\D/g, '');
            
            if (value.length > 3) {
                value = value.substring(0, 3) + '.' + value.substring(3);
            }
            if (value.length > 7) {
                value = value.substring(0, 7) + '.' + value.substring(7);
            }
            if (value.length > 11) {
                value = value.substring(0, 11) + '-' + value.substring(11, 13);
            }
            
            input.value = value;
        }

        // Mostrar/Esconder senha
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleBtn = field.parentElement.querySelector('.toggle-password i');
            
            if (field.type === 'password') {
                field.type = 'text';
                toggleBtn.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                toggleBtn.className = 'fas fa-eye';
            }
        }

        // Verificar força da senha
        document.getElementById('nova_senha').addEventListener('input', function() {
            const password = this.value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let color = '#ff4757';
            let text = 'Muito fraca';
            
            if (password.length >= 6) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            strengthFill.style.width = strength + '%';
            
            if (strength >= 75) {
                color = '#2ed573';
                text = 'Forte';
            } else if (strength >= 50) {
                color = '#ffa502';
                text = 'Média';
            } else if (strength >= 25) {
                color = '#ff7f50';
                text = 'Fraca';
            }
            
            strengthFill.style.backgroundColor = color;
            strengthText.textContent = 'Força da senha: ' + text;
            strengthText.style.color = color;
            
            // Verificar se as senhas coincidem
            checkPasswordMatch();
        });

        // Verificar se as senhas coincidem
        document.getElementById('confirmar_senha').addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = document.getElementById('nova_senha').value;
            const confirm = document.getElementById('confirmar_senha').value;
            const matchInfo = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchInfo.style.display = 'none';
                return;
            }
            
            matchInfo.style.display = 'flex';
            
            if (password === confirm) {
                matchInfo.innerHTML = '<i class="fas fa-check" style="color:#2ed573"></i><span style="color:#2ed573">As senhas coincidem</span>';
            } else {
                matchInfo.innerHTML = '<i class="fas fa-times" style="color:#ff4757"></i><span style="color:#ff4757">As senhas não coincidem</span>';
            }
        }

        // Contagem regressiva para redirecionamento
        <?php if ($sucesso): ?>
            let seconds = 3;
            const countdownElement = document.getElementById('countdown');
            
            const countdown = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdown);
                }
            }, 1000);
        <?php endif; ?>

        // Foco no campo CPF ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            const cpfInput = document.getElementById('cpf');
            if (cpfInput && cpfInput.value === '') {
                cpfInput.focus();
            }
            
            // Fechar mensagem após 5 segundos (se for erro)
            const message = document.getElementById('message');
            if (message && !message.classList.contains('alert-success')) {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        if (message.parentElement) {
                            message.remove();
                        }
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>