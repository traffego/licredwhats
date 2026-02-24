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
    .alert {
        padding: 15px;
        margin: 10px 0;
        border-radius: 4px;
    }
    .alert-info { background: #cce5ff; border: 1px solid #b8daff; }
    .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        padding: 8px;
        text-align: left;
        border: 1px solid #ddd;
    }
    th {
        background-color: #f2f2f2;
    }
    tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .status-pago { color: green; }
    .status-parcial { color: blue; }
    .status-pendente { color: orange; }
    .status-atrasado { color: red; }
</style>';

// Busca todos os empréstimos
echo '<h2>Lista de Parcelas Pendentes por Cliente</h2>';

// Usando a função do arquivo queries.php
$emprestimos = buscarTodosEmprestimosComCliente($conn);

if (empty($emprestimos)) {
    echo '<div class="alert alert-danger">';
    echo 'Nenhum empréstimo encontrado ou erro na consulta';
    echo '</div>';
    exit;
}

// Array para armazenar todas as parcelas
$todas_parcelas = [];

// Processa cada empréstimo
foreach ($emprestimos as $emprestimo) {
    $parcelas = json_decode($emprestimo['json_parcelas'], true);
    
    if (is_array($parcelas)) {
        foreach ($parcelas as $parcela) {
            // Pula parcelas com status "pago"
            if ($parcela['status'] === 'pago') {
                continue;
            }
            
            // Define status das parcelas
            $status_display = $parcela['status'];
            $status_class = 'status-pendente';
            
            if ($parcela['status'] === 'parcial') {
                $status_class = 'status-parcial';
            } else {
                // Verificar se está atrasada
                $vencimento = new DateTime($parcela['vencimento']);
                $hoje = new DateTime();
                
                if ($vencimento < $hoje) {
                    $status_class = 'status-atrasado';
                    $status_display = 'atrasado';
                }
            }
            
            $todas_parcelas[] = [
                'cliente_nome' => $emprestimo['cliente_nome'],
                'emprestimo_id' => $emprestimo['id'],
                'parcela_numero' => $parcela['numero'],
                'valor' => floatval($parcela['valor']),
                'valor_pago' => isset($parcela['valor_pago']) ? floatval($parcela['valor_pago']) : 0,
                'vencimento' => $parcela['vencimento'],
                'vencimento_formatado' => date('d/m/Y', strtotime($parcela['vencimento'])),
                'status' => $status_display,
                'status_class' => $status_class
            ];
        }
    }
}

// Ordena as parcelas por data de vencimento
usort($todas_parcelas, function($a, $b) {
    return strtotime($a['vencimento']) - strtotime($b['vencimento']);
});

echo '<div class="alert alert-info">';
echo 'Total de parcelas pendentes encontradas: ' . count($todas_parcelas);
echo '</div>';

// Exibe a tabela de parcelas
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>Cliente</th>';
echo '<th>Empréstimo</th>';
echo '<th>Nº Parcela</th>';
echo '<th>Valor</th>';
echo '<th>Vencimento</th>';
echo '<th>Status</th>';
echo '<th>Valor Pago</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($todas_parcelas as $parcela) {
    echo '<tr>';
    echo '<td>' . $parcela['cliente_nome'] . '</td>';
    echo '<td>#' . $parcela['emprestimo_id'] . '</td>';
    echo '<td>' . $parcela['parcela_numero'] . '</td>';
    echo '<td>R$ ' . number_format($parcela['valor'], 2, ',', '.') . '</td>';
    echo '<td>' . $parcela['vencimento_formatado'] . '</td>';
    echo '<td class="' . $parcela['status_class'] . '">' . ucfirst($parcela['status']) . '</td>';
    
    if ($parcela['status'] === 'pago' || $parcela['status'] === 'parcial') {
        echo '<td>R$ ' . number_format($parcela['valor_pago'], 2, ',', '.') . '</td>';
    } else {
        echo '<td>-</td>';
    }
    
    echo '</tr>';
}

echo '</tbody>';
echo '</table>'; 