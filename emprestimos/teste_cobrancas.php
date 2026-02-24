<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/queries.php';

// Habilita exibição de todos os erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Estilo básico para melhor visualização
echo '<style>
    body { font-family: monospace; padding: 20px; }
    .emprestimo { 
        background: #f8f9fa; 
        border: 1px solid #dee2e6; 
        padding: 15px; 
        margin: 10px 0; 
        border-radius: 4px;
    }
    .alert {
        padding: 15px;
        margin: 10px 0;
        border-radius: 4px;
    }
    .alert-info { background: #cce5ff; border: 1px solid #b8daff; }
    .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; }
    .json-data {
        white-space: pre-wrap; 
        background: #f0f0f0; 
        padding: 10px;
        font-size: 12px;
        max-height: 300px;
        overflow-y: auto;
    }
    .status-pago { color: green; }
    .status-parcial { color: blue; }
    .status-pendente { color: orange; }
    .status-atrasado { color: red; }
</style>';

// Busca todos os empréstimos
echo '<h2>Lista de Empréstimos</h2>';

// Usando a função do arquivo queries.php
$emprestimos = buscarTodosEmprestimosComCliente($conn);

if (empty($emprestimos)) {
    echo '<div class="alert alert-danger">';
    echo 'Nenhum empréstimo encontrado ou erro na consulta';
    echo '</div>';
    exit;
}

echo '<div class="alert alert-info">';
echo 'Total de empréstimos encontrados: ' . count($emprestimos);
echo '</div>';

foreach ($emprestimos as $emprestimo) {
    echo '<div class="emprestimo">';
    echo '<h3>Empréstimo #' . $emprestimo['id'] . '</h3>';
    echo '<strong>Cliente:</strong> ' . $emprestimo['cliente_nome'] . '<br>';
    echo '<strong>Valor Emprestado:</strong> R$ ' . number_format($emprestimo['valor_emprestado'], 2, ',', '.') . '<br>';
    echo '<strong>Data:</strong> ' . date('d/m/Y', strtotime($emprestimo['data_emprestimo'])) . '<br>';
    echo '<strong>Status:</strong> ' . $emprestimo['status'] . '<br>';
    echo '<strong>Total Parcelas:</strong> ' . $emprestimo['total_parcelas'] . ' (Pagas: ' . $emprestimo['parcelas_pagas'] . ')<br>';
    
    echo '<hr>';
    
    // Exibe parcelas
    $parcelas = json_decode($emprestimo['json_parcelas'], true);
    
    if (is_array($parcelas)) {
        echo '<h4>Parcelas</h4>';
        echo '<table width="100%" border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse;">';
        echo '<tr>';
        echo '<th>Nº</th>';
        echo '<th>Valor</th>';
        echo '<th>Vencimento</th>';
        echo '<th>Status</th>';
        echo '<th>Valor Pago</th>';
        echo '</tr>';
        
        foreach ($parcelas as $p) {
            // Define status das parcelas
            $status_class = 'status-pendente';
            if ($p['status'] === 'pago') {
                $status_class = 'status-pago';
            } elseif ($p['status'] === 'parcial') {
                $status_class = 'status-parcial';
            } else {
                // Verificar se está atrasada
                $vencimento = new DateTime($p['vencimento']);
                $hoje = new DateTime();
                $ontem = clone $hoje;
                $ontem->modify('-1 day');
                
                if ($vencimento < $ontem) {
                    $status_class = 'status-atrasado';
                }
            }
            
            echo '<tr>';
            echo '<td>' . $p['numero'] . '</td>';
            echo '<td>R$ ' . number_format(floatval($p['valor']), 2, ',', '.') . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($p['vencimento'])) . '</td>';
            echo '<td class="' . $status_class . '">' . ucfirst($p['status']) . '</td>';
            echo '<td>' . ($p['status'] !== 'pendente' ? 'R$ ' . number_format(floatval($p['valor_pago']), 2, ',', '.') : '-') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '<hr>';
    echo '<strong>JSON Parcelas:</strong>';
    echo '<pre class="json-data">' . 
        htmlspecialchars($emprestimo['json_parcelas']) . 
        '</pre>';
    echo '</div>';
} 