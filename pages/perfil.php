<?php
session_start();
require_once 'php/conexao.php'; // Inclui o arquivo de conexão

// Processar atualização do perfil
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

// Buscar dados atualizados do usuário do banco
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
  <title>Perfil - UNIF</title>
  <link rel="stylesheet" href="styles/perfil.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <script src="scripts/sidebar.js" defer></script>
  <style>
    .mensagem {
        padding: 12px 20px;
        margin: 15px 0;
        border-radius: 5px;
        text-align: center;
        font-weight: bold;
        animation: fadeIn 0.5s;
    }
    
    .mensagem.sucesso {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .mensagem.erro {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-section form {
        width: 100%;
    }
    
    .input-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .input-group input:focus {
        outline: none;
        border-color: #4a6fa5;
        box-shadow: 0 0 0 2px rgba(74, 111, 165, 0.2);
    }
  </style>
</head>

<body>

  <?php
  // Verifica se foi iniciada a sessão do usuário
  if (isset($_SESSION["cpf"])) {
    
    // Mostrar mensagem se existir
    if (isset($_SESSION['mensagem'])) {
        $tipo = $_SESSION['tipo_mensagem'] ?? 'erro';
        $mensagem = $_SESSION['mensagem'];
        echo '<div class="mensagem ' . $tipo . '">' . $mensagem . '</div>';
        // Limpar mensagem após mostrar
        unset($_SESSION['mensagem']);
        unset($_SESSION['tipo_mensagem']);
    }
  ?>

    <div class="background">
      <div class="logo-box">
        <img src="images/unif.png" alt="Logo UNIF" class="logo">
      </div>

      <div class="profile-box">
        <h2>Editar Perfil</h2>
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

    <script>
      // Adicionar validação de formulário
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
  
  // Fechar conexão se existir
  if ($conn && !$conn->connect_error) {
      $conn->close();
  }
  ?>
</body>

</html>