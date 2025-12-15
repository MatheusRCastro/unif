<?php
session_start();


if (!isset($_SESSION["cpf"])) {
  echo "Usuário não autenticado!";
  echo '<br><a href="login.html" class="erro-php">Se identifique aqui</a>';
  exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $senhaAtual = $_POST['senha_atual'] ?? '';
  $novaSenha  = $_POST['nova_senha'] ?? '';
  $confirma   = $_POST['confirmar_senha'] ?? '';

  if ($novaSenha !== $confirma) {
    $msg = "As senhas não coincidem.";
  } else {
    $cpf = $_SESSION['cpf'];

    $stmt = $pdo->prepare("SELECT senha, senha_hash FROM usuario WHERE cpf = ?");
    $stmt->execute([$cpf]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $senhaValida = false;

    if (!empty($user['senha_hash'])) {
      $senhaValida = password_verify($senhaAtual, $user['senha_hash']);
    } else {
      $senhaValida = ($senhaAtual === $user['senha']);
    }

    if ($senhaValida) {
      $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
      $upd = $pdo->prepare(
        "UPDATE usuario SET senha_hash = ?, senha = NULL WHERE cpf = ?"
      );
      $upd->execute([$hash, $cpf]);
      $msg = "Senha atualizada com sucesso!";
    } else {
      $msg = "Senha atual incorreta.";
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
  <link rel="stylesheet" href="styles/redefinirSenha.css">
</head>

<body>
<div class="container">

  <main class="main-content">
    <div class="box-senha">
      <h2>Redefinir Senha</h2>

      <?php if ($msg): ?>
        <p class="mensagem"><?= htmlspecialchars($msg) ?></p>
      <?php endif; ?>

      <form method="post">
        <input type="password" name="senha_atual" placeholder="Senha atual" required>
        <input type="password" name="nova_senha" placeholder="Nova senha" required>
        <input type="password" name="confirmar_senha" placeholder="Confirmar nova senha" required>

        <button type="submit">Atualizar senha</button>
        <a href="perfil.php">Voltar</a>
      </form>
    </div>
  </main>

</div>
</body>
</html>
