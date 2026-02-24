<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/conexao.php';
require_once __DIR__ . '/../../includes/autenticacao.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/ZApi.php';

use Menuia\Settings;
use Menuia\Device;
use Menuia\Message;

// Buscar configurações da API WhatsApp no banco de dados
$sql_config = "SELECT menuia_endpoint, menuia_auth_key, menuia_app_key, whatsapp_provider, zapi_instance_id, zapi_token, zapi_client_token FROM configuracoes WHERE id = 1";
$result_config = $conn->query($sql_config);

// Configurações devem vir obrigatoriamente do banco de dados
if ($result_config && $result_config->num_rows > 0) {
    $config = $result_config->fetch_assoc();
    $whatsapp_provider = $config['whatsapp_provider'] ?? 'menuia';
    
    if ($whatsapp_provider === 'zapi') {
        // Verificar credenciais Z-API
        if (empty($config['zapi_instance_id']) || empty($config['zapi_token'])) {
            $status_message = "Erro: Credenciais da Z-API não configuradas. Verifique em Configurações > WhatsApp.";
            header("Location: ../../emprestimos/visualizar.php?id=" . (isset($_GET['emprestimo_id']) ? $_GET['emprestimo_id'] : '0') . "&error=" . urlencode($status_message));
            exit;
        }
        $zapi = new ZApi(
            $config['zapi_instance_id'],
            $config['zapi_token'],
            $config['zapi_client_token'] ?? ''
        );
    } else {
        $endpoint = $config['menuia_endpoint'];
        $authkey = $config['menuia_auth_key'];
        $appkey = $config['menuia_app_key'];
        
        // Verificar se as credenciais estão presentes
        if (empty($endpoint) || empty($authkey) || empty($appkey)) {
            $status_message = "Erro: Credenciais da API Menuia não configuradas. Verifique em Configurações > WhatsApp.";
            header("Location: ../../emprestimos/visualizar.php?id=" . (isset($_GET['emprestimo_id']) ? $_GET['emprestimo_id'] : '0') . "&error=" . urlencode($status_message));
            exit;
        }
        
        // Configurar Menuia
        Settings::setEndpoint($endpoint);
        Settings::setAuthkey($authkey);
        Settings::setAppkey($appkey);
    }
} 
// Se não encontrar configurações, encerra o processamento
else {
    $status_message = "Erro: Configurações da API WhatsApp não encontradas no banco de dados.";
    header("Location: ../../emprestimos/visualizar.php?id=" . (isset($_GET['emprestimo_id']) ? $_GET['emprestimo_id'] : '0') . "&error=" . urlencode($status_message));
    exit;
}

// Inicializar variáveis
$emprestimo_id = 0;
$parcela_id = 0;
$template_id = 0;
$telefone = "";
$status_message = "";
$enviado = false;

// Obter os dados da URL
if (isset($_GET['emprestimo_id'])) {
    $emprestimo_id = (int)$_GET['emprestimo_id'];
}

if (isset($_GET['parcela_id'])) {
    $parcela_id = (int)$_GET['parcela_id'];
}

if (isset($_GET['template_id'])) {
    $template_id = (int)$_GET['template_id'];
}

if (isset($_GET['telefone'])) {
    $telefone = trim($_GET['telefone']);
    // Limpar formatação do telefone (remover parênteses, traços, espaços)
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    // Adicionar código do país (55) se não estiver presente
    if (strlen($telefone) <= 11) {
        $telefone = "55" . $telefone;
    }
}

// Verificar se todos os dados necessários estão presentes
if ($emprestimo_id <= 0 || $parcela_id <= 0 || $template_id <= 0 || empty($telefone)) {
    $status_message = "Erro: Dados incompletos para envio da mensagem.";
    header("Location: ../../emprestimos/visualizar.php?id=" . $emprestimo_id . "&error=" . urlencode($status_message));
    exit;
}

// Buscar dados do empréstimo e cliente
$sql_emprestimo = "SELECT e.id, e.cliente_id, e.valor_emprestado, e.parcelas, e.valor_parcela,
                      (e.valor_parcela * e.parcelas) as valor_total,  
                      c.nome as nome_cliente, c.telefone as telefone_cliente 
                   FROM emprestimos e
                   JOIN clientes c ON e.cliente_id = c.id
                   WHERE e.id = ?";
$stmt_emprestimo = $conn->prepare($sql_emprestimo);
$stmt_emprestimo->bind_param("i", $emprestimo_id);
$stmt_emprestimo->execute();
$resultado_emprestimo = $stmt_emprestimo->get_result();

$dados_emprestimo = null;
if ($resultado_emprestimo && $dados_emprestimo = $resultado_emprestimo->fetch_assoc()) {
    // Se o telefone não foi fornecido, usar o do cliente
    if (empty($telefone) && !empty($dados_emprestimo['telefone_cliente'])) {
        // Remover qualquer caractere não numérico e adicionar código do país (55)
        $telefone = "55" . preg_replace('/[^0-9]/', '', $dados_emprestimo['telefone_cliente']);
    }
} else {
    $status_message = "Erro: Empréstimo não encontrado.";
    header("Location: ../../emprestimos/visualizar.php?id=" . $emprestimo_id . "&error=" . urlencode($status_message));
    exit;
}

// Buscar dados da parcela
$sql_parcela = "SELECT * FROM parcelas WHERE id = ?";
$stmt_parcela = $conn->prepare($sql_parcela);
$stmt_parcela->bind_param("i", $parcela_id);
$stmt_parcela->execute();
$resultado_parcela = $stmt_parcela->get_result();

$dados_parcela = null;
$dias_atraso = 0;
$status_parcela = "";
if ($resultado_parcela && $dados_parcela = $resultado_parcela->fetch_assoc()) {
    // Determinar o status da parcela
    $data_vencimento = strtotime($dados_parcela['vencimento']);
    $data_atual = strtotime(date('Y-m-d'));
    $data_ontem = strtotime('-1 day', $data_atual);
    
    if ($dados_parcela['status'] == 'pago') {
        $status_parcela = 'quitado';
    } elseif ($data_vencimento < $data_ontem) {
        $status_parcela = 'atrasado';
        $dias_atraso = floor(($data_atual - $data_vencimento) / (60 * 60 * 24));
    } else {
        $status_parcela = 'pendente';
    }
} else {
    $status_message = "Erro: Parcela não encontrada.";
    header("Location: ../../emprestimos/visualizar.php?id=" . $emprestimo_id . "&error=" . urlencode($status_message));
    exit;
}

// Buscar o template selecionado
$sql_template = "SELECT * FROM templates_mensagens WHERE id = ? AND ativo = 1";
$stmt_template = $conn->prepare($sql_template);
$stmt_template->bind_param("i", $template_id);
$stmt_template->execute();
$resultado_template = $stmt_template->get_result();

$template = null;
if ($resultado_template && $template = $resultado_template->fetch_assoc()) {
    // Temos o template
} else {
    $status_message = "Erro: Template não encontrado.";
    header("Location: ../../emprestimos/visualizar.php?id=" . $emprestimo_id . "&error=" . urlencode($status_message));
    exit;
}

// Inicializar variáveis para os cálculos
$total_parcelas = 0;
$parcelas_pagas = 0;
$valor_total = 0;
$valor_pago = 0;
$valor_em_aberto = 0;
$lista_parcelas = [];

// Buscar todas as parcelas para calcular valores totais
$sql_todas_parcelas = "SELECT * FROM parcelas WHERE emprestimo_id = ?";
$stmt_todas_parcelas = $conn->prepare($sql_todas_parcelas);
$stmt_todas_parcelas->bind_param("i", $emprestimo_id);
$stmt_todas_parcelas->execute();
$resultado_todas_parcelas = $stmt_todas_parcelas->get_result();

if ($resultado_todas_parcelas) {
    while ($parcela = $resultado_todas_parcelas->fetch_assoc()) {
        $total_parcelas++;
        $valor_total += floatval($parcela['valor'] ?? 0);
        
        if (isset($parcela['status']) && $parcela['status'] == 'pago') {
            $parcelas_pagas++;
            $valor_pago += floatval($parcela['valor'] ?? 0);
        } else {
            $valor_em_aberto += floatval($parcela['valor'] ?? 0);
            $lista_parcelas[] = $parcela['numero'] ?? '?';
        }
    }
}

// Se não conseguimos calcular o valor total pelas parcelas, usar o dado do empréstimo
if ($valor_total == 0 && isset($dados_emprestimo['valor_total'])) {
    $valor_total = floatval($dados_emprestimo['valor_total']);
}

// Preparar a mensagem e substituir as variáveis
$mensagem = $template['mensagem'];

// Preparar os dados para substituição
$dados = [
    'nome_cliente' => $dados_emprestimo['nome_cliente'] ?? 'Cliente',
    'valor_parcela' => 'R$ ' . number_format($dados_parcela['valor'] ?? 0, 2, ',', '.'),
    'data_vencimento' => date('d/m/Y', strtotime($dados_parcela['vencimento'] ?? date('Y-m-d'))),
    'atraso' => isset($dias_atraso) ? $dias_atraso . ' dias' : '0 dias',
    'valor_total' => 'R$ ' . number_format($valor_total, 2, ',', '.'),
    'valor_em_aberto' => 'R$ ' . number_format($valor_em_aberto, 2, ',', '.'),
    'total_parcelas' => $total_parcelas,
    'parcelas_pagas' => $parcelas_pagas,
    'valor_pago' => 'R$ ' . number_format($valor_pago, 2, ',', '.'),
    'numero_parcela' => $dados_parcela['numero'] ?? '0',
    'lista_parcelas_restantes' => implode(', ', $lista_parcelas),
    'link_pagamento' => BASE_URL . 'pagamento/link.php?p=' . $parcela_id,
    'nomedogestor' => $_SESSION['nome'] ?? 'Gestor'
];

// Substituir as variáveis
foreach ($dados as $chave => $valor) {
    $mensagem = str_replace('{' . $chave . '}', $valor, $mensagem);
}

// Enviar a mensagem
try {
    $envio_sucesso = false;
    $erro_msg = '';
    
    if ($whatsapp_provider === 'zapi') {
        // Enviar via Z-API
        try {
            $response = $zapi->sendText($telefone, $mensagem);
            $envio_sucesso = ZApi::isSuccess($response);
            if (!$envio_sucesso) {
                $erro_msg = isset($response->message) ? $response->message : 'Erro desconhecido';
            }
        } catch (Exception $ex) {
            $envio_sucesso = false;
            $erro_msg = $ex->getMessage();
        }
    } else {
        // Enviar via Menuia
        Message::$phone = $telefone;
        Message::$message = $mensagem;
        Message::$type = "text";
        
        $response = Message::send();
        $envio_sucesso = isset($response->status) && $response->status == 200;
        if (!$envio_sucesso) {
            $erro_msg = isset($response->message) ? $response->message : 'Erro desconhecido';
        }
    }
    
    if ($envio_sucesso) {
        $status_message = "Mensagem enviada com sucesso!";
        $enviado = true;
        
        // Registrar o envio no log
        $sql_log = "INSERT INTO mensagens_log (
                        emprestimo_id,
                        parcela_id,
                        template_id,
                        telefone,
                        mensagem,
                        data_envio,
                        usuario_id,
                        status
                    ) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'sucesso')";
        
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param(
            "iiissi",
            $emprestimo_id,
            $parcela_id,
            $template_id,
            $telefone,
            $mensagem,
            $_SESSION['usuario_id']
        );
        $stmt_log->execute();
        
        // Redirecionar para a página do empréstimo com mensagem de sucesso
        header("Location: ../../emprestimos/visualizar.php?id=" . $emprestimo_id . "&success=" . urlencode($status_message));
        exit;
    } else {
        $status_message = "Erro ao enviar mensagem: " . $erro_msg;
        
        // Registrar o erro no log
        $sql_log = "INSERT INTO mensagens_log (
                        emprestimo_id,
                        parcela_id,
                        template_id,
                        telefone,
                        mensagem,
                        data_envio,
                        usuario_id,
                        status,
                        erro
                    ) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'erro', ?)";
        
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param(
            "iiissis",
            $emprestimo_id,
            $parcela_id,
            $template_id,
            $telefone,
            $mensagem,
            $_SESSION['usuario_id'],
            $erro_msg
        );
        $stmt_log->execute();
        
        // Redirecionar para a página do empréstimo com mensagem de erro
        header("Location: ../../emprestimos/visualizar.php?id=" . $emprestimo_id . "&error=" . urlencode($status_message));
        exit;
    }
    
} catch (Exception $e) {
    $status_message = "Erro: " . $e->getMessage();
    
    // Registrar o erro no log
    $sql_log = "INSERT INTO mensagens_log (
                    emprestimo_id,
                    parcela_id,
                    template_id,
                    telefone,
                    mensagem,
                    data_envio,
                    usuario_id,
                    status,
                    erro
                ) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'erro', ?)";
    
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param(
        "iiissis",
        $emprestimo_id,
        $parcela_id,
        $template_id,
        $telefone,
        $mensagem,
        $_SESSION['usuario_id'],
        $e->getMessage()
    );
    $stmt_log->execute();
    
    // Redirecionar para a página do empréstimo com mensagem de erro
    header("Location: ../../emprestimos/visualizar.php?id=" . $emprestimo_id . "&error=" . urlencode($status_message));
    exit;
}
?> 