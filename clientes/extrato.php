<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

// Verifica se o ID do cliente foi fornecido
$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$cliente_id) {
    header('Location: ' . BASE_URL . 'clientes');
    exit;
}

// Buscar informações do cliente
$sql_cliente = "SELECT * FROM clientes WHERE id = ?";
$stmt = $conn->prepare($sql_cliente);
$stmt->bind_param('i', $cliente_id);
$stmt->execute();
$result_cliente = $stmt->get_result();
$cliente = $result_cliente->fetch_assoc();

if (!$cliente) {
    header('Location: ' . BASE_URL . 'clientes');
    exit;
}

// Buscar todos os empréstimos do cliente
$sql_emprestimos = "SELECT 
    e.*,
    (SELECT COALESCE(SUM(valor_pago), 0) FROM parcelas WHERE emprestimo_id = e.id) as total_pago,
    (SELECT COALESCE(SUM(valor), 0) FROM parcelas WHERE emprestimo_id = e.id) as total_parcelas,
    (SELECT COUNT(*) FROM parcelas WHERE emprestimo_id = e.id AND status IN ('pendente', 'atrasado') AND vencimento < CURRENT_DATE) as parcelas_atrasadas
FROM emprestimos e 
WHERE e.cliente_id = ?
ORDER BY e.data_inicio DESC";

$stmt = $conn->prepare($sql_emprestimos);
$stmt->bind_param('i', $cliente_id);
$stmt->execute();
$result_emprestimos = $stmt->get_result();
$emprestimos = [];

while ($emprestimo = $result_emprestimos->fetch_assoc()) {
    $emprestimos[] = $emprestimo;
}

// Buscar todas as parcelas do cliente
$sql_parcelas = "SELECT 
    p.*,
    e.data_inicio,
    e.valor_emprestado,
    e.tipo_de_cobranca,
    CONCAT('Empréstimo de R$ ', FORMAT(e.valor_emprestado, 2, 'pt_BR'), ' em ', e.parcelas, ' parcelas') as emprestimo_descricao
FROM parcelas p
INNER JOIN emprestimos e ON p.emprestimo_id = e.id
WHERE e.cliente_id = ?
ORDER BY p.vencimento DESC";

$stmt = $conn->prepare($sql_parcelas);
$stmt->bind_param('i', $cliente_id);
$stmt->execute();
$result_parcelas = $stmt->get_result();
$parcelas = [];

while ($parcela = $result_parcelas->fetch_assoc()) {
    $parcelas[] = $parcela;
}

// Calcular totais
$total_emprestado = 0;
$total_pago = 0;
$total_parcelas_valor = 0;
$total_atrasadas = 0;

foreach ($emprestimos as $emprestimo) {
    $total_emprestado += $emprestimo['valor_emprestado'];
    $total_pago += $emprestimo['total_pago'];
    $total_parcelas_valor += $emprestimo['total_parcelas'];
    $total_atrasadas += $emprestimo['parcelas_atrasadas'];
}

$saldo_devedor = max(0, $total_parcelas_valor - $total_pago);
?>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Extrato do Cliente</h1>
            <div class="btn-group">
                <a href="<?= BASE_URL ?>relatorios/resumo_cliente.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
            </div>
        </div>
        
        <!-- Informações do Cliente -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title">Dados do Cliente</h5>
                        <p class="mb-1"><strong>Nome:</strong> <?= htmlspecialchars($cliente['nome']) ?></p>
                        <p class="mb-1"><strong>Telefone:</strong> <?= htmlspecialchars($cliente['telefone']) ?></p>
                        <p class="mb-1"><strong>CPF:</strong> <?= htmlspecialchars($cliente['cpf']) ?></p>
                        <p class="mb-0"><strong>Endereço:</strong> <?= htmlspecialchars($cliente['endereco']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5 class="card-title">Resumo Financeiro</h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="border rounded p-2">
                                    <div class="small text-muted">Total Emprestado</div>
                                    <div class="fw-bold">R$ <?= number_format($total_emprestado, 2, ',', '.') ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2">
                                    <div class="small text-muted">Total Pago</div>
                                    <div class="fw-bold text-success">R$ <?= number_format($total_pago, 2, ',', '.') ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2">
                                    <div class="small text-muted">Saldo Devedor</div>
                                    <div class="fw-bold text-danger">R$ <?= number_format($saldo_devedor, 2, ',', '.') ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2">
                                    <div class="small text-muted">Parcelas Atrasadas</div>
                                    <div class="fw-bold <?= $total_atrasadas > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $total_atrasadas ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de Empréstimos -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Empréstimos</h5>
            </div>
            <div class="card-body">
                <?php if (count($emprestimos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Valor Emprestado</th>
                                    <th>Total Parcelas</th>
                                    <th>Total Pago</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emprestimos as $emprestimo): 
                                    $saldo = $emprestimo['total_parcelas'] - $emprestimo['total_pago'];
                                    if ($saldo <= 0) {
                                        $status = 'Quitado';
                                        $status_class = 'success';
                                    } elseif ($emprestimo['parcelas_atrasadas'] > 0) {
                                        $status = 'Atrasado';
                                        $status_class = 'danger';
                                    } else {
                                        $status = 'Em dia';
                                        $status_class = 'primary';
                                    }
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($emprestimo['data_inicio'])) ?></td>
                                    <td>
                                        <?= sprintf(
                                            'Empréstimo de R$ %s em %d parcelas (%s)',
                                            number_format($emprestimo['valor_emprestado'], 2, ',', '.'),
                                            $emprestimo['parcelas'],
                                            $emprestimo['tipo_de_cobranca'] === 'parcelada_comum' ? 'Normal' : 'Reparcelado'
                                        ) ?>
                                    </td>
                                    <td>R$ <?= number_format($emprestimo['valor_emprestado'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($emprestimo['total_parcelas'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($emprestimo['total_pago'], 2, ',', '.') ?></td>
                                    <td><span class="badge bg-<?= $status_class ?>"><?= $status ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        Nenhum empréstimo encontrado.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lista de Parcelas -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Histórico de Parcelas</h5>
            </div>
            <div class="card-body">
                <?php if (count($parcelas) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Vencimento</th>
                                    <th>Empréstimo</th>
                                    <th>Valor</th>
                                    <th>Valor Pago</th>
                                    <th>Data Pagamento</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parcelas as $parcela): 
                                    $status_class = '';
                                    switch ($parcela['status']) {
                                        case 'pago':
                                            $status_class = 'success';
                                            break;
                                        case 'pendente':
                                            $status_class = 'warning';
                                            break;
                                        case 'atrasado':
                                            $status_class = 'danger';
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($parcela['vencimento'])) ?></td>
                                    <td><?= htmlspecialchars($parcela['emprestimo_descricao']) ?></td>
                                    <td>R$ <?= number_format($parcela['valor'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($parcela['valor_pago'] ?? 0, 2, ',', '.') ?></td>
                                    <td>
                                        <?= $parcela['data_pagamento'] ? date('d/m/Y', strtotime($parcela['data_pagamento'])) : '-' ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= ucfirst($parcela['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        Nenhuma parcela encontrada.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        @media print {
            .navbar, .btn-group, footer {
                display: none !important;
            }
            .container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .card-body {
                padding: 0 !important;
            }
        }
    </style>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 