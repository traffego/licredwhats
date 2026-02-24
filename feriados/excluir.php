<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/queries_feriados.php';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?mensagem=erro&texto=ID do feriado não informado");
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: index.php?mensagem=erro&texto=ID do feriado inválido");
    exit;
}

// Buscar o feriado no banco para obter o ano (para redirecionamento)
$feriado = buscarFeriadoPorId($conn, $id);
if (!$feriado) {
    header("Location: index.php?mensagem=erro&texto=Feriado não encontrado");
    exit;
}

$ano = $feriado['ano'];

// Excluir o feriado
$resultado = excluirFeriado($conn, $id);

if ($resultado) {
    header("Location: index.php?ano={$ano}&mensagem=sucesso&texto=Feriado excluído com sucesso!");
} else {
    header("Location: index.php?ano={$ano}&mensagem=erro&texto=Erro ao excluir o feriado: " . $conn->error);
}
exit; 