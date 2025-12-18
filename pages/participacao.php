<?php
session_start();
require_once 'php/conexao.php'; // Para pegar dados da UNIF atual

// Buscar UNIF atual
$unif_atual = null;
$unif_nome = "UNIF";
$unif_ano = date('Y');

if ($conn && $conn->connect_error === null) {
    $sql = "SELECT * FROM unif ORDER BY data_inicio_unif DESC LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $unif_atual = $result->fetch_assoc();
        if (!empty($unif_atual['nome'])) {
            $unif_nome = $unif_atual['nome'];
        }
        if (!empty($unif_atual['data_inicio_unif'])) {
            $unif_ano = date('Y', strtotime($unif_atual['data_inicio_unif']));
        } elseif (preg_match('/\d{4}/', $unif_nome, $matches)) {
            $unif_ano = $matches[0];
        }
    }
}

// Verificar se o usuário está autenticado
if (!isset($_SESSION["cpf"])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Restrito - <?php echo $unif_nome; ?></title>
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
                <p>Para participar da <?php echo $unif_nome; ?>, é necessário estar autenticado no sistema.</p>
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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolha Sua Participação - <?php echo $unif_nome; ?></title>
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

        /* Sidebar Modernizada */
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
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 50px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(28, 112, 28, 0.1);
        }

        .main-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 3rem;
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

        /* Container Principal */
        .main-content-wrapper {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 40px;
            margin-bottom: 40px;
        }

        @media (max-width: 992px) {
            .main-content-wrapper {
                grid-template-columns: 1fr;
            }
            
            .logo-section {
                order: -1;
                text-align: center;
                margin-bottom: 40px;
            }
        }

        /* Cards de Papéis */
        .roles-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .role-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-top: 5px solid transparent;
            position: relative;
        }

        .role-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .role-card:nth-child(1) {
            border-top-color: var(--primary);
        }

        .role-card:nth-child(2) {
            border-top-color: var(--secondary);
        }

        .role-card:nth-child(3) {
            border-top-color: var(--accent);
        }

        .role-btn {
            width: 100%;
            padding: 25px;
            border: none;
            background: none;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: var(--transition);
        }

        .role-btn:hover {
            background: rgba(28, 112, 28, 0.05);
        }

        .role-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
            display: block;
            transition: var(--transition);
        }

        .role-btn:hover .role-icon {
            transform: scale(1.1);
        }

        .role-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .role-card:nth-child(1) .role-title {
            color: var(--primary);
        }

        .role-card:nth-child(2) .role-title {
            color: var(--secondary);
        }

        .role-card:nth-child(3) .role-title {
            color: var(--accent);
        }

        .role-description {
            padding: 0 25px 25px 25px;
            opacity: 0;
            height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            color: var(--gray);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .role-card:hover .role-description {
            opacity: 1;
            height: auto;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
        }

        /* Seção do Logo */
        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 2px dashed rgba(28, 112, 28, 0.2);
        }

        .logo {
            max-width: 180px;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            transition: var(--transition);
        }

        .logo:hover {
            transform: rotate(-5deg) scale(1.05);
        }

        .logo-text {
            text-align: center;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary);
            line-height: 1.4;
        }

        /* Instruções */
        .instructions {
            background: white;
            padding: 20px 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            margin-top: 30px;
            border-left: 4px solid var(--primary);
        }

        .instructions p {
            color: var(--gray);
            font-size: 1rem;
            margin: 0;
        }

        .instructions strong {
            color: var(--primary);
        }

        /* Badge para status das inscrições */
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-open {
            background: rgba(25, 196, 68, 0.15);
            color: var(--secondary);
            border: 1px solid rgba(25, 196, 68, 0.3);
        }

        .status-closed {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Responsividade */
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
                font-size: 2.2rem;
            }
            
            .subtitle {
                font-size: 1rem;
            }
            
            .roles-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-title {
                font-size: 1.8rem;
            }
            
            .role-title {
                font-size: 1.5rem;
            }
            
            .logo {
                max-width: 150px;
            }
            
            .logo-text {
                font-size: 1.2rem;
            }
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        /* Efeito de brilho nos cards */
        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .role-card:hover::before {
            transform: translateX(100%);
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-users"></i> <?php echo $unif_nome; ?></h2>
        </div>
        
        <ul class="sidebar-menu">
            <li data-tooltip="Início">
                <a href="inicio.php"><i class="fas fa-home"></i> <span>Início</span></a>
            </li>
            <li class="active" data-tooltip="Participação">
                <a href="participacao.php"><i class="fas fa-users"></i> <span>Participação</span></a>
            </li>
            <li data-tooltip="Perfil">
                <a href="perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a>
            </li>
            <li data-tooltip="Eventos">
                <a href="eventos.php"><i class="fas fa-calendar-alt"></i> <span>Eventos</span></a>
            </li>
            <li data-tooltip="Ajuda">
                <a href="ajuda.php"><i class="fas fa-question-circle"></i> <span>Ajuda</span></a>
            </li>
        </ul>
    </nav>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="content-wrapper">
            <header class="header fade-in">
                <h1 class="main-title">Escolha seu papel na <?php echo $unif_nome; ?></h1>
                <p class="subtitle">Selecione uma das funções abaixo e descubra onde você pode fazer a diferença nesta experiência diplomática</p>
            </header>

            <div class="main-content-wrapper">
                <div class="roles-container">
                    <!-- Delegado -->
                    <div class="role-card fade-in delay-1">
                        <span class="status-badge status-open">Inscrições Abertas</span>
                        <button class="role-btn" onclick="window.location.href='entraComite.php'">
                            <div class="role-icon">👨‍💼</div>
                            <span class="role-title">Delegado</span>
                            <small>Representante Diplomático</small>
                        </button>
                        <div class="role-description">
                            <p>Como delegado, você será o porta-voz oficial de um país ou organização, defendendo seus interesses nas negociações internacionais. Esta função exige pesquisa aprofundada sobre a política externa da nação representada, habilidades de oratória, negociação estratégica e capacidade de construir coalizões diplomáticas.</p>
                            <div style="margin-top: 15px; font-size: 0.9rem;">
                                <p><strong>Responsabilidades:</strong></p>
                                <ul style="text-align: left; margin: 10px 0; padding-left: 20px;">
                                    <li>Elaborar discursos e propostas</li>
                                    <li>Participar ativamente dos debates</li>
                                    <li>Negociar resoluções</li>
                                    <li>Representar fielmente a posição oficial</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Mesa Diretora -->
                    <div class="role-card fade-in delay-2">
                        <span class="status-badge status-open">Inscrições Abertas</span>
                        <button class="role-btn" onclick="window.location.href='inscricaoMesa.php'">
                            <div class="role-icon">👨‍⚖️</div>
                            <span class="role-title">Mesa Diretora</span>
                            <small>Moderador Oficial</small>
                        </button>
                        <div class="role-description">
                            <p>A mesa diretora é responsável por conduzir os trabalhos do comitê com imparcialidade e eficiência. Você será o guardião das regras de procedimento, garantindo que o debate flua de maneira organizada, produtiva e respeitosa. Esta função desenvolve habilidades de liderança, tomada de decisão e gestão de conflitos.</p>
                            <div style="margin-top: 15px; font-size: 0.9rem;">
                                <p><strong>Responsabilidades:</strong></p>
                                <ul style="text-align: left; margin: 10px 0; padding-left: 20px;">
                                    <li>Moderar debates e discursos</li>
                                    <li>Aplicar as regras de procedimento</li>
                                    <li>Organizar a ordem dos trabalhos</li>
                                    <li>Manter a disciplina na sessão</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Staff -->
                    <div class="role-card fade-in delay-3">
                        <span class="status-badge status-open">Inscrições Abertas</span>
                        <button class="role-btn" onclick="window.location.href='inscricaoStaff.php'">
                            <div class="role-icon">👨‍💻</div>
                            <span class="role-title">Staff</span>
                            <small>Suporte Operacional</small>
                        </button>
                        <div class="role-description">
                            <p>O staff é a espinha dorsal operacional da simulação, atuando nos bastidores para garantir que tudo funcione perfeitamente. Você será responsável pelo suporte logístico, técnico e administrativo, trabalhando em equipe para resolver problemas e facilitar a comunicação entre todos os participantes.</p>
                            <div style="margin-top: 15px; font-size: 0.9rem;">
                                <p><strong>Responsabilidades:</strong></p>
                                <ul style="text-align: left; margin: 10px 0; padding-left: 20px;">
                                    <li>Suporte logístico e técnico</li>
                                    <li>Distribuição de documentos</li>
                                    <li>Controle de acesso e organização</li>
                                    <li>Comunicação entre participantes</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logo Section -->
                <div class="logo-section fade-in delay-2">
                    <img src="images/unif.png" alt="Logo UNIF" class="logo">
                    <p class="logo-text">Simulação Diplomática<br><strong><?php echo $unif_ano; ?> Edition</strong></p>
                    <div style="margin-top: 20px; font-size: 0.9rem; color: var(--gray);">
                        <p><i class="fas fa-clock" style="margin-right: 5px;"></i> Inscrições até: 31/12/<?php echo $unif_ano; ?></p>
                        <p><i class="fas fa-users" style="margin-right: 5px;"></i> Vagas limitadas</p>
                    </div>
                </div>
            </div>

            <div class="instructions fade-in delay-3">
                <p><strong>Dica:</strong> Passe o mouse sobre cada função para ver detalhes completos. Clique para iniciar sua inscrição!</p>
            </div>
        </div>
    </main>
</div>

<script>
    // Efeito de tooltip para cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.role-card');
        
        cards.forEach(card => {
            // Efeito de brilho ao passar o mouse
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
            
            // Permitir clique em toda a área do card
            card.addEventListener('click', function(e) {
                if (!e.target.classList.contains('role-btn')) {
                    const btn = this.querySelector('.role-btn');
                    if (btn) {
                        window.location.href = btn.getAttribute('onclick').match(/'([^']+)'/)[1];
                    }
                }
            });
        });
        
        // Adicionar classe de animação aos elementos
        const fadeElements = document.querySelectorAll('.fade-in');
        fadeElements.forEach((el, index) => {
            el.style.animationDelay = `${(index + 1) * 0.2}s`;
        });
    });
</script>
</body>
</html>