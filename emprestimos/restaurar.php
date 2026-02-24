<?php
// Instruções de saída de buffer (não remova esta linha)
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verifica se o ID foi passado
$emprestimo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$emprestimo_id) {
    header("Location: inativos.php?erro=1&msg=" . urlencode("ID do empréstimo não informado"));
    exit;
}

// Verifica se o empréstimo existe e está inativo
$stmt = $conn->prepare("SELECT id, status FROM emprestimos WHERE id = ?");
$stmt->bind_param("i", $emprestimo_id);
$stmt->execute();
$result = $stmt->get_result();
$emprestimo = $result->fetch_assoc();

if (!$emprestimo) {
    header("Location: inativos.php?erro=1&msg=" . urlencode("Empréstimo não encontrado"));
    exit;
}

if ($emprestimo['status'] !== 'inativo') {
    header("Location: inativos.php?erro=1&msg=" . urlencode("Este empréstimo não está inativo"));
    exit;
}

// Atualiza o status para ativo
$stmt = $conn->prepare("UPDATE emprestimos SET status = 'ativo' WHERE id = ?");
$stmt->bind_param("i", $emprestimo_id);

if ($stmt->execute()) {
    header("Location: index.php?sucesso=1&msg=" . urlencode("Empréstimo restaurado com sucesso!"));
} else {
    header("Location: inativos.php?erro=1&msg=" . urlencode("Erro ao restaurar empréstimo: " . $stmt->error));
}
exit; 