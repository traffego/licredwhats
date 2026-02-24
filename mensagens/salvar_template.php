<?php
require_once '../config.php';
require_once '../includes/conexao.php';
require_once '../includes/autenticacao.php';

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: templates_mensagens.php?erro=metodo_invalido');
    exit;
}

// Obtém e valida os dados do formulário
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nome = trim($_POST['nome'] ?? '');
$status = trim($_POST['status'] ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');

// Validação dos campos obrigatórios
if (empty($nome) || empty($status) || empty($mensagem)) {
    header('Location: templates_mensagens.php?erro=campos_obrigatorios');
    exit;
}

// Validação do status
$status_validos = ['pendente', 'atrasado', 'quitado'];
if (!in_array($status, $status_validos)) {
    header('Location: templates_mensagens.php?erro=status_invalido');
    exit;
}

// Prepara os dados dos checkboxes
$incluir_nome = isset($_POST['incluir_nome']) ? 1 : 0;
$incluir_valor = isset($_POST['incluir_valor']) ? 1 : 0;
$incluir_vencimento = isset($_POST['incluir_vencimento']) ? 1 : 0;
$incluir_atraso = isset($_POST['incluir_atraso']) ? 1 : 0;
$incluir_valor_total = isset($_POST['incluir_valor_total']) ? 1 : 0;
$incluir_valor_em_aberto = isset($_POST['incluir_valor_em_aberto']) ? 1 : 0;
$incluir_total_parcelas = isset($_POST['incluir_total_parcelas']) ? 1 : 0;
$incluir_parcelas_pagas = isset($_POST['incluir_parcelas_pagas']) ? 1 : 0;
$incluir_valor_pago = isset($_POST['incluir_valor_pago']) ? 1 : 0;
$incluir_numero_parcela = isset($_POST['incluir_numero_parcela']) ? 1 : 0;
$incluir_lista_parcelas = isset($_POST['incluir_lista_parcelas']) ? 1 : 0;
$incluir_link_pagamento = isset($_POST['incluir_link_pagamento']) ? 1 : 0;

try {
    // Inicia a transação
    $conn->begin_transaction();

    if ($id > 0) {
        // Atualização
        $sql = "UPDATE templates_mensagens SET 
                nome = ?, 
                status = ?, 
                mensagem = ?,
                incluir_nome = ?,
                incluir_valor = ?,
                incluir_vencimento = ?,
                incluir_atraso = ?,
                incluir_valor_total = ?,
                incluir_valor_em_aberto = ?,
                incluir_total_parcelas = ?,
                incluir_parcelas_pagas = ?,
                incluir_valor_pago = ?,
                incluir_numero_parcela = ?,
                incluir_lista_parcelas = ?,
                incluir_link_pagamento = ?
                WHERE id = ? AND usuario_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssiiiiiiiiiiiiii",
            $nome,
            $status,
            $mensagem,
            $incluir_nome,
            $incluir_valor,
            $incluir_vencimento,
            $incluir_atraso,
            $incluir_valor_total,
            $incluir_valor_em_aberto,
            $incluir_total_parcelas,
            $incluir_parcelas_pagas,
            $incluir_valor_pago,
            $incluir_numero_parcela,
            $incluir_lista_parcelas,
            $incluir_link_pagamento,
            $id,
            $_SESSION['usuario_id']
        );
    } else {
        // Inserção
        $sql = "INSERT INTO templates_mensagens (
                nome,
                status,
                mensagem,
                incluir_nome,
                incluir_valor,
                incluir_vencimento,
                incluir_atraso,
                incluir_valor_total,
                incluir_valor_em_aberto,
                incluir_total_parcelas,
                incluir_parcelas_pagas,
                incluir_valor_pago,
                incluir_numero_parcela,
                incluir_lista_parcelas,
                incluir_link_pagamento,
                usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssiiiiiiiiiiiii",
            $nome,
            $status,
            $mensagem,
            $incluir_nome,
            $incluir_valor,
            $incluir_vencimento,
            $incluir_atraso,
            $incluir_valor_total,
            $incluir_valor_em_aberto,
            $incluir_total_parcelas,
            $incluir_parcelas_pagas,
            $incluir_valor_pago,
            $incluir_numero_parcela,
            $incluir_lista_parcelas,
            $incluir_link_pagamento,
            $_SESSION['usuario_id']
        );
    }

    // Executa a query
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar a query: " . $stmt->error);
    }

    // Commit da transação
    $conn->commit();
    
    // Redireciona com mensagem de sucesso
    header('Location: templates_mensagens.php?sucesso=1');
    exit;

} catch (Exception $e) {
    // Rollback em caso de erro
    $conn->rollback();
    
    error_log("Erro ao salvar template: " . $e->getMessage());
    header('Location: templates_mensagens.php?erro=erro_sql');
    exit;
}
