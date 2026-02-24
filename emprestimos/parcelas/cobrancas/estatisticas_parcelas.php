<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/autenticacao.php';
require_once __DIR__ . '/../../../includes/conexao.php';

// Query para buscar todas as estatísticas de uma vez
$sql = "
    SELECT 
        COUNT(*) as total_parcelas,
        SUM(valor) as total_valor,
        SUM(CASE WHEN status = 'pendente' OR status = 'parcial' THEN 1 ELSE 0 END) as total_pendentes,
        SUM(CASE WHEN status = 'pendente' OR status = 'parcial' THEN valor ELSE 0 END) as valor_pendente,
        SUM(CASE WHEN status = 'atrasado' THEN 1 ELSE 0 END) as total_atrasadas,
        SUM(CASE WHEN status = 'atrasado' THEN valor ELSE 0 END) as valor_atrasado,
        SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as total_pagas,
        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as valor_pago,
        SUM(CASE WHEN status = 'parcial' THEN valor_pago ELSE 0 END) as valor_pago_parcial
    FROM parcelas";

$result = $conn->query($sql);
$stats = $result->fetch_assoc();

// Ajusta os valores para incluir pagamentos parciais
$stats['valor_pago'] += $stats['valor_pago_parcial'];
unset($stats['valor_pago_parcial']);

// Formata os valores monetários
foreach (['total_valor', 'valor_pendente', 'valor_atrasado', 'valor_pago'] as $campo) {
    $stats[$campo] = number_format($stats[$campo], 2, ',', '.');
}

// Retorna os dados em formato JSON
header('Content-Type: application/json');
echo json_encode($stats); 