function mostrarCampos(mostrar) {
  const campos = document.getElementById('camposProf');
  campos.style.display = mostrar ? 'block' : 'none';
}

// Seleciona os elementos
const formulario = document.getElementById("cadastroForm");
const camposProf = document.getElementById("camposProf");
const botoesProfessor = document.querySelectorAll('.radio-group button');

// Estado do botão selecionado
let botaoSelecionado = null;

// Inicialmente esconde os campos do professor
camposProf.style.display = 'none';

// =============================================
// FUNÇÃO PARA MOSTRAR/ESCONDER CAMPOS DO PROFESSOR
// =============================================
function mostrarCampos(ehProfessor) {
    // Remove a classe active de todos os botões
    botoesProfessor.forEach(botao => {
        botao.classList.remove('active');
    });
    
    // Adiciona a classe active no botão clicado
    if (ehProfessor) {
        botoesProfessor[0].classList.add('active');
        camposProf.style.display = 'block';
        botaoSelecionado = 'sim';
        
        // Adiciona required nos campos do professor
        const inputsProf = camposProf.querySelectorAll('input');
        inputsProf.forEach(input => {
            input.required = true;
        });
    } else {
        botoesProfessor[1].classList.add('active');
        camposProf.style.display = 'none';
        botaoSelecionado = 'nao';
        
        // Remove required dos campos do professor
        const inputsProf = camposProf.querySelectorAll('input');
        inputsProf.forEach(input => {
            input.required = false;
            input.value = ''; // Limpa os valores
        });
    }
}

// =============================================
// FUNÇÕES DE VALIDAÇÃO
// =============================================
function validarEmail(email) {
    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regexEmail.test(email);
}

function validarCPF(cpf) {
    const regexCPF = /^\d{3}\.\d{3}\.\d{3}-\d{2}$/;
    return regexCPF.test(cpf);
}

function formatarCPF(cpf) {
    // Remove tudo que não é número
    cpf = cpf.replace(/\D/g, '');
    
    // Aplica a formatação
    if (cpf.length <= 11) {
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }
    
    return cpf;
}

function validarTelefone(telefone) {
    // Aceita formatos: (11) 99999-9999 ou 11 99999-9999
    const regexTelefone = /^(\(\d{2}\)\s?|\d{2}\s?)\d{4,5}-\d{4}$/;
    return regexTelefone.test(telefone);
}

function formatarTelefone(telefone) {
    // Remove tudo que não é número
    telefone = telefone.replace(/\D/g, '');
    
    // Aplica a formatação
    if (telefone.length === 11) {
        telefone = telefone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (telefone.length === 10) {
        telefone = telefone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }
    
    return telefone;
}

function validarSenha(senha) {
    return senha.length >= 6;
}

function senhasConferem(senha, confirmarSenha) {
    return senha === confirmarSenha;
}

// =============================================
// EVENT LISTENERS PARA FORMATAÇÃO
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    // Formatação automática do CPF
    const cpfInput = document.querySelector('input[placeholder="CPF"]');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            e.target.value = formatarCPF(e.target.value);
        });
    }

    // Formatação automática do telefone
    const telefoneInput = document.querySelector('input[placeholder="Número de telefone"]');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            e.target.value = formatarTelefone(e.target.value);
        });
    }

    // Formatação automática do telefone da instituição (se existir)
    const telefoneInstInput = document.querySelector('input[placeholder="Informe o número de contato da sua instituição"]');
    if (telefoneInstInput) {
        telefoneInstInput.addEventListener('input', function(e) {
            e.target.value = formatarTelefone(e.target.value);
        });
    }

    // Mostrar erros do PHP se houver
    mostrarErrosPHP();
});

// =============================================
// VALIDAÇÃO DO FORMULÁRIO
// =============================================
formulario.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Coleta os dados do formulário
    const inputs = document.querySelectorAll('#cadastroForm input, #cadastroForm select');
    const dados = {
        email: document.querySelector('input[placeholder="E-mail"]').value.trim(),
        cpf: document.querySelector('input[placeholder="CPF"]').value.trim(),
        senha: document.querySelector('input[placeholder="Senha"]').value,
        confirmar_senha: document.querySelector('input[placeholder="Confirmar senha"]').value,
        telefone: document.querySelector('input[placeholder="Número de telefone"]').value.trim(),
        instituicao: document.querySelector('input[placeholder="Informe sua instituição"]').value.trim(),
        medicamento: document.querySelector('input[placeholder="Faz uso de algum medicamento? (se não, deixar em branco)"]').value.trim(),
        opcao_alimentar: document.getElementById('Oa').value,
        alergia: document.querySelector('input[placeholder="Tem alguma alergia? (se não, deixar em branco)"]').value.trim(),
        restricao_alimentar: document.querySelector('input[placeholder="Tem alguma restrição alimentar? (se não, deixar em branco)"]').value.trim(),
        eh_professor: botaoSelecionado === 'sim'
    };

    // Se for professor, adiciona os campos extras
    if (dados.eh_professor) {
        dados.telefone_instituicao = document.querySelector('input[placeholder="Informe o número de contato da sua instituição"]').value.trim();
        dados.email_instituicao = document.querySelector('input[placeholder="Informe o e-mail de contato da sua instituição"]').value.trim();
    }

    // Validações
    let valido = true;
    let mensagemErro = '';

    // Valida email
    if (!validarEmail(dados.email)) {
        mensagemErro = '⚠️ Por favor, insira um e-mail válido';
        valido = false;
    }
    // Valida CPF
    else if (!validarCPF(dados.cpf)) {
        mensagemErro = '⚠️ Por favor, insira um CPF válido no formato: 000.000.000-00';
        valido = false;
    }
    // Valida senha
    else if (!validarSenha(dados.senha)) {
        mensagemErro = '⚠️ A senha deve ter pelo menos 6 caracteres';
        valido = false;
    }
    // Valida confirmação de senha
    else if (!senhasConferem(dados.senha, dados.confirmar_senha)) {
        mensagemErro = '⚠️ As senhas não conferem';
        valido = false;
    }
    // Valida telefone
    else if (!validarTelefone(dados.telefone)) {
        mensagemErro = '⚠️ Por favor, insira um telefone válido no formato: (11) 99999-9999';
        valido = false;
    }
    // Valida instituição
    else if (dados.instituicao === '') {
        mensagemErro = '⚠️ Por favor, informe sua instituição';
        valido = false;
    }
    // Valida se selecionou se é professor
    else if (botaoSelecionado === null) {
        mensagemErro = '⚠️ Por favor, selecione se você é professor acompanhante';
        valido = false;
    }
    // Se for professor, valida campos extras
    else if (dados.eh_professor) {
        if (!validarTelefone(dados.telefone_instituicao)) {
            mensagemErro = '⚠️ Por favor, insira um telefone válido para a instituição';
            valido = false;
        } else if (!validarEmail(dados.email_instituicao)) {
            mensagemErro = '⚠️ Por favor, insira um e-mail válido para a instituição';
            valido = false;
        }
    }

    // Mostra mensagem de erro se houver
    if (!valido) {
        alert(mensagemErro);
        return;
    }

    // Se todas as validações passaram, envia o formulário
    enviarCadastro(dados);
});

// =============================================
// ENVIO DO FORMULÁRIO
// =============================================
async function enviarCadastro(dados) {
    try {
        // Mostra loading (opcional)
        const botaoSubmit = formulario.querySelector('button[type="submit"]');
        const textoOriginal = botaoSubmit.textContent;
        botaoSubmit.textContent = 'Cadastrando...';
        botaoSubmit.disabled = true;

        const formData = new FormData();
        
        // Adiciona todos os dados ao FormData
        Object.keys(dados).forEach(key => {
            formData.append(key, dados[key]);
        });

        const response = await fetch('php/cadastro.php', {
            method: 'POST',
            body: formData
        });

        const resultado = await response.json();

        if (resultado.success) {
            alert('✅ Cadastro realizado com sucesso!');
            window.location.href = 'login.html'; // Redireciona para login
        } else {
            let mensagemErro = '❌ Erro no cadastro: ';
            
            // Mensagens específicas para cada tipo de erro
            const mensagensErro = {
                'cpf_existe': 'CPF já cadastrado',
                'email_existe': 'E-mail já cadastrado', 
                'campos_vazios': 'Preencha todos os campos obrigatórios',
                'database_error': 'Erro no servidor'
            };
            
            mensagemErro += mensagensErro[resultado.error] || resultado.error || 'Erro desconhecido';
            alert(mensagemErro);
        }

    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao conectar com o servidor');
    } finally {
        // Restaura o botão
        const botaoSubmit = formulario.querySelector('button[type="submit"]');
        botaoSubmit.textContent = 'Submeter';
        botaoSubmit.disabled = false;
    }
}

// =============================================
// MOSTRAR ERROS DO PHP (se houver)
// =============================================
function mostrarErrosPHP() {
    const urlParams = new URLSearchParams(window.location.search);
    const erro = urlParams.get('erro');
    
    if (erro) {
        const mensagens = {
            'cpf_existe': '⚠️ CPF já cadastrado',
            'email_existe': '⚠️ E-mail já cadastrado',
            'campos_vazios': '⚠️ Preencha todos os campos obrigatórios',
            'database_error': '⚠️ Erro no servidor'
        };
        
        const mensagemErro = mensagens[erro] || '⚠️ Erro desconhecido';
        alert(mensagemErro);
        
        // Limpa a URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}