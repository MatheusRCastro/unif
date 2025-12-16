<?php
session_start();

// ✅ Ativar erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Verificar arquivo de conexão
$conexao_path = 'php/conexao.php';
if (!file_exists($conexao_path)) {
    die("Erro: Arquivo de conexão não encontrado!");
}

require_once $conexao_path;

// ✅ Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['cpf']) || !isset($_SESSION['adm']) || $_SESSION['adm'] != 1) {
    echo "<script>alert('Acesso restrito a administradores!'); window.location.href = 'login.html';</script>";
    exit();
}

// ✅ Processar logout se solicitado
if (isset($_GET['logout'])) {
    session_destroy();
    echo "<script>alert('Logout realizado com sucesso!'); window.location.href = 'login.html';</script>";
    exit();
}

// ✅ Processar visualização de PDF
if (isset($_GET['ver_pdf']) && isset($_GET['cpf'])) {
    $cpf = $_GET['cpf'];
    
    // Buscar PDF no banco
    $sql = "SELECT pdf_pagamento FROM delegado WHERE cpf = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $pdf_blob = $row['pdf_pagamento'];
        
        if ($pdf_blob) {
            // Retornar como PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="pagamento_' . $cpf . '.pdf"');
            echo $pdf_blob;
            exit();
        }
    }
    
    // Se não encontrar PDF
    header('HTTP/1.0 404 Not Found');
    echo "PDF não encontrado";
    exit();
}

// ✅ Processar ações AJAX de alteração de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && isset($_POST['cpf'])) {
    $cpf = $_POST['cpf'];
    $acao = $_POST['acao'];
    
    if (in_array($acao, ['aprovado', 'reprovado', 'pendente'])) {
        $sql = "UPDATE delegado SET status_pagamento = ? WHERE cpf = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $acao, $cpf);
        
        if ($stmt->execute()) {
            echo "SUCESSO";
        } else {
            echo "ERRO";
        }
        $stmt->close();
    }
    exit();
}

// ✅ Buscar dados dos delegados
$delegados = [];
$contadores = [
    'pendente' => 0,
    'aprovado' => 0,
    'reprovado' => 0,
    'total' => 0
];

if ($conn) {
    $sql = "SELECT 
                d.cpf,
                d.status_pagamento,
                d.id_delegacao,
                d.pdf_pagamento,
                u.nome,
                u.instituicao,
                u.email,
                u.telefone,
                dl.nome as nome_delegacao,
                c.nome_comite
            FROM delegado d
            LEFT JOIN usuario u ON d.cpf = u.cpf
            LEFT JOIN delegacao dl ON d.id_delegacao = dl.id_delegacao
            LEFT JOIN comite c ON d.id_comite = c.id_comite
            WHERE d.status_pagamento IN ('pendente', 'aprovado', 'reprovado')
            ORDER BY 
                FIELD(d.status_pagamento, 'pendente', 'aprovado', 'reprovado'),
                u.nome";
    
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $delegados[] = $row;
            $contadores[$row['status_pagamento']]++;
            $contadores['total']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Pagamentos - UNIF</title>
    <!-- PDF.js Library -->
    <script src="pdfjs/pdf.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, #1a237e, #3949ab);
            color: white;
            padding: 20px 0;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            position: relative;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-role {
            font-size: 12px;
            opacity: 0.9;
        }
        
        h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .logout-btn, .back-btn {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover, .back-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .back-btn {
            background-color: #6c757d;
            margin-right: 10px;
        }
        
        /* Modal para visualização de PDF */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            background: linear-gradient(135deg,rgb(24, 179, 57),rgb(17, 184, 58));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        
        .modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .modal-body {
            flex: 1;
            overflow: auto;
            padding: 20px;
            position: relative;
        }
        
        #pdf-viewer {
            width: 100%;
            min-height: 500px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: auto;
        }
        
        .pdf-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .pdf-btn {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .pdf-btn:hover {
            background-color: #0056b3;
        }
        
        .pdf-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .pdf-page-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 10px;
        }
        
        /* Resto do CSS anterior permanece igual */
        .filtros {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filtro-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filtro-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-aprovado {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-reprovado {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .tabela-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background-color: #f8f9fa;
        }
        
        th {
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: top;
        }
        
        tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .acoes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-aprovar {
            background-color: #28a745;
            color: white;
        }
        
        .btn-aprovar:hover {
            background-color: #218838;
        }
        
        .btn-reprovar {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-reprovar:hover {
            background-color: #c82333;
        }
        
        .btn-desfazer {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-desfazer:hover {
            background-color: #5a6268;
        }
        
        .btn-ver-pdf {
            background-color: #007bff;
            color: white;
        }
        
        .btn-ver-pdf:hover {
            background-color: #0056b3;
        }
        
        .instituicao-badge {
            display: inline-block;
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 5px;
        }
        
        .contato-info {
            font-size: 12px;
            color: #6c757d;
            margin-top: 3px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        .mensagem {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            display: none;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .mensagem-sucesso {
            background-color: #28a745;
        }
        
        .mensagem-erro {
            background-color: #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .contadores {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .contador {
            flex: 1;
            min-width: 150px;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .contador-numero {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .contador-pendente .contador-numero {
            color: #ffc107;
        }
        
        .contador-aprovado .contador-numero {
            color: #28a745;
        }
        
        .contador-reprovado .contador-numero {
            color: #dc3545;
        }
        
        .contador-total .contador-numero {
            color: #007bff;
        }
        
        .contador-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .sem-dados {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
        }
        
        .sem-pdf {
            color: #6c757d;
            font-style: italic;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .filtros {
                flex-direction: column;
            }
            
            .tabela-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
            
            .acoes {
                flex-direction: column;
            }
            
            .contador {
                min-width: 120px;
            }
            
            .contador-numero {
                font-size: 24px;
            }
            
            .modal-content {
                width: 95%;
                max-height: 95vh;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div>
                <h1>📋 Análise de Pagamentos - UNIF</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php 
                            $iniciais = substr($_SESSION['nome'] ?? 'A', 0, 1);
                            echo strtoupper($iniciais); 
                        ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['nome'] ?? 'Administrador'); ?></div>
                        <div class="user-role"><?php echo ($_SESSION['adm'] ?? 0) ? 'Administrador' : 'Usuário'; ?></div>
                    </div>
                </div>
            </div>
            <div>
                <a href="painelControle.php" class="back-btn">← Voltar ao Painel</a>
                <a href="?logout=true" class="logout-btn" onclick="return confirm('Deseja realmente sair?')">Sair</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="contadores">
            <div class="contador contador-pendente">
                <div class="contador-numero" id="contador-pendente"><?php echo $contadores['pendente']; ?></div>
                <div class="contador-label">Pendentes</div>
            </div>
            <div class="contador contador-aprovado">
                <div class="contador-numero" id="contador-aprovado"><?php echo $contadores['aprovado']; ?></div>
                <div class="contador-label">Aprovados</div>
            </div>
            <div class="contador contador-reprovado">
                <div class="contador-numero" id="contador-reprovado"><?php echo $contadores['reprovado']; ?></div>
                <div class="contador-label">Reprovados</div>
            </div>
            <div class="contador contador-total">
                <div class="contador-numero" id="contador-total"><?php echo $contadores['total']; ?></div>
                <div class="contador-label">Total de Delegados</div>
            </div>
        </div>
        
        <div class="filtros">
            <div class="filtro-group">
                <label for="filtro-status">Status do Pagamento</label>
                <select id="filtro-status">
                    <option value="todos">Todos os Status</option>
                    <option value="pendente">Pendente</option>
                    <option value="aprovado">Aprovado</option>
                    <option value="reprovado">Reprovado</option>
                </select>
            </div>
            <div class="filtro-group">
                <label for="filtro-instituicao">Instituição</label>
                <input type="text" id="filtro-instituicao" placeholder="Filtrar por instituição...">
            </div>
            <div class="filtro-group">
                <label for="filtro-nome">Nome do Delegado</label>
                <input type="text" id="filtro-nome" placeholder="Filtrar por nome...">
            </div>
            <div class="filtro-group">
                <label for="filtro-comite">Comitê</label>
                <input type="text" id="filtro-comite" placeholder="Filtrar por comitê...">
            </div>
        </div>
        
        <div class="tabela-container">
            <div class="loading" id="loading">Processando...</div>
            <table id="tabela-delegados">
                <thead>
                    <tr>
                        <th>Nome e Contato</th>
                        <th>CPF</th>
                        <th>Instituição</th>
                        <th>Delegação</th>
                        <th>Comitê</th>
                        <th>Status Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tabela-corpo">
                    <?php if (empty($delegados)): ?>
                        <tr>
                            <td colspan="7" class="sem-dados">Nenhum delegado encontrado no sistema</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($delegados as $delegado): 
                            $tem_pdf = !empty($delegado['pdf_pagamento']);
                        ?>
                        <tr data-cpf="<?php echo htmlspecialchars($delegado['cpf']); ?>"
                            data-status="<?php echo htmlspecialchars($delegado['status_pagamento']); ?>"
                            data-instituicao="<?php echo htmlspecialchars($delegado['instituicao']); ?>"
                            data-nome="<?php echo htmlspecialchars($delegado['nome']); ?>"
                            data-comite="<?php echo htmlspecialchars($delegado['nome_comite'] ?? ''); ?>"
                            data-tem-pdf="<?php echo $tem_pdf ? 'sim' : 'nao'; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($delegado['nome']); ?></strong><br>
                                <div class="contato-info">
                                    ✉️ <?php echo htmlspecialchars($delegado['email']); ?><br>
                                    📱 <?php echo htmlspecialchars($delegado['telefone']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($delegado['cpf']); ?></td>
                            <td>
                                <div class="instituicao-badge">
                                    <?php echo htmlspecialchars($delegado['instituicao']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($delegado['nome_delegacao'] ?? 'Individual'); ?></td>
                            <td><?php echo htmlspecialchars($delegado['nome_comite'] ?? 'Não atribuído'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($delegado['status_pagamento']); ?>">
                                    <?php echo htmlspecialchars($delegado['status_pagamento']); ?>
                                </span><br>
                                <?php if ($tem_pdf): ?>
                                    <small style="color: #28a745;">✓ PDF disponível</small>
                                <?php else: ?>
                                    <small class="sem-pdf">✗ PDF não enviado</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acoes">
                                    <?php if ($tem_pdf): ?>
                                    <button class="btn btn-ver-pdf" onclick="verPDF('<?php echo $delegado['cpf']; ?>', '<?php echo htmlspecialchars($delegado['nome']); ?>')">Ver PDF</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($delegado['status_pagamento'] != 'aprovado'): ?>
                                    <button class="btn btn-aprovar" onclick="alterarStatus('<?php echo $delegado['cpf']; ?>', 'aprovado')">Aprovar</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($delegado['status_pagamento'] != 'reprovado'): ?>
                                    <button class="btn btn-reprovar" onclick="alterarStatus('<?php echo $delegado['cpf']; ?>', 'reprovado')">Reprovar</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($delegado['status_pagamento'] != 'pendente'): ?>
                                    <button class="btn btn-desfazer" onclick="alterarStatus('<?php echo $delegado['cpf']; ?>', 'pendente')">Pendente</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal para visualização de PDF -->
    <div class="modal-overlay" id="pdfModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="pdfModalTitle">Visualizando PDF</div>
                <button class="modal-close" onclick="fecharModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="pdf-viewer"></div>
                <div class="pdf-controls">
                    <button class="pdf-btn" id="prevPage" onclick="prevPage()">← Anterior</button>
                    <div class="pdf-page-info">
                        <span>Página: <span id="pageNum"></span> / <span id="pageCount"></span></span>
                    </div>
                    <button class="pdf-btn" id="nextPage" onclick="nextPage()">Próxima →</button>
                    <button class="pdf-btn" onclick="zoomIn()">+</button>
                    <button class="pdf-btn" onclick="zoomOut()">-</button>
                    <button class="pdf-btn" onclick="downloadPDF()">Download</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="mensagem" class="mensagem"></div>

    <script>
        // Variáveis globais para o PDF
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.5;
        
        // ✅ Abrir modal e carregar PDF
        function verPDF(cpf, nome) {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('pdfModalTitle').textContent = `PDF de Pagamento - ${nome}`;
            document.getElementById('pdfModal').style.display = 'flex';
            
            // Limpar visualizador anterior
            document.getElementById('pdf-viewer').innerHTML = '';
            
            // Carregar PDF usando PDF.js
            const pdfUrl = `analise_pagamentos.php?ver_pdf=1&cpf=${encodeURIComponent(cpf)}&t=${Date.now()}`;
            
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                pdfDoc = pdf;
                document.getElementById('pageCount').textContent = pdf.numPages;
                renderPage(pageNum);
                document.getElementById('loading').style.display = 'none';
            }).catch(function(error) {
                console.error('Erro ao carregar PDF:', error);
                document.getElementById('pdf-viewer').innerHTML = 
                    '<div style="text-align: center; padding: 50px; color: #dc3545;">' +
                    '<h3>Erro ao carregar PDF</h3>' +
                    '<p>Não foi possível carregar o documento.</p>' +
                    '</div>';
                document.getElementById('loading').style.display = 'none';
            });
        }
        
        // ✅ Renderizar página do PDF
        function renderPage(num) {
            pageRendering = true;
            
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({scale: scale});
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const viewer = document.getElementById('pdf-viewer');
                
                viewer.innerHTML = '';
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                viewer.appendChild(canvas);
                
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                
                const renderTask = page.render(renderContext);
                
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
                
                document.getElementById('pageNum').textContent = num;
            });
            
            // Atualizar botões de navegação
            document.getElementById('prevPage').disabled = num <= 1;
            document.getElementById('nextPage').disabled = num >= pdfDoc.numPages;
        }
        
        // ✅ Navegação entre páginas
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }
        
        function prevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }
        
        function nextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }
        
        // ✅ Zoom
        function zoomIn() {
            scale += 0.25;
            renderPage(pageNum);
        }
        
        function zoomOut() {
            if (scale > 0.5) {
                scale -= 0.25;
                renderPage(pageNum);
            }
        }
        
        // ✅ Download do PDF
        function downloadPDF() {
            const cpf = document.querySelector('#pdfModalTitle').textContent.split(' - ')[1];
            window.open(`analise_pagamentos.php?ver_pdf=1&cpf=${cpf}&download=1`, '_blank');
        }
        
        // ✅ Fechar modal
        function fecharModal() {
            document.getElementById('pdfModal').style.display = 'none';
            pdfDoc = null;
            pageNum = 1;
        }
        
        // ✅ Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharModal();
            }
        });
        
        // ✅ Fechar modal clicando fora
        document.getElementById('pdfModal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
        
        // ✅ Contadores e filtros (mantidos do código anterior)
        function atualizarContadores() {
            const linhasVisiveis = document.querySelectorAll('#tabela-corpo tr[data-cpf]:not([style*="display: none"])');
            
            const pendentes = Array.from(linhasVisiveis).filter(row => 
                row.getAttribute('data-status') === 'pendente'
            ).length;
            
            const aprovados = Array.from(linhasVisiveis).filter(row => 
                row.getAttribute('data-status') === 'aprovado'
            ).length;
            
            const reprovados = Array.from(linhasVisiveis).filter(row => 
                row.getAttribute('data-status') === 'reprovado'
            ).length;
            
            document.getElementById('contador-pendente').textContent = pendentes;
            document.getElementById('contador-aprovado').textContent = aprovados;
            document.getElementById('contador-reprovado').textContent = reprovados;
            document.getElementById('contador-total').textContent = linhasVisiveis.length;
        }
        
        function aplicarFiltros() {
            const filtroStatus = document.getElementById('filtro-status').value;
            const filtroInstituicao = document.getElementById('filtro-instituicao').value.toLowerCase();
            const filtroNome = document.getElementById('filtro-nome').value.toLowerCase();
            const filtroComite = document.getElementById('filtro-comite').value.toLowerCase();
            
            const linhas = document.querySelectorAll('#tabela-corpo tr[data-cpf]');
            let linhasVisiveis = 0;
            
            linhas.forEach(linha => {
                const status = linha.getAttribute('data-status');
                const instituicao = linha.getAttribute('data-instituicao').toLowerCase();
                const nome = linha.getAttribute('data-nome').toLowerCase();
                const comite = linha.getAttribute('data-comite').toLowerCase();
                
                let mostrar = true;
                
                if (filtroStatus !== 'todos' && status !== filtroStatus) {
                    mostrar = false;
                }
                
                if (filtroInstituicao && !instituicao.includes(filtroInstituicao)) {
                    mostrar = false;
                }
                
                if (filtroNome && !nome.includes(filtroNome)) {
                    mostrar = false;
                }
                
                if (filtroComite && !comite.includes(filtroComite)) {
                    mostrar = false;
                }
                
                if (mostrar) {
                    linha.style.display = '';
                    linhasVisiveis++;
                } else {
                    linha.style.display = 'none';
                }
            });
            
            atualizarContadores();
        }
        
        function alterarStatus(cpf, novoStatus) {
            if (!confirm(`Deseja realmente alterar o status para "${novoStatus}"?`)) {
                return;
            }
            
            document.getElementById('loading').style.display = 'block';
            
            const formData = new FormData();
            formData.append('cpf', cpf);
            formData.append('acao', novoStatus);
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            
            xhr.onload = function() {
                document.getElementById('loading').style.display = 'none';
                
                if (xhr.status === 200) {
                    const resposta = xhr.responseText.trim();
                    
                    if (resposta === 'SUCESSO') {
                        const linha = document.querySelector(`tr[data-cpf="${cpf}"]`);
                        if (linha) {
                            linha.setAttribute('data-status', novoStatus);
                            
                            const badge = linha.querySelector('.status-badge');
                            badge.className = `status-badge status-${novoStatus}`;
                            badge.textContent = novoStatus;
                            
                            const acoesDiv = linha.querySelector('.acoes');
                            acoesDiv.innerHTML = '';
                            
                            // Adicionar botão de ver PDF se existir
                            const temPDF = linha.getAttribute('data-tem-pdf') === 'sim';
                            if (temPDF) {
                                const nome = linha.querySelector('strong').textContent;
                                acoesDiv.innerHTML += `<button class="btn btn-ver-pdf" onclick="verPDF('${cpf}', '${nome.replace(/'/g, "\\'")}')">Ver PDF</button>`;
                            }
                            
                            // Botões de status
                            if (novoStatus !== 'aprovado') {
                                acoesDiv.innerHTML += `<button class="btn btn-aprovar" onclick="alterarStatus('${cpf}', 'aprovado')">Aprovar</button>`;
                            }
                            
                            if (novoStatus !== 'reprovado') {
                                acoesDiv.innerHTML += `<button class="btn btn-reprovar" onclick="alterarStatus('${cpf}', 'reprovado')">Reprovar</button>`;
                            }
                            
                            if (novoStatus !== 'pendente') {
                                acoesDiv.innerHTML += `<button class="btn btn-desfazer" onclick="alterarStatus('${cpf}', 'pendente')">Pendente</button>`;
                            }
                        }
                        
                        mostrarMensagem(`✅ Status alterado para "${novoStatus}" com sucesso!`, 'sucesso');
                        atualizarContadores();
                    } else {
                        mostrarMensagem('❌ Erro ao alterar status', 'erro');
                    }
                } else {
                    mostrarMensagem('❌ Erro na comunicação com o servidor', 'erro');
                }
            };
            
            xhr.onerror = function() {
                document.getElementById('loading').style.display = 'none';
                mostrarMensagem('❌ Erro na comunicação com o servidor', 'erro');
            };
            
            xhr.send(formData);
        }
        
        function mostrarMensagem(texto, tipo) {
            const mensagemDiv = document.getElementById('mensagem');
            mensagemDiv.textContent = texto;
            mensagemDiv.className = `mensagem mensagem-${tipo}`;
            mensagemDiv.style.display = 'block';
            
            setTimeout(() => {
                mensagemDiv.style.display = 'none';
            }, 3000);
        }
        
        // ✅ Event listeners
        document.getElementById('filtro-status').addEventListener('change', aplicarFiltros);
        document.getElementById('filtro-instituicao').addEventListener('input', aplicarFiltros);
        document.getElementById('filtro-nome').addEventListener('input', aplicarFiltros);
        document.getElementById('filtro-comite').addEventListener('input', aplicarFiltros);
        
        // ✅ Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            atualizarContadores();
            aplicarFiltros();
        });
    </script>
</body>
</html>