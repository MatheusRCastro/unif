let email = document.getElementById("email");
let senha = document.getElementById("senha");

// Lista de emails/senhas "cadastrados" (mock)
const usuarios = {
  "teste@email.com": "123456",
  "admin@unif.com": "admin"
};

function buttonConfirmar() {
  confereCamposEmail();
  confereCamposSenha();

  // Se não há erros visíveis, tenta login
  if (
    document.getElementById("emailVazio").style.display === "none" &&
    document.getElementById("emailInvalido").style.display === "none" &&
    document.getElementById("emailNaoCadastrado").style.display === "none" &&
    document.getElementById("senhaVazia").style.display === "none" &&
    document.getElementById("senhaIncorreta").style.display === "none"
  ) {
    alert("Login realizado com sucesso!");
  }
}

function emailValido() {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (regex.test(email.value)) {
    document.getElementById("emailInvalido").style.display = "none";
  } else if (email.value.trim() !== "") {
    document.getElementById("emailInvalido").style.display = "block";
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

function confereCamposEmail() {
  emailValido();
  emailEstaVazio();
  emailCadastrado();
}

function confereCamposSenha() {
  senhaEstaVazio();
  senhaIncorreta();
}
