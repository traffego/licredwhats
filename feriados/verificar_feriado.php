<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/queries_feriados.php';

// Configurar cabeçalhos para resposta JSON
header('Content-Type: application/json');

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método de requisição inválido'
    ]);
    exit;
}

// Obter a data da requisição
$data = filter_input(INPUT_GET, 'data', FILTER_SANITIZE_SPECIAL_CHARS);

// Validar a data
if (!$data || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Formato de data inválido. Use YYYY-MM-DD.'
    ]);
    exit;
}

// Verificar se a data é um feriado
$feriado = verificarSeDataEFeriado($conn, $data);

// Verificar se é um feriado para evitar
$evitar = false;
if ($feriado && $feriado['evitar'] === 'sim_evitar') {
    $evitar = true;
}

echo json_encode([
    'sucesso' => true,
    'data' => $data,
    'e_feriado' => $feriado ? true : false,
    'evitar' => $evitar,
    'feriado' => $feriado
]);
exit; 