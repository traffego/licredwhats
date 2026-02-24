<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';

// Verificar permissões administrativas
apenasAdmin();

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/queries.php';

// Buscar dados iniciais para os cards
$sql_totais = "SELECT 
    SUM(valor_emprestado) as total_emprestado,
    COUNT(id) as total_emprestimos
FROM emprestimos 
WHERE (status != 'inativo' OR status IS NULL)";
$result_totais = $conn->query($sql_totais);
$totais = $result_totais->fetch_assoc();

// Buscar total recebido
$sql_recebido = "SELECT 
    SUM(CASE 
        WHEN p.status = 'pago' THEN p.valor
        WHEN p.status = 'parcial' THEN p.valor_pago
        ELSE 0 
    END) as total_recebido
FROM parcelas p
INNER JOIN emprestimos e ON p.emprestimo_id = e.id
WHERE (e.status != 'inativo' OR e.status IS NULL)";
$result_recebido = $conn->query($sql_recebido);
$recebido = $result_recebido->fetch_assoc();

// Buscar total pendente
$sql_pendente = "SELECT 
    SUM(CASE 
        WHEN p.status = 'pendente' THEN p.valor
        WHEN p.status = 'parcial' THEN (p.valor - IFNULL(p.valor_pago, 0))
        ELSE 0 
    END) as total_pendente
FROM parcelas p
INNER JOIN emprestimos e ON p.emprestimo_id = e.id
WHERE p.status IN ('pendente', 'parcial') 
AND (e.status != 'inativo' OR e.status IS NULL)";
$result_pendente = $conn->query($sql_pendente);
$pendente = $result_pendente->fetch_assoc();

// Buscar empréstimos ativos
$emprestimos_ativos = contarEmprestimosAtivos($conn);

// Buscar total atrasado
$ontem = date('Y-m-d', strtotime('-1 day'));
$sql_atrasado = "SELECT 
    SUM(CASE 
        WHEN p.status = 'parcial' THEN (p.valor - IFNULL(p.valor_pago, 0))
        ELSE p.valor 
    END) AS total_valor,
    COUNT(DISTINCT p.emprestimo_id) AS total_emprestimos,
    COUNT(p.id) AS total_parcelas
FROM parcelas p
INNER JOIN emprestimos e ON p.emprestimo_id = e.id
WHERE (p.status = 'atrasado' OR (p.status IN ('pendente', 'parcial') AND p.vencimento < ?))
AND (e.status != 'inativo' OR e.status IS NULL)";

$stmt_atrasado = $conn->prepare($sql_atrasado);
$stmt_atrasado->bind_param("s", $ontem);
$stmt_atrasado->execute();
$result_atrasado = $stmt_atrasado->get_result();
$atrasado = $result_atrasado->fetch_assoc();
?>

<div class="container py-4">
    <div id="alertas"></div>

    <!-- Cards de Resumo - Desktop -->
    <div class="d-none d-md-block">
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Emprestado</h6>
                        <h4 class="mb-0">R$ <?= number_format($totais['total_emprestado'] ?? 0, 2, ',', '.') ?></h4>
                        <p class="mt-1 mb-0">Total: <?= $totais['total_emprestimos'] ?? 0 ?> empréstimos</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Recebido</h6>
                        <h4 class="mb-0">R$ <?= number_format($recebido['total_recebido'] ?? 0, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Pendente</h6>
                        <h4 class="mb-0">R$ <?= number_format($pendente['total_pendente'] ?? 0, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Empréstimos</h6>
                        <h4 class="mb-0"><?= $totais['total_emprestimos'] ?? 0 ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-primary bg-opacity-75 text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Ativos</h6>
                        <h4 class="mb-0"><?= (int)$emprestimos_ativos ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Parcelas Atrasadas</h6>
                        <h4><?= $atrasado['total_parcelas'] ?? 0 ?> parcelas | R$ <?= number_format($atrasado['total_valor'] ?? 0, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards de Resumo - Mobile -->
    <div class="d-md-none mb-4">
        <div class="row g-3">
            <div class="col-6">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Emprestado</h6>
                        <h4 class="mb-0">R$ <?= number_format($totais['total_emprestado'] ?? 0, 2, ',', '.') ?></h4>
                        <p class="mt-1 mb-0">Total: <?= $totais['total_emprestimos'] ?? 0 ?> empréstimos</p>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Recebido</h6>
                        <h4 class="mb-0">R$ <?= number_format($recebido['total_recebido'] ?? 0, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="card-title">Total Pendente</h6>
                        <h4 class="mb-0">R$ <?= number_format($pendente['total_pendente'] ?? 0, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Empréstimos</h6>
                        <h4 class="mb-0"><?= $totais['total_emprestimos'] ?? 0 ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-primary bg-opacity-75 text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Ativos</h6>
                        <h4 class="mb-0"><?= (int)$emprestimos_ativos ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Atrasados</h6>
                        <h4 class="mb-0"><?= $atrasado['total_emprestimos'] ?? 0 ?></h4>
                        <small><?= $atrasado['total_parcelas'] ?? 0 ?> parcelas | R$ <?= number_format($atrasado['total_valor'] ?? 0, 2, ',', '.') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cabeçalho com Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="form-filtros" class="row g-3">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Empréstimos</h5>
                        <div>
                            <a href="novo.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle"></i> Novo
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row g-2">
                        <div class="col-sm-6 col-md-5">
                            <input type="text" name="busca" class="form-control form-control-sm" 
                                   placeholder="Buscar por cliente...">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select name="tipo" class="form-select form-select-sm">
                                <option value="">Todos os tipos</option>
                                <option value="parcelada_comum">Parcelada Comum</option>
                                <option value="reparcelada_com_juros">Reparcelada com Juros</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Status</option>
                                <option value="ativo">Ativo</option>
                                <option value="atrasado">Atrasado</option>
                                <option value="quitado">Quitado</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <select name="por_pagina" class="form-select form-select-sm">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="-1">Todos</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Empréstimos -->
    <div class="card">
        <!-- Loader Overlay -->
        <div id="loader-overlay" class="position-absolute w-100 h-100 d-none" style="background: rgba(255,255,255,0.8); z-index: 1000;">
            <div class="d-flex justify-content-center align-items-center h-100">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
        </div>

        <!-- Tabela para Desktop -->
        <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabela-emprestimos">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 35%">Cliente</th>
                            <th style="width: 25%">Progresso/Falta</th>
                            <th style="width: 15%">Valor</th>
                            <th style="width: 25%">Parcelas/Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-emprestimos">
                        <!-- Dados serão carregados via AJAX -->
                    </tbody>
                </table>
                <div id="paginacao" class="p-3">
                    <!-- Paginação será carregada via AJAX -->
                </div>
            </div>
        </div>

        <!-- Cards para Mobile -->
        <div class="d-md-none">
            <div class="list-group list-group-flush" id="lista-mobile">
                <!-- Dados serão carregados via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
let timeoutId;
let currentPage = 1;
let isMobile = window.innerWidth < 768;
let loaderTimeout;

// Função para mostrar/esconder o loader
function toggleLoader(show) {
    clearTimeout(loaderTimeout);
    
    if (show) {
        // Só mostra o loader se a requisição demorar mais que 500ms
        loaderTimeout = setTimeout(() => {
            document.getElementById('loader-overlay').classList.remove('d-none');
        }, 500);
    } else {
        document.getElementById('loader-overlay').classList.add('d-none');
    }
}

// Função para carregar os empréstimos
function carregarEmprestimos(pagina = 1) {
    const formData = new FormData(document.getElementById('form-filtros'));
    formData.append('pagina', pagina);
    
    const queryString = new URLSearchParams(formData).toString();
    const endpoint = isMobile ? 'buscar_emprestimos_mobile.php' : 'buscar_emprestimos.php';
    
    toggleLoader(true);
    
    fetch(endpoint + '?' + queryString)
        .then(response => response.json())
        .then(data => {
            if (isMobile) {
                document.getElementById('lista-mobile').innerHTML = data.html_cards;
            } else {
                document.getElementById('tbody-emprestimos').innerHTML = data.html_tabela;
                document.getElementById('paginacao').innerHTML = data.html_paginacao;
            }
            
            // Reativar os eventos de clique após atualizar o conteúdo
            ativarEventosClique();
        })
        .catch(error => {
            console.error('Erro ao carregar empréstimos:', error);
            mostrarAlerta('Erro ao carregar os empréstimos. Por favor, tente novamente.', 'danger');
        })
        .finally(() => {
            toggleLoader(false);
        });
}

// Função para mostrar alertas
function mostrarAlerta(mensagem, tipo) {
    const alertasDiv = document.getElementById('alertas');
    const alerta = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${mensagem}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    `;
    alertasDiv.innerHTML = alerta;
}

// Função para ativar eventos de clique
function ativarEventosClique() {
    // Eventos de clique nas linhas da tabela
    document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('a')) {
                window.location = this.dataset.href;
            }
        });
    });

    // Eventos de clique na paginação
    document.querySelectorAll('.page-link[data-pagina]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            currentPage = parseInt(this.dataset.pagina);
            carregarEmprestimos(currentPage);
        });
    });
}

// Eventos dos filtros
document.querySelectorAll('#form-filtros select').forEach(select => {
    select.addEventListener('change', () => {
        currentPage = 1;
        carregarEmprestimos(currentPage);
    });
});

document.querySelector('input[name="busca"]').addEventListener('input', function() {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        currentPage = 1;
        carregarEmprestimos(currentPage);
    }, 500);
});

// Carregar empréstimos iniciais
document.addEventListener('DOMContentLoaded', () => {
    carregarEmprestimos(currentPage);
});

// Atualizar isMobile quando a janela for redimensionada
window.addEventListener('resize', () => {
    const wasMobile = isMobile;
    isMobile = window.innerWidth < 768;
    
    // Se mudou de mobile para desktop ou vice-versa, recarregar os dados
    if (wasMobile !== isMobile) {
        carregarEmprestimos(currentPage);
    }
});
</script>

<style>
    /* Estilos para tabela compacta */
    .table {
        font-size: 0.875rem;
    }
    
    .table td {
        padding: 0.5rem;
        vertical-align: middle;
    }
    
    .table .fw-bold {
        font-size: 0.9rem;
        line-height: 1.2;
    }
    
    .table .small {
        font-size: 0.75rem;
        line-height: 1.2;
    }
    
    .table .progress {
        margin: 0.25rem 0;
        background-color: rgba(0,0,0,.05);
    }
    
    .table .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    
    .table .text-danger {
        color: #dc3545 !important;
    }
    
    /* Ajuste para células com duas linhas */
    .d-flex.flex-column {
        gap: 0.25rem;
    }
    
    /* Hover effect */
    .clickable-row:hover {
        background-color: rgba(0,0,0,.03);
    }
    
    /* Ajuste para o badge de status */
    .badge.w-100 {
        max-width: 80px;
        margin: 0 auto;
    }

    /* Estilos para a coluna de progresso/falta */
    .progress {
        height: 6px !important;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .progress-bar {
        transition: width .3s ease;
    }
    
    .progress + .text-muted {
        margin-top: 0.25rem;
    }
    
    /* Ajuste para o layout de valor faltante + porcentagem */
    .justify-content-between .fw-bold {
        font-size: 0.95rem;
    }
    
    .justify-content-between .small {
        background: rgba(0,0,0,.05);
        padding: 2px 6px;
        border-radius: 3px;
    }

    /* Estilos para a coluna de parcelas/status */
    .justify-content-between .badge {
        font-size: 0.7rem;
        padding: 0.35em 0.65em;
        min-width: 60px;
        text-align: center;
    }

    .text-muted span {
        font-size: 0.75rem;
        line-height: 1.2;
    }

    /* Ajustes específicos para status */
    .badge.text-bg-primary { background-color: #0d6efd !important; }
    .badge.text-bg-danger { background-color: #dc3545 !important; }
    .badge.text-bg-success { background-color: #198754 !important; }

    /* Melhorar alinhamento vertical */
    .d-flex.justify-content-between {
        min-height: 24px;
    }

    /* Ajuste para células com múltiplas linhas */
    td .d-flex.flex-column {
        min-height: 48px;
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>