<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/queries_feriados.php';

// Configurar cabeçalhos para resposta JSON
header('Content-Type: application/json');

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método de requisição inválido'
    ]);
    exit;
}

// Obter as datas de início e fim
$data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS);
$data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS);

// Validar as datas
if (!$data_inicio || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio) || 
    !$data_fim || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Formato de data inválido. Use YYYY-MM-DD.'
    ]);
    exit;
}

// Verificar se a data fim é posterior à data início
if (strtotime($data_fim) < strtotime($data_inicio)) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'A data final deve ser posterior à data inicial.'
    ]);
    exit;
}

// Buscar os feriados do período
$feriados_para_evitar = [];

// Preparar a consulta SQL
$sql = "SELECT * FROM feriados WHERE 
        (data BETWEEN ? AND ?) AND 
        evitar = 'sim_evitar'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $data_inicio, $data_fim);
$stmt->execute();
$resultado = $stmt->get_result();

while ($feriado = $resultado->fetch_assoc()) {
    // Adicionar ao array de feriados formatado por data como chave
    $data_formatada = $feriado['data'];
    $feriados_para_evitar[$data_formatada] = $feriado;
}

// Agora, buscar feriados fixos anteriores ao período que possam se repetir
$ano_inicio = date('Y', strtotime($data_inicio));
$ano_fim = date('Y', strtotime($data_fim));

// Para cada ano do período
for ($ano = $ano_inicio; $ano <= $ano_fim; $ano++) {
    // Buscar feriados fixos
    $sql_fixos = "SELECT * FROM feriados WHERE 
            tipo = 'fixo' AND 
            evitar = 'sim_evitar' AND
            ano < ?";
    
    $stmt_fixos = $conn->prepare($sql_fixos);
    $stmt_fixos->bind_param("i", $ano);
    $stmt_fixos->execute();
    $resultado_fixos = $stmt_fixos->get_result();
    
    while ($feriado_fixo = $resultado_fixos->fetch_assoc()) {
        // Extrair mês e dia do feriado fixo
        $data_feriado = new DateTime($feriado_fixo['data']);
        $mes = $data_feriado->format('m');
        $dia = $data_feriado->format('d');
        
        // Criar a data para este ano
        $nova_data = "{$ano}-{$mes}-{$dia}";
        
        // Verificar se está dentro do período
        if (strtotime($nova_data) >= strtotime($data_inicio) && 
            strtotime($nova_data) <= strtotime($data_fim)) {
            
            // Clonar o feriado fixo para este ano
            $feriado_fixo_novo = $feriado_fixo;
            $feriado_fixo_novo['data'] = $nova_data;
            $feriado_fixo_novo['ano'] = $ano;
            
            // Adicionar ao array de feriados
            $feriados_para_evitar[$nova_data] = $feriado_fixo_novo;
        }
    }
}

echo json_encode([
    'sucesso' => true,
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim,
    'feriados' => $feriados_para_evitar
]);
exit; 