<?php
// Define o tipo de conteúdo como JSON
header('Content-Type: application/json; charset=utf-8');

// Desativa a exibição de erros no output
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/conexao.php';

// Função para retornar resposta JSON
function jsonResponse($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verifica os parâmetros recebidos
$emprestimo_id = filter_input(INPUT_GET, 'emprestimo_id', FILTER_VALIDATE_INT);
$parcela_id = filter_input(INPUT_GET, 'parcela_id', FILTER_VALIDATE_INT);

if (!$emprestimo_id) {
    jsonResponse('error', 'ID do empréstimo não fornecido ou inválido');
}

try {
    // Obtém as informações da parcela
    $stmt_parcela = $conn->prepare("
        SELECT 
            p.id, 
            p.numero, 
            p.valor, 
            p.vencimento, 
            p.status, 
            p.valor_pago,
            e.parcelas as total_parcelas,
            e.cliente_id
        FROM parcelas p
        JOIN emprestimos e ON p.emprestimo_id = e.id
        WHERE e.id = ? AND p.id = ?
    ");
    
    if (!$stmt_parcela) {
        jsonResponse('error', 'Erro ao preparar consulta: ' . $conn->error);
    }
    
    $stmt_parcela->bind_param("ii", $emprestimo_id, $parcela_id);
    $stmt_parcela->execute();
    $result_parcela = $stmt_parcela->get_result();
    
    if ($result_parcela->num_rows === 0) {
        jsonResponse('error', 'Parcela não encontrada');
    }
    
    $parcela = $result_parcela->fetch_assoc();
    $cliente_id = $parcela['cliente_id'];
    
    // Obtém as informações do cliente
    $stmt_cliente = $conn->prepare("
        SELECT 
            id, 
            nome, 
            telefone, 
            email 
        FROM clientes 
        WHERE id = ?
    ");
    
    if (!$stmt_cliente) {
        jsonResponse('error', 'Erro ao preparar consulta de cliente: ' . $conn->error);
    }
    
    $stmt_cliente->bind_param("i", $cliente_id);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    
    if ($result_cliente->num_rows === 0) {
        jsonResponse('error', 'Cliente não encontrado');
    }
    
    $cliente = $result_cliente->fetch_assoc();
    
    // Retorna os dados
    jsonResponse('success', 'Dados obtidos com sucesso', [
        'parcela' => $parcela,
        'cliente' => $cliente
    ]);
    
} catch (Exception $e) {
    jsonResponse('error', 'Erro ao processar solicitação: ' . $e->getMessage());
} 