<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

$ids = $_POST['ids'] ?? [];
$nao_excluidos = [];
$excluidos = [];

foreach ($ids as $id) {
    $id = (int) $id;

    // Verificar se o cliente possui emprestimos
    $stmt = $conn->prepare("SELECT COUNT(*) FROM emprestimos WHERE cliente_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($temEmprestimos);
    $stmt->fetch();
    $stmt->close();

    if ($temEmprestimos > 0) {
        $stmt = $conn->prepare("SELECT nome FROM clientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($nome);
        $stmt->fetch();
        $stmt->close();

        $nao_excluidos[] = $nome;
        continue;
    }

    // Excluir cliente
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $excluidos[] = $id;
    }
    $stmt->close();
}

$conn->close();

require_once __DIR__ . '/../includes/head.php';
?>

<body>
<div class="container py-4">
    <h3 class="mb-4">Resultado da Exclusão</h3>

    <?php if ($excluidos): ?>
        <div class="alert alert-success">
            <?= count($excluidos) ?> cliente(s) excluído(s) com sucesso.
        </div>
    <?php endif; ?>

    <?php if ($nao_excluidos): ?>
        <div class="alert alert-warning">
            Os seguintes clientes não puderam ser excluídos pois possuem empréstimos registrados:
            <ul>
                <?php foreach ($nao_excluidos as $nome): ?>
                    <li><?= htmlspecialchars($nome) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary">Voltar para a listagem</a>
</div>
</body>
</html>
