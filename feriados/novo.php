<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/queries_feriados.php';

// Título da página
$titulo = "Cadastrar Novo Feriado";

// Include do cabeçalho
include_once __DIR__ . '/../includes/head.php';
include_once __DIR__ . '/../includes/navbar.php';

// Verificar se existe mensagem de erro
$mensagem = null;
if (isset($_GET['mensagem']) && $_GET['mensagem'] === 'erro') {
    $mensagem = [
        'tipo' => 'erro',
        'texto' => $_GET['texto'] ?? 'Ocorreu um erro ao processar sua solicitação.'
    ];
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4><?php echo $titulo; ?></h4>
        </div>
        <div class="card-body">
            <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $mensagem['tipo'] === 'erro' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensagem['texto']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <form action="salvar.php" method="POST">
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome do Feriado <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
                
                <div class="mb-3">
                    <label for="data" class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="data" name="data" required>
                </div>
                
                <div class="mb-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="fixo" selected>Fixo</option>
                        <option value="movel">Móvel</option>
                    </select>
                    <div class="form-text">Feriados fixos ocorrem na mesma data todo ano e serão exibidos automaticamente em todos os anos. Feriados móveis podem variar de data a cada ano.</div>
                </div>
                
                <div class="mb-3">
                    <label for="evitar" class="form-label">Evitar Geração de Parcelas</label>
                    <select class="form-select" id="evitar" name="evitar">
                        <option value="sim_evitar" selected>Sim, evitar</option>
                        <option value="nao_evitar">Não é necessário evitar</option>
                    </select>
                    <div class="form-text">Indica se o sistema deve evitar gerar parcelas nesta data. Você poderá alterar esta opção posteriormente.</div>
                </div>
                
                <div class="mb-3">
                    <label for="local" class="form-label">Abrangência</label>
                    <select class="form-select" id="local" name="local">
                        <option value="nacional" selected>Nacional</option>
                        <option value="estadual">Estadual</option>
                        <option value="municipal">Municipal</option>
                    </select>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">Voltar</a>
                    <button type="submit" class="btn btn-primary">Salvar Feriado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?> 