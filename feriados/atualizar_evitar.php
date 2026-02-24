<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/queries_feriados.php';

// Configurar cabeçalhos para resposta JSON
header('Content-Type: application/json');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método de requisição inválido'
    ]);
    exit;
}

// Obter os parâmetros da requisição
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$evitar = filter_input(INPUT_POST, 'evitar', FILTER_SANITIZE_SPECIAL_CHARS);

// Validar parâmetros
if (!$id || !in_array($evitar, ['sim_evitar', 'nao_evitar'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Parâmetros inválidos'
    ]);
    exit;
}

// Verificar se o feriado existe
$feriado = buscarFeriadoPorId($conn, $id);
if (!$feriado) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Feriado não encontrado'
    ]);
    exit;
}

// Atualizar o campo "evitar"
$resultado = atualizarEvitarFeriado($conn, $id, $evitar);

if ($resultado) {
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Feriado atualizado com sucesso',
        'evitar' => $evitar,
        'evitar_texto' => ($evitar === 'sim_evitar') ? 'Sim' : 'Não'
    ]);
} else {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao atualizar o feriado: ' . $conn->error
    ]);
}
exit; 