<?php
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/funcoes_comissoes.php';

// Busca o ID do usuário logado
$usuario_id = $_SESSION['usuario_id'];

// Busca o status das comissões
$status_comissoes = buscarStatusComissoes($conn, $usuario_id);

// Inclui o cabeçalho
$titulo_pagina = "Status das Comissões";
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $titulo_pagina; ?></h1>
    
    <!-- Cards de Resumo -->
    <div class="row mt-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h4 class="mb-2">Total Previsto</h4>
                    <h2 class="mb-0">R$ <?php echo number_format($status_comissoes['resumo']['total_comissao_prevista'], 2, ',', '.'); ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">Comissão total esperada</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h4 class="mb-2">Já Recebido</h4>
                    <h2 class="mb-0">R$ <?php echo number_format($status_comissoes['resumo']['total_comissao_processada'], 2, ',', '.'); ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">Comissões já processadas</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <h4 class="mb-2">A Receber</h4>
                    <h2 class="mb-0">R$ <?php echo number_format($status_comissoes['resumo']['total_comissao_pendente'], 2, ',', '.'); ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">Comissões pendentes</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <h4 class="mb-2">Total Emprestado</h4>
                    <h2 class="mb-0">R$ <?php echo number_format($status_comissoes['resumo']['total_valor_emprestado'], 2, ',', '.'); ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">Capital total investido</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Empréstimos -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Detalhamento por Empréstimo
        </div>
        <div class="card-body">
            <table id="tabela-emprestimos" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Valor Emprestado</th>
                        <th>Progresso</th>
                        <th>Comissão Prevista</th>
                        <th>Já Recebido</th>
                        <th>A Receber</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_comissoes['emprestimos'] as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['emprestimo']['cliente_nome']); ?></td>
                        <td>R$ <?php echo number_format($emp['emprestimo']['valor_emprestado'], 2, ',', '.'); ?></td>
                        <td>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $emp['status']['progresso']; ?>%"
                                     aria-valuenow="<?php echo $emp['status']['progresso']; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo number_format($emp['status']['progresso'], 1); ?>%
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo $emp['emprestimo']['parcelas_pagas']; ?> de <?php echo $emp['emprestimo']['total_parcelas']; ?> parcelas
                            </small>
                        </td>
                        <td>R$ <?php echo number_format($emp['comissoes']['prevista'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($emp['comissoes']['processada'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($emp['comissoes']['pendente'], 2, ',', '.'); ?></td>
                        <td>
                            <?php if ($emp['status']['finalizado']): ?>
                                <span class="badge bg-success">Finalizado</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Em Andamento</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gráfico de Comparativo -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-bar me-1"></i>
            Comparativo Realizado vs Previsto
        </div>
        <div class="card-body">
            <canvas id="graficoComparativo" width="100%" height="30"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuração da tabela
    new DataTable('#tabela-emprestimos', {
        order: [[2, 'desc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        }
    });

    // Configuração do gráfico
    const ctx = document.getElementById('graficoComparativo');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Comissões'],
            datasets: [{
                label: 'Previsto',
                data: [<?php echo $status_comissoes['resumo']['total_comissao_prevista']; ?>],
                backgroundColor: 'rgba(13, 110, 253, 0.5)',
                borderColor: 'rgb(13, 110, 253)',
                borderWidth: 1
            }, {
                label: 'Realizado',
                data: [<?php echo $status_comissoes['resumo']['total_comissao_processada']; ?>],
                backgroundColor: 'rgba(25, 135, 84, 0.5)',
                borderColor: 'rgb(25, 135, 84)',
                borderWidth: 1
            }, {
                label: 'Pendente',
                data: [<?php echo $status_comissoes['resumo']['total_comissao_pendente']; ?>],
                backgroundColor: 'rgba(255, 193, 7, 0.5)',
                borderColor: 'rgb(255, 193, 7)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': R$ ' + context.raw.toLocaleString('pt-BR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?> 