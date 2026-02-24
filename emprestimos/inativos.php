<?php
// Instruções de saída de buffer (não remova esta linha)
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

// Busca apenas empréstimos inativos
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.cliente_id,
        e.valor_emprestado,
        e.parcelas,
        e.valor_parcela,
        e.data_inicio,
        e.status,
        c.nome AS cliente_nome 
    FROM emprestimos e 
    JOIN clientes c ON e.cliente_id = c.id 
    WHERE e.status = 'inativo'
    ORDER BY e.id DESC
");
$stmt->execute();
$emprestimos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Empréstimos Inativos</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        Esta página mostra todos os empréstimos que foram marcados como inativos. Estes empréstimos não aparecem na listagem principal.
    </div>

    <?php if (empty($emprestimos)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            Não existem empréstimos inativos no sistema.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Parcelas</th>
                                <th>Data Início</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emprestimos as $emp): ?>
                                <tr>
                                    <td><?= $emp['id'] ?></td>
                                    <td><?= htmlspecialchars($emp['cliente_nome']) ?></td>
                                    <td>R$ <?= number_format($emp['valor_emprestado'], 2, ',', '.') ?></td>
                                    <td><?= $emp['parcelas'] ?>x R$ <?= number_format($emp['valor_parcela'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($emp['data_inicio'])) ?></td>
                                    <td>
                                        <a href="visualizar.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                onclick="restaurarEmprestimo(<?= $emp['id'] ?>)">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function restaurarEmprestimo(id) {
    if (confirm('Tem certeza que deseja restaurar este empréstimo? Ele voltará a aparecer na listagem principal.')) {
        window.location.href = 'restaurar.php?id=' + id;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?> 