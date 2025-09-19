// Seleciona os inputs
const email = document.getElementById("email");
const senha = document.getElementById("senha");

// "Banco de dados" mock de usuários
const usuarios = {
  "teste@email.com": "123456",
  "admin@unif.com": "admin"
};

// Função principal de login
function buttonConfirmar() {
  confereCamposEmail();
  confereCamposSenha();

  // Lista de erros
  const erros = [
    "emailVazio",
    "emailInvalido",
    "emailNaoCadastrado",
    "senhaVazia",
    "senhaIncorreta"
  ];

  const temErro = erros.some(
    (id) => document.getElementById(id).style.display === "block"
  );

  if (!temErro) {
    alert("✅ Login realizado com sucesso!");
    // Redireciona para a página principal (ajuste o caminho)
    // window.location.href = "/./pages/dashboard.html";
  }
}

/* -------------------
   FUNÇÕES DE EMAIL
------------------- */
function emailValido() {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (email.value.trim() !== "" && !regex.test(email.value)) {
    document.getElementById("emailInvalido").style.display = "block";
  } else {
    document.getElementById("emailInvalido").style.display = "none";
  }
}

function emailEstaVazio() {
  if (email.value.trim() === "") {
    document.getElementById("emailVazio").style.display = "block";
  } else {
    document.getElementById("emailVazio").style.display = "none";
  }
}

function emailCadastrado() {
  if (email.value.trim() !== "" && !(email.value in usuarios)) {
    document.getElementById("emailNaoCadastrado").style.display = "block";
  } else {
    document.getElementById("emailNaoCadastrado").style.display = "none";
  }
}

/* -------------------
   FUNÇÕES DE SENHA
------------------- */
function senhaEstaVazio() {
  if (senha.value.trim() === "") {
    document.getElementById("senhaVazia").style.display = "block";
  } else {
    document.getElementById("senhaVazia").style.display = "none";
  }
}

function senhaIncorreta() {
  if (
    email.value in usuarios &&
    senha.value.trim() !== "" &&
    usuarios[email.value] !== senha.value
  ) {
    document.getElementById("senhaIncorreta").style.display = "block";
  } else {
    document.getElementById("senhaIncorreta").style.display = "none";
  }
}

/* -------------------
   FUNÇÕES GERAIS
------------------- */
function confereCamposEmail() {
  emailEstaVazio();
  emailValido();
  emailCadastrado();
}

function confereCamposSenha() {
  senhaEstaVazio();
  senhaIncorreta();
}
