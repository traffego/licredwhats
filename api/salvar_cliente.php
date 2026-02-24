<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';

// Definir cabeçalhos para resposta JSON
header('Content-Type: application/json');

// Checar se a requisição é via AJAX
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
if (!$is_ajax) {
    echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);
    exit;
}

try {
    $tipo_pessoa = $_POST['tipo_pessoa'] == '2' ? 'Jurídica' : 'Física';
    
    // Valores default para campos não obrigatórios
    $email = $_POST['email'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $cpf_cnpj = $_POST['cpf_cnpj'] ?? '';
    $chave_pix = $_POST['chave_pix'] ?? '';
    $status = $_POST['status'] ?? 'Ativo';
    
    // Tratando o investidor (quem cadastrou o cliente)
    $investidor_id = isset($_POST['investidor_id']) ? $_POST['investidor_id'] : null;
    if (empty($investidor_id) && isset($_SESSION['usuario_id'])) {
        $investidor_id = $_SESSION['usuario_id'];
    }

    // Validar nome (obrigatório)
    if (empty($_POST['nome'])) {
        echo json_encode(['success' => false, 'message' => 'O nome do cliente é obrigatório.']);
        exit;
    }

    // INSERT
    $sql = "INSERT INTO clientes (
        nome, email, telefone, tipo_pessoa, cpf_cnpj, 
        status, chave_pix, indicacao
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssssi",
        $_POST['nome'],
        $email,
        $telefone,
        $tipo_pessoa,
        $cpf_cnpj,
        $status,
        $chave_pix,
        $investidor_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar query: " . $stmt->error);
    }

    $novo_id = $conn->insert_id;
    
    // Buscar os dados do cliente recém-inserido para retornar
    $sql_cliente = "SELECT id, nome FROM clientes WHERE id = ?";
    $stmt_cliente = $conn->prepare($sql_cliente);
    $stmt_cliente->bind_param("i", $novo_id);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    $cliente = $result_cliente->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Cliente cadastrado com sucesso.',
        'cliente' => $cliente
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao cadastrar cliente: ' . $e->getMessage()
    ]);
} 