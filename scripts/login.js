email = document.getElementById("email");
senha = document.getElementById("senha");

function buttonConfirmar() {}

function emailValido() {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (regex.test(email.value)) {
    document.getElementById("emailInvalido").style.display = "none";
  } else {
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

function emailCadastrado() {}

function senhaEstaVazio() {
  if (senha.value.trim() === "") {
    document.getElementById("senhaVazia").style.display = "block";
  } else {
    document.getElementById("senhaVazia").style.display = "none";
  }
}

function senhaIncorreta() {}

function confereCamposEmail() {
  emailValido();
  emailEstaVazio();
  emailCadastrado();
}

function confereCamposSenha() {
  senhaEstaVazio();
  senhaIncorreta();
}
