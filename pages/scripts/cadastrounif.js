// =============================================
// INICIALIZAÇÃO - AGUARDA O DOM CARREGAR
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    // DEBUG: Verifica se há JSON.parse sendo usado
    console.log('=== INICIANDO SCRIPT ===');
    
    // Seleciona os elementos
    const formulario = document.getElementById("cadastroForm");
    const camposProf = document.getElementById("camposProf");
    const botoesProfessor = document.querySelectorAll('.radio-group button');

    // Verifica se os elementos existem
    if (!formulario) {
        console.error('Formulário com id "cadastroForm" não encontrado!');
        return;
    }

    if (!camposProf) {
        console.warn('Elemento com id "camposProf" não encontrado!');
    }

    if (botoesProfessor.length === 0) {
        console.warn('Botões de professor não encontrados!');
    }

    // Estado do botão selecionado
    let botaoSelecionado = null;

    // Inicialmente esconde os campos do professor
    if (camposProf) {
        camposProf.style.display = 'none';
    }

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
            if (camposProf) {
                camposProf.style.display = 'block';
                
                // Adiciona required nos campos do professor
                const inputsProf = camposProf.querySelectorAll('input');
                inputsProf.forEach(input => {
                    input.required = true;
                });
            }
            botaoSelecionado = 'sim';
        } else {
            botoesProfessor[1].classList.add('active');
            if (camposProf) {
                camposProf.style.display = 'none';
                
                // Remove required dos campos do professor
                const inputsProf = camposProf.querySelectorAll('input');
                inputsProf.forEach(input => {
                    input.required = false;
                    input.value = ''; // Limpa os valores
                });
            }
            botaoSelecionado = 'nao';
        }
    }

    // =============================================
    // FUNÇÕES DE VALIDAÇÃO
    // =============================================
    function validarEmail(email) {
        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regexEmail.test(email);
    }

    // Função para validar CPF matematicamente
    function validarCPFMatematicamente(cpf) {
        // Remove caracteres não numéricos
        cpf = cpf.replace(/\D/g, '');
        
        // Verifica se tem 11 dígitos
        if (cpf.length !== 11) return false;
        
        // Verifica se todos os dígitos são iguais (CPF inválido)
        if (/^(\d)\1{10}$/.test(cpf)) return false;
        
        // Validação do primeiro dígito verificador
        let soma = 0;
        for (let i = 0; i < 9; i++) {
            soma += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.charAt(9))) return false;
        
        // Validação do segundo dígito verificador
        soma = 0;
        for (let i = 0; i < 10; i++) {
            soma += parseInt(cpf.charAt(i)) * (11 - i);
        }
        resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.charAt(10))) return false;
        
        return true;
    }

    function validarCPF(cpf) {
        // Primeiro verifica o formato
        const regexCPF = /^\d{3}\.\d{3}\.\d{3}-\d{2}$/;
        if (!regexCPF.test(cpf)) return false;
        
        // Depois valida matematicamente
        return validarCPFMatematicamente(cpf);
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
    // Formatação automática do CPF
    const cpfInput = document.querySelector('input[placeholder="CPF"]');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            e.target.value = formatarCPF(e.target.value);
        });
        
        // Validação matemática em tempo real (opcional)
        cpfInput.addEventListener('blur', function(e) {
            const cpf = e.target.value;
            if (cpf && !validarCPF(cpf)) {
                // Pode adicionar uma mensagem visual aqui se quiser
                console.log('CPF inválido matematicamente');
            }
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

    // =============================================
    // VALIDAÇÃO DO FORMULÁRIO
    // =============================================
    formulario.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Coleta os dados do formulário
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
        // Valida CPF (agora com validação matemática)
        else if (!validarCPF(dados.cpf)) {
            mensagemErro = '⚠️ Por favor, insira um CPF válido';
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
    // ENVIO DO FORMULÁRIO (SEM JSON - SOMENTE TEXTO)
    // =============================================
    async function enviarCadastro(dados) {
        console.log('=== DEBUG: Iniciando enviarCadastro ===');
        
        try {
            // Mostra loading
            const botaoSubmit = formulario.querySelector('button[type="submit"]');
            const textoOriginal = botaoSubmit.textContent;
            botaoSubmit.textContent = 'Cadastrando...';
            botaoSubmit.disabled = true;

            // Cria FormData
            const formData = new FormData();
            
            // Adiciona todos os dados ao FormData
            formData.append('cpf', dados.cpf);
            formData.append('email', dados.email);
            formData.append('senha', dados.senha);
            formData.append('telefone', dados.telefone);
            formData.append('instituicao', dados.instituicao);
            formData.append('medicamento', dados.medicamento);
            formData.append('opcao_alimentar', dados.opcao_alimentar);
            formData.append('alergia', dados.alergia);
            formData.append('restricao_alimentar', dados.restricao_alimentar);
            formData.append('eh_professor', dados.eh_professor ? 'true' : 'false');
            
            // Se for professor, adiciona campos extras (se existirem)
            if (dados.eh_professor) {
                if (dados.telefone_instituicao) {
                    formData.append('telefone_instituicao', dados.telefone_instituicao);
                }
                if (dados.email_instituicao) {
                    formData.append('email_instituicao', dados.email_instituicao);
                }
            }

            // DEBUG: Verifica o que está sendo enviado
            console.log('Dados sendo enviados:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }

            // Faz a requisição - NÃO USA JSON
            console.log('Fazendo requisição para php/cadastro.php');
            const response = await fetch('php/cadastro.php', {
                method: 'POST',
                body: formData
            });

            console.log('Status da resposta:', response.status, response.statusText);
            
            // OBTÉM A RESPOSTA COMO TEXTO - NÃO COMO JSON
            const resultado = await response.text();
            console.log('Resposta do servidor (TEXTO):', resultado);
            
            // Verifica se a resposta não está vazia
            if (!resultado || resultado.trim() === '') {
                throw new Error('Resposta vazia do servidor');
            }
            
            // Processa a resposta no formato "1:mensagem" ou "0:mensagem"
            const primeiroCaractere = resultado.charAt(0);
            const mensagem = resultado.substring(2); // Remove "1:" ou "0:"
            
            // Verifica se o formato está correto
            if (resultado.length < 3 || !['0', '1'].includes(primeiroCaractere) || resultado.charAt(1) !== ':') {
                console.error('Formato de resposta inválido:', resultado);
                throw new Error('Formato de resposta inválido: ' + resultado);
            }
            
            if (primeiroCaractere === '1') {
                alert('✅ ' + mensagem);
                window.location.href = 'login.html'; // Redireciona para login
            } else {
                alert('❌ ' + mensagem);
            }

        } catch (error) {
            console.error('=== ERRO DETALHADO ===');
            console.error('Tipo:', error.name);
            console.error('Mensagem:', error.message);
            console.error('Stack:', error.stack);
            console.error('====================');
            
            // Mensagens específicas
            if (error.name === 'SyntaxError' && error.message.includes('JSON')) {
                alert('❌ ERRO: Alguém está tentando usar JSON onde não deve. Verifique outros scripts.');
            } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                alert('❌ Erro de conexão. Verifique sua internet.');
            } else {
                alert('❌ Erro: ' + error.message);
            }
        } finally {
            // Restaura o botão
            const botaoSubmit = formulario.querySelector('button[type="submit"]');
            if (botaoSubmit) {
                botaoSubmit.textContent = textoOriginal || 'Cadastrar';
                botaoSubmit.disabled = false;
            }
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

    // =============================================
    // EXPORTA FUNÇÕES PARA USO NO HTML
    // =============================================
    // Torna a função mostrarCampos disponível globalmente
    window.mostrarCampos = mostrarCampos;
    
    console.log('=== SCRIPT CARREGADO COM SUCESSO ===');
});