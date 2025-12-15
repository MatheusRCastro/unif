<?php
session_start();

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    
    // Validar CPF
    if (strlen($cpf) !== 11) {
        $msg = "CPF inválido. Digite 11 dígitos.";
    } elseif ($novaSenha !== $confirmarSenha) {
        $msg = "As senhas não coincidem.";
    } elseif (strlen($novaSenha) < 6) {
        $msg = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        // Verificar se CPF existe
        $stmt = $pdo->prepare("SELECT id FROM usuario WHERE cpf = ?");
        $stmt->execute([$cpf]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            // Atualizar senha
            $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare(
                "UPDATE usuario SET senha_hash = ?, senha = NULL WHERE cpf = ?"
            );
            
            if ($updateStmt->execute([$hash, $cpf])) {
                $msg = "Senha alterada com sucesso!";
                $sucesso = true;
            } else {
                $msg = "Erro ao alterar senha. Tente novamente.";
            }
        } else {
            $msg = "CPF não encontrado no sistema.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/esqueciSenha.css">
</head>
<body>
<div class="container">

    <main class="main-content">
        <div class="box-recuperacao">
            <h2>Redefinir Senha</h2>
            
            <?php if ($msg): ?>
                <p class="mensagem <?php echo isset($sucesso) && $sucesso ? 'sucesso' : 'erro'; ?>">
                    <?= htmlspecialchars($msg) ?>
                </p>
            <?php endif; ?>
            
            <?php if (!isset($sucesso)): ?>
                <!-- Formulário de redefinição -->
                <form method="post">
                    <div class="campo">
                        <label for="cpf">CPF</label>
                        <input type="text" 
                               id="cpf" 
                               name="cpf" 
                               placeholder="000.000.000-00" 
                               oninput="formatarCPF(this)"
                               maxlength="14"
                               required
                               value="<?= isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : '' ?>">
                    </div>
                    
                    <div class="campo">
                        <label for="nova_senha">Nova Senha</label>
                        <input type="password" 
                               id="nova_senha" 
                               name="nova_senha" 
                               placeholder="Mínimo 6 caracteres" 
                               minlength="6"
                               required>
                    </div>
                    
                    <div class="campo">
                        <label for="confirmar_senha">Confirmar Nova Senha</label>
                        <input type="password" 
                               id="confirmar_senha" 
                               name="confirmar_senha" 
                               placeholder="Digite a senha novamente" 
                               minlength="6"
                               required>
                    </div>
                    
                    <button type="submit">Alterar Senha</button>
                    
                    <div class="links">
                        <a href="login.php">← Voltar para o Login</a>
                    </div>
                </form>
            <?php else: ?>
                <!-- Mensagem de sucesso -->
                <div class="sucesso-box">
                    <div class="icone-sucesso">✓</div>
                    <p>Senha alterada com sucesso!</p>
                    <p>Agora você pode fazer login com sua nova senha.</p>
                    <a href="login.php" class="btn-login">Ir para o Login</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

</div>

<script>
// Formatar CPF automaticamente
function formatarCPF(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 11) {
        value = value.substring(0, 11);
    }
    
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }
    
    input.value = value;
}

// Focar no CPF ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    const cpfInput = document.getElementById('cpf');
    if (cpfInput) {
        cpfInput.focus();
    }
    
    // Validar senhas em tempo real
    const novaSenha = document.getElementById('nova_senha');
    const confirmarSenha = document.getElementById('confirmar_senha');
    
    function validarSenhas() {
        if (novaSenha.value && confirmarSenha.value) {
            if (novaSenha.value !== confirmarSenha.value) {
                confirmarSenha.style.borderColor = '#d32f2f';
            } else {
                confirmarSenha.style.borderColor = '#4CAF50';
            }
        }
    }
    
    if (novaSenha && confirmarSenha) {
        novaSenha.addEventListener('input', validarSenhas);
        confirmarSenha.addEventListener('input', validarSenhas);
    }
});
</script>
</body>
</html>