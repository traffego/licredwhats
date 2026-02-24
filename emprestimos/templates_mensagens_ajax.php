<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/templates_mensagens_db.php';

// Verifica se o usuário está logado
session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado']);
    exit;
}

// Inicializa a classe de banco de dados
$templatesDB = new TemplatesMensagensDB();

// Obtém a ação da requisição
$acao = $_REQUEST['acao'] ?? '';

// Processa a ação
switch ($acao) {
    case 'listar':
        // Lista todos os templates ativos
        $templates = $templatesDB->getTemplatesAtivos();
        echo json_encode(['sucesso' => true, 'templates' => $templates]);
        break;
        
    case 'obter':
        // Obtém um template específico
        $id = $_REQUEST['id'] ?? 0;
        $template = $templatesDB->getTemplatePorId($id);
        
        if ($template) {
            echo json_encode(['sucesso' => true, 'template' => $template]);
        } else {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'Template não encontrado']);
        }
        break;
        
    case 'salvar':
        // Valida os dados
        $dados = [
            'nome' => $_POST['nome'] ?? '',
            'status' => $_POST['status'] ?? '',
            'mensagem' => $_POST['mensagem'] ?? '',
            'incluir_nome' => isset($_POST['incluir_nome']) ? 1 : 0,
            'incluir_valor' => isset($_POST['incluir_valor']) ? 1 : 0,
            'incluir_vencimento' => isset($_POST['incluir_vencimento']) ? 1 : 0,
            'incluir_atraso' => isset($_POST['incluir_atraso']) ? 1 : 0,
            'incluir_valor_total' => isset($_POST['incluir_valor_total']) ? 1 : 0,
            'incluir_valor_em_aberto' => isset($_POST['incluir_valor_em_aberto']) ? 1 : 0,
            'incluir_total_parcelas' => isset($_POST['incluir_total_parcelas']) ? 1 : 0,
            'incluir_parcelas_pagas' => isset($_POST['incluir_parcelas_pagas']) ? 1 : 0,
            'incluir_valor_pago' => isset($_POST['incluir_valor_pago']) ? 1 : 0,
            'incluir_numero_parcela' => isset($_POST['incluir_numero_parcela']) ? 1 : 0,
            'incluir_lista_parcelas' => isset($_POST['incluir_lista_parcelas']) ? 1 : 0,
            'incluir_link_pagamento' => isset($_POST['incluir_link_pagamento']) ? 1 : 0,
            'usuario_id' => $_SESSION['usuario_id']
        ];
        
        // Validações básicas
        if (empty($dados['nome'])) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'O nome do template é obrigatório']);
            exit;
        }
        
        if (empty($dados['status'])) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'O status do template é obrigatório']);
            exit;
        }
        
        if (empty($dados['mensagem'])) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'A mensagem do template é obrigatória']);
            exit;
        }
        
        // Verifica se é uma atualização ou inserção
        $id = $_POST['id'] ?? 0;
        
        if ($id > 0) {
            // Atualiza o template existente
            if ($templatesDB->atualizarTemplate($id, $dados)) {
                echo json_encode(['sucesso' => true, 'mensagem' => 'Template atualizado com sucesso']);
            } else {
                http_response_code(500);
                echo json_encode(['sucesso' => false, 'erro' => 'Erro ao atualizar o template']);
            }
        } else {
            // Insere um novo template
            if ($templatesDB->salvarTemplate($dados)) {
                echo json_encode(['sucesso' => true, 'mensagem' => 'Template salvo com sucesso']);
            } else {
                http_response_code(500);
                echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar o template']);
            }
        }
        break;
        
    case 'excluir':
        // Exclui um template
        $id = $_POST['id'] ?? 0;
        
        if ($id > 0) {
            if ($templatesDB->excluirTemplate($id)) {
                echo json_encode(['sucesso' => true, 'mensagem' => 'Template excluído com sucesso']);
            } else {
                http_response_code(500);
                echo json_encode(['sucesso' => false, 'erro' => 'Erro ao excluir o template']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'ID do template inválido']);
        }
        break;
        
    case 'processar':
        // Processa um template com dados de exemplo
        $id = $_POST['id'] ?? 0;
        $dados = [
            'nome_cliente' => $_POST['nome_cliente'] ?? '',
            'valor_parcela' => $_POST['valor_parcela'] ?? '',
            'data_vencimento' => $_POST['data_vencimento'] ?? '',
            'atraso' => $_POST['atraso'] ?? '',
            'valor_total' => $_POST['valor_total'] ?? '',
            'valor_em_aberto' => $_POST['valor_em_aberto'] ?? '',
            'total_parcelas' => $_POST['total_parcelas'] ?? '',
            'parcelas_pagas' => $_POST['parcelas_pagas'] ?? '',
            'valor_pago' => $_POST['valor_pago'] ?? '',
            'numero_parcela' => $_POST['numero_parcela'] ?? '',
            'lista_parcelas_restantes' => $_POST['lista_parcelas_restantes'] ?? '',
            'nomedogestor' => $_POST['nomedogestor'] ?? ''
        ];
        
        if ($id > 0) {
            // Processa um template existente
            $mensagem = $templatesDB->processarTemplate($id, $dados);
            if ($mensagem !== false) {
                echo json_encode(['sucesso' => true, 'mensagem' => $mensagem]);
            } else {
                http_response_code(404);
                echo json_encode(['sucesso' => false, 'erro' => 'Template não encontrado']);
            }
        } else {
            // Processa o template em edição
            $mensagem = $dados['mensagem'] ?? '';
            
            // Substitui os placeholders
            foreach ($dados as $chave => $valor) {
                $mensagem = str_replace('{' . $chave . '}', $valor, $mensagem);
            }
            
            echo json_encode(['sucesso' => true, 'mensagem' => $mensagem]);
        }
        break;
        
    case 'buscar_por_status':
        // Busca templates por status
        $status = $_REQUEST['status'] ?? '';
        
        if (empty($status)) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Status não informado']);
            exit;
        }
        
        $templates = $templatesDB->getTemplatesPorStatus($status);
        
        if (!empty($templates)) {
            echo json_encode(['sucesso' => true, 'templates' => $templates]);
        } else {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'erro' => 'Nenhum template encontrado para o status: ' . $status]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Ação inválida']);
        break;
} 