<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autenticacao.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/head.php';

// Verifica se o usuário tem permissão adequada
$nivel_usuario = $_SESSION['nivel_autoridade'] ?? '';
if ($nivel_usuario !== 'administrador' && $nivel_usuario !== 'superadmin') {
    echo '<div class="container py-4"><div class="alert alert-danger">Você não tem permissão para acessar esta página.</div></div>';
    exit;
}

// Verifica se o formulário foi submetido
$filtro_aplicado = isset($_GET['filtrar']);

// Filtros
$filtro_investidor = isset($_GET['investidor']) ? intval($_GET['investidor']) : 0;
$filtro_periodo = isset($_GET['periodo']) ? intval($_GET['periodo']) : 30;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';

// Condição de status para as queries
$status_condition = "";
if ($filtro_aplicado && $filtro_status) {
    if ($filtro_status === 'ativo') {
        $status_condition = "AND (e.status = 'ativo' OR e.status IS NULL)";
    } elseif ($filtro_status === 'quitado') {
        $status_condition = "AND e.status = 'quitado'";
    }
}

// Query principal para buscar resumo dos investidores
$sql = "SELECT 
    u.id AS investidor_id,
    u.nome AS investidor_nome,
    COUNT(DISTINCT c.id) AS total_clientes,
    COUNT(DISTINCT CASE WHEN e.id IS NOT NULL $status_condition THEN e.id END) AS total_emprestimos,
    COALESCE(SUM(CASE WHEN e.id IS NOT NULL $status_condition THEN e.valor_emprestado ELSE 0 END), 0) AS total_valor_emprestado,
    (
        SELECT COALESCE(SUM(
            CASE 
                WHEN p.status = 'pago' THEN p.valor
                WHEN p.status = 'parcial' THEN COALESCE(p.valor_pago, 0)
                ELSE 0 
            END
        ), 0)
        FROM parcelas p
        JOIN emprestimos e2 ON p.emprestimo_id = e2.id
        WHERE e2.investidor_id = u.id
        $status_condition
    ) AS total_valor_recebido,
    (
        SELECT COALESCE(SUM(
            CASE 
                WHEN p.status IN ('pendente', 'atrasado') THEN p.valor
                WHEN p.status = 'parcial' THEN (p.valor - COALESCE(p.valor_pago, 0))
                ELSE 0 
            END
        ), 0)
        FROM parcelas p
        JOIN emprestimos e2 ON p.emprestimo_id = e2.id
        WHERE e2.investidor_id = u.id
        $status_condition
    ) AS total_valor_pendente,
    (
        SELECT COUNT(DISTINCT p2.id)
        FROM parcelas p2
        JOIN emprestimos e2 ON p2.emprestimo_id = e2.id
        WHERE e2.investidor_id = u.id
        $status_condition
        AND p2.vencimento < CURRENT_DATE()
        AND p2.status = 'pendente'
    ) AS parcelas_atrasadas
FROM 
    usuarios u
LEFT JOIN 
    emprestimos e ON u.id = e.investidor_id
LEFT JOIN 
    clientes c ON e.cliente_id = c.id
WHERE 
    u.tipo = 'investidor'
GROUP BY 
    u.id, u.nome
ORDER BY 
    u.nome";

// Executar query principal sem parâmetros
$result = $conn->query($sql);
$investidores = [];
while ($row = $result->fetch_assoc()) {
    $investidores[] = $row;
}

// Query para buscar detalhes dos clientes por investidor
if ($filtro_investidor > 0) {
    $sql_clientes = "SELECT 
        c.id AS cliente_id,
        c.nome AS cliente_nome,
        c.indicacao,
        COUNT(DISTINCT CASE WHEN e.id IS NOT NULL $status_condition THEN e.id END) AS total_emprestimos,
        COALESCE(SUM(CASE WHEN e.id IS NOT NULL $status_condition THEN e.valor_emprestado ELSE 0 END), 0) AS total_valor_emprestado,
        (
            SELECT COALESCE(SUM(
                CASE 
                    WHEN p2.status = 'pago' THEN p2.valor
                    WHEN p2.status = 'parcial' THEN COALESCE(p2.valor_pago, 0)
                    ELSE 0 
                END
            ), 0)
            FROM parcelas p2
            JOIN emprestimos e2 ON p2.emprestimo_id = e2.id
            WHERE e2.cliente_id = c.id 
            AND e2.investidor_id = ?
            $status_condition
        ) AS valor_recebido,
        (
            SELECT COALESCE(SUM(
                CASE 
                    WHEN p2.status IN ('pendente', 'atrasado') THEN p2.valor
                    WHEN p2.status = 'parcial' THEN (p2.valor - COALESCE(p2.valor_pago, 0))
                    ELSE 0 
                END
            ), 0)
            FROM parcelas p2
            JOIN emprestimos e2 ON p2.emprestimo_id = e2.id
            WHERE e2.cliente_id = c.id 
            AND e2.investidor_id = ?
            $status_condition
        ) AS valor_pendente,
        (
            SELECT COUNT(DISTINCT p3.id)
            FROM parcelas p3
            JOIN emprestimos e3 ON p3.emprestimo_id = e3.id
            WHERE e3.cliente_id = c.id 
            AND e3.investidor_id = ?
            $status_condition
            AND p3.vencimento < CURRENT_DATE()
            AND p3.status = 'pendente'
        ) AS parcelas_atrasadas
    FROM 
        clientes c
    LEFT JOIN 
        emprestimos e ON c.id = e.cliente_id AND e.investidor_id = ?
    WHERE 
        (c.indicacao = ? OR EXISTS (
            SELECT 1 
            FROM emprestimos e4 
            WHERE e4.cliente_id = c.id 
            AND e4.investidor_id = ?
        ))
    GROUP BY 
        c.id, c.nome, c.indicacao
    ORDER BY 
        c.nome";

    $stmt_clientes = $conn->prepare($sql_clientes);
    $stmt_clientes->bind_param("iiiiii", 
        $filtro_investidor,  // Para primeira subquery (valor_recebido)
        $filtro_investidor,  // Para segunda subquery (valor_pendente)
        $filtro_investidor,  // Para terceira subquery (parcelas_atrasadas)
        $filtro_investidor,  // Para JOIN com empréstimos
        $filtro_investidor,  // Para WHERE indicacao
        $filtro_investidor   // Para EXISTS subquery
    );
    
    $stmt_clientes->execute();
    $result_clientes = $stmt_clientes->get_result();
    
    $clientes_investidor = [];
    if ($result_clientes && $result_clientes->num_rows > 0) {
        while ($row = $result_clientes->fetch_assoc()) {
            $clientes_investidor[] = $row;
        }
    }
}

// Nome do investidor selecionado para exibição
$investidor_selecionado_nome = '';
if ($filtro_investidor > 0) {
    foreach ($investidores as $inv) {
        if ($inv['investidor_id'] == $filtro_investidor) {
            $investidor_selecionado_nome = $inv['investidor_nome'];
            break;
        }
    }
}

// Calcular totais gerais
$total_geral_clientes = 0;
$total_geral_emprestimos = 0;
$total_geral_emprestado = 0;
$total_geral_recebido = 0;
$total_geral_pendente = 0;
$total_geral_atrasadas = 0;

foreach ($investidores as $investidor) {
    $total_geral_clientes += $investidor['total_clientes'];
    $total_geral_emprestimos += $investidor['total_emprestimos'];
    $total_geral_emprestado += $investidor['total_valor_emprestado'];
    $total_geral_recebido += $investidor['total_valor_recebido'];
    $total_geral_pendente += $investidor['total_valor_pendente'];
    $total_geral_atrasadas += $investidor['parcelas_atrasadas'];
}
?>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?= $filtro_investidor > 0 ? "Clientes do Investidor: $investidor_selecionado_nome" : "Relatório de Investidores" ?></h1>
            <div class="btn-group">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
                <button type="button" class="btn btn-outline-success" onclick="exportarExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="exportarPDF()">
                    <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
            </div>
        </div>
        
        <!-- Resumo -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Emprestado</h5>
                        <h3 class="mb-0">R$ <?= number_format($total_geral_emprestado, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Recebido</h5>
                        <h3 class="mb-0">R$ <?= number_format($total_geral_recebido, 2, ',', '.') ?></h3>
                        <small>No período selecionado</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Total Pendente</h5>
                        <h3 class="mb-0">R$ <?= number_format($total_geral_pendente, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Parcelas Atrasadas</h5>
                        <h3 class="mb-0"><?= $total_geral_atrasadas ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="investidor" class="form-label">Investidor</label>
                        <select name="investidor" id="investidor" class="form-select">
                            <option value="0">Todos os Investidores</option>
                            <?php foreach ($investidores as $inv): ?>
                                <option value="<?= $inv['investidor_id'] ?>" <?= $filtro_investidor == $inv['investidor_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inv['investidor_nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="periodo" class="form-label">Período (dias)</label>
                        <input type="number" class="form-control" id="periodo" name="periodo" value="<?= $filtro_periodo ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="ativo" <?= $filtro_status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                            <option value="quitado" <?= $filtro_status === 'quitado' ? 'selected' : '' ?>>Quitados</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="filtrar" value="1" class="btn btn-primary">
                            <i class="bi bi-filter me-1"></i>Filtrar
                        </button>
                        <?php if ($filtro_aplicado): ?>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Limpar Filtros
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($filtro_investidor === 0): ?>
            <!-- Tabela de Resumo por Investidor -->
            <div class="card">
                <div class="card-body">
                    <?php if (count($investidores) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Investidor</th>
                                        <th>Quantidade</th>
                                        <th class="text-end">Valor Emprestado</th>
                                        <th class="text-end">Valor Recebido</th>
                                        <th class="text-end">Pendente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($investidores as $investidor): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($investidor['investidor_nome']) ?></td>
                                        <td>
                                            <div>
                                                <small class="text-muted">Clientes Ativos: </small>
                                                <strong><?= $investidor['total_clientes'] ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">Total Empréstimos: </small>
                                                <span><?= $investidor['total_emprestimos'] ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">R$ <?= number_format($investidor['total_valor_emprestado'], 2, ',', '.') ?></td>
                                        <td class="text-end">R$ <?= number_format($investidor['total_valor_recebido'], 2, ',', '.') ?></td>
                                        <td class="text-end">
                                            <div>
                                                <strong>R$ <?= number_format($investidor['total_valor_pendente'], 2, ',', '.') ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">
                                                    <?php if ($investidor['parcelas_atrasadas'] > 0): ?>
                                                        <span class="text-danger"><?= $investidor['parcelas_atrasadas'] ?> parcela(s) atrasada(s)</span>
                                                    <?php else: ?>
                                                        <span class="text-success">Sem atrasos</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Totais</th>
                                        <th>
                                            <div>
                                                <small class="text-muted">Clientes Ativos: </small>
                                                <strong><?= $total_geral_clientes ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">Total Empréstimos: </small>
                                                <span><?= $total_geral_emprestimos ?></span>
                                            </div>
                                        </th>
                                        <th class="text-end">R$ <?= number_format($total_geral_emprestado, 2, ',', '.') ?></th>
                                        <th class="text-end">R$ <?= number_format($total_geral_recebido, 2, ',', '.') ?></th>
                                        <th class="text-end">
                                            <div>
                                                <strong>R$ <?= number_format($total_geral_pendente, 2, ',', '.') ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">
                                                    <?php if ($total_geral_atrasadas > 0): ?>
                                                        <span class="text-danger"><?= $total_geral_atrasadas ?> parcela(s) atrasada(s)</span>
                                                    <?php else: ?>
                                                        <span class="text-success">Sem atrasos</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Não foram encontrados investidores com os filtros selecionados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Tabela de Clientes do Investidor Selecionado -->
            <div class="card">
                <div class="card-body">
                    <?php if (count($clientes_investidor) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Total Empréstimos</th>
                                        <th>Valor Total</th>
                                        <th>Valor Recebido</th>
                                        <th>Pendente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes_investidor as $cliente): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cliente['cliente_nome']) ?></td>
                                        <td><?= $cliente['total_emprestimos'] ?></td>
                                        <td>R$ <?= number_format($cliente['total_valor_emprestado'], 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format($cliente['valor_recebido'], 2, ',', '.') ?></td>
                                        <td>
                                            <div>
                                                <strong>R$ <?= number_format($cliente['valor_pendente'], 2, ',', '.') ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">
                                                    <?php if ($cliente['parcelas_atrasadas'] > 0): ?>
                                                        <span class="text-danger"><?= $cliente['parcelas_atrasadas'] ?> parcela(s) atrasada(s)</span>
                                                    <?php else: ?>
                                                        <span class="text-success">Sem atrasos</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Este investidor não possui clientes registrados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        @media print {
            .navbar, .btn-group, footer, form {
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
            @page {
                size: landscape;
            }
        }
        /* Ajusta o espaçamento e estilo da coluna de quantidade */
        .text-muted {
            color: #6c757d !important;
            font-size: 0.85rem;
        }
        /* Remove margem desnecessária */
        .mb-1 {
            margin-bottom: 0 !important;
        }
        /* Ajusta o tamanho do número */
        strong, span {
            font-size: 0.95rem;
        }
        /* Remove o display block que estava forçando quebra de linha */
        .d-block {
            display: inline !important;
            font-size: 0.85rem;
        }
    </style>
    
    <script>
        // Função para exportar para Excel
        function exportarExcel() {
            // Criar dados CSV
            let csv = [];
            const rows = document.querySelectorAll('table tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Limpar o texto (remover espaços extras, quebras de linha, etc)
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                    // Colocar aspas ao redor de cada campo
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }
            
            // Baixar CSV
            const csvString = csv.join('\n');
            const filename = '<?= $filtro_investidor > 0 ? "clientes_investidor_" . $filtro_investidor : "relatorio_investidores" ?>_<?= date("Y-m-d") ?>.csv';
            
            let downloadLink = document.createElement('a');
            downloadLink.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString);
            downloadLink.download = filename;
            
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        // Função para exportar para PDF
        function exportarPDF() {
            window.print();
        }
    </script>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 