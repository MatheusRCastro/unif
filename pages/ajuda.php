<?php
session_start();
require_once 'php/conexao.php'; // Para pegar dados do usuário

// Processar envio do formulário
$mensagem_sucesso = '';
$mensagem_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitacao'])) {
    $assunto = $_POST['assunto'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $prioridade = $_POST['prioridade'] ?? 'normal';
    
    // Obter dados do usuário da sessão
    $nome_usuario = $_SESSION['nome'] ?? 'Usuário não identificado';
    $email_usuario = $_SESSION['email'] ?? 'email@nao.informado';
    $cpf_usuario = $_SESSION['cpf'] ?? 'Não informado';
    
    // Configurar email
    $para = "unif.ourobranco@ifmg.edu.br";
    $assunto_email = "[SUPORTE UNIF] $assunto - Prioridade: " . ucfirst($prioridade);
    
    // Construir corpo do email
    $corpo_email = "
    ============================================
    SOLICITAÇÃO DE SUPORTE - SISTEMA UNIF
    ============================================
    
    **Dados do Solicitante:**
    Nome: $nome_usuario
    Email: $email_usuario
    CPF: $cpf_usuario
    Data/Hora: " . date('d/m/Y H:i:s') . "
    
    **Detalhes da Solicitação:**
    Assunto: $assunto
    Prioridade: " . ucfirst($prioridade) . "
    
    **Descrição do Problema:**
    $descricao
    
    ============================================
    Esta mensagem foi enviada automaticamente pelo sistema UNIF.
    ";
    
    // Cabeçalhos do email
    $headers = "From: sistema@unif.ifmg.edu.br\r\n";
    $headers .= "Reply-To: $email_usuario\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Enviar email
    if (mail($para, $assunto_email, $corpo_email, $headers)) {
        $mensagem_sucesso = "Sua solicitação foi enviada com sucesso! Entraremos em contato em breve.";
        
        // Limpar formulário
        $assunto = $descricao = '';
    } else {
        $mensagem_erro = "Ocorreu um erro ao enviar sua solicitação. Tente novamente ou entre em contato diretamente.";
    }
}

// Verificar autenticação
if (!isset($_SESSION["cpf"])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Restrito - Ajuda UNIF</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: rgb(28, 112, 28);
                --primary-light: rgb(48, 170, 74);
                --primary-dark: rgb(25, 196, 68);
                --secondary: rgb(14, 138, 35);
                --accent: #ff4081;
                --light: #f8f9fa;
                --dark: #212529;
            }
            
            body {
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .auth-error {
                text-align: center;
                padding: 40px;
                max-width: 500px;
                width: 90%;
            }
            
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                border-top: 5px solid var(--primary);
            }
            
            .auth-error h2 {
                color: var(--primary);
                margin-bottom: 20px;
                font-size: 2rem;
            }
            
            .auth-error p {
                color: #666;
                margin-bottom: 30px;
                font-size: 1.1rem;
                line-height: 1.6;
            }
            
            .auth-btn {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: var(--primary);
                color: white;
                text-decoration: none;
                padding: 15px 30px;
                border-radius: 30px;
                font-weight: 600;
                font-size: 1.1rem;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(28, 112, 28, 0.3);
            }
            
            .auth-btn:hover {
                background: var(--secondary);
                transform: translateY(-3px);
                box-shadow: 0 8px 20px rgba(28, 112, 28, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="auth-error">
            <div class="error-container">
                <h2><i class="fas fa-lock"></i> Acesso Restrito</h2>
                <p>Para acessar a Central de Ajuda, é necessário estar autenticado no sistema.</p>
                <a href="login.php" class="auth-btn">
                    <i class="fas fa-sign-in-alt"></i> Fazer Login
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Ajuda - UNIF</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: rgb(28, 112, 28);
            --primary-light: rgb(48, 170, 74);
            --primary-dark: rgb(25, 196, 68);
            --secondary: rgb(14, 138, 35);
            --accent: #ff4081;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 15px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--dark);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        .sidebar-header {
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 25px;
        }

        .sidebar-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            background: linear-gradient(90deg, #ffffff, var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            list-style: none;
            flex-grow: 1;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .sidebar-menu li.active a,
        .sidebar-menu li a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--accent);
            padding-left: 30px;
        }

        .sidebar-menu li a i {
            width: 24px;
            margin-right: 15px;
            font-size: 1.2rem;
            text-align: center;
        }

        /* Conteúdo Principal */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .content-wrapper {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(28, 112, 28, 0.1);
        }

        .main-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.8rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 15px;
        }

        .subtitle {
            color: var(--gray);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Mensagens */
        .mensagem {
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .mensagem.sucesso {
            background: rgba(25, 196, 68, 0.15);
            color: var(--secondary);
            border: 2px solid rgba(25, 196, 68, 0.3);
        }

        .mensagem.erro {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 2px solid rgba(220, 53, 69, 0.3);
        }

        /* Cards de Ajuda */
        .help-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 768px) {
            .help-container {
                grid-template-columns: 1fr;
            }
        }

        .help-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }

        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .help-card.support {
            border-top: 5px solid var(--primary);
        }

        .help-card.faq {
            border-top: 5px solid var(--secondary);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .card-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            color: var(--dark);
            margin: 0;
        }

        .intro {
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.6;
        }

        /* Formulário */
        .help-form {
            margin-top: 20px;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .input-group input,
        .input-group textarea,
        .input-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .input-group input:focus,
        .input-group textarea:focus,
        .input-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(28, 112, 28, 0.1);
        }

        .input-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .priority-select {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .priority-option {
            flex: 1;
            min-width: 120px;
        }

        .priority-option input[type="radio"] {
            display: none;
        }

        .priority-option label {
            display: block;
            padding: 10px 15px;
            background: var(--light-gray);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .priority-option input[type="radio"]:checked + label {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        .priority-option.urgente label {
            border-left: 4px solid var(--accent);
        }

        .priority-option.normal label {
            border-left: 4px solid var(--primary);
        }

        .priority-option.baixa label {
            border-left: 4px solid var(--secondary);
        }

        /* Botões */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .send-btn {
            background: var(--primary);
            color: white;
        }

        .send-btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(28, 112, 28, 0.3);
        }

        .clear-btn {
            background: var(--light-gray);
            color: var(--dark);
        }

        .clear-btn:hover {
            background: var(--gray);
            color: white;
            transform: translateY(-2px);
        }

        /* FAQ */
        .faq-list {
            margin-top: 20px;
        }

        .faq-item {
            background: var(--light-gray);
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .faq-question {
            padding: 15px 20px;
            font-weight: 600;
            color: var(--dark);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .faq-question:hover {
            background: rgba(28, 112, 28, 0.1);
        }

        .faq-question i {
            transition: var(--transition);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            color: var(--gray);
            line-height: 1.6;
        }

        .faq-item.active .faq-answer {
            padding: 20px;
            max-height: 500px;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* Contato Direto */
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(28, 112, 28, 0.05);
            border-radius: 8px;
            transition: var(--transition);
        }

        .contact-item:hover {
            background: rgba(28, 112, 28, 0.1);
            transform: translateX(5px);
        }

        .contact-item i {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .contact-details h4 {
            margin: 0 0 5px 0;
            color: var(--dark);
        }

        .contact-details p {
            margin: 0;
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Responsividade */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2 span,
            .sidebar-menu li a span {
                display: none;
            }
            
            .sidebar-header h2 {
                justify-content: center;
            }
            
            .sidebar-menu li a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-menu li a i {
                margin-right: 0;
                font-size: 1.4rem;
            }
            
            .main-title {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                padding: 15px 0;
            }
            
            .sidebar-menu {
                display: flex;
                justify-content: space-around;
                padding: 0 15px;
            }
            
            .sidebar-header {
                display: none;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .main-title {
                font-size: 2rem;
            }
            
            .subtitle {
                font-size: 1rem;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .priority-select {
                flex-direction: column;
            }
            
            .priority-option {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-title {
                font-size: 1.8rem;
            }
            
            .card-title {
                font-size: 1.5rem;
            }
            
            .help-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-question-circle"></i> <span>Central de Ajuda</span></h2>
        </div>
        
        <ul class="sidebar-menu">
            <li data-tooltip="Início">
                <a href="inicio.php"><i class="fas fa-home"></i> <span>Início</span></a>
            </li>
            <li data-tooltip="Eventos">
                <a href="eventos.php"><i class="fas fa-calendar-alt"></i> <span>Eventos</span></a>
            </li>
            <li data-tooltip="Participação">
                <a href="participacao.php"><i class="fas fa-users"></i> <span>Participação</span></a>
            </li>
            <li data-tooltip="Perfil">
                <a href="perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a>
            </li>
            <li class="active" data-tooltip="Ajuda">
                <a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a>
            </li>
        </ul>
    </nav>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="content-wrapper">
            <!-- Header -->
            <header class="header">
                <h1 class="main-title">Central de Ajuda e Suporte 🛠️</h1>
                <p class="subtitle">Estamos aqui para ajudar! Envie sua dúvida, problema ou sugestão para nossa equipe.</p>
            </header>

            <!-- Mensagens de Sucesso/Erro -->
            <?php if ($mensagem_sucesso): ?>
                <div class="mensagem sucesso">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensagem_sucesso); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensagem_erro): ?>
                <div class="mensagem erro">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($mensagem_erro); ?>
                </div>
            <?php endif; ?>

            <div class="help-container">
                <!-- Formulário de Suporte -->
                <div class="help-card support">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h3 class="card-title">Solicitar Suporte</h3>
                    </div>
                    
                    <p class="intro">Descreva detalhadamente sua dúvida, problema ou sugestão. Nossa equipe responderá o mais breve possível.</p>
                    
                    <form class="help-form" method="POST" action="">
                        <div class="input-group">
                            <label for="assunto">Assunto *</label>
                            <input type="text" id="assunto" name="assunto" required 
                                   value="<?php echo htmlspecialchars($assunto ?? ''); ?>"
                                   placeholder="Ex: Problema com inscrição, Dúvida sobre pagamento, etc.">
                        </div>

                        <div class="input-group">
                            <label for="prioridade">Prioridade</label>
                            <div class="priority-select">
                                <div class="priority-option urgente">
                                    <input type="radio" id="urgente" name="prioridade" value="urgente">
                                    <label for="urgente">Urgente</label>
                                </div>
                                <div class="priority-option normal">
                                    <input type="radio" id="normal" name="prioridade" value="normal" checked>
                                    <label for="normal">Normal</label>
                                </div>
                                <div class="priority-option baixa">
                                    <input type="radio" id="baixa" name="prioridade" value="baixa">
                                    <label for="baixa">Baixa</label>
                                </div>
                            </div>
                        </div>

                        <div class="input-group">
                            <label for="descricao">Descrição detalhada *</label>
                            <textarea id="descricao" name="descricao" rows="8" required 
                                      placeholder="Descreva com o máximo de detalhes possível o problema ou dúvida. Inclua passos para reproduzir, mensagens de erro, etc."><?php echo htmlspecialchars($descricao ?? ''); ?></textarea>
                        </div>

                        <div class="button-group">
                            <button type="submit" name="enviar_solicitacao" class="btn send-btn">
                                <i class="fas fa-paper-plane"></i> Enviar Solicitação
                            </button>
                            <button type="reset" class="btn clear-btn">
                                <i class="fas fa-eraser"></i> Limpar Formulário
                            </button>
                        </div>
                    </form>
                </div>

                <!-- FAQ e Contato -->
                <div class="help-card faq">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="card-title">FAQ & Contato</h3>
                    </div>
                    
                    <p class="intro">Perguntas frequentes e formas alternativas de contato.</p>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Quanto tempo leva para receber uma resposta?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Normalmente respondemos em até 48 horas úteis. Para questões urgentes, tentamos responder no mesmo dia.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Posso acompanhar o status da minha solicitação?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Sim! Você receberá atualizações por email sobre o status da sua solicitação.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>O que fazer se meu problema for crítico?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Selecione "Urgente" como prioridade e descreva claramente a urgência. Nossa equipe dará atenção imediata.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-info">
                        <h4 style="margin-bottom: 15px; color: var(--dark);">Contato Direto:</h4>
                        
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div class="contact-details">
                                <h4>Email Oficial</h4>
                                <p>unif.ourobranco@ifmg.edu.br</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div class="contact-details">
                                <h4>Horário de Atendimento</h4>
                                <p>Segunda a Sexta, 8h às 18h</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="contact-details">
                                <h4>Importante</h4>
                                <p>Todas as solicitações são registradas e acompanhadas</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // FAQ Accordion
    document.addEventListener('DOMContentLoaded', function() {
        const faqQuestions = document.querySelectorAll('.faq-question');
        
        faqQuestions.forEach(question => {
            question.addEventListener('click', function() {
                const faqItem = this.parentElement;
                faqItem.classList.toggle('active');
            });
        });
        
        // Animação para mensagens
        const mensagens = document.querySelectorAll('.mensagem');
        mensagens.forEach((msg, index) => {
            msg.style.animationDelay = `${index * 0.1}s`;
        });
        
        // Validação do formulário
        const form = document.querySelector('.help-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const assunto = document.getElementById('assunto').value.trim();
                const descricao = document.getElementById('descricao').value.trim();
                
                if (!assunto || !descricao) {
                    e.preventDefault();
                    alert('Por favor, preencha todos os campos obrigatórios.');
                    return false;
                }
                
                if (descricao.length < 20) {
                    e.preventDefault();
                    alert('Por favor, forneça uma descrição mais detalhada (mínimo 20 caracteres).');
                    return false;
                }
                
                // Mostrar loading
                const submitBtn = form.querySelector('[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                submitBtn.disabled = true;
                
                // Restaurar após 3 segundos (caso o envio falhe)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        }
    });
</script>
</body>
</html>