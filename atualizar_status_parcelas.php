<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/conexao.php';

// Desativa o limite de tempo de execução
set_time_limit(0);

// Array para armazenar estatísticas
$stats = [
    'total_verificadas' => 0,
    'atualizadas_para_pendente' => 0,
    'atualizadas_para_atrasado' => 0,
    'erros' => []
];

try {
    // Primeiro, vamos ver a distribuição atual dos status
    $sql_distribuicao = "SELECT status, COUNT(*) as total FROM parcelas GROUP BY status";
    $result = $conn->query($sql_distribuicao);
    
    echo "Distribuição atual dos status:\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['status']}: {$row['total']}\n";
    }
    echo "\n";

    // 1. Atualizar parcelas que estão como 'atrasado' mas vencem hoje para 'pendente'
    $sql_update_pendentes = "
        UPDATE parcelas 
        SET status = 'pendente' 
        WHERE status = 'atrasado' 
        AND DATE(vencimento) = CURRENT_DATE
        AND status != 'pago'";
    
    $stmt = $conn->prepare($sql_update_pendentes);
    $stmt->execute();
    $stats['atualizadas_para_pendente'] = $stmt->affected_rows;

    // 2. Atualizar parcelas que deveriam estar atrasadas
    $sql_update_atrasadas = "
        UPDATE parcelas 
        SET status = 'atrasado' 
        WHERE status IN ('pendente', 'parcial') 
        AND vencimento < CURRENT_DATE
        AND status != 'pago'";
    
    $stmt = $conn->prepare($sql_update_atrasadas);
    $stmt->execute();
    $stats['atualizadas_para_atrasado'] = $stmt->affected_rows;

    // Verificar a nova distribuição
    $result = $conn->query($sql_distribuicao);
    
    echo "Nova distribuição dos status:\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['status']}: {$row['total']}\n";
    }
    
    echo "\nResumo das atualizações:\n";
    echo "Parcelas atualizadas para pendente: {$stats['atualizadas_para_pendente']}\n";
    echo "Parcelas atualizadas para atrasado: {$stats['atualizadas_para_atrasado']}\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    $stats['erros'][] = $e->getMessage();
}

// Exibir erros se houver
if (!empty($stats['erros'])) {
    echo "\nErros encontrados:\n";
    foreach ($stats['erros'] as $erro) {
        echo "- $erro\n";
    }
}