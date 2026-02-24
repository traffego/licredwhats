<?php
// Desativa a exibição de erros
error_reporting(0);
ini_set('display_errors', 0);

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/autenticacao.php';
require_once __DIR__ . '/../../includes/conexao.php';
require_once __DIR__ . '/../../includes/funcoes_comissoes.php';

// Função para retornar resposta JSON e encerrar
function jsonResponse($status, $message, $httpCode = 200) {
    ob_clean();
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método não permitido', 405);
}

$emprestimo_id = filter_input(INPUT_POST, 'emprestimo_id', FILTER_VALIDATE_INT);
$valor_quitacao = filter_input(INPUT_POST, 'valor_quitacao', FILTER_VALIDATE_FLOAT);
$data_quitacao = filter_input(INPUT_POST, 'data_quitacao', FILTER_SANITIZE_STRING);
$forma_pagamento = filter_input(INPUT_POST, 'forma_pagamento', FILTER_SANITIZE_STRING);

// Validação
$erros = [];
if (!$emprestimo_id) $erros[] = 'ID do empréstimo inválido';
if (!$valor_quitacao || $valor_quitacao <= 0) $erros[] = 'Valor de quitação inválido';
if (!$data_quitacao) $erros[] = 'Data de quitação inválida';
if (!$forma_pagamento) $erros[] = 'Forma de pagamento inválida';

if (!empty($erros)) {
    jsonResponse('error', implode(', ', $erros), 400);
}

$conn->begin_transaction();

try {
    // Busca as parcelas do empréstimo
    $stmt = $conn->prepare("
        SELECT 
            p.id, 
            p.numero, 
            p.valor, 
            p.vencimento, 
            p.status, 
            p.valor_pago, 
            p.data_pagamento, 
            p.forma_pagamento
        FROM 
            parcelas p
        WHERE 
            p.emprestimo_id = ? 
        ORDER BY 
            p.numero
    ");
    $stmt->bind_param("i", $emprestimo_id);
    $stmt->execute();
    $result_parcelas = $stmt->get_result();
    
    if ($result_parcelas->num_rows === 0) {
        jsonResponse('error', 'Nenhuma parcela encontrada para este empréstimo.', 404);
    }
    
    // Atualiza todas as parcelas pendentes ou parciais
    while ($parcela = $result_parcelas->fetch_assoc()) {
        if ($parcela['status'] !== 'pago') {
            $stmt_atualiza = $conn->prepare("
                UPDATE parcelas 
                SET 
                    status = 'pago',
                    valor_pago = valor,
                    data_pagamento = ?,
                    forma_pagamento = ?,
                    observacao = 'Quitação do empréstimo'
                WHERE 
                    id = ?
            ");
            $stmt_atualiza->bind_param("ssi", $data_quitacao, $forma_pagamento, $parcela['id']);
            $stmt_atualiza->execute();
        }
    }

    // Processa o retorno do capital e as comissões usando a nova função
    $resultado_processamento = processarComissoesERetornos($conn, $emprestimo_id);
    
    if (!$resultado_processamento['success']) {
        throw new Exception("Erro ao processar comissões: " . $resultado_processamento['message']);
    }
    
    // Registra a quitação no histórico
    $stmt_historico = $conn->prepare("
        INSERT INTO historico (
            emprestimo_id, 
            tipo, 
            descricao, 
            valor, 
            data, 
            usuario_id
        ) VALUES (?, 'quitacao', 'Quitação do empréstimo', ?, ?, ?)
    ");
    $usuario_id = $_SESSION['usuario_id'] ?? 1;
    $stmt_historico->bind_param("idsi", $emprestimo_id, $valor_quitacao, $data_quitacao, $usuario_id);
    $stmt_historico->execute();
    
    $conn->commit();
    jsonResponse('success', 'Empréstimo quitado com sucesso!');
    
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse('error', 'Erro ao processar a quitação: ' . $e->getMessage());
} 