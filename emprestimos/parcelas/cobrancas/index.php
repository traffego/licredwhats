<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/autenticacao.php';
require_once __DIR__ . '/../../../includes/conexao.php';
require_once __DIR__ . '/../../../includes/head.php';

// Busca todos os clientes para o filtro
$clientes = [];
$stmt_clientes = $conn->query("SELECT id, nome FROM clientes ORDER BY nome");
while ($cliente = $stmt_clientes->fetch_assoc()) {
    $clientes[] = $cliente;
}

// Busca tipos de cobrança únicos
$tipos_cobranca = [];
$stmt_tipos = $conn->query("SELECT DISTINCT tipo_de_cobranca FROM emprestimos WHERE tipo_de_cobranca IS NOT NULL ORDER BY tipo_de_cobranca");
while ($tipo = $stmt_tipos->fetch_assoc()) {
    $tipos_cobranca[] = $tipo['tipo_de_cobranca'];
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Parcelas para Cobrança</h3>
        <a href="<?= BASE_URL ?>emprestimos/index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar para Empréstimos
        </a>
    </div>

    <!-- Cards Informativos -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total de Parcelas</h6>
                    <div id="card-total-parcelas">
                        <h4 class="mb-0">Carregando...</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="card-title">Parcelas Pendentes</h6>
                    <div id="card-parcelas-pendentes">
                        <h4 class="mb-0">Carregando...</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Parcelas Atrasadas</h6>
                    <div id="card-parcelas-atrasadas">
                        <h4 class="mb-0">Carregando...</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Parcelas Pagas</h6>
                    <div id="card-parcelas-pagas">
                        <h4 class="mb-0">Carregando...</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form id="form-filtros" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendente">Pendente</option>
                        <option value="parcial">Parcial</option>
                        <option value="pago">Pago</option>
                        <option value="atrasado">Atrasado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Vencimento Início</label>
                    <input type="date" name="data_inicio" id="data_inicio" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Vencimento Fim</label>
                    <input type="date" name="data_fim" id="data_fim" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="cliente" class="form-label">Cliente</label>
                    <input type="text" name="cliente" id="cliente" class="form-control" placeholder="Nome do cliente">
                </div>
                <div class="col-md-3">
                    <label for="valor_min" class="form-label">Valor Mínimo</label>
                    <input type="number" name="valor_min" id="valor_min" class="form-control" step="0.01" min="0">
                </div>
                <div class="col-md-3">
                    <label for="valor_max" class="form-label">Valor Máximo</label>
                    <input type="number" name="valor_max" id="valor_max" class="form-control" step="0.01" min="0">
                </div>
                <div class="col-md-3">
                    <label for="tipo_cobranca" class="form-label">Tipo de Cobrança</label>
                    <select name="tipo_cobranca" id="tipo_cobranca" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_cobranca as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $tipo))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ordem" class="form-label">Ordenar por</label>
                    <select name="ordem" id="ordem" class="form-select">
                        <option value="prioridade">Prioridade</option>
                        <option value="vencimento">Vencimento</option>
                        <option value="valor">Valor</option>
                        <option value="cliente">Cliente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="por-pagina" class="form-label">Registros por página</label>
                    <select name="por_pagina" id="por-pagina" class="form-select">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="1000000000000">Todos</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="limparFiltros()">Limpar</button>
                    <button type="button" class="btn btn-outline-warning" onclick="filtrarParcelasHoje()">
                        <i class="bi bi-calendar-check"></i> Parcelas de Hoje
                    </button>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Parcelas -->
    <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Telefone</th>
                                <th>Empréstimo</th>
                                <th>Parcela</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                    <tbody id="tabela-parcelas">
                        <!-- Dados serão carregados via JavaScript -->
                        </tbody>
                    </table>
                    </div>
                    
            <!-- Paginação -->
            <div class="d-flex justify-content-end align-items-center mt-3">
                <nav aria-label="Navegação de páginas">
                    <ul class="pagination mb-0" id="paginacao">
                        <!-- Paginação será carregada via JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
let paginaAtual = 1;
let totalPaginas = 1;

function atualizarCards(data) {
    // Busca estatísticas totais do servidor
    fetch('estatisticas_parcelas.php')
        .then(response => response.json())
        .then(stats => {
            // Atualiza os cards com as estatísticas totais
            document.getElementById('card-total-parcelas').innerHTML = `
                <h4 class="mb-0">${stats.total_parcelas}</h4>
                <div class="small">R$ ${stats.total_valor}</div>
            `;

            document.getElementById('card-parcelas-pendentes').innerHTML = `
                <h4 class="mb-0">${stats.total_pendentes}</h4>
                <div class="small">R$ ${stats.valor_pendente}</div>
            `;

            document.getElementById('card-parcelas-atrasadas').innerHTML = `
                <h4 class="mb-0">${stats.total_atrasadas}</h4>
                <div class="small">R$ ${stats.valor_atrasado}</div>
            `;

            document.getElementById('card-parcelas-pagas').innerHTML = `
                <h4 class="mb-0">${stats.total_pagas}</h4>
                <div class="small">R$ ${stats.valor_pago}</div>
            `;
        })
        .catch(error => console.error('Erro ao carregar estatísticas:', error));
}

function carregarParcelas(pagina = 1) {
    const form = document.getElementById('form-filtros');
    const formData = new FormData(form);
    formData.append('pagina', pagina);
    formData.append('por_pagina', document.getElementById('por-pagina').value);

    const queryString = new URLSearchParams(formData).toString();
    
    fetch(`carregar_parcelas.php?${queryString}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('tabela-parcelas');
            tbody.innerHTML = '';

            data.dados.forEach(parcela => {
                tbody.innerHTML += `
                    <tr>
                        <td>${parcela.cliente.nome}</td>
                        <td>${parcela.cliente.telefone}</td>
                        <td>
                            <span class="badge bg-secondary">#${parcela.emprestimo.id}</span>
                            <small class="d-block text-muted">${parcela.emprestimo.tipo.replace('_', ' ')}</small>
                        </td>
                        <td>
                            <div><strong>${parcela.parcela.numero} de ${parcela.emprestimo.total_parcelas}</strong></div>
                            <div>R$ ${parcela.parcela.valor}</div>
                            <div class="small text-muted">${parcela.parcela.vencimento}</div>
                            ${parcela.parcela.valor_pago ? `
                                <div class="small">
                                    <span class="text-success">Pago: R$ ${parcela.parcela.valor_pago}</span>
                                </div>
                            ` : ''}
                        </td>
                        <td>
                            <span class="badge bg-${parcela.parcela.status_class}">${parcela.parcela.status}</span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="registrarPagamento(${parcela.id})">
                                    <i class="bi bi-cash"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="enviarMensagem(${parcela.id})">
                                    <i class="bi bi-whatsapp"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            // Atualiza cards
            atualizarCards(data);

            // Atualiza paginação
            paginaAtual = data.paginacao.pagina_atual;
            totalPaginas = data.paginacao.total_paginas;
            atualizarPaginacao();
        })
        .catch(error => {
            console.error('Erro ao carregar parcelas:', error);
            alert('Erro ao carregar parcelas. Por favor, tente novamente.');
        });
}

function atualizarPaginacao() {
    const paginacao = document.getElementById('paginacao');
    paginacao.innerHTML = '';

    // Botão Anterior
    paginacao.innerHTML += `
        <li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="carregarParcelas(${paginaAtual - 1})" aria-label="Anterior">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
    `;
    
    // Páginas
    for (let i = 1; i <= totalPaginas; i++) {
        if (
            i === 1 || // Primeira página
            i === totalPaginas || // Última página
            (i >= paginaAtual - 2 && i <= paginaAtual + 2) // 2 páginas antes e depois da atual
        ) {
            paginacao.innerHTML += `
                <li class="page-item ${i === paginaAtual ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="carregarParcelas(${i})">${i}</a>
                </li>
            `;
        } else if (
            (i === paginaAtual - 3 && paginaAtual > 4) ||
            (i === paginaAtual + 3 && paginaAtual < totalPaginas - 3)
        ) {
            paginacao.innerHTML += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
    }

    // Botão Próximo
    paginacao.innerHTML += `
        <li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="carregarParcelas(${paginaAtual + 1})" aria-label="Próximo">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `;
}

function filtrarParcelasHoje() {
    // Limpa outros filtros primeiro
    limparFiltros();
    
    // Define os filtros para mostrar apenas parcelas pendentes de hoje
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('data_inicio').value = hoje;
    document.getElementById('data_fim').value = hoje;
    document.getElementById('status').value = 'pendente';
    
    // Submete o formulário
    document.getElementById('form-filtros').dispatchEvent(new Event('submit'));
}

function limparFiltros() {
    const form = document.getElementById('form-filtros');
    form.reset();
    form.dispatchEvent(new Event('submit'));
}

// Event Listeners
document.getElementById('form-filtros').addEventListener('submit', function(e) {
    e.preventDefault();
    carregarParcelas(1);
});

document.getElementById('por-pagina').addEventListener('change', function() {
    carregarParcelas(1);
});

// Funções de ação
function registrarPagamento(parcelaId) {
    // Implementar lógica de pagamento
    window.location.href = `../registrar_pagamento.php?id=${parcelaId}`;
}

function enviarMensagem(parcelaId) {
    // Implementar lógica de envio de mensagem
    window.location.href = `../../../mensagens/enviar.php?parcela_id=${parcelaId}`;
}

// Carrega os dados iniciais
document.addEventListener('DOMContentLoaded', function() {
    carregarParcelas(1);
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>