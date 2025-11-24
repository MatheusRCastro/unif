// Seleciona os inputs
const email = document.getElementById("email");
const senha = document.getElementById("senha");
const formulario = document.getElementById("loginForm");

// =============================================
// FUNÇÃO PARA MOSTRAR ERROS DO PHP - NOVA
// =============================================
function mostrarErrosPHP() {
    const urlParams = new URLSearchParams(window.location.search);
    const erro = urlParams.get('erro');
    
    if (erro) {
        const mensagens = {
            'campos_vazios': '⚠️ Preencha todos os campos',
            'email_nao_encontrado': '⚠️ E-mail não cadastrado',
            'senha_incorreta': '⚠️ Senha incorreta',
            'database_error': '⚠️ Erro no servidor',
            'usernotfound': '⚠️ Usuário não encontrado'
        };
        
        const mensagemErro = mensagens[erro] || '⚠️ Erro desconhecido';
        
        // Cria ou seleciona a div de erro
        let erroDiv = document.getElementById('erroPHP');
        if (!erroDiv) {
            erroDiv = document.createElement('div');
            erroDiv.id = 'erroPHP';
            erroDiv.className = 'erro-php';
            
            // Insere antes do formulário (mais específico)
            const formularioDiv = document.querySelector('.formulario');
            const titulo = formularioDiv.querySelector('h2');
            formularioDiv.insertBefore(erroDiv, titulo.nextSibling);
        }
        
        erroDiv.textContent = mensagemErro;
        erroDiv.style.display = 'block';
        
        // Limpa a URL para não mostrar o erro ao recarregar
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// =============================================
// EXECUTA AO CARREGAR A PÁGINA - CORRIGIDO
// =============================================
// Como o script tem 'defer', podemos executar diretamente
mostrarErrosPHP();

// =============================================
// FUNÇÃO PRINCIPAL DE LOGIN
// =============================================
function buttonConfirmar() {
    // Valida campos primeiro
    confereCamposEmail();
    confereCamposSenha();

    // Lista de erros apenas do frontend
    const erros = [
        "emailVazio",
        "emailInvalido", 
        "senhaVazia"
    ];

    const temErro = erros.some(
        (id) => document.getElementById(id).style.display === "block"
    );

    if (!temErro) {
        // Envia o formulário tradicionalmente - SEM JSON!
        formulario.submit();
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

/* -------------------
   FUNÇÕES GERAIS
------------------- */
function confereCamposEmail() {
    emailEstaVazio();
    emailValido();
}

function confereCamposSenha() {
    senhaEstaVazio();
}

// Event listeners para limpar erros ao digitar
email.addEventListener('input', () => {
    document.getElementById("emailVazio").style.display = "none";
    document.getElementById("emailInvalido").style.display = "none";
});

senha.addEventListener('input', () => {
    document.getElementById("senhaVazia").style.display = "none";
});

// Enter key support
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        buttonConfirmar();
    }
});