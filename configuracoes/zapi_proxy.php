<?php
/**
 * Proxy PHP para chamadas à Z-API
 * Evita problemas de CORS e protege as credenciais
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';

// Verificar se o usuário é admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Buscar credenciais Z-API do banco
$sql = "SELECT zapi_instance_id, zapi_token, zapi_client_token FROM configuracoes WHERE id = 1";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Configurações não encontradas']);
    exit;
}

$config = $result->fetch_assoc();
$instanceId = $config['zapi_instance_id'];
$token = $config['zapi_token'];
$clientToken = $config['zapi_client_token'] ?? '';

if (empty($instanceId) || empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Instance ID e Token da Z-API são obrigatórios. Preencha os campos acima e salve antes de conectar.']);
    exit;
}

// Ação solicitada
$action = $_GET['action'] ?? '';

$baseUrl = "https://api.z-api.io/instances/{$instanceId}/token/{$token}";

switch ($action) {
    case 'status':
        $response = zapiRequest($baseUrl . '/status', $clientToken);
        break;
    
    case 'qrcode':
        $response = zapiRequest($baseUrl . '/qr-code/image', $clientToken);
        break;
    
    case 'disconnect':
        $response = zapiRequest($baseUrl . '/disconnect', $clientToken);
        break;
    
    case 'restart':
        $response = zapiRequest($baseUrl . '/restart', $clientToken);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida']);
        exit;
}

header('Content-Type: application/json');
echo $response;

/**
 * Faz requisição GET para a Z-API
 */
function zapiRequest(string $url, string $clientToken = ''): string {
    $headers = ['Content-Type: application/json'];
    
    if (!empty($clientToken)) {
        $headers[] = 'Client-Token: ' . $clientToken;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        http_response_code(500);
        return json_encode(['error' => 'Erro de conexão: ' . $error]);
    }
    
    if ($httpCode >= 400) {
        http_response_code($httpCode);
    }
    
    return $response ?: json_encode(['error' => 'Resposta vazia da Z-API']);
}
