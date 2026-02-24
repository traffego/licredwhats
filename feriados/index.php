<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/funcoes_data.php';
require_once __DIR__ . '/queries_feriados.php';

// Obter o ano via GET ou usar o ano atual
$ano_atual = date('Y');
$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: $ano_atual;

// Título da página
$titulo = "Gerenciamento de Feriados - {$ano}";

// Buscar os feriados do ano selecionado
$feriados = buscarFeriadosPorAno($conn, $ano);

// Include do cabeçalho
include_once __DIR__ . '/../includes/head.php';
include_once __DIR__ . '/../includes/navbar.php';

// Verificar se existe mensagem
$mensagem = null;
if (isset($_GET['mensagem'])) {
    $tipo = $_GET['mensagem'] === 'sucesso' ? 'sucesso' : 'erro';
    $mensagem = [
        'tipo' => $tipo,
        'texto' => $_GET['texto'] ?? ($tipo === 'sucesso' ? 'Operação realizada com sucesso' : 'Ocorreu um erro ao processar sua solicitação.')
    ];
}

// Mapeamento dos tipos de feriado
$tipos_feriado = [
    'fixo' => 'Fixo',
    'movel' => 'Móvel'
];

// Mapeamento para opção de evitar
$opcoes_evitar = [
    'sim_evitar' => 'Sim',
    'nao_evitar' => 'Não'
];

// Mapeamento de abrangência
$opcoes_local = [
    'nacional' => 'Nacional',
    'estadual' => 'Estadual',
    'municipal' => 'Municipal'
];
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h4><?php echo $titulo; ?></h4>
                <div>
                    <a href="novo.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Novo Feriado
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $mensagem['tipo'] === 'erro' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensagem['texto']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <!-- Mensagem de feedback AJAX -->
            <div id="mensagem-ajax" class="alert d-none"></div>

            <!-- Seleção de Ano -->
            <div class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <form method="GET" action="index.php" class="d-flex">
                            <select name="ano" class="form-select me-2">
                                <?php for ($i = $ano_atual - 5; $i <= $ano_atual + 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php if ($i == $ano) echo 'selected'; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (empty($feriados)): ?>
                <div class="alert alert-info">
                    Nenhum feriado cadastrado para o ano <?php echo $ano; ?>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Evitar Parcelas</th>
                                <th>Abrangência</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feriados as $feriado): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($feriado['nome']); ?></td>
                                    <td><?php echo formatarData($feriado['data']); ?></td>
                                    <td><?php echo $tipos_feriado[$feriado['tipo']] ?? $feriado['tipo']; ?></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input toggle-evitar" 
                                                   type="checkbox" 
                                                   id="evitar_<?php echo $feriado['id']; ?>" 
                                                   data-id="<?php echo $feriado['id']; ?>"
                                                   <?php echo ($feriado['evitar'] === 'sim_evitar') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="evitar_<?php echo $feriado['id']; ?>">
                                                <span id="label_evitar_<?php echo $feriado['id']; ?>">
                                                    <?php echo ($feriado['evitar'] === 'sim_evitar') ? 'Sim' : 'Não'; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </td>
                                    <td><?php echo $opcoes_local[$feriado['local']] ?? 'Nacional'; ?></td>
                                    <td>
                                        <a href="editar.php?id=<?php echo $feriado['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="excluir.php?id=<?php echo $feriado['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este feriado?')">
                                            <i class="fas fa-trash"></i> Excluir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript para atualizar o campo "evitar" -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.toggle-evitar');
    const mensagemAjax = document.getElementById('mensagem-ajax');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const evitar = this.checked ? 'sim_evitar' : 'nao_evitar';
            const label = document.getElementById(`label_evitar_${id}`);
            
            // Atualizar via AJAX
            fetch('atualizar_evitar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&evitar=${evitar}`
            })
            .then(response => response.json())
            .then(data => {
                // Atualizar o texto do label
                label.textContent = this.checked ? 'Sim' : 'Não';
                
                // Exibir mensagem de sucesso/erro
                mensagemAjax.textContent = data.mensagem;
                mensagemAjax.classList.remove('d-none', 'alert-success', 'alert-danger');
                mensagemAjax.classList.add(data.sucesso ? 'alert-success' : 'alert-danger');
                
                // Esconder a mensagem após 3 segundos
                setTimeout(() => {
                    mensagemAjax.classList.add('d-none');
                }, 3000);
            })
            .catch(error => {
                console.error('Erro:', error);
                mensagemAjax.textContent = 'Erro ao atualizar o feriado';
                mensagemAjax.classList.remove('d-none', 'alert-success');
                mensagemAjax.classList.add('alert-danger');
            });
        });
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?> 