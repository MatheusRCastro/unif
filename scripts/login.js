// Seleciona os inputs
const email = document.getElementById("email");
const senha = document.getElementById("senha");

// Função principal de login
async function buttonConfirmar() {
  // Valida campos primeiro
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
    await fazerLogin();
  }
}

/* -------------------
   FUNÇÕES DE EMAIL
------------------- */
function emailValido() {
  const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const regexCPF = /^\d{3}\.\d{3}\.\d{3}-\d{2}$/;
  
  if (email.value.trim() !== "" && !regexEmail.test(email.value) && !regexCPF.test(email.value)) {
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
  // Esta função será validada no servidor
  document.getElementById("emailNaoCadastrado").style.display = "none";
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
  // Esta função será validada no servidor
  document.getElementById("senhaIncorreta").style.display = "none";
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

/* -------------------
   FUNÇÃO DE LOGIN COM PHP
------------------- */
/* -------------------
   FUNÇÃO DE LOGIN COM PHP
------------------- */
async function fazerLogin() {
  const formData = new FormData();
  formData.append('email', email.value);
  formData.append('senha', senha.value);

  try {
    console.log('📤 Enviando dados para PHP...');
    
    const response = await fetch('/php/login.php', {
      method: 'POST',
      body: formData
    });

    console.log('📥 Resposta recebida, status:', response.status);
    
    const text = await response.text();
    console.log('🔍 Resposta bruta do servidor:', text);
    
    let result;
    try {
      result = JSON.parse(text);
      console.log('✅ JSON parseado com sucesso:', result);
    } catch (parseError) {
      console.error('❌ Erro no JSON.parse:', parseError);
      console.error('📄 Texto que causou o erro:', text);
      throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
    }

    if (result.success) {
      console.log('🎉 Login bem-sucedido!');
      alert("✅ Login realizado com sucesso!");
      
      if (result.adm) {
        window.location.href = "/pages/painelControle.html";
      } else {
        window.location.href = "/pages/inicio.html";
      }
    } else {
      console.log('❌ Login falhou:', result.error);
      // ... resto do código de erro
    }
  } catch (error) {
    console.error('💥 Erro completo:', error);
    console.error('🔗 Stack trace:', error.stack);
    alert('Erro ao conectar com o servidor: ' + error.message);
  }
}

// Event listeners para limpar erros ao digitar
email.addEventListener('input', () => {
  document.getElementById("emailVazio").style.display = "none";
  document.getElementById("emailInvalido").style.display = "none";
  document.getElementById("emailNaoCadastrado").style.display = "none";
});

senha.addEventListener('input', () => {
  document.getElementById("senhaVazia").style.display = "none";
  document.getElementById("senhaIncorreta").style.display = "none";
});

// Enter key support
document.addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    buttonConfirmar();
  }
});