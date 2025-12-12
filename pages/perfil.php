<?php
session_start();
require_once 'php/conexao.php'; // Inclui o arquivo de conexão

// Processar atualização do perfil (MANTIDO INTACTO)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    if (!$conn || $conn->connect_error) {
        $_SESSION['mensagem'] = "Erro de conexão com o banco de dados";
        $_SESSION['tipo_mensagem'] = "erro";
    } else {
        $cpf = $_SESSION["cpf"];
        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefone = $_POST['telefone'] ?? '';
        $instituicao = $_POST['instituicao'] ?? '';
        
        // Validações básicas
        if (empty($nome) || empty($email) || empty($telefone) || empty($instituicao)) {
            $_SESSION['mensagem'] = "Todos os campos são obrigatórios";
            $_SESSION['tipo_mensagem'] = "erro";
        } else {
            try {
                // Verificar se o novo email já existe para outro usuário
                $sql_verifica_email = "SELECT cpf FROM usuario WHERE email = ? AND cpf != ?";
                $stmt = $conn->prepare($sql_verifica_email);
                $stmt->bind_param("ss", $email, $cpf);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['mensagem'] = "Este email já está em uso por outro usuário";
                    $_SESSION['tipo_mensagem'] = "erro";
                    $stmt->close();
                } else {
                    $stmt->close();
                    
                    // Atualizar os dados do usuário
                    $sql_atualizar = "UPDATE usuario SET 
                                        nome = ?, 
                                        email = ?, 
                                        telefone = ?, 
                                        instituicao = ? 
                                        WHERE cpf = ?";
                    
                    $stmt = $conn->prepare($sql_atualizar);
                    $stmt->bind_param("sssss", $nome, $email, $telefone, $instituicao, $cpf);
                    
                    if ($stmt->execute()) {
                        // Atualizar dados na sessão
                        $_SESSION["nome"] = $nome;
                        $_SESSION["email"] = $email;
                        $_SESSION["telefone"] = $telefone;
                        $_SESSION["instituicao"] = $instituicao;
                        
                        $_SESSION['mensagem'] = "Perfil atualizado com sucesso!";
                        $_SESSION['tipo_mensagem'] = "sucesso";
                    } else {
                        $_SESSION['mensagem'] = "Erro ao atualizar perfil: " . $stmt->error;
                        $_SESSION['tipo_mensagem'] = "erro";
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $_SESSION['mensagem'] = "Erro inesperado: " . $e->getMessage();
                $_SESSION['tipo_mensagem'] = "erro";
            }
        }
    }
    
    // Recarregar a página para mostrar a mensagem
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Buscar dados atualizados do usuário do banco (MANTIDO INTACTO)
if (isset($_SESSION["cpf"]) && $conn && !$conn->connect_error) {
    $cpf = $_SESSION["cpf"];
    $sql = "SELECT nome, email, telefone, instituicao FROM usuario WHERE cpf = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        // Atualizar sessão com dados do banco
        $_SESSION["nome"] = $usuario['nome'];
        $_SESSION["email"] = $usuario['email'];
        $_SESSION["telefone"] = $usuario['telefone'];
        $_SESSION["instituicao"] = $usuario['instituicao'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Perfil - UNIF</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="styles/global.css" /> <link rel="stylesheet" href="styles/perfil.css"> <script src="scripts/sidebar.js" defer></script>
    </head>

<body>

    <?php
    // Verifica se foi iniciada a sessão do usuário
    if (isset($_SESSION["cpf"])) {
        
        // Mostrar mensagem se existir (MANTIDO INTACTO)
        if (isset($_SESSION['mensagem'])) {
            $tipo = $_SESSION['tipo_mensagem'] ?? 'erro';
            $mensagem = $_SESSION['mensagem'];
            echo '<div class="mensagem ' . $tipo . '">' . $mensagem . '</div>';
            // Limpar mensagem após mostrar
            unset($_SESSION['mensagem']);
            unset($_SESSION['tipo_mensagem']);
        }
    ?>

    <div class="container"> <nav class="sidebar collapsed">
            <div class="sidebar-header">
                <h2>Menu</h2>
            </div>

            <ul class="sidebar-menu">
                <li data-tooltip="Início">
                    <a href="inicio.php"><i class="fas fa-home"></i> <span>Início</span></a>
                </li>
                <li data-tooltip="Perfil">
                    <a href="perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a>
                </li>
                <li data-tooltip="Mensagens">
                    <a href="mensagens.php"><i class="fas fa-envelope"></i> <span>Mensagens</span></a>
                </li>
                <li data-tooltip="Configurações">
                    <a href="#"><i class="fas fa-cog"></i> <span>Configurações</span></a>
                </li>
                <li data-tooltip="Ajuda">
                    <a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a>
                </li>
            </ul>
        </nav>

        <main class="main-content">
            <div class="wraper">
                

                <div class="profile-box">
                    <div class="info-section">
                        <div class="form-section">
                            <form method="POST" action="">
                                <div class="input-group">
                                    <label>Nome:</label>
                                    <input type="text" name="nome" value="<?php echo htmlspecialchars($_SESSION["nome"] ?? ''); ?>" required>
                                </div>

                                <div class="input-group">
                                    <label>E-mail:</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION["email"] ?? ''); ?>" required>
                                </div>

                                <div class="input-group">
                                    <label>Telefone:</label>
                                    <input type="text" name="telefone" value="<?php echo htmlspecialchars($_SESSION["telefone"] ?? ''); ?>" required>
                                </div>

                                <div class="input-group">
                                    <label>Instituição:</label>
                                    <input type="text" name="instituicao" value="<?php echo htmlspecialchars($_SESSION["instituicao"] ?? ''); ?>" required>
                                </div>

                                <div class="button-group">
                                    <button type="submit" name="atualizar_perfil" value="1" class="update-btn">Salvar</button>
                                    <button type="button" class="cancel-btn" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // MANTIDO INTACTO
        document.addEventListener('DOMContentLoaded', function() {
          const form = document.querySelector('form');
          const telefoneInput = document.querySelector('input[name="telefone"]');
          
          // Formatação de telefone
          telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length === 11) {
              value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            } else if (value.length === 10) {
              value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
            }
            
            e.target.value = value;
          });
          
          // Validação antes de enviar
          form.addEventListener('submit', function(e) {
            const telefone = telefoneInput.value.replace(/\D/g, '');
            
            if (telefone.length !== 10 && telefone.length !== 11) {
              e.preventDefault();
              alert('Por favor, insira um telefone válido com 10 ou 11 dígitos');
              telefoneInput.focus();
              return false;
            }
            
            // Validação de email
            const emailInput = document.querySelector('input[name="email"]');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(emailInput.value)) {
              e.preventDefault();
              alert('Por favor, insira um e-mail válido');
              emailInput.focus();
              return false;
            }
            
            // Confirmação
            return confirm('Tem certeza que deseja atualizar seus dados?');
          });
        });
    </script>

    <?php
    } else {
        echo "Usuário não autenticado!";
    ?>
        <a href="login.html" class="erro-php">Se identifique aqui</a>
    <?php
    }
    
    // Fechar conexão se existir (MANTIDO INTACTO)
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
    ?>
</body>

</html>