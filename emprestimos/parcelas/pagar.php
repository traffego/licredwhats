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
    ob_clean(); // Limpa qualquer saída anterior
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
$parcela_numero = filter_input(INPUT_POST, 'parcela_numero', FILTER_VALIDATE_INT);
$valor_pago = filter_input(INPUT_POST, 'valor_pago', FILTER_VALIDATE_FLOAT);
$data_pagamento = filter_input(INPUT_POST, 'data_pagamento', FILTER_DEFAULT);
$forma_pagamento = filter_input(INPUT_POST, 'forma_pagamento', FILTER_DEFAULT);
$modo_distribuicao = filter_input(INPUT_POST, 'modo_distribuicao', FILTER_DEFAULT) ?? 'desconto_proximas';

// Validação mais detalhada
$erros = [];
if (!$emprestimo_id) $erros[] = 'ID do empréstimo inválido';
if (!$parcela_numero) $erros[] = 'Número da parcela inválido';
if (!$valor_pago || $valor_pago <= 0) $erros[] = 'Valor do pagamento inválido';
if (!$data_pagamento) $erros[] = 'Data do pagamento inválida';
if (!$forma_pagamento) $erros[] = 'Forma de pagamento inválida';

if (!empty($erros)) {
    jsonResponse('error', implode(', ', $erros), 400);
}

$conn->begin_transaction();

try {
    // Busca o empréstimo e o valor padrão das parcelas
    $stmt = $conn->prepare("SELECT valor_parcela FROM emprestimos WHERE id = ?");
    $stmt->bind_param("i", $emprestimo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $emprestimo = $result->fetch_assoc();

    if (!$emprestimo) {
        jsonResponse('error', 'Empréstimo não encontrado.', 404);
    }

    // Busca a parcela específica
    $stmt = $conn->prepare("SELECT id, valor, valor_pago, status FROM parcelas WHERE emprestimo_id = ? AND numero = ?");
    $stmt->bind_param("ii", $emprestimo_id, $parcela_numero);
    $stmt->execute();
    $result = $stmt->get_result();
    $parcela_atual = $result->fetch_assoc();

    if (!$parcela_atual) {
        jsonResponse('error', 'Parcela não encontrada.', 404);
    }

    $valor_pago_total_recebido = floatval($valor_pago);
    $valor_parcela_original = floatval($emprestimo['valor_parcela']);
    
    // Lê a ação de distribuição do POST
    $acao_diferenca_aplicada = $modo_distribuicao;

    // --- Lógica de Pagamento Principal ---
    // Calcula quanto falta pagar na parcela atual
    $valor_ja_pago = floatval($parcela_atual['valor_pago'] ?? 0);
    $valor_faltante_atual = floatval($parcela_atual['valor']) - $valor_ja_pago;
    
    // Calcula quanto do valor pago vai para a parcela atual
    $valor_aplicado_parcela_atual = min($valor_pago_total_recebido, $valor_faltante_atual);
    
    // Atualiza a parcela atual
    $novo_valor_pago = $valor_ja_pago + $valor_aplicado_parcela_atual;
    $novo_status = ($novo_valor_pago >= floatval($parcela_atual['valor'])) ? 'pago' : 'parcial';
    $diferenca = $valor_pago_total_recebido - $valor_aplicado_parcela_atual;
    
    // Atualiza a parcela atual no banco
    $stmt = $conn->prepare("
        UPDATE parcelas 
        SET 
            valor_pago = ?,
            data_pagamento = ?,
            forma_pagamento = ?,
            status = ?,
            observacao = CONCAT(IFNULL(observacao, ''), ' | diferenca_transacao: ', ?, ', acao_diferenca: ', ?)
        WHERE 
            id = ?
    ");
    $stmt->bind_param(
        "dsssisi", 
        $novo_valor_pago, 
        $data_pagamento, 
        $forma_pagamento, 
        $novo_status,
        $diferenca,
        $acao_diferenca_aplicada,
        $parcela_atual['id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao atualizar parcela atual: " . $stmt->error);
    }

    // Se houver diferença positiva e a ação for desconto_proximas
    if ($diferenca > 0 && $acao_diferenca_aplicada === 'desconto_proximas') {
        $valor_restante = $diferenca;
        
        // Busca as próximas parcelas não pagas
        $stmt = $conn->prepare("
            SELECT id, numero, valor, valor_pago, status 
            FROM parcelas 
            WHERE emprestimo_id = ? AND numero > ? AND status != 'pago'
            ORDER BY numero
        ");
        $stmt->bind_param("ii", $emprestimo_id, $parcela_numero);
        $stmt->execute();
        $proximas_parcelas = $stmt->get_result();
        
        while ($proxima_parcela = $proximas_parcelas->fetch_assoc()) {
            if ($valor_restante <= 0) break;
            
            // Calcula quanto falta pagar nesta parcela
            $valor_ja_pago_proxima = floatval($proxima_parcela['valor_pago'] ?? 0);
            $valor_faltante = floatval($proxima_parcela['valor']) - $valor_ja_pago_proxima;
            
            // Calcula quanto será aplicado nesta parcela
            $valor_a_aplicar = min($valor_restante, $valor_faltante);
            $novo_valor_pago_proxima = $valor_ja_pago_proxima + $valor_a_aplicar;
            $novo_status_proxima = ($novo_valor_pago_proxima >= floatval($proxima_parcela['valor'])) ? 'pago' : 'parcial';
            
            // Atualiza a próxima parcela
            $stmt = $conn->prepare("
                UPDATE parcelas 
                SET 
                    valor_pago = ?,
                    data_pagamento = ?,
                    forma_pagamento = ?,
                    status = ?,
                    observacao = CONCAT(IFNULL(observacao, ''), ' | diferenca_transacao: ', ?, ', acao_diferenca: ', ?)
                WHERE 
                    id = ?
            ");
            $stmt->bind_param(
                "dsssisi", 
                $novo_valor_pago_proxima, 
                $data_pagamento, 
                $forma_pagamento, 
                $novo_status_proxima,
                $valor_a_aplicar,
                $acao_diferenca_aplicada,
                $proxima_parcela['id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar próxima parcela: " . $stmt->error);
            }
            
            // Atualiza o valor restante
            $valor_restante -= $valor_a_aplicar;
        }
    }

    // Se houver diferença positiva e a ação for desconto_ultimas
    if ($diferenca > 0 && $acao_diferenca_aplicada === 'desconto_ultimas') {
        $valor_restante = $diferenca;
        
        // Busca as últimas parcelas não pagas em ordem decrescente
        $stmt = $conn->prepare("
            SELECT id, numero, valor, valor_pago, status 
            FROM parcelas 
            WHERE emprestimo_id = ? AND numero > ? AND status != 'pago'
            ORDER BY numero DESC
        ");
        $stmt->bind_param("ii", $emprestimo_id, $parcela_numero);
        $stmt->execute();
        $ultimas_parcelas = $stmt->get_result();
        
        while ($ultima_parcela = $ultimas_parcelas->fetch_assoc()) {
            if ($valor_restante <= 0) break;
            
            // Calcula quanto falta pagar nesta parcela
            $valor_ja_pago_ultima = floatval($ultima_parcela['valor_pago'] ?? 0);
            $valor_faltante = floatval($ultima_parcela['valor']) - $valor_ja_pago_ultima;
            
            // Calcula quanto será aplicado nesta parcela
            $valor_a_aplicar = min($valor_restante, $valor_faltante);
            $novo_valor_pago_ultima = $valor_ja_pago_ultima + $valor_a_aplicar;
            $novo_status_ultima = ($novo_valor_pago_ultima >= floatval($ultima_parcela['valor'])) ? 'pago' : 'parcial';
            
            // Atualiza a última parcela
            $stmt = $conn->prepare("
                UPDATE parcelas 
                SET 
                    valor_pago = ?,
                    data_pagamento = ?,
                    forma_pagamento = ?,
                    status = ?,
                    observacao = CONCAT(IFNULL(observacao, ''), ' | diferenca_transacao: ', ?, ', acao_diferenca: ', ?)
                WHERE 
                    id = ?
            ");
            $stmt->bind_param(
                "dsssisi", 
                $novo_valor_pago_ultima, 
                $data_pagamento, 
                $forma_pagamento, 
                $novo_status_ultima,
                $valor_a_aplicar,
                $acao_diferenca_aplicada,
                $ultima_parcela['id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar última parcela: " . $stmt->error);
            }
            
            // Atualiza o valor restante
            $valor_restante -= $valor_a_aplicar;
        }
    }

    // Se houver diferença negativa e a ação for proxima_parcela
    if ($diferenca < 0 && $acao_diferenca_aplicada === 'proxima_parcela') {
        $valor_faltante = abs($diferenca);
        
        // Busca a próxima parcela
        $stmt = $conn->prepare("
            SELECT id, numero, valor 
            FROM parcelas 
            WHERE emprestimo_id = ? AND numero = ?
        ");
        $proximo_numero = $parcela_numero + 1;
        $stmt->bind_param("ii", $emprestimo_id, $proximo_numero);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($proxima_parcela = $result->fetch_assoc()) {
            $novo_valor_parcela = floatval($valor_parcela_original) + $valor_faltante;
            
            // Atualiza o valor da próxima parcela
            $stmt = $conn->prepare("
                UPDATE parcelas 
                SET 
                    valor = ?,
                    observacao = CONCAT(IFNULL(observacao, ''), ' | diferenca_transacao: -', ?, ', acao_diferenca: ', ?)
                WHERE 
                    id = ?
            ");
            $stmt->bind_param(
                "ddsi", 
                $novo_valor_parcela, 
                $valor_faltante,
                $acao_diferenca_aplicada,
                $proxima_parcela['id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar valor da próxima parcela: " . $stmt->error);
            }
        }
    }
    
    // Verifica se todas as parcelas estão pagas e processa comissões se necessário
    $status_comissoes = calcularPrevisaoComissoes($conn, $emprestimo_id);
    
    // Se todas as parcelas estão pagas, processa o retorno e comissões
    if ($status_comissoes && 
        $status_comissoes['status']['todas_parcelas_pagas'] && 
        !isset($status_comissoes['status']['retorno_processado'])) {
        
        // Processa o retorno do capital e as comissões
        $resultado_processamento = processarComissoesERetornos($conn, $emprestimo_id);
        
        if (!$resultado_processamento['success']) {
            throw new Exception("Erro ao processar comissões: " . $resultado_processamento['message']);
        }
    }

    $conn->commit();
    jsonResponse('success', 'Pagamento registrado com sucesso!');
    
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse('error', 'Erro ao processar pagamento: ' . $e->getMessage());
}