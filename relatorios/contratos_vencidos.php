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
            const filename = 'contratos_vencidos_' + new Date().toISOString().slice(0, 10) + '.csv';
            
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
                doc.text('Contratos Vencidos', 14, 15);
                
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
                
                // Capturar valores totais
                const totalContratos = tableData.length;
                let totalValor = 0;
                
                // Supondo que o valor está na 4ª coluna (índice 3) da tabela
                tableData.forEach(row => {
                    const valorStr = row[3] ? row[3].replace('R$', '').replace('.', '').replace(',', '.').trim() : '0';
                    const valor = parseFloat(valorStr) || 0;
                    totalValor += valor;
                });
                
                // Adicionar estatísticas
                doc.setFontSize(10);
                const estatisticas = [
                    ['Total de Contratos Vencidos:', totalContratos],
                    ['Valor Total:', 'R$ ' + totalValor.toLocaleString('pt-BR', {minimumFractionDigits: 2})]
                ];
                
                // Adicionar estatísticas como tabela
                doc.autoTable({
                    startY: 35,
                    head: [['Métrica', 'Valor']],
                    body: estatisticas,
                    theme: 'grid',
                    headStyles: { fillColor: [220, 53, 69] }, // vermelho para contratos vencidos
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
                doc.save('contratos_vencidos_' + new Date().toISOString().slice(0, 10) + '.pdf');
            }
        }
    </script>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 