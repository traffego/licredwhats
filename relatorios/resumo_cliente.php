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

// Opções de filtro
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_ordem = isset($_GET['ordem']) ? $_GET['ordem'] : 'nome';
$filtro_direcao = isset($_GET['direcao']) ? $_GET['direcao'] : 'asc';

// Consulta para buscar resumo por cliente
$sql = "SELECT 
            c.id AS cliente_id,
            c.nome AS nome_cliente,
            c.telefone AS telefone_cliente,
            COUNT(DISTINCT e.id) AS total_emprestimos,
            COALESCE(SUM(e.valor_emprestado), 0) AS total_emprestado,
            (
                SELECT COALESCE(SUM(p2.valor_pago), 0) 
                FROM parcelas p2 
                INNER JOIN emprestimos e2 ON p2.emprestimo_id = e2.id 
                WHERE e2.cliente_id = c.id
            ) AS total_pago,
            (
                SELECT COALESCE(SUM(p3.valor), 0) 
                FROM parcelas p3 
                INNER JOIN emprestimos e3 ON p3.emprestimo_id = e3.id 
                WHERE e3.cliente_id = c.id
            ) AS total_parcelas,
            (
                SELECT COUNT(p3.id) 
                FROM parcelas p3 
                INNER JOIN emprestimos e3 ON p3.emprestimo_id = e3.id 
                WHERE e3.cliente_id = c.id 
                AND p3.status IN ('pendente', 'atrasado') 
                AND p3.vencimento < CURRENT_DATE()
            ) AS parcelas_atrasadas
        FROM 
            clientes c
            LEFT JOIN emprestimos e ON c.id = e.cliente_id
        GROUP BY 
            c.id";

// Adicionar condições de filtro se necessário
if ($filtro_status === 'inadimplentes') {
    $sql .= " HAVING parcelas_atrasadas > 0";
} elseif ($filtro_status === 'em_dia') {
    $sql .= " HAVING parcelas_atrasadas = 0 AND total_parcelas > total_pago";
} elseif ($filtro_status === 'sem_emprestimos') {
    $sql .= " HAVING total_emprestimos = 0";
} elseif ($filtro_status === 'quitados') {
    $sql .= " HAVING total_parcelas <= total_pago AND total_emprestado > 0";
} else {
    // No filtro padrão, não mostrar clientes sem empréstimos, mas sem excluí-los da consulta
    $sql .= " HAVING total_emprestimos > 0";
}

// Adicionar ordenação
if ($filtro_ordem === 'total_emprestado') {
    $sql .= " ORDER BY total_emprestado " . ($filtro_direcao === 'desc' ? 'DESC' : 'ASC');
} elseif ($filtro_ordem === 'total_pago') {
    $sql .= " ORDER BY total_pago " . ($filtro_direcao === 'desc' ? 'DESC' : 'ASC');
} elseif ($filtro_ordem === 'saldo_devedor') {
    $sql .= " ORDER BY (total_parcelas - total_pago) " . ($filtro_direcao === 'desc' ? 'DESC' : 'ASC');
} elseif ($filtro_ordem === 'parcelas_atrasadas') {
    $sql .= " ORDER BY parcelas_atrasadas " . ($filtro_direcao === 'desc' ? 'DESC' : 'ASC');
} else {
    // Ordenação padrão por nome
    $sql .= " ORDER BY nome_cliente " . ($filtro_direcao === 'desc' ? 'DESC' : 'ASC');
}

$result = $conn->query($sql);
$clientes = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
}

// Calcular totais
$total_geral_emprestado = 0;
$total_geral_pago = 0;
$total_geral_saldo = 0;
$total_geral_atrasadas = 0;
$total_clientes_ativos = 0;
$total_clientes_inadimplentes = 0;

foreach ($clientes as $cliente) {
    $total_geral_emprestado += floatval($cliente['total_emprestado']);
    $total_geral_pago += floatval($cliente['total_pago']);
    $saldo = floatval($cliente['total_parcelas']) - floatval($cliente['total_pago']);
    $total_geral_saldo += max(0, $saldo);
    $total_geral_atrasadas += intval($cliente['parcelas_atrasadas']);
    
    if (floatval($cliente['total_parcelas']) > floatval($cliente['total_pago'])) {
        $total_clientes_ativos++;
    }
    
    if (intval($cliente['parcelas_atrasadas']) > 0) {
        $total_clientes_inadimplentes++;
    }
}
?>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Resumo por Cliente</h1>
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
                        <h5 class="card-title">Total Pago</h5>
                        <h3 class="mb-0">R$ <?= number_format($total_geral_pago, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Saldo Devedor</h5>
                        <h3 class="mb-0">R$ <?= number_format($total_geral_saldo, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Clientes Inadimplentes</h5>
                        <h3 class="mb-0"><?= $total_clientes_inadimplentes ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="" <?= $filtro_status === '' ? 'selected' : '' ?>>Todos</option>
                            <option value="inadimplentes" <?= $filtro_status === 'inadimplentes' ? 'selected' : '' ?>>Inadimplentes</option>
                            <option value="em_dia" <?= $filtro_status === 'em_dia' ? 'selected' : '' ?>>Em dia (com saldo)</option>
                            <option value="quitados" <?= $filtro_status === 'quitados' ? 'selected' : '' ?>>Quitados</option>
                            <option value="sem_emprestimos" <?= $filtro_status === 'sem_emprestimos' ? 'selected' : '' ?>>Sem empréstimos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="ordem" class="form-label">Ordenar por</label>
                        <select name="ordem" id="ordem" class="form-select">
                            <option value="nome" <?= $filtro_ordem === 'nome' ? 'selected' : '' ?>>Nome</option>
                            <option value="total_emprestado" <?= $filtro_ordem === 'total_emprestado' ? 'selected' : '' ?>>Total Emprestado</option>
                            <option value="total_pago" <?= $filtro_ordem === 'total_pago' ? 'selected' : '' ?>>Total Pago</option>
                            <option value="saldo_devedor" <?= $filtro_ordem === 'saldo_devedor' ? 'selected' : '' ?>>Saldo Devedor</option>
                            <option value="parcelas_atrasadas" <?= $filtro_ordem === 'parcelas_atrasadas' ? 'selected' : '' ?>>Parcelas Atrasadas</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="direcao" class="form-label">Ordem</label>
                        <select name="direcao" id="direcao" class="form-select">
                            <option value="asc" <?= $filtro_direcao === 'asc' ? 'selected' : '' ?>>Crescente</option>
                            <option value="desc" <?= $filtro_direcao === 'desc' ? 'selected' : '' ?>>Decrescente</option>
                        </select>
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabela de Clientes -->
        <div class="card">
            <div class="card-body">
                <?php if (count($clientes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente / Contato</th>
                                    <th>Empréstimos / Valores</th>
                                    <th>Saldo / Atrasos</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): 
                                    $saldo_devedor = max(0, floatval($cliente['total_parcelas']) - floatval($cliente['total_pago']));
                                    
                                    // Determinar status do cliente
                                    $status = '';
                                    $status_class = '';
                                    
                                    if (intval($cliente['parcelas_atrasadas']) > 0) {
                                        $status = 'Inadimplente';
                                        $status_class = 'danger';
                                    } elseif (floatval($cliente['total_parcelas']) > floatval($cliente['total_pago'])) {
                                        $status = 'Em dia';
                                        $status_class = 'success';
                                    } elseif (floatval($cliente['total_emprestado']) > 0) {
                                        $status = 'Quitado';
                                        $status_class = 'info';
                                    } else {
                                        $status = 'Sem empréstimos';
                                        $status_class = 'secondary';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($cliente['nome_cliente']) ?></div>
                                        <small class="text-muted"><i class="bi bi-telephone"></i> <?= htmlspecialchars($cliente['telefone_cliente']) ?></small>
                                    </td>
                                    <td>
                                        <div><span class="badge bg-primary"><?= $cliente['total_emprestimos'] ?> empréstimo(s)</span></div>
                                        <div class="mt-1 small">
                                            <div><i class="bi bi-cash"></i> Emprestado: R$ <?= number_format($cliente['total_emprestado'], 2, ',', '.') ?></div>
                                            <div><i class="bi bi-check2-circle"></i> Pago: R$ <?= number_format($cliente['total_pago'], 2, ',', '.') ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold <?= $saldo_devedor > 0 ? 'text-danger' : 'text-success' ?>">
                                            <i class="bi bi-wallet2"></i> R$ <?= number_format($saldo_devedor, 2, ',', '.') ?>
                                        </div>
                                        <div class="mt-1">
                                            <?php if ($cliente['parcelas_atrasadas'] > 0): ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-exclamation-triangle"></i> <?= $cliente['parcelas_atrasadas'] ?> parcela(s) atrasada(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Sem atrasos
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?>"><?= $status ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <form action="<?= BASE_URL ?>clientes/visualizar.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= $cliente['cliente_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </form>
                                            <?php if (floatval($cliente['total_emprestado']) > 0): ?>
                                                <a href="<?= BASE_URL ?>clientes/extrato.php?id=<?= $cliente['cliente_id'] ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-file-text"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Nenhum cliente encontrado.
                    </div>
                <?php endif; ?>
            </div>
        </div>
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
    </style>
    
    <script>
        // Função para exportar para Excel
        function exportarExcel() {
            // Criar dados CSV
            let csv = [];
            const rows = document.querySelectorAll('table tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Obter o texto puro, removendo elementos HTML
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ');
                    data = data.replace(/"/g, '""'); // Escapar aspas duplas
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }
            
            // Download do arquivo
            const csvString = csv.join('\n');
            const filename = 'resumo_clientes_' + new Date().toISOString().slice(0, 10) + '.csv';
            
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            
            // Criar link e forçar download
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Função para exportar para PDF usando jsPDF
        function exportarPDF() {
            // Carregar jsPDF dinamicamente
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            script.onload = function() {
                // Carregar jspdf-autotable para suporte a tabelas
                const tableScript = document.createElement('script');
                tableScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js';
                tableScript.onload = function() {
                    gerarPDF();
                };
                document.head.appendChild(tableScript);
            };
            document.head.appendChild(script);
            
            function gerarPDF() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'mm', 'a4'); // landscape
                
                // Adicionar título
                doc.setFontSize(18);
                doc.text('Resumo por Cliente', 14, 15);
                
                // Adicionar data do relatório
                doc.setFontSize(10);
                doc.text('Data do relatório: ' + new Date().toLocaleDateString('pt-BR'), 14, 22);
                
                // Extrair dados da tabela
                const tableData = [];
                const headerRow = [];
                
                // Obter cabeçalhos
                document.querySelectorAll('table thead th').forEach(th => {
                    headerRow.push(th.innerText);
                });
                
                // Obter dados das linhas
                document.querySelectorAll('table tbody tr').forEach(tr => {
                    const rowData = [];
                    tr.querySelectorAll('td').forEach(td => {
                        rowData.push(td.innerText.replace(/\n/g, ' '));
                    });
                    tableData.push(rowData);
                });
                
                // Adicionar informações resumidas
                doc.setFontSize(12);
                doc.text('Informações Resumidas:', 14, 30);
                
                // Capturar valores dos cards
                const totalEmprestado = document.querySelector('.row.mb-4 .bg-primary .mb-0').innerText;
                const totalPago = document.querySelector('.row.mb-4 .bg-success .mb-0').innerText;
                const saldoDevedor = document.querySelector('.row.mb-4 .bg-warning .mb-0').innerText;
                const clientesInadimplentes = document.querySelector('.row.mb-4 .bg-danger .mb-0').innerText;
                
                // Adicionar estatísticas
                doc.setFontSize(10);
                const estatisticas = [
                    ['Total Emprestado:', totalEmprestado],
                    ['Total Pago:', totalPago],
                    ['Saldo Devedor:', saldoDevedor],
                    ['Clientes Inadimplentes:', clientesInadimplentes]
                ];
                
                // Adicionar estatísticas como tabela
                doc.autoTable({
                    startY: 35,
                    head: [['Métrica', 'Valor']],
                    body: estatisticas,
                    theme: 'grid',
                    headStyles: { fillColor: [41, 128, 185] },
                    margin: { left: 14 },
                    tableWidth: 100
                });
                
                // Adicionar a tabela principal
                doc.autoTable({
                    startY: doc.lastAutoTable.finalY + 10,
                    head: [headerRow],
                    body: tableData,
                    theme: 'grid',
                    headStyles: { fillColor: [41, 128, 185] },
                    styles: { fontSize: 8 },
                    margin: { left: 14 }
                });
                
                // Adicionar rodapé
                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setFontSize(8);
                    doc.text('Página ' + i + ' de ' + pageCount, 14, doc.internal.pageSize.height - 10);
                    doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), doc.internal.pageSize.width - 60, doc.internal.pageSize.height - 10);
                }
                
                // Salvar o PDF
                doc.save('resumo_clientes_' + new Date().toISOString().slice(0, 10) + '.pdf');
            }
        }
    </script>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 