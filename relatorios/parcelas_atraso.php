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

// Data atual para verificar parcelas atrasadas
$hoje = date('Y-m-d');

// Consulta para buscar parcelas em atraso
$sql = "SELECT 
            c.nome AS nome_cliente,
            c.telefone AS telefone_cliente,
            e.id AS id_emprestimo,
            p.id AS parcela_id,
            p.numero AS numero_parcela,
            p.valor AS valor_parcela,
            IFNULL(p.valor_pago, 0) AS valor_pago,
            p.status,
            p.vencimento,
            DATEDIFF(CURRENT_DATE(), p.vencimento) AS dias_atraso
        FROM 
            parcelas p
            INNER JOIN emprestimos e ON p.emprestimo_id = e.id
            INNER JOIN clientes c ON e.cliente_id = c.id
        WHERE 
            (p.status = 'atrasado' OR (p.status IN ('pendente', 'parcial') AND p.vencimento < CURRENT_DATE))
            AND (e.status = 'ativo' OR e.status IS NULL)
        ORDER BY 
            dias_atraso DESC, c.nome ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$parcelas_atraso = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $parcelas_atraso[] = $row;
    }
}

// Calcular totais
$total_pendente = 0;
$total_clientes_atraso = 0;
$clientes_unicos = [];
$emprestimos_unicos = [];

foreach ($parcelas_atraso as $parcela) {
    // Calcular valor pendente considerando valor_pago
    $valor_pendente = floatval($parcela['valor_parcela']) - floatval($parcela['valor_pago']);
    $total_pendente += $valor_pendente;
    
    // Contar clientes únicos
    if (!in_array($parcela['nome_cliente'], $clientes_unicos)) {
        $clientes_unicos[] = $parcela['nome_cliente'];
        $total_clientes_atraso++;
    }
    
    // Contar empréstimos únicos
    if (!in_array($parcela['id_emprestimo'], $emprestimos_unicos)) {
        $emprestimos_unicos[] = $parcela['id_emprestimo'];
    }
}

$total_emprestimos_atraso = count($emprestimos_unicos);
?>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Parcelas em Atraso</h1>
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
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Pendente</h5>
                        <h3 class="mb-0">R$ <?= number_format($total_pendente, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Parcelas em Atraso</h5>
                        <h3 class="mb-0"><?= count($parcelas_atraso) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Clientes em Atraso</h5>
                        <h3 class="mb-0"><?= $total_clientes_atraso ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Empréstimos Afetados</h5>
                        <h3 class="mb-0"><?= $total_emprestimos_atraso ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Parcelas em Atraso -->
        <?php if (count($parcelas_atraso) > 0): ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Telefone</th>
                                    <th>ID Emp.</th>
                                    <th>Parcela</th>
                                    <th>Valor</th>
                                    <th>Pago</th>
                                    <th>Pendente</th>
                                    <th>Status</th>
                                    <th>Vencimento</th>
                                    <th>Dias em Atraso</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parcelas_atraso as $parcela): 
                                    $valor_pendente = floatval($parcela['valor_parcela']) - floatval($parcela['valor_pago']);
                                    
                                    // Determinar a classe de cor adequada para o status
                                    if ($parcela['status'] === 'atrasado') {
                                        $status_class = 'danger';
                                    } elseif ($parcela['status'] === 'parcial') {
                                        $status_class = 'info';
                                    } else {
                                        $status_class = 'secondary';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($parcela['nome_cliente']) ?></td>
                                    <td><?= htmlspecialchars($parcela['telefone_cliente']) ?></td>
                                    <td><?= $parcela['id_emprestimo'] ?></td>
                                    <td><?= $parcela['numero_parcela'] ?></td>
                                    <td>R$ <?= number_format($parcela['valor_parcela'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($parcela['valor_pago'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($valor_pendente, 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= ucfirst($parcela['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($parcela['vencimento'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $parcela['dias_atraso'] > 30 ? 'danger' : ($parcela['dias_atraso'] > 15 ? 'warning' : 'info') ?>">
                                            <?= $parcela['dias_atraso'] ?> dias
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>emprestimos/visualizar.php?id=<?= $parcela['id_emprestimo'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                Não há parcelas em atraso no momento.
            </div>
        <?php endif; ?>
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
            const filename = 'parcelas_atraso_' + new Date().toISOString().slice(0, 10) + '.csv';
            
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
                doc.text('Parcelas em Atraso', 14, 15);
                
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
                        // Para células com badges ou outros elementos HTML
                        rowData.push(td.innerText.replace(/\n/g, ' '));
                    });
                    tableData.push(rowData);
                });
                
                // Adicionar informações resumidas
                doc.setFontSize(12);
                doc.text('Informações Resumidas:', 14, 30);
                
                // Capturar valores dos cards
                const totalPendente = document.querySelector('.row.mb-4 .bg-danger .mb-0').innerText;
                const totalParcelas = document.querySelector('.row.mb-4 .bg-warning .mb-0').innerText;
                const totalClientes = document.querySelector('.row.mb-4 .bg-info .mb-0').innerText;
                
                // Adicionar estatísticas
                doc.setFontSize(10);
                const estatisticas = [
                    ['Total Pendente:', totalPendente],
                    ['Parcelas em Atraso:', totalParcelas],
                    ['Clientes em Atraso:', totalClientes]
                ];
                
                // Adicionar estatísticas como tabela
                doc.autoTable({
                    startY: 35,
                    head: [['Métrica', 'Valor']],
                    body: estatisticas,
                    theme: 'grid',
                    headStyles: { fillColor: [220, 53, 69] }, // vermelho para atraso
                    margin: { left: 14 },
                    tableWidth: 100
                });
                
                // Adicionar a tabela principal
                doc.autoTable({
                    startY: doc.lastAutoTable.finalY + 10,
                    head: [headerRow],
                    body: tableData,
                    theme: 'grid',
                    headStyles: { fillColor: [220, 53, 69] },
                    styles: { fontSize: 7 }, // fonte menor para caber todas as colunas
                    margin: { left: 14, right: 14 }
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
                doc.save('parcelas_atraso_' + new Date().toISOString().slice(0, 10) + '.pdf');
            }
        }
    </script>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 