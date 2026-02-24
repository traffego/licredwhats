<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verificar permissões administrativas
apenasAdmin();

require_once __DIR__ . '/../includes/head.php';

// Busca todos os usuários
$sql = "SELECT id, nome, email, tipo, nivel_autoridade FROM usuarios ORDER BY nome ASC";
$resultado = $conn->query($sql);
$usuarios = $resultado->fetch_all(MYSQLI_ASSOC);
?>

<body>
<div class="container py-4">

    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['sucesso'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['erro'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Usuários do Sistema</h3>
        <a href="novo.php" class="btn btn-primary">+ Novo Usuário</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Tipo</th>
                            <th>Nível de Autoridade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($usuarios) > 0): ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usuario['nome']) ?></td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $usuario['tipo'] === 'administrador' ? 'primary' : 'info' ?>">
                                            <?= ucfirst($usuario['tipo']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $usuario['nivel_autoridade'] === 'superadmin' ? 'danger' : ($usuario['nivel_autoridade'] === 'administrador' ? 'primary' : 'secondary') ?>">
                                            <?= ucfirst($usuario['nivel_autoridade']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <form action="editar.php" method="POST" class="me-1">
                                                <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i> Editar
                                                </button>
                                            </form>
                                            <?php if ($_SESSION['usuario_id'] != $usuario['id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluir" data-id="<?= $usuario['id'] ?>" data-nome="<?= htmlspecialchars($usuario['nome']) ?>">
                                                    <i class="bi bi-trash"></i> Excluir
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-3">Nenhum usuário cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalExcluirLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o usuário <strong id="nome-usuario"></strong>?</p>
                <p class="text-danger">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="excluir.php" method="POST">
                    <input type="hidden" name="id" id="id-excluir">
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar dados do modal de exclusão
    const modalExcluir = document.getElementById('modalExcluir');
    if (modalExcluir) {
        modalExcluir.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nome = button.getAttribute('data-nome');
            
            document.getElementById('id-excluir').value = id;
            document.getElementById('nome-usuario').textContent = nome;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 