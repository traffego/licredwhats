<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verifica se a coluna 'status' já existe na tabela emprestimos
$stmt = $conn->query("SHOW COLUMNS FROM emprestimos LIKE 'status'");
$coluna_existe = $stmt->num_rows > 0;

if (!$coluna_existe) {
    // Adiciona a coluna status
    $stmt = $conn->prepare("ALTER TABLE emprestimos ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ativo'");
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Coluna "status" adicionada com sucesso à tabela emprestimos!</div>';
    } else {
        echo '<div class="alert alert-danger">Erro ao adicionar coluna "status": ' . $stmt->error . '</div>';
    }
} else {
    echo '<div class="alert alert-info">A coluna "status" já existe na tabela emprestimos.</div>';
}

// Atualiza os empréstimos que não tem status definido
$stmt = $conn->prepare("UPDATE emprestimos SET status = 'ativo' WHERE status IS NULL OR status = ''");
if ($stmt->execute()) {
    echo '<div class="alert alert-success">Status dos empréstimos atualizado com sucesso!</div>';
} else {
    echo '<div class="alert alert-danger">Erro ao atualizar status dos empréstimos: ' . $stmt->error . '</div>';
}

// Agora vamos modificar as consultas para não mostrar empréstimos inativos
echo '<div class="alert alert-info">Atualizações concluídas! Os empréstimos inativados não aparecerão mais na listagem principal.</div>';
echo '<div class="mt-3"><a href="index.php" class="btn btn-primary">Voltar para a listagem de empréstimos</a></div>';
?> 