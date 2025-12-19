<?php
session_start();
require_once 'php/conexao.php';

// Verificar autenticação
if (!isset($_SESSION["cpf"])) {
    header("Location: login.php");
    exit();
}

// Verificar se é diretor e pegar o comitê
$is_diretor = false;
$diretor_info = null;
$comite_id = null;

if ($conn && $conn->connect_error === null) {
    $cpf = $_SESSION["cpf"];
    
    // Verificar se o usuário é diretor e pegar o id_comite
    $sql_diretor = "SELECT d.*, c.nome_comite 
                   FROM diretor d 
                   JOIN comite c ON d.id_comite = c.id_comite 
                   WHERE d.cpf = ? 
                   LIMIT 1";
    $stmt = $conn->prepare($sql_diretor);
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $is_diretor = true;
        $diretor_info = $result->fetch_assoc();
        $comite_id = $diretor_info['id_comite'];
    }
    $stmt->close();
}

if (!$is_diretor) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Restrito</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: rgb(28, 112, 28);
                --primary-light: rgb(48, 170, 74);
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
            
            .access-denied {
                text-align: center;
                padding: 40px;
                max-width: 500px;
                width: 90%;
            }
            
            .denied-container {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                border-top: 5px solid var(--primary);
            }
            
            .access-denied h2 {
                color: var(--primary);
                margin-bottom: 20px;
                font-size: 2rem;
            }
            
            .access-denied p {
                color: #666;
                margin-bottom: 30px;
                font-size: 1.1rem;
                line-height: 1.6;
            }
            
            .back-btn {
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
            
            .back-btn:hover {
                background: var(--primary-light);
                transform: translateY(-3px);
                box-shadow: 0 8px 20px rgba(28, 112, 28, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="access-denied">
            <div class="denied-container">
                <h2><i class="fas fa-exclamation-triangle"></i> Acesso Restrito</h2>
                <p>Apenas diretores aprovados podem acessar a organização de sessões do comitê.</p>
                <a href="inicio.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Voltar ao Início
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Buscar delegados do comitê
$delegados = [];
if ($conn && $comite_id) {
    $sql_delegados = "SELECT d.cpf, u.nome, r.nome_representacao, d.representacao
                     FROM delegado d
                     JOIN usuario u ON d.cpf = u.cpf
                     LEFT JOIN representacao r ON d.representacao = r.id_representacao
                     WHERE d.id_comite = ? AND d.id_comite IS NOT NULL
                     ORDER BY r.nome_representacao";
    $stmt = $conn->prepare($sql_delegados);
    $stmt->bind_param("i", $comite_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $delegados[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organização de Sessão - <?php echo htmlspecialchars($diretor_info['nome_comite']); ?></title>
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
            --border-radius: 12px;
            --box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
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

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .comite-info h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .comite-info p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .diretor-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
        }

        .diretor-badge i {
            font-size: 1.2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 40px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        @media (max-width: 1200px) {
            .container {
                grid-template-columns: 1fr;
            }
        }

        /* Timer Section */
        .timer-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .timer-display {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            border-radius: var(--border-radius);
            color: white;
        }

        .current-speaker {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.9);
        }

        .timer {
            font-family: 'Montserrat', monospace;
            font-size: 5rem;
            font-weight: 700;
            margin: 20px 0;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            letter-spacing: 2px;
        }

        .timer.warning {
            color: #ff9800;
        }

        .timer.danger {
            color: #f44336;
        }

        .timer-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .timer-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 140px;
            justify-content: center;
        }

        .btn-start {
            background: var(--primary);
            color: white;
        }

        .btn-pause {
            background: #ff9800;
            color: white;
        }

        .btn-reset {
            background: var(--gray);
            color: white;
        }

        .btn-next {
            background: var(--secondary);
            color: white;
        }

        .timer-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .timer-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Timer Configuration */
        .timer-config {
            background: var(--light-gray);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-top: 30px;
        }

        .config-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .config-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .input-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .input-group input {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            text-align: center;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(28, 112, 28, 0.1);
        }

        /* Speakers Lists */
        .speakers-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            height: fit-content;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .speakers-lists {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        @media (max-width: 768px) {
            .speakers-lists {
                grid-template-columns: 1fr;
            }
        }

        .speaker-list {
            background: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 25px;
            min-height: 300px;
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .list-header h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.4rem;
            color: var(--dark);
        }

        .list-count {
            background: var(--primary);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .speakers-container {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .speakers-container::-webkit-scrollbar {
            width: 8px;
        }

        .speakers-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }

        .speakers-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .speaker-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }

        .speaker-item:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .speaker-item.current {
            border-left-color: var(--accent);
            background: rgba(255, 64, 129, 0.05);
        }

        .speaker-info h4 {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .speaker-info p {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .speaker-controls {
            display: flex;
            gap: 8px;
        }

        .control-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 50%;
            background: var(--light);
            color: var(--dark);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .control-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .control-btn.move {
            background: var(--secondary);
            color: white;
        }

        .control-btn.remove {
            background: #f44336;
            color: white;
        }

        /* Delegados List */
        .delegados-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            margin-top: 30px;
        }

        .delegados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .delegado-card {
            background: var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .delegado-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .delegado-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .country-flag {
            font-size: 1.5rem;
        }

        .delegado-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .delegado-representacao {
            color: var(--primary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .delegado-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .add-btn {
            padding: 8px 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            flex: 1;
        }

        .add-btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        /* Session Controls */
        .session-controls {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .session-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-chamada {
            background: var(--secondary);
            color: white;
        }

        .btn-representacao {
            background: #2196F3;
            color: white;
        }

        .session-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .timer {
                font-size: 4rem;
            }
            
            .delegados-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .timer {
                font-size: 3rem;
            }
            
            .timer-controls {
                flex-direction: column;
                align-items: center;
            }
            
            .timer-btn {
                width: 100%;
                max-width: 300px;
            }
            
            .config-inputs {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 15px 30px;
            }
            
            .header {
                padding: 20px;
            }
            
            .comite-info h1 {
                font-size: 1.8rem;
            }
            
            .timer-section,
            .speakers-section,
            .delegados-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="comite-info">
                <h1>
                    <i class="fas fa-users"></i>
                    <?php echo htmlspecialchars($diretor_info['nome_comite']); ?>
                </h1>
                <p>Sessão de Trabalho - Controle de Oradores</p>
            </div>
            <div class="diretor-badge">
                <i class="fas fa-user-tie"></i>
                <span>Diretor da Sessão</span>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Left Column: Timer and Delegados -->
        <div class="left-column">
            <!-- Timer Section -->
            <div class="timer-section">
                <div class="timer-display">
                    <div class="current-speaker">
                        <i class="fas fa-user-microphone"></i>
                        <span id="currentSpeaker">Aguardando início da sessão...</span>
                    </div>
                    <div class="timer" id="timerDisplay">05:00</div>
                    <div class="speaker-info" id="speakerInfo"></div>
                </div>

                <div class="timer-controls">
                    <button class="timer-btn btn-start" id="startBtn">
                        <i class="fas fa-play"></i> Iniciar
                    </button>
                    <button class="timer-btn btn-pause" id="pauseBtn" disabled>
                        <i class="fas fa-pause"></i> Pausar
                    </button>
                    <button class="timer-btn btn-reset" id="resetBtn">
                        <i class="fas fa-redo"></i> Reiniciar
                    </button>
                    <button class="timer-btn btn-next" id="nextBtn">
                        <i class="fas fa-forward"></i> Próximo
                    </button>
                </div>

                <!-- Timer Configuration -->
                <div class="timer-config">
                    <h3 class="config-title">
                        <i class="fas fa-cog"></i> Configurações do Timer
                    </h3>
                    <div class="config-inputs">
                        <div class="input-group">
                            <label for="minutes">Minutos</label>
                            <input type="number" id="minutes" min="1" max="30" value="5">
                        </div>
                        <div class="input-group">
                            <label for="seconds">Segundos</label>
                            <input type="number" id="seconds" min="0" max="59" value="0">
                        </div>
                        <div class="input-group">
                            <label for="warningTime">Aviso (segundos)</label>
                            <input type="number" id="warningTime" min="0" max="300" value="30">
                        </div>
                    </div>
                    <button class="timer-btn btn-start" id="applyConfig">
                        <i class="fas fa-check"></i> Aplicar Configurações
                    </button>
                </div>
            </div>

            <!-- Delegados Section -->
            <div class="delegados-section">
                <h2 class="section-title">
                    <i class="fas fa-users"></i> Delegados do Comitê
                </h2>
                <p class="intro">Clique em "Adicionar à Fila" para incluir delegados na lista de oradores.</p>
                
                <div class="delegados-grid" id="delegadosList">
                    <?php if (!empty($delegados)): ?>
                        <?php foreach ($delegados as $delegado): ?>
                            <div class="delegado-card" data-delegado-id="<?php echo $delegado['cpf']; ?>">
                                <div class="delegado-header">
                                    <div class="country-flag">🇺🇳</div>
                                    <span class="badge">Delegado</span>
                                </div>
                                <div class="delegado-name"><?php echo htmlspecialchars($delegado['nome']); ?></div>
                                <div class="delegado-representacao">
                                    <?php echo htmlspecialchars($delegado['nome_representacao'] ?? $delegado['representacao'] ?? 'N/A'); ?>
                                </div>
                                <div class="delegado-actions">
                                    <button class="add-btn add-to-queue-btn" 
                                            data-cpf="<?php echo $delegado['cpf']; ?>"
                                            data-name="<?php echo htmlspecialchars($delegado['nome']); ?>"
                                            data-country="<?php echo htmlspecialchars($delegado['nome_representacao'] ?? $delegado['representacao'] ?? 'N/A'); ?>">
                                        <i class="fas fa-plus"></i> Adicionar à Fila
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Nenhum delegado encontrado para este comitê.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Speakers Lists -->
        <div class="right-column">
            <!-- Speakers Lists -->
            <div class="speakers-section">
                <h2 class="section-title">
                    <i class="fas fa-list-ol"></i> Lista de Oradores
                </h2>
                
                <div class="speakers-lists">
                    <!-- Current Speakers -->
                    <div class="speaker-list">
                        <div class="list-header">
                            <h3>Em Espera</h3>
                            <span class="list-count" id="waitingCount">0</span>
                        </div>
                        <div class="speakers-container" id="waitingList">
                            <!-- Lista de espera será populada via JavaScript -->
                        </div>
                    </div>

                    <!-- Speaking Queue -->
                    <div class="speaker-list">
                        <div class="list-header">
                            <h3>Falando Agora</h3>
                            <span class="list-count" id="speakingCount">0</span>
                        </div>
                        <div class="speakers-container" id="speakingList">
                            <!-- Orador atual será mostrado aqui -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session Controls -->
            <div class="session-controls">
                <button class="session-btn btn-chamada" onclick="window.open('chamada.php?comite=<?php echo $comite_id; ?>', '_blank')">
                    <i class="fas fa-clipboard-list"></i> Lista de Chamada
                </button>
                <button class="session-btn btn-representacao" onclick="window.location.href='adicionarRepresentacao.php?comite=<?php echo $comite_id; ?>'">
                    <i class="fas fa-flag"></i> Gerenciar Representações
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais
        let timer;
        let timeLeft = 300; // 5 minutos em segundos
        let isRunning = false;
        let totalTime = 300;
        let warningTime = 30;
        let currentSpeaker = null;
        let waitingQueue = [];
        const comiteId = <?php echo json_encode($comite_id); ?>;

        // Elementos DOM
        const timerDisplay = document.getElementById('timerDisplay');
        const currentSpeakerSpan = document.getElementById('currentSpeaker');
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const resetBtn = document.getElementById('resetBtn');
        const nextBtn = document.getElementById('nextBtn');
        const minutesInput = document.getElementById('minutes');
        const secondsInput = document.getElementById('seconds');
        const warningTimeInput = document.getElementById('warningTime');
        const applyConfigBtn = document.getElementById('applyConfig');
        const waitingList = document.getElementById('waitingList');
        const speakingList = document.getElementById('speakingList');
        const waitingCount = document.getElementById('waitingCount');
        const speakingCount = document.getElementById('speakingCount');

        // Funções do Timer
        function startTimer() {
            console.log('startTimer chamado');
            if (isRunning) {
                console.log('Timer já está rodando');
                return;
            }
            
            if (!currentSpeaker && waitingQueue.length === 0) {
                alert('Adicione delegados à fila de espera primeiro!');
                return;
            }
            
            if (!currentSpeaker && waitingQueue.length > 0) {
                nextSpeaker();
            }
            
            isRunning = true;
            startBtn.disabled = true;
            pauseBtn.disabled = false;
            
            timer = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    isRunning = false;
                    timerDisplay.classList.add('danger');
                    startBtn.disabled = false;
                    pauseBtn.disabled = true;
                    alert('Tempo esgotado!');
                } else if (timeLeft <= warningTime) {
                    timerDisplay.classList.add('warning');
                }
            }, 1000);
        }

        function pauseTimer() {
            console.log('pauseTimer chamado');
            if (!isRunning) return;
            
            clearInterval(timer);
            isRunning = false;
            startBtn.disabled = false;
            pauseBtn.disabled = true;
        }

        function resetTimer() {
            console.log('resetTimer chamado');
            pauseTimer();
            timeLeft = totalTime;
            updateTimerDisplay();
            timerDisplay.classList.remove('warning', 'danger');
        }

        function nextSpeaker() {
            console.log('nextSpeaker chamado');
            if (waitingQueue.length === 0) {
                currentSpeaker = null;
                currentSpeakerSpan.textContent = 'Sessão encerrada';
                speakingList.innerHTML = '<div class="speaker-item"><div class="speaker-info"><p>Nenhum orador no momento</p></div></div>';
                resetTimer();
                return;
            }
            
            // Pegar próximo orador
            currentSpeaker = waitingQueue.shift();
            currentSpeakerSpan.textContent = `${currentSpeaker.name} (${currentSpeaker.country})`;
            timeLeft = totalTime;
            resetTimer();
            updateLists();
            saveToLocalStorage();
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // Funções da Lista de Oradores
        function addToQueue(cpf, name, country) {
            console.log('addToQueue:', cpf, name, country);
            const delegado = {
                id: cpf,
                name: name,
                country: country,
                added: new Date().toLocaleTimeString('pt-BR')
            };
            
            waitingQueue.push(delegado);
            updateLists();
            saveToLocalStorage();
        }

        function removeFromQueue(index) {
            console.log('removeFromQueue:', index);
            waitingQueue.splice(index, 1);
            updateLists();
            saveToLocalStorage();
        }

        function moveToTop(index) {
            console.log('moveToTop:', index);
            if (index > 0) {
                const delegado = waitingQueue[index];
                waitingQueue.splice(index, 1);
                waitingQueue.unshift(delegado);
                updateLists();
                saveToLocalStorage();
            }
        }

        function updateLists() {
            console.log('updateLists chamado');
            // Atualizar lista de espera
            waitingList.innerHTML = '';
            waitingQueue.forEach((delegado, index) => {
                const div = document.createElement('div');
                div.className = 'speaker-item';
                div.innerHTML = `
                    <div class="speaker-info">
                        <h4>${delegado.country}</h4>
                        <p>${delegado.name}</p>
                        <small>Adicionado: ${delegado.added}</small>
                    </div>
                    <div class="speaker-controls">
                        <button class="control-btn move" onclick="moveToTop(${index})" title="Mover para o topo">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button class="control-btn remove" onclick="removeFromQueue(${index})" title="Remover da lista">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                waitingList.appendChild(div);
            });
            
            // Atualizar orador atual
            speakingList.innerHTML = '';
            if (currentSpeaker) {
                const div = document.createElement('div');
                div.className = 'speaker-item current';
                div.innerHTML = `
                    <div class="speaker-info">
                        <h4>${currentSpeaker.country}</h4>
                        <p>${currentSpeaker.name}</p>
                        <small>Tempo restante: <span id="currentTimeLeft"></span></small>
                    </div>
                    <div class="speaker-controls">
                        <button class="control-btn" onclick="nextSpeaker()" title="Próximo orador">
                            <i class="fas fa-forward"></i>
                        </button>
                    </div>
                `;
                speakingList.appendChild(div);
            } else {
                speakingList.innerHTML = '<div class="speaker-item"><div class="speaker-info"><p>Nenhum orador no momento</p></div></div>';
            }
            
            // Atualizar contadores
            waitingCount.textContent = waitingQueue.length;
            speakingCount.textContent = currentSpeaker ? 1 : 0;
        }

        // Local Storage
        function saveToLocalStorage() {
            const data = {
                waitingQueue: waitingQueue,
                currentSpeaker: currentSpeaker,
                totalTime: totalTime,
                warningTime: warningTime
            };
            localStorage.setItem('comiteSession_' + comiteId, JSON.stringify(data));
            console.log('Dados salvos no localStorage');
        }

        function loadFromLocalStorage() {
            const saved = localStorage.getItem('comiteSession_' + comiteId);
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    waitingQueue = data.waitingQueue || [];
                    currentSpeaker = data.currentSpeaker || null;
                    totalTime = data.totalTime || 300;
                    warningTime = data.warningTime || 30;
                    
                    minutesInput.value = Math.floor(totalTime / 60);
                    secondsInput.value = totalTime % 60;
                    warningTimeInput.value = warningTime;
                    
                    if (currentSpeaker) {
                        currentSpeakerSpan.textContent = `${currentSpeaker.name} (${currentSpeaker.country})`;
                    }
                    
                    updateTimerDisplay();
                    updateLists();
                    console.log('Dados carregados do localStorage');
                } catch (e) {
                    console.error('Erro ao carregar dados salvos:', e);
                }
            }
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado, comiteId:', comiteId);
            
            // Adicionar event listeners aos botões
            document.querySelectorAll('.add-to-queue-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const cpf = this.getAttribute('data-cpf');
                    const name = this.getAttribute('data-name');
                    const country = this.getAttribute('data-country');
                    addToQueue(cpf, name, country);
                });
            });

            // Event listeners do timer
            startBtn.addEventListener('click', startTimer);
            pauseBtn.addEventListener('click', pauseTimer);
            resetBtn.addEventListener('click', resetTimer);
            nextBtn.addEventListener('click', nextSpeaker);

            // Configuração
            applyConfigBtn.addEventListener('click', function() {
                const minutes = parseInt(minutesInput.value) || 5;
                const seconds = parseInt(secondsInput.value) || 0;
                warningTime = parseInt(warningTimeInput.value) || 30;
                
                totalTime = (minutes * 60) + seconds;
                if (currentSpeaker) {
                    timeLeft = totalTime;
                }
                updateTimerDisplay();
                saveToLocalStorage();
                
                alert('Configurações aplicadas com sucesso!');
            });

            // Carregar dados salvos
            loadFromLocalStorage();

            // Atualizar tempo restante do orador atual
            setInterval(() => {
                if (isRunning) {
                    const currentTimeElement = document.getElementById('currentTimeLeft');
                    if (currentTimeElement && timeLeft >= 0) {
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        currentTimeElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    }
                }
            }, 100);
        });

        // Salvar dados ao sair
        window.addEventListener('beforeunload', function() {
            saveToLocalStorage();
        });
    </script>
</body>
</html>