<?php
$titulo_pagina = "Templates de Mensagens";
$scripts_header = '
<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

<!-- DataTables JavaScript -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
';

require_once '../config.php';
require_once '../includes/conexao.php';
require_once '../includes/autenticacao.php';

// Verifica se é uma requisição AJAX
if (isset($_REQUEST['acao'])) {
    header('Content-Type: application/json');
    $acao = $_REQUEST['acao'] ?? '';
    $response = ['success' => false, 'message' => 'Ação inválida'];

    switch ($acao) {
        case 'listar':
            $sql = "SELECT * FROM templates_mensagens WHERE ativo = 1 ORDER BY status, nome";
            $result = $conn->query($sql);
            
            $templates = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $templates[] = $row;
                }
                $response = ['success' => true, 'data' => $templates];
            } else {
                $response = ['success' => false, 'message' => 'Erro ao buscar templates: ' . $conn->error];
            }
            break;

        case 'obter':
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                $sql = "SELECT * FROM templates_mensagens WHERE id = ? AND ativo = 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $row = $result->fetch_assoc()) {
                    $response = ['success' => true, 'data' => $row];
                } else {
                    $response = ['success' => false, 'message' => 'Template não encontrado'];
                }
            } else {
                $response = ['success' => false, 'message' => 'ID inválido'];
            }
            break;

        case 'salvar':
            $id = (int)($_REQUEST['id'] ?? 0);
            $dados = [
                'nome' => $_REQUEST['nome'] ?? '',
                'status' => $_REQUEST['status'] ?? '',
                'mensagem' => $_REQUEST['mensagem'] ?? '',
                'incluir_nome' => (int)($_REQUEST['incluir_nome'] ?? 0),
                'incluir_valor' => (int)($_REQUEST['incluir_valor'] ?? 0),
                'incluir_vencimento' => (int)($_REQUEST['incluir_vencimento'] ?? 0),
                'incluir_atraso' => (int)($_REQUEST['incluir_atraso'] ?? 0),
                'incluir_valor_total' => (int)($_REQUEST['incluir_valor_total'] ?? 0),
                'incluir_valor_em_aberto' => (int)($_REQUEST['incluir_valor_em_aberto'] ?? 0),
                'incluir_total_parcelas' => (int)($_REQUEST['incluir_total_parcelas'] ?? 0),
                'incluir_parcelas_pagas' => (int)($_REQUEST['incluir_parcelas_pagas'] ?? 0),
                'incluir_valor_pago' => (int)($_REQUEST['incluir_valor_pago'] ?? 0),
                'incluir_numero_parcela' => (int)($_REQUEST['incluir_numero_parcela'] ?? 0),
                'incluir_lista_parcelas' => (int)($_REQUEST['incluir_lista_parcelas'] ?? 0),
                'incluir_link_pagamento' => (int)($_REQUEST['incluir_link_pagamento'] ?? 0),
                'usuario_id' => $_SESSION['usuario_id']
            ];

            // Para debug
            error_log("Status recebido: " . $dados['status']);

            if (empty($dados['nome']) || empty($dados['status']) || empty($dados['mensagem'])) {
                $response = ['success' => false, 'message' => 'Preencha todos os campos obrigatórios'];
                break;
            }

            // Validar status
            $status_validos = ['pendente', 'atrasado', 'pago', 'parcial'];
            
            // Se não for um dos status padrão, aceita como status personalizado/categoria
            if (!in_array($dados['status'], $status_validos)) {
                // Validação para status personalizado: limitar tamanho
                if (strlen($dados['status']) > 30) {
                    $response = ['success' => false, 'message' => 'Status personalizado muito longo (máximo 30 caracteres)'];
                    break;
                }
                
                // Aceita o status personalizado
                error_log("Status personalizado aceito: " . $dados['status']);
            }

            if ($id > 0) {
                // Atualização
                $sql = "UPDATE templates_mensagens SET 
                    nome = ?, status = ?, mensagem = ?,
                    incluir_nome = ?, incluir_valor = ?, incluir_vencimento = ?, incluir_atraso = ?,
                    incluir_valor_total = ?, incluir_valor_em_aberto = ?, incluir_total_parcelas = ?,
                    incluir_parcelas_pagas = ?, incluir_valor_pago = ?, incluir_numero_parcela = ?,
                    incluir_lista_parcelas = ?, incluir_link_pagamento = ?
                    WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssiiiiiiiiiiiii",
                    $dados['nome'], $dados['status'], $dados['mensagem'],
                    $dados['incluir_nome'], $dados['incluir_valor'], $dados['incluir_vencimento'],
                    $dados['incluir_atraso'], $dados['incluir_valor_total'], $dados['incluir_valor_em_aberto'],
                    $dados['incluir_total_parcelas'], $dados['incluir_parcelas_pagas'], $dados['incluir_valor_pago'],
                    $dados['incluir_numero_parcela'], $dados['incluir_lista_parcelas'], $dados['incluir_link_pagamento'],
                    $id
                );
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Template atualizado com sucesso', 'status' => $dados['status']];
                } else {
                    $response = ['success' => false, 'message' => 'Erro ao atualizar template'];
                }
            } else {
                // Inserção
                $sql = "INSERT INTO templates_mensagens (
                    nome, status, mensagem,
                    incluir_nome, incluir_valor, incluir_vencimento, incluir_atraso,
                    incluir_valor_total, incluir_valor_em_aberto, incluir_total_parcelas,
                    incluir_parcelas_pagas, incluir_valor_pago, incluir_numero_parcela,
                    incluir_lista_parcelas, incluir_link_pagamento, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssiiiiiiiiiiiii",
                    $dados['nome'], $dados['status'], $dados['mensagem'],
                    $dados['incluir_nome'], $dados['incluir_valor'], $dados['incluir_vencimento'],
                    $dados['incluir_atraso'], $dados['incluir_valor_total'], $dados['incluir_valor_em_aberto'],
                    $dados['incluir_total_parcelas'], $dados['incluir_parcelas_pagas'], $dados['incluir_valor_pago'],
                    $dados['incluir_numero_parcela'], $dados['incluir_lista_parcelas'], $dados['incluir_link_pagamento'],
                    $dados['usuario_id']
                );
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Template criado com sucesso', 'status' => $dados['status']];
                } else {
                    $response = ['success' => false, 'message' => 'Erro ao criar template'];
                }
            }
            break;

        case 'excluir':
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                $sql = "UPDATE templates_mensagens SET ativo = 0 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Template excluído com sucesso'];
                } else {
                    $response = ['success' => false, 'message' => 'Erro ao excluir template'];
                }
            } else {
                $response = ['success' => false, 'message' => 'ID inválido'];
            }
            break;

        case 'processar':
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                $sql = "SELECT * FROM templates_mensagens WHERE id = ? AND ativo = 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $template = $result->fetch_assoc()) {
                    $mensagem = $template['mensagem'];
                    
                    // Dados de exemplo para preview
                    $dados = [
                        'nome_cliente' => 'João da Silva',
                        'valor_parcela' => 'R$ 1.000,00',
                        'data_vencimento' => '10/05/2024',
                        'atraso' => '5 dias',
                        'valor_total' => 'R$ 12.000,00',
                        'valor_em_aberto' => 'R$ 8.000,00',
                        'total_parcelas' => '12',
                        'parcelas_pagas' => '4',
                        'valor_pago' => 'R$ 4.000,00',
                        'numero_parcela' => '5',
                        'lista_parcelas_restantes' => '5, 6, 7, 8, 9, 10, 11, 12',
                        'link_pagamento' => 'https://exemplo.com/pagar',
                        'nomedogestor' => $_SESSION['nome'] ?? 'Gestor'
                    ];
                    
                    foreach ($dados as $chave => $valor) {
                        $mensagem = str_replace('{' . $chave . '}', $valor, $mensagem);
                    }
                    
                    $response = ['success' => true, 'data' => $mensagem];
                } else {
                    $response = ['success' => false, 'message' => 'Template não encontrado'];
                }
            } else {
                $response = ['success' => false, 'message' => 'ID inválido'];
            }
            break;
    }

    echo json_encode($response);
    exit;
}

// Se não for AJAX, renderiza a página
require_once '../includes/head.php';
?>

<div class="container py-4">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">Templates de Mensagens</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTemplate">
            <i class="fas fa-plus"></i> Novo Template
        </button>
    </div>

    <div class="row g-4" id="containerTemplates">
        <!-- Os templates serão inseridos aqui -->
    </div>
</div>

<style>
    body {
        background: #f0f2f5;
        min-height: 100vh;
    }

    .page-header {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .page-header h1 {
        color: #333;
        margin: 0;
        font-size: 1.8rem;
        font-weight: 600;
    }

    .page-header .btn {
        background: #25d366;
        border: none;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .page-header .btn:hover {
        background: #128c7e;
        transform: translateY(-2px);
    }

    .card-template {
        background: #fff;
        transition: all 0.3s ease;
        border: 0;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        overflow: hidden;
        height: 400px;
        display: flex;
        flex-direction: column;
    }

    .card-template:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .card-template .card-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
        flex-shrink: 0;
        background-color: #f8f9fa;
    }

    .card-template .card-title {
        color: #333;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0;
    }

    .status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 10;
        font-size: 0.8rem;
        padding: 0.5em 1em;
        border-radius: 30px;
    }

    .status-pendente { 
        background-color: #FFC107;
        color: #000;
    }

    .status-atrasado { 
        background-color: #FF4D4F;
        color: #fff;
    }

    .status-pago { 
        background-color: #25D366;
        color: #fff;
    }

    .status-parcial { 
        background-color: #3498db;
        color: #fff;
    }

    /* Estilo padrão para status personalizados */
    .status-badge:not(.status-pendente):not(.status-atrasado):not(.status-pago):not(.status-parcial) {
        background-color: #777777;
        color: #fff;
    }

    .template-tags {
        display: flex;
        flex-wrap: wrap;
        padding: 1rem;
        min-height: 120px;
        overflow-y: auto;
        background-color: #fff;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .template-tag {
        font-size: 0.75rem;
        padding: 0.25em 0.75em;
        border-radius: 20px;
        background-color: #f0f2f5;
        color: #333;
        height: fit-content;
        white-space: nowrap;
        border: 1px solid #e0e0e0;
    }

    .template-tag.active {
        background-color: #25D366;
        color: white;
        border-color: #25D366;
        opacity: 1;
    }

    .card-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    .card-title {
        color: #344767;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0;
    }

    .btn-actions {
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid #e9ecef;
        flex-shrink: 0;
        background-color: #f8f9fa;
    }

    .btn-editar {
        background-color: #34b7f1;
        border-color: #34b7f1;
        color: #fff;
    }

    .btn-editar:hover {
        background-color: #0590d3;
        border-color: #0590d3;
        color: #fff;
    }

    .btn-excluir {
        background-color: #FF4D4F;
        border-color: #FF4D4F;
    }

    .btn-excluir:hover {
        background-color: #FF3337;
        border-color: #FF3337;
    }

    .modal-content {
        background: #fff;
        color: #333;
    }

    .modal-header {
        border-bottom: 1px solid #e0e0e0;
    }

    .modal-footer {
        border-top: 1px solid #e0e0e0;
    }

    .form-control, .form-select {
        background: #fff;
        border: 1px solid #ddd;
        color: #333;
    }

    .form-control:focus, .form-select:focus {
        background: #fff;
        border-color: #25d366;
        color: #333;
    }

    .form-label {
        color: #333;
    }

    .checkbox-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .btn-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        background-color: #f0f2f5;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.3s ease;
        user-select: none;
        position: relative;
        border: 1px solid #ddd;
        margin-bottom: 5px;
        font-size: 0.9rem;
        color: #333;
        font-weight: normal;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        outline: none;
    }

    .btn-tag:hover {
        background-color: #25D366;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(37, 211, 102, 0.4);
    }

    .btn-tag:active {
        background-color: #128C7E;
        transform: translateY(0);
        box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }

    .btn-tag span {
        display: inline-block;
        line-height: 1.2;
        text-align: center;
    }

    .btn-tag.clicked {
        animation: pulse 0.3s ease-in-out;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .card-template .template-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
        flex: 1;
        align-content: flex-start;
        overflow-y: auto;
        background-color: #ffffff;
    }

    .card-template .template-tag {
        font-size: 0.75rem;
        padding: 0.25em 0.75em;
        border-radius: 20px;
        background-color: #f0f2f5;
        color: #333;
        height: fit-content;
        white-space: nowrap;
        border: 1px solid #e0e0e0;
    }

    .card-template .preview-container {
        display: flex;
        flex-direction: column;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
        flex: 1;
        overflow-y: auto;
        background-color: #434343;
        position: relative;
        font-size: 0.9rem;
        min-height: 150px;
        box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);
    }

    .card-template .whatsapp-message-preview {
        background-color: #DCF8C6;
        padding: 8px 12px;
        border-radius: 7.5px;
        position: relative;
        max-width: 95%;
        margin-left: auto;
        margin-bottom: 8px;
        box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
        line-height: 1.4;
        word-wrap: break-word;
    }
    
    .card-template .whatsapp-message-preview::after {
        content: "";
        position: absolute;
        right: -8px;
        top: 0;
        border: 8px solid transparent;
        border-left-color: #DCF8C6;
        border-top: 0;
    }

    .preview-time {
        display: inline-block;
        font-size: 0.7rem;
        color: #777;
        margin-left: 8px;
        float: right;
        margin-top: 4px;
    }

    .card-template .btn-actions {
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid #e9ecef;
        flex-shrink: 0;
        background-color: #f8f9fa;
    }

    .whatsapp-header {
        background-color: #128C7E;
        color: white;
        padding: 10px 15px;
        display: flex;
        align-items: center;
    }

    .whatsapp-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #ddd;
        margin-right: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #128C7E;
        background-color: white;
    }

    .whatsapp-contact-info {
        display: flex;
        flex-direction: column;
    }

    .whatsapp-contact-name {
        font-weight: bold;
        font-size: 1rem;
    }

    .whatsapp-contact-status {
        font-size: 0.75rem;
        opacity: 0.8;
    }

    .whatsapp-header-actions {
        margin-left: auto;
        display: flex;
        gap: 15px;
        color: white;
    }

    .whatsapp-header-actions i {
        font-size: 1.2rem;
    }

    .whatsapp-chat {
        flex: 1;
        padding: 15px;
        display: flex;
        flex-direction: column;
        min-height: 200px;
    }

    .whatsapp-message {
        max-width: 80%;
        background-color: #DCF8C6;
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 5px;
        position: relative;
        align-self: flex-end;
        font-size: 0.9rem;
        line-height: 1.5;
        box-shadow: 0 1px 1px rgba(0,0,0,0.1);
        white-space: pre-line;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .whatsapp-message::after {
        content: '';
        position: absolute;
        top: 0;
        right: -8px;
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 0 0 10px 10px;
        border-color: transparent transparent transparent #DCF8C6;
    }

    .whatsapp-message-time {
        font-size: 0.65rem;
        color: #999;
        float: right;
        margin-top: 2px;
        margin-left: 8px;
    }

    .whatsapp-message-status {
        display: inline-block;
        margin-left: 4px;
        color: #4FC3F7;
    }

    .whatsapp-footer {
        padding: 10px;
        background-color: #f0f0f0;
        display: flex;
        align-items: center;
    }

    .whatsapp-input {
        flex: 1;
        background-color: white;
        border-radius: 20px;
        padding: 8px 15px;
        margin: 0 10px;
        display: flex;
        align-items: center;
    }

    .whatsapp-input i {
        color: #777;
        margin-right: 10px;
    }

    .whatsapp-input-placeholder {
        color: #999;
        font-size: 0.9rem;
    }

    .whatsapp-send {
        width: 40px;
        height: 40px;
        background-color: #128C7E;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .mensagem-container,
    .informacoes-container {
        display: block !important;
    }

    .btn-editar-mensagem {
        display: none;
    }

    .mensagem-container textarea {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 14px;
        line-height: 1.5;
        color: #333;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .mensagem-container textarea:focus {
        border-color: #25D366;
        box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
    }

    .whatsapp-container {
        display: flex;
        flex-direction: column;
        background-color: #e5ddd5;
        background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAE7SURBVEhLY2QAGT/78TczM7OG8fHxZ9++fbN+//7969OnT+//+PHjAYgPAvj4+MKgWu8A2UeB+BxQ/SWgHfVA/hwQRoHkgwD3W1tb9crKyr8JCQmDGFBBOtDCb0CfQggIDwRpaWk/X7x4sQXqBBQAFBgFdPCHsbGx/0CaCFTRPKCcOpCpgCxAiisDE2MXUDhJAai4G7I80OAKoA1VQA+f+//vv8nfv3/LgOoigfgAEOcDLfkINPQb0CCwgf8Y/zsCcTcQbwLi/UC8CKbw///wf4Acwv///42A8ktB4v////sPNOQfSAyuEegJcADkCoWEBLQAKB8L9Fy5goLCeqBzDjAxMSVeIPzfBArV/y7/obh6oBOYCGkEOrQCSG0C0ieB+DkDI6MpyGXYANBVGkCqAYiZ8EkOVgAAdxuJC6HnMpYAAAAASUVORK5CYII=");
        background-repeat: repeat;
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
</style>

<!-- Modal Template -->
<div class="modal fade" id="modalTemplate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template de Mensagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formTemplate" action="templates_mensagens.php" method="POST">
                    <input type="hidden" id="template_id" name="id">
                    <input type="hidden" name="acao" value="salvar">
                    
                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="nome" class="form-label">Nome do Template</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Selecione...</option>
                                <option value="pendente">Pendente</option>
                                <option value="atrasado">Atrasado</option>
                                <option value="pago">Pago</option>
                                <option value="parcial">Parcial</option>
                                <option value="outro">Outro (personalizado)</option>
                            </select>
                        </div>
                        <div class="form-group mt-2" id="statusOutroContainer" style="display: none;">
                            <label for="statusOutro" class="form-label">Status personalizado</label>
                            <input type="text" class="form-control" id="statusOutro" name="statusOutro" placeholder="Digite um status personalizado">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-editar-mensagem">
                        <i class="fas fa-edit"></i> Editar Conteúdo da Mensagem
                    </button>
                    
                    <div class="whatsapp-container mb-3">
                        <div class="whatsapp-header">
                            <div class="whatsapp-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="whatsapp-contact-info">
                                <div class="whatsapp-contact-name">Cliente</div>
                                <div class="whatsapp-contact-status" id="previewStatus">online</div>
                            </div>
                            <div class="whatsapp-header-actions">
                                <i class="fas fa-video"></i>
                                <i class="fas fa-phone"></i>
                                <i class="fas fa-ellipsis-v"></i>
                            </div>
                        </div>
                        <div class="whatsapp-chat">
                            <div class="whatsapp-message" id="previewMensagem">
                                Digite uma mensagem para visualizar o preview...
                                <span class="whatsapp-message-time">12:00
                                    <i class="fas fa-check-double whatsapp-message-status"></i>
                                </span>
                            </div>
                        </div>
                        <div class="whatsapp-footer">
                            <i class="far fa-smile"></i>
                            <div class="whatsapp-input">
                                <i class="fas fa-paperclip"></i>
                                <span class="whatsapp-input-placeholder">Digite uma mensagem</span>
                            </div>
                            <div class="whatsapp-send">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mensagem-container">
                        <div class="mb-3">
                            <label for="mensagem" class="form-label">Mensagem</label>
                            <textarea class="form-control" id="mensagem" name="mensagem" rows="5" required></textarea>
                        </div>
                    </div>
                    
                    <div class="informacoes-container">
                        <div class="mb-3">
                            <label class="form-label">Informações a Incluir</label>
                            <div class="checkbox-tags">
                                <button type="button" class="btn-tag" data-tag="nome_cliente">
                                    <span>Nome do Cliente</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="valor_parcela">
                                    <span>Valor da Parcela</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="data_vencimento">
                                    <span>Data de Vencimento</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="atraso">
                                    <span>Dias de Atraso</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="valor_total">
                                    <span>Valor Total</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="valor_em_aberto">
                                    <span>Valor em Aberto</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="total_parcelas">
                                    <span>Total de Parcelas</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="parcelas_pagas">
                                    <span>Parcelas Pagas</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="valor_pago">
                                    <span>Valor Pago</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="numero_parcela">
                                    <span>Número da Parcela</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="lista_parcelas_restantes">
                                    <span>Lista de Parcelas</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="link_pagamento">
                                    <span>Link de Pagamento</span>
                                </button>
                                <button type="button" class="btn-tag" data-tag="nomedogestor">
                                    <span>Nome do Gestor</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Verificar se SweetAlert2 está disponível, caso contrário, carregá-lo
    if (typeof Swal === 'undefined') {
        // Carregar SweetAlert2 se não estiver disponível
        var sweetAlertScript = document.createElement('script');
        sweetAlertScript.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
        document.head.appendChild(sweetAlertScript);
        
        // Esperar até que o script seja carregado
        sweetAlertScript.onload = function() {
            console.log('SweetAlert2 carregado com sucesso');
            inicializarFuncionalidades();
        };
    } else {
        // Se já estiver disponível, inicializar normalmente
        inicializarFuncionalidades();
    }
    
    // Função principal que contém todas as funcionalidades
    function inicializarFuncionalidades() {
    // Tratamento das mensagens de retorno
    <?php if (isset($_GET['sucesso'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: 'Template salvo com sucesso!',
            showConfirmButton: false,
            timer: 1500
        }).then(() => {
            // Remove os parâmetros da URL
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        let mensagem = '';
        switch ('<?php echo $_GET['erro']; ?>') {
            case 'campos_obrigatorios':
                mensagem = 'Por favor, preencha todos os campos obrigatórios.';
                break;
            case 'status_invalido':
                mensagem = 'O status selecionado é inválido.';
                break;
            case 'erro_sql':
                mensagem = 'Erro ao salvar o template. Tente novamente.';
                break;
            case 'template_nao_encontrado':
                mensagem = 'Template não encontrado ou você não tem permissão para editá-lo.';
                break;
            case 'metodo_invalido':
                mensagem = 'Método de requisição inválido.';
                break;
            default:
                mensagem = 'Ocorreu um erro ao processar sua solicitação.';
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: mensagem
        }).then(() => {
            // Remove os parâmetros da URL
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    <?php endif; ?>

    // Função para obter as tags usadas em uma mensagem
    function obterTagsUsadas(mensagem) {
        const tags = {
            'nome_cliente': 'Nome do Cliente',
            'valor_parcela': 'Valor da Parcela',
            'data_vencimento': 'Data de Vencimento',
            'atraso': 'Dias de Atraso',
            'valor_total': 'Valor Total',
            'valor_em_aberto': 'Valor em Aberto',
            'total_parcelas': 'Total de Parcelas',
            'parcelas_pagas': 'Parcelas Pagas',
            'valor_pago': 'Valor Pago',
            'numero_parcela': 'Número da Parcela',
            'lista_parcelas_restantes': 'Lista de Parcelas',
            'link_pagamento': 'Link de Pagamento',
            'nomedogestor': 'Nome do Gestor'
        };
        
        const tagsUsadas = [];
        for (const [tag, label] of Object.entries(tags)) {
            if (mensagem.includes('{' + tag + '}')) {
                tagsUsadas.push(label);
            }
        }
        return tagsUsadas;
    }

    // Função para formatar a hora atual
    function getHoraAtual() {
        const agora = new Date();
        return agora.getHours() + ':' + (agora.getMinutes() < 10 ? '0' : '') + agora.getMinutes();
    }

    // Função para carregar os templates
    function carregarTemplates() {
        $.post('templates_mensagens.php', {
            acao: 'listar'
        }, function(response) {
            if (response.success) {
                var html = '';
                response.data.forEach(function(template) {
                        // Verifica se é um status padrão ou personalizado
                        const statusPadrao = ['pendente', 'atrasado', 'pago', 'parcial'];
                        let statusClass = '';
                        
                        if (statusPadrao.includes(template.status)) {
                            // Se for status padrão, usa a classe diretamente
                            statusClass = 'status-' + template.status;
                        } else {
                            // Se for personalizado, formata o nome para camelCase sem espaços
                            const statusFormatado = template.status
                                .split(' ')
                                .map((word, index) => {
                                    if (index === 0) {
                                        return word.toLowerCase();
                                    }
                                    return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
                                })
                                .join('');
                            
                            // Remove caracteres especiais
                            const statusLimpo = statusFormatado.replace(/[^a-zA-Z0-9]/g, '');
                            
                            // Adiciona o prefixo status-
                            statusClass = 'status-' + statusLimpo.charAt(0).toUpperCase() + statusLimpo.slice(1);
                            
                            // Log para debugging
                            console.log('Status personalizado:', template.status, '-> Classe CSS:', statusClass);
                        }
                        
                    var statusText = {
                        'pendente': 'Pendente',
                        'atrasado': 'Atrasado',
                            'pago': 'Pago',
                            'parcial': 'Parcial'
                    }[template.status] || template.status;

                    // Obter tags usadas na mensagem
                    var tagsUsadas = obterTagsUsadas(template.mensagem);
                    
                    // Lista de todas as tags possíveis
                    var todasTags = [
                        { id: 'incluir_nome', label: 'Nome do Cliente' },
                        { id: 'incluir_valor', label: 'Valor da Parcela' },
                        { id: 'incluir_vencimento', label: 'Data de Vencimento' },
                        { id: 'incluir_atraso', label: 'Dias de Atraso' },
                        { id: 'incluir_valor_total', label: 'Valor Total' },
                        { id: 'incluir_valor_em_aberto', label: 'Valor em Aberto' },
                        { id: 'incluir_total_parcelas', label: 'Total de Parcelas' },
                        { id: 'incluir_parcelas_pagas', label: 'Parcelas Pagas' },
                        { id: 'incluir_valor_pago', label: 'Valor Pago' },
                        { id: 'incluir_numero_parcela', label: 'Número da Parcela' },
                        { id: 'incluir_lista_parcelas', label: 'Lista de Parcelas' },
                        { id: 'incluir_link_pagamento', label: 'Link de Pagamento' }
                    ];

                    // Gerar HTML para todas as tags
                    var tagsHtml = todasTags.map(tag => {
                        const isAtiva = tagsUsadas.includes(tag.label);
                        return `<span class="template-tag ${isAtiva ? 'active' : ''}">${tag.label}</span>`;
                    }).join('');

                    html += `
                        <div class="col-md-4">
                            <div class="card card-template">
                                <div class="card-header">
                                    <span class="badge ${statusClass} status-badge">${statusText}</span>
                                    <h5 class="card-title">${template.nome}</h5>
                                </div>
                                
                                    <div class="preview-container">
                                        <div class="whatsapp-message-preview">
                                            ${gerarPreviewMensagem(template.mensagem)}
                                        </div>
                                </div>
                                
                                <div class="btn-actions">
                                    <button class="btn btn-sm btn-editar me-2" data-id="${template.id}">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-excluir" data-id="${template.id}">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                $('#containerTemplates').html(html);
            } else {
                alert(response.message || 'Erro ao carregar templates');
            }
        });
    }

        // Função para gerar o preview da mensagem no card
        function gerarPreviewMensagem(mensagem) {
            // Dados de exemplo para preview
            var dados = {
                'nome_cliente': 'João da Silva',
                'valor_parcela': 'R$ 1.000,00',
                'data_vencimento': '10/05/2024',
                'atraso': '5 dias',
                'valor_total': 'R$ 12.000,00',
                'valor_em_aberto': 'R$ 8.000,00',
                'total_parcelas': '12',
                'parcelas_pagas': '4',
                'valor_pago': 'R$ 4.000,00',
                'numero_parcela': '5',
                'lista_parcelas_restantes': '5, 6, 7, 8, 9, 10, 11, 12',
                'link_pagamento': 'https://exemplo.com/pagar',
                'nomedogestor': 'Gestor'
            };
            
            // Cria uma cópia da mensagem para substituição
            var mensagemPreview = mensagem;
            
            // Substituir todas as tags por seus valores correspondentes
            for (var tag in dados) {
                var regex = new RegExp('{' + tag + '}', 'g');
                mensagemPreview = mensagemPreview.replace(regex, dados[tag]);
            }
            
            // Limitar o preview a 150 caracteres com reticências
            if (mensagemPreview.length > 150) {
                mensagemPreview = mensagemPreview.substring(0, 150) + '...';
            }
            
            // Obter a hora atual formatada
            const agora = new Date();
            const hora = agora.getHours().toString().padStart(2, '0') + ':' + agora.getMinutes().toString().padStart(2, '0');
            
            // Formatar quebras de linha para HTML e adicionar a hora e o check duplo
            const mensagemFormatada = mensagemPreview.replace(/\n/g, '<br>');
            
            return `
                ${mensagemFormatada}
                <span class="preview-time">${hora}
                    <i class="fas fa-check-double" style="color: #4FC3F7; margin-left: 2px; font-size: 0.8em;"></i>
                </span>
            `;
    }

    // Carregar templates ao iniciar
    carregarTemplates();
    
    // Editar template
    $('#containerTemplates').on('click', '.btn-editar', function() {
        var id = $(this).data('id');
        $.post('templates_mensagens.php', {
            acao: 'obter',
            id: id
        }, function(response) {
            if (response.success) {
                var template = response.data;
                    
                    // Limpar formulário antes
                    $('#formTemplate')[0].reset();
                    
                    // Definir os valores básicos
                $('#template_id').val(template.id);
                $('#nome').val(template.nome);
                $('#mensagem').val(template.mensagem);
                
                    console.log('Status do template:', template.status);
                    
                    // Verificar se o status é um dos padrões ou personalizado
                    const statusPadrao = ['pendente', 'atrasado', 'pago', 'parcial'];
                    if (statusPadrao.includes(template.status)) {
                        $('#status').val(template.status);
                        $('#statusOutroContainer').hide();
                        $('#statusOutro').val('').prop('required', false);
                    } else {
                        // É um status personalizado
                        $('#status').val('outro');
                        $('#statusOutro').val(template.status);
                        $('#statusOutroContainer').show();
                        $('#statusOutro').prop('required', true);
                    }
                    
                    // Criar campos hidden para cada variável
                    const tagsPresentes = {
                        'nome_cliente': 'incluir_nome',
                        'valor_parcela': 'incluir_valor',
                        'data_vencimento': 'incluir_vencimento',
                        'atraso': 'incluir_atraso',
                        'valor_total': 'incluir_valor_total',
                        'valor_em_aberto': 'incluir_valor_em_aberto',
                        'total_parcelas': 'incluir_total_parcelas',
                        'parcelas_pagas': 'incluir_parcelas_pagas',
                        'valor_pago': 'incluir_valor_pago',
                        'numero_parcela': 'incluir_numero_parcela',
                        'lista_parcelas_restantes': 'incluir_lista_parcelas',
                        'link_pagamento': 'incluir_link_pagamento'
                    };
                    
                    // Adicionar campos hidden se não existirem
                    for (const [tag, fieldName] of Object.entries(tagsPresentes)) {
                        // Verificar se já existe um campo com esse nome
                        if ($(`input[name="${fieldName}"]`).length === 0) {
                            // Se não existir, criar um campo hidden
                            $('<input>').attr({
                                type: 'hidden',
                                name: fieldName,
                                id: fieldName,
                                value: template[fieldName] || '0'
                            }).appendTo('#formTemplate');
                        } else {
                            // Se já existir, atualizar o valor
                            $(`input[name="${fieldName}"]`).val(template[fieldName] || '0');
                        }
                    }
                
                // Mostrar containers e atualizar botão
                $('.mensagem-container, .informacoes-container').addClass('show');
                $('.btn-editar-mensagem').html('<i class="fas fa-edit"></i> Ocultar Conteúdo');
                
                atualizarPreview();
                $('#modalTemplate').modal('show');
            }
        });
    });
    
    // Excluir template
    $('#containerTemplates').on('click', '.btn-excluir', function() {
        var id = $(this).data('id');
        if (confirm('Tem certeza que deseja excluir este template?')) {
            $.post('templates_mensagens.php', {
                acao: 'excluir',
                id: id
            }, function(response) {
                if (response.success) {
                    carregarTemplates();
                }
                alert(response.message);
            });
        }
    });
    
    // Atualizar checkboxes antes do envio do formulário
        $('#formTemplate').on('submit', function(e) {
            // Adicionar log para depuração
            console.log('Enviando formulário - Status selecionado:', $('#status').val());
            console.log('Status personalizado:', $('#statusOutro').val());
            
            // Processar status personalizado
            if ($('#status').val() === 'outro') {
                const statusOutro = $('#statusOutro').val().trim();
                console.log('Status outro detectado, valor personalizado:', statusOutro);
                
                if (!statusOutro) {
                    alert('Por favor, digite um status personalizado');
                    e.preventDefault();
                    return false;
                }
                
                // Criar um campo hidden com o status personalizado
                $('<input>').attr({
                    type: 'hidden',
                    name: 'status',
                    value: statusOutro
                }).appendTo('#formTemplate');
                
                // Certificar-se de que o campo select original não interfira
                $('#status').prop('disabled', true);
                
                console.log('Campo hidden status criado com valor:', statusOutro);
            }
            
            // Obter a mensagem atual
            const mensagem = $('#mensagem').val();
            
            // Verificar quais tags estão presentes na mensagem e atualizar os campos hidden
            const tagsPresentes = {
                'nome_cliente': 'incluir_nome',
                'valor_parcela': 'incluir_valor',
                'data_vencimento': 'incluir_vencimento',
                'atraso': 'incluir_atraso',
                'valor_total': 'incluir_valor_total',
                'valor_em_aberto': 'incluir_valor_em_aberto',
                'total_parcelas': 'incluir_total_parcelas',
                'parcelas_pagas': 'incluir_parcelas_pagas',
                'valor_pago': 'incluir_valor_pago',
                'numero_parcela': 'incluir_numero_parcela',
                'lista_parcelas_restantes': 'incluir_lista_parcelas',
                'link_pagamento': 'incluir_link_pagamento'
            };
            
            // Adicionar campos hidden se não existirem
            for (const [tag, fieldName] of Object.entries(tagsPresentes)) {
                // Verificar se já existe um campo com esse nome
                if ($(`input[name="${fieldName}"]`).length === 0) {
                    // Se não existir, criar um campo hidden
                    $('<input>').attr({
                        type: 'hidden',
                        name: fieldName,
                        id: fieldName,
                        value: '0'
                    }).appendTo('#formTemplate');
                }
                
                // Definir o valor como 1 se a tag estiver presente na mensagem, 0 caso contrário
                const isPresent = mensagem.includes('{' + tag + '}');
                $(`input[name="${fieldName}"]`).val(isPresent ? '1' : '0');
            }
            
            // Usar AJAX para enviar o formulário e evitar recarregar a página
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Determinar se é um status personalizado ou padrão
                        const statusPadrao = ['pendente', 'atrasado', 'pago', 'parcial'];
                        const isStatusPersonalizado = !statusPadrao.includes(response.status);
                        
                        let messageTitle = 'Template salvo com sucesso!';
                        let messageHtml = response.message;
                        
                        // Se for um status personalizado, adicionar informação na mensagem
                        if (isStatusPersonalizado) {
                            messageHtml += `<br><br>Status personalizado <strong>${response.status}</strong> aplicado com sucesso!`;
                        }
                        
                        // Mostrar mensagem de sucesso
                        Swal.fire({
                            icon: 'success',
                            title: messageTitle,
                            html: messageHtml,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#25D366'
                        }).then(() => {
                            // Fechar o modal e recarregar os templates
                            $('#modalTemplate').modal('hide');
                            carregarTemplates();
                        });
            } else {
                        // Mostrar mensagem de erro
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de Conexão',
                        text: 'Não foi possível completar a operação. Verifique sua conexão e tente novamente.'
                    });
                }
        });
    });
    
        // Mostrar/ocultar campo de status personalizado
        $('#status').on('change', function() {
            if ($(this).val() === 'outro') {
                $('#statusOutroContainer').show();
                $('#statusOutro').prop('required', true);
            } else {
                $('#statusOutroContainer').hide();
                $('#statusOutro').prop('required', false);
            }
            atualizarPreview();
        });
        
        // Limpar formulário e resetar estado ao abrir modal
        $('#modalTemplate').on('show.bs.modal', function() {
            // Resetar texto do botão
            $('.btn-editar-mensagem').html('<i class="fas fa-edit"></i> Editar Conteúdo da Mensagem');
            
            if (!$('#template_id').val()) {
                $('#formTemplate')[0].reset();
                
                // Definir modelo de mensagem básica
                var modeloMensagem = `Olá {nome_cliente},

Sua parcela de {valor_parcela} que vencia em {data_vencimento} está atrasada há {atraso}.

Valor total do empréstimo: {valor_total}
Valor em aberto: {valor_em_aberto}
Total de parcelas: {total_parcelas}
Parcelas pagas: {parcelas_pagas}
Valor já pago: {valor_pago}

Para regularizar sua situação, acesse: {link_pagamento}

Atenciosamente,
{nomedogestor}`;

                $('#mensagem').val(modeloMensagem);
                $('#previewMensagem').empty();
                
                // Verificar as tags usadas na mensagem padrão
                atualizarPreview();
            }
        });

        // Inicialização de eventos - garante que os botões de tag funcionem corretamente
        function inicializarEventosBotoes() {
            // Remover eventos existentes para evitar duplicação
            $('.btn-tag').off('click');
            
            // Adicionar evento de clique nos botões de tag
            $('.btn-tag').on('click', function() {
                const tag = $(this).data('tag');
                
                // Efeito de animação ao clicar
                $(this).addClass('clicked');
                setTimeout(() => {
                    $(this).removeClass('clicked');
                }, 300);
                
                // Inserir a tag na posição do cursor
                inserirTagNaPosicaoDoCursor(tag);
                
                // Atualizar o preview
        atualizarPreview();
    });
    
            // Armazenar a posição do cursor ao clicar no textarea
            $('#mensagem').on('click keyup focus', function() {
                $(this).data('cursorPosition', this.selectionStart);
            });

            // Garantir que o textarea tenha foco e posição inicial do cursor
            $('#mensagem').on('focus', function() {
                if ($(this).data('cursorPosition') === undefined) {
                    $(this).data('cursorPosition', 0);
                }
            });
        }

        // Função para inserir a tag na posição do cursor
        function inserirTagNaPosicaoDoCursor(tag) {
            const tagPattern = '{' + tag + '}';
            const mensagemTextarea = document.getElementById('mensagem');
            const mensagem = mensagemTextarea.value;
            
            // Obter a posição atual do cursor
            const cursorPosition = $('#mensagem').data('cursorPosition') || 0;
            
            // Inserir a tag na posição do cursor
            const novoTexto = mensagem.substring(0, cursorPosition) + tagPattern + mensagem.substring(cursorPosition);
            mensagemTextarea.value = novoTexto;
            
            // Definir a nova posição do cursor (após a tag inserida)
            const novaPosicao = cursorPosition + tagPattern.length;
            
            // Aplicar a nova posição do cursor
            setTimeout(function() {
                mensagemTextarea.focus();
                mensagemTextarea.setSelectionRange(novaPosicao, novaPosicao);
                $('#mensagem').data('cursorPosition', novaPosicao);
            }, 10);
        }
        
        // Inicializar eventos ao carregar a página e depois de operações AJAX
        inicializarEventosBotoes();
        $(document).ajaxComplete(function() {
            inicializarEventosBotoes();
    });

    // Função para atualizar o preview no modal
    function atualizarPreview() {
        var mensagem = $('#mensagem').val();
        var status = $('#status').val();
        
        // Atualizar status no preview
        var statusText = {
            'pendente': 'online',
            'atrasado': 'digitando...',
                'pago': 'visto por último hoje',
                'parcial': 'online agora'
        }[status] || 'online';
        $('#previewStatus').text(statusText);
        
        // Dados de exemplo para preview
        var dados = {
            'nome_cliente': 'João da Silva',
            'valor_parcela': 'R$ 1.000,00',
            'data_vencimento': '10/05/2024',
            'atraso': '5 dias',
            'valor_total': 'R$ 12.000,00',
            'valor_em_aberto': 'R$ 8.000,00',
            'total_parcelas': '12',
            'parcelas_pagas': '4',
            'valor_pago': 'R$ 4.000,00',
            'numero_parcela': '5',
            'lista_parcelas_restantes': '5, 6, 7, 8, 9, 10, 11, 12',
            'link_pagamento': 'https://exemplo.com/pagar',
            'nomedogestor': 'Gestor'
        };
        
        // Criar uma cópia da mensagem original para preview
        var mensagemPreview = mensagem;
        
            // Substituir todas as tags por seus valores correspondentes
            for (var tag in dados) {
                var regex = new RegExp('{' + tag + '}', 'g');
                mensagemPreview = mensagemPreview.replace(regex, dados[tag]);
            }
        
        // Atualizar mensagem no preview
        var mensagemFormatada = mensagemPreview.replace(/\n/g, '<br>');
        var horaAtual = getHoraAtual();
        
        $('#previewMensagem').html(`
            ${mensagemFormatada}
                <span class="preview-time">${horaAtual}
                    <i class="fas fa-check-double" style="color: #4FC3F7; margin-left: 2px; font-size: 0.8em;"></i>
            </span>
        `);
    }

    // Atualizar preview quando a mensagem mudar
    $('#mensagem').on('input', function() {
        atualizarPreview();
    });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>