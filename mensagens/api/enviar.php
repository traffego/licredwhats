<?php
require_once '../../config.php';
require_once '../../includes/conexao.php';
require_once 'vendor/autoload.php';
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
} 
// Se não encontrar configurações, encerra o processamento
else {
    die("Erro: Configurações da API WhatsApp não encontradas no banco de dados.");
}

// Inicializar o provedor selecionado
$zapi = null;
if ($whatsapp_provider === 'zapi') {
    $zapi = new ZApi(
        $config['zapi_instance_id'],
        $config['zapi_token'],
        $config['zapi_client_token'] ?? ''
    );
} else {
    // Configurar Menuia
    $endpoint = $config['menuia_endpoint'];
    $authkey = $config['menuia_auth_key'];
    $appkey = $config['menuia_app_key'];
    Settings::setEndpoint($endpoint);
    Settings::setAuthkey($authkey);
    Settings::setAppkey($appkey);
}

// Inicializar variáveis
$resultado = [];
$resposta = [];
$resposta['sucesso'] = false;

// Verificar se há parâmetros necessários
$coletiva = isset($_GET['coletiva']) ? strtolower($_GET['coletiva']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$template_id = isset($_GET['template']) ? (int)$_GET['template'] : 0;
$cliente_id = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$cliente_especifico = isset($_GET['cliente_especifico']) ? (int)$_GET['cliente_especifico'] : 0;
$parcela_id = isset($_GET['parcela_id']) ? $_GET['parcela_id'] : '';

// Validar parâmetros
if (empty($coletiva) || empty($status) || $template_id <= 0) {
    $resposta['mensagem'] = "Parâmetros inválidos. Necessário: coletiva, status e template";
    echo json_encode($resposta);
    exit;
}

// Converter lista de status em array se fornecido como string
$lista_status = explode(',', $status);
$lista_status = array_map('trim', $lista_status);
$lista_status = array_filter($lista_status);

if (empty($lista_status)) {
    $resposta['mensagem'] = "Status inválido ou vazio";
    echo json_encode($resposta);
    exit;
}

// Verificar se há um status especial para vencimentos de hoje
$vencimento_hoje = false;
$lista_status_final = [];

foreach ($lista_status as $s) {
    if ($s === 'vence_hoje') {
        $vencimento_hoje = true;
        // Vamos usar 'pendente' como status real na consulta
        $lista_status_final[] = 'pendente';
    } else {
        $lista_status_final[] = $s;
    }
}

// Buscar template da mensagem
$sql_template = "SELECT * FROM templates_mensagens WHERE id = ? AND ativo = 1";
$stmt_template = $conn->prepare($sql_template);
$stmt_template->bind_param("i", $template_id);
$stmt_template->execute();
$resultado_template = $stmt_template->get_result();

if (!$resultado_template || !($template = $resultado_template->fetch_assoc())) {
    $resposta['mensagem'] = "Template não encontrado ou inativo";
    echo json_encode($resposta);
    exit;
}

// Construir consulta SQL para buscar parcelas pelo status
$placeholders = implode(',', array_fill(0, count($lista_status_final), '?'));

// Consulta SQL base
$sql_parcelas = "
    SELECT p.id as parcela_id, p.emprestimo_id, p.numero as numero_parcela, 
           p.valor, p.vencimento, p.status as status_parcela,
           e.valor_emprestado, e.parcelas as total_parcelas, e.valor_parcela,
           c.id as cliente_id, c.nome as nome_cliente, c.telefone as telefone_cliente
    FROM parcelas p
    JOIN emprestimos e ON p.emprestimo_id = e.id
    JOIN clientes c ON e.cliente_id = c.id
    WHERE p.status IN ($placeholders)";

// Adicionar condição de vencimento para hoje se necessário
if ($vencimento_hoje) {
    $hoje = date('Y-m-d');
    $sql_parcelas .= " AND p.vencimento = ?";
}

// Adicionar filtro por cliente específico se fornecido
if ($cliente_especifico && $cliente_id > 0) {
    $sql_parcelas .= " AND c.id = ?";
}

// Adicionar filtro por parcelas específicas se fornecido
if (!empty($parcela_id)) {
    $parcela_ids = explode(',', $parcela_id);
    if (!empty($parcela_ids)) {
        $placeholders_parcelas = implode(',', array_fill(0, count($parcela_ids), '?'));
        $sql_parcelas .= " AND p.id IN ($placeholders_parcelas)";
    }
}

$stmt_parcelas = $conn->prepare($sql_parcelas);

// Associar os parâmetros dinamicamente
$tipos = '';
$params = [];

// Adicionar os status
$tipos .= str_repeat('s', count($lista_status_final));
$params = array_merge($params, $lista_status_final);

// Adicionar data de vencimento se necessário
if ($vencimento_hoje) {
    $tipos .= 's';
    $params[] = date('Y-m-d');
}

// Adicionar cliente_id se filtro por cliente específico
if ($cliente_especifico && $cliente_id > 0) {
    $tipos .= 'i';
    $params[] = $cliente_id;
}

// Adicionar parcela_ids se fornecidos
if (!empty($parcela_id)) {
    $parcela_ids = explode(',', $parcela_id);
    if (!empty($parcela_ids)) {
        $tipos .= str_repeat('i', count($parcela_ids));
        foreach ($parcela_ids as $pid) {
            $params[] = (int)$pid;
        }
    }
}

// Binding de parâmetros
if (!empty($params)) {
    $stmt_parcelas->bind_param($tipos, ...$params);
}

$stmt_parcelas->execute();
$resultado_parcelas = $stmt_parcelas->get_result();

// Verificar se encontrou parcelas
if (!$resultado_parcelas || $resultado_parcelas->num_rows == 0) {
    $resposta['mensagem'] = "Nenhuma parcela encontrada com o(s) status informado(s)";
    echo json_encode($resposta);
    exit;
}

// Lista para armazenar os telefones processados (para evitar duplicação)
$telefones_processados = [];
$telefones_enviados = [];
$telefones_falha = [];

// Processar parcelas e enviar mensagens
while ($parcela = $resultado_parcelas->fetch_assoc()) {
    $telefone = preg_replace('/[^0-9]/', '', $parcela['telefone_cliente']);
    
    // Adicionar código do país (55) se não estiver presente
    if (strlen($telefone) <= 11) {
        $telefone = "55" . $telefone;
    }
    
    // Pular se o telefone já foi processado
    if (in_array($telefone, $telefones_processados)) {
        continue;
    }
    
    // Adicionar à lista de processados
    $telefones_processados[] = $telefone;
    
    // Calcular dias de atraso, se aplicável
    $dias_atraso = 0;
    if ($parcela['status_parcela'] == 'atrasado') {
        $data_vencimento = strtotime($parcela['vencimento']);
        $data_atual = strtotime(date('Y-m-d'));
        $dias_atraso = floor(($data_atual - $data_vencimento) / (60 * 60 * 24));
    }
    
    // Recuperar dados de todas as parcelas do empréstimo para calcular valores totais
    $sql_todas_parcelas = "SELECT * FROM parcelas WHERE emprestimo_id = ?";
    $stmt_todas_parcelas = $conn->prepare($sql_todas_parcelas);
    $stmt_todas_parcelas->bind_param("i", $parcela['emprestimo_id']);
    $stmt_todas_parcelas->execute();
    $resultado_todas_parcelas = $stmt_todas_parcelas->get_result();
    
    // Inicializar variáveis para os cálculos
    $total_parcelas = 0;
    $parcelas_pagas = 0;
    $valor_total = 0;
    $valor_pago = 0;
    $valor_em_aberto = 0;
    $lista_parcelas = [];
    
    if ($resultado_todas_parcelas) {
        while ($p = $resultado_todas_parcelas->fetch_assoc()) {
            $total_parcelas++;
            $valor_total += floatval($p['valor'] ?? 0);
            
            if (isset($p['status']) && $p['status'] == 'pago') {
                $parcelas_pagas++;
                $valor_pago += floatval($p['valor'] ?? 0);
            } else {
                $valor_em_aberto += floatval($p['valor'] ?? 0);
                $lista_parcelas[] = $p['numero'] ?? '?';
            }
        }
    }
    
    // Preparar a mensagem e substituir as variáveis
    $mensagem = $template['mensagem'];
    
    // Preparar os dados para substituição
    $dados = [
        'nome_cliente' => $parcela['nome_cliente'] ?? 'Cliente',
        'valor_parcela' => 'R$ ' . number_format($parcela['valor'] ?? 0, 2, ',', '.'),
        'data_vencimento' => date('d/m/Y', strtotime($parcela['vencimento'] ?? date('Y-m-d'))),
        'atraso' => isset($dias_atraso) ? $dias_atraso . ' dias' : '0 dias',
        'valor_total' => 'R$ ' . number_format($valor_total, 2, ',', '.'),
        'valor_em_aberto' => 'R$ ' . number_format($valor_em_aberto, 2, ',', '.'),
        'total_parcelas' => $total_parcelas,
        'parcelas_pagas' => $parcelas_pagas,
        'valor_pago' => 'R$ ' . number_format($valor_pago, 2, ',', '.'),
        'numero_parcela' => $parcela['numero_parcela'] ?? '0',
        'lista_parcelas_restantes' => implode(', ', $lista_parcelas),
        'link_pagamento' => BASE_URL . 'pagamento/link.php?p=' . $parcela['parcela_id'],
        'nomedogestor' => isset($_SESSION['nome']) ? $_SESSION['nome'] : 'Gestor'
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
            $telefones_enviados[] = [
                'telefone' => $telefone,
                'cliente' => $parcela['nome_cliente'],
                'parcela_id' => $parcela['parcela_id']
            ];
            
            // Registrar o envio no log
            if (isset($conn)) {
                $sql_log = "INSERT INTO mensagens_log (
                    emprestimo_id,
                    parcela_id,
                    template_id,
                    telefone,
                    mensagem,
                    data_envio,
                    usuario_id,
                    status
                ) VALUES (?, ?, ?, ?, ?, NOW(), 1, 'sucesso')";
                
                $stmt_log = $conn->prepare($sql_log);
                $stmt_log->bind_param(
                    "iiiss",
                    $parcela['emprestimo_id'],
                    $parcela['parcela_id'],
                    $template_id,
                    $telefone,
                    $mensagem
                );
                $stmt_log->execute();
            }
        } else {
            $telefones_falha[] = [
                'telefone' => $telefone,
                'cliente' => $parcela['nome_cliente'],
                'erro' => $erro_msg
            ];
            
            // Registrar o erro no log
            if (isset($conn)) {
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
                ) VALUES (?, ?, ?, ?, ?, NOW(), 1, 'erro', ?)";
                
                $stmt_log = $conn->prepare($sql_log);
                $stmt_log->bind_param(
                    "iiisss",
                    $parcela['emprestimo_id'],
                    $parcela['parcela_id'],
                    $template_id,
                    $telefone,
                    $mensagem,
                    $erro_msg
                );
                $stmt_log->execute();
            }
        }
        
        // Pequeno intervalo entre envios para evitar bloqueios
        usleep(300000); // 0.3 segundos
        
    } catch (Exception $e) {
        $telefones_falha[] = [
            'telefone' => $telefone,
            'cliente' => $parcela['nome_cliente'],
            'erro' => $e->getMessage()
        ];
    }
}

// Preparar resposta
$resposta['sucesso'] = count($telefones_enviados) > 0;
$resposta['total_enviados'] = count($telefones_enviados);
$resposta['total_falhas'] = count($telefones_falha);
$resposta['enviados'] = $telefones_enviados;
$resposta['falhas'] = $telefones_falha;
$resposta['mensagem'] = "Processo concluído. " . count($telefones_enviados) . " mensagens enviadas e " . count($telefones_falha) . " falhas.";

// Retornar resultado como JSON
header('Content-Type: application/json');
echo json_encode($resposta, JSON_PRETTY_PRINT); 