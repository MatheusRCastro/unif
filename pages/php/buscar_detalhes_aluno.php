<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar parâmetros
if (!isset($_GET['cpf']) || !isset($_GET['delegacao'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

$cpf = $_GET['cpf'];
$id_delegacao = $_GET['delegacao'];

// Buscar detalhes do aluno com segurança
$sql = "
    SELECT 
        d.cpf,
        d.id_delegacao,
        d.id_comite,
        d.representacao,
        d.comite_desejado,
        d.primeira_op_representacao,
        d.segunda_op_representacao,
        d.terceira_op_representacao,
        d.segunda_op_comite,
        d.terceira_op_comite,
        d.aprovado_delegacao,
        d.justificativa,
        u.nome as nome_aluno,
        u.email,
        u.telefone,
        c.nome_comite,
        c.tipo_comite,
        r.nome_representacao,
        c2.nome_comite as segunda_op_nome,
        c3.nome_comite as terceira_op_nome,
        r2.nome_representacao as segunda_op_rep_nome,
        r3.nome_representacao as terceira_op_rep_nome
    FROM delegado d
    INNER JOIN usuario u ON d.cpf = u.cpf
    LEFT JOIN comite c ON d.id_comite = c.id_comite
    LEFT JOIN comite c2 ON d.segunda_op_comite = c2.id_comite
    LEFT JOIN comite c3 ON d.terceira_op_comite = c3.id_comite
    LEFT JOIN representacao r ON d.representacao = r.id_representacao
    LEFT JOIN representacao r2 ON d.segunda_op_representacao = r2.id_representacao
    LEFT JOIN representacao r3 ON d.terceira_op_representacao = r3.id_representacao
    WHERE d.cpf = ?
    AND d.id_delegacao = ?
    AND d.aprovado_delegacao = 'pendente'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $cpf, $id_delegacao);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $aluno = $result->fetch_assoc();
    
    // Limpar dados sensíveis para exibição
    $aluno['justificativa'] = htmlspecialchars($aluno['justificativa'] ?? '');
    $aluno['nome_aluno'] = htmlspecialchars($aluno['nome_aluno']);
    $aluno['email'] = htmlspecialchars($aluno['email']);
    $aluno['telefone'] = htmlspecialchars($aluno['telefone'] ?? '');
    
    echo json_encode(['success' => true, 'aluno' => $aluno]);
} else {
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado']);
}

$stmt->close();
$conn->close();
?>