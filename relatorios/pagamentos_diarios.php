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

// Consulta para buscar pagamentos dos últimos 30 dias
$sql = "SELECT 
            DATE(p.data_pagamento) AS data_pagamento,
            SUM(p.valor_pago) AS total_recebido,
            COUNT(p.id) AS quantidade_pagamentos
        FROM 
            parcelas p
        WHERE 
            p.data_pagamento IS NOT NULL
            AND p.data_pagamento >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            AND p.status IN ('pago', 'parcial')
        GROUP BY 
            DATE(p.data_pagamento)
        ORDER BY 
            data_pagamento DESC";

$result = $conn->query($sql);
$pagamentos_diarios = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pagamentos_diarios[] = $row;
    }
}

// Preparar dados para o gráfico
$datas = [];
$valores = [];
$total_periodo = 0;
$total_pagamentos = 0;
$media_diaria = 0;

foreach ($pagamentos_diarios as $pagamento) {
    $datas[] = date('d/m/Y', strtotime($pagamento['data_pagamento']));
    $valores[] = floatval($pagamento['total_recebido']);
    $total_periodo += floatval($pagamento['total_recebido']);
    $total_pagamentos += intval($pagamento['quantidade_pagamentos']);
}

if (count($pagamentos_diarios) > 0) {
    $media_diaria = $total_periodo / count($pagamentos_diarios);
}

// Converter para JSON para usar no gráfico
$datas_json = json_encode($datas);
$valores_json = json_encode($valores);
?>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Pagamentos Recebidos por Dia</h1>
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
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Recebido</h5>
                        <h3 class="mb-0">R$ <?= number_format($total_periodo, 2, ',', '.') ?></h3>
                        <small>Últimos 30 dias</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Média Diária</h5>
                        <h3 class="mb-0">R$ <?= number_format($media_diaria, 2, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total de Pagamentos</h5>
                        <h3 class="mb-0"><?= $total_pagamentos ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico -->
        <?php if (count($pagamentos_diarios) > 0): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Evolução de Pagamentos</h5>
                    <canvas id="graficoRecebimentos" height="100"></canvas>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Tabela de Pagamentos -->
        <div class="card">
            <div class="card-body">
                <?php if (count($pagamentos_diarios) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Quantidade de Pagamentos</th>
                                    <th>Total Recebido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagamentos_diarios as $pagamento): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($pagamento['data_pagamento'])) ?></td>
                                    <td><?= $pagamento['quantidade_pagamentos'] ?></td>
                                    <td>R$ <?= number_format($pagamento['total_recebido'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>Total no Período</th>
                                    <th><?= $total_pagamentos ?></th>
                                    <th>R$ <?= number_format($total_periodo, 2, ',', '.') ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Não foram encontrados pagamentos nos últimos 30 dias.
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
            @page {
                size: portrait;
            }
        }
    </style>
    
    <?php if (count($pagamentos_diarios) > 0): ?>
    <!-- Script para o gráfico -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('graficoRecebimentos').getContext('2d');
            
            const datas = <?= $datas_json ?>;
            const valores = <?= $valores_json ?>;
            
            // Inverte arrays para mostrar evolução cronológica
            datas.reverse();
            valores.reverse();
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: datas,
                    datasets: [{
                        label: 'Total Recebido (R$)',
                        data: valores,
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `R$ ${context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
    
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
            const filename = 'pagamentos_diarios_' + new Date().toISOString().slice(0, 10) + '.csv';
            
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
                doc.text('Pagamentos Diários', 14, 15);
                
                // Adicionar data do relatório
                doc.setFontSize(10);
                const dataFiltro = document.getElementById('data').value || 'Todos';
                doc.text('Data do relatório: ' + new Date().toLocaleDateString('pt-BR'), 14, 22);
                doc.text('Filtro aplicado: ' + dataFiltro, 14, 27);
                
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
                doc.text('Informações Resumidas:', 14, 35);
                
                // Capturar valor total recebido
                const totalRecebido = document.querySelector('.card .card-body h4').innerText;
                
                // Adicionar estatísticas
                doc.setFontSize(10);
                const estatisticas = [
                    ['Total Recebido:', totalRecebido],
                    ['Quantidade de Pagamentos:', tableData.length]
                ];
                
                // Adicionar estatísticas como tabela
                doc.autoTable({
                    startY: 40,
                    head: [['Métrica', 'Valor']],
                    body: estatisticas,
                    theme: 'grid',
                    headStyles: { fillColor: [40, 167, 69] }, // verde para pagamentos
                    margin: { left: 14 },
                    tableWidth: 100
                });
                
                // Adicionar a tabela principal
                doc.autoTable({
                    startY: doc.lastAutoTable.finalY + 10,
                    head: [headerRow],
                    body: tableData,
                    theme: 'grid',
                    headStyles: { fillColor: [40, 167, 69] },
                    styles: { fontSize: 8 },
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
                doc.save('pagamentos_diarios_' + new Date().toISOString().slice(0, 10) + '.pdf');
            }
        }
    </script>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 